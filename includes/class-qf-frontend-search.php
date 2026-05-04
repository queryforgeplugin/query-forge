<?php
/**
 * Frontend search: localize data and search bar markup.
 *
 * @package Query_Forge
 * @since   1.3.3
 */

namespace Query_Forge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers for frontend search (requires WordPress 6.2+ for search_columns).
 */
class QF_Frontend_Search {

	/**
	 * Sanitize a CSS color for inline output (hex or legacy rgb/rgba).
	 *
	 * @param string $color Raw color.
	 * @return string Sanitized color or empty string.
	 */
	public static function sanitize_css_color( string $color ): string {
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
	 * Data for wp_localize_script (QueryForgeWidget).
	 *
	 * @since 1.3.3
	 * @return array<string, mixed>
	 */
	public static function get_widget_script_data() {
		return [
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'query_forge_nonce' ),
			'i18n'    => [
				'searchPlaceholder' => __( 'Search…', 'query-forge' ),
				'noResults'         => __( 'No results found.', 'query-forge' ),
				'minChars'          => __( 'Enter at least 3 characters.', 'query-forge' ),
				'loading'           => __( 'Loading…', 'query-forge' ),
			],
		];
	}

	/**
	 * Build extra_args for QF_Query_Parser::get_query (null = no search filter).
	 *
	 * @since 1.3.3
	 * @param string $search_term  Trimmed search string.
	 * @param string $search_field title|content|title_content.
	 * @return array<string, mixed>|null
	 */
	public static function extra_args_for_search( $search_term, $search_field ) {
		$term = is_string( $search_term ) ? trim( $search_term ) : '';
		if ( strlen( $term ) < 3 ) {
			return null;
		}
		$columns = [ 'post_title', 'post_content' ];
		if ( 'title' === $search_field ) {
			$columns = [ 'post_title' ];
		} elseif ( 'content' === $search_field ) {
			$columns = [ 'post_content' ];
		}
		return [
			's'              => $term,
			'search_columns' => $columns,
		];
	}

	/**
	 * @since 1.3.3
	 * @param mixed $raw Raw POST value.
	 * @return string
	 */
	public static function sanitize_search_term( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		return sanitize_text_field( wp_unslash( $raw ) );
	}

	/**
	 * Print search UI (one slot). Repeat for position "both".
	 *
	 * @since 1.3.3
	 * @param string $instance_id Instance id (matches data-qf-instance-id on root).
	 * @param string $slot        above|below.
	 * @param string $alignment   left|center|right.
	 * @param array  $style       Snake_case keys from block/widget settings (see class-qf-block).
	 */
	public static function render_search_bar( $instance_id, $slot = 'above', $alignment = 'left', array $style = [] ) {
		if ( ! in_array( $alignment, [ 'left', 'center', 'right' ], true ) ) {
			$alignment = 'left';
		}

		$sk = isset( $style['search_style'] ) ? (string) $style['search_style'] : 'branded';
		if ( ! in_array( $sk, [ 'branded', 'minimal', 'floating' ], true ) ) {
			$sk = 'branded';
		}

		$align_class = 'qf-search-align-' . $alignment;
		$style_class = 'qf-search-' . $sk;
		$classes     = 'qf-search-bar ' . $align_class . ' ' . $style_class;

		if ( 'floating' === $sk ) {
			$intensity = isset( $style['search_shadow_intensity'] ) ? (string) $style['search_shadow_intensity'] : 'medium';
			if ( ! in_array( $intensity, [ 'light', 'medium', 'strong' ], true ) ) {
				$intensity = 'medium';
			}
			$classes .= ' qf-shadow-' . $intensity;
		}

		$focus_ring = isset( $style['search_focus_ring_color'] ) ? self::sanitize_css_color( (string) $style['search_focus_ring_color'] ) : '';
		$ph_color   = isset( $style['search_placeholder_color'] ) ? self::sanitize_css_color( (string) $style['search_placeholder_color'] ) : '';
		$txt_color  = isset( $style['search_input_text_color'] ) ? self::sanitize_css_color( (string) $style['search_input_text_color'] ) : '';
		$bg_color   = isset( $style['search_input_bg_color'] ) ? self::sanitize_css_color( (string) $style['search_input_bg_color'] ) : '';
		$bd_color   = isset( $style['search_border_color'] ) ? self::sanitize_css_color( (string) $style['search_border_color'] ) : '';
		$icon_col   = isset( $style['search_icon_color'] ) ? self::sanitize_css_color( (string) $style['search_icon_color'] ) : '';
		$sh_color   = isset( $style['search_shadow_color'] ) ? self::sanitize_css_color( (string) $style['search_shadow_color'] ) : '';

		$wrapper_style_parts = [];
		if ( '' !== $focus_ring ) {
			$wrapper_style_parts[] = '--qf-focus-ring: ' . $focus_ring;
		}
		if ( '' !== $ph_color ) {
			$wrapper_style_parts[] = '--qf-placeholder-color: ' . $ph_color;
		}
		if ( 'floating' === $sk && '' !== $sh_color ) {
			$wrapper_style_parts[] = '--qf-search-shadow-color: ' . $sh_color;
		}
		$wrapper_style = ! empty( $wrapper_style_parts ) ? implode( '; ', $wrapper_style_parts ) : '';

		$input_style_parts = [];
		if ( '' !== $txt_color ) {
			$input_style_parts[] = 'color: ' . $txt_color;
		}
		if ( '' !== $bg_color ) {
			$input_style_parts[] = 'background-color: ' . $bg_color;
		}
		$fs = isset( $style['search_input_font_size'] ) ? absint( $style['search_input_font_size'] ) : 0;
		if ( $fs > 0 ) {
			$input_style_parts[] = 'font-size: ' . $fs . 'px';
		}
		$br = isset( $style['search_border_radius'] ) ? absint( $style['search_border_radius'] ) : 0;
		if ( $br > 0 ) {
			$input_style_parts[] = 'border-radius: ' . $br . 'px';
		}
		if ( 'branded' === $sk ) {
			if ( '' !== $bd_color ) {
				$input_style_parts[] = 'border-color: ' . $bd_color;
			}
			$bw = isset( $style['search_border_width'] ) ? absint( $style['search_border_width'] ) : 0;
			if ( $bw > 0 ) {
				$input_style_parts[] = 'border-width: ' . $bw . 'px';
				$input_style_parts[] = 'border-style: solid';
			}
		}
		$input_style = ! empty( $input_style_parts ) ? implode( '; ', $input_style_parts ) : '';

		$icon_style = '';
		if ( 'branded' === $sk && '' !== $icon_col ) {
			$icon_style = 'color: ' . $icon_col;
		}

		$label = __( 'Search', 'query-forge' );
		$ph    = __( 'Search…', 'query-forge' );
		$input_id = 'qf-search-' . $instance_id . '-' . $slot;

		$icon_svg = '<svg class="qf-search-icon-svg" xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0016 9.5 6.5 6.5 0 109.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/></svg>';
		?>
		<div class="<?php echo esc_attr( $classes ); ?>"<?php echo $wrapper_style ? ' style="' . esc_attr( $wrapper_style ) . '"' : ''; ?> data-qf-search-slot="<?php echo esc_attr( $slot ); ?>" data-qf-search-for="<?php echo esc_attr( $instance_id ); ?>">
			<label class="screen-reader-text" for="<?php echo esc_attr( $input_id ); ?>"><?php echo esc_html( $label ); ?></label>
			<?php if ( 'branded' === $sk ) : ?>
				<div class="qf-search-branded-track">
					<span class="qf-search-icon"<?php echo $icon_style ? ' style="' . esc_attr( $icon_style ) . '"' : ''; ?>><?php echo $icon_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup. ?></span>
					<input
						id="<?php echo esc_attr( $input_id ); ?>"
						type="search"
						class="qf-search-input"
						autocomplete="off"
						placeholder="<?php echo esc_attr( $ph ); ?>"
						aria-label="<?php echo esc_attr( $label ); ?>"
						<?php echo $input_style ? ' style="' . esc_attr( $input_style ) . '"' : ''; ?>
					/>
				</div>
			<?php else : ?>
				<input
					id="<?php echo esc_attr( $input_id ); ?>"
					type="search"
					class="qf-search-input"
					autocomplete="off"
					placeholder="<?php echo esc_attr( $ph ); ?>"
					aria-label="<?php echo esc_attr( $label ); ?>"
					<?php echo $input_style ? ' style="' . esc_attr( $input_style ) . '"' : ''; ?>
				/>
			<?php endif; ?>
		</div>
		<?php
	}
}
