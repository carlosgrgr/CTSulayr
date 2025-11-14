<?php

namespace TotalThemeCore\WPBakery\Params;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakery Param => Number.
 */
final class Number {
	public static function output( $settings, $value ) {
		$value = $value ? floatval( $value ) : '';
		$min   = isset( $settings['min'] ) ? floatval( $settings['min'] ) : '0';
		$step  = isset( $settings['step'] ) ? floatval( $settings['step'] ) : '';
		$max   = isset( $settings['max'] ) ? floatval( $settings['max'] ) : '';
		$output = '<input';
			$output .= ' name="' . esc_attr( $settings['param_name'] ) . '"';
			$output .= ' class="wpb_vc_param_value wpb-input wpb-select ' . esc_attr( $settings['param_name'] ) . ' vcex_number_field"';
			$output .= 'type="number"';
			$output .= 'value="' . esc_attr( $value ) . '"';
			if ( $min || '0' === $min ) {
				$output .= 'min="' . esc_attr( $min ). '"';
			}
			if ( $max || '0' === $min ) {
				$output .= 'max="' . esc_attr( $max ) . '"';
			}
			if ( $step ) {
				$output .= 'step="' . esc_attr( $step ) . '"';
			}
			if ( ! empty( $settings['placeholder'] ) ) {
				$output .= 'placeholder="' . esc_attr( $settings['placeholder'] ) . '"';
			}
			if ( $min && $max ) {
				$output .= 'placeholder="' . esc_html__( 'range', 'total-theme-core' ) . ' ' . esc_attr( $min ) . '&dash;' . esc_attr( $max ) . '"';
			}
		$output .= '>';
		return $output;
	}
}
