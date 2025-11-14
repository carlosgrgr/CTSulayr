<?php
/**
 * vcex_gitem_post_terms shortcode output
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

$taxonomy = ! empty( $atts['taxonomy'] ) ? sanitize_key( $atts['taxonomy'] ) : '';

if ( ! $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
	return;
}

// Get terms for live preview
if ( 'vc_grid_item' == get_post_type( $post->ID ) ) {
	$terms = get_terms( $taxonomy, [
		'hide_empty' => false,
		'number'     => 4,
	] );
}

// Get terms for live site
else {

	// Sanitize order
	if ( isset( $atts['order'] ) && in_array( strtoupper( $atts['order'] ), [ 'ASC', 'DESC' ], true ) ) {
		$order = $atts['order'];
	} else {
		$order = 'DESC';
	}

	// Sanitize orderby
	if ( isset( $atts['orderby'] ) && in_array( $atts['orderby'], [ 'name', 'slug', 'term_group', 'term_id', 'id', 'description' ], true ) ) {
		$orderby = $atts['orderby'];
	} else {
		$orderby = 'name';
	}

	$query_args = [
		'order'   => $order,
		'orderby' => $orderby,
		'fields'  => 'all',
	];

	$query_args = apply_filters( 'vcex_post_terms_query_args', $query_args );

	$terms = wp_get_post_terms( get_the_ID(), $taxonomy, $query_args );
}

// Terms needed
if ( ! $terms || is_wp_error( $terms ) ) {
	return;
}

// Wrap classes
$wrap_classes = 'vcex-post-terms wpex-clr';

if ( $atts['visibility'] ) {

	$wrap_classes .= ' ' . vcex_parse_visibility_class( $atts['visibility'] );
}

if ( $atts['classes'] ) {
	$wrap_classes .= ' ' . vcex_get_extra_class( $atts['classes'] );
}

if ( 'center' == $atts['button_align'] ) {
	$wrap_classes .= ' textcenter';
}

if ( ! empty( $atts['css'] ) ) {
	$wrap_classes .= ' ' . vcex_vc_shortcode_custom_css_class( $atts['css'] );
}

// Get button classes
$button_classes = vcex_get_button_classes( $atts['button_style'], $atts['button_color_style'], $atts['button_size'], $atts['button_align'] );
if ( ! empty( $atts['css_animation'] ) && 'none' != $atts['css_animation'] ) {
	$button_classes .= ' ' . vcex_get_css_animation( $atts['css_animation'] );
}

// Button Style
$button_style = vcex_inline_style( array(
	'margin'         => $atts['button_margin'],
	'color'          => $atts['button_color'],
	'background'     => $atts['button_background'],
	'padding'        => $atts['button_padding'],
	'font_size'      => $atts['button_font_size'],
	'font_weight'    => $atts['button_font_weight'],
	'font_family'    => $atts['button_font_family'],
	'border_radius'  => $atts['button_border_radius'],
	'text_transform' => $atts['button_text_transform'],
) );

// Button data
$button_hover_data = array();
$button_data = '';
if ( $atts['button_hover_background'] ) {
	$button_hover_data['background'] = $atts['button_hover_background'];
}
if ( $atts['button_hover_color'] ) {
	$button_hover_data['color'] = $atts['button_hover_color'];
}
if ( $button_hover_data ) {
	$button_data = " data-wpex-hover='" . htmlspecialchars( wp_json_encode( $button_hover_data ) ) . "'";
}

// Get excluded terms
if ( $atts['exclude_terms'] ) {
	$exclude_terms = preg_split( '/\,[\s]*/', $atts['exclude_terms'] );
} else {
	$exclude_terms = array();
}

// Define output var
$output = '';

// Get total count
$tcount = count( $terms );

// VC filter
$wrap_classes = vcex_parse_shortcode_classes( $wrap_classes, 'vcex_post_terms', $atts );

// Begin output
$output .= '<div class="' . esc_attr( $wrap_classes ) . '">';

	foreach ( $terms as $term ) :

		if ( in_array( $term->slug, $exclude_terms ) ) {
			continue;
		}

		if ( 'true' == $atts['archive_link'] ) {
			$output .= '<a href="' . get_term_link( $term, $atts['taxonomy'] ) . '" class="' . esc_attr( $button_classes ) . '"' . $button_style . $button_data . '>';
		} else {
			$output .= '<span class="' . esc_attr( $button_classes ) . '"' . $button_style . $button_data . '>';
		}

		$output .= $term->name;

		if ( 'true' == $atts['archive_link'] ) {
			$output .= '</a>';
		} else {
			$output .= '</span>';
		}

	endforeach;

$output .= '</div>';

// @codingStandardsIgnoreLine
echo $output;
