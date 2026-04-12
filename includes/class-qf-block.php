<?php
/**
 * Gutenberg dynamic block: Query Forge output (logic ported from Elementor widget).
 *
 * @package Query_Forge
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and renders the query-forge/builder block.
 */
class QF_Block {

	/**
	 * Per-request ordinal when multiple blocks exist on one page (stable instance id).
	 *
	 * @var int
	 */
	private static $render_instance_ordinal = 0;

	/**
	 * Unique wrapper ID for this render (pagination / load-more JS).
	 *
	 * @var string
	 */
	private $wrapper_id = '';

	/**
	 * Register block type with metadata from block.json.
	 */
	public function register(): void {
		wp_register_style(
			'query_forge_widget_block',
			QUERY_FORGE_URL . 'assets/css/qf-widget.css',
			[],
			QUERY_FORGE_VERSION
		);

		register_block_type(
			QUERY_FORGE_PATH . 'block.json',
			[
				'render_callback' => [ $this, 'render_block' ],
				'style'           => 'query_forge_widget_block',
			]
		);
	}

	/**
	 * Front-end AJAX pagination / load-more (same script as Elementor widget; not needed in editor preview).
	 */
	private function enqueue_frontend_widget_script(): void {
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
			QF_Frontend_Search::get_widget_script_data()
		);
	}

	/**
	 * Server-side render callback.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Inner blocks (unused).
	 * @param \WP_Block $block     Block instance (wrapper attributes).
	 * @return string
	 */
	public function render_block( array $attributes, string $content, $block = null ): string {
		// REST_REQUEST is set when ServerSideRender calls this via the blocks REST route.
		// This render_callback is block-only; revisit if ever reused from another REST route.
		$is_editor_preview = defined( 'REST_REQUEST' ) && REST_REQUEST;

		if ( ! $is_editor_preview ) {
			$this->enqueue_frontend_widget_script();
		}

		++self::$render_instance_ordinal;
		$logic_raw = isset( $attributes['logicJson'] ) ? (string) $attributes['logicJson'] : '';
		$post_id   = get_the_ID();
		if ( ! $post_id && is_singular() ) {
			$post_id = get_queried_object_id();
		}
		$instance_id = md5( $logic_raw . '|' . (int) $post_id . '|' . self::$render_instance_ordinal );
		$this->wrapper_id = 'qf-' . $instance_id;

		$settings    = $this->attributes_to_settings( $attributes );
		$paged       = $this->resolve_request_paged();
		$ppp         = QF_Query_Parser::resolve_posts_per_page_for_query( $settings['qf_logic_json'] );
		$search_on   = ! empty( $settings['search_enabled'] );

		$ttl         = QF_Query_Cache::get_cache_ttl_from_logic( $logic_raw );
		$logic_hash  = QF_Query_Cache::logic_hash( $logic_raw );
		$ctx_hash    = md5( wp_json_encode( $attributes ) );
		$cache_key   = QF_Query_Cache::build_cache_key( $logic_raw, $paged, $ppp, $ctx_hash, 'html' );
		$cached_html = null;
		if ( $ttl > 0 && ! QF_Query_Cache::should_bypass() && ! $is_editor_preview && '' !== $logic_raw && ! $search_on ) {
			$cached_html = QF_Query_Cache::get( $cache_key, $logic_hash );
		}

		$wrapper_attrs = get_block_wrapper_attributes(
			[
				'data-qf-instance-id'          => $instance_id,
				'data-qf-posts-per-page'       => (string) $ppp,
				'data-qf-current-page'         => (string) $paged,
				'data-qf-search-active'        => '0',
				'data-qf-search-enabled'       => $search_on ? '1' : '0',
				'data-qf-search-field'           => $settings['search_field'],
				'data-qf-search-position'        => $settings['search_position'],
				'data-qf-search-alignment'       => $settings['search_alignment'],
				'data-qf-search-style'           => $settings['search_style'] ?? 'branded',
				'class'                        => 'qf-query-forge-root',
			]
		);

		ob_start();
		echo '<div ' . $wrapper_attrs . '>';
		$this->maybe_enqueue_google_fonts( $attributes );
		if ( null !== $cached_html ) {
			echo $cached_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Cached trusted plugin HTML fragment.
		} else {
			ob_start();
			$this->output_scoped_grid_css( $attributes );
			$this->render_main( $attributes, $settings, $paged, $ppp, $instance_id );
			$inner = ob_get_clean();
			if ( $ttl > 0 && ! QF_Query_Cache::should_bypass() && ! $is_editor_preview && '' !== $logic_raw && ! $search_on ) {
				QF_Query_Cache::set( $cache_key, $logic_hash, $inner, $ttl );
			}
			echo $inner; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Same as uncached render path.
		}
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Sanitize a CSS color value from ColorPicker.
	 * Accepts hex, rgb(), rgba(). Returns empty string for anything else.
	 * Known limitation: HSL/HSV and modern space-separated CSS color syntax
	 * (e.g. rgb(255 0 0 / 0.5)) will return '' — acceptable for WP ColorPicker output.
	 *
	 * @param string $color Raw color string.
	 * @return string Sanitized color or empty string.
	 */
	private function sanitize_color( string $color ): string {
		if ( '' === $color ) {
			return '';
		}
		$hex = sanitize_hex_color( $color );
		if ( $hex ) {
			return $hex;
		}
		if ( preg_match( '/^rgba?\(\s*\d+\s*,\s*\d+\s*,\s*\d+\s*(,\s*[\d.]+\s*)?\)$/', $color ) ) {
			return $color;
		}
		return '';
	}

	/**
	 * Print inline CSS scoped to this block wrapper ID.
	 *
	 * @param array $attributes Block attributes.
	 */
	private function output_scoped_grid_css( array $attributes ): void {
		$wrapper_id = $this->wrapper_id;

		$shadow_map = [
			'none'   => 'none',
			'soft'   => '0 2px 8px rgba(0,0,0,0.08)',
			'strong' => '0 4px 24px rgba(0,0,0,0.18)',
		];
		$shadow_raw  = isset( $attributes['cardShadow'] ) ? (string) $attributes['cardShadow'] : 'soft';
		$shadow_slug = in_array( $shadow_raw, array_keys( $shadow_map ), true ) ? $shadow_raw : 'soft';
		$shadow_css  = $shadow_map[ $shadow_slug ];

		$columns = absint( $attributes['columns'] ?? 3 );
		$col_gap = absint( $attributes['columnGap'] ?? 20 );
		$row_gap = absint( $attributes['rowGap'] ?? 20 );
		$radius  = absint( $attributes['cardBorderRadius'] ?? 0 );

		$allowed_ratios = [ '16/9', '4/3', '1/1' ];
		$img_ratio_raw  = isset( $attributes['cardImageRatio'] ) ? (string) $attributes['cardImageRatio'] : '16/9';
		$img_ratio      = in_array( $img_ratio_raw, $allowed_ratios, true ) ? $img_ratio_raw : '16/9';

		$bg_color      = $this->sanitize_color( (string) ( $attributes['cardBackgroundColor'] ?? '' ) );
		$title_color   = $this->sanitize_color( (string) ( $attributes['cardTitleColor'] ?? '' ) );
		$meta_color    = $this->sanitize_color( (string) ( $attributes['cardMetaColor'] ?? '' ) );
		$excerpt_color = $this->sanitize_color( (string) ( $attributes['cardExcerptColor'] ?? '' ) );
		$button_color  = $this->sanitize_color( (string) ( $attributes['cardButtonColor'] ?? '' ) );

		$allowed_align = [ 'left', 'center', 'right', '' ];
		$title_align   = in_array( $attributes['titleAlign'] ?? '', $allowed_align, true ) ? (string) ( $attributes['titleAlign'] ?? '' ) : '';
		$meta_align    = in_array( $attributes['metaAlign'] ?? '', $allowed_align, true ) ? (string) ( $attributes['metaAlign'] ?? '' ) : '';
		$excerpt_align = in_array( $attributes['excerptAlign'] ?? '', $allowed_align, true ) ? (string) ( $attributes['excerptAlign'] ?? '' ) : '';
		$button_align  = in_array( $attributes['buttonAlign'] ?? '', $allowed_align, true ) ? (string) ( $attributes['buttonAlign'] ?? '' ) : '';
		$content_align = in_array( $attributes['cardContentAlignment'] ?? '', $allowed_align, true ) ? (string) ( $attributes['cardContentAlignment'] ?? '' ) : '';

		$css  = '#' . $wrapper_id . ".qf-grid { grid-template-columns: repeat({$columns}, 1fr); column-gap: {$col_gap}px; row-gap: {$row_gap}px; }\n";
		$css .= '#' . $wrapper_id . " .qf-card { border-radius: {$radius}px; box-shadow: {$shadow_css}; }\n";
		$css .= '#' . $wrapper_id . " .qf-card-image img { aspect-ratio: {$img_ratio}; object-fit: cover; width: 100%; }\n";

		if ( $bg_color ) {
			$css .= '#' . $wrapper_id . " .qf-card { background-color: {$bg_color}; }\n";
		}
		if ( $title_color ) {
			$css .= '#' . $wrapper_id . " .qf-card-title, #{$wrapper_id} .qf-card-title a { color: {$title_color}; }\n";
		}
		if ( $meta_color ) {
			$css .= '#' . $wrapper_id . " .qf-card-meta, #{$wrapper_id} .qf-card-meta a { color: {$meta_color}; }\n";
		}
		if ( $excerpt_color ) {
			$css .= '#' . $wrapper_id . " .qf-card-excerpt { color: {$excerpt_color}; }\n";
		}
		if ( $button_color ) {
			$css .= '#' . $wrapper_id . " .qf-card-button { background-color: {$button_color}; }\n";
		}
		if ( $title_align ) {
			$css .= '#' . $wrapper_id . " .qf-card-title { text-align: {$title_align}; }\n";
		}
		if ( $meta_align ) {
			$css .= '#' . $wrapper_id . " .qf-card-meta { text-align: {$meta_align}; }\n";
		}
		if ( $excerpt_align ) {
			$css .= '#' . $wrapper_id . " .qf-card-excerpt { text-align: {$excerpt_align}; }\n";
		}
		if ( $button_align ) {
			$css .= '#' . $wrapper_id . " .qf-card-button-wrapper { text-align: {$button_align}; }\n";
		}
		if ( $content_align ) {
			$css .= '#' . $wrapper_id . " .qf-card-content { text-align: {$content_align}; }\n";
		}

		$css .= $this->build_card_typography_scoped_css( $wrapper_id, $attributes );

		if ( ! empty( $attributes['showResultsSummary'] ) && 'yes' === $attributes['showResultsSummary'] ) {
			$css .= $this->build_results_summary_scoped_css( $wrapper_id, $attributes );
		}

		echo '<style>' . $css . '</style>';
	}

	/**
	 * Scoped CSS for the results summary line (Elementor parity: typography, color, alignment).
	 *
	 * @param string $wrapper_id Grid wrapper element ID (summary uses {$wrapper_id}-rs).
	 * @param array  $attributes Block attributes.
	 * @return string CSS fragment without wrapping style tag.
	 */
	private function build_results_summary_scoped_css( string $wrapper_id, array $attributes ): string {
		$rs_sel = '#' . $wrapper_id . '-rs';

		$color = $this->sanitize_color( (string) ( $attributes['resultsSummaryColor'] ?? '' ) );
		$allowed_align = [ 'left', 'center', 'right' ];
		$align         = in_array( $attributes['resultsSummaryAlign'] ?? '', $allowed_align, true )
			? (string) ( $attributes['resultsSummaryAlign'] ?? '' )
			: '';

		$font_size = $this->resolve_element_font_size( $attributes, 'resultsSummaryFontSize' );
		$weight    = $this->sanitize_font_weight( (string) ( $attributes['resultsSummaryFontWeight'] ?? '' ) );
		$lh        = $this->sanitize_line_height( (string) ( $attributes['resultsSummaryLineHeight'] ?? '' ) );
		$ff        = $this->resolve_font_family_preset_slug( (string) ( $attributes['resultsSummaryFontFamily'] ?? '' ) );
		$fs        = $this->sanitize_font_style( (string) ( $attributes['resultsSummaryFontStyle'] ?? '' ) );
		$ls        = $this->sanitize_results_summary_css_size( (string) ( $attributes['resultsSummaryLetterSpacing'] ?? '' ) );
		$tt        = $this->sanitize_text_transform( (string) ( $attributes['resultsSummaryTextTransform'] ?? '' ) );

		$rules = [];
		if ( $color ) {
			$rules[] = 'color: ' . $color;
		}
		if ( $align ) {
			$rules[] = 'text-align: ' . $align;
		}
		if ( $font_size ) {
			$rules[] = 'font-size: ' . $font_size;
		}
		if ( $weight ) {
			$rules[] = 'font-weight: ' . $weight;
		}
		if ( $lh ) {
			$rules[] = 'line-height: ' . $lh;
		}
		if ( $ff ) {
			$rules[] = 'font-family: ' . $ff;
		}
		if ( $fs ) {
			$rules[] = 'font-style: ' . $fs;
		}
		if ( $ls ) {
			$rules[] = 'letter-spacing: ' . $ls;
		}
		if ( $tt ) {
			$rules[] = 'text-transform: ' . $tt;
		}

		if ( empty( $rules ) ) {
			return '';
		}

		return $rs_sel . ' { ' . implode( '; ', $rules ) . "; }\n";
	}

	/**
	 * Font size / letter-spacing: allow number + common unit.
	 *
	 * @param string $val Raw value.
	 * @return string Sanitized or empty.
	 */
	private function sanitize_results_summary_css_size( string $val ): string {
		$val = trim( $val );
		if ( '' === $val ) {
			return '';
		}
		// Font size / letter-spacing: 16px, 1rem, .02em
		if ( preg_match( '/^(\d+(\.\d+)?|\.\d+)(px|em|rem|%|pt)$/', $val ) ) {
			return $val;
		}
		return '';
	}

	/**
	 * Line height: unitless number or size with unit.
	 *
	 * @param string $val Raw value.
	 * @return string Sanitized or empty.
	 */
	private function sanitize_line_height( string $val ): string {
		$val = trim( $val );
		if ( '' === $val ) {
			return '';
		}
		if ( preg_match( '/^\d+(\.\d+)?$/', $val ) ) {
			return $val;
		}
		if ( preg_match( '/^\d+(\.\d+)?(px|em|rem|%)$/', $val ) ) {
			return $val;
		}
		return '';
	}

	/**
	 * Font weight keyword or 100–900.
	 *
	 * @param string $val Raw value.
	 * @return string Sanitized or empty.
	 */
	private function sanitize_font_weight( string $val ): string {
		$val = trim( $val );
		if ( '' === $val ) {
			return '';
		}
		$allowed_kw = [ 'normal', 'bold', 'bolder', 'lighter' ];
		if ( in_array( $val, $allowed_kw, true ) ) {
			return $val;
		}
		if ( preg_match( '/^[1-9]00$/', $val ) ) {
			return $val;
		}
		return '';
	}

	/**
	 * text-transform.
	 *
	 * @param string $val Raw value.
	 * @return string Sanitized or empty.
	 */
	private function sanitize_text_transform( string $val ): string {
		$allowed = [ 'none', 'uppercase', 'lowercase', 'capitalize' ];
		return in_array( $val, $allowed, true ) ? $val : '';
	}

	/**
	 * Font size: integer px from block (0 = inherit) or legacy string from older saves.
	 *
	 * @param array  $attributes Block attributes.
	 * @param string $attr_key   Attribute name (e.g. resultsSummaryFontSize, cardTitleFontSize).
	 * @return string e.g. "16px" or empty.
	 */
	private function resolve_element_font_size( array $attributes, string $attr_key ): string {
		$raw = $attributes[ $attr_key ] ?? 0;
		if ( is_int( $raw ) || is_float( $raw ) ) {
			$n = (int) round( (float) $raw );
			if ( $n >= 1 && $n <= 96 ) {
				return $n . 'px';
			}
			return '';
		}
		if ( is_string( $raw ) && $raw !== '' ) {
			$t = trim( $raw );
			if ( preg_match( '/^\d+$/', $t ) ) {
				$n = (int) $t;
				if ( $n >= 1 && $n <= 96 ) {
					return $n . 'px';
				}
				return '';
			}
			return $this->sanitize_results_summary_css_size( $raw );
		}
		return '';
	}

	/**
	 * Legacy preset slugs, full CSS stacks from the block editor, or empty.
	 *
	 * @param string $value Raw attribute value.
	 * @return string CSS font-family value or empty.
	 */
	private function resolve_font_family_preset_slug( string $value ): string {
		if ( '' === $value ) {
			return '';
		}

		// Skip JS sentinels.
		if ( '__custom__' === $value || '__sep__' === $value ) {
			return '';
		}

		$presets = $this->get_font_family_presets();
		if ( isset( $presets[ $value ] ) ) {
			return $presets[ $value ];
		}

		// Pass through full CSS font-family stacks (see block editor FONT_FAMILY_OPTIONS).
		if ( preg_match( '#^[A-Za-z0-9À-ÖØ-öø-ÿ\s\',\-\."\/_]+$#u', $value ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Enqueue Google Fonts for any typography slot that uses a quoted webfont name.
	 *
	 * @param array $attributes Block attributes.
	 */
	private function maybe_enqueue_google_fonts( array $attributes ): void {
		$prefixes = [ 'cardTitle', 'cardDate', 'cardAuthor', 'cardExcerpt', 'cardButton', 'resultsSummary' ];
		$families   = [];

		foreach ( $prefixes as $prefix ) {
			$val = $attributes[ $prefix . 'FontFamily' ] ?? '';
			if ( ! is_string( $val ) || '' === $val ) {
				continue;
			}
			if ( '__custom__' === $val || '__sep__' === $val ) {
				continue;
			}
			if ( strpos( $val, "'" ) === false && strpos( $val, '"' ) === false ) {
				continue;
			}
			if ( preg_match( '/[\'"]([^\'"]+)[\'"]/', $val, $m ) ) {
				$families[] = str_replace( ' ', '+', $m[1] );
			}
		}

		if ( empty( $families ) ) {
			return;
		}

		$families = array_unique( $families );
		$query    = implode( '&family=', $families );
		$url      = 'https://fonts.googleapis.com/css2?family=' . $query . '&display=swap';

		wp_enqueue_style(
			'qf-google-fonts-' . md5( $query ),
			$url,
			[],
			null
		);
	}

	/**
	 * Allowed font-family presets (results summary + card typography).
	 *
	 * @return array<string, string> slug => font-family stack.
	 */
	private function get_font_family_presets(): array {
		return [
			''              => '',
			'system-ui'     => 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
			'sans'          => 'ui-sans-serif, system-ui, -apple-system, sans-serif',
			'serif'         => 'Georgia, "Times New Roman", Times, serif',
			'mono'          => 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace',
			'classic-serif' => '"Palatino Linotype", Palatino, "Book Antiqua", Georgia, serif',
		];
	}

	/**
	 * Typography for card title, meta, excerpt, and button (Elementor-style type controls).
	 *
	 * @param string $wrapper_id Grid wrapper element ID.
	 * @param array  $attributes Block attributes.
	 * @return string CSS fragment.
	 */
	private function build_card_typography_scoped_css( string $wrapper_id, array $attributes ): string {
		$wid = '#' . $wrapper_id;
		$parts = [
			[
				'prefix' => 'cardTitle',
				'select' => "{$wid} .qf-card-title, {$wid} .qf-card-title a",
			],
			[
				'prefix' => 'cardDate',
				'select' => "{$wid} .qf-card-date",
			],
			[
				'prefix' => 'cardAuthor',
				'select' => "{$wid} .qf-card-author, {$wid} .qf-card-author a",
			],
			[
				'prefix' => 'cardExcerpt',
				'select' => "{$wid} .qf-card-excerpt",
			],
			[
				'prefix' => 'cardButton',
				'select' => "{$wid} .qf-card-button",
			],
		];

		$out = '';
		foreach ( $parts as $part ) {
			$p = $part['prefix'];
			$rules = [];

			$fs = $this->resolve_element_font_size( $attributes, $p . 'FontSize' );
			if ( $fs ) {
				$rules[] = 'font-size: ' . $fs;
			}
			$ff = $this->resolve_font_family_preset_slug( (string) ( $attributes[ $p . 'FontFamily' ] ?? '' ) );
			if ( $ff ) {
				$rules[] = 'font-family: ' . $ff;
			}
			$fst = $this->sanitize_font_style( (string) ( $attributes[ $p . 'FontStyle' ] ?? '' ) );
			if ( $fst ) {
				$rules[] = 'font-style: ' . $fst;
			}
			$fw = $this->sanitize_font_weight( (string) ( $attributes[ $p . 'FontWeight' ] ?? '' ) );
			if ( $fw ) {
				$rules[] = 'font-weight: ' . $fw;
			}
			$lh = $this->sanitize_line_height( (string) ( $attributes[ $p . 'LineHeight' ] ?? '' ) );
			if ( $lh ) {
				$rules[] = 'line-height: ' . $lh;
			}
			$ls = $this->sanitize_results_summary_css_size( (string) ( $attributes[ $p . 'LetterSpacing' ] ?? '' ) );
			if ( $ls ) {
				$rules[] = 'letter-spacing: ' . $ls;
			}
			$tt = $this->sanitize_text_transform( (string) ( $attributes[ $p . 'TextTransform' ] ?? '' ) );
			if ( $tt ) {
				$rules[] = 'text-transform: ' . $tt;
			}

			if ( ! empty( $rules ) ) {
				$out .= $part['select'] . ' { ' . implode( '; ', $rules ) . "; }\n";
			}
		}

		return $out;
	}

	/**
	 * font-style for results summary.
	 *
	 * @param string $val Raw value.
	 * @return string Sanitized or empty.
	 */
	private function sanitize_font_style( string $val ): string {
		$allowed = [ 'normal', 'italic', 'oblique' ];
		return in_array( $val, $allowed, true ) ? $val : '';
	}

	/**
	 * Map Gutenberg camelCase attributes to legacy snake_case settings used by render helpers.
	 *
	 * @param array $attributes Block attributes.
	 * @return array
	 */
	private function attributes_to_settings( array $attributes ): array {
		return [
			'qf_logic_json'            => $attributes['logicJson'] ?? '',
			'qf_graph_state'           => $attributes['graphState'] ?? '',
			'display_type'             => 'canned',
			'card_style'               => $attributes['cardStyle'] ?? 'vertical',
			'show_title'               => $attributes['showTitle'] ?? 'yes',
			'show_excerpt'             => $attributes['showExcerpt'] ?? 'yes',
			'show_image'               => $attributes['showImage'] ?? 'yes',
			'show_date'                => $attributes['showDate'] ?? 'yes',
			'show_author'              => $attributes['showAuthor'] ?? 'yes',
			'excerpt_length'           => isset( $attributes['excerptLength'] ) ? (int) $attributes['excerptLength'] : 100,
			'image_size'               => $attributes['imageSize'] ?? 'medium',
			'show_pagination'          => $attributes['showPagination'] ?? 'no',
			'pagination_type'          => $attributes['paginationType'] ?? 'standard',
			'show_results_summary'     => $attributes['showResultsSummary'] ?? 'no',
			'results_summary_position' => $attributes['resultsSummaryPosition'] ?? 'above_grid',
			'pagination_prev_text'     => $attributes['paginationPrevText'] ?? '',
			'pagination_next_text'     => $attributes['paginationNextText'] ?? '',
			'load_more_button_text'    => '',
			'loading_text'             => '',
			'infinite_scroll_offset'   => 200,
			'elementor_template_id'    => '',
			'show_read_more'           => $attributes['showReadMore'] ?? 'yes',
			'link_target'              => $attributes['linkTarget'] ?? '_self',
			'card_button_position'     => $attributes['cardButtonPosition'] ?? 'bottom',
			'title_link_decoration'    => ( isset( $attributes['titleLinkDecoration'] ) && 'underline' === $attributes['titleLinkDecoration'] ) ? 'underline' : 'none',
			'search_enabled'           => ! empty( $attributes['searchEnabled'] ),
			'search_position'          => self::normalize_search_position( isset( $attributes['searchPosition'] ) ? (string) $attributes['searchPosition'] : 'above' ),
			'search_alignment'         => self::normalize_search_alignment( isset( $attributes['searchAlignment'] ) ? (string) $attributes['searchAlignment'] : 'left' ),
			'search_field'             => self::normalize_search_field( isset( $attributes['searchField'] ) ? (string) $attributes['searchField'] : 'title' ),
			'search_style'             => self::normalize_search_style( isset( $attributes['searchStyle'] ) ? (string) $attributes['searchStyle'] : 'branded' ),
			'search_input_text_color'  => $this->sanitize_color( (string) ( $attributes['searchInputTextColor'] ?? '' ) ),
			'search_placeholder_color' => $this->sanitize_color( (string) ( $attributes['searchPlaceholderColor'] ?? '' ) ),
			'search_input_bg_color'    => $this->sanitize_color( (string) ( $attributes['searchInputBgColor'] ?? '' ) ),
			'search_input_font_size'   => isset( $attributes['searchInputFontSize'] ) ? max( 0, (int) $attributes['searchInputFontSize'] ) : 0,
			'search_border_radius'     => isset( $attributes['searchBorderRadius'] ) ? max( 0, (int) $attributes['searchBorderRadius'] ) : 0,
			'search_focus_ring_color'  => $this->sanitize_color( (string) ( $attributes['searchFocusRingColor'] ?? '' ) ),
			'search_icon_color'        => $this->sanitize_color( (string) ( $attributes['searchIconColor'] ?? '' ) ),
			'search_border_color'      => $this->sanitize_color( (string) ( $attributes['searchBorderColor'] ?? '' ) ),
			'search_border_width'      => isset( $attributes['searchBorderWidth'] ) ? max( 0, (int) $attributes['searchBorderWidth'] ) : 0,
			'search_shadow_color'      => $this->sanitize_color( (string) ( $attributes['searchShadowColor'] ?? '' ) ),
			'search_shadow_intensity'  => self::normalize_search_shadow_intensity( isset( $attributes['searchShadowIntensity'] ) ? (string) $attributes['searchShadowIntensity'] : 'medium' ),
		];
	}

	/**
	 * @param string $pos Raw position.
	 * @return string
	 */
	private static function normalize_search_position( string $pos ): string {
		return in_array( $pos, [ 'above', 'below', 'both' ], true ) ? $pos : 'above';
	}

	/**
	 * @param string $align Raw alignment.
	 * @return string
	 */
	private static function normalize_search_alignment( string $align ): string {
		return in_array( $align, [ 'left', 'center', 'right' ], true ) ? $align : 'left';
	}

	/**
	 * @param string $field Raw field slug.
	 * @return string
	 */
	private static function normalize_search_field( string $field ): string {
		return in_array( $field, [ 'title', 'content', 'title_content' ], true ) ? $field : 'title';
	}

	/**
	 * @param string $style Raw search bar style.
	 * @return string
	 */
	private static function normalize_search_style( string $style ): string {
		return in_array( $style, [ 'branded', 'minimal', 'floating' ], true ) ? $style : 'branded';
	}

	/**
	 * @param string $intensity Raw shadow intensity.
	 * @return string
	 */
	private static function normalize_search_shadow_intensity( string $intensity ): string {
		return in_array( $intensity, [ 'light', 'medium', 'strong' ], true ) ? $intensity : 'medium';
	}

	/**
	 * Snake_case search style keys for QF_Frontend_Search::render_search_bar.
	 *
	 * @param array $settings Normalized block settings.
	 * @return array<string, mixed>
	 */
	private function search_style_settings_for_render( array $settings ): array {
		return [
			'search_style'             => $settings['search_style'] ?? 'branded',
			'search_input_text_color'  => $settings['search_input_text_color'] ?? '',
			'search_placeholder_color' => $settings['search_placeholder_color'] ?? '',
			'search_input_bg_color'    => $settings['search_input_bg_color'] ?? '',
			'search_input_font_size'   => isset( $settings['search_input_font_size'] ) ? (int) $settings['search_input_font_size'] : 0,
			'search_border_radius'     => isset( $settings['search_border_radius'] ) ? (int) $settings['search_border_radius'] : 0,
			'search_focus_ring_color'  => $settings['search_focus_ring_color'] ?? '',
			'search_icon_color'        => $settings['search_icon_color'] ?? '',
			'search_border_color'      => $settings['search_border_color'] ?? '',
			'search_border_width'      => isset( $settings['search_border_width'] ) ? (int) $settings['search_border_width'] : 0,
			'search_shadow_color'      => $settings['search_shadow_color'] ?? '',
			'search_shadow_intensity'  => $settings['search_shadow_intensity'] ?? 'medium',
		];
	}

	/**
	 * Main output (ported from Elementor widget render; no Elementor APIs).
	 *
	 * @param array $attributes Raw block attributes (for data-qf-settings JSON).
	 * @param array $settings   Legacy-shaped settings for helpers.
	 * @param int   $paged      Current page (explicit; not via $_GET in parser).
	 * @param int   $ppp        Posts per page for the query.
	 * @param string $instance_id Stable instance id for search UI.
	 */
	private function render_main( array $attributes, array $settings, $paged = 1, $ppp = 10, $instance_id = '' ): void {
		$search_cfg = [
			'search_enabled'    => ! empty( $settings['search_enabled'] ),
			'search_position'   => $settings['search_position'] ?? 'above',
			'search_alignment'  => $settings['search_alignment'] ?? 'left',
			'search_field'      => $settings['search_field'] ?? 'title',
		];
		$data_settings = wp_json_encode(
			[
				'logic_json'      => $attributes['logicJson'] ?? '',
				'widget_settings' => [
					'display_type'           => 'canned',
					'card_style'             => $attributes['cardStyle'] ?? 'vertical',
					'show_title'             => $attributes['showTitle'] ?? 'yes',
					'show_excerpt'           => $attributes['showExcerpt'] ?? 'yes',
					'show_image'             => $attributes['showImage'] ?? 'yes',
					'show_date'              => $attributes['showDate'] ?? 'yes',
					'show_author'            => $attributes['showAuthor'] ?? 'yes',
					'excerpt_length'         => isset( $attributes['excerptLength'] ) ? (int) $attributes['excerptLength'] : 100,
					'image_size'             => $attributes['imageSize'] ?? 'medium',
					'elementor_template_id'    => '',
					'pagination_type'          => $attributes['paginationType'] ?? 'standard',
					'pagination_prev_text'     => $attributes['paginationPrevText'] ?? '',
					'pagination_next_text'     => $attributes['paginationNextText'] ?? '',
					'load_more_button_text'    => '',
					'loading_text'             => '',
					'infinite_scroll_offset'   => 200,
					'show_read_more'           => $attributes['showReadMore'] ?? 'yes',
					'card_button_position'     => $attributes['cardButtonPosition'] ?? 'bottom',
					'link_target'              => $attributes['linkTarget'] ?? '_self',
					'show_results_summary'     => $attributes['showResultsSummary'] ?? 'no',
					'results_summary_position' => $attributes['resultsSummaryPosition'] ?? 'above_grid',
					'results_summary_color'    => $attributes['resultsSummaryColor'] ?? '',
					'results_summary_align'    => $attributes['resultsSummaryAlign'] ?? '',
					'results_summary_font_size' => isset( $attributes['resultsSummaryFontSize'] ) ? (int) $attributes['resultsSummaryFontSize'] : 0,
					'results_summary_font_weight' => $attributes['resultsSummaryFontWeight'] ?? '',
					'results_summary_line_height' => $attributes['resultsSummaryLineHeight'] ?? '',
					'results_summary_font_family' => $attributes['resultsSummaryFontFamily'] ?? '',
					'results_summary_font_style' => $attributes['resultsSummaryFontStyle'] ?? '',
					'results_summary_letter_spacing' => $attributes['resultsSummaryLetterSpacing'] ?? '',
					'results_summary_text_transform' => $attributes['resultsSummaryTextTransform'] ?? '',
					'widget_id'                      => $this->wrapper_id,
				],
			]
		);

		if ( empty( $settings['qf_logic_json'] ) ) {
			echo '<div class="qf-placeholder">';
			echo '<p>' . esc_html__( 'Please configure Query Builder to display posts.', 'query-forge' ) . '</p>';
			echo '</div>';
			return;
		}

		$spos = isset( $search_cfg['search_position'] ) ? $search_cfg['search_position'] : 'above';
		if ( ! empty( $search_cfg['search_enabled'] ) && in_array( $spos, [ 'above', 'both' ], true ) ) {
			QF_Frontend_Search::render_search_bar( $instance_id, 'above', $search_cfg['search_alignment'], $this->search_style_settings_for_render( $settings ) );
		}

		$query = QF_Query_Parser::get_query( $settings['qf_logic_json'], $paged, $ppp );

		if ( ! $query || ! $query->have_posts() ) {
			echo '<div class="qf-placeholder">';
			echo '<p>' . esc_html__( 'No posts found. Check your query settings.', 'query-forge' ) . '</p>';
			echo '</div>';
			return;
		}

		$show_results_summary = ! empty( $settings['show_results_summary'] ) && 'yes' === $settings['show_results_summary'];
		$results_position     = ! empty( $settings['results_summary_position'] ) ? $settings['results_summary_position'] : 'above_grid';

		if ( $show_results_summary && 'above_grid' === $results_position ) {
			$this->render_results_summary( $query, $attributes );
		}

		$card_style = ! empty( $settings['card_style'] ) ? $settings['card_style'] : 'vertical';
		$qf_paged   = max( 1, (int) $query->get( 'paged' ) );
		if ( $qf_paged < 1 ) {
			$qf_paged = 1;
		}
		$qf_max_pages = $this->resolve_query_max_pages( $query );
		$qf_total     = (int) $query->found_posts;
		$qf_ppp       = (int) $query->get( 'posts_per_page' );
		if ( $qf_ppp <= 0 ) {
			$qf_ppp = $ppp;
		}
		?>
		<div
			id="<?php echo esc_attr( $this->wrapper_id ); ?>"
			class="qf-grid qf-card-style-<?php echo esc_attr( $card_style ); ?>"
			data-qf-widget-id="<?php echo esc_attr( $this->wrapper_id ); ?>"
			data-qf-settings="<?php echo esc_attr( $data_settings ); ?>"
			data-qf-logic-json="<?php echo esc_attr( $settings['qf_logic_json'] ); ?>"
			data-qf-total="<?php echo esc_attr( (string) $qf_total ); ?>"
			data-qf-max-pages="<?php echo esc_attr( (string) $qf_max_pages ); ?>"
			data-qf-posts-per-page="<?php echo esc_attr( (string) $qf_ppp ); ?>"
			data-qf-current-page="<?php echo esc_attr( (string) $qf_paged ); ?>"
		>
			<?php
			while ( $query->have_posts() ) {
				$query->the_post();
				$this->render_card( $settings, $card_style );
			}
			wp_reset_postdata();
			?>
		</div>
		<?php

		if ( ! empty( $search_cfg['search_enabled'] ) && in_array( $spos, [ 'below', 'both' ], true ) ) {
			QF_Frontend_Search::render_search_bar( $instance_id, 'below', $search_cfg['search_alignment'], $this->search_style_settings_for_render( $settings ) );
		}

		if ( $show_results_summary && 'above_pagination' === $results_position ) {
			$this->render_results_summary( $query, $attributes );
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
			$this->render_results_summary( $query, $attributes );
		}
	}

	/**
	 * Render results summary.
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                           $attributes Block attributes (styling uses scoped CSS on #{$wrapper_id}-rs).
	 */
	private function render_results_summary( $query, array $attributes = [] ) {
		if ( ! $query ) {
			return;
		}

		// Do not use have_posts() here: for positions above/below pagination this runs
		// after the main loop, so the query cursor is exhausted and have_posts() is false.
		$total    = (int) $query->found_posts;
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

		$text = sprintf(
			esc_html__( 'Showing %1$d–%2$d of %3$d results', 'query-forge' ),
			$start,
			$end,
			$total
		);

		echo '<div id="' . esc_attr( $this->wrapper_id . '-rs' ) . '" class="qf-results-summary">' . esc_html( $text ) . '</div>';
	}

	/**
	 * Render card based on style.
	 *
	 * @param array  $settings Widget-shaped settings.
	 * @param string $style    Card style slug.
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
	 *
	 * @param array $settings Settings.
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
					$this->render_title( $link_target, $settings );
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
	 *
	 * @param array $settings Settings.
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
					$this->render_title( $link_target, $settings );
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
	 *
	 * @param array $settings Settings.
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
					$this->render_title( $link_target, $settings );
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
	 *
	 * @param array $settings Settings.
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
					$this->render_title( $link_target, $settings );
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
	 *
	 * @param array $settings Settings.
	 */
	private function render_magazine_card( $settings, $show_title, $show_excerpt, $show_date, $show_author, $show_image, $link_target, $show_button, $button_position ) {
		?>
		<div class="qf-card-inner qf-card-magazine">
			<?php if ( $show_image ) : ?>
				<div class="qf-card-image">
					<?php $this->render_featured_image( $settings, $link_target ); ?>
					<?php if ( $show_title ) : ?>
						<div class="qf-card-overlay">
							<?php $this->render_title( $link_target, $settings ); ?>
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
					$this->render_title( $link_target, $settings );
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
	 * @param array  $settings    Settings (title_link_decoration: none|underline).
	 */
	private function render_title( $link_target, array $settings = [] ) {
		$decoration = $settings['title_link_decoration'] ?? 'none';
		if ( ! in_array( $decoration, [ 'none', 'underline' ], true ) ) {
			$decoration = 'none';
		}
		$title_style = 'text-decoration:' . $decoration . ';color:inherit;';
		?>
		<h3 class="qf-card-title">
			<a href="<?php echo esc_url( get_permalink() ); ?>" target="<?php echo esc_attr( $link_target ); ?>" style="<?php echo esc_attr( $title_style ); ?>">
				<?php echo esc_html( get_the_title() ); ?>
			</a>
		</h3>
		<?php
	}

	/**
	 * Render excerpt
	 *
	 * @param array $settings Settings.
	 */
	private function render_excerpt( $settings ) {
		$excerpt_length = ! empty( $settings['excerpt_length'] ) ? absint( $settings['excerpt_length'] ) : 100;
		$excerpt        = get_the_excerpt();

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
	 * @param array  $settings Settings.
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
			?>
			<div class="qf-card-image-placeholder">
				<span class="qf-placeholder-icon">📷</span>
			</div>
			<?php
		}
	}

	/**
	 * Reliable page count for paginate_links (WP_Query can report max_num_pages as 0 while found_posts is set).
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper|object $query Query instance.
	 * @return int Total pages (minimum 1).
	 */
	private function resolve_query_max_pages( $query ) {
		if ( ! is_object( $query ) ) {
			return 1;
		}
		$max = isset( $query->max_num_pages ) ? (int) $query->max_num_pages : 0;
		if ( $max > 0 ) {
			return $max;
		}
		$found = isset( $query->found_posts ) ? (int) $query->found_posts : 0;
		$per_page = 0;
		if ( $query instanceof \WP_Query ) {
			$per_page = (int) $query->get( 'posts_per_page' );
		}
		if ( $per_page <= 0 ) {
			$per_page = max( 1, (int) get_option( 'posts_per_page' ) );
		}
		if ( $found <= 0 ) {
			return 1;
		}
		return max( 1, (int) ceil( $found / $per_page ) );
	}

	/**
	 * Base URL for pagination links: singular permalink when on a static page, otherwise request URL.
	 *
	 * @return string
	 */
	private function resolve_pagination_base_url() {
		if ( is_singular() ) {
			$object_id = get_queried_object_id();
			if ( $object_id ) {
				$permalink = get_permalink( $object_id );
				if ( $permalink ) {
					return $permalink;
				}
			}
		}
		global $wp;
		$request = ( isset( $wp->request ) && is_string( $wp->request ) ) ? $wp->request : '';
		return home_url( add_query_arg( [], $request ) );
	}

	/**
	 * Current page number from the request (paged query arg and query vars used on singular).
	 *
	 * @return int>=1
	 */
	private function resolve_request_paged() {
		return QF_Query_Parser::resolve_request_paged();
	}

	/**
	 * Render pagination
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                           $settings Settings.
	 */
	public function render_pagination( $query, $settings = [] ) {
		$paged           = $this->resolve_request_paged();
		$pagination_type = ! empty( $settings['pagination_type'] ) ? $settings['pagination_type'] : 'standard';
		$is_ajax         = 'ajax' === $pagination_type;

		$current_url = $this->resolve_pagination_base_url();
		$base        = remove_query_arg( 'paged', $current_url );
		$base        = remove_query_arg( 'page', $base );

		$base = trailingslashit( $base );
		if ( strpos( $base, '?' ) !== false ) {
			$format = '&paged=%#%';
		} else {
			$format = '?paged=%#%';
		}

		$max_pages = $this->resolve_query_max_pages( $query );

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
			$block_id   = $this->wrapper_id;
			echo '<div class="qf-pagination' . esc_attr( $ajax_class ) . '" data-widget-id="' . esc_attr( $block_id ) . '" data-qf-search-active="0">' . wp_kses_post( $pagination ) . '</div>';
		}
	}

	/**
	 * Render Load More button
	 *
	 * @param \WP_Query|\Query_Forge\QF_Query_Result_Wrapper $query Query object.
	 * @param array                                           $settings Settings.
	 */
	private function render_load_more_button( $query, $settings = [] ) {
		$max_pages    = $this->resolve_query_max_pages( $query );
		$current_page = $this->resolve_request_paged();

		if ( $current_page >= $max_pages ) {
			return;
		}

		$block_id   = $this->wrapper_id;
		$next_page  = $current_page + 1;
		$button_text = ! empty( $settings['load_more_button_text'] ) ? $settings['load_more_button_text'] : __( 'Load More', 'query-forge' );
		$loading_text = ! empty( $settings['loading_text'] ) ? $settings['loading_text'] : __( 'Loading...', 'query-forge' );
		?>
		<div class="qf-load-more-wrapper" data-widget-id="<?php echo esc_attr( $block_id ); ?>" data-next-page="<?php echo esc_attr( (string) $next_page ); ?>" data-loading-text="<?php echo esc_attr( $loading_text ); ?>">
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
	 * @param array                                           $settings Settings.
	 */
	private function render_infinite_scroll_trigger( $query, $settings = [] ) {
		$max_pages    = $this->resolve_query_max_pages( $query );
		$current_page = $this->resolve_request_paged();

		if ( $current_page >= $max_pages ) {
			return;
		}

		$block_id      = $this->wrapper_id;
		$next_page     = $current_page + 1;
		$scroll_offset = ! empty( $settings['infinite_scroll_offset'] ) ? absint( $settings['infinite_scroll_offset'] ) : 200;
		?>
		<div class="qf-infinite-scroll-trigger" data-widget-id="<?php echo esc_attr( $block_id ); ?>" data-next-page="<?php echo esc_attr( (string) $next_page ); ?>" data-max-pages="<?php echo esc_attr( (string) $max_pages ); ?>" data-scroll-offset="<?php echo esc_attr( (string) $scroll_offset ); ?>"></div>
		<?php
	}
}
