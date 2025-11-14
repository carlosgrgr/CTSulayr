<?php

namespace TotalThemeCore\WPBakery\Params;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakery Param => Grid Column Responsive.
 */
final class Grid_Column_Responsive {

	public static function output( $settings, $value ) {

		if ( function_exists( '\wpex_grid_columns' ) ) {

			$medias = [
				'tl' => [
					'label' => esc_html__( 'Tablet Landscape', 'total-theme-core' ),
					'breakpoint' => '1024px',
				],
				'tp' => [
					'label' => esc_html__( 'Tablet Portrait', 'total-theme-core' ),
					'breakpoint' => '959px',
				],
				'pl' => [
					'label' => esc_html__( 'Phone Landscape', 'total-theme-core' ),
					'breakpoint' => '767px',
				],
				'pp' => [
					'label' => esc_html__( 'Phone Portrait', 'total-theme-core' ),
					'breakpoint' => '479px',
				],
			];

			$defaults = [];

			foreach ( $medias as $key => $val ) {
				$defaults[ $key ] = '';
			}

			$field_values = vcex_parse_multi_attribute( $value, $defaults );

			$output = '<div class="vcex-responsive-columns-param"><div class="wpex-row wpex-clr">';

				$grid_options = wpex_grid_columns();

				foreach ( $medias as $media_key => $media_val ) {
					$field_value = $field_values[ $media_key ] ?? '';
					$output .= '<div class="vc_col-sm-6">';
						$output .= '<div class="wpb_element_label">';
							$output .= esc_attr( $media_val['label'] );
							$output .= '<small>(max-width: ' . esc_html( $media_val['breakpoint'] )  . ')</small>';
						$output .= '</div>';
						$output .= '<select name="' . esc_attr( $media_key ) . '" class="vcex-responsive-column-select">';
							$output .= '<option value="" ' . selected( '', $field_value, false ) . '>'. esc_attr__( 'Default', 'total-theme-core' ) .'</option>';
							foreach ( $grid_options as $grid_option_key => $grid_option_name ) {
								$output .= '<option value="' . esc_attr( $grid_option_key )  .  '" ' . selected( $field_value, $grid_option_key, false ) . '>' . esc_attr( $grid_option_name ) . '</option>';
							}
						$output .= '</select>';
					$output .= '</div>';
				}

			// Add hidden field
			$output .= '<input name="' . esc_attr( $settings['param_name'] ) . '" class="wpb_vc_param_value  ' . esc_attr( $settings['param_name'] ) . ' ' . esc_attr( $settings['type'] ) . '_field" type="hidden" value="' . esc_attr( $value ) . '">';

	   		// Close wrapper
			$output .= '</div></div>';

		} else {
			$output = vcex_total_exclusive_notice();
			$output .= '<input type="hidden" class="wpb_vc_param_value '
					. esc_attr( $settings['param_name'] ) . ' '
					. esc_attr( $settings['type'] ) . '" name="' . esc_attr( $settings['param_name'] ) . '" value="' . esc_attr( $value ) . '">';
		}

		return $output;

	}

}
