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

		// Card Design Section (typography, colors, alignment, and button alignment).
		$this->start_controls_section(
			'section_card_design',
			[
				'label'     => __( 'Card Design', 'query-forge' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		// Typography subgroup.
		$this->add_control(
			'card_design_typography_heading',
			[
				'label'     => __( 'Typography', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
			]
		);

		$this->add_control(
			'card_title_typography_label',
			[
				'label'     => __( 'Title', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'card_title_typography',
				'selector'  => '{{WRAPPER}} .qf-card-title',
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_control(
			'card_excerpt_typography_label',
			[
				'label'     => __( 'Excerpt', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'card_excerpt_typography',
				'selector'  => '{{WRAPPER}} .qf-card-excerpt',
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_control(
			'card_meta_typography_label',
			[
				'label' => __( 'Meta', 'query-forge' ),
				'type'  => Controls_Manager::HEADING,
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'     => 'card_meta_typography',
				'selector' => '{{WRAPPER}} .qf-card-meta',
			]
		);

		$this->add_control(
			'card_button_typography_label',
			[
				'label'     => __( 'Button', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'condition' => [
					'show_read_more' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'card_button_typography',
				'selector'  => '{{WRAPPER}} .qf-card-button',
				'condition' => [
					'show_read_more' => 'yes',
				],
			]
		);

		// Element Alignment subgroup.
		$this->add_control(
			'element_alignment_heading',
			[
				'label'     => __( 'Element Alignment', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'title_align',
			[
				'label'     => __( 'Title Align', 'query-forge' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-card-title' => 'text-align: {{VALUE}};',
				],
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'meta_align',
			[
				'label'   => __( 'Meta Align', 'query-forge' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-card-meta' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_responsive_control(
			'excerpt_align',
			[
				'label'   => __( 'Excerpt Align', 'query-forge' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-card-excerpt' => 'text-align: {{VALUE}};',
				],
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'button_align',
			[
				'label'   => __( 'Button Align', 'query-forge' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-card-button-wrapper' => 'text-align: {{VALUE}};',
				],
				'condition' => [
					'show_read_more' => 'yes',
				],
			]
		);

		// Colors subgroup.
		$this->add_control(
			'card_design_colors_heading',
			[
				'label'     => __( 'Colors', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_control(
			'card_background_color',
			[
				'label'     => __( 'Card Background', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-card' => 'background-color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_title_color',
			[
				'label'     => __( 'Title', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-card-title, {{WRAPPER}} .qf-card-title a' => 'color: {{VALUE}};',
				],
				'condition' => [
					'show_title' => 'yes',
				],
			]
		);

		$this->add_control(
			'card_meta_color',
			[
				'label'     => __( 'Meta', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-card-meta, {{WRAPPER}} .qf-card-meta a' => 'color: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_excerpt_color',
			[
				'label'     => __( 'Excerpt', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-card-excerpt' => 'color: {{VALUE}};',
				],
				'condition' => [
					'show_excerpt' => 'yes',
				],
			]
		);

		$this->add_control(
			'card_button_color',
			[
				'label'     => __( 'Button', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-card-button' => 'background-color: {{VALUE}};',
				],
				'condition' => [
					'show_read_more' => 'yes',
				],
			]
		);

		// Layout & Style subgroup.
		$this->add_control(
			'card_design_layout_heading',
			[
				'label'     => __( 'Layout & Style', 'query-forge' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			]
		);

		$this->add_responsive_control(
			'card_content_alignment',
			[
				'label'   => __( 'Alignment', 'query-forge' ),
				'type'    => Controls_Manager::CHOOSE,
				'options' => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-card-content' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'card_image_ratio',
			[
				'label'   => __( 'Image Ratio', 'query-forge' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '16/9',
				'options' => [
					'16/9' => '16:9',
					'4/3'  => '4:3',
					'1/1'  => '1:1',
				],
				'selectors' => [
					'{{WRAPPER}} .qf-card-image img' => 'aspect-ratio: {{VALUE}}; object-fit: cover;',
				],
				'condition' => [
					'show_image' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'card_border_radius',
			[
				'label'      => __( 'Border Radius', 'query-forge' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => [ 'px' ],
				'range'      => [
					'px' => [
						'min' => 0,
						'max' => 40,
					],
				],
				'selectors'  => [
					'{{WRAPPER}} .qf-card' => 'border-radius: {{SIZE}}{{UNIT}};',
				],
			]
		);

		$this->add_control(
			'card_shadow',
			[
				'label'   => __( 'Card Shadow', 'query-forge' ),
				'type'    => Controls_Manager::SELECT,
				'default' => 'soft',
				'options' => [
					'none'  => __( 'None', 'query-forge' ),
					'soft'  => __( 'Soft', 'query-forge' ),
					'strong'=> __( 'Strong', 'query-forge' ),
				],
				'selectors' => [
					'{{WRAPPER}} .qf-card' => 'box-shadow: {{VALUE}};',
				],
			]
		);

		$this->end_controls_section();

		// Results Summary Section.
		$this->start_controls_section(
			'section_results_summary',
			[
				'label'     => __( 'Results Summary', 'query-forge' ),
				'tab'       => Controls_Manager::TAB_CONTENT,
				'condition' => [
					'display_type' => 'canned',
				],
			]
		);

		$this->add_control(
			'show_results_summary',
			[
				'label'   => __( 'Show Results Summary', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			]
		);

		$this->add_control(
			'results_summary_position',
			[
				'label'     => __( 'Position', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'above_grid',
				'options'   => [
					'above_grid'       => __( 'Above Grid', 'query-forge' ),
					'above_pagination' => __( 'Above Pagination', 'query-forge' ),
					'below_pagination' => __( 'Below Pagination', 'query-forge' ),
				],
				'condition' => [
					'show_results_summary' => 'yes',
				],
			]
		);

		$this->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name'      => 'results_summary_typography',
				'selector'  => '{{WRAPPER}} .qf-results-summary',
				'condition' => [
					'show_results_summary' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'results_summary_color',
			[
				'label'     => __( 'Text Color', 'query-forge' ),
				'type'      => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} .qf-results-summary' => 'color: {{VALUE}};',
				],
				'condition' => [
					'show_results_summary' => 'yes',
				],
			]
		);

		$this->add_responsive_control(
			'results_summary_align',
			[
				'label'     => __( 'Alignment', 'query-forge' ),
				'type'      => Controls_Manager::CHOOSE,
				'options'   => [
					'left'   => [
						'title' => __( 'Left', 'query-forge' ),
						'icon'  => 'eicon-text-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'query-forge' ),
						'icon'  => 'eicon-text-align-center',
					],
					'right'  => [
						'title' => __( 'Right', 'query-forge' ),
						'icon'  => 'eicon-text-align-right',
					],
				],
				'default'   => '',
				'selectors' => [
					'{{WRAPPER}} .qf-results-summary' => 'text-align: {{VALUE}};',
				],
				'condition' => [
					'show_results_summary' => 'yes',
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
			'show_read_more',
			[
				'label'   => __( 'Show Read More Button', 'query-forge' ),
				'type'    => Controls_Manager::SWITCHER,
				'default' => '',
			]
		);

		$this->add_control(
			'card_button_position',
			[
				'label'     => __( 'Button Position', 'query-forge' ),
				'type'      => Controls_Manager::SELECT,
				'default'   => 'bottom',
				'options'   => [
					'top'    => __( 'Top', 'query-forge' ),
					'bottom' => __( 'Bottom', 'query-forge' ),
				],
				'condition' => [
					'show_read_more' => 'yes',
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

		$pagination_options = [
			'standard' => __( 'Standard (Page Numbers)', 'query-forge' ),
		];
		
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

		$prev_next_condition = [ 'standard' ];
		
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

		$display_type = 'canned';

		$show_results_summary = ! empty( $settings['show_results_summary'] ) && 'yes' === $settings['show_results_summary'];
		$results_position     = ! empty( $settings['results_summary_position'] ) ? $settings['results_summary_position'] : 'above_grid';

		// Render results summary above grid, if configured.
		if ( $show_results_summary && 'above_grid' === $results_position ) {
			$this->render_results_summary( $query );
		}

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
		if ( $show_results_summary && 'above_pagination' === $results_position ) {
			$this->render_results_summary( $query );
		}

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

		if ( $show_results_summary && 'below_pagination' === $results_position ) {
			$this->render_results_summary( $query );
		}
	}

	/**
	 * Render results summary.
	 *
	 * @param \WP_Query $query Query object.
	 */
	private function render_results_summary( $query ) {
		if ( ! $query || ! $query->have_posts() ) {
			return;
		}

		$total   = (int) $query->found_posts;
		$per_page = (int) $query->get( 'posts_per_page' );
		if ( $per_page <= 0 ) {
			$per_page = $total;
		}

		$paged = (int) $query->get( 'paged' );
		if ( $paged < 1 ) {
			$paged = 1;
		}

		$start = ( ( $paged - 1 ) * $per_page ) + 1;
		$end   = min( $paged * $per_page, $total );

		if ( $total <= 0 || $start > $end ) {
			return;
		}

		/* translators: 1: first result number, 2: last result number, 3: total results. */
		$text = sprintf(
			esc_html__( 'Showing %1$d–%2$d of %3$d results', 'query-forge' ),
			$start,
			$end,
			$total
		);

		echo '<div class="qf-results-summary">' . esc_html( $text ) . '</div>';
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
		$show_button  = ! empty( $settings['show_read_more'] ) && 'yes' === $settings['show_read_more'];
		$button_position = ! empty( $settings['card_button_position'] ) ? $settings['card_button_position'] : 'bottom';

		?>
		<div class="qf-card qf-card-<?php echo esc_attr( $style ); ?>">
			<?php
			switch ( $style ) {
				case 'horizontal':
					$this->render_horizontal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
					break;
				case 'vertical':
					$this->render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
					break;
				case 'minimal':
					$this->render_minimal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
					break;
				case 'grid':
					$this->render_grid_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
					break;
				case 'magazine':
					$this->render_magazine_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
					break;
				default:
					$this->render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position );
			}
			?>
		</div>
		<?php
	}

	/**
	 * Render horizontal card (image left, content right)
	 */
	private function render_horizontal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
		?>
		<div class="qf-card-inner qf-card-horizontal">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_button && 'top' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				if ( $show_button && 'bottom' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render vertical card (image top, content below)
	 */
	private function render_vertical_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
		?>
		<div class="qf-card-inner qf-card-vertical">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_button && 'top' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				if ( $show_button && 'bottom' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render minimal list card (no image, text only)
	 */
	private function render_minimal_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
		?>
		<div class="qf-card-inner qf-card-minimal">
			<div class="qf-card-content">
				<?php
				if ( $show_button && 'top' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				if ( $show_button && 'bottom' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render grid card (square image, compact content)
	 */
	private function render_grid_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
		?>
		<div class="qf-card-inner qf-card-grid">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
				</div>
			<?php endif; ?>
			<div class="qf-card-content">
				<?php
				if ( $show_button && 'top' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				if ( $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				if ( $show_button && 'bottom' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render magazine style card (large image with overlay)
	 */
	private function render_magazine_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
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
				if ( $show_button && 'top' === $button_position ) {
					$this->render_read_more_button( $link_target );
				}
				if ( ! $show_image && $show_title ) {
					$this->render_title( $link_target );
				}
				if ( $show_excerpt ) {
					$this->render_excerpt( $settings );
				}
				if ( $show_date || $show_author ) {
					$this->render_meta( $show_date, $show_author );
				}
				if ( $show_button && 'bottom' === $button_position ) {
					$this->render_read_more_button( $link_target );
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
				<?php echo esc_html( get_the_title() ); ?>
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
										<?php echo esc_html( get_the_author() ); ?>
									</a>
								</span>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render read more button
	 *
	 * @param string $link_target Link target.
	 */
	private function render_read_more_button( $link_target ) {
		?>
		<div class="qf-card-button-wrapper">
			<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>" class="qf-card-button">
				<?php echo esc_html__( 'Read More', 'query-forge' ); ?>
			</a>
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

