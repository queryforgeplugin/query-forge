<?php
/**
 * Shared markup for Query Forge single layouts (included from single-{slug}.php).
 *
 * Expects globals: $qf_post (\WP_Post), $qf_settings (array from transient).
 * Optional: $qf_single_template_slug (string) set by wrapper.
 *
 * @package Query_Forge
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $qf_post, $qf_settings;

if ( ! $qf_post instanceof \WP_Post || ! is_array( $qf_settings ) ) {
	status_header( 404 );
	nocache_headers();
	echo '<!DOCTYPE html><html><head><title>' . esc_html__( 'Not found', 'query-forge' ) . '</title></head><body></body></html>';
	exit;
}

$slug = isset( $qf_single_template_slug ) ? sanitize_key( (string) $qf_single_template_slug ) : 'vertical';
if ( ! in_array( $slug, \Query_Forge\QF_Single_Template::allowed_styles(), true ) ) {
	$slug = 'vertical';
}

global $post;
// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Intentional: template renders $qf_post as the loop post.
$post = $qf_post;
setup_postdata( $qf_post );

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public navigation param only.
$qfr_instance = isset( $_GET[ \Query_Forge\QF_Single_Template::QUERY_VAR ] )
	? sanitize_text_field( wp_unslash( (string) $_GET[ \Query_Forge\QF_Single_Template::QUERY_VAR ] ) )
	: '';

/**
 * @param string $url URL.
 * @return string
 */
$qf_link_with_qfr = static function ( $url ) use ( $qfr_instance ) {
	$url = (string) $url;
	if ( '' === $url || '' === $qfr_instance ) {
		return $url;
	}
	return add_query_arg( \Query_Forge\QF_Single_Template::QUERY_VAR, $qfr_instance, $url );
};

$S = static function ( $key, $default = '' ) use ( $qf_settings ) {
	return isset( $qf_settings[ $key ] ) ? $qf_settings[ $key ] : $default;
};

$show_title  = (bool) $S( 'singleShowTitle', true );
$show_image  = (bool) $S( 'singleShowImage', true );
$img_pos     = in_array( (string) $S( 'singleImagePosition', 'above-title' ), [ 'above-title', 'below-title', 'background' ], true )
	? (string) $S( 'singleImagePosition', 'above-title' )
	: 'above-title';
$img_size    = (string) $S( 'singleImageSize', 'large' );
$show_content = (bool) $S( 'singleShowContent', true );
$show_excerpt = (bool) $S( 'singleShowExcerpt', true );
$show_date    = (bool) $S( 'singleShowDate', true );
$show_author  = (bool) $S( 'singleShowAuthor', true );
$show_avatar  = (bool) $S( 'singleShowAuthorAvatar', true );
$show_terms   = (bool) $S( 'singleShowTerms', true );
$show_nav     = (bool) $S( 'singleShowNavigation', true );

$title_fs = (int) $S( 'singleTitleFontSize', 0 );
$title_color = (string) $S( 'singleTitleColor', '' );
$title_align = in_array( (string) $S( 'singleTitleAlignment', 'left' ), [ 'left', 'center', 'right' ], true )
	? (string) $S( 'singleTitleAlignment', 'left' )
	: 'left';

$ex_fs   = (int) $S( 'singleExcerptFontSize', 0 );
$ex_col  = (string) $S( 'singleExcerptColor', '' );
$ex_al   = in_array( (string) $S( 'singleExcerptAlignment', 'left' ), [ 'left', 'center', 'right' ], true )
	? (string) $S( 'singleExcerptAlignment', 'left' )
	: 'left';

$c_fs = (int) $S( 'singleContentFontSize', 0 );
$c_lh = (float) $S( 'singleContentLineHeight', 0 );
$c_col = (string) $S( 'singleContentColor', '' );

$df_raw = (string) $S( 'singleDateFormat', '' );
$df     = '' !== $df_raw ? $df_raw : get_option( 'date_format' );
$d_fs   = (int) $S( 'singleDateFontSize', 0 );
$d_col  = (string) $S( 'singleDateColor', '' );

$a_fs  = (int) $S( 'singleAuthorFontSize', 0 );
$a_col = (string) $S( 'singleAuthorColor', '' );

$terms_style = in_array( (string) $S( 'singleTermsStyle', 'pills' ), [ 'pills', 'plain', 'comma' ], true )
	? (string) $S( 'singleTermsStyle', 'pills' )
	: 'pills';
$t_fs  = (int) $S( 'singleTermsFontSize', 0 );
$t_col = (string) $S( 'singleTermsColor', '' );

$n_fs   = (int) $S( 'singleNavFontSize', 0 );
$n_col  = (string) $S( 'singleNavColor', '' );
$n_prev = (string) $S( 'singleNavPrevLabel', __( 'Previous', 'query-forge' ) );
$n_next = (string) $S( 'singleNavNextLabel', __( 'Next', 'query-forge' ) );

$title_style = '';
if ( $title_fs > 0 ) {
	$title_style .= 'font-size:' . $title_fs . 'px;';
}
if ( '' !== $title_color && sanitize_hex_color( $title_color ) ) {
	$title_style .= 'color:' . sanitize_hex_color( $title_color ) . ';';
}
$title_style .= 'text-align:' . $title_align . ';';

$feat_html = '';
if ( $show_image ) {
	$thumb_id = get_post_thumbnail_id( $qf_post );
	if ( $thumb_id ) {
		$feat_html = get_the_post_thumbnail( $qf_post, $img_size, [ 'class' => 'qf-single__image-el' ] );
	}
}

$body_style = '';
if ( 'background' === $img_pos && '' !== $feat_html ) {
	$url = wp_get_attachment_image_url( get_post_thumbnail_id( $qf_post ), $img_size );
	if ( $url ) {
		$body_style = 'background-image:url(' . esc_url( $url ) . ');background-size:cover;background-position:center;';
	}
}

get_header();

?>
<main id="qf-single-main" class="qf-single qf-single--<?php echo esc_attr( $slug ); ?>"<?php echo $body_style ? ' style="' . esc_attr( $body_style ) . '"' : ''; ?>>
	<article <?php post_class( 'qf-single__article', $qf_post ); ?>>

		<?php if ( $show_image && 'above-title' === $img_pos && 'background' !== $img_pos && '' !== $feat_html ) : ?>
			<div class="qf-single__media qf-single__media--above-title"><?php echo $feat_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<?php if ( $show_title ) : ?>
			<header class="qf-single__header">
				<h1 class="qf-single__title entry-title" style="<?php echo esc_attr( $title_style ); ?>"><?php echo esc_html( get_the_title( $qf_post ) ); ?></h1>
			</header>
		<?php endif; ?>

		<?php if ( $show_image && 'below-title' === $img_pos && '' !== $feat_html ) : ?>
			<div class="qf-single__media qf-single__media--below-title"><?php echo $feat_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<?php endif; ?>

		<?php if ( $show_date || $show_author ) : ?>
			<div class="qf-single__meta" style="<?php echo esc_attr( ( $d_fs > 0 ? 'font-size:' . $d_fs . 'px;' : '' ) . ( '' !== $d_col && sanitize_hex_color( $d_col ) ? 'color:' . sanitize_hex_color( $d_col ) . ';' : '' ) ); ?>">
				<?php if ( $show_date ) : ?>
					<time class="qf-single__date" datetime="<?php echo esc_attr( get_the_date( 'c', $qf_post ) ); ?>"><?php echo esc_html( get_the_date( $df, $qf_post ) ); ?></time>
				<?php endif; ?>
				<?php if ( $show_date && $show_author ) : ?><span class="qf-single__meta-sep"> · </span><?php endif; ?>
				<?php if ( $show_author ) : ?>
					<span class="qf-single__author" style="<?php echo esc_attr( ( $a_fs > 0 ? 'font-size:' . $a_fs . 'px;' : '' ) . ( '' !== $a_col && sanitize_hex_color( $a_col ) ? 'color:' . sanitize_hex_color( $a_col ) . ';' : '' ) ); ?>">
						<?php
						if ( $show_avatar ) {
							echo get_avatar( (int) $qf_post->post_author, 32, '', '', [ 'class' => 'qf-single__avatar' ] );
						}
						echo ' ';
						the_author_posts_link();
						?>
					</span>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_excerpt ) : ?>
			<div class="qf-single__excerpt" style="<?php echo esc_attr( 'text-align:' . $ex_al . ';' . ( $ex_fs > 0 ? 'font-size:' . $ex_fs . 'px;' : '' ) . ( '' !== $ex_col && sanitize_hex_color( $ex_col ) ? 'color:' . sanitize_hex_color( $ex_col ) . ';' : '' ) ); ?>">
				<?php echo esc_html( get_the_excerpt( $qf_post ) ); ?>
			</div>
		<?php endif; ?>

		<?php if ( $show_content ) : ?>
			<div class="qf-single__content entry-content" style="<?php echo esc_attr( ( $c_fs > 0 ? 'font-size:' . $c_fs . 'px;' : '' ) . ( $c_lh > 0 ? 'line-height:' . $c_lh . ';' : '' ) . ( '' !== $c_col && sanitize_hex_color( $c_col ) ? 'color:' . sanitize_hex_color( $c_col ) . ';' : '' ) ); ?>">
				<?php
				$content = apply_filters( 'the_content', get_post_field( 'post_content', $qf_post ) );
				echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core content filter.
				?>
			</div>
		<?php endif; ?>

		<?php
		if ( $show_terms ) :
			$cats = get_the_terms( $qf_post, 'category' );
			$tags = get_the_terms( $qf_post, 'post_tag' );
			$all  = array_merge( is_array( $cats ) ? $cats : [], is_array( $tags ) ? $tags : [] );
			if ( ! empty( $all ) ) :
				$term_style = ( $t_fs > 0 ? 'font-size:' . $t_fs . 'px;' : '' ) . ( '' !== $t_col && sanitize_hex_color( $t_col ) ? 'color:' . sanitize_hex_color( $t_col ) . ';' : '' );
				?>
			<div class="qf-single__terms qf-single__terms--<?php echo esc_attr( $terms_style ); ?>" style="<?php echo esc_attr( $term_style ); ?>">
				<?php if ( 'comma' === $terms_style ) : ?>
					<?php echo esc_html( implode( ', ', wp_list_pluck( $all, 'name' ) ) ); ?>
				<?php else : ?>
					<ul class="qf-single__term-list">
						<?php foreach ( $all as $term ) : ?>
							<li class="qf-single__term-item">
								<a href="<?php echo esc_url( get_term_link( $term ) ); ?>"><?php echo esc_html( $term->name ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</div>
				<?php
			endif;
		endif;
		?>

		<?php
		if ( $show_nav ) :
			$prev_post = get_adjacent_post( false, '', true );
			$next_post = get_adjacent_post( false, '', false );
			if ( $prev_post || $next_post ) :
				$nav_style = ( $n_fs > 0 ? 'font-size:' . $n_fs . 'px;' : '' ) . ( '' !== $n_col && sanitize_hex_color( $n_col ) ? 'color:' . sanitize_hex_color( $n_col ) . ';' : '' );
				?>
			<nav class="qf-single__nav" style="<?php echo esc_attr( $nav_style ); ?>" aria-label="<?php esc_attr_e( 'Post navigation', 'query-forge' ); ?>">
				<?php if ( $prev_post ) : ?>
					<a class="qf-single__nav-prev" rel="prev" href="<?php echo esc_url( $qf_link_with_qfr( get_permalink( $prev_post ) ) ); ?>"><?php echo esc_html( $n_prev ); ?></a>
				<?php endif; ?>
				<?php if ( $next_post ) : ?>
					<a class="qf-single__nav-next" rel="next" href="<?php echo esc_url( $qf_link_with_qfr( get_permalink( $next_post ) ) ); ?>"><?php echo esc_html( $n_next ); ?></a>
				<?php endif; ?>
			</nav>
				<?php
			endif;
		endif;
		?>

	</article>
</main>
<?php
wp_reset_postdata();
get_footer();
