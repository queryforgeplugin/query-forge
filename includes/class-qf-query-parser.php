<?php
/**
 * Query Parser Class
 *
 * Translates JSON schema into WP_Query arguments and executes queries.
 *
 * @package Query_Forge
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

/**
 * Query Parser Class
 */
class QF_Query_Parser {

	/**
	 * Active join filters (for cleanup)
	 * Format: ['filter_id' => ['table' => '...', 'alias' => '...', 'priority' => 10]]
	 *
	 * @var array
	 */
	private static $active_join_filters = [];

	/**
	 * Active where filters (for cleanup)
	 * Format: ['filter_id' => ['callback' => callable, 'priority' => 10]]
	 *
	 * @var array
	 */
	private static $active_where_filters = [];

	/**
	 * Run ID for the current get_query() call; used so posts_where only applies to our WP_Query.
	 *
	 * @var string|null
	 */
	private static $current_where_run_id = null;


	/**
	 * Allowed table names whitelist (for security)
	 * Add custom tables here as needed.
	 *
	 * @var array
	 */
	private static $allowed_tables = [
		'users',
		'usermeta',
		'posts',
		'postmeta',
		'terms',
		'term_taxonomy',
		'term_relationships',
		'termmeta',
		'comments',
		'commentmeta',
		// Add more as needed.
	];

	/**
	 * Get WP_Query object from JSON schema
	 *
	 * @since 1.0.0
	 * @param string $json_data JSON schema string.
	 * @return \WP_Query|QF_Query_Result_Wrapper Query object or wrapper for non-post queries.
	 * @throws \Exception If query parsing fails.
	 */
	public static function get_query( $json_data ) {
		// Detect if we're in Elementor preview/editor mode or on frontend.
		$is_preview = false;
		if ( class_exists( '\Elementor\Plugin' ) && \Elementor\Plugin::$instance->editor && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			$is_preview = true;
		}

		$data = json_decode( $json_data, true );

		if ( ! $data || empty( $data ) ) {
			return self::get_empty_query();
		}

		// Broken pathway = no output. If no complete path from Source to Target, return nothing.
		if ( ! empty( $data['no_output'] ) ) {
			return self::get_empty_query();
		}

		try {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['paged'] is a public pagination parameter, not form data.
			$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

			$query_args = self::build_query_args( $data );
			$source_type = $query_args['_qf_source_type'] ?? 'post_type';

			if ( ! empty( $query_args['post_type'] ) && 'post_type' === $source_type ) {
				$user_set_post_status = self::query_contains_post_status_filter( isset( $data['query'] ) ? $data['query'] : null );
				if ( ! $user_set_post_status ) {
					if ( $is_preview ) {
						$query_args['post_status'] = [ 'publish', 'draft', 'pending', 'future', 'private', 'acf-disabled' ];
					} else {
						$query_args['post_status'] = [ 'publish', 'private' ];
					}
					$query_args['suppress_filters'] = false;
				}
			}

			$query_args = self::resolve_dynamic_values( $query_args );
			unset( $query_args['_qf_source_type'] );

			$joins = isset( $data['joins'] ) && is_array( $data['joins'] ) ? $data['joins'] : [];
			self::add_join_filters( $joins );

			if ( 'post_type' !== $source_type ) {
				self::remove_join_filters();
				self::remove_where_filters();
				return self::get_empty_query();
			}

			$query_node = isset( $data['query'] ) ? $data['query'] : null;
			$has_query  = ! empty( $query_node ) && is_array( $query_node ) && (
				( isset( $query_node['filter'] ) && is_array( $query_node['filter'] ) ) ||
				( isset( $query_node['pipeline'] ) && is_array( $query_node['pipeline'] ) && count( $query_node['pipeline'] ) > 0 ) ||
				( ! empty( $query_node['logic'] ) && is_array( $query_node['logic'] ) && ! empty( $query_node['logic']['branches'] ) && is_array( $query_node['logic']['branches'] ) ) ||
				( isset( $query_node['paths'] ) && is_array( $query_node['paths'] ) && count( $query_node['paths'] ) > 0 )
			);

			if ( ! $has_query ) {
				// query absent or empty: no restriction — plain WP_Query with source + target pagination.
				$query_args['paged'] = $paged;
				$query = new \WP_Query( $query_args );
				self::remove_join_filters();
				self::remove_where_filters();
				return $query;
			}

			$post_ids = self::execute_query( $query_node, $query_args, null );

			$final_args = $query_args;
			unset( $final_args['_qf_where_filters'] );
			$final_args['post__in'] = ! empty( $post_ids ) ? array_map( 'absint', $post_ids ) : [ 0 ];
			$final_args['paged']   = $paged;

			$query = new \WP_Query( $final_args );

			self::remove_join_filters();
			self::remove_where_filters();
			return $query;
		} catch ( \Exception $e ) {
			// SECURITY FIX: Ensure filters are removed even on error.
			self::remove_join_filters();
			self::remove_where_filters();

			// Return empty query.
			return self::get_empty_query();
		}
	}

	/**
	 * Build query arguments from schema
	 *
	 * @since 1.0.0
	 * @param array $data Schema data.
	 * @return array Query arguments array.
	 */
	private static function build_query_args( $data ) {
		$args = [
			'post_type'      => 'post',
			'posts_per_page' => 10,
			'orderby'        => 'date',
			'order'          => 'DESC',
		];

		// Single source: schema has one source object.
		$source = isset( $data['source'] ) && is_array( $data['source'] ) ? $data['source'] : null;
		$source_type = 'post_type';
		if ( $source && ! empty( $source['type'] ) ) {
			$source_type = sanitize_text_field( $source['type'] );
		}

		if ( 'post_type' === $source_type && $source ) {
			$post_type = 'post';
			if ( ! empty( $source['data'] ) && is_array( $source['data'] ) ) {
				$sd = $source['data'];
				if ( ! empty( $sd['postType'] ) ) {
					$post_type = sanitize_text_field( $sd['postType'] );
				} elseif ( ! empty( $sd['sourceType'] ) ) {
					$st = sanitize_text_field( $sd['sourceType'] );
					if ( 'posts' === $st ) {
						$post_type = 'post';
					} elseif ( 'pages' === $st ) {
						$post_type = 'page';
					} elseif ( 'cpts' === $st && ! empty( $sd['postType'] ) ) {
						$post_type = sanitize_text_field( $sd['postType'] );
					}
				}
			} elseif ( ! empty( $source['value'] ) ) {
				$post_type = sanitize_text_field( $source['value'] );
			}
			$args['post_type'] = $post_type;
		}

		$args['_qf_source_type'] = $source_type;

		// Set query parameters from target node settings.
		if ( ! empty( $data['target'] ) ) {
			if ( ! empty( $data['target']['posts_per_page'] ) ) {
				$args['posts_per_page'] = absint( $data['target']['posts_per_page'] );
			}
			
			// Handle sorting from Sort nodes (or fallback to target defaults).
			if ( ! empty( $data['target']['orderby'] ) ) {
				$orderby = sanitize_text_field( $data['target']['orderby'] );
				
				// Map field names to WP_Query orderby values.
				$orderby_map = [
					'ID'             => 'ID',
					'title'          => 'title',
					'date'           => 'date',
					'menu_order'     => 'menu_order',
					'rand'           => 'rand',
					// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta value ordering is a core feature of the query builder.
					'meta_value'     => 'meta_value',
					'meta_value_num' => 'meta_value_num',
				];
				
				$allowed_sorts = [ 'ID', 'title', 'date' ];
				if ( ! in_array( $orderby, $allowed_sorts, true ) ) {
					$orderby = 'date';
				}
				
				$args['orderby'] = isset( $orderby_map[ $orderby ] ) ? $orderby_map[ $orderby ] : $orderby;
				
				// For meta_value and meta_value_num, check if meta_key is provided
				if ( 'meta_value' === $args['orderby'] || 'meta_value_num' === $args['orderby'] ) {
					// Check if meta_key is stored directly in target (for primary sort)
					if ( ! empty( $data['target']['meta_key'] ) ) {
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Meta key ordering is a core feature of the query builder.
						$args['meta_key'] = sanitize_text_field( $data['target']['meta_key'] );
					}
					// Fallback: check first sort node if sorts array exists
					elseif ( ! empty( $data['target']['sorts'] ) && is_array( $data['target']['sorts'] ) && ! empty( $data['target']['sorts'][0]['meta_key'] ) ) {
						// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Meta key ordering is a core feature of the query builder.
						$args['meta_key'] = sanitize_text_field( $data['target']['sorts'][0]['meta_key'] );
			}
				}
			}
			
			if ( ! empty( $data['target']['order'] ) ) {
				$args['order'] = strtoupper( sanitize_text_field( $data['target']['order'] ) ) === 'ASC' ? 'ASC' : 'DESC';
			}
			
			// Handle multiple sorts (if present).
			if ( ! empty( $data['target']['sorts'] ) && is_array( $data['target']['sorts'] ) ) {
				// WP_Query supports array for orderby with multiple fields.
				$orderby_array = [ $args['orderby'] => $args['order'] ];
				
				foreach ( $data['target']['sorts'] as $sort ) {
					if ( ! empty( $sort['field'] ) ) {
						$field = sanitize_text_field( $sort['field'] );
						$direction = ! empty( $sort['direction'] ) ? strtoupper( sanitize_text_field( $sort['direction'] ) ) : 'DESC';
						
						$orderby_map = [
							'ID'             => 'ID',
							'title'          => 'title',
							'date'           => 'date',
							'menu_order'     => 'menu_order',
							'rand'           => 'rand',
							// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Meta value ordering is a core feature of the query builder.
							'meta_value'     => 'meta_value',
							'meta_value_num' => 'meta_value_num',
						];
						
						if ( isset( $orderby_map[ $field ] ) ) {
							$orderby_value = $orderby_map[ $field ];
							
							$allowed_sorts = [ 'ID', 'title', 'date' ];
							if ( ! in_array( $orderby_value, $allowed_sorts, true ) ) {
								$orderby_value = 'date';
							}
							
							// For meta_value and meta_value_num, we need to set meta_key
							if ( ( 'meta_value' === $orderby_value || 'meta_value_num' === $orderby_value ) && ! empty( $sort['meta_key'] ) ) {
								// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Meta key ordering is a core feature of the query builder.
								$args['meta_key'] = sanitize_text_field( $sort['meta_key'] );
							}
							
							$orderby_array[ $orderby_value ] = $direction === 'ASC' ? 'ASC' : 'DESC';
						}
					}
				}
				
				$args['orderby'] = $orderby_array;
			}
		}

		// Query (filter/pipeline/logic) is executed by execute_query() in get_query(); not applied here.

		// Build tax_query from filters.
		if ( ! empty( $data['tax_filters'] ) && is_array( $data['tax_filters'] ) ) {
			$tax_query = self::build_tax_query( $data['tax_filters'] );
			if ( ! empty( $tax_query ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Tax queries are a core feature of the query builder.
				$args['tax_query'] = $tax_query;
			}
		}

		// Handle inclusion/exclusion settings.
		// NOTE: Must be processed AFTER filters are applied to ensure proper query construction.
		// IMPORTANT: Resolve dynamic values BEFORE parsing ID lists.
		if ( ! empty( $data['include_exclude'] ) && is_array( $data['include_exclude'] ) ) {
			$include_exclude = self::resolve_dynamic_values( $data['include_exclude'] );

			// Post IDs to include.
			if ( ! empty( $include_exclude['post__in'] ) ) {
				$post_in_value = $include_exclude['post__in'];
				// Handle both string and array (if already resolved to array).
				if ( is_array( $post_in_value ) ) {
					$post_in_ids = array_map( 'absint', $post_in_value );
				} else {
					$post_in_ids = wp_parse_id_list( $post_in_value );
				}
				if ( ! empty( $post_in_ids ) ) {
					$args['post__in'] = $post_in_ids;
				}
			}

			// Post IDs to exclude.
			if ( ! empty( $include_exclude['post__not_in'] ) ) {
				$post_not_in_value = $include_exclude['post__not_in'];
				// Handle both string and array (if already resolved to array).
				if ( is_array( $post_not_in_value ) ) {
					$post_not_in_ids = array_map( 'absint', $post_not_in_value );
				} else {
					$post_not_in_ids = wp_parse_id_list( $post_not_in_value );
				}
				if ( ! empty( $post_not_in_ids ) ) {
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Exclusion is a core feature of the query builder, used when explicitly configured by user.
					$args['post__not_in'] = $post_not_in_ids;
				}
			}

			// Ignore sticky posts.
			if ( isset( $include_exclude['ignore_sticky_posts'] ) ) {
				$args['ignore_sticky_posts'] = (bool) $include_exclude['ignore_sticky_posts'];
			} else {
				// Default to true to prevent sticky posts from breaking the grid.
				$args['ignore_sticky_posts'] = true;
			}

			// Author IDs to include.
			if ( ! empty( $include_exclude['author__in'] ) ) {
				$author_in_value = $include_exclude['author__in'];
				if ( is_array( $author_in_value ) ) {
					$args['author__in'] = array_map( 'absint', $author_in_value );
				} else {
					$args['author__in'] = wp_parse_id_list( $author_in_value );
				}
			}

			// Author IDs to exclude.
			if ( ! empty( $include_exclude['author__not_in'] ) ) {
				$author_not_in_value = $include_exclude['author__not_in'];
				if ( is_array( $author_not_in_value ) ) {
					$args['author__not_in'] = array_map( 'absint', $author_not_in_value );
				} else {
					$args['author__not_in'] = wp_parse_id_list( $author_not_in_value );
				}
			}
		} else {
			// Default: ignore sticky posts if no include_exclude settings.
			$args['ignore_sticky_posts'] = true;
		}

		return $args;
	}

	/**
	 * Check if the query tree contains a post_status filter (for default post_status override).
	 *
	 * @param array|null $query_node Query node (filter, pipeline, or logic).
	 * @return bool True if any filter in the tree has field post_status.
	 */
	private static function query_contains_post_status_filter( $query_node ) {
		if ( empty( $query_node ) || ! is_array( $query_node ) ) {
			return false;
		}
		if ( ! empty( $query_node['filter'] ) && is_array( $query_node['filter'] ) ) {
			$field = isset( $query_node['filter']['field'] ) ? $query_node['filter']['field'] : '';
			if ( 'post_status' === $field ) {
				return true;
			}
		}
		if ( ! empty( $query_node['pipeline'] ) && is_array( $query_node['pipeline'] ) ) {
			foreach ( $query_node['pipeline'] as $step ) {
				if ( self::query_contains_post_status_filter( $step ) ) {
					return true;
				}
			}
		}
		if ( ! empty( $query_node['logic']['branches'] ) && is_array( $query_node['logic']['branches'] ) ) {
			foreach ( $query_node['logic']['branches'] as $branch ) {
				if ( self::query_contains_post_status_filter( $branch ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Execute a query node (filter, pipeline, or logic) and return post IDs.
	 * Empty pipeline/branches = no restriction: run one WP_Query with base args and return IDs.
	 *
	 * @param array|null $query_node Query node: { filter }, { pipeline: [ steps ] }, or { logic: { relation, branches } }.
	 * @param array      $base_args  Base WP_Query args (post_type, post_status, etc.).
	 * @param array|null $post_in    Optional. Restrict to these post IDs. null = no restriction. Empty array = return [].
	 * @return array Post IDs (never null).
	 */
	private static function execute_query( $query_node, $base_args, $post_in = null ) {
		if ( is_array( $post_in ) && empty( $post_in ) ) {
			return [];
		}

		// No node or empty: no restriction — run one WP_Query with base args (and post_in if provided).
		if ( empty( $query_node ) || ! is_array( $query_node ) ) {
			return self::run_query_for_ids( $base_args, $post_in );
		}

		// Single filter.
		if ( ! empty( $query_node['filter'] ) && is_array( $query_node['filter'] ) ) {
			return self::run_filter_for_ids( $query_node['filter'], $base_args, $post_in );
		}

		// Pipeline: sequential steps, each narrows the result.
		if ( array_key_exists( 'pipeline', $query_node ) && is_array( $query_node['pipeline'] ) ) {
			$steps = $query_node['pipeline'];
			if ( empty( $steps ) ) {
				return self::run_query_for_ids( $base_args, $post_in );
			}
			$current_ids = null;
			foreach ( $steps as $step ) {
				if ( ! empty( $step['include_exclude'] ) && is_array( $step['include_exclude'] ) ) {
					if ( null === $current_ids ) {
						$current_ids = self::run_query_for_ids( $base_args, $post_in );
					}
					$current_ids = self::apply_include_exclude_to_ids( $current_ids, $step['include_exclude'] );
				} else {
					$current_ids = self::execute_query( $step, $base_args, $current_ids );
				}
				if ( empty( $current_ids ) ) {
					return [];
				}
			}
			return $current_ids;
		}

		// Logic: branches in parallel, combined by relation.
		if ( ! empty( $query_node['logic'] ) && is_array( $query_node['logic'] ) ) {
			$relation = isset( $query_node['logic']['relation'] ) ? strtoupper( sanitize_text_field( $query_node['logic']['relation'] ) ) : 'AND';
			$branches = isset( $query_node['logic']['branches'] ) && is_array( $query_node['logic']['branches'] ) ? $query_node['logic']['branches'] : [];
			if ( empty( $branches ) ) {
				return self::run_query_for_ids( $base_args, $post_in );
			}
			$branch_results = [];
			foreach ( $branches as $branch ) {
				$branch_results[] = self::execute_query( $branch, $base_args, $post_in );
			}
			return self::combine_logic_ids( $relation, $branch_results );
		}

		// Paths: multiple independent paths (no Logic node). Execute each, merge IDs with array_unique(array_merge(...)).
		if ( isset( $query_node['paths'] ) && is_array( $query_node['paths'] ) && ! empty( $query_node['paths'] ) ) {
			$path_results = [];
			foreach ( $query_node['paths'] as $path ) {
				$path_results[] = self::execute_query( $path, $base_args, null );
			}
			$merged = [];
			foreach ( $path_results as $ids ) {
				$merged = array_merge( $merged, is_array( $ids ) ? $ids : [] );
			}
			$merged = array_values( array_unique( $merged ) );
			return $merged;
		}

		// Unknown shape: no restriction.
		return self::run_query_for_ids( $base_args, $post_in );
	}

	/**
	 * Run a single WP_Query with base args (and optional post__in) and return post IDs.
	 *
	 * @param array      $base_args Base args (post_type, post_status, etc.).
	 * @param array|null $post_in   Optional. Restrict to these IDs.
	 * @return array Post IDs.
	 */
	private static function run_query_for_ids( $base_args, $post_in = null ) {
		$args = array_merge( $base_args, [
			'no_found_rows'     => true,
			'posts_per_page'    => -1,
			'fields'            => 'ids',
			'suppress_filters'  => false,
		] );
		unset( $args['paged'] );
		if ( is_array( $post_in ) && ! empty( $post_in ) ) {
			$args['post__in'] = array_map( 'absint', $post_in );
		}
		$query = new \WP_Query( $args );
		$ids = $query->posts;
		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Run a single filter and return post IDs.
	 *
	 * @param array      $filter    Single filter object (field, operator, value, value_type).
	 * @param array      $base_args Base WP_Query args.
	 * @param array|null $post_in   Optional. Restrict to these IDs.
	 * @return array Post IDs.
	 */
	private static function run_filter_for_ids( $filter, $base_args, $post_in = null ) {
		$args = array_merge( [], $base_args );
		$args['no_found_rows']  = true;
		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';
		$args['suppress_filters'] = false;
		unset( $args['paged'] );
		if ( is_array( $post_in ) && ! empty( $post_in ) ) {
			$args['post__in'] = array_map( 'absint', $post_in );
		}

		$field = isset( $filter['field'] ) ? sanitize_text_field( $filter['field'] ) : '';
		if ( empty( $field ) ) {
			return self::run_query_for_ids( $base_args, $post_in );
		}

		// Fix: If the filter operator requires a value but the value is missing/empty,
		// drop this filter node (do not block the query).
		$operator = isset( $filter['operator'] ) ? strtoupper( sanitize_text_field( $filter['operator'] ) ) : '=';
		$requires_value_ops = [ '=', '!=', 'LIKE', 'NOT LIKE' ];
		$value_provided = array_key_exists( 'value', $filter ) && $filter['value'] !== '';
		if ( in_array( $operator, $requires_value_ops, true ) && ! $value_provided ) {
			return self::run_query_for_ids( $base_args, $post_in );
		}

		if ( self::is_standard_field( $field ) ) {
			self::apply_standard_filters( $args, [ 'clauses' => [ $filter ] ] );
		} elseif ( strpos( $field, 'tax:' ) === 0 ) {
			$taxonomy = sanitize_key( substr( $field, 4 ) );

			if ( ! taxonomy_exists( $taxonomy ) ) {
				return self::run_query_for_ids( $base_args, $post_in );
			}

			$raw_value = isset( $filter['value'] ) ? $filter['value'] : '';
			$terms     = array_filter( array_map( 'trim', explode( ',', $raw_value ) ) );

			if ( empty( $terms ) ) {
				return self::run_query_for_ids( $base_args, $post_in );
			}

			$all_numeric = array_reduce(
				$terms,
				function( $carry, $term ) {
					return $carry && is_numeric( $term );
				},
				true
			);
			$term_field = $all_numeric ? 'term_id' : 'slug';
			if ( $all_numeric ) {
				$terms = array_map( 'absint', $terms );
			} else {
				$terms = array_map( 'sanitize_text_field', $terms );
			}

			$allowed_tax_ops = [ 'IN', 'NOT IN', 'AND' ];
			$wp_operator     = in_array( $operator, $allowed_tax_ops, true ) ? $operator : 'IN';

			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Tax queries are a core feature of the query builder.
			$args['tax_query'] = [
				'relation' => 'AND',
				[
					'taxonomy' => $taxonomy,
					'field'    => $term_field,
					'terms'    => $terms,
					'operator' => $wp_operator,
				],
			];

			$query = new \WP_Query( $args );
			self::remove_where_filters();

			$ids = $query->posts;
			return is_array( $ids ) ? $ids : [];
		} else {
			$meta_clause = self::build_meta_clause( $filter );
			if ( ! empty( $meta_clause ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Meta queries are a core feature of the query builder.
				$args['meta_query'] = [ 'relation' => 'AND', $meta_clause ];
			}
		}

		if ( ! empty( $args['_qf_where_filters'] ) ) {
			self::$current_where_run_id = uniqid( 'qf_where_', true );
			$args['_qf_where_run_id']   = self::$current_where_run_id;
			self::add_where_filters( $args['_qf_where_filters'] );
			unset( $args['_qf_where_filters'] );
		}

		$query = new \WP_Query( $args );
		self::remove_where_filters();

		$ids = $query->posts;
		return is_array( $ids ) ? $ids : [];
	}

	/**
	 * Apply include_exclude to an array of post IDs (intersect, subtract, author filter).
	 *
	 * @param array $ids            Post IDs.
	 * @param array $include_exclude Keys: post__in, post__not_in, author__in, author__not_in, ignore_sticky_posts.
	 * @return array Filtered post IDs.
	 */
	private static function apply_include_exclude_to_ids( $ids, $include_exclude ) {
		if ( ! is_array( $ids ) || ! is_array( $include_exclude ) ) {
			return $ids;
		}
		$resolved = self::resolve_dynamic_values( $include_exclude );
		if ( ! empty( $resolved['post__in'] ) ) {
			$post_in = is_array( $resolved['post__in'] ) ? array_map( 'absint', $resolved['post__in'] ) : wp_parse_id_list( $resolved['post__in'] );
			if ( ! empty( $post_in ) ) {
				$ids = array_intersect( $ids, $post_in );
			}
		}
		if ( ! empty( $resolved['post__not_in'] ) ) {
			$post_not_in = is_array( $resolved['post__not_in'] ) ? array_map( 'absint', $resolved['post__not_in'] ) : wp_parse_id_list( $resolved['post__not_in'] );
			if ( ! empty( $post_not_in ) ) {
				$ids = array_diff( $ids, $post_not_in );
			}
		}
		if ( ! empty( $resolved['author__in'] ) || ! empty( $resolved['author__not_in'] ) ) {
			$keep = [];
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( ! $post ) {
					continue;
				}
				$author = (int) $post->post_author;
				if ( ! empty( $resolved['author__not_in'] ) ) {
					$author_not_in = is_array( $resolved['author__not_in'] ) ? array_map( 'absint', $resolved['author__not_in'] ) : wp_parse_id_list( $resolved['author__not_in'] );
					if ( in_array( $author, $author_not_in, true ) ) {
						continue;
					}
				}
				if ( ! empty( $resolved['author__in'] ) ) {
					$author_in = is_array( $resolved['author__in'] ) ? array_map( 'absint', $resolved['author__in'] ) : wp_parse_id_list( $resolved['author__in'] );
					if ( ! in_array( $author, $author_in, true ) ) {
						continue;
					}
				}
				$keep[] = $id;
			}
			$ids = $keep;
		}
		return array_values( $ids );
	}

	/**
	 * Combine branch ID arrays by relation (AND, OR, UNION, UNION ALL).
	 *
	 * @param string $relation       AND | OR | UNION | UNION ALL.
	 * @param array  $branch_results Array of arrays of post IDs.
	 * @return array Combined post IDs.
	 */
	private static function combine_logic_ids( $relation, $branch_results ) {
		$branch_results = array_filter( $branch_results, 'is_array' );
		if ( empty( $branch_results ) ) {
			return [];
		}
		if ( 'AND' === $relation ) {
			$out = array_shift( $branch_results );
			foreach ( $branch_results as $br ) {
				$out = array_intersect( $out, $br );
			}
			return array_values( $out );
		}
		if ( 'OR' === $relation || 'UNION' === $relation ) {
			$merged = [];
			foreach ( $branch_results as $br ) {
				$merged = array_merge( $merged, $br );
			}
			return array_values( array_unique( $merged ) );
		}
		if ( 'UNION ALL' === $relation ) {
			$merged = [];
			foreach ( $branch_results as $br ) {
				$merged = array_merge( $merged, $br );
			}
			return array_values( $merged );
		}
		// Default: AND.
		$out = array_shift( $branch_results );
		foreach ( $branch_results as $br ) {
			$out = array_intersect( $out, $br );
		}
		return array_values( $out );
	}

	/**
	 * Build single meta clause
	 *
	 * @since 1.0.0
	 * @param array $clause Clause data.
	 * @return array|null Meta clause array or null.
	 */
	private static function build_meta_clause( $clause ) {
		$field = sanitize_text_field( $clause['field'] );
		if ( empty( $field ) ) {
			return null;
		}

		$operator = ! empty( $clause['operator'] ) ? strtoupper( sanitize_text_field( $clause['operator'] ) ) : '=';
		
		$allowed_operators = [ '=', '!=', 'LIKE' ];
		if ( ! in_array( $operator, $allowed_operators, true ) ) {
			$operator = '=';
		}

		$meta_clause = [
			'key'     => $field,
			'compare' => $operator,
		];

		// Handle value type.
		if ( ! empty( $clause['value_type'] ) ) {
			$type = strtoupper( sanitize_text_field( $clause['value_type'] ) );
			$allowed_types = [ 'CHAR', 'NUMERIC', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'BINARY' ];
			if ( in_array( $type, $allowed_types, true ) ) {
				$meta_clause['type'] = $type;
			}
		}

		// Handle value (skip for EXISTS/NOT EXISTS).
		if ( ! in_array( $operator, [ 'EXISTS', 'NOT EXISTS' ], true ) ) {
			if ( isset( $clause['value'] ) ) {
				if ( in_array( $operator, [ 'IN', 'NOT IN', 'BETWEEN' ], true ) && is_array( $clause['value'] ) ) {
					$meta_clause['value'] = array_map( 'sanitize_text_field', $clause['value'] );
				} else {
					$meta_clause['value'] = sanitize_text_field( $clause['value'] );
				}
			}
		}

		return $meta_clause;
	}

	/**
	 * Check if a field is a standard WordPress post field
	 *
	 * @since 1.0.0
	 * @param string $field Field name.
	 * @return bool True if standard field, false otherwise.
	 */
	private static function is_standard_field( $field ) {
		$standard_fields = [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_date',
			'post_modified',
			'post_author',
			'post_name',
			'post_status',
			'comment_count',
		];
		return in_array( $field, $standard_fields, true );
	}

	/**
	 * Apply standard field filters to WP_Query args
	 *
	 * @param array $args WP_Query arguments (passed by reference).
	 * @param array $filters Standard field filters.
	 */
	private static function apply_standard_filters( &$args, $filters ) {
		if ( empty( $filters['clauses'] ) || ! is_array( $filters['clauses'] ) ) {
			return;
		}

		// Unified where filters: title, content, excerpt (each adds AND condition; all combined with AND).
		if ( ! isset( $args['_qf_where_filters'] ) ) {
			$args['_qf_where_filters'] = [];
		}

		foreach ( $filters['clauses'] as $clause ) {
			if ( isset( $clause['relation'] ) && ! empty( $clause['clauses'] ) ) {
				continue;
			}

			if ( ! is_array( $clause ) || empty( $clause['field'] ) ) {
				continue;
			}

			$field = sanitize_text_field( $clause['field'] );
			$operator = ! empty( $clause['operator'] ) ? strtoupper( sanitize_text_field( $clause['operator'] ) ) : '=';
			$value = isset( $clause['value'] ) ? $clause['value'] : '';

			switch ( $field ) {
				case 'post_title':
					// Route through _qf_where_filters so only post_title is searched (not s, which searches title OR excerpt OR content).
					$args['_qf_where_filters'][] = [
						'field'    => 'post_title',
						'operator' => $operator,
						'value'    => sanitize_text_field( $value ),
					];
					break;

				case 'post_content':
					// Apply as AND on post_content only.
					$args['_qf_where_filters'][] = [
						'field'    => 'post_content',
						'operator' => $operator,
						'value'    => $value,
					];
					break;

				case 'post_excerpt':
					// Apply as AND on post_excerpt only.
					$args['_qf_where_filters'][] = [
						'field'    => 'post_excerpt',
						'operator' => $operator,
						'value'    => $value,
					];
					break;

				case 'post_date':
				case 'post_modified':
					// Use date_query.
					if ( ! isset( $args['date_query'] ) ) {
						$args['date_query'] = [];
					}
					$date_query = [
						'column' => $field === 'post_date' ? 'post_date' : 'post_modified',
					];
					if ( $operator === '=' ) {
						$date_query['year'] = gmdate( 'Y', strtotime( $value ) );
						$date_query['month'] = gmdate( 'm', strtotime( $value ) );
						$date_query['day'] = gmdate( 'd', strtotime( $value ) );
					} elseif ( $operator === '>=' ) {
						$date_query['after'] = $value;
					} elseif ( $operator === '<=' ) {
						$date_query['before'] = $value;
					} elseif ( $operator === '>' ) {
						$date_query['after'] = $value;
						$date_query['inclusive'] = false;
					} elseif ( $operator === '<' ) {
						$date_query['before'] = $value;
						$date_query['inclusive'] = false;
					}
					$args['date_query'][] = $date_query;
					break;

				case 'post_author':
					// Use 'author' parameter.
					if ( $operator === '=' ) {
						$args['author'] = absint( $value );
					} elseif ( $operator === '!=' ) {
						$args['author__not_in'] = wp_parse_id_list( $value );
					} elseif ( $operator === 'IN' ) {
						$args['author__in'] = wp_parse_id_list( $value );
					} elseif ( $operator === 'NOT IN' ) {
						$args['author__not_in'] = wp_parse_id_list( $value );
					}
					break;

				case 'post_status':
					// Use 'post_status' parameter.
					if ( $operator === '=' ) {
						$args['post_status'] = sanitize_text_field( $value );
					}
					break;

				case 'comment_count':
					// Use 'comment_count' parameter (requires custom handling via posts_where).
					// For now, we'll handle this in a filter hook.
					if ( $operator === '=' ) {
						$args['comment_count'] = absint( $value );
					} elseif ( $operator === '>=' ) {
						$args['comment_count'] = absint( $value );
						$args['comment_count_compare'] = '>=';
					} elseif ( $operator === '<=' ) {
						$args['comment_count'] = absint( $value );
						$args['comment_count_compare'] = '<=';
					}
					break;
			}
		}
	}

	/**
	 * Build tax_query from filter clauses
	 *
	 * @since 1.0.0
	 * @param array $filters Filter structure.
	 * @return array Taxonomy query array.
	 */
	private static function build_tax_query( $filters ) {
		$tax_query = [];

		$tax_query['relation'] = 'AND';

		if ( ! empty( $filters['clauses'] ) && is_array( $filters['clauses'] ) ) {
			foreach ( $filters['clauses'] as $clause ) {
				if ( ! empty( $clause['taxonomy'] ) && ! empty( $clause['terms'] ) ) {
					$tax_clause = [
						'taxonomy' => sanitize_text_field( $clause['taxonomy'] ),
						'field'    => ! empty( $clause['field'] ) ? sanitize_text_field( $clause['field'] ) : 'term_id',
						'terms'    => is_array( $clause['terms'] ) ? array_map( 'intval', $clause['terms'] ) : [ intval( $clause['terms'] ) ],
						'operator' => ! empty( $clause['operator'] ) ? strtoupper( sanitize_text_field( $clause['operator'] ) ) : 'IN',
					];
					$tax_query[] = $tax_clause;
				}
			}
		}

		return count( $tax_query ) > 1 ? $tax_query : [];
	}

	/**
	 * Add join filters to posts_join
	 *
	 * @since 1.0.0
	 * @param array $joins Join definitions.
	 */
	private static function add_join_filters( $joins ) {
		foreach ( $joins as $join ) {
			if ( empty( $join['table'] ) || empty( $join['on'] ) ) {
				continue;
			}

			// SECURITY: Strictly whitelist table names - only alphanumeric and underscores.
			$table = preg_replace( '/[^a-zA-Z0-9_]/', '', sanitize_text_field( $join['table'] ) );
			if ( empty( $table ) ) {
				continue; // Skip if table name is invalid.
			}

			// Optional: Check against whitelist of known safe tables.
			// Uncomment the following lines if you want strict whitelist enforcement:
			// if ( ! in_array( $table, self::$allowed_tables, true ) ) {
			// 	continue; // Skip unknown tables.
			// }

			// SECURITY: Strictly whitelist alias, left, and right column names.
			$alias = ! empty( $join['alias'] ) ? preg_replace( '/[^a-zA-Z0-9_]/', '', sanitize_text_field( $join['alias'] ) ) : $table;
			$left  = preg_replace( '/[^a-zA-Z0-9_]/', '', sanitize_text_field( $join['on']['left'] ?? 'ID' ) );
			$right = preg_replace( '/[^a-zA-Z0-9_]/', '', sanitize_text_field( $join['on']['right'] ?? 'post_id' ) );

			if ( empty( $alias ) || empty( $left ) || empty( $right ) ) {
				continue; // Skip if required fields are invalid.
			}

			// Create unique filter ID.
			$filter_id = 'qf_join_' . md5( $table . $alias . $left . $right );

			// SECURITY FIX: Use named class method instead of anonymous closure so we can remove it.
			// Store filter data for the named method.
			self::$active_join_filters[ $filter_id ] = [
				'table'   => $table,
				'alias'   => $alias,
				'left'    => $left,
				'right'   => $right,
				'priority' => 10,
			];

			// Add filter using named class method (only add once).
			if ( ! has_filter( 'posts_join', [ self::class, 'modify_join_sql' ] ) ) {
				add_filter( 'posts_join', [ self::class, 'modify_join_sql' ], 10, 1 );
			}
		}
	}

	/**
	 * Modify JOIN SQL for custom table joins
	 * Named method so it can be removed properly.
	 *
	 * @since 1.0.0
	 * @param string $join_sql Current JOIN SQL.
	 * @return string Modified JOIN SQL.
	 */
	public static function modify_join_sql( $join_sql ) {
		global $wpdb;

		// Apply all active join filters.
		foreach ( self::$active_join_filters as $filter_data ) {
			$table = $filter_data['table'];
			$alias  = $filter_data['alias'];
			$left   = $filter_data['left'];
			$right  = $filter_data['right'];

			// SECURITY: Double-check table/alias names are safe (defense in depth).
			$table = preg_replace( '/[^a-zA-Z0-9_]/', '', $table );
			$alias  = preg_replace( '/[^a-zA-Z0-9_]/', '', $alias );
			$left   = preg_replace( '/[^a-zA-Z0-9_]/', '', $left );
			$right  = preg_replace( '/[^a-zA-Z0-9_]/', '', $right );

			if ( empty( $table ) || empty( $alias ) || empty( $left ) || empty( $right ) ) {
				continue; // Skip invalid entries.
			}

			// Build safe JOIN clause.
			// Note: $wpdb->prepare doesn't work for table/column names, so we rely on regex whitelisting above.
			// The table/alias/column names are already sanitized via preg_replace.
			$join_sql .= " LEFT JOIN {$wpdb->prefix}{$table} AS {$alias} ON {$wpdb->posts}.{$left} = {$alias}.{$right} ";
		}

		return $join_sql;
	}

	/**
	 * Remove all active join filters
	 * SECURITY FIX: Now properly removes filters using named class method.
	 *
	 * @since 1.0.0
	 */
	private static function remove_join_filters() {
		// Remove the filter using the named class method.
		if ( ! empty( self::$active_join_filters ) ) {
			remove_filter( 'posts_join', [ self::class, 'modify_join_sql' ], 10 );
			// Clear active filters.
		self::$active_join_filters = [];
		}
	}

	/**
	 * Add where filters for post_title, post_content, post_excerpt (each AND'd).
	 *
	 * @since 1.0.0
	 * @param array $where_filters Array of filter definitions with 'field', 'operator', 'value'.
	 */
	private static function add_where_filters( $where_filters ) {
		if ( empty( $where_filters ) || ! is_array( $where_filters ) ) {
			return;
		}

		$allowed_fields = [ 'post_title', 'post_content', 'post_excerpt' ];
		foreach ( $where_filters as $filter ) {
			if ( empty( $filter['operator'] ) || ! isset( $filter['value'] ) ) {
				continue;
			}
			$field = isset( $filter['field'] ) ? $filter['field'] : 'post_content';
			if ( ! in_array( $field, $allowed_fields, true ) ) {
				continue;
			}

			$filter_id = 'qf_where_' . md5( $field . $filter['operator'] . $filter['value'] );
			self::$active_where_filters[ $filter_id ] = [
				'field'    => $field,
				'operator' => $filter['operator'],
				'value'    => $filter['value'],
			];
		}

		if ( ! empty( self::$active_where_filters ) && ! has_filter( 'posts_where', [ self::class, 'modify_where_sql' ] ) ) {
			add_filter( 'posts_where', [ self::class, 'modify_where_sql' ], 10, 2 );
		}
	}

	/**
	 * Modify WHERE SQL for post_title, post_content, post_excerpt (each AND'd).
	 * Only applies when the query is our get_query() run.
	 *
	 * @since 1.0.0
	 * @param string    $where_sql Current WHERE SQL.
	 * @param \WP_Query $query     The WP_Query instance (optional).
	 * @return string Modified WHERE SQL.
	 */
	public static function modify_where_sql( $where_sql, $query = null ) {
		global $wpdb;

		if ( $query instanceof \WP_Query && ( self::$current_where_run_id === null || $query->get( '_qf_where_run_id' ) !== self::$current_where_run_id ) ) {
			return $where_sql;
		}

		$column_whitelist = [ 'post_title' => 'post_title', 'post_content' => 'post_content', 'post_excerpt' => 'post_excerpt' ];
		foreach ( self::$active_where_filters as $filter_data ) {
			$field    = isset( $filter_data['field'] ) ? $filter_data['field'] : 'post_content';
			$operator = strtoupper( sanitize_text_field( $filter_data['operator'] ) );
			$value    = $filter_data['value'];
			if ( ! isset( $column_whitelist[ $field ] ) ) {
				continue;
			}
			$column = $column_whitelist[ $field ];

			if ( '=' === $operator ) {
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.{$column} = %s", $value );
			} elseif ( 'LIKE' === $operator ) {
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.{$column} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
			} elseif ( '!=' === $operator || '<>' === $operator ) {
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.{$column} != %s", $value );
			} elseif ( 'NOT LIKE' === $operator ) {
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.{$column} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
			}
		}

		return $where_sql;
	}

	/**
	 * Remove all active where filters
	 *
	 * @since 1.0.0
	 */
	private static function remove_where_filters() {
		// Remove the filter using the named class method.
		if ( ! empty( self::$active_where_filters ) ) {
			remove_filter( 'posts_where', [ self::class, 'modify_where_sql' ], 10 );
			// Clear active filters and run id.
			self::$active_where_filters = [];
			self::$current_where_run_id = null;
		}
	}


	/**
	 * Get empty query (no results)
	 *
	 * @since 1.0.0
	 * @return \WP_Query Empty query object.
	 */
	private static function get_empty_query() {
		return new \WP_Query(
			[
				'post__in' => [ 0 ],
			]
		);
	}

	/**
	 * Resolve dynamic values (magic tags) in query arguments
	 *
	 * Recursively scans the query array and replaces {{ tag_name:optional_arg }} patterns
	 * with their resolved values. Handles arrays returned by tags (e.g., current_post_terms).
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments array.
	 * @return array Query arguments with resolved dynamic values.
	 */
	private static function resolve_dynamic_values( $args ) {
		if ( ! is_array( $args ) ) {
			// If not an array, check if it's a string with dynamic tags.
			if ( is_string( $args ) ) {
				$resolved = self::resolve_string_value( $args );
				// If tag returned an array (e.g., current_post_terms), convert to wp_parse_id_list format.
				if ( is_array( $resolved ) ) {
					return $resolved;
				}
				return $resolved;
			}
			return $args;
		}

		$resolved = [];
		// Keys that should NOT be processed here (already processed in build_query_args).
		$skip_keys = [ 'post__in', 'post__not_in', 'author__in', 'author__not_in' ];
		
		foreach ( $args as $key => $value ) {
			// Skip ID list fields that are already arrays (they've been processed in build_query_args).
			if ( in_array( $key, $skip_keys, true ) && is_array( $value ) ) {
				$resolved[ $key ] = $value;
				continue;
			}
			
			if ( is_array( $value ) ) {
				// Recursively process nested arrays (meta_query, tax_query, etc.).
				$resolved[ $key ] = self::resolve_dynamic_values( $value );
			} elseif ( is_string( $value ) ) {
				// Process string values for dynamic tags.
				$resolved_value = self::resolve_string_value( $value );
				$resolved[ $key ] = $resolved_value;
			} else {
				// Pass through other types (int, bool, etc.) as-is.
				$resolved[ $key ] = $value;
			}
		}

		return $resolved;
	}

	/**
	 * Resolve dynamic tags in a string value
	 *
	 * @since 1.0.0
	 * @param string $value String value that may contain dynamic tags.
	 * @return string|int|mixed Resolved value.
	 */
	private static function resolve_string_value( $value ) {
		// Pattern to match {{ tag_name:optional_arg }} or {{ tag_name }}.
		$pattern = '/\{\{\s*([a-z_]+)(?::([^}]+))?\s*\}\}/i';

		if ( ! preg_match( $pattern, $value, $matches ) ) {
			// No dynamic tags found, return as-is.
			return $value;
		}

		// Return the literal string including {{ tags }}.
		return $value;
	}

	/**
	 * Resolve a specific dynamic tag
	 *
	 * @since 1.0.0
	 * @param string $tag_name Tag name (e.g., 'current_user_id').
	 * @param string $tag_arg Optional argument (e.g., 'KEY' for url_param:KEY).
	 * @return string|int|array Resolved value.
	 */
	private static function resolve_tag( $tag_name, $tag_arg = '' ) {
		switch ( $tag_name ) {
			case 'current_user_id':
				$user_id = get_current_user_id();
				return $user_id > 0 ? $user_id : 0;

			case 'current_post_id':
				$post_id = get_the_ID();
				return $post_id > 0 ? $post_id : 0;

			case 'current_author_id':
				global $post;
				if ( $post && isset( $post->post_author ) ) {
					return absint( $post->post_author );
				}
				return 0;

			case 'current_date':
				return current_time( 'Y-m-d' );

			case 'url_param':
				// Intended for public URL query arguments (e.g. ?category=5). Not form data; nonce not required.
				if ( empty( $tag_arg ) ) {
					return '';
				}
				$param_key = sanitize_text_field( $tag_arg );
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- url_param is for public query args only; key and value are sanitized.
				$param_value = isset( $_GET[ $param_key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $param_key ] ) ) : '';
				return $param_value;

			case 'user_meta':
				if ( empty( $tag_arg ) ) {
					return '';
				}
				$user_id = get_current_user_id();
				if ( $user_id <= 0 ) {
					return '';
				}
				// Security: Sanitize the meta key.
				$meta_key = sanitize_text_field( $tag_arg );
				$meta_value = get_user_meta( $user_id, $meta_key, true );
				// Return as string, but handle empty values.
				return $meta_value !== false && $meta_value !== '' ? (string) $meta_value : '';

			case 'current_post_terms':
				if ( empty( $tag_arg ) ) {
					return [];
				}
				// Security: Sanitize taxonomy name.
				$taxonomy = sanitize_key( $tag_arg );
				// Validate taxonomy exists.
				if ( ! taxonomy_exists( $taxonomy ) ) {
					return [];
				}
				$post_id = get_the_ID();
				if ( $post_id <= 0 ) {
					return [];
				}
				// Use WordPress core function to get term IDs.
				$term_ids = wp_get_post_terms( $post_id, $taxonomy, [ 'fields' => 'ids' ] );
				// Return array of term IDs (or empty array on error).
				return is_wp_error( $term_ids ) ? [] : array_map( 'absint', $term_ids );

			default:
				// Unknown tag, return empty string to prevent query errors.
				return '';
		}
	}
}

