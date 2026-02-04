<?php
/**
 * Query Forge Widget
 *
 * Custom widget that renders query results from Query Forge.
 *
 * @package Query_Forge
 * @since   1.0.0
 * @version 1.0.0
 */

namespace Query_Forge\Widgets;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.WP.I18n.TextDomainMismatch -- Text domain must be lowercase per WordPress.org requirements, but PluginCheck expects directory name match.

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use Query_Forge\QF_Query_Parser;

/**
 * Query Forge Widget Class
 */
class Smart_Loop_Grid extends Widget_Base {

	/**
	 * Get widget name
	 *
	 * @since 1.0.0
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'qf_smart_loop_grid';
	}

	/**
	 * Get widget title
	 *
	 * @since 1.0.0
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'Query Forge', 'query-forge' );
	}

	/**
	 * Get widget icon
	 *
	 * @since 1.0.0
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-posts-grid';
	}

	/**
	 * Get widget categories
	 *
	 * @since 1.0.0
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'query-forge' ];
	}

	/**
	 * Register widget controls
	 *
	 * @since 1.0.0
	 */
	protected function register_controls() {
		// Query Builder Section.
		$this->start_controls_section(
			'section_query_builder',
			[
				'label' => __( 'Query Builder', 'query-forge' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'qf_open_builder',
			[
				'type'        => Controls_Manager::BUTTON,
				'label'       => __( 'Open Visual Logic Builder', 'query-forge' ),
				'text'        => __( '⚡ Open Builder', 'query-forge' ),
				'button_type' => 'default',
				'separator'   => 'after',
			]
		);

		// Explore Pro link
		$this->add_control(
			'qf_explore_pro',
			[
				'type'        => Controls_Manager::RAW_HTML,
				'raw'         => sprintf(
					'<div style="text-align: center; margin-top: 10px;"><a href="%s" target="_blank" rel="noopener noreferrer" style="font-size: 12px; color: #999; text-decoration: none;">%s</a></div>',
					esc_url( 'https://queryforgeplugin.com' ),
					esc_html__( 'Explore Pro features →', 'query-forge' )
				),
				'separator'   => 'after',
			]
		);

		// Hidden controls for storing data.
		// frontend_available => true ensures these values are saved to the database
		// and available on the frontend, not just in the editor preview.
		$this->add_control(
			'qf_graph_state',
			[
				'type'              => Controls_Manager::HIDDEN,
				'frontend_available' => true,
			]
		);

		$this->add_control(
			'qf_logic_json',
			[
				'type'              => Controls_Manager::HIDDEN,
				'frontend_available' => true,
			]
		);

		$this->end_controls_section();

		// Layout Section.
		$this->start_controls_section(
			'section_layout',
			[
				'label' => __( 'Layout', 'query-forge' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_responsive_control(
			'columns',
			[
				'label'          => __( 'Columns', 'query-forge' ),
				'type'           => Controls_Manager::SELECT,
				'options'        => [
					'1' => '1',
					'2' => '2',
					'3' => '3',
					'4' => '4',
					'5' => '5',
					'6' => '6',
				],
				'default'        => '3',
				'tablet_default' => '2',
				'mobile_default' => '1',
				'selectors'      => [
					'{{WRAPPER}} .qf-grid' => 'grid-template-columns: repeat({{VALUE}}, 1fr);',
				],
			]
		);

		$this->add_control(
			'column_gap',
			[
				'label'     => __( 'Column Gap', 'query-forge' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 20,
				],
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .qf-grid' => 'column-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'row_gap',
			[
				'label'     => __( 'Row Gap', 'query-forge' ),
				'type'      => Controls_Manager::SLIDER,
				'default'   => [
					'size' => 20,
				],
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 100,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .qf-grid' => 'row-gap: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();

		// Card Style Section.
		$this->start_controls_section(
			'section_card_style',
			[
				'label' => __( 'Card Style', 'query-forge' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'display_type',
			[
				'label'   => __( 'Display Type', 'query-forge' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'canned',
				'options' => [
					'canned' => __( 'Canned Styles', 'query-forge' ),
					'template' => __( 'Custom Elementor Template', 'query-forge' ),
				],
			]
		);

		// Get available Elementor templates.
		$templates = $this->get_elementor_templates();

		$this->add_control(
			'elementor_template_id',
			[
				'label'     => __( 'Select Template', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'options'   => $templates,
				'default'   => '',
				'condition' => [
					'display_type' => 'template',
				],
			]
		);

		$this->add_control(
			'card_style',
			[
				'label'     => __( 'Card Style', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'vertical',
				'options'   => [
					'horizontal' => __( 'Horizontal Card', 'query-forge' ),
					'vertical'   => __( 'Vertical Card', 'query-forge' ),
					'minimal'    => __( 'Minimal List', 'query-forge' ),
					'grid'       => __( 'Grid Card', 'query-forge' ),
					'magazine'   => __( 'Magazine Style', 'query-forge' ),
				],
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		$this->end_controls_section();

		// Content Fields Section.
		$this->start_controls_section(
			'section_content_fields',
			[
				'label'     => __( 'Content Fields', 'query-forge' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		$this->add_control(
			'show_title',
			[
				'label'   => __( 'Show Title', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_excerpt',
			[
				'label'   => __( 'Show Excerpt', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'excerpt_length',
			[
				'label'     => __( 'Excerpt Length', 'query-forge' ),
				'type'      => Controls_Manager::NUMBER,
				'default'   => 100,
				'min'       => 10,
				'max'       => 500,
				'step'      => 10,
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_control(
			'show_date',
			[
				'label'   => __( 'Show Date', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_author',
			[
				'label'   => __( 'Show Author', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'show_image',
			[
				'label'   => __( 'Show Featured Image', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		$this->add_control(
			'image_size',
			[
				'label'     => __( 'Image Size', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'medium',
				'options'   => [
					'thumbnail' => __( 'Thumbnail', 'query-forge' ),
					'medium'    => __( 'Medium', 'query-forge' ),
					'large'     => __( 'Large', 'query-forge' ),
					'full'      => __( 'Full', 'query-forge' ),
				],
				'condition' => [
					'show_image' => 'yes',
				],
			]
		);

		$this->add_control(
			'link_target',
			[
				'label'   => __( 'Link Target', 'query-forge' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '_self',
				'options' => [
					'_self'  => __( 'Same Window', 'query-forge' ),
					'_blank' => __( 'New Window', 'query-forge' ),
				],
			]
		);

		$this->end_controls_section();

		// Pagination Section.
		$this->start_controls_section(
			'section_pagination',
			[
				'label' => __( 'Pagination', 'query-forge' ),
				'tab'   => Controls_Manager::TAB_CONTENT,
			]
		);

		$this->add_control(
			'show_pagination',
			[
				'label'   => __( 'Show Pagination', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);

		// Check if Pro is active
		$is_pro = defined( 'QUERY_FORGE_PRO_ACTIVE' ) && QUERY_FORGE_PRO_ACTIVE;
		
		// Build pagination options - Free only has Standard
		$pagination_options = [
			'standard' => __( 'Standard (Page Numbers)', 'query-forge' ),
		];
		
		// Add Pro options if Pro is active
		if ( $is_pro ) {
			$pagination_options['ajax']           = __( 'AJAX (Page Numbers)', 'query-forge' );
			$pagination_options['load_more']      = __( 'Load More Button', 'query-forge' );
			$pagination_options['infinite_scroll'] = __( 'Infinite Scroll', 'query-forge' );
		}
		
		$this->add_control(
			'pagination_type',
			[
				'label'     => __( 'Pagination Type', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'standard',
				'options'   => $pagination_options,
				'condition' => [
					'show_pagination' => 'yes',
				],
			]
		);
		
		// Show hint for Pro features if not Pro
		if ( ! $is_pro ) {
			$this->add_control(
				'pagination_pro_hint',
				[
					'type'        => Controls_Manager::RAW_HTML,
					'raw'         => sprintf(
						'<div style="font-size: 11px; color: #999; font-style: italic; margin-top: 5px;">%s <a href="%s" target="_blank" rel="noopener noreferrer" style="color: #5c4bde; text-decoration: none;">%s</a></div>',
						esc_html__( 'AJAX, Load More, and Infinite Scroll available in', 'query-forge' ),
						esc_url( 'https://queryforgeplugin.com' ),
						esc_html__( 'Pro', 'query-forge' )
					),
					'condition' => [
						'show_pagination' => 'yes',
					],
				]
			);
		}

		// Load More Button Text - Pro only
		if ( $is_pro ) {
			$this->add_control(
				'load_more_button_text',
				[
					'label'     => __( 'Button Text', 'query-forge' ),
					'type'      => Controls_Manager::TEXT,
					'default'   => __( 'Load More', 'query-forge' ),
					'condition' => [
						'show_pagination' => 'yes',
						'pagination_type' => 'load_more',
					],
				]
			);
		}

		// Infinite Scroll Offset - Pro only
		if ( $is_pro ) {
			$this->add_control(
				'infinite_scroll_offset',
				[
					'label'     => __( 'Scroll Offset (px)', 'query-forge' ),
					'type'      => Controls_Manager::NUMBER,
					'default'   => 200,
					'min'       => 0,
					'max'       => 1000,
					'step'      => 50,
					'condition' => [
						'show_pagination' => 'yes',
						'pagination_type' => 'infinite_scroll',
					],
					'description' => __( 'Distance from bottom of viewport to trigger loading (in pixels).', 'query-forge' ),
				]
			);
		}

		// Previous/Next Text for Standard and AJAX
		$prev_next_condition = [ 'standard' ];
		if ( $is_pro ) {
			$prev_next_condition[] = 'ajax';
		}
		
		$this->add_control(
			'pagination_prev_text',
			[
				'label'     => __( 'Previous Text', 'query-forge' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( '&laquo; Previous', 'query-forge' ),
				'condition' => [
					'show_pagination' => 'yes',
					'pagination_type' => $prev_next_condition,
				],
			]
		);

		$this->add_control(
			'pagination_next_text',
			[
				'label'     => __( 'Next Text', 'query-forge' ),
				'type'      => Controls_Manager::TEXT,
				'default'   => __( 'Next &raquo;', 'query-forge' ),
				'condition' => [
					'show_pagination' => 'yes',
					'pagination_type' => $prev_next_condition,
				],
			]
		);

		// Loading Text/Indicator (for AJAX, Load More, Infinite Scroll) - Pro only
		if ( $is_pro ) {
			$this->add_control(
				'loading_text',
				[
					'label'     => __( 'Loading Text', 'query-forge' ),
					'type'      => Controls_Manager::TEXT,
					'default'   => __( 'Loading...', 'query-forge' ),
					'condition' => [
						'show_pagination' => 'yes',
						'pagination_type' => [ 'ajax', 'load_more', 'infinite_scroll' ],
					],
					'description' => __( 'Text shown while content is loading.', 'query-forge' ),
				]
			);
		}

		$this->end_controls_section();

		// Typography Section.
		$this->start_controls_section(
			'section_typography',
			[
				'label'     => __( 'Typography', 'query-forge' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		$this->add_control(
			'title_typography',
			[
				'label'     => __( 'Title', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'title_typography',
				'selector'  => '{{WRAPPER}} .qf-card-title',
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_control(
			'excerpt_typography',
			[
				'label'     => __( 'Excerpt', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'excerpt_typography',
				'selector'  => '{{WRAPPER}} .qf-card-excerpt',
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_control(
			'meta_typography',
			[
				'label'     => __( 'Date & Author', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'meta_typography',
				'selector' => '{{WRAPPER}} .qf-card-meta',
			]
		);

		$this->end_controls_section();

		// Spacing Section.
		$this->start_controls_section(
			'section_spacing',
			[
				'label'     => __( 'Spacing', 'query-forge' ),
				'tab'       => Controls_Manager::TAB_STYLE,
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		$this->add_responsive_control(
			'card_padding',
			[
				'label'      => __( 'Card Padding', 'query-forge' ),
				'type'       => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', 'em', '%' ],
				'selectors'  => [
					'{{WRAPPER}} .qf-card' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_responsive_control(
			'content_spacing',
			[
				'label'     => __( 'Content Spacing', 'query-forge' ),
				'type'      => Controls_Manager::SLIDER,
				'range'     => [
					'px' => [
						'min' => 0,
						'max' => 50,
					],
				],
				'selectors' => [
					'{{WRAPPER}} .qf-card-content > * + *' => 'margin-top: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->end_controls_section();
	}

	/**
	 * Get available Elementor templates
	 *
	 * @return array Template options.
	 */
	private function get_elementor_templates() {
		$templates = [
			'' => __( '— Select Template —', 'query-forge' ),
		];

		// Get Elementor templates - include all template types that can be used as cards.
		$template_types = [ 'section', 'page', 'widget' ];

		$template_posts = get_posts(
			[
				'post_type'      => 'elementor_library',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Required to filter Elementor templates by type.
				'meta_query'     => [
					[
						'key'     => '_elementor_template_type',
						'value'   => $template_types,
						'compare' => 'IN',
					],
				],
			]
		);

		foreach ( $template_posts as $template_post ) {
			$templates[ $template_post->ID ] = $template_post->post_title;
		}

		return $templates;
	}

	/**
	 * Render widget output
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();
		
		// Store widget settings in data attribute for JavaScript access
		$widget_id = $this->get_id();
		$this->add_render_attribute( 'wrapper', 'data-qf-widget-id', $widget_id );
		$this->add_render_attribute( 'wrapper', 'data-qf-settings', wp_json_encode( [
			'logic_json' => $settings['qf_logic_json'] ?? '',
			'widget_settings' => [
				'display_type' => $settings['display_type'] ?? 'canned',
				'card_style' => $settings['card_style'] ?? 'vertical',
				'show_title' => $settings['show_title'] ?? 'yes',
				'show_excerpt' => $settings['show_excerpt'] ?? 'yes',
				'show_image' => $settings['show_image'] ?? 'yes',
				'show_date' => $settings['show_date'] ?? 'yes',
				'show_author' => $settings['show_author'] ?? 'yes',
				'excerpt_length' => $settings['excerpt_length'] ?? 100,
				'image_size' => $settings['image_size'] ?? 'medium',
				'elementor_template_id' => $settings['elementor_template_id'] ?? '',
				'pagination_type' => $settings['pagination_type'] ?? 'standard',
				'pagination_prev_text' => $settings['pagination_prev_text'] ?? '',
				'pagination_next_text' => $settings['pagination_next_text'] ?? '',
				'load_more_button_text' => $settings['load_more_button_text'] ?? '',
				'loading_text' => $settings['loading_text'] ?? '',
				'infinite_scroll_offset' => $settings['infinite_scroll_offset'] ?? 200,
			],
		] ) );

		// Check if query builder is configured.
		if ( empty( $settings['qf_logic_json'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="qf-placeholder">';
				echo '<p>' . esc_html__( 'Please configure Query Builder to display posts.', 'query-forge' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		// Get query from parser.
		$query = QF_Query_Parser::get_query( $settings['qf_logic_json'] );


		if ( ! $query || ! $query->have_posts() ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="qf-placeholder">';
				echo '<p>' . esc_html__( 'No posts found. Check your query settings.', 'query-forge' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		$display_type = ! empty( $settings['display_type'] ) ? $settings['display_type'] : 'canned';

		// Render grid.
		if ( 'template' === $display_type && ! empty( $settings['elementor_template_id'] ) ) {
			// Use custom Elementor template.
			$this->render_with_template( $query, $settings );
		} else {
			// Use canned styles.
			$card_style = ! empty( $settings['card_style'] ) ? $settings['card_style'] : 'vertical';
		?>
			<div <?php $this->print_render_attribute_string( 'wrapper' ); ?> class="qf-grid qf-card-style-<?php echo esc_attr( $card_style ); ?>">
				<?php
				while ( $query->have_posts() ) {
					$query->the_post();
					$this->render_card( $settings, $card_style );
				}
				wp_reset_postdata();
				?>
			</div>
			<?php
		}

		// Pagination.
		$pagination_type = ! empty( $settings['pagination_type'] ) ? $settings['pagination_type'] : 'standard';
		if ( ! empty( $settings['show_pagination'] ) && 'yes' === $settings['show_pagination'] ) {
			if ( 'standard' === $pagination_type || 'ajax' === $pagination_type ) {
				$this->render_pagination( $query, $settings );
			} elseif ( 'load_more' === $pagination_type ) {
				$this->render_load_more_button( $query, $settings );
			} elseif ( 'infinite_scroll' === $pagination_type ) {
				$this->render_infinite_scroll_trigger( $query, $settings );
			}
		}
	}

	/**
	 * Render posts using Elementor template
	 *
	 * @since 1.0.0
	 * @param \WP_Query|QF_Query_Result_Wrapper $query Query object.
	 * @param array                              $settings Widget settings.
	 */
	private function render_with_template( $query, $settings ) {
		$template_id = absint( $settings['elementor_template_id'] );

		if ( ! $template_id ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="qf-placeholder">';
				echo '<p>' . esc_html__( 'Please select an Elementor template.', 'query-forge' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		// Check if template exists.
		$template_post = get_post( $template_id );
		if ( ! $template_post || 'elementor_library' !== $template_post->post_type ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div class="qf-placeholder">';
				echo '<p>' . esc_html__( 'Selected template not found.', 'query-forge' ) . '</p>';
				echo '</div>';
			}
			return;
		}

		?>
		<div <?php $this->print_render_attribute_string( 'wrapper' ); ?> class="qf-grid qf-template-grid">
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				?>
				<div class="qf-template-item">
					<?php
					// Render Elementor template for this post.
					// Elementor's dynamic tags will automatically use the current post context.
					$elementor_instance = \Elementor\Plugin::instance();
					if ( $elementor_instance->frontend ) {
						// Get the template content.
						$content = $elementor_instance->frontend->get_builder_content_for_display( $template_id );
						if ( $content ) {
							echo wp_kses_post( $content );
						}
					}
					?>
				</div>
				<?php
			}
			wp_reset_postdata();
			?>
		</div>
		<?php
	}

	/**
	 * Render card based on style
	 *
	 * @param array  $settings Widget settings.
	 * @param string $style Card style.
	 */
	public function render_card( $settings, $style ) {
		$show_title   = ! empty( $settings['show_title'] ) && 'yes' === $settings['show_title'];
		$show_excerpt = ! empty( $settings['show_excerpt'] ) && 'yes' === $settings['show_excerpt'];
		$show_date    = ! empty( $settings['show_date'] ) && 'yes' === $settings['show_date'];
		$show_author  = ! empty( $settings['show_author'] ) && 'yes' === $settings['show_author'];
		$show_image   = ! empty( $settings['show_image'] ) && 'yes' === $settings['show_image'];
		$link_target  = ! empty( $settings['link_target'] ) ? $settings['link_target'] : '_self';

		?>
		<div class="qf-card qf-card-<?php echo esc_attr( $style ); ?>">
			<?php
			switch ( $style ) {
				case 'horizontal':
					$this->render_horizontal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
					break;
				case 'vertical':
					$this->render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
					break;
				case 'minimal':
					$this->render_minimal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
					break;
				case 'grid':
					$this->render_grid_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
					break;
				case 'magazine':
					$this->render_magazine_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
					break;
				default:
					$this->render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render horizontal card (image left, content right)
	 */
	private function render_horizontal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target ) {
		?>
		<div class="qf-card-inner qf-card-horizontal">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render vertical card (image top, content below)
	 */
	private function render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target ) {
		?>
		<div class="qf-card-inner qf-card-vertical">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render minimal list card (no image, text only)
	 */
	private function render_minimal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target ) {
		?>
		<div class="qf-card-inner qf-card-minimal">
			<div class="qf-card-content">
				<?php
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render grid card (square image, compact content)
	 */
	private function render_grid_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target ) {
		?>
		<div class="qf-card-inner qf-card-grid">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render magazine style card (large image with overlay)
	 */
	private function render_magazine_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target ) {
		?>
		<div class="qf-card-inner qf-card-magazine">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
					<?php if ( $show_title ) : ?>
						<div class="qf-card-overlay">
							<?php $this->render_title( $link_target ); ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( ! $show_image && $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render title
	 *
	 * @param string $link_target Link target.
	 */
	private function render_title( $link_target ) {
		?>
		<h3 class="qf-card-title">
			<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>">
				<?php the_title(); ?>
			</a>
		</h3>
		<?php
	}

	/**
	 * Render excerpt
	 *
	 * @param array $settings Widget settings.
	 */
	private function render_excerpt( $settings ) {
		$excerpt_length = ! empty( $settings['excerpt_length'] ) ? absint( $settings['excerpt_length'] ) : 100;
		$excerpt        = get_the_excerpt();

		// If excerpt is empty, use first 100 chars of content.
		if ( empty( $excerpt ) ) {
			$content = get_the_content();
			$content = wp_strip_all_tags( $content );
			$excerpt = wp_trim_words( $content, $excerpt_length / 10, '...' );
		} else {
			$excerpt = wp_trim_words( $excerpt, $excerpt_length / 10, '...' );
		}

		?>
		<div class="qf-card-excerpt">
			<?php echo esc_html( $excerpt ); ?>
		</div>
		<?php
	}

	/**
	 * Render meta (date and author)
	 *
	 * @param bool $show_date Show date.
	 * @param bool $show_author Show author.
	 */
	private function render_meta( $show_date, $show_author ) {
		?>
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
		<?php
	}

	/**
	 * Render featured image
	 *
	 * @param array  $settings Widget settings.
	 * @param string $link_target Link target.
	 */
	private function render_featured_image( $settings, $link_target ) {
		$image_size = ! empty( $settings['image_size'] ) ? $settings['image_size'] : 'medium';
		$image_id   = get_post_thumbnail_id();

		if ( $image_id ) {
			$image_url = wp_get_attachment_image_url( $image_id, $image_size );
			$image_alt = get_post_meta( $image_id, '_wp_attachment_image_alt', true );
			?>
			<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>">
				<img src="<?php echo esc_url( $image_url ); ?>" alt="<?php echo esc_attr( $image_alt ? $image_alt : get_the_title() ); ?>" />
			</a>
			<?php
		} else {
			// CSS placeholder.
			?>
			<div class="qf-card-image-placeholder">
				<span class="qf-placeholder-icon">📷</span>
			</div>
			<?php
		}
	}

	/**
	 * Render pagination
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                                        $settings Widget settings.
	 */
	public function render_pagination( $query, $settings = [] ) {
		// Get the current page number from the query string.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['paged'] is a public pagination parameter, not form data.
		$paged = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$pagination_type = ! empty( $settings['pagination_type'] ) ? $settings['pagination_type'] : 'standard';
		$is_ajax = 'ajax' === $pagination_type;

		// Get the current page URL without pagination parameter.
		global $wp;
		$current_url = home_url( add_query_arg( [], $wp->request ) );
		
		// Remove existing paged parameter if present.
		$base = remove_query_arg( 'paged', $current_url );
		
		// Add trailing slash if needed and query string separator.
		$base = trailingslashit( $base );
		if ( strpos( $base, '?' ) !== false ) {
			$format = '&paged=%#%';
		} else {
			$format = '?paged=%#%';
		}

		$max_pages = $query->max_num_pages ?? 1;

		$prev_text = ! empty( $settings['pagination_prev_text'] ) ? $settings['pagination_prev_text'] : __( '&laquo; Previous', 'query-forge' );
		$next_text = ! empty( $settings['pagination_next_text'] ) ? $settings['pagination_next_text'] : __( 'Next &raquo;', 'query-forge' );

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
			$ajax_class = $is_ajax ? ' qf-pagination-ajax' : '';
			$widget_id = $this->get_id();
			echo '<div class="qf-pagination' . esc_attr( $ajax_class ) . '" data-widget-id="' . esc_attr( $widget_id ) . '">' . wp_kses_post( $pagination ) . '</div>';
		}
	}

	/**
	 * Render Load More button
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                                        $settings Widget settings.
	 */
	private function render_load_more_button( $query, $settings = [] ) {
		$max_pages = $query->max_num_pages ?? 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['paged'] is a public pagination parameter, not form data.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		if ( $current_page >= $max_pages ) {
			return; // No more pages to load.
		}

		$widget_id = $this->get_id();
		$next_page = $current_page + 1;
		$button_text = ! empty( $settings['load_more_button_text'] ) ? $settings['load_more_button_text'] : __( 'Load More', 'query-forge' );
		$loading_text = ! empty( $settings['loading_text'] ) ? $settings['loading_text'] : __( 'Loading...', 'query-forge' );
		?>
		<div class="qf-load-more-wrapper" data-widget-id="<?php echo esc_attr( $widget_id ); ?>" data-next-page="<?php echo esc_attr( $next_page ); ?>" data-loading-text="<?php echo esc_attr( $loading_text ); ?>">
			<button type="button" class="qf-load-more-button">
				<?php echo esc_html( $button_text ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render Infinite Scroll trigger
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                                        $settings Widget settings.
	 */
	private function render_infinite_scroll_trigger( $query, $settings = [] ) {
		$max_pages = $query->max_num_pages ?? 1;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- $_GET['paged'] is a public pagination parameter, not form data.
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;

		if ( $current_page >= $max_pages ) {
			return; // No more pages to load.
		}

		$widget_id = $this->get_id();
		$next_page = $current_page + 1;
		$scroll_offset = ! empty( $settings['infinite_scroll_offset'] ) ? absint( $settings['infinite_scroll_offset'] ) : 200;
		?>
		<div class="qf-infinite-scroll-trigger" data-widget-id="<?php echo esc_attr( $widget_id ); ?>" data-next-page="<?php echo esc_attr( $next_page ); ?>" data-max-pages="<?php echo esc_attr( $max_pages ); ?>" data-scroll-offset="<?php echo esc_attr( $scroll_offset ); ?>"></div>
		<?php
	}
}

