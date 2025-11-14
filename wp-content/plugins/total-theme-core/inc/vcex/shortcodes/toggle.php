<?php

defined( 'ABSPATH' ) || exit;

/**
 * Toggle Shortcode.
 */
if ( ! class_exists( 'VCEX_Toggle_Shortcode' ) ) {

	class VCEX_Toggle_Shortcode extends TotalThemeCore\Vcex\Shortcode_Abstract {
		
		/**
		 * Shortcode tag.
		 */
		public const TAG = 'vcex_toggle';

		/**
		 * Main constructor.
		 */
		public function __construct() {
			$this->scripts = $this->scripts_to_register();

			// Call parent constructor.
			parent::__construct();
		}

		/**
		 * Shortcode title.
		 */
		public static function get_title(): string {
			return \esc_html__( 'Toggle (FAQ)', 'total-theme-core' );
		}

		/**
		 * Shortcode description.
		 */
		public static function get_description(): string {
			return esc_html__( 'Show/hide text toggle', 'total-theme-core' );
		}

		/**
		 * Register scripts.
		 */
		public function scripts_to_register(): array {
			return [
				[
					'vcex-toggle',
					vcex_get_js_file( 'frontend/toggle' ),
					[],
					TTC_VERSION,
					true
				]
			];
		}

		/**
		 * Returns list of script dependencies.
		 */
		public static function get_script_depends(): array {
			return [
				'vcex-toggle',
			];
		}

		/**
		 * Returns custom vc map settings.
		 */
		public static function get_vc_lean_map_settings(): array {
			return [
				'admin_enqueue_js' => 'toggle',
				'js_view' => 'vcexToggleView',
			];
		}

		/**
		 * Array of shortcode parameters.
		 */
		public static function get_params_list(): array {
			return [
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Toggle ID (Optional)', 'total-theme-core' ),
					'param_name' => 'content_id',
					'description' => esc_html__( 'Specify a unique ID for this toggle. If left blank, the heading will be used as the ID.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Heading', 'total-theme-core' ),
					'value' => 'Lorem ipsum dolor sit amet?',
					'param_name' => 'heading',
					'description' => self::param_description( 'text' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textarea_html',
					'heading' => esc_html__( 'Content', 'total-theme-core' ),
					'param_name' => 'content',
					'value' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla eros urna, aliquet et porttitor in, congue ut risus. Nunc placerat faucibus ligula a mattis.',
					'admin_label' => true,
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Default State', 'total-theme-core' ),
					'param_name' => 'state',
					'std' => 'closed',
					'choices' => [
						'closed' => esc_html__( 'Closed', 'total-theme-core' ),
						'open' => esc_html__( 'Open', 'total-theme-core' ),
					],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Animate', 'total-theme-core' ),
					'param_name' => 'animate',
					'std' => 'true',
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'FAQ Markup', 'total-theme-core' ),
					'param_name' => 'faq_microdata',
					'std' => 'false',
					'description' => esc_html__( 'Enable to include FAQ microdata markup for use with FAQ schema page types.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Animation Speed', 'total-theme-core' ),
					'param_name' => 'animation_speed',
					'value' => [
						esc_html__( 'Default', 'total-theme-core' ) => '',
						'75ms' => '75',
						'100ms' => '100',
						'150ms' => '150',
						'200ms' => '200',
						'300ms' => '300',
						'400ms' => '400',
						'500ms' => '500',
						'600ms' => '600',
						'700ms' => '700',
						'1000ms' => '1000',
					],
					'dependency' => [ 'element' => 'animate', 'value' => 'true' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Visibility', 'total-theme-core' ),
					'param_name' => 'visibility',
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Extra class name', 'total-theme-core' ),
					'param_name' => 'el_class',
					'description' => self::param_description( 'el_class' ),
					'editors' => [ 'wpbakery', 'elementor' ],
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
					'std' => '',
					'heading' => esc_html__( 'Style', 'total-theme-core' ),
					'param_name' => 'style',
					'choices' => [
						'' => esc_html__( 'Default', 'total-theme-core' ),
						'boxed' => esc_html__( 'Boxed', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Bottom Margin', 'total-theme-core' ),
					'param_name' => 'bottom_margin',
					'admin_label' => true,
					'group' => esc_html__( 'Style', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'background',
					'css' => [ 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Hover', 'total-theme-core' ),
					'param_name' => 'background_hover',
					'css' => [ 'selector' => '{{WRAPPER}}:not(.vcex-toggle--active):hover', 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Active', 'total-theme-core' ),
					'param_name' => 'background_active',
					'css' => [ 'selector' => '{{WRAPPER}}.vcex-toggle--active', 'property' => 'background-color' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'transition_duration',
					'heading' => esc_html__( 'Color Transition Speed', 'total-theme-core' ),
					'param_name' => 'transition_duration',
					'css' => [ 'selector' => [ '{{WRAPPER}}', '.vcex-toggle__trigger' ], 'property' => 'transition-duration' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'padding',
					'heading' => esc_html__( 'Vertical Padding ', 'total-theme-core' ),
					'param_name' => 'padding_y',
					'css' => [ 'property' => '--vcex-toggle__padding-block' ],
					'description' => self::param_description( 'padding' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'padding',
					'heading' => esc_html__( 'Horizontal Padding ', 'total-theme-core' ),
					'param_name' => 'padding_x',
					'description' => self::param_description( 'padding' ),
					'css' => [ 'property' => '--vcex-toggle__padding-inline' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'border_radius',
					'exclude_choices' => [ 'rounded-xl', 'rounded-2xl', 'rounded-3xl', 'rounded-4xl', 'rounded-full' ],
					'heading' => esc_html__( 'Border Radius ', 'total-theme-core' ),
					'param_name' => 'border_radius',
					'css' => [ 'property' => 'border-radius' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'boxed' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Icon
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Icon Type', 'total-theme-core' ),
					'param_name' => 'icon_type',
					'std' => 'plus',
					'choices' => [
						'plus' => esc_html__( 'Plus', 'total-theme-core' ),
						'angle' => esc_html__( 'Angle', 'total-theme-core' ),
						'none' => esc_html__( 'None', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
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
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Icon Color', 'total-theme-core' ),
					'param_name' => 'icon_color',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'color' ],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Icon Background', 'total-theme-core' ),
					'param_name' => 'icon_background',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'background-color' ],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'border_radius',
					'heading' => esc_html__( 'Icon Border Radius', 'total-theme-core' ),
					'param_name' => 'icon_border_radius',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => 'border-radius' ],
					'dependency' => [ 'element' => 'icon_background', 'not_empty' => true ],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'gap',
					'heading' => esc_html__( 'Icon Spacing', 'total-theme-core' ),
					'param_name' => 'icon_spacing',
					'css' => [ 'selector' => '.vcex-toggle__trigger', 'property' => 'gap' ],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Icon Size', 'total-theme-core' ),
					'param_name' => 'icon_size',
					'css' => [ 'selector' => '.vcex-toggle__icon svg', 'property' => [ 'width', 'height' ] ],
					'description' => self::param_description( 'font_size' ),
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Icon Dimensions', 'total-theme-core' ),
					'description' => esc_html__( 'Controls the height and width of the icon wrapper div.', 'total-theme-core' ),
					'param_name' => 'icon_dims',
					'css' => [ 'selector' => '.vcex-toggle__icon', 'property' => [ 'width', 'height' ] ],
					'group' => esc_html__( 'Icon', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Heading
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Inline Heading', 'total-theme-core' ),
					'param_name' => 'heading_inline',
					'std' => 'false',
					'description' => esc_html__( 'Enable to display the heading inline so any white space next to the heading is not clickable.', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value_not_equal_to' => 'boxed' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
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
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'description' => esc_html__( 'Used for SEO reasons only, not styling.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Extra class name', 'total-theme-core' ),
					'param_name' => 'heading_el_class',
					'description' => self::param_description( 'el_class' ),
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'heading_color',
					'css' => [ 'selector' => '.vcex-toggle__trigger', 'property' => 'color' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Hover', 'total-theme-core' ),
					'param_name' => 'heading_color_hover',
					'css' => [ 'selector' => '.vcex-toggle__trigger:hover', 'property' => 'color' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Active', 'total-theme-core' ),
					'param_name' => 'heading_color_active',
					'css' => [ 'selector' => '.vcex-toggle__trigger[aria-expanded="true"]', 'property' => 'color' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'heading_font_size',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-size' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
				],
				[
					'type'  => 'vcex_font_family_select',
					'heading' => esc_html__( 'Font Family', 'total-theme-core' ),
					'param_name' => 'heading_font_family',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-family' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'param_name' => 'heading_font_weight',
					'css' => [ 'selector' => '.vcex-toggle__heading', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight: Active', 'total-theme-core' ),
					'param_name' => 'heading_font_weight_active',
					'css' => [ 'selector' => '{{WRAPPER}}.vcex-toggle--active .vcex-toggle__heading', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
				],
				[
					'type' => 'typography',
					'heading' => esc_html__( 'Typography', 'total-theme-core' ),
					'param_name' => 'heading_typo',
					'selector' => '.vcex-toggle__heading',
					'group' => esc_html__( 'Heading', 'total-theme-core' ),
					'editors' => [ 'elementor' ],
				],
				// Content
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'content_color',
					'css' => [ 'selector' => '.vcex-toggle__content', 'property' => 'color' ],
					'group' => esc_html__( 'Content', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'content_font_size',
					'css' => [ 'selector' => '.vcex-toggle__content', 'property' => 'font-size' ],
					'group' => esc_html__( 'Content', 'total-theme-core' ),
				],
				[
					'type'  => 'vcex_font_family_select',
					'heading' => esc_html__( 'Font Family', 'total-theme-core' ),
					'param_name' => 'content_font_family',
					'css' => [ 'selector' => '.vcex-toggle__content', 'property' => 'font-family' ],
					'group' => esc_html__( 'Content', 'total-theme-core' ),
				],
				[
					'type' => 'typography',
					'heading' => esc_html__( 'Typography', 'total-theme-core' ),
					'param_name' => 'content_typo',
					'selector' => '.vcex-toggle__content',
					'group' => esc_html__( 'Content', 'total-theme-core' ),
					'editors' => [ 'elementor' ],
				],
				// Hidden
				[ 'type' => 'hidden', 'param_name' => 'parse_content', 'std' => 'true' ]
			];
		}

	}

}

new VCEX_Toggle_Shortcode;

if ( class_exists( 'WPBakeryShortCode' ) && ! class_exists( 'WPBakeryShortCode_Vcex_Toggle' ) ) {
	class WPBakeryShortCode_Vcex_Toggle extends WPBakeryShortCode {
		protected function outputTitle( $title ) {
			$icon = $this->settings( 'icon' );
			return '<h4 class="wpb_element_title"><i class="vc_general vc_element-icon' . ( ! empty( $icon ) ? ' ' . $icon : '' ) . '" aria-hidden="true"></i><span class="vcex-heading-text">' . esc_html__( 'Toggle (FAQ)', 'total-theme-core' ) . '<span></span></span></h4>';
		}
	}
}
