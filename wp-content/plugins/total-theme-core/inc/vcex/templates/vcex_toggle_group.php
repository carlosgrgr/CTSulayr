<?php

/**
 * Toggle Group.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

if ( ! $content ) {
	return;
}

$output    = '';
$vc_inline = vcex_vc_is_inline();
$style     = ( ! empty( $atts['style'] ) && 'none' !== $atts['style'] ) ? sanitize_text_field( $atts['style'] ) : '';

$wrap_class = [
	'vcex-toggle-group',
];

// wpb frontend editor fixes
if ( $vc_inline ) {
	if ( 'boxed' === $style ) {
		$wrap_class[] = 'vcex-vc_inline-toggle-group-boxed';
	}
	if ( ! empty( $atts['icon_position'] ) && 'right' === $atts['icon_position'] ) {
		$wrap_class[] = 'vcex-toggle-group--icon-end';
	}
	if ( vcex_validate_att_boolean( 'heading_inline', $atts ) ) {
		$wrap_class[] = 'vcex-toggle-group--heading-inline';
	}
}

if ( $style && 'boxed' !== $style ) {
	$wrap_class[] = 'vcex-toggle-group--' . sanitize_html_class( $style );
	if ( 'w-borders' === $style ) {
		if ( vcex_validate_att_boolean( 'no_top_border', $atts ) ) {
			$wrap_class[] = 'vcex-toggle-group--no-top-border';
		}
		if ( vcex_validate_att_boolean( 'no_bottom_border', $atts ) ) {
			$wrap_class[] = 'vcex-toggle-group--no-bottom-border';
		}
	}
}

$wrap_class[] = 'vcex-module';
$wrap_class[] = 'wpex-mx-auto';

$extra_classes = vcex_get_shortcode_extra_classes( $atts, 'vcex_toggle_group' );

if ( $extra_classes ) {
	$wrap_class = array_merge( $wrap_class, $extra_classes );
}

$wrap_class = vcex_parse_shortcode_classes( $wrap_class, 'vcex_toggle_group', $atts );

$output .= '<div' . vcex_get_unique_id( $atts ) . ' class="' . esc_attr( $wrap_class ) . '" data-vcex-accordion="' . esc_attr( absint( vcex_validate_att_boolean( 'accordion', $atts, true ) ) ) . '">';
	if ( $vc_inline ) {
		$output .= do_shortcode( $content );
	} else {
		if ( vcex_validate_att_boolean( 'parse_content', $atts, true ) ) {
			$regex = get_shortcode_regex( [ 'vcex_toggle' ] );
			preg_match_all( "/{$regex}/", $content, $matches );
			if ( empty( $matches[0] ) ) {
				return '';
			}
			$content_sanitized = implode( ' ', $matches[0] );
			$single_attrs = [
				'animate'        => ! empty( $atts['animate'] ) ? sanitize_text_field( $atts['animate']  ) : '',
				'faq_microdata'  => ! empty( $atts['faq_microdata'] ) ? sanitize_text_field( $atts['faq_microdata']  ) : '',
				'icon_position'  => ( ! empty( $atts['icon_position'] ) && 'right' === $atts['icon_position'] ) ? 'right' : '',
			];
			if ( 'boxed' !== $style && vcex_validate_att_boolean( 'heading_inline', $atts ) ) {
				$single_attrs['heading_inline'] = true;
			}
			if ( ! empty( $atts['heading_tag'] ) ) {
				$single_attrs['heading_tag'] = $atts['heading_tag'];
			}
			if ( 'boxed' === $style ) {
				$single_attrs['style'] = 'boxed';
			}
			if ( ! empty( $atts['icon_type'] ) && 'plus' !== $atts['icon_type'] ) {
				$single_attrs['icon_type'] = $atts['icon_type'];
			}
			if ( ! empty( $atts['icon_spacing'] ) ) {
				$single_attrs['icon_spacing'] = $atts['icon_spacing'];
			}
			foreach ( array_filter( $single_attrs ) as $k => $v ) {
				$content_sanitized = str_replace( '[vcex_toggle', '[vcex_toggle ' . $k . '="' . $v . '"', $content_sanitized );
			}
			$output .= do_shortcode( $content_sanitized );
		} else {
			$output .= $content;
		}
	}
$output .= '</div>';

echo $output; // @codingStandardsIgnoreLine
