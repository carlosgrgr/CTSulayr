<?php

/**
 * vcex_post_excerpt shortcode output.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4.2
 */

defined( 'ABSPATH' ) || exit;

if ( vcex_is_template_edit_mode() ) {
	$excerpt = esc_html__( 'This is a sample post excerpt for working in the frontend editor.', 'total-theme-core' );
	if ( ! empty( $atts['length'] ) ) {
		$excerpt = wp_trim_words( $excerpt, $atts['length'] );
	}
} else {
	$post = get_post( vcex_get_the_ID() );
	if ( ! $post ) {
		return;
	}
	$source = $atts['source'] ?? 'auto';
	switch ( $source ) {
		case 'meta_description':
			if ( defined( 'WPSEO_VERSION' ) ) {
				$meta_desc = get_post_meta( get_the_ID(), '_yoast_wpseo_metadesc', true );
				if ( $meta_desc && function_exists( 'wpseo_replace_vars' ) ) {
					$meta_desc = wpseo_replace_vars( $meta_desc, [] );
				}
				$excerpt = $meta_desc;
			}
			break;
		case 'post_excerpt':
		default:
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : '';
			break;
	}
}

if ( empty( $excerpt ) && vcex_validate_att_boolean( 'fallback', $atts, false ) ) {
	$excerpt = vcex_get_excerpt( [
		'length'  => ! empty( $atts['length'] ) ? $atts['length'] : '30',
		'context' => 'vcex_post_excerpt',
		'post_id' => vcex_get_the_ID(),
	] );
}

if ( empty( $excerpt ) ) {
	return;
}

$wrap_class = [
	'vcex-post-excerpt',
	'vcex-module',
	'wpex-text-pretty',
	'wpex-last-mb-0',
];

if ( ! empty( $atts['max_lines'] ) ) {
	$max_lines_safe = absint( $atts['max_lines'] );
	if ( $max_lines_safe && in_array( $max_lines_safe, [ 1, 2, 3, 4, 5 ], true ) ) {
		$wrap_class[] = "wpex-line-clamp-{$max_lines_safe}";
	}
}

$extra_classes = vcex_get_shortcode_extra_classes( $atts, 'vcex_post_excerpt' );

if ( $extra_classes ) {
	$wrap_class = array_merge( $wrap_class, $extra_classes );
}

$wrap_class = vcex_parse_shortcode_classes( $wrap_class, 'vcex_post_excerpt', $atts );

$html = '<div class="' . esc_attr( trim( $wrap_class ) ) . '"' . vcex_get_unique_id( $atts['unique_id'] ) . '>';
	$html .= wpautop( vcex_parse_text_safe( $excerpt ) );
$html .= '</div>';

echo $html; // @codingStandardsIgnoreLine
