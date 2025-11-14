<?php

/**
 * Grid Container shortcode template.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

$wrap_class = [
	'vcex-grid-container',
	'vcex-module',
	'wpex-grid',
];

if ( empty( $atts['el_class'] ) || ! str_contains( (string) $atts['el_class'], '-gap-' ) ) {
	$wrap_class[] = 'wpex-gap-20';
}

if ( ! empty( $atts['width'] ) ) {
	$align = ! empty( $atts['align'] ) ? $atts['align'] : 'center';
	$wrap_class[] = vcex_parse_align_class( $align );
}

$wrap_class[] = 'wpex-grid-cols-' . sanitize_html_class( absint( $atts['columns'] ) );

$breakpoints = [ 'sm', 'md', 'lg', 'xl' ];

foreach ( $breakpoints as $bk ) {
	if ( ! empty( $atts["columns_{$bk}"] ) && is_numeric( $atts["columns_{$bk}"] ) ) {
		$cols = sanitize_html_class( absint( $atts["columns_{$bk}"] ) );
		$wrap_class[] = "wpex-{$bk}-grid-cols-{$cols}";
	}
}

if ( ! empty( $atts['align_items'] ) ) {
	$wrap_class[] = vcex_parse_align_items_class( $atts['align_items'] );
}

if ( ! empty( $atts['justify_items'] ) ) {
	$wrap_class[] = vcex_parse_justify_items_class( $atts['justify_items'] );
}

if ( $extra_classes = vcex_get_shortcode_extra_classes( $atts, 'vcex_grid_container' ) ) {
	$wrap_class = array_merge( $wrap_class, $extra_classes );
}

$wrap_class = vcex_parse_shortcode_classes( $wrap_class, 'vcex_grid_container', $atts );

$html = '<div class="' . esc_attr( $wrap_class ) . '"' . vcex_get_unique_id( $atts ) . '>';

	$html .= do_shortcode( wp_kses_post( $content ) );

$html .= '</div>';

echo $html; // @codingStandardsIgnoreLine
