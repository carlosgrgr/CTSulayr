<?php

defined( 'ABSPATH' ) || exit;

/**
 * Toggle Group Shortcode.
 */
if ( ! class_exists( 'Vcex_Toggle_Group_Shortcode' ) ) {

	class Vcex_Toggle_Group_Shortcode extends \TotalThemeCore\Vcex\Shortcode_Abstract {

		/**
		 * Shortcode tag.
		 */
		public const TAG = 'vcex_toggle_group';

		/**
		 * Main constructor.
		 */
		public function __construct() {
			parent::__construct();
		}

		/**
		 * Shortcode title.
		 */
		public static function get_title(): string {
			return esc_html__( 'Toggle (FAQ) Group', 'total-theme-core' );
		}

		/**
		 * Shortcode description.
		 */
		public static function get_description(): string {
			return esc_html__( 'Create an accordion using toggle elements', 'total-theme-core' );
		}

		/**
		 * Returns custom vc map settings.
		 */
		public static function get_vc_lean_map_settings(): array {
			return [
				'allowed_container_element' => false,
				'is_container' => true,
				'content_element' => true,
				'js_view' => 'VcColumnView',
				'as_parent' => [ 'only' => 'vcex_toggle' ],
			];
		}

		/**
		 * Array of shortcode parameters.
		 */
		public static function get_params_list(): array {
			return [
				[
					'type' => 'vcex_ofswitch',
					'std' => 'true',
					'heading' => esc_html__( 'Accordion', 'total-theme-core' ),
					'param_name' => 'accordion',
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Animate', 'total-theme-core' ),
					'notice' => esc_html__( 'Due to how WPBakery functions, changes to this option won\'t appear in the frontend editor but will be visible on the live site.', 'total-theme-core' ),
					'param_name' => 'animate',
					'std' => 'true',
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'FAQ Markup', 'total-theme-core' ),
					'param_name' => 'faq_microdata',
					'std' => 'false',
					'description' => esc_html__( 'Enable to include FAQ microdata markup for use with FAQ schema page types.', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Animation Speed', 'total-theme-core' ),
					'notice' => esc_html__( 'Due to how WPBakery functions, changes to this option won\'t appear in the frontend editor but will be visible on the live site.', 'total-theme-core' ),
					'param_name' => 'animation_speed',
					'choices' => [
						''     => esc_html__( 'Default', 'total-theme-core' ),
						'75'   => '75ms',
						'100'  => '100ms',
						'150'  => '150ms',
						'200'  => '200ms',
						'300'  => '300ms',
						'400'  => '400ms',
						'500'  => '500ms',
						'600'  => '600ms',
						'700'  => '700ms',
						'1000' => '1000ms',
					],
					'dependency' => [ 'element' => 'animate', 'value' => 'true' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Visibility', 'total-theme-core' ),
					'param_name' => 'visibility',
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Element ID', 'total-theme-core' ),
					'param_name' => 'unique_id',
					'admin_label' => true,
					'description' => self::param_description( 'unique_id' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Extra class name', 'total-theme-core' ),
					'param_name' => 'el_class',
					'description' => self::param_description( 'el_class' ),
				],
				vcex_vc_map_add_css_animation(),
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Duration', 'total'),
					'param_name' => 'animation_duration',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total'),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Delay', 'total'),
					'param_name' => 'animation_delay',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total'),
				],
				// Style
				[
					'type' => 'vcex_select_buttons',
					'std' => 'w-borders',
					'heading' => esc_html__( 'Style', 'total-theme-core' ),
					'param_name' => 'style',
					'choices' => [
						'w-borders' => esc_html__( 'Bordered', 'total-theme-core' ),
						'boxed' => esc_html__( 'Boxed', 'total-theme-core' ),
						'none' => esc_html__( 'None', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_ofswitch',
					'std' => 'false',
					'heading' => esc_html__( 'Remove First Border', 'total-theme-core' ),
					'param_name' => 'no_top_border',
					'dependency' => [ 'element' => 'style', 'value' => 'w-borders' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_ofswitch',
					'std' => 'false',
					'heading' => esc_html__( 'Remove Last Border', 'total-theme-core' ),
					'param_name' => 'no_bottom_border',
					'dependency' => [ 'element' => 'style', 'value' => 'w-borders' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Bottom Margin', 'total-theme-core' ),
					'param_name' => 'bottom_margin',
					'admin_label' => true,
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Max Width', 'total-theme-core' ),
					'param_name' => 'max_width',
					'css' => true,
					'description' => self::param_description( 'width' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Border Color', 'total-theme-core' ),
					'param_name' => 'border_color',
					'css' => [ 'selector' => '.vcex-toggle', 'property' => '--vcex-border-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'w-borders' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Border Spacing', 'total-theme-core' ),
					'param_name' => 'border_spacing',
					'css' => [ 'property' => '--vcex-spacing' ],
					'description' => self::param_description( 'margin' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'w-borders' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Border Width', 'total-theme-core' ),
					'param_name' => 'border_width',
					'choices' => [
						'' => esc_html__( 'Default', 'total-theme-core' ),
						'1px' => '1px',
						'2px' => '2px',
						'3px' => '3px',
						'4px' => '4px',
					],
					'css' => [ 'selector' => '.vcex-toggle', 'property' => '--vcex-border-width' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'w-borders' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'heading' => esc_html__( 'Gap Between Toggles', 'total-theme-core' ),
					'param_name' => 'gap',
					'css' => [ 'property' => '--vcex-spacing' ],
					'description' => self::param_description( 'margin' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => [ 'none', 'boxed' ] ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'item_background',
					'css' => [ 'selector' => '.vcex-toggle', 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Hover', 'total-theme-core' ),
					'param_name' => 'item_background_hover',
					'css' => [ 'selector' => '.vcex-toggle:not(.vcex-toggle--active):hover', 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Active', 'total-theme-core' ),
					'param_name' => 'item_background_active',
					'css' => [ 'selector' => '.vcex-toggle--active', 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'transition_duration',
					'heading' => esc_html__( 'Color Transition Speed', 'total-theme-core' ),
					'param_name' => 'item_transition_duration',
					'css' => [ 'selector' => [ '.vcex-toggle', '.vcex-toggle__trigger' ], 'property' => 'transition-duration' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'padding',
					'heading' => esc_html__( 'Vertical Padding ', 'total-theme-core' ),
					'param_name' => 'item_padding_y',
					'css' => [ 'property' => '--vcex-toggle__padding-block' ],
					'description' => self::param_description( 'padding' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'padding',
					'heading' => esc_html__( 'Horizontal Padding ', 'total-theme-core' ),
					'param_name' => 'item_padding_x',
					'description' => self::param_description( 'padding' ),
					'css' => [ 'property' => '--vcex-toggle__padding-inline' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'border_radius',
					'exclude_choices' => [ 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-4xl', 'rounded-full' ],
					'heading' => esc_html__( 'Border Radius ', 'total-theme-core' ),
					'param_name' => 'item_border_radius',
					'css' => [ 'selector' => '.vcex-toggle', 'property' => 'border-radius', ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				// Headings
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Inline Heading', 'total-theme-core' ),
					'param_name' => 'heading_inline',
					'std' => 'false',
					'description' => esc_html__( 'Enable to display the heading inline so any white space next to the heading is not clickable.', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value_not_equal_to' => 'boxed' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Heading Tag', 'total-theme-core' ),
					'param_name' => 'heading_tag',
					'std' => 'div',
					'choices' => [
						'div' => 'div',
						'h2' => 'h2',
						'h3' => 'h3',
						'h4' => 'h4',
						'h5' => 'h5',
						'h6' => 'h6',
					],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
					'description' => esc_html__( 'Used for SEO reasons only, not styling.', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'heading_color',
					'css' => [ 'selector' => '.vcex-toggle__trigger', 'property' => 'color' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Hover', 'total-theme-core' ),
					'param_name' => 'heading_color_hover',
					'css' => [ 'selector' => '.vcex-toggle__trigger:hover', 'property' => 'color' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Active', 'total-theme-core' ),
					'param_name' => 'heading_color_active',
					'css' => [ 'selector' => '.vcex-toggle__trigger[aria-expanded="true"]', 'property' => 'color' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'heading_font_size',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-size' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_font_family_select',
					'choices' => 'heading_font_family',
					'heading' => esc_html__( 'Font Family', 'total-theme-core' ),
					'param_name' => 'heading_font_family',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-family' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'param_name' => 'heading_font_weight',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight: Active', 'total-theme-core' ),
					'param_name' => 'heading_font_weight_active',
					'css' => [ 'selector' => '.vcex-toggle--active .vcex-toggle__heading', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Headings', 'total-theme-core' ),
				],
				// Icons
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Icon Type', 'total-theme-core' ),
					'notice' => esc_html__( 'Due to how WPBakery functions, changes to this option won\'t appear in the frontend editor but will be visible on the live site.', 'total-theme-core' ),
					'param_name' => 'icon_type',
					'std' => 'plus',
					'choices' => [
						'plus'  => esc_html__( 'Plus', 'total-theme-core' ),
						'angle' => esc_html__( 'Angle', 'total-theme-core' ),
						'none'  => esc_html__( 'None', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Icon Position', 'total-theme-core' ),
					'param_name' => 'icon_position',
					'std' => 'left',
					'choices' => [
						'left' => esc_html__( 'Left', 'total-theme-core' ),
						'right' => esc_html__( 'Right', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Icon Color', 'total-theme-core' ),
					'param_name' => 'icon_color',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'color' ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Icon Background', 'total-theme-core' ),
					'param_name' => 'icon_background',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'background-color' ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'border_radius',
					'heading' => esc_html__( 'Icon Border Radius', 'total-theme-core' ),
					'param_name' => 'icon_border_radius',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'border-radius' ],
					'dependency' => [ 'element' => 'icon_background', 'not_empty' => true ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'gap',
					'heading' => esc_html__( 'Icon Spacing', 'total-theme-core' ),
					'param_name' => 'icon_spacing',
					'css' => [ 'selector' => '.vcex-toggle__trigger', 'property' => 'gap' ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Icon Size', 'total-theme-core' ),
					'description' => esc_html__( 'Controls the height and width of the icon SVG.', 'total-theme-core' ),
					'param_name' => 'icon_size',
					'css' => [ 'selector' => '.vcex-toggle__icon svg', 'property' => [ 'width', 'height' ] ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Icon Dimensions', 'total-theme-core' ),
					'description' => esc_html__( 'Controls the height and width of the icon wrapper div.', 'total-theme-core' ),
					'param_name' => 'icon_dims',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => [ 'width', 'height' ] ],
					'group' => esc_html__( 'Icons', 'total-theme-core' ),
				],
				// Content
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'content_color',
					'css' => [ 'selector' => '.vcex-toggle__content', 'property' => 'color', ],
					'group' => esc_html__( 'Content', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'content_font_size',
					'css' => [ 'selector' => '.vcex-toggle__content', 'property' => 'font-size' ],
					'group' => esc_html__( 'Content', 'total-theme-core' ),
				],
				// Design
				[
					'type' => 'css_editor',
					'heading' => esc_html__( 'CSS box', 'total-theme-core' ),
					'param_name' => 'css',
					'group' => esc_html__( 'CSS', 'total-theme-core' ),
				],
				// Hidden
				[ 'type' => 'hidden', 'param_name' => 'parse_content', 'std' => 'true' ]
			];
		}

	}

}

new Vcex_Toggle_Group_Shortcode;

if ( class_exists( 'WPBakeryShortCodesContainer' ) && ! class_exists( 'WPBakeryShortCode_Vcex_Toggle_Group' ) ) {
	class WPBakeryShortCode_Vcex_Toggle_Group extends WPBakeryShortCodesContainer {}
}
