<?php

defined( 'ABSPATH' ) || exit;

/**
 * Post Terms Shortcode.
 */
if ( ! class_exists( 'VCEX_Post_Terms_Shortcode' ) ) {

	class VCEX_Post_Terms_Shortcode extends TotalThemeCore\Vcex\Shortcode_Abstract {

		/**
		 * Shortcode tag.
		 */
		public const TAG = 'vcex_post_terms';

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
			return esc_html__( 'Post Terms', 'total-theme-core' );
		}

		/**
		 * Shortcode description.
		 */
		public static function get_description(): string {
			return esc_html__( 'Display your post terms', 'total-theme-core' );
		}

		/**
		 * Array of shortcode parameters.
		 */
		public static function get_params_list(): array {
			return [
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Taxonomy', 'total-theme-core' ),
					'param_name' => 'taxonomy',
					'choices' => 'taxonomy',
					'admin_label' => true,
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'autocomplete',
					'heading' => esc_html__( 'Child Of', 'total-theme-core' ),
					'param_name' => 'child_of',
					'settings' => [
						'multiple' => false,
						'min_length' => 1,
						'groups' => true,
						'display_inline' => true,
					//	'delay' => 0,
						'auto_focus' => true,
					],
				],
				[
					'type' => 'autocomplete',
					'heading' => esc_html__( 'Exclude terms', 'total-theme-core' ),
					'param_name' => 'exclude_terms',
					'settings' => [
						'multiple' => true,
						'min_length' => 1,
						'groups' => true,
						'display_inline' => true,
					//	'delay' => 0,
						'auto_focus' => true,
					],
				],
				[
					'type' => 'vcex_ofswitch',
					'std' => 'false',
					'heading' => esc_html__( 'Display First or Primary Term Only', 'total-theme-core' ),
					'param_name' => 'first_term_only',
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Order', 'total-theme-core' ),
					'param_name' => 'order',
					'value' => [
						esc_html__( 'ASC', 'total-theme-core' ) => 'ASC',
						esc_html__( 'DESC', 'total-theme-core' ) => 'DESC',
					],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Order By', 'total-theme-core' ),
					'param_name' => 'orderby',
					'value' => [
						esc_html__( 'Name', 'total-theme-core' ) => 'name',
						esc_html__( 'Slug', 'total-theme-core' ) => 'slug',
						esc_html__( 'Term Group', 'total-theme-core' ) => 'term_group',
						esc_html__( 'Term ID', 'total-theme-core' ) => 'term_id',
						'ID' => 'id',
						esc_html__( 'Description', 'total-theme-core' ) => 'description',
					],
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
					'heading' => esc_html__( 'Element ID', 'total-theme-core' ),
					'param_name' => 'unique_id',
					'admin_label' => true,
					'description' => sprintf( esc_html__( 'Optional element ID (Note: make sure it is unique and valid according to %sw3c specification%s).', 'total-theme-core' ), '<a href="https://www.w3schools.com/tags/att_global_id.asp" target="_blank" rel="noopener noreferrer">', '</a>' ),
						'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'admin_label' => true,
					'heading' => esc_html__( 'Extra class name', 'total-theme-core' ),
					'param_name' => 'classes',
					'description' => self::param_description( 'el_class' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				vcex_vc_map_add_css_animation(),
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Duration', 'total-theme-core' ),
					'param_name' => 'animation_duration',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total-theme-core' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Delay', 'total-theme-core' ),
					'param_name' => 'animation_delay',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total-theme-core' ),
				],
				// Link
				[
					'type' => 'vcex_ofswitch',
					'std' => 'true',
					'heading' => esc_html__( 'Link to Archive?', 'total-theme-core' ),
					'param_name' => 'archive_link',
					'group' => esc_html__( 'Link', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select_buttons',
					'std' => 'self',
					'heading' => esc_html__( 'Link Target', 'total-theme-core' ),
					'param_name' => 'archive_link_target',
					'choices' => 'link_target',
					'group' => esc_html__( 'Link', 'total-theme-core' ),
					'dependency' => [ 'element' => 'archive_link', 'value' => 'true' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Style
				[
					'type' => 'vcex_select_buttons',
					'heading' => esc_html__( 'Style', 'total-theme-core' ),
					'param_name' => 'style',
					'std' => 'buttons',
					'choices' => [
						'buttons' => esc_html__( 'Buttons', 'total-theme-core' ),
						'inline'  => esc_html__( 'Inline List', 'total-theme-core' ),
						'ul'      => esc_html__( 'UL List', 'total-theme-core' ),
						'ol'      => esc_html__( 'OL List', 'total-theme-core' ),
					],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Custom Spacer', 'total-theme-core' ),
					'param_name' => 'spacer',
					'dependency' => [ 'element' => 'style', 'value' => 'inline' ],
					'description' => esc_html__( 'Enter a custom spacer to insert between items such as a comma.', 'total-theme-core' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Width', 'total-theme-core' ),
					'param_name' => 'max_width',
					'css' => [ 'property' => 'max-width' ],
					'description' => self::param_description( 'width' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text_align',
					'heading' => esc_html__( 'Aligment', 'total-theme-core' ),
					'param_name' => 'align',
					'std' => 'center',
					'dependency' => [ 'element' => 'max_width', 'not_empty' => true ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Bottom Margin', 'total-theme-core' ),
					'param_name' => 'bottom_margin',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'margin',
					'heading' => esc_html__( 'Spacing', 'total-theme-core' ),
					'param_name' => 'spacing',
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Button Styling
				[
					'type' => 'vcex_subheading',
					'text' => esc_html__( 'Buttons', 'total-theme-core' ),
					'param_name' => 'vcex_subheading__button-styles',
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text_align',
					'heading' => esc_html__( 'Alignment', 'total-theme-core' ),
					'param_name' => 'button_align',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_button_styles',
					'heading' => esc_html__( 'Style', 'total-theme-core' ),
					'param_name' => 'button_style',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_button_colors',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'button_color_style',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Size', 'total-theme-core' ),
					'param_name' => 'button_size',
					'std' => '',
					'value' => [
						esc_html__( 'Default', 'total-theme-core' ) => '',
						esc_html__( 'Small', 'total-theme-core' ) => 'small',
						esc_html__( 'Medium', 'total-theme-core' ) => 'medium',
						esc_html__( 'Large', 'total-theme-core' ) => 'large',
					],
					'description' => esc_html__( 'Select the default button font size. You can use the Typography tab if you want to define a custom size.', 'total-theme-core' ),
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'button_background',
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Hover', 'total-theme-core' ),
					'param_name' => 'button_hover_background',
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'button_color',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Hover', 'total-theme-core' ),
					'param_name' => 'button_hover_color',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'heading' => esc_html__( 'Border Radius', 'total-theme-core' ),
					'param_name' => 'button_border_radius',
					'css' => [ 'selector' => '.vcex-post-terms__item--btn', 'property' => 'border-radius' ],
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'choices' => 'border_radius',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_trbl',
					'heading' => esc_html__( 'Padding', 'total-theme-core' ),
					'param_name' => 'button_padding',
					'description' => self::param_description( 'padding' ),
					'css' => [ 'selector' => '.vcex-post-terms__item--btn', 'property' => 'padding' ],
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_trbl',
					'heading' => esc_html__( 'Margin (Deprecated, use Spacing instead)', 'total-theme-core' ),
					'param_name' => 'button_margin',
					'description' => self::param_description( 'margin' ),
					'css' => [ 'selector' => '.vcex-post-terms__item--btn', 'property' => 'margin' ],
					'dependency' => [ 'element' => 'style', 'value' => 'buttons' ],
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Before
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Before Text', 'total-theme-core' ),
					'param_name' => 'before_text',
					'dependency' => [ 'element' => 'style', 'value' => 'inline' ],
					'group' => esc_html__( 'Before', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'dependency' => [ 'element' => 'before_text', 'not_empty' => true ],
					'css' => [ 'selector' => '.vcex-post-terms__label', 'property' => 'font-weight' ],
					'param_name' => 'before_text_font_weight',
					'group' => esc_html__( 'Before', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'before_text_color',
					'css' => [ 'selector' => '.vcex-post-terms__label', 'property' => 'color' ],
					'dependency' => [ 'element' => 'before_text', 'not_empty' => true ],
					'editors' => [ 'wpbakery', 'elementor' ],
					'group' => esc_html__( 'Before', 'total-theme-core' ),
				],
				// Typography
				[
					'type' => 'vcex_font_family_select',
					'heading' => esc_html__( 'Font Family', 'total-theme-core' ),
					'param_name' => 'button_font_family',
					'css' => [ 'selector' => ':is(.vcex-post-terms__label,.vcex-post-terms__item)', 'property' => 'font-family' ],
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'button_font_size',
					'css' => [ 'selector' => ':is(.vcex-post-terms__label,.vcex-post-terms__item)', 'property' => 'font-size' ],
					'description' => self::param_description( 'font_size' ),
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_preset_textfield',
					'heading' => esc_html__( 'Letter Spacing', 'total-theme-core' ),
					'param_name' => 'button_letter_spacing',
					'choices' => 'letter_spacing',
					'css' => [ 'selector' => ':is(.vcex-post-terms__label,.vcex-post-terms__item)', 'property' => 'letter-spacing' ],
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Text Transform', 'total-theme-core' ),
					'param_name' => 'button_text_transform',
					'choices' => 'text_transform',
					'css' => [ 'selector' => ':is(.vcex-post-terms__label,.vcex-post-terms__item)', 'property' => 'text-transform' ],
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'param_name' => 'button_font_weight',
					'css' => [ 'selector' => ':is(.vcex-post-terms__label,.vcex-post-terms__item)', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Link Underline', 'total-theme-core' ),
					'param_name' => 'link_underline',
					'value' => [
						esc_html__( 'Default', 'total-theme-core' ) => '',
						esc_html__( 'Underline', 'total-theme-core' ) => 'true',
						esc_html__( 'No underline', 'total-theme-core' ) => 'false',
					],
					'description' => esc_html__( 'This setting applies only to standard links and not custom button styles.', 'total-theme-core' ),
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'dropdown',
					'heading' => esc_html__( 'Link Underline: Hover', 'total-theme-core' ),
					'param_name' => 'link_underline_hover',
					'value' => [
						esc_html__( 'Default', 'total-theme-core' ) => '',
						esc_html__( 'Underline', 'total-theme-core' ) => 'true',
						esc_html__( 'No underline', 'total-theme-core' ) => 'false',
					],
					'description' => esc_html__( 'This setting applies only to standard links and not custom button styles.', 'total-theme-core' ),
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'typography',
					'heading' => esc_html__( 'Typography', 'total-theme-core' ),
					'param_name' => 'typography',
					'selector' => '.vcex-post-terms',
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
					'editors' => [ 'elementor' ],
				],
				// Deprecated params
				[ 'type' => 'hidden', 'param_name' => 'target' ],
			];
		}

		/**
		 * Advanced CSS.
		 */
		protected static function css_pre_render( $css, $atts = [] ): void {

			// Must set colors here since term_color must be applied to each item using the utility classes
			$settings = [
				'button_color'            => [ '.vcex-post-terms__item', 'color' ],
				'button_hover_color'      => [ '.vcex-post-terms__item:hover', 'color' ],
				'button_background'       => [ '.vcex-post-terms__item--btn', 'background' ],
				'button_hover_background' => [ '.vcex-post-terms__item--btn:hover', 'background' ],
			];
			foreach ( $settings as $k => $v ) {
				if ( ! empty( $atts[ $k ] ) ) {
					if ( 'term_color' !== $atts[ $k ] ) {
						$args = [
							'selector' => $v[0],
							'property' => $v[1],
							'val'      => $atts[ $k ],
						];
						if ( 'button_hover_color' === $k || 'button_hover_background' === $k ) {
							$non_hover = str_replace( '_hover', '', $k );
							if ( isset( $atts[ $non_hover ] ) && 'term_color' === $atts[ $non_hover ] ) {
								$args['important'] = true; // must override the has-term-{id}-hover class
							}
						}
						$css->add_extra_css( $args );
					}
				}
			}

			// Link underlines - requires specific checks
			$has_underline       = ! empty( $atts['link_underline'] );
			$has_hover_underline = ! empty( $atts['link_underline_hover'] );

			if ( $has_underline || $has_hover_underline ) {
				$style = ! empty( $atts['style'] ) ? $atts['style'] : 'buttons';
				if ( 'buttons' !== $style || ( 'buttons' === $style && isset( $atts['button_style'] ) && 'plain-text' === $atts['button_style'] ) ) {
					if ( $has_underline ) {
						$css->add_extra_css( [
							'selector' => '{{WRAPPER}}',
							'property' => '--wpex-link-decoration-line',
							'val'      => vcex_validate_boolean( $atts['link_underline'] ) ? 'underline' : 'none',
						] );
					}
					if ( $has_hover_underline ) {
						$css->add_extra_css( [
							'selector' => '{{WRAPPER}}',
							'property' => '--wpex-hover-link-decoration-line',
							'val'      => vcex_validate_boolean( $atts['link_underline_hover'] ) ? 'underline' : 'none',
						] );
					}
				}
			}
		}

		/**
		 * Parse WPBakery attributes on edit.
		 */
		public static function vc_edit_form_fields_attributes( $atts = [] ) {
			return self::parse_deprecated_attributes( $atts );
		}

		/**
		 * Parses deprecated params.
		 */
		public static function parse_deprecated_attributes( $atts = [] ) {
			if ( empty( $atts['archive_link_target'] ) && ! empty( $atts['target'] ) ) {
				$atts['archive_link_target'] = $atts['target'];
				unset( $atts['target'] );
			}
			// @since 2.4 - updated to use flex container with justify instead of float this provides backwards RTL compat
			if ( ! empty( $atts['button_align'] ) && is_rtl() && in_array( $atts['button_align'], [ 'left', 'right' ], true ) ) {
				$atts['button_align'] = ( 'right' === $atts['button_align'] ) ? 'left' : 'right';
			}
			return $atts;
		}

		/**
		 * Register autocomplete hooks.
		 */
		public static function register_vc_autocomplete_hooks(): void {
			\add_filter(
				'vc_autocomplete_vcex_post_terms_exclude_terms_callback',
				'TotalThemeCore\WPBakery\Autocomplete\Taxonomy_Terms::callback'
			);
			\add_filter(
				'vc_autocomplete_vcex_post_terms_exclude_terms_render',
				'TotalThemeCore\WPBakery\Autocomplete\Taxonomy_Terms::render'
			);
			\add_filter(
				'vc_autocomplete_vcex_post_terms_child_of_callback',
				'TotalThemeCore\WPBakery\Autocomplete\Taxonomy_Terms::callback'
			);
			\add_filter(
				'vc_autocomplete_vcex_post_terms_child_of_render',
				'TotalThemeCore\WPBakery\Autocomplete\Taxonomy_Terms::render'
			);
		}

	}

}

new VCEX_Post_Terms_Shortcode;

if ( class_exists( 'WPBakeryShortCode' ) && ! class_exists( 'WPBakeryShortCode_Vcex_Post_Terms' ) ) {
	class WPBakeryShortCode_Vcex_Post_Terms extends WPBakeryShortCode {}
}
