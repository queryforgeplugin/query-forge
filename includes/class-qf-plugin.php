<?php
/**
 * Main Plugin Class
 *
 * @package Query_Forge
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Query_Forge;

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Plugin Class
 */
final class Plugin {

	/**
	 * Instance
	 *
	 * @var Plugin The single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Instance
	 *
	 * Ensures only one instance of the class is loaded.
	 *
	 * @since 1.0.0
	 * @return Plugin An instance of the class.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->includes();
		$this->hooks();
	}

	/**
	 * Include Files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		require_once QUERY_FORGE_PATH . 'includes/class-qf-query-parser.php';
		require_once QUERY_FORGE_PATH . 'includes/class-qf-query-result-wrapper.php';
		require_once QUERY_FORGE_PATH . 'includes/class-qf-query-cache.php';
		require_once QUERY_FORGE_PATH . 'includes/class-qf-frontend-search.php';
		require_once QUERY_FORGE_PATH . 'includes/class-qf-block.php';
		require_once QUERY_FORGE_PATH . 'includes/class-qf-starter-queries.php';
	}

	/**
	 * Register Hooks
	 *
	 * @since 1.0.0
	 */
	private function hooks() {
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'enqueue_block_editor_assets', [ $this, 'enqueue_block_editor_scripts' ] );
		add_action( 'elementor/init', [ $this, 'on_elementor_init' ] );
		// Use elementor/editor/after_enqueue_scripts with very high priority to run after Elementor finishes script registration.
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ], 999 );
		add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_widget_styles' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_widget_styles' ] );
		add_action( 'wp_ajax_query_forge_get_meta_keys', [ $this, 'ajax_get_meta_keys' ] );
		add_action( 'wp_ajax_query_forge_search_terms', [ $this, 'ajax_search_terms' ] );
		add_action( 'wp_ajax_query_forge_save_query', [ $this, 'ajax_save_query' ] );
		add_action( 'wp_ajax_query_forge_get_saved_queries', [ $this, 'ajax_get_saved_queries' ] );
		add_action( 'wp_ajax_query_forge_delete_query', [ $this, 'ajax_delete_query' ] );
		add_action( 'wp_ajax_query_forge_load_more_posts', [ $this, 'ajax_load_more_posts' ] );
		add_action( 'wp_ajax_nopriv_query_forge_load_more_posts', [ $this, 'ajax_load_more_posts' ] );
		add_action( 'wp_ajax_qf_search', [ $this, 'ajax_qf_search' ] );
		add_action( 'wp_ajax_nopriv_qf_search', [ $this, 'ajax_qf_search' ] );
		add_action( 'wp_ajax_query_forge_flush_block_cache', [ $this, 'ajax_flush_block_cache' ] );
		add_action( 'wp_ajax_qf_dismiss_notice', [ $this, 'ajax_qf_dismiss_notice' ] );
		add_action( 'wp_ajax_qf_complete_onboarding', [ $this, 'ajax_qf_complete_onboarding' ] );
		add_action( 'admin_notices', [ $this, 'render_activation_admin_notice' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_notice_dismiss' ] );
		add_action( 'save_post', [ $this, 'invalidate_query_caches_on_save' ], 10, 2 );
	}

	/**
	 * On Elementor Init
	 *
	 * @since 1.0.0
	 */
	public function on_elementor_init() {
		add_action( 'elementor/elements/categories_registered', [ $this, 'register_categories' ] );
		add_action( 'elementor/widgets/register', [ $this, 'register_widgets' ] );
	}

	/**
	 * Register Custom Categories
	 *
	 * @since 1.0.0
	 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
	 */
	public function register_categories( $elements_manager ) {
		$elements_manager->add_category(
			'query-forge',
			[
				'title' => __( 'Query Forge', 'query-forge' ),
				'icon'  => 'fa fa-code-branch',
			]
		);
	}

	/**
	 * Register Widgets
	 *
	 * @since 1.0.0
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public function register_widgets( $widgets_manager ) {
		$widget_file = QUERY_FORGE_PATH . 'widgets/class-smart-loop-grid.php';
		if ( ! file_exists( $widget_file ) ) {
			return;
		}
		require_once $widget_file;
		if ( class_exists( '\Query_Forge\Widgets\Smart_Loop_Grid' ) ) {
			$widgets_manager->register( new \Query_Forge\Widgets\Smart_Loop_Grid() );
		}
	}

	/**
	 * Enqueue Editor Scripts
	 *
	 * @since 1.0.0
	 */
	public function enqueue_editor_scripts() {
		// Only enqueue in Elementor editor context.
		if ( ! \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
			return;
		}

		// Elementor provides React/ReactDOM globally, so we don't need wp-element as a dependency.
		// Removing wp-element prevents WordPress from resolving Elementor's script dependencies prematurely.
		// Using empty dependencies array to avoid triggering WordPress script dependency validation.
		wp_enqueue_script(
			'query_forge_editor',
			QUERY_FORGE_URL . 'assets/js/qf-editor.bundle.js',
			[], // No dependencies - jQuery and React are provided globally by Elementor/WordPress.
			QUERY_FORGE_VERSION,
			true
		);

		wp_localize_script(
			'query_forge_editor',
			'QueryForgeConfig',
			[
				'postTypes'       => $this->get_post_types_for_localize(),
				'userRoles'       => $this->get_user_roles_for_localize(),
				'assetsUrl'       => QUERY_FORGE_URL . 'assets/',
				'nonce'           => wp_create_nonce( 'query_forge_nonce' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'version'         => QUERY_FORGE_VERSION,
				'showOnboarding'  => ! (bool) get_option( 'qf_onboarding_complete', false ),
			]
		);

		// Enqueue bundled React Flow CSS (bundled during build process).
		wp_enqueue_style(
			'query_forge_reactflow',
			QUERY_FORGE_URL . 'assets/js/style-qf-editor.css',
			[],
			QUERY_FORGE_VERSION
		);
	}

	/**
	 * Register the Gutenberg block (style handle + block.json; editor script is enqueued separately).
	 *
	 * @since 1.3.0
	 */
	public function register_block() {
		$block = new QF_Block();
		$block->register();
	}

	/**
	 * Enqueue block editor script and shared QueryForgeConfig (same shape as Elementor editor localize).
	 *
	 * @since 1.3.0
	 */
	public function enqueue_block_editor_scripts() {
		$asset_file = QUERY_FORGE_PATH . 'assets/js/qf-block.bundle.asset.php';
		$asset      = file_exists( $asset_file ) ? include $asset_file : [ 'dependencies' => [], 'version' => QUERY_FORGE_VERSION ];

		wp_enqueue_script(
			'query_forge_block',
			QUERY_FORGE_URL . 'assets/js/qf-block.bundle.js',
			isset( $asset['dependencies'] ) ? $asset['dependencies'] : [],
			isset( $asset['version'] ) ? $asset['version'] : QUERY_FORGE_VERSION,
			true
		);

		wp_localize_script(
			'query_forge_block',
			'QueryForgeConfig',
			[
				'postTypes'       => $this->get_post_types_for_localize(),
				'userRoles'       => $this->get_user_roles_for_localize(),
				'assetsUrl'       => QUERY_FORGE_URL . 'assets/',
				'nonce'           => wp_create_nonce( 'query_forge_nonce' ),
				'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
				'version'         => QUERY_FORGE_VERSION,
				'showOnboarding'  => ! (bool) get_option( 'qf_onboarding_complete', false ),
			]
		);

		wp_enqueue_style(
			'query_forge_reactflow_block',
			QUERY_FORGE_URL . 'assets/js/style-qf-editor.css',
			[],
			QUERY_FORGE_VERSION
		);
	}

	/**
	 * Custom public post types for the query builder (exclude built-in and system types).
	 *
	 * @since 1.3.0
	 * @return array<int, array{name: string, label: string}>
	 */
	private function get_post_types_for_localize() {
		$post_type_names = get_post_types(
			[
				'public'             => true,
				'publicly_queryable' => true,
				'_builtin'           => false,
			],
			'objects'
		);

		$excluded_types = [
			'attachment',
			'revision',
			'nav_menu_item',
			'custom_css',
			'customize_changeset',
			'oembed_cache',
			'user_request',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
		];

		$post_types_array = [];
		foreach ( $post_type_names as $post_type_name => $post_type_obj ) {
			if ( in_array( $post_type_name, $excluded_types, true ) ) {
				continue;
			}
			if ( isset( $post_type_obj->_builtin ) && $post_type_obj->_builtin ) {
				continue;
			}
			$post_types_array[] = [
				'name'  => $post_type_obj->name,
				'label' => $post_type_obj->label ? $post_type_obj->label : $post_type_obj->name,
			];
		}

		usort(
			$post_types_array,
			function( $a, $b ) {
				return strcasecmp( $a['label'], $b['label'] );
			}
		);

		return $post_types_array;
	}

	/**
	 * All user roles for the query builder.
	 *
	 * @since 1.3.0
	 * @return array<int, array{key: string, label: string}>
	 */
	private function get_user_roles_for_localize() {
		global $wp_roles;
		$user_roles = [];
		if ( ! empty( $wp_roles ) && is_object( $wp_roles ) ) {
			foreach ( $wp_roles->roles as $role_key => $role_info ) {
				$user_roles[] = [
					'key'   => $role_key,
					'label' => translate_user_role( $role_info['name'] ),
				];
			}
		}
		return $user_roles;
	}

	/**
	 * Dismissible activation notice (Query Forge active + starter queries).
	 */
	public function render_activation_admin_notice() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		if ( get_option( 'qf_notice_dismissed', false ) ) {
			return;
		}
		?>
		<div class="notice notice-info is-dismissible qf-query-forge-activation-notice" data-nonce="<?php echo esc_attr( wp_create_nonce( 'query_forge_nonce' ) ); ?>">
			<p><?php esc_html_e( 'Query Forge is active. Open the block editor or Elementor, add a Query Forge block or widget, and find your 4 starter queries under Import on the canvas.', 'query-forge' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Inline script: persist admin notice dismissal.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public function enqueue_admin_notice_dismiss( $hook_suffix ) {
		if ( get_option( 'qf_notice_dismissed', false ) ) {
			return;
		}
		wp_enqueue_script( 'jquery' );
		$script = sprintf(
			'jQuery(function($){$(document).on("click",".qf-query-forge-activation-notice .notice-dismiss",function(){var n=$(this).closest(".qf-query-forge-activation-notice").data("nonce");$.post(ajaxurl,{action:"qf_dismiss_notice",nonce:n});});});'
		);
		wp_add_inline_script( 'jquery', $script );
	}

	/**
	 * AJAX: dismiss activation notice.
	 */
	public function ajax_qf_dismiss_notice() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
		}
		update_option( 'qf_notice_dismissed', 1, false );
		wp_send_json_success();
	}

	/**
	 * AJAX: first-run onboarding modal completed.
	 */
	public function ajax_qf_complete_onboarding() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
		}
		update_option( 'qf_onboarding_complete', 1, false );
		wp_send_json_success();
	}

	/**
	 * Enqueue Widget Styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_widget_styles() {
		wp_enqueue_style(
			'query_forge_widget_css',
			QUERY_FORGE_URL . 'assets/css/qf-widget.css',
			[],
			QUERY_FORGE_VERSION
		);
		wp_enqueue_script(
			'query_forge_widget',
			QUERY_FORGE_URL . 'assets/js/qf-widget.js',
			[ 'jquery' ],
			QUERY_FORGE_VERSION,
			true
		);
		wp_localize_script(
			'query_forge_widget',
			'QueryForgeWidget',
			\Query_Forge\QF_Frontend_Search::get_widget_script_data()
		);
	}

	/**
	 * AJAX handler: Get meta keys
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_meta_keys() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
			return;
		}

		$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'post';

		// Standard WordPress post fields.
		$standard_fields = [
			[
				'key'   => 'post_title',
				'label' => __( 'Title', 'query-forge' ),
				'type'  => 'standard',
			],
			[
				'key'   => 'post_date',
				'label' => __( 'Date', 'query-forge' ),
				'type'  => 'standard',
			],
			[
				'key'   => 'post_author',
				'label' => __( 'Author', 'query-forge' ),
				'type'  => 'standard',
			],
			[
				'key'   => 'post_content',
				'label' => __( 'Content', 'query-forge' ),
				'type'  => 'standard',
			],
			[
				'key'   => 'post_excerpt',
				'label' => __( 'Excerpt', 'query-forge' ),
				'type'  => 'standard',
			],
		];

		// Public taxonomies for this post type (filter node: taxonomy terms).
		$taxonomy_fields = [];
		$taxonomies      = get_object_taxonomies( $post_type, 'objects' );
		foreach ( $taxonomies as $tax ) {
			if ( ! $tax->public ) {
				continue;
			}
			$taxonomy_fields[] = [
				'key'      => 'tax:' . $tax->name,
				'label'    => $tax->label,
				'type'     => 'taxonomy',
				'taxonomy' => $tax->name,
			];
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct query required to get all meta keys, caching not applicable for dynamic meta key discovery.
		$meta_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_key
				FROM {$wpdb->postmeta}
				WHERE meta_key NOT LIKE %s
				AND meta_key NOT LIKE %s
				AND post_id IN (
					SELECT ID FROM {$wpdb->posts}
					WHERE post_type = %s
				)
				ORDER BY meta_key
				LIMIT 100",
				$wpdb->esc_like( '_' ) . '%',
				$wpdb->esc_like( 'elementor' ) . '%',
				$post_type
			)
		);

		// Also include common WordPress meta keys.
		$common_meta_keys = [
			'_thumbnail_id',
			'_wp_attachment_image_alt',
		];

		// Merge and remove duplicates.
		$all_meta_keys = array_unique( array_merge( $meta_keys, $common_meta_keys ) );
		sort( $all_meta_keys );

		// Format meta keys as objects.
		$meta_fields = array_map( function( $key ) {
			return [
				'key'   => $key,
				'label' => $key,
				'type'  => 'meta',
			];
		}, $all_meta_keys );

		// Standard fields, then taxonomy fields, then meta fields.
		$all_fields = array_merge( $standard_fields, $taxonomy_fields, $meta_fields );

		wp_send_json_success( [
			'fields'            => $all_fields,
			'taxonomy_fields'   => $taxonomy_fields,
			'standard_fields'   => $standard_fields,
			'meta_keys'         => $all_meta_keys, // Keep for backward compatibility.
		] );
	}

	/**
	 * AJAX handler: Search taxonomy terms for filter node autocomplete (editor).
	 *
	 * @since 1.0.0
	 */
	public function ajax_search_terms() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
			return;
		}

		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid taxonomy.', 'query-forge' ) ] );
			return;
		}

		$search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
		if ( strlen( $search ) < 1 ) {
			wp_send_json_success( [ 'terms' => [] ] );
			return;
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 20,
				'search'     => $search,
				'orderby'    => 'name',
				'order'      => 'ASC',
			]
		);

		if ( is_wp_error( $terms ) ) {
			wp_send_json_error( [ 'message' => $terms->get_error_message() ] );
			return;
		}

		$out = [];
		foreach ( $terms as $term ) {
			$out[] = [
				'id'   => (int) $term->term_id,
				'name' => $term->name,
				'slug' => $term->slug,
			];
		}

		wp_send_json_success( [ 'terms' => $out ] );
	}

	/**
	 * Deep sanitize array recursively (sanitize string values only, preserve structure)
	 *
	 * @since 1.0.0
	 * @param mixed $data Data to sanitize (array, string, or other).
	 * @return mixed Sanitized data.
	 */
	private function deep_sanitize_array( $data ) {
		if ( is_array( $data ) ) {
			$sanitized = [];
			foreach ( $data as $key => $value ) {
				$sanitized_key = is_string( $key ) ? sanitize_text_field( $key ) : $key;
				$sanitized[ $sanitized_key ] = $this->deep_sanitize_array( $value );
			}
			return $sanitized;
		} elseif ( is_string( $data ) ) {
			return sanitize_text_field( $data );
		}
		// Return other types (int, bool, null, etc.) as-is.
		return $data;
	}

	/**
	 * AJAX handler: Save query
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_query() {
		// Verify nonce.
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
			return;
		}

		/*
		 * Decode the incoming JSON payload for the saved query.
		 *
		 * Note: json_decode() itself is not used as a sanitization step. It is only used
		 * to turn the JSON string into an array. Below we validate the structure and then
		 * recursively sanitize all string values while preserving the JSON structure.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data is validated and sanitized recursively below.
		$raw_query_data = isset( $_POST['query_data'] ) ? json_decode( wp_unslash( $_POST['query_data'] ), true ) : null;

		if ( ! $raw_query_data || ! is_array( $raw_query_data ) || empty( $raw_query_data['name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}

		// Sanitize top-level string fields.
		$sanitized_name = isset( $raw_query_data['name'] ) && is_string( $raw_query_data['name'] ) ? sanitize_text_field( $raw_query_data['name'] ) : '';
		$sanitized_date = isset( $raw_query_data['date'] ) && is_string( $raw_query_data['date'] ) ? sanitize_text_field( $raw_query_data['date'] ) : current_time( 'mysql' );

		// Deep sanitize graphState and logicJson: decode JSON, sanitize recursively, then re-encode.
		$graph_state_raw = isset( $raw_query_data['graphState'] ) && is_string( $raw_query_data['graphState'] ) ? $raw_query_data['graphState'] : '';
		$logic_json_raw  = isset( $raw_query_data['logicJson'] ) && is_string( $raw_query_data['logicJson'] ) ? $raw_query_data['logicJson'] : '';

		// Decode and deep sanitize graphState.
		$graph_state_decoded = json_decode( $graph_state_raw, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $graph_state_decoded ) ) {
			$graph_state_sanitized = $this->deep_sanitize_array( $graph_state_decoded );
			$graph_state = wp_json_encode( $graph_state_sanitized );
		} else {
			// If JSON decode fails, sanitize as plain string (fallback).
			$graph_state = sanitize_text_field( $graph_state_raw );
		}

		// Decode and deep sanitize logicJson.
		$logic_json_decoded = json_decode( $logic_json_raw, true );
		if ( json_last_error() === JSON_ERROR_NONE && is_array( $logic_json_decoded ) ) {
			$logic_json_sanitized = $this->deep_sanitize_array( $logic_json_decoded );
			$logic_json = wp_json_encode( $logic_json_sanitized );
		} else {
			// If JSON decode fails, sanitize as plain string (fallback).
			$logic_json = sanitize_text_field( $logic_json_raw );
		}

		// Validate structure and extract only expected keys.
		$query_data = [
			'name'       => $sanitized_name,
			'date'       => $sanitized_date,
			'graphState' => $graph_state,
			'logicJson'  => $logic_json,
		];

		// Ensure name is not empty after sanitization.
		if ( empty( $query_data['name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Query name is required.', 'query-forge' ) ] );
			return;
		}

		// Generate unique ID.
		$query_id = 'query_forge_query_' . md5( $query_data['name'] . time() );

		// Save to WordPress options.
		$saved = update_option( $query_id, $query_data, false );

		if ( $saved !== false ) {
			// Also store in list of saved queries.
			$query_list = get_option( 'query_forge_saved_queries', [] );
			if ( ! is_array( $query_list ) ) {
				$query_list = [];
			}
			$query_list[ $query_id ] = [
				'id'   => $query_id,
				'name' => $query_data['name'],
				'date' => current_time( 'mysql' ),
			];
			update_option( 'query_forge_saved_queries', $query_list, false );

			wp_send_json_success( [ 'message' => __( 'Query saved successfully.', 'query-forge' ), 'id' => $query_id ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Failed to save query.', 'query-forge' ) ] );
		}
	}

	/**
	 * AJAX handler: Get saved queries
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_saved_queries() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
			return;
		}

		$query_list = get_option( 'query_forge_saved_queries', [] );

		// Fetch full query data for each saved query.
		$queries_with_data = [];
		foreach ( $query_list as $query_id => $query_meta ) {
			$full_query_data = get_option( $query_id, null );
			if ( $full_query_data && is_array( $full_query_data ) ) {
				$graph_state = isset( $full_query_data['graphState'] ) ? $full_query_data['graphState'] : '';
				$logic_json  = isset( $full_query_data['logicJson'] ) ? $full_query_data['logicJson'] : '';
				
				$queries_with_data[ $query_id ] = array_merge(
					$query_meta,
					[
						'graphState' => $graph_state,
						'logicJson'  => $logic_json,
					]
				);
			} else {
				// Include metadata even if full data is missing.
				$queries_with_data[ $query_id ] = $query_meta;
			}
		}

		wp_send_json_success( [ 'queries' => $queries_with_data ] );
	}

	/**
	 * AJAX handler: Delete query
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_query() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		// Check user capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'query-forge' ) ] );
			return;
		}

		$query_id = isset( $_POST['query_id'] ) ? sanitize_text_field( wp_unslash( $_POST['query_id'] ) ) : '';

		if ( empty( $query_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query ID.', 'query-forge' ) ] );
			return;
		}

		// Validate query_id prefix to prevent deletion of arbitrary options.
		if ( strpos( $query_id, 'query_forge_query_' ) !== 0 ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query ID.', 'query-forge' ) ] );
			return;
		}

		// Delete the query.
		delete_option( $query_id );

		// Remove from query list.
		$query_list = get_option( 'query_forge_saved_queries', [] );
		if ( is_array( $query_list ) && isset( $query_list[ $query_id ] ) ) {
			unset( $query_list[ $query_id ] );
			update_option( 'query_forge_saved_queries', $query_list, false );
		}

		wp_send_json_success( [ 'message' => __( 'Query deleted successfully.', 'query-forge' ) ] );
	}

	/**
	 * AJAX handler: Load more posts
	 *
	 * @since 1.0.0
	 */
	public function ajax_load_more_posts() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );
		
		/*
		 * logic_json is a JSON-encoded representation of the graph built in the editor.
		 * We treat it as an opaque JSON string here, but we first ensure it decodes to an
		 * array. The query parser then validates and sanitizes individual fields before
		 * using them to build WP_Query arguments.
		 */
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string is validated via json_decode below, and values are sanitized in the query parser.
		$logic_json_raw = isset( $_POST['logic_json'] ) ? wp_unslash( $_POST['logic_json'] ) : '';
		$paged = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string is validated and sanitized below.
		$widget_settings_json = isset( $_POST['widget_settings'] ) ? wp_unslash( $_POST['widget_settings'] ) : '';
		
		if ( empty( $logic_json_raw ) || ! is_string( $logic_json_raw ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}

		// Validate logic_json decodes to an array (query parser expects JSON string; we pass through but ensure it's valid).
		$logic_decoded = json_decode( $logic_json_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $logic_decoded ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}
		$logic_json = $logic_json_raw;

		// Parse and sanitize widget settings: only allow known keys with sanitized values.
		$widget_settings_raw = json_decode( $widget_settings_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $widget_settings_raw ) ) {
			$widget_settings_raw = [];
		}
		$widget_settings = $this->sanitize_load_more_widget_settings( $widget_settings_raw );

		$search_enabled_req = $this->ajax_request_search_enabled();
		$extra_args         = null;
		$search_filter_applied = false;

		if ( $search_enabled_req ) {
			$search_term = \Query_Forge\QF_Frontend_Search::sanitize_search_term( isset( $_POST['search_term'] ) ? $_POST['search_term'] : '' );
			$field_raw   = isset( $_POST['search_field'] ) ? sanitize_text_field( wp_unslash( $_POST['search_field'] ) ) : 'title';
			$search_field = in_array( $field_raw, [ 'title', 'content', 'title_content' ], true ) ? $field_raw : 'title';
			$extra_args     = \Query_Forge\QF_Frontend_Search::extra_args_for_search( $search_term, $search_field );
			$search_filter_applied = ( null !== $extra_args );
		}

		$ppp        = \Query_Forge\QF_Query_Parser::resolve_posts_per_page_for_query( $logic_json );
		$ttl        = \Query_Forge\QF_Query_Cache::get_cache_ttl_from_logic( $logic_json );
		$logic_hash = \Query_Forge\QF_Query_Cache::logic_hash( $logic_json );
		$ctx_hash   = md5( $widget_settings_json );
		$cache_key  = \Query_Forge\QF_Query_Cache::build_cache_key( $logic_json, $paged, $ppp, $ctx_hash, 'ajax' );

		if ( $ttl > 0 && ! \Query_Forge\QF_Query_Cache::should_bypass() && ! $search_enabled_req ) {
			$cached_payload = \Query_Forge\QF_Query_Cache::get_array( $cache_key, $logic_hash );
			if ( is_array( $cached_payload ) ) {
				wp_send_json_success( $cached_payload );
			}
		}

		$query = \Query_Forge\QF_Query_Parser::get_query( $logic_json, $paged, $ppp, $extra_args );

		if ( ! $query || ! method_exists( $query, 'have_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'No more posts found.', 'query-forge' ) ] );
			return;
		}

		if ( ! $query->have_posts() && ! $search_enabled_req ) {
			wp_send_json_error( [ 'message' => __( 'No more posts found.', 'query-forge' ) ] );
			return;
		}

		$payload = $this->compose_ajax_grid_payload( $query, $paged, $ppp, $widget_settings, $search_filter_applied );

		if ( $ttl > 0 && ! \Query_Forge\QF_Query_Cache::should_bypass() && ! $search_enabled_req ) {
			\Query_Forge\QF_Query_Cache::set_array( $cache_key, $logic_hash, $payload, $ttl );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * AJAX: flush cached HTML/payloads for a logic JSON graph.
	 *
	 * @since 1.3.3
	 */
	public function ajax_flush_block_cache() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Forbidden.', 'query-forge' ) ] );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated via logic_hash length / json_decode in cache layer.
		$logic_json = isset( $_POST['logic_json'] ) ? wp_unslash( $_POST['logic_json'] ) : '';
		if ( ! is_string( $logic_json ) || '' === $logic_json ) {
			wp_send_json_error( [ 'message' => __( 'Missing query data.', 'query-forge' ) ] );
		}
		$check = json_decode( $logic_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $check ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
		}
		\Query_Forge\QF_Query_Cache::flush_by_logic_hash( \Query_Forge\QF_Query_Cache::logic_hash( $logic_json ) );
		wp_send_json_success( [ 'message' => __( 'Cache cleared.', 'query-forge' ) ] );
	}

	/**
	 * Invalidate query result caches when any post is saved.
	 *
	 * @since 1.3.3
	 * @param int     $post_id Post ID.
	 * @param \WP_Post $post   Post object.
	 */
	public function invalidate_query_caches_on_save( $post_id, $post ) {
		unset( $post );
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		\Query_Forge\QF_Query_Cache::flush_all();
	}

	/**
	 * Shared AJAX response for grid HTML, pagination fragment, and optional results summary.
	 *
	 * @since 1.3.3
	 * @param \WP_Query $query           Query object.
	 * @param int       $paged           Current page.
	 * @param int       $ppp             Posts per page fallback.
	 * @param array     $widget_settings Sanitized widget settings.
	 * @param bool      $search_active   Whether frontend search filter is active.
	 * @return array<string, mixed>
	 */
	private function compose_ajax_grid_payload( $query, $paged, $ppp, array $widget_settings, $search_active = false ) {
		ob_start();

		$display_type = ! empty( $widget_settings['display_type'] ) ? $widget_settings['display_type'] : 'canned';

		if ( $query->have_posts() ) {
			if ( 'template' === $display_type && ! empty( $widget_settings['elementor_template_id'] ) && class_exists( '\Elementor\Plugin' ) ) {
				$template_id        = absint( $widget_settings['elementor_template_id'] );
				$elementor_instance = \Elementor\Plugin::instance();
				if ( $elementor_instance->frontend ) {
					while ( $query->have_posts() ) {
						$query->the_post();
						?>
						<div class="qf-template-item">
							<?php
							$content = $elementor_instance->frontend->get_builder_content_for_display( $template_id );
							if ( $content ) {
								echo wp_kses_post( $content );
							}
							?>
						</div>
						<?php
					}
				}
			} else {
				$card_style = ! empty( $widget_settings['card_style'] ) ? $widget_settings['card_style'] : 'vertical';
				while ( $query->have_posts() ) {
					$query->the_post();
					$this->render_card_html( $widget_settings, $card_style );
				}
			}
		} else {
			echo '<div class="qf-placeholder qf-search-empty"><p>' . esc_html__( 'No results found.', 'query-forge' ) . '</p></div>';
		}

		wp_reset_postdata();

		$html = ob_get_clean();

		$max_pages = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 0;
		if ( $max_pages < 1 ) {
			$found = isset( $query->found_posts ) ? (int) $query->found_posts : 0;
			$per   = (int) $query->get( 'posts_per_page' );
			if ( $per <= 0 ) {
				$per = max( 1, $ppp );
			}
			$max_pages = $found > 0 ? max( 1, (int) ceil( $found / $per ) ) : 1;
		}
		$has_more = $paged < $max_pages;

		$pagination_html = '';
		global $wp;
		$current_url = home_url( add_query_arg( [], $wp->request ) );
		$base        = remove_query_arg( 'paged', $current_url );
		$base        = trailingslashit( $base );
		if ( strpos( $base, '?' ) !== false ) {
			$format = '&paged=%#%';
		} else {
			$format = '?paged=%#%';
		}

		$prev_text = ! empty( $widget_settings['pagination_prev_text'] ) ? $widget_settings['pagination_prev_text'] : __( '&laquo; Previous', 'query-forge' );
		$next_text = ! empty( $widget_settings['pagination_next_text'] ) ? $widget_settings['pagination_next_text'] : __( 'Next &raquo;', 'query-forge' );

		$pagination = paginate_links(
			[
				'total'     => $max_pages,
				'current'   => $paged,
				'prev_text' => $prev_text,
				'next_text' => $next_text,
				'format'    => $format,
				'base'      => $base . '%_%',
			]
		);

		if ( $pagination ) {
			$pagination_html = wp_kses_post( $pagination );
		}

		$results_summary_html = '';
		if ( ! empty( $widget_settings['show_results_summary'] ) && 'yes' === $widget_settings['show_results_summary'] ) {
			$total    = (int) $query->found_posts;
			$per_page = 0;
			if ( is_object( $query ) && method_exists( $query, 'get' ) ) {
				$per_page = (int) $query->get( 'posts_per_page' );
			}
			if ( $per_page <= 0 && $max_pages > 0 && $total > 0 ) {
				$per_page = (int) max( 1, ceil( $total / $max_pages ) );
			}
			if ( $per_page <= 0 ) {
				$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
			}
			$start = ( ( $paged - 1 ) * $per_page ) + 1;
			$end   = min( $paged * $per_page, $total );
			if ( $total > 0 && $start <= $end ) {
				$text = sprintf(
					esc_html__( 'Showing %1$d–%2$d of %3$d results', 'query-forge' ),
					$start,
					$end,
					$total
				);
				$results_summary_html = '<div class="qf-results-summary">' . esc_html( $text ) . '</div>';
			}
		}

		return [
			'html'                 => $html,
			'has_more'             => $has_more,
			'next_page'            => $has_more ? $paged + 1 : null,
			'current_page'         => $paged,
			'max_pages'            => $max_pages,
			'pagination_html'      => $pagination_html,
			'results_summary_html' => $results_summary_html,
			'search_active'        => $search_active ? '1' : '0',
		];
	}

	/**
	 * AJAX: frontend search / grid refresh (no query-result cache).
	 *
	 * @since 1.3.3
	 */
	public function ajax_qf_search() {
		check_ajax_referer( 'query_forge_nonce', 'nonce' );

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validated below.
		$logic_json_raw = isset( $_POST['logic_json'] ) ? wp_unslash( $_POST['logic_json'] ) : '';
		$paged          = isset( $_POST['paged'] ) ? max( 1, absint( $_POST['paged'] ) ) : 1;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON validated below.
		$widget_settings_json = isset( $_POST['widget_settings'] ) ? wp_unslash( $_POST['widget_settings'] ) : '';

		if ( empty( $logic_json_raw ) || ! is_string( $logic_json_raw ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}

		$logic_decoded = json_decode( $logic_json_raw, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $logic_decoded ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}
		$logic_json = $logic_json_raw;

		if ( ! $this->ajax_request_search_enabled() ) {
			wp_send_json_error( [ 'message' => __( 'Search is not enabled for this block or widget.', 'query-forge' ) ] );
			return;
		}

		$widget_settings_raw = json_decode( $widget_settings_json, true );
		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array( $widget_settings_raw ) ) {
			$widget_settings_raw = [];
		}
		$widget_settings = $this->sanitize_load_more_widget_settings( $widget_settings_raw );

		$search_term  = \Query_Forge\QF_Frontend_Search::sanitize_search_term( isset( $_POST['search_term'] ) ? $_POST['search_term'] : '' );
		$field_raw    = isset( $_POST['search_field'] ) ? sanitize_text_field( wp_unslash( $_POST['search_field'] ) ) : 'title';
		$search_field = in_array( $field_raw, [ 'title', 'content', 'title_content' ], true ) ? $field_raw : 'title';

		$extra_args    = \Query_Forge\QF_Frontend_Search::extra_args_for_search( $search_term, $search_field );
		$search_active = ( null !== $extra_args );
		$ppp            = \Query_Forge\QF_Query_Parser::resolve_posts_per_page_for_query( $logic_json );
		$query          = \Query_Forge\QF_Query_Parser::get_query( $logic_json, $paged, $ppp, $extra_args );

		if ( ! $query || ! method_exists( $query, 'have_posts' ) ) {
			wp_send_json_error( [ 'message' => __( 'Could not run query.', 'query-forge' ) ] );
			return;
		}

		$payload = $this->compose_ajax_grid_payload( $query, $paged, $ppp, $widget_settings, $search_active );
		wp_send_json_success( $payload );
	}

	/**
	 * Whether the client declares search enabled for this instance (POST).
	 *
	 * @since 1.3.3
	 * @return bool
	 */
	private function ajax_request_search_enabled() {
		$raw = isset( $_POST['search_enabled'] ) ? wp_unslash( $_POST['search_enabled'] ) : false;
		return filter_var( $raw, FILTER_VALIDATE_BOOLEAN ) === true;
	}

	/**
	 * Sanitize widget settings for load-more AJAX: only allow known keys with sanitized values.
	 *
	 * @since 1.0.0
	 * @param array $raw Raw decoded widget settings.
	 * @return array Sanitized settings.
	 */
	private function sanitize_load_more_widget_settings( $raw ) {
		$allowed_string_keys = [
			'display_type', 'card_style', 'pagination_type', 'link_target', 'image_size',
			'pagination_prev_text', 'pagination_next_text',
		];
		$allowed_yes_no = [ 'show_title', 'show_excerpt', 'show_date', 'show_author', 'show_image', 'show_results_summary' ];
		$sanitized = [];
		foreach ( $allowed_string_keys as $key ) {
			if ( isset( $raw[ $key ] ) && is_string( $raw[ $key ] ) ) {
				$sanitized[ $key ] = sanitize_text_field( $raw[ $key ] );
			}
		}
		foreach ( $allowed_yes_no as $key ) {
			if ( isset( $raw[ $key ] ) ) {
				$sanitized[ $key ] = ( 'yes' === sanitize_text_field( $raw[ $key ] ) ) ? 'yes' : 'no';
			}
		}
		if ( isset( $raw['elementor_template_id'] ) ) {
			$sanitized['elementor_template_id'] = absint( $raw['elementor_template_id'] );
		}
		if ( isset( $raw['excerpt_length'] ) ) {
			$sanitized['excerpt_length'] = absint( $raw['excerpt_length'] );
		}
		if ( isset( $raw['results_summary_position'] ) && is_string( $raw['results_summary_position'] ) ) {
			$sanitized['results_summary_position'] = sanitize_text_field( $raw['results_summary_position'] );
		}
		return $sanitized;
	}

	/**
	 * Render card HTML (helper method for AJAX)
	 *
	 * @since 1.0.0
	 * @param array  $settings Widget settings.
	 * @param string $style Card style.
	 */
	private function render_card_html( $settings, $style ) {
		$show_title   = ! empty( $settings['show_title'] ) && 'yes' === $settings['show_title'];
		$show_excerpt = ! empty( $settings['show_excerpt'] ) && 'yes' === $settings['show_excerpt'];
		$show_date    = ! empty( $settings['show_date'] ) && 'yes' === $settings['show_date'];
		$show_author  = ! empty( $settings['show_author'] ) && 'yes' === $settings['show_author'];
		$show_image   = ! empty( $settings['show_image'] ) && 'yes' === $settings['show_image'];
		$link_target  = ! empty( $settings['link_target'] ) ? $settings['link_target'] : '_self';
		$image_size   = ! empty( $settings['image_size'] ) ? $settings['image_size'] : 'medium';
		$excerpt_length = ! empty( $settings['excerpt_length'] ) ? absint( $settings['excerpt_length'] ) : 100;

		?>
		<div class="qf-card qf-card-<?php echo esc_attr( $style ); ?>">
			<div class="qf-card-inner qf-card-<?php echo esc_attr( $style ); ?>">
				<?php if ( $show_image ) : ?>
					<div class="qf-card-image">
						<?php
						$image_id = get_post_thumbnail_id();
						if ( $image_id ) {
							$image_url = wp_get_attachment_image_url( $image_id, $image_size );
							$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
							?>
							<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>">
								<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ? $image_alt : get_the_title() ); ?>" />
							</a>
							<?php
						} else {
							?>
							<div class="qf-card-image-placeholder">
								<span class="qf-placeholder-icon" aria-hidden="true">📷</span>
							</div>
							<?php
						}
						?>
					</div>
				<?php endif; ?>
				<div class="qf-card-content">
					<?php if ( $show_title ) : ?>
						<h3 class="qf-card-title">
							<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>">
								<?php echo esc_html( get_the_title() ); ?>
							</a>
						</h3>
					<?php endif; ?>
					<?php if ( $show_excerpt ) : ?>
						<div class="qf-card-excerpt">
							<?php
							$excerpt = get_the_excerpt();
							if ( empty( $excerpt ) ) {
								$content = get_the_content();
								$content = wp_strip_all_tags( $content );
								$excerpt = wp_trim_words( $content, $excerpt_length / 10, '...' );
							} else {
								$excerpt = wp_trim_words( $excerpt, $excerpt_length / 10, '...' );
							}
							echo esc_html( $excerpt );
							?>
						</div>
					<?php endif; ?>
					<?php if ( $show_date || $show_author ) : ?>
						<div class="qf-card-meta">
							<?php if ( $show_date ) : ?>
								<span class="qf-card-date">
									<?php echo esc_html( get_the_date() ); ?>
								</span>
							<?php endif; ?>
							<?php if ( $show_date && $show_author ) : ?>
								<span class="qf-card-separator"> • </span>
							<?php endif; ?>
							<?php if ( $show_author ) : ?>
								<span class="qf-card-author">
									<a href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
										<?php echo esc_html( get_the_author() ); ?>
									</a>
								</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}
}
