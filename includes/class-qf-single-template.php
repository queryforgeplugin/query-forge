<?php
/**
 * Single Template support for Query Forge.
 *
 * @package Query_Forge
 */

namespace Query_Forge;

use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transient-backed single view settings keyed by block/widget instance id.
 */
class QF_Single_Template {

	const QUERY_VAR = 'qfr';

	/**
	 * The 5 allowed style slugs in Free.
	 *
	 * @return string[]
	 */
	public static function allowed_styles(): array {
		return [ 'vertical', 'horizontal', 'minimal-list', 'grid-card', 'magazine' ];
	}

	/**
	 * Store single template settings from a block render.
	 *
	 * @param string $instance_id The qfInstanceId attribute value (sanitized key recommended).
	 * @param array  $attributes  Block attributes.
	 */
	public static function store_from_block( string $instance_id, array $attributes ): void {
		if ( '' === $instance_id ) {
			return;
		}
		$key = 'qf_single_instance_' . sanitize_key( $instance_id );
		if ( empty( $attributes['singleTemplateEnabled'] ) ) {
			delete_transient( $key );
			return;
		}
		set_transient(
			$key,
			self::build_settings_from_block_attributes( $attributes ),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Store single template settings from an Elementor widget render.
	 *
	 * @param string $widget_id Widget ID from $this->get_id().
	 * @param array  $settings  Elementor widget settings.
	 */
	public static function store_from_elementor( string $widget_id, array $settings ): void {
		if ( '' === $widget_id ) {
			return;
		}
		$key = 'qf_single_instance_' . sanitize_key( $widget_id );
		if ( empty( $settings['single_template_enabled'] ) || 'yes' !== $settings['single_template_enabled'] ) {
			delete_transient( $key );
			return;
		}
		set_transient(
			$key,
			self::build_settings_from_elementor_settings( $settings ),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Build the normalized settings array from block attributes.
	 *
	 * @param array $a Block attributes.
	 * @return array<string, mixed>
	 */
	private static function build_settings_from_block_attributes( array $a ): array {
		$style = sanitize_key( $a['singleTemplateStyle'] ?? 'vertical' );
		if ( ! in_array( $style, self::allowed_styles(), true ) ) {
			$style = 'vertical';
		}
		return [
			'style'                  => $style,
			'singleShowTitle'        => ! empty( $a['singleShowTitle'] ),
			'singleShowImage'        => ! empty( $a['singleShowImage'] ),
			'singleImagePosition'    => $a['singleImagePosition'] ?? 'above-title',
			'singleImageSize'        => $a['singleImageSize'] ?? 'full',
			'singleShowContent'      => ! empty( $a['singleShowContent'] ),
			'singleShowExcerpt'      => ! empty( $a['singleShowExcerpt'] ),
			'singleShowDate'         => ! empty( $a['singleShowDate'] ),
			'singleShowAuthor'       => ! empty( $a['singleShowAuthor'] ),
			'singleShowAuthorAvatar' => ! empty( $a['singleShowAuthorAvatar'] ),
			'singleShowTerms'        => ! empty( $a['singleShowTerms'] ),
			'singleShowNavigation'   => ! empty( $a['singleShowNavigation'] ),
			'singleTitleFontSize'    => (int) ( $a['singleTitleFontSize'] ?? 0 ),
			'singleTitleColor'       => (string) ( $a['singleTitleColor'] ?? '' ),
			'singleTitleAlignment'   => $a['singleTitleAlignment'] ?? 'left',
			'singleContentFontSize'  => (int) ( $a['singleContentFontSize'] ?? 0 ),
			'singleContentLineHeight' => (float) ( $a['singleContentLineHeight'] ?? 0 ),
			'singleContentColor'     => (string) ( $a['singleContentColor'] ?? '' ),
			'singleExcerptFontSize'  => (int) ( $a['singleExcerptFontSize'] ?? 0 ),
			'singleExcerptColor'     => (string) ( $a['singleExcerptColor'] ?? '' ),
			'singleExcerptAlignment' => $a['singleExcerptAlignment'] ?? 'left',
			'singleDateFormat'       => (string) ( $a['singleDateFormat'] ?? '' ),
			'singleDateFontSize'     => (int) ( $a['singleDateFontSize'] ?? 0 ),
			'singleDateColor'        => (string) ( $a['singleDateColor'] ?? '' ),
			'singleAuthorFontSize'   => (int) ( $a['singleAuthorFontSize'] ?? 0 ),
			'singleAuthorColor'      => (string) ( $a['singleAuthorColor'] ?? '' ),
			'singleTermsStyle'       => $a['singleTermsStyle'] ?? 'pills',
			'singleTermsFontSize'    => (int) ( $a['singleTermsFontSize'] ?? 0 ),
			'singleTermsColor'       => (string) ( $a['singleTermsColor'] ?? '' ),
			'singleNavFontSize'      => (int) ( $a['singleNavFontSize'] ?? 0 ),
			'singleNavColor'         => (string) ( $a['singleNavColor'] ?? '' ),
			'singleNavPrevLabel'     => (string) ( $a['singleNavPrevLabel'] ?? 'Previous' ),
			'singleNavNextLabel'     => (string) ( $a['singleNavNextLabel'] ?? 'Next' ),
		];
	}

	/**
	 * Build the normalized settings array from Elementor widget settings.
	 *
	 * @param array $s Widget settings.
	 * @return array<string, mixed>
	 */
	private static function build_settings_from_elementor_settings( array $s ): array {
		$style = sanitize_key( $s['single_template_style'] ?? 'vertical' );
		if ( ! in_array( $style, self::allowed_styles(), true ) ) {
			$style = 'vertical';
		}
		return [
			'style'                  => $style,
			'singleShowTitle'        => ! empty( $s['single_show_title'] ) && 'yes' === $s['single_show_title'],
			'singleShowImage'        => ! empty( $s['single_show_image'] ) && 'yes' === $s['single_show_image'],
			'singleImagePosition'    => $s['single_image_position'] ?? 'above-title',
			'singleImageSize'        => $s['single_image_size'] ?? 'full',
			'singleShowContent'      => ! empty( $s['single_show_content'] ) && 'yes' === $s['single_show_content'],
			'singleShowExcerpt'      => ! empty( $s['single_show_excerpt'] ) && 'yes' === $s['single_show_excerpt'],
			'singleShowDate'         => ! empty( $s['single_show_date'] ) && 'yes' === $s['single_show_date'],
			'singleShowAuthor'       => ! empty( $s['single_show_author'] ) && 'yes' === $s['single_show_author'],
			'singleShowAuthorAvatar' => ! empty( $s['single_show_author_avatar'] ) && 'yes' === $s['single_show_author_avatar'],
			'singleShowTerms'        => ! empty( $s['single_show_terms'] ) && 'yes' === $s['single_show_terms'],
			'singleShowNavigation'   => ! empty( $s['single_show_navigation'] ) && 'yes' === $s['single_show_navigation'],
			'singleTitleFontSize'    => (int) ( $s['single_title_font_size'] ?? 0 ),
			'singleTitleColor'       => (string) ( $s['single_title_color'] ?? '' ),
			'singleTitleAlignment'   => $s['single_title_alignment'] ?? 'left',
			'singleContentFontSize'  => (int) ( $s['single_content_font_size'] ?? 0 ),
			'singleContentLineHeight' => (float) ( $s['single_content_line_height'] ?? 0 ),
			'singleContentColor'     => (string) ( $s['single_content_color'] ?? '' ),
			'singleExcerptFontSize'  => (int) ( $s['single_excerpt_font_size'] ?? 0 ),
			'singleExcerptColor'     => (string) ( $s['single_excerpt_color'] ?? '' ),
			'singleExcerptAlignment' => $s['single_excerpt_alignment'] ?? 'left',
			'singleDateFormat'       => (string) ( $s['single_date_format'] ?? '' ),
			'singleDateFontSize'     => (int) ( $s['single_date_font_size'] ?? 0 ),
			'singleDateColor'        => (string) ( $s['single_date_color'] ?? '' ),
			'singleAuthorFontSize'   => (int) ( $s['single_author_font_size'] ?? 0 ),
			'singleAuthorColor'      => (string) ( $s['single_author_color'] ?? '' ),
			'singleTermsStyle'       => $s['single_terms_style'] ?? 'pills',
			'singleTermsFontSize'    => (int) ( $s['single_terms_font_size'] ?? 0 ),
			'singleTermsColor'       => (string) ( $s['single_terms_color'] ?? '' ),
			'singleNavFontSize'      => (int) ( $s['single_nav_font_size'] ?? 0 ),
			'singleNavColor'         => (string) ( $s['single_nav_color'] ?? '' ),
			'singleNavPrevLabel'     => (string) ( $s['single_nav_prev_label'] ?? 'Previous' ),
			'singleNavNextLabel'     => (string) ( $s['single_nav_next_label'] ?? 'Next' ),
		];
	}
}

/**
 * Register Single Template controls on the Elementor widget.
 * Called from widgets/class-smart-loop-grid.php if this function exists.
 *
 * @param \Elementor\Widget_Base $widget The widget instance.
 */
function qf_register_elementor_single_template_controls( $widget ): void {
	$widget->start_controls_section(
		'section_single_template',
		[
			'label' => __( 'Single Template', 'query-forge' ),
			'tab'   => Controls_Manager::TAB_CONTENT,
		]
	);

	$widget->add_control(
		'single_template_enabled',
		[
			'label'       => __( 'Enable single template', 'query-forge' ),
			'type'        => Controls_Manager::SWITCHER,
			'default'     => '',
			'description' => __( 'Save the page once to activate single templates.', 'query-forge' ),
		]
	);

	$widget->add_control(
		'single_template_style',
		[
			'label'     => __( 'Style', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'vertical',
			'options'   => [
				'vertical'     => __( 'Vertical', 'query-forge' ),
				'horizontal'   => __( 'Horizontal', 'query-forge' ),
				'minimal-list' => __( 'Minimal List', 'query-forge' ),
				'grid-card'    => __( 'Grid Card', 'query-forge' ),
				'magazine'     => __( 'Magazine', 'query-forge' ),
			],
			'condition' => [ 'single_template_enabled' => 'yes' ],
		]
	);

	$widget->end_controls_section();

	$widget->start_controls_section(
		'section_single_page_controls',
		[
			'label'     => __( 'Page Controls', 'query-forge' ),
			'tab'       => Controls_Manager::TAB_CONTENT,
			'condition' => [ 'single_template_enabled' => 'yes' ],
		]
	);

	foreach (
		[
			'single_show_title'      => __( 'Title', 'query-forge' ),
			'single_show_image'      => __( 'Featured Image', 'query-forge' ),
			'single_show_content'    => __( 'Content', 'query-forge' ),
			'single_show_excerpt'    => __( 'Excerpt', 'query-forge' ),
			'single_show_date'       => __( 'Date', 'query-forge' ),
			'single_show_author'     => __( 'Author', 'query-forge' ),
			'single_show_terms'      => __( 'Categories and Tags', 'query-forge' ),
			'single_show_navigation' => __( 'Previous / Next Navigation', 'query-forge' ),
		] as $control_id => $label
	) {
		$widget->add_control(
			$control_id,
			[
				'label'   => $label,
				'type'    => Controls_Manager::SWITCHER,
				'default' => 'yes',
			]
		);
	}

	$widget->add_control(
		'single_title_font_size',
		[
			'label'     => __( 'Title Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_title' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_title_color',
		[
			'label'     => __( 'Title Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_title' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_title_alignment',
		[
			'label'     => __( 'Title Alignment', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'left',
			'options'   => [
				'left'   => __( 'Left', 'query-forge' ),
				'center' => __( 'Center', 'query-forge' ),
				'right'  => __( 'Right', 'query-forge' ),
			],
			'condition' => [ 'single_show_title' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_image_position',
		[
			'label'     => __( 'Image Position', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'above-title',
			'options'   => [
				'above-title' => __( 'Above Title', 'query-forge' ),
				'below-title' => __( 'Below Title', 'query-forge' ),
				'background'  => __( 'Background', 'query-forge' ),
			],
			'condition' => [ 'single_show_image' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_image_size',
		[
			'label'     => __( 'Image Size', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'full',
			'options'   => [
				'thumbnail' => __( 'Thumbnail', 'query-forge' ),
				'medium'    => __( 'Medium', 'query-forge' ),
				'large'     => __( 'Large', 'query-forge' ),
				'full'      => __( 'Full', 'query-forge' ),
			],
			'condition' => [ 'single_show_image' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_content_font_size',
		[
			'label'     => __( 'Content Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_content' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_content_line_height',
		[
			'label'     => __( 'Content Line Height', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_content' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_content_color',
		[
			'label'     => __( 'Content Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_content' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_excerpt_font_size',
		[
			'label'     => __( 'Excerpt Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_excerpt' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_excerpt_color',
		[
			'label'     => __( 'Excerpt Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_excerpt' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_excerpt_alignment',
		[
			'label'     => __( 'Excerpt Alignment', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'left',
			'options'   => [
				'left'   => __( 'Left', 'query-forge' ),
				'center' => __( 'Center', 'query-forge' ),
				'right'  => __( 'Right', 'query-forge' ),
			],
			'condition' => [ 'single_show_excerpt' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_date_format',
		[
			'label'       => __( 'Date Format', 'query-forge' ),
			'type'        => Controls_Manager::TEXT,
			'default'     => '',
			'placeholder' => 'F j, Y',
			'condition'   => [ 'single_show_date' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_date_font_size',
		[
			'label'     => __( 'Date Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_date' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_date_color',
		[
			'label'     => __( 'Date Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_date' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_show_author_avatar',
		[
			'label'     => __( 'Show Avatar', 'query-forge' ),
			'type'      => Controls_Manager::SWITCHER,
			'default'   => 'yes',
			'condition' => [ 'single_show_author' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_author_font_size',
		[
			'label'     => __( 'Author Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_author' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_author_color',
		[
			'label'     => __( 'Author Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_author' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_terms_style',
		[
			'label'     => __( 'Terms Style', 'query-forge' ),
			'type'      => Controls_Manager::SELECT,
			'default'   => 'pills',
			'options'   => [
				'pills' => __( 'Pills', 'query-forge' ),
				'plain' => __( 'Plain', 'query-forge' ),
				'comma' => __( 'Comma', 'query-forge' ),
			],
			'condition' => [ 'single_show_terms' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_terms_font_size',
		[
			'label'     => __( 'Terms Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_terms' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_terms_color',
		[
			'label'     => __( 'Terms Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_terms' => 'yes' ],
		]
	);

	$widget->add_control(
		'single_nav_prev_label',
		[
			'label'     => __( 'Prev Label', 'query-forge' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'Previous',
			'condition' => [ 'single_show_navigation' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_nav_next_label',
		[
			'label'     => __( 'Next Label', 'query-forge' ),
			'type'      => Controls_Manager::TEXT,
			'default'   => 'Next',
			'condition' => [ 'single_show_navigation' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_nav_font_size',
		[
			'label'     => __( 'Nav Font Size', 'query-forge' ),
			'type'      => Controls_Manager::NUMBER,
			'default'   => 0,
			'condition' => [ 'single_show_navigation' => 'yes' ],
		]
	);
	$widget->add_control(
		'single_nav_color',
		[
			'label'     => __( 'Nav Color', 'query-forge' ),
			'type'      => Controls_Manager::COLOR,
			'default'   => '',
			'condition' => [ 'single_show_navigation' => 'yes' ],
		]
	);

	$widget->end_controls_section();
}
