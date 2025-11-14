<?php

namespace TotalThemeCore\WPBakery\Params;

defined( 'ABSPATH' ) || exit;

/**
 * WPBakery Param => Preset textfield.
 */
final class Custom_Field {

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Choices to return.
	 */
	protected static $choices = '';

	/**
	 * Field output.
	 */
	public static function output( $settings, $value ): string {
		$is_preset     = false;
		$input_value   = $value; // store og value since some may use the format key|type
		self::$choices = $settings['choices'] ?? 'all';

		$html = '<div class="vcex-param-custom-field">';

			if ( $choices = self::get_choices() ) {
				if ( $value && str_contains( $value, '|' ) ) {
					$parts = explode( '|', $value, 2 );
					$value = $parts[0]; // use field name only for select
				}
				$html .= '<select class="vcex-param-custom-field__select wpb-input wpb-select">';
				foreach ( $choices as $key => $val ) {
					if ( is_array( $val ) ) {
						$html .= '<optgroup label="' . esc_attr( $val['label'] ) . '">';
						foreach ( $val['options'] as $option_key => $option_val ) {
							if ( ! $is_preset && $value && $option_key === $value ) {
								$is_preset = true;
							}
							$html .= '<option value="' . esc_attr( $option_key )  . '" ' . selected( $value, $option_key, false ) . '>' . esc_attr( $option_val ) . '</option>';
						}
						$html .= '</optgroup>';
					} else {
						$html .= '<option value="' . esc_attr( $key )  . '" ' . selected( $value, $key, false ) . '>' . esc_attr( $val ) . '</option>';
					}
				}
				$html .= '</select>';
			}

			// Acf option checkbox
			$is_acf_option = $input_value && str_ends_with( $input_value, '|option' );
			if ( function_exists( 'acf_get_options_pages' ) || $is_acf_option ) {
				$option_checkbox_is_checked = false;
				if ( $is_acf_option ) {
					$option_checkbox_is_checked = true;
				}
				$option_checkbox_class = 'vcex-param-custom-field__acf-option-checkbox';
				if ( ! $option_checkbox_is_checked ) {
					$option_checkbox_class .= ' hidden';
				}
				$html .= '<div class="' . esc_attr( $option_checkbox_class ) . '"><label><input type="checkbox" ' . checked( $option_checkbox_is_checked, true, false ) . '>' . esc_html__( 'Get from Options Page', 'total-theme-core' ) . '</label></div>';
			}

			// Hidden input
			$field_type = ( ! $choices || $is_preset ) ? 'hidden' : 'text';
			
			$html .= '<input placeholder="' . esc_html( 'Enter your custom field name or ACF field key here', 'total-theme-core' ) . '" name="' . esc_attr( $settings['param_name'] ) . '" class="vcex-param-custom-field__input wpb_vc_param_value  ' . esc_attr( $settings['param_name'] ) . ' ' . esc_attr( $settings['type'] ) . '_field" type="' . esc_attr( $field_type ) . '" value="' . esc_attr( $input_value ) . '">';

		$html .= '</div>';

		return $html;
	}

	/**
	 * Returns choices for select.
	 */
	protected static function get_choices(): array {
		if ( is_array( self::$choices ) ) {
			return self::$choices;
		}

		$allowed_choices = self::get_allowed_choices();

		$choices = [
			'' => esc_html__( 'Custom', 'total-theme-core' ),
		];

		// Add ACF fields first as they will be used the most
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			$acf_groups = (array) acf_get_field_groups();
			foreach ( $acf_groups as $group ) {
				$group_title = $group['title'] ?? '';
				if ( ! empty( $group['key'] ) ) {
					$group_id = sanitize_key( $group['key'] );
				} elseif ( ! empty( $group['ID'] ) ) {
					$group_id = sanitize_key( $group['ID'] );
				} elseif ( ! empty( $group['id'] ) ) {
					$group_id = sanitize_key( $group['id'] );
				} else {
					$group_id = null;
				}
				if ( $group_id ) {
					$fields = (array) acf_get_fields( $group_id );
					if ( $fields ) {
						$group_options = self::process_acf_fields( $fields );
						if ( $group_options ) {
							$group_title = esc_html( $group['title'] ?? $group_id );
							$choices[ "acf_{$group_id}" ] = [
								'label'   => "ACF - {$group_title}",
								'options' => $group_options,
							];
						}
					}
				}
			}
		}

		// Add theme fields last
		$theme_choices = self::get_theme_choices( $allowed_choices );

		if ( $theme_choices ) {
			$choices = array_merge( $choices, $theme_choices );
		}

		// Apply filters and return choices
		return (array) apply_filters( 'vcex_custom_field_param_choices', $choices, $allowed_choices );
	}

	/**
	 * Loops through acf groups to return allowed fields.
	 */
	private static function process_acf_fields( $fields = [], $group_options = [], $repeater_field = '' ) {
		foreach ( $fields as $field ) {
			$field_type = $field['type'] ?? '';
			if ( 'repeater' === $field_type ) {
				$sub_fields = $field['sub_fields'] ?? [];
				$group_options = array_merge( $group_options, self::process_acf_fields( $sub_fields, $group_options, $field ) );
			}
			if ( ! empty( $field['key'] ) && self::is_field_type_allowed( $field_type ) ) {
				$label = $field['label'] ?? $field['key'];
				if ( $repeater_field ) {
					$repeater_field_label = $repeater_field['label'] ?? $repeater_field['key'];
					$label = "{$repeater_field_label} > {$label}";
				}
				$group_options[ $field['key'] ] = $label;
			}
		}
		return $group_options;
	}

	/**
	 * Returns theme choices by type.
	 */
	private static function get_theme_choices( $type = 'all' ): array {
		$fields = totalthemecore_call_static( 'Meta\Main_Metabox', 'get_all_public_fields' );

		$choices = [];
		$fields  = totalthemecore_call_static( 'Meta\Main_Metabox', 'get_all_public_fields' );

		if ( empty( $fields ) || ! is_array( $fields ) ) {
			return $choices;
		}

		foreach ( $fields as $field_id => $field ) {
			if ( ! isset( $field['group'] ) || ! isset( $field['name'] ) || ! isset( $field['type'] ) ) {
				continue;
			}

			if ( 'all' !== $type && ! self::is_field_type_allowed( $field['type'] ) ) {
				continue;
			}

			$group_key = sanitize_key( $field['group'] );

			// Initialize group if it doesn't exist
			if ( ! isset( $choices[ $group_key ] ) ) {
				$choices[ $group_key ] = [
					'label'   => esc_html( $field['group'] ),
					'options' => [],
				];
			}

			// Add the field to the group's options
			$choices[ $group_key ]['options'][ $field_id ] = esc_html( $field['name'] );
		}

		return $choices;
	}

	/**
	 * Returns all choices.
	 * 
	 * @important This function is used for the custom field backend view!
	 */
	public static function get_theme_choices_all(): array {
		return self::get_theme_choices( 'all' );
	}

	/**
	 * Returns link choices.
	 */
	protected static function get_theme_choices_link(): array {
		$choices = [
			'main' => [
				'label' => esc_attr( 'Theme Settings', 'total-theme-core' ),
				'options' => [
					'wpex_post_link' => esc_html__( 'Redirect', 'total-theme-core' ),
				],
			],
		];

		if ( class_exists( 'TotalThemeCore\Cpt\Portfolio', false ) && post_type_exists( 'portfolio' ) ) {
			$choices['portfolio'] = [
				'label' => get_post_type_object( 'portfolio' )->labels->singular_name,
				'options' => [
					'wpex_portfolio_url' => esc_html__( 'Company URL', 'total-theme-core' ),
				],
			];
		}

		if ( class_exists( 'TotalThemeCore\Cpt\Testimonials', false ) && post_type_exists( 'testimonials' ) ) {
			$choices['testimonials'] = [
				'label' => get_post_type_object( 'testimonials' )->labels->singular_name,
				'options' => [
					'wpex_testimonial_url' => esc_html__( 'Company URL', 'total-theme-core' ),
				],
			];
		}

		return $choices;
	}

	/**
	 * Returns Allowed Fields.
	 */
	protected static function get_allowed_choices() {
		$choices = self::$choices;
		if ( 's' === substr( $choices, -1 ) ) {
			$choices = substr( $choices, 0, -1 );
		}
		return $choices;
	}

	/**
	 * Check if field type is allowed.
	 */
	protected static function is_field_type_allowed( $type ): bool {
		return in_array( $type, self::allowed_field_types(), true );
	}

	/**
	 * Returns allowed field types for the current field.
	 */
	protected static function allowed_field_types(): array {
		$types = [];

		switch ( self::get_allowed_choices() ) {
			case 'posts':
			case 'post';
				$types = [ 'post_object', 'relationship' ];
				break;
			case 'image':
				$types = [ 'image' ];
				break;
			case 'video':
				$types = [ 'oembed', 'file', 'self_hosted' ];
				break;
			case 'gallery':
				$types = [ 'gallery' ];
				break;
			case 'number':
			case 'percent':
				$types = [ 'text', 'number' ];
				break;
			case 'text':
				$types = [
					'message',
					'text',
					'textarea',
					'wysiwyg',
					'image',
					'file',
					'oembed',
					'range',
					'email',
					'url',
					'link',
					'select',
					'button_group',
					'radio',
					'date_picker',
					'date_time_picker',
					'time_picker',
					'number',
				];
				break;
			case 'link':
				$types = [
					'link',
					'image',
					'file',
					'oembed',
					'email',
					'url',
					'text',
					'email',
				];
				break;
			case 'date':
				$types = [
					'date_picker',
					'date_time_picker',
				];
				break;
			default:
				$types = [
					'message',
					'textarea',
					'text',
					'select',
					'number',
					'link',
					'google_map',
					'wysiwyg',
					'file',
					'range',
					'email',
					'url',
					'link',
					'image',
					'oembed',
					'date_picker',
					'date_time_picker',
					'time_picker',
					'button_group',
					'radio',
				];
				break;
		}

		return $types;
	}

}
