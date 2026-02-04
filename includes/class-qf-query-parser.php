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
	 * Check if PRO version is active
	 *
	 * @since 1.0.0
	 * @return bool True if PRO is active, false otherwise.
	 */
	private static function is_pro() {
		// Check for the PRO constant. Default to FALSE if not defined.
		return defined( 'QUERY_FORGE_PRO_ACTIVE' ) && QUERY_FORGE_PRO_ACTIVE;
	}

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

		try {
			// Get the current page number for pagination.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['paged'] is a public pagination parameter, not form data.
			$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
			
			// Build query args from schema.
			// Note: Dynamic values in include_exclude are resolved inside build_query_args().
			$query_args = self::build_query_args( $data );
			
			// Explicitly set post_status based on context to ensure consistency between preview and frontend.
			// Always set it explicitly to override WordPress defaults which vary by context.
			// Determine source type before it gets removed.
			$source_type = $query_args['_qf_source_type'] ?? 'post_type';
			
			if ( ! empty( $query_args['post_type'] ) && 'post_type' === $source_type ) {
				// Only override if post_status wasn't explicitly set by user in filters.
				$user_set_post_status = false;
				if ( ! empty( $data['filters']['clauses'] ) ) {
					foreach ( $data['filters']['clauses'] as $clause ) {
						if ( ! empty( $clause['field'] ) && 'post_status' === $clause['field'] ) {
							$user_set_post_status = true;
							break;
						}
					}
				}
				
				if ( ! $user_set_post_status ) {
					if ( $is_preview ) {
						// In preview/editor mode, allow all statuses that Elementor typically shows.
						$query_args['post_status'] = [ 'publish', 'draft', 'pending', 'future', 'private', 'acf-disabled' ];
					} else {
						// On frontend, only show published posts (and private if user can view them).
						// Use 'any' and then filter in SQL to ensure WordPress doesn't override our selection.
						$query_args['post_status'] = [ 'publish', 'private' ];
					}
					
					// Force WordPress to respect our post_status by suppressing default status filtering
					// This prevents WordPress from adding its own status filters based on context
					$query_args['suppress_filters'] = false; // We want our filters, but not WordPress's default status filtering
				}
			}

			// Add pagination to query args.
			$query_args['paged'] = $paged;

			// Resolve dynamic values (magic tags) in other query args (not include_exclude, already done).
			// Exclude post__in, post__not_in, author__in, author__not_in from second pass since they're already processed.
			$query_args = self::resolve_dynamic_values( $query_args );

			// Determine source type
			$source_type = $query_args['_qf_source_type'] ?? 'post_type';
			unset( $query_args['_qf_source_type'] ); // Remove from query args

			// Add join filters if needed (only for post_type queries) - PRO only.
			if ( 'post_type' === $source_type && ! empty( $data['joins'] ) && is_array( $data['joins'] ) ) {
				if ( self::is_pro() ) {
				self::add_join_filters( $data['joins'] );
				}
			}

			// Add where filters if needed (for post_content exact matching).
			if ( 'post_type' === $source_type && ! empty( $query_args['_qf_content_filters'] ) ) {
				self::add_where_filters( $query_args['_qf_content_filters'] );
				unset( $query_args['_qf_content_filters'] ); // Remove from query args
			}

			// Execute query based on source type.
			if ( 'post_type' === $source_type ) {
				// Standard WP_Query for posts.
				$query = new \WP_Query( $query_args );
				
				self::remove_join_filters();
				self::remove_where_filters();
				
				return $query;
			} elseif ( 'user' === $source_type ) {
				// Users query - PRO only.
				if ( ! self::is_pro() ) {
					// Free version: return empty query for user sources.
					$query = self::get_empty_query();
				} else {
				$query = self::get_users_query( $data, $query_args );
				}
				
				return $query;
			} elseif ( 'comment' === $source_type ) {
				// Comments query - PRO only.
				if ( ! self::is_pro() ) {
					// Free version: return empty query for comment sources.
					$query = self::get_empty_query();
				} else {
				$query = self::get_comments_query( $data, $query_args );
				}
				
				return $query;
			} elseif ( 'sql_table' === $source_type ) {
				// SQL Table query - PRO only.
				if ( ! self::is_pro() ) {
					// Free version: return empty query for SQL table sources.
					$query = self::get_empty_query();
				} else {
				$query = self::get_sql_table_query( $data, $query_args );
				}
				
				return $query;
			} elseif ( 'rest_api' === $source_type ) {
				// REST API query - PRO only.
				if ( ! self::is_pro() ) {
					// Free version: return empty query for REST API sources.
					$query = self::get_empty_query();
				} else {
				$query = self::get_rest_api_query( $data, $query_args );
				}
				
				return $query;
			}

			// Default: return empty query for unknown source types.
			self::remove_join_filters();
			self::remove_where_filters();
			return self::get_empty_query();
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
				
				// Check for PRO-only sort options and fallback to 'date' if not PRO.
				$pro_only_sorts = [ 'rand', 'menu_order', 'meta_value', 'meta_value_num' ];
				if ( ! self::is_pro() && in_array( $orderby, $pro_only_sorts, true ) ) {
					// Free version: fallback to 'date' for PRO-only sort options.
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
							
							// Check for PRO-only sort options and fallback to 'date' if not PRO.
							$pro_only_sorts = [ 'rand', 'menu_order', 'meta_value', 'meta_value_num' ];
							if ( ! self::is_pro() && in_array( $orderby_value, $pro_only_sorts, true ) ) {
								// Free version: fallback to 'date' for PRO-only sort options.
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

		// Build filters - separate standard fields and meta fields.
		if ( ! empty( $data['filters'] ) && is_array( $data['filters'] ) ) {
			// Split filters into standard and meta.
			$standard_filters = self::extract_standard_filters( $data['filters'] );
			$meta_filters = self::extract_meta_filters( $data['filters'] );

			// Apply standard field filters directly to WP_Query args.
			if ( ! empty( $standard_filters ) ) {
				self::apply_standard_filters( $args, $standard_filters );
			}

			// Build meta_query for meta fields.
			if ( ! empty( $meta_filters ) ) {
				$meta_query = self::build_meta_query( $meta_filters );
			if ( ! empty( $meta_query ) ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Meta queries are a core feature of the query builder.
				$args['meta_query'] = $meta_query;
				}
			}
		}

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
	 * Build meta_query from filter clauses
	 *
	 * @since 1.0.0
	 * @param array $filters Filter structure.
	 * @return array Meta query array.
	 */
	private static function build_meta_query( $filters ) {
		$meta_query = [];

		// Free version: enforce AND-only relation
		if ( ! self::is_pro() ) {
			$meta_query['relation'] = 'AND';
		} else {
			// Set relation (Pro allows OR).
			if ( ! empty( $filters['relation'] ) ) {
				$meta_query['relation'] = strtoupper( sanitize_text_field( $filters['relation'] ) ) === 'OR' ? 'OR' : 'AND';
			} else {
				$meta_query['relation'] = 'AND';
			}
		}

		// Process clauses.
		if ( ! empty( $filters['clauses'] ) && is_array( $filters['clauses'] ) ) {
			$clause_count = 0;
			foreach ( $filters['clauses'] as $clause ) {
				if ( ! is_array( $clause ) ) {
					continue;
				}

				// Free version: enforce single meta key only
				if ( ! self::is_pro() && $clause_count >= 1 ) {
					// Skip additional meta clauses in Free version
					continue;
				}

				// Handle nested groups (with relation) - PRO only
				if ( isset( $clause['relation'] ) && ! empty( $clause['clauses'] ) ) {
					if ( self::is_pro() ) {
						$nested = self::build_meta_query( $clause );
						if ( ! empty( $nested ) ) {
							$meta_query[] = $nested;
						}
					}
					// Free version: skip nested groups
				} elseif ( ! empty( $clause['field'] ) ) {
					// Regular filter clause.
					$meta_clause = self::build_meta_clause( $clause );
					if ( ! empty( $meta_clause ) ) {
						$meta_query[] = $meta_clause;
						$clause_count++;
					}
				}
			}
		}

		// Only return if we have clauses (more than just relation).
		return count( $meta_query ) > 1 ? $meta_query : [];
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
		
		// Free version: only allow basic operators
		if ( ! self::is_pro() ) {
			$allowed_operators = [ '=', '!=', 'LIKE' ];
			if ( ! in_array( $operator, $allowed_operators, true ) ) {
				// Fallback to '=' for disallowed operators in Free version
				$operator = '=';
			}
		} else {
			// Pro version: allow all operators
			$allowed_operators = [ '=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'EXISTS', 'NOT EXISTS' ];
			if ( ! in_array( $operator, $allowed_operators, true ) ) {
				$operator = '=';
			}
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
	 * Extract standard field filters from filter structure
	 *
	 * @since 1.0.0
	 * @param array $filters Filter structure.
	 * @return array Standard field filters.
	 */
	private static function extract_standard_filters( $filters ) {
		// Free version: enforce AND-only relation
		$standard_filters = [
			'relation' => self::is_pro() ? ( $filters['relation'] ?? 'AND' ) : 'AND',
			'clauses'  => [],
		];

		if ( ! empty( $filters['clauses'] ) && is_array( $filters['clauses'] ) ) {
			foreach ( $filters['clauses'] as $clause ) {
				if ( ! is_array( $clause ) ) {
					continue;
				}

				// Handle nested groups - PRO only
				if ( isset( $clause['relation'] ) && ! empty( $clause['clauses'] ) ) {
					if ( self::is_pro() ) {
						$nested = self::extract_standard_filters( $clause );
						if ( ! empty( $nested['clauses'] ) ) {
							$standard_filters['clauses'][] = $nested;
						}
					}
					// Free version: skip nested groups
				} elseif ( ! empty( $clause['field'] ) && self::is_standard_field( $clause['field'] ) ) {
					$standard_filters['clauses'][] = $clause;
				}
			}
		}

		return $standard_filters;
	}

	/**
	 * Extract meta field filters from filter structure
	 *
	 * @since 1.0.0
	 * @param array $filters Filter structure.
	 * @return array Meta field filters.
	 */
	private static function extract_meta_filters( $filters ) {
		// Free version: enforce AND-only relation
		$meta_filters = [
			'relation' => self::is_pro() ? ( $filters['relation'] ?? 'AND' ) : 'AND',
			'clauses'  => [],
		];

		if ( ! empty( $filters['clauses'] ) && is_array( $filters['clauses'] ) ) {
			foreach ( $filters['clauses'] as $clause ) {
				if ( ! is_array( $clause ) ) {
					continue;
				}

				// Handle nested groups - PRO only
				if ( isset( $clause['relation'] ) && ! empty( $clause['clauses'] ) ) {
					if ( self::is_pro() ) {
						$nested = self::extract_meta_filters( $clause );
						if ( ! empty( $nested['clauses'] ) ) {
							$meta_filters['clauses'][] = $nested;
						}
					}
					// Free version: skip nested groups
				} elseif ( ! empty( $clause['field'] ) && ! self::is_standard_field( $clause['field'] ) ) {
					$meta_filters['clauses'][] = $clause;
				}
			}
		}

		return $meta_filters;
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

		// Initialize content filters array if needed.
		if ( ! isset( $args['_qf_content_filters'] ) ) {
			$args['_qf_content_filters'] = [];
		}

		foreach ( $filters['clauses'] as $clause ) {
			// Handle nested groups (recursive).
			if ( isset( $clause['relation'] ) && ! empty( $clause['clauses'] ) ) {
				self::apply_standard_filters( $args, $clause );
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
					// Use 's' parameter for search, or custom title filter.
					if ( $operator === '=' || $operator === 'LIKE' ) {
						$args['s'] = sanitize_text_field( $value );
					}
					break;

				case 'post_content':
					// Don't use 's' parameter - it searches title, excerpt, AND content.
					// Instead, we'll use a posts_where filter for exact content matching.
					// Store the filter data to be processed by add_where_filters().
					// We'll skip setting args['s'] here and handle it via where filter.
					// Store in a temporary array that will be processed by add_where_filters.
					if ( ! isset( $args['_qf_content_filters'] ) ) {
						$args['_qf_content_filters'] = [];
					}
					$args['_qf_content_filters'][] = [
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

		// Free version: enforce AND-only relation
		if ( ! self::is_pro() ) {
			$tax_query['relation'] = 'AND';
		} else {
			// Pro version: allow OR
			if ( ! empty( $filters['relation'] ) ) {
				$tax_query['relation'] = strtoupper( sanitize_text_field( $filters['relation'] ) ) === 'OR' ? 'OR' : 'AND';
			} else {
				$tax_query['relation'] = 'AND';
			}
		}

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
	 * Add where filters for post_content exact matching
	 *
	 * @since 1.0.0
	 * @param array $content_filters Array of content filter definitions.
	 */
	private static function add_where_filters( $content_filters ) {
		if ( empty( $content_filters ) || ! is_array( $content_filters ) ) {
			return;
		}

		foreach ( $content_filters as $filter ) {
			if ( empty( $filter['operator'] ) || empty( $filter['value'] ) ) {
				continue;
			}

			// Create unique filter ID.
			$filter_id = 'qf_where_' . md5( $filter['operator'] . $filter['value'] );

			// Store filter data for the named method.
			self::$active_where_filters[ $filter_id ] = [
				'operator' => $filter['operator'],
				'value'    => $filter['value'],
				'priority' => 10,
			];
		}

		// Add filter using named class method (only add once).
		if ( ! empty( self::$active_where_filters ) && ! has_filter( 'posts_where', [ self::class, 'modify_where_sql' ] ) ) {
			add_filter( 'posts_where', [ self::class, 'modify_where_sql' ], 10, 1 );
		}
	}

	/**
	 * Modify WHERE SQL for post_content exact matching
	 * Named method so it can be removed properly.
	 *
	 * @since 1.0.0
	 * @param string $where_sql Current WHERE SQL.
	 * @return string Modified WHERE SQL.
	 */
	public static function modify_where_sql( $where_sql ) {
		global $wpdb;

		// Apply all active where filters.
		foreach ( self::$active_where_filters as $filter_data ) {
			$operator = strtoupper( sanitize_text_field( $filter_data['operator'] ) );
			$value    = $filter_data['value'];

			// Sanitize and escape the value for SQL.
			$escaped_value = $wpdb->prepare( '%s', $value );

			if ( '=' === $operator ) {
				// For post_content, "=" means "contains" (LIKE), not exact match.
				// This matches the old behavior where s parameter was used.
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
			} elseif ( 'LIKE' === $operator ) {
				// LIKE match: post_content LIKE '%value%'
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.post_content LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
			} elseif ( '!=' === $operator || '<>' === $operator ) {
				// Not equal: post_content != 'value'
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.post_content != %s", $value );
			} elseif ( 'NOT LIKE' === $operator ) {
				// NOT LIKE: post_content NOT LIKE '%value%'
				$where_sql .= $wpdb->prepare( " AND {$wpdb->posts}.post_content NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
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
			// Clear active filters.
			self::$active_where_filters = [];
		}
	}

	/**
	 * Get Users query
	 *
	 * @since 1.0.0
	 * @param array $data Schema data.
	 * @param array $base_args Base query arguments.
	 * @return QF_Query_Result_Wrapper Query result wrapper.
	 */
	private static function get_users_query( $data, $base_args ) {
		$user_args = [];
		
		// Pagination
		$per_page = ! empty( $base_args['posts_per_page'] ) ? absint( $base_args['posts_per_page'] ) : 10;
		$paged = ! empty( $base_args['paged'] ) ? absint( $base_args['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		
		$user_args['number'] = $per_page;
		$user_args['offset'] = $offset;
		
		// Role filter from source settings
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) && ! empty( $data['sources'][0]['role'] ) ) {
			$role = sanitize_text_field( $data['sources'][0]['role'] );
			if ( ! empty( $role ) ) {
				$user_args['role'] = $role;
			}
		} elseif ( ! empty( $data['source']['role'] ) ) {
			$role = sanitize_text_field( $data['source']['role'] );
			if ( ! empty( $role ) ) {
				$user_args['role'] = $role;
			}
		}
		
		// Ordering
		if ( ! empty( $base_args['orderby'] ) ) {
			$user_args['orderby'] = $base_args['orderby'];
		}
		if ( ! empty( $base_args['order'] ) ) {
			$user_args['order'] = $base_args['order'];
		}
		
		// Execute query
		$user_query = new \WP_User_Query( $user_args );
		$users = $user_query->get_results();
		$total = $user_query->get_total();
		
		// Convert users to post-like objects for widget compatibility
		$post_like_users = [];
		foreach ( $users as $user ) {
			$post_obj = (object) [
				'ID' => $user->ID,
				'post_title' => $user->display_name,
				'post_author' => $user->ID,
				'post_date' => $user->user_registered,
				'post_content' => $user->user_description ?? '',
				'post_excerpt' => '',
				'post_type' => 'user',
				'_qf_user' => $user, // Store original user object
			];
			$post_like_users[] = $post_obj;
		}
		
		return new QF_Query_Result_Wrapper( $post_like_users, $total, $per_page );
	}

	/**
	 * Get Comments query
	 *
	 * @since 1.0.0
	 * @param array $data Schema data.
	 * @param array $base_args Base query arguments.
	 * @return QF_Query_Result_Wrapper Query result wrapper.
	 */
	private static function get_comments_query( $data, $base_args ) {
		$comment_args = [
			'status' => 'approve',
		];
		
		// Status filter from source settings
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) && ! empty( $data['sources'][0]['status'] ) ) {
			$status = sanitize_text_field( $data['sources'][0]['status'] );
			if ( 'all' === $status ) {
				$comment_args['status'] = 'all';
			} elseif ( ! empty( $status ) ) {
				$comment_args['status'] = $status;
			}
		} elseif ( ! empty( $data['source']['status'] ) ) {
			$status = sanitize_text_field( $data['source']['status'] );
			if ( 'all' === $status ) {
				$comment_args['status'] = 'all';
			} elseif ( ! empty( $status ) ) {
				$comment_args['status'] = $status;
			}
		}
		
		// Post type filter from source settings
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) && ! empty( $data['sources'][0]['post_type'] ) ) {
			$post_type = sanitize_text_field( $data['sources'][0]['post_type'] );
			if ( ! empty( $post_type ) ) {
				// Get post IDs for this post type
				$post_ids = get_posts( [
					'post_type' => $post_type,
					'posts_per_page' => -1,
					'fields' => 'ids',
				] );
				if ( ! empty( $post_ids ) ) {
					$comment_args['post__in'] = $post_ids;
				} else {
					// No posts of this type, return empty result
					$comment_args['post__in'] = [ 0 ];
				}
			}
		} elseif ( ! empty( $data['source']['post_type'] ) ) {
			$post_type = sanitize_text_field( $data['source']['post_type'] );
			if ( ! empty( $post_type ) ) {
				$post_ids = get_posts( [
					'post_type' => $post_type,
					'posts_per_page' => -1,
					'fields' => 'ids',
				] );
				if ( ! empty( $post_ids ) ) {
					$comment_args['post__in'] = $post_ids;
				} else {
					$comment_args['post__in'] = [ 0 ];
				}
			}
		}
		
		// Pagination
		$per_page = ! empty( $base_args['posts_per_page'] ) ? absint( $base_args['posts_per_page'] ) : 10;
		$paged = ! empty( $base_args['paged'] ) ? absint( $base_args['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		
		$comment_args['number'] = $per_page;
		$comment_args['offset'] = $offset;
		
		// Ordering
		if ( ! empty( $base_args['orderby'] ) ) {
			$comment_args['orderby'] = $base_args['orderby'];
		}
		if ( ! empty( $base_args['order'] ) ) {
			$comment_args['order'] = $base_args['order'];
		}
		
		// Execute query
		$comments = get_comments( $comment_args );
		
		// Get total count based on status
		$comment_counts = wp_count_comments();
		if ( isset( $comment_args['status'] ) && 'all' !== $comment_args['status'] ) {
			$status_key = $comment_args['status'];
			if ( 'approve' === $status_key ) {
				$total = $comment_counts->approved;
			} elseif ( 'hold' === $status_key ) {
				$total = $comment_counts->moderated;
			} elseif ( 'spam' === $status_key ) {
				$total = $comment_counts->spam;
			} elseif ( 'trash' === $status_key ) {
				$total = $comment_counts->trash;
			} else {
				$total = $comment_counts->approved; // Default
			}
		} else {
			$total = $comment_counts->approved + $comment_counts->moderated; // Approximate for "all"
		}
		
		// Convert comments to post-like objects for widget compatibility
		$post_like_comments = [];
		foreach ( $comments as $comment ) {
			$post_obj = (object) [
				'ID' => $comment->comment_ID,
				'post_title' => $comment->comment_author,
				'post_author' => $comment->user_id,
				'post_date' => $comment->comment_date,
				'post_content' => $comment->comment_content,
				'post_excerpt' => wp_trim_words( $comment->comment_content, 20 ),
				'post_type' => 'comment',
				'_qf_comment' => $comment, // Store original comment object
			];
			$post_like_comments[] = $post_obj;
		}
		
		return new QF_Query_Result_Wrapper( $post_like_comments, $total, $per_page );
	}

	/**
	 * Get SQL Table query
	 *
	 * @since 1.0.0
	 * @param array $data Schema data.
	 * @param array $base_args Base query arguments.
	 * @return QF_Query_Result_Wrapper Query result wrapper.
	 */
	private static function get_sql_table_query( $data, $base_args ) {
		global $wpdb;
		
		// Get table name from source
		$table_name = '';
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) && ! empty( $data['sources'][0]['value'] ) ) {
			$table_name = sanitize_text_field( $data['sources'][0]['value'] );
		} elseif ( ! empty( $data['source']['value'] ) ) {
			$table_name = sanitize_text_field( $data['source']['value'] );
		}
		
		if ( empty( $table_name ) ) {
			return self::get_empty_query();
		}
		
		// SECURITY: Strictly whitelist table name
		$table_name = preg_replace( '/[^a-zA-Z0-9_]/', '', $table_name );
		if ( empty( $table_name ) ) {
			return self::get_empty_query();
		}
		
		$full_table_name = $wpdb->prefix . $table_name;
		
		// Pagination
		$per_page = ! empty( $base_args['posts_per_page'] ) ? absint( $base_args['posts_per_page'] ) : 10;
		$paged = ! empty( $base_args['paged'] ) ? absint( $base_args['paged'] ) : 1;
		$offset = ( $paged - 1 ) * $per_page;
		
		// Check if table exists
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table existence check requires direct query, caching not applicable.
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $full_table_name ) );
		if ( ! $table_exists ) {
			return self::get_empty_query();
		}
		
		// Get total count
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is whitelisted via regex (alphanumeric + underscore only), direct query required for custom table access, caching not applicable for dynamic queries.
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$full_table_name}" );
		
		// Get rows
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is whitelisted via regex (alphanumeric + underscore only), LIMIT/OFFSET are prepared, direct query required for custom table access, caching not applicable for dynamic queries.
		$results = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$full_table_name} LIMIT %d OFFSET %d", $per_page, $offset ) );
		
		// Convert to post-like objects
		$post_like_rows = [];
		foreach ( $results as $row ) {
			$post_obj = (object) [
				'ID' => $row->id ?? 0,
				'post_title' => $row->title ?? $row->name ?? 'Untitled',
				'post_author' => $row->author_id ?? 0,
				'post_date' => $row->created_at ?? $row->date ?? current_time( 'mysql' ),
				'post_content' => $row->content ?? $row->description ?? '',
				'post_excerpt' => wp_trim_words( $row->content ?? $row->description ?? '', 20 ),
				'post_type' => 'sql_table',
				'_qf_row' => $row, // Store original row data
			];
			$post_like_rows[] = $post_obj;
		}
		
		return new QF_Query_Result_Wrapper( $post_like_rows, $total, $per_page );
	}

	/**
	 * Get REST API query
	 *
	 * @since 1.0.0
	 * @param array $data Schema data.
	 * @param array $base_args Base query arguments.
	 * @return QF_Query_Result_Wrapper Query result wrapper.
	 */
	private static function get_rest_api_query( $data, $base_args ) {
		// Get API URL from source
		$api_url = '';
		$api_method = 'GET';
		if ( ! empty( $data['sources'] ) && is_array( $data['sources'] ) && ! empty( $data['sources'][0]['value'] ) ) {
			$api_url = esc_url_raw( $data['sources'][0]['value'] );
			$api_method = ! empty( $data['sources'][0]['method'] ) ? sanitize_text_field( $data['sources'][0]['method'] ) : 'GET';
		} elseif ( ! empty( $data['source']['value'] ) ) {
			$api_url = esc_url_raw( $data['source']['value'] );
			$api_method = ! empty( $data['source']['method'] ) ? sanitize_text_field( $data['source']['method'] ) : 'GET';
		}
		
		if ( empty( $api_url ) ) {
			return self::get_empty_query();
		}
		
		// Make API request
		$per_page = ! empty( $base_args['posts_per_page'] ) ? absint( $base_args['posts_per_page'] ) : 10;
		$paged = ! empty( $base_args['paged'] ) ? absint( $base_args['paged'] ) : 1;
		
		$request_args = [
			'method' => $api_method,
			'timeout' => 30,
			'sslverify' => false, // Allow self-signed certificates for local dev
		];
		
		// Add pagination to URL for GET requests
		if ( 'GET' === $api_method ) {
			$api_url = add_query_arg( [
				'per_page' => $per_page,
				'page' => $paged,
			], $api_url );
		}
		
		$response = wp_remote_request( $api_url, $request_args );
		
		if ( is_wp_error( $response ) ) {
			return self::get_empty_query();
		}
		
		$body = wp_remote_retrieve_body( $response );
		$json_data = json_decode( $body, true );
		
		if ( ! $json_data || ! is_array( $json_data ) ) {
			return self::get_empty_query();
		}
		
		// Handle different API response formats
		$items = [];
		if ( isset( $json_data['data'] ) && is_array( $json_data['data'] ) ) {
			$items = $json_data['data'];
		} elseif ( isset( $json_data['items'] ) && is_array( $json_data['items'] ) ) {
			$items = $json_data['items'];
		} else {
			$items = $json_data;
		}
		
		$total = isset( $json_data['total'] ) ? absint( $json_data['total'] ) : count( $items );
		
		// Convert to post-like objects
		$post_like_items = [];
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$post_obj = (object) [
				'ID' => $item['id'] ?? $item['ID'] ?? 0,
				'post_title' => $item['title'] ?? $item['name'] ?? 'Untitled',
				'post_author' => $item['author_id'] ?? $item['author'] ?? 0,
				'post_date' => $item['date'] ?? $item['created_at'] ?? current_time( 'mysql' ),
				'post_content' => $item['content'] ?? $item['description'] ?? '',
				'post_excerpt' => wp_trim_words( $item['content'] ?? $item['description'] ?? '', 20 ),
				'post_type' => 'rest_api',
				'_qf_item' => $item, // Store original item data
			];
			$post_like_items[] = $post_obj;
		}
		
		return new QF_Query_Result_Wrapper( $post_like_items, $total, $per_page );
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

		// Check if PRO is active - dynamic tags are PRO only.
		if ( ! self::is_pro() ) {
			// Free version: return raw string without processing dynamic tags.
			return $value; // Return the literal string including {{ tags }}.
		}

		$tag_name = isset( $matches[1] ) ? trim( $matches[1] ) : '';
		$tag_arg  = isset( $matches[2] ) ? trim( $matches[2] ) : '';

		// Resolve the tag.
		$resolved_value = self::resolve_tag( $tag_name, $tag_arg );

		// If the entire string is just the tag (no other text), return the resolved value directly.
		// Otherwise, replace the tag in the string.
		if ( trim( $value ) === $matches[0] ) {
			return $resolved_value;
		} else {
			// Replace all occurrences of this tag pattern in the string.
			return preg_replace( $pattern, $resolved_value, $value );
		}
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

