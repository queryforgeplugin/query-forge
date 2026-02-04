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
	}

	/**
	 * Register Hooks
	 *
	 * @since 1.0.0
	 */
	private function hooks() {
		add_action( 'elementor/init', [ $this, 'on_elementor_init' ] );
		add_action( 'elementor/editor/after_enqueue_scripts', [ $this, 'enqueue_editor_scripts' ] );
		add_action( 'elementor/frontend/after_enqueue_styles', [ $this, 'enqueue_widget_styles' ] );
		add_action( 'elementor/editor/after_enqueue_styles', [ $this, 'enqueue_widget_styles' ] );
		add_action( 'wp_ajax_query_forge_get_meta_keys', [ $this, 'ajax_get_meta_keys' ] );
		add_action( 'wp_ajax_nopriv_query_forge_get_meta_keys', [ $this, 'ajax_get_meta_keys' ] );
		add_action( 'wp_ajax_query_forge_save_query', [ $this, 'ajax_save_query' ] );
		add_action( 'wp_ajax_nopriv_query_forge_save_query', [ $this, 'ajax_save_query' ] );
		add_action( 'wp_ajax_query_forge_get_saved_queries', [ $this, 'ajax_get_saved_queries' ] );
		add_action( 'wp_ajax_nopriv_query_forge_get_saved_queries', [ $this, 'ajax_get_saved_queries' ] );
		add_action( 'wp_ajax_query_forge_delete_query', [ $this, 'ajax_delete_query' ] );
		add_action( 'wp_ajax_nopriv_query_forge_delete_query', [ $this, 'ajax_delete_query' ] );
		add_action( 'wp_ajax_query_forge_load_more_posts', [ $this, 'ajax_load_more_posts' ] );
		add_action( 'wp_ajax_nopriv_query_forge_load_more_posts', [ $this, 'ajax_load_more_posts' ] );
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
		wp_enqueue_script(
			'query_forge_editor',
			QUERY_FORGE_URL . 'assets/js/qf-editor.bundle.js',
			[ 'wp-element', 'jquery' ],
			QUERY_FORGE_VERSION,
			true
		);

		$post_type_names = get_post_types( [], 'names' );
		$post_types_array = [];
		foreach ( $post_type_names as $post_type_name ) {
			$post_type_obj = get_post_type_object( $post_type_name );
			if ( $post_type_obj ) {
				$post_types_array[] = [
					'name'  => $post_type_obj->name,
					'label' => $post_type_obj->label ? $post_type_obj->label : $post_type_obj->name,
				];
			}
		}

		// Get all user roles for the builder.
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

		wp_localize_script(
			'query_forge_editor',
			'QueryForgeConfig',
			[
				'postTypes' => $post_types_array,
				'userRoles' => $user_roles,
				'assetsUrl' => QUERY_FORGE_URL . 'assets/',
				'nonce'     => wp_create_nonce( 'query_forge_nonce' ),
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'isPro'     => defined( 'QUERY_FORGE_PRO_ACTIVE' ) && QUERY_FORGE_PRO_ACTIVE,
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
	 * Enqueue Widget Styles
	 *
	 * @since 1.0.0
	 */
	public function enqueue_widget_styles() {
		wp_enqueue_style(
			'query_forge_widget',
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
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'query_forge_nonce' ),
			]
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

		// Combine standard fields and meta fields.
		$all_fields = array_merge( $standard_fields, $meta_fields );

		wp_send_json_success( [
			'fields' => $all_fields,
			'standard_fields' => $standard_fields,
			'meta_keys' => $all_meta_keys, // Keep for backward compatibility.
		] );
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

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON data is validated and sanitized below.
		$raw_query_data = isset( $_POST['query_data'] ) ? json_decode( wp_unslash( $_POST['query_data'] ), true ) : null;

		if ( ! $raw_query_data || ! is_array( $raw_query_data ) || empty( $raw_query_data['name'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid query data.', 'query-forge' ) ] );
			return;
		}

		// Validate structure and sanitize: only allow expected keys.
		$query_data = [
			'name'       => sanitize_text_field( $raw_query_data['name'] ),
			'date'       => isset( $raw_query_data['date'] ) && is_string( $raw_query_data['date'] ) ? sanitize_text_field( $raw_query_data['date'] ) : current_time( 'mysql' ),
			'graphState' => isset( $raw_query_data['graphState'] ) && is_string( $raw_query_data['graphState'] ) ? wp_unslash( $raw_query_data['graphState'] ) : '',
			'logicJson'  => isset( $raw_query_data['logicJson'] ) && is_string( $raw_query_data['logicJson'] ) ? wp_unslash( $raw_query_data['logicJson'] ) : '',
		];

		// Generate unique ID.
		$query_id = 'query_forge_query_' . md5( $query_data['name'] . time() );

		// Save to WordPress options.
		$saved = update_option( $query_id, $query_data );

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
			update_option( 'query_forge_saved_queries', $query_list );

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

		// Debug: Log query list retrieval (only if WP_DEBUG is enabled).
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		}
		
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
			update_option( 'query_forge_saved_queries', $query_list );
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
		
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON string is validated via json_decode below.
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

		// Set paged in GET for query parser
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Internal pagination parameter, already sanitized via absint().
		$_GET['paged'] = $paged;

		// Execute query
		$query = \Query_Forge\QF_Query_Parser::get_query( $logic_json );

		if ( ! $query || ( method_exists( $query, 'have_posts' ) && ! $query->have_posts() ) ) {
			wp_send_json_error( [ 'message' => __( 'No more posts found.', 'query-forge' ) ] );
		}

		// Render posts HTML
		ob_start();
		
		$display_type = ! empty( $widget_settings['display_type'] ) ? $widget_settings['display_type'] : 'canned';
		
		if ( 'template' === $display_type && ! empty( $widget_settings['elementor_template_id'] ) ) {
			$template_id = absint( $widget_settings['elementor_template_id'] );
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
			// Use inline rendering for cards
			$card_style = ! empty( $widget_settings['card_style'] ) ? $widget_settings['card_style'] : 'vertical';
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_card_html( $widget_settings, $card_style );
			}
		}
		
		wp_reset_postdata();
		
		$html = ob_get_clean();
		
		// Check if there are more pages
		$max_pages = $query->max_num_pages ?? 1;
		$has_more = $paged < $max_pages;
		
		// Generate pagination HTML if needed (for AJAX pagination)
		$pagination_html = '';
		if ( ! empty( $widget_settings['pagination_type'] ) && 'ajax' === $widget_settings['pagination_type'] ) {
			$widget_id = isset( $_POST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_POST['widget_id'] ) ) : 'ajax-' . time();
			
			global $wp;
			$current_url = home_url( add_query_arg( [], $wp->request ) );
			$base = remove_query_arg( 'paged', $current_url );
			$base = trailingslashit( $base );
			if ( strpos( $base, '?' ) !== false ) {
				$format = '&paged=%#%';
			} else {
				$format = '?paged=%#%';
			}
			
			$prev_text = ! empty( $widget_settings['pagination_prev_text'] ) ? $widget_settings['pagination_prev_text'] : __( '&laquo; Previous', 'query-forge' );
			$next_text = ! empty( $widget_settings['pagination_next_text'] ) ? $widget_settings['pagination_next_text'] : __( 'Next &raquo;', 'query-forge' );
			
			// Temporarily set $_GET['paged'] so paginate_links can detect it
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- $_GET['paged'] is a public pagination parameter, already sanitized via absint() above.
			$original_paged = isset( $_GET['paged'] ) ? wp_unslash( $_GET['paged'] ) : null;
			$_GET['paged'] = $paged;
			
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
			
			// Restore original $_GET['paged'] if it existed
			if ( null !== $original_paged ) {
				$_GET['paged'] = $original_paged;
			} else {
				unset( $_GET['paged'] );
			}
			
			if ( $pagination ) {
				// Return just the pagination links HTML (the wrapper div is already in the DOM)
				$pagination_html = wp_kses_post( $pagination );
			}
		}
		
		wp_send_json_success( [
			'html'            => $html,
			'has_more'        => $has_more,
			'next_page'       => $has_more ? $paged + 1 : null,
			'current_page'    => $paged,
			'max_pages'       => $max_pages,
			'pagination_html' => $pagination_html,
		] );
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
		$allowed_yes_no = [ 'show_title', 'show_excerpt', 'show_date', 'show_author', 'show_image' ];
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
								<?php the_title(); ?>
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
										<?php the_author(); ?>
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
