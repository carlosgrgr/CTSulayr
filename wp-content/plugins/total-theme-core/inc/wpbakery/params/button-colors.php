<?php

namespace TotalThemeCore\WPBakery\Params;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakery Param => Button Colors.
 */
final class Button_Colors {

	/**
	 * Param output.
	 */
	public static function output( $settings, $value ) {

		if ( function_exists( 'wpex_get_accent_colors' ) ) {

			$colors = self::get_accent_colors();

			if ( ! $colors ) {
				return;
			}

			$output = '<select name="'
				. $settings['param_name']
				. '" class="wpb_vc_param_value wpb-input wpb-select '
				. $settings['param_name']
				. ' ' . $settings['type'] .'">';

			foreach ( $colors as $key => $settings ) {
				$key   = ( 'default' === $key ) ? '' : $key;
				$hex   = $settings[ 'hex' ] ?? '#fff';
				$label = $settings[ 'label' ] ?? '';

				$output .= '<option';
					$output .= ' value="' . esc_attr( $key )  . '"';
					$output .= ' ' . selected( $value, $key, false );
				$output .= '>';
					$output .= esc_attr( $label );
				$output .= '</option>';
			}

			$output .= '</select>';

		} else {
			$output = vcex_total_exclusive_notice();
			$output .= '<input type="hidden" class="wpb_vc_param_value '
					. esc_attr( $settings['param_name'] ) . ' '
					. esc_attr( $settings['type'] ) . '" name="' . esc_attr( $settings['param_name'] ) . '" value="' . esc_attr( $value ) . '">';
		}

		return $output;
	}

	/**
	 * Get accent colors.
	 */
	private static function get_accent_colors() {
		$colors = wpex_get_accent_colors();
		if ( $colors && is_array( $colors ) ) {
			return $colors;
		}
	}

}
