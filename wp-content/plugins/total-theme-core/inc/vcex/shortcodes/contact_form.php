<?php

defined( 'ABSPATH' ) || exit;

/**
 * Contact Form Shortcode.
 */
if ( ! class_exists( 'VCEX_Contact_Form_Shortcode' ) ) {
	class VCEX_Contact_Form_Shortcode extends TotalThemeCore\Vcex\Shortcode_Abstract {

		/**
		 * Shortcode tag.
		 */
		public const TAG = 'vcex_contact_form';

		/**
		 * Main constructor.
		 */
		public function __construct() {
			$this->scripts = $this->scripts_to_register();
			add_action( 'wp_ajax_vcex_contact_form_action', [ self::class, 'form_submission' ] );
			add_action( 'wp_ajax_nopriv_vcex_contact_form_action', [ self::class, 'form_submission' ] );
			parent::__construct();
		}

		/**
		 * Shortcode title.
		 */
		public static function get_title(): string {
			return esc_html__( 'Contact Form', 'total-theme-core' );
		}

		/**
		 * Shortcode description.
		 */
		public static function get_description(): string {
			return esc_html__( 'Simple contact form', 'total-theme-core' );
		}

		/**
		 * Register form scripts.
		 */
		public function scripts_to_register(): array {
			$scripts = [
				[
					'vcex-contact-form',
					vcex_get_js_file( 'frontend/contact-form' ),
					[],
					'1.0',
					true
				],
			];
			if ( function_exists( 'wpex_get_recaptcha_keys' ) && $site_key = wpex_get_recaptcha_keys( 'site' ) ) {
				$site_key_safe = sanitize_text_field( $site_key );
				if ( $site_key_safe ) {
					$scripts[] = [
						'recaptcha',
						"https://www.google.com/recaptcha/api.js?render={$site_key_safe}",
						[],
						null,
						[ 'strategy' => 'defer' ]
					];
				}
			}
			return $scripts;
		}

		/**
		 * Enqueue form scripts.
		 */
		protected static function enqueue_scripts( array $atts ): void {
			wp_enqueue_script( 'vcex-contact-form' );
			if ( vcex_validate_att_boolean( 'enable_recaptcha', $atts, true ) ) {
				wp_enqueue_script( 'recaptcha' );
			}
		}

		/**
		 * Array of shortcode parameters.
		 */
		public static function get_params_list(): array {
			return [
				// General
				[
					'type' => 'vcex_notice',
					'param_name' => 'editor_notice',
					'text' => esc_html__( 'Forms will be sent to the "Administration Email Address" as defined under Settings > General or via your custom email address defined in the Theme Options Panel.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Privacy Checkbox', 'total-theme-core' ),
					'param_name' => 'enable_privacy_check',
					'std' => 'true',
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'reCAPTCHA', 'total-theme-core' ),
					'param_name' => 'enable_recaptcha',
					'std' => 'true',
					'description' => sprintf( esc_html__( 'Enable Google reCAPTCHA to help prevent spam submissions. You will need to generate your site and secret keys %shere%s (use the v3 API) then enter these keys in the %sTheme Panel%s.', 'total-theme-core' ), '<a href="https://www.google.com/recaptcha/admin/create" target="_blank" rel="nofollow noopener noreferrer">', '&#8599;</a>', '<a href="' . esc_url( admin_url( '?page=wpex-panel' ) ) . '" target="_blank" rel="nofollow noopener noreferrer">', '&#8599;</a>' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Hide reCAPTCHA Badge', 'total-theme-core' ),
					'param_name' => 'enable_recaptcha_notice',
					'std' => 'false',
					'description' => sprintf( esc_html__( 'According to the %sreCAPTCHA guidelines%s, if you wish to hide the default reCAPTCHA badge you must include the reCAPTCHA branding visibly in the user flow. Enable this setting to display the reCAPTCHA branding after your form and hide the default badge.', 'total-theme-core' ), '<a href="https://developers.google.com/recaptcha/docs/faq#id-like-to-hide-the-recaptcha-badge.-what-is-allowed" target="_blank" rel="nofollow noopener noreferrer">', '&#8599;</a>' ),
					'dependency' => [ 'element' => 'enable_recaptcha', 'value' => 'true' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Custom Subject', 'total-theme-core' ),
					'param_name' => 'email_subject',
					'description' => esc_html__( 'Override the default email subject that shows up when you receive a new message.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Extra class name', 'total-theme-core' ),
					'description' => self::param_description( 'el_class' ),
					'param_name' => 'el_class',
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				vcex_vc_map_add_css_animation(),
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Duration', 'total-theme-core'),
					'param_name' => 'animation_duration',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Animation Delay', 'total-theme-core'),
					'param_name' => 'animation_delay',
					'css' => true,
					'description' => esc_html__( 'Enter your custom time in seconds (decimals allowed).', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				// Style
				[
					'type' => 'vcex_select',
					'choices' => 'form_style',
					'heading' => esc_html__( 'Style', 'total-theme-core' ),
					'param_name' => 'style',
					'description' => esc_html__( 'Select a preset form style or go to Appearance > Customize > General Theme Options > Forms where you can customize the design of all forms.', 'total-theme-core' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Bottom Margin', 'total-theme-core' ),
					'param_name' => 'bottom_margin',
					'admin_label' => true,
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Max Width', 'total-theme-core' ),
					'param_name' => 'width',
					'css' => [ 'property' => 'max-width' ],
					'description' => self::param_description( 'width' ),
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'heading' => esc_html__( 'Shadow', 'total-theme-core' ),
					'param_name' => 'shadow',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'font_size',
					'css' => true,
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'vcex_text_align',
					'heading' => esc_html__( 'Text Align', 'total-theme-core' ),
					'param_name' => 'text_align',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'margin',
					'heading' => esc_html__( 'Item Spacing', 'total-theme-core' ),
					'param_name' => 'items_spacing',
					'group' => esc_html__( 'Style', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Labels
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Placeholders', 'total-theme-core' ),
					'param_name' => 'enable_placeholders',
					'std' => 'false',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Name Label', 'total-theme-core' ),
					'param_name' => 'label_name',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Email Label', 'total-theme-core' ),
					'param_name' => 'label_email',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Message Label', 'total-theme-core' ),
					'param_name' => 'label_message',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Privacy Policy Label', 'total-theme-core' ),
					'param_name' => 'label_privacy',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'dependency' => [ 'element' => 'enable_privacy_check', 'value' => 'true' ],
					'description' => esc_html__( 'To create a link to your privacy page simply add double curly brackets around the text you want to link, example: {{Privacy Page}} and the element will automatically link to your privacy page as defined in the WordPress Settings > Privacy tab.', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'margin',
					'heading' => esc_html__( 'Bottom Margin', 'total-theme-core' ),
					'param_name' => 'labels_bottom_margin',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'dependency' => [ 'element' => 'enable_placeholders', 'value_not_equal_to' => 'true' ],
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'labels_color',
					'dependency' => [ 'element' => 'enable_placeholders', 'value_not_equal_to' => 'true' ],
					'css' => [ 'selector' => '.vcex-contact-form__label', 'property' => 'color' ],
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'labels_font_size',
					'css' => [ 'selector' => '.vcex-contact-form__label', 'property' => 'font-size' ],
					'dependency' => [ 'element' => 'enable_placeholders', 'value_not_equal_to' => 'true' ],
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'param_name' => 'labels_font_weight',
					'dependency' => [ 'element' => 'enable_placeholders', 'value_not_equal_to' => 'true' ],
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Required Label', 'total-theme-core' ),
					'param_name' => 'enable_required_label',
					'std' => 'true',
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text',
					'placeholder' => '*',
					'heading' => esc_html__( 'Custom Required Label', 'total-theme-core' ),
					'param_name' => 'label_required',
					'dependency' => [ 'element' => 'enable_required_label', 'value' => 'true' ],
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Required Label Color', 'total-theme-core' ),
					'param_name' => 'required_color',
					'css' => [ 'selector' => '.vcex-contact-form__required', 'property' => 'color' ],
					'dependency' => [ 'element' => 'enable_required_label', 'value' => 'true' ],
					'group' => esc_html__( 'Labels', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Inputs
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Stack Fields', 'total-theme-core' ),
					'param_name' => 'stack_fields',
					'std' => 'false',
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text',
					'placeholder' => '8',
					'heading' => esc_html__( 'Message Rows', 'total-theme-core' ),
					'param_name' => 'message_rows',
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Message Minlength', 'total-theme-core' ),
					'param_name' => 'message_minlength',
					'description' => esc_html__( 'Minimum length in characters for the message field.', 'total-theme-core' ),
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'textfield',
					'heading' => esc_html__( 'Message Maxlength', 'total-theme-core' ),
					'param_name' => 'message_maxlength',
					'description' => esc_html__( 'Maximum length in characters for the message field.', 'total-theme-core' ),
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'input_background',
					'css' => [ 'selector' => [ 'input', 'textarea' ], 'property' => 'background' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Focus', 'total-theme-core' ),
					'param_name' => 'input_focus_background',
					'css' => [ 'selector' => [ 'input:focus', 'textarea:focus' ], 'property' => 'background' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'input_color',
					'css' => [ 'selector' => [ 'input', 'textarea' ], 'property' => 'color' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'choices' => 'border_width',
					'heading' => esc_html__( 'Border Width', 'total-theme-core' ),
					'param_name' => 'input_border_width',
					'css' => [ 'selector' => [ 'input', 'textarea' ], 'property' => 'border-width' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Border Color', 'total-theme-core' ),
					'param_name' => 'input_border_color',
					'css' => [ 'selector' => [ 'input', 'textarea' ], 'property' => 'border-color' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Border Color: Focus', 'total-theme-core' ),
					'param_name' => 'input_focus_border_color',
					'css' => [ 'selector' => [ 'input:focus', 'textarea:focus' ], 'property' => 'border-color' ],
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'heading' => esc_html__( 'Border Radius', 'total-theme-core' ),
					'param_name' => 'input_border_radius',
					'css' => [ 'selector' => [ 'input', 'textarea' ], 'property' => 'border-radius' ],
					'choices' => 'border_radius',
					'group' => esc_html__( 'Inputs', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Button
				[
					'type' => 'vcex_ofswitch',
					'heading' => esc_html__( 'Full Width', 'total-theme-core' ),
					'param_name' => 'button_fullwidth',
					'std' => 'true',
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text',
					'placeholder' => '50px',
					'heading' => esc_html__( 'Minimum Height', 'total-theme-core' ),
					'param_name' => 'button_min_height',
					'css' => [ 'selector' => '.vcex-contact-form__submit--full', 'property' => 'min-height' ],
					'dependency' => [ 'element' => 'button_fullwidth', 'value' => 'true' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text',
					'heading' => esc_html__( 'Text', 'total-theme-core' ),
					'placeholder' => esc_html__( 'Submit', 'total-theme-core' ),
					'param_name' => 'button_text',
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'button_font_size',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'font-size' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'font_weight',
					'heading' => esc_html__( 'Font Weight', 'total-theme-core' ),
					'param_name' => 'button_font_weight',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'font-weight' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'typography',
					'heading' => esc_html__( 'Typography', 'total-theme-core' ),
					'param_name' => 'button_typo',
					'selector' => '.vcex-contact-form__submit',
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'elementor' ],
				],
				[
					'type' => 'vcex_preset_textfield',
					'heading' => esc_html__( 'Border Radius', 'total-theme-core' ),
					'param_name' => 'button_border_radius',
					'choices' => 'border_radius',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'border-radius' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_trbl',
					'heading' => esc_html__( 'Padding', 'total-theme-core' ),
					'param_name' => 'button_padding',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'padding' ],
					'description' => self::param_description( 'padding' ),
					'group' => esc_html__( 'Button', 'total-theme-core' ),
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'button_background',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'background' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'button_color',
					'css' => [ 'selector' => '.vcex-contact-form__submit', 'property' => 'color' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background: Hover', 'total-theme-core' ),
					'param_name' => 'button_hover_background',
					'css' => [ 'selector' => '.vcex-contact-form__submit:hover', 'property' => 'background' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color: Hover', 'total-theme-core' ),
					'param_name' => 'button_hover_color',
					'css' => [ 'selector' => '.vcex-contact-form__submit:hover', 'property' => 'color' ],
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_select',
					'choices' => 'svg_loader',
					'std' => '',
					'heading' => esc_html__( 'Loader Icon', 'total-theme-core' ),
					'param_name' => 'loader_icon',
					'group' => esc_html__( 'Button', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// Notices
				[
					'type' => 'vcex_text',
					'heading' => esc_html__( 'Success Notice', 'total-theme-core' ),
					'placeholder' => esc_html__( 'Thank you for the message. We will respond as soon as possible.', 'total-theme-core' ),
					'label_block' => true, // elementor param.
					'param_name' => 'notice_success',
					'group' => esc_html__( 'Notices', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_text',
					'heading' => esc_html__( 'Error Notice', 'total-theme-core' ),
					'placeholder' => esc_html__( 'The following error occurred: {{error}}', 'total-theme-core' ),
					'label_block' => true, // elementor param.
					'param_name' => 'notice_error',
					'group' => esc_html__( 'Notices', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_font_size',
					'heading' => esc_html__( 'Font Size', 'total-theme-core' ),
					'param_name' => 'notice_font_size',
					'css' => [ 'selector' => '.vcex-contact-form__notice', 'property' => 'font-size' ],
					'group' => esc_html__( 'Notices', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Color', 'total-theme-core' ),
					'param_name' => 'notice_color',
					'css' => [ 'selector' => '.vcex-contact-form__notice', 'property' => 'color' ],
					'group' => esc_html__( 'Notices', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				[
					'type' => 'vcex_colorpicker',
					'heading' => esc_html__( 'Background', 'total-theme-core' ),
					'param_name' => 'notice_background',
					'css' => [ 'selector' => '.vcex-contact-form__notice', 'property' => [ 'background', 'border-color' ] ],
					'group' => esc_html__( 'Notices', 'total-theme-core' ),
					'editors' => [ 'wpbakery', 'elementor' ],
				],
				// CSS
				[
					'type' => 'css_editor',
					'heading' => esc_html__( 'CSS box', 'total-theme-core' ),
					'param_name' => 'css',
					'group' => esc_html__( 'CSS', 'total-theme-core' ),
					'editors' => [ 'wpbakery' ],
				],
				// Elementor Fields.
				[
					'type' => 'typography',
					'heading' => esc_html__( 'Typography', 'total-theme-core' ),
					'param_name' => 'typography',
					'selector' => '.vcex-contact-form',
					'group' => esc_html__( 'Typography', 'total-theme-core' ),
					'editors' => [ 'elementor' ],
				],
			];
		}

		/**
		 * Submit form.
		 */
		public static function form_submission() {
			if ( ! check_ajax_referer( 'vcex-contact-form-nonce', 'nonce', false ) ) {
				wp_send_json_error( [ 'message' => esc_html__( 'Security check failed.', 'total-theme-core' ) ] );
			}

			$error        = '';
			$status       = 'error';
			$captcha_pass = false;

			// reCAPTCHA check
			if ( ! empty( $_POST['recaptcha'] ) && function_exists( 'wpex_get_recaptcha_keys' ) ) {
				$keys = wpex_get_recaptcha_keys();

				if ( empty( $keys['site'] ) || empty( $keys['secret'] ) ) {
					$captcha_pass = true; // no keys saved
				} else {
					$recaptcha = wp_remote_get( add_query_arg( [
						'response' => sanitize_text_field( wp_unslash( $_POST['recaptcha'] ) ),
						'secret'   => sanitize_text_field( $keys['secret'] ),
					], 'https://www.google.com/recaptcha/api/siteverify' ) );

					if ( empty( $recaptcha['body'] ) ) {
						wp_send_json_error( [
							'message' => esc_html__( 'Captcha verification failed.', 'total-theme-core' ),
							'debug'   => 'Empty response body from reCAPTCHA — possible misconfigured API keys.'
						] );
					} else {
						$recaptcha = json_decode( $recaptcha['body'], false );
						// This is a human
						if ( true == $recaptcha->success
							&& 0.5 <= $recaptcha->score
							&& 'vcex_contact_form' === $recaptcha->action
						) {
							$captcha_pass = true;
						}
						// Score less than 0.5 indicates suspicious activity. Return an error.
						else {
							wp_send_json_error( [
								'message' => esc_html__( 'Captcha verification failed.', 'total-theme-core' )
							] );
						}
					}
				}
			} else {
				$captcha_pass = true; // captcha is disabled
			}

			if ( ! $captcha_pass ) {
				wp_send_json_error( [
					'message' => esc_html__( 'Captcha verification failed.', 'total-theme-core' )
				] );
			}

			// Display error if required fields are empty
			if ( empty( $_POST['name'] ) || empty( $_POST['email'] ) || empty( $_POST['message'] ) ) {
				wp_send_json_error( [
					'message' => esc_html__( 'One or more required fields are empty.', 'total-theme-core' ) ]
				);
			}
			
			// Try and send the email
			else {
				$send_email = self::send_email( $_POST );
				if ( is_wp_error( $send_email ) ) {
					wp_send_json_error( [
						'message' => esc_html__( 'Failed to send the email. Please try again later.', 'total-theme-core' ),
						'debug'   => esc_html( $send_email->get_error_message() ),
					] );
				} elseif ( $send_email ) {
					wp_send_json_success();
				} else {
					wp_send_json_error( [
						'message' => esc_html__( 'Failed to send the email. Please try again later.', 'total-theme-core' ),
						'debug'   => 'wp_mail() error - if you are on a localhost wp_mail won\'t work - if not check with your webhost.',
					] );
				}
			}

			wp_send_json_error( [
				'message' => esc_html__( 'Failed to send the email. Please try again later.', 'total-theme-core' ),
				'debug'   => 'unknown error',
			] );
		}

		/**
		 * Send the email.
		 */
		protected static function send_email( $data ) {
			if ( true === apply_filters( 'vcex_contact_form_demo_mode', false ) ) {
				return true;
			}

			if ( empty( $data ) || ! is_array( $data ) ) {
				return false;
			}

			$mail_to = sanitize_email( self::email_to_address( $data ) );

			if ( ! $mail_to || ! is_email( $mail_to ) ) {
				return false;
			}

			$mail_headers = [
				'Content-Type: text/html; charset=UTF-8',
			];

			$mail_subject = ( ! empty( $data['subject'] ) && 'undefined' !== $data['subject'] )
				? sanitize_text_field( $data['subject'] )
				: sprintf( esc_html__( 'New contact form submission from %s', 'total-theme-core' ), get_bloginfo( 'name' ) );

			$mail_body = '';

			if ( ! empty( $data['name'] ) ) {
				$name_safe  = sanitize_text_field( $data['name'] );
				$label_name = ( ! empty( $data['label_name'] ) && 'undefined' !== $data['label_name'] )
					? sanitize_text_field( $data['label_name'] )
					: esc_html__( 'Your Name', 'total-theme-core' );
				$mail_body .= '<strong>' . esc_html( str_replace( ':', '', $label_name ) ) . '</strong>: ' . esc_html( $name_safe ) . '<br>';
			}

			if ( ! empty( $data['email'] ) ) {
				$sanitized_email = sanitize_email( $data['email'] );
				if ( $sanitized_email ) {
					$reply_to_safe  = ! empty( $name_safe )
						? sprintf( '%s <%s>', $name_safe, $sanitized_email )
						: $sanitized_email;
					$mail_headers[] = 'Reply-To: ' . $reply_to_safe;
				}
				$label_email = ( ! empty( $data['label_email'] ) && 'undefined' !== $data['label_email'] )
					? sanitize_text_field( $data['label_email'] )
					: esc_html__( 'Your Email', 'total-theme-core' );
				$mail_body .= '<strong>' . esc_html( str_replace( ':', '', $label_email ) ) . '</strong>: ' . esc_html( $sanitized_email ) . '<br>';
			}

			if ( ! empty( $data['message'] ) ) {
				$label_message = ( ! empty( $data['label_message'] ) && 'undefined' !== $data['label_message'] )
					? sanitize_text_field( $data['label_message'] )
					: esc_html__( 'Message', 'total-theme-core' );
				$mail_body .= '<strong>' . esc_html( str_replace( ':', '', $label_message ) ) . '</strong>:<br />' . wpautop( wp_kses_post( stripslashes( $data['message'] ) ) );
			}

			$mail_subject = (string) apply_filters( 'vcex_contact_form_mail_subject', $mail_subject, $data );
			$mail_body    = (string) apply_filters( 'vcex_contact_form_mail_body', $mail_body, $data );
			$mail_headers = apply_filters( 'vcex_contact_form_mail_headers', $mail_headers, $data );

			if ( $mail_body ) {
				return wp_mail( $mail_to, $mail_subject, $mail_body, $mail_headers );
			}

			return false;
		}

		/**
		 * Get the to email for the contact form.
		 */
		protected static function email_to_address( $data ): string {
			if ( ! empty( $data['post_id'] ) && is_numeric( $data['post_id'] ) ) {
				$email_address_meta_key = (string) get_theme_mod( 'contact_form_mail_to_address_meta_key' );
				if ( $email_address_meta_key ) {
					$meta_email_address = get_post_meta( absint( $data['post_id'] ), $email_address_meta_key, true );
					if ( $meta_email_address && is_email( $meta_email_address ) ) {
						$email_address = $meta_email_address;
					}
				}
			}

			if ( empty( $email_address ) ) {
				$custom_email_address = (string) get_theme_mod( 'contact_form_mail_to_address' );
				if ( $custom_email_address && is_email( $custom_email_address ) ) {
					$email_address = $custom_email_address;
				} else {
					$email_address = (string) get_bloginfo( 'admin_email' );
				}
			}

			$allowed_data = array_flip( [
				'post_id',
				'name',
				'subject',
				'email',
			] );

			$data = array_intersect_key( $data, $allowed_data );

			return (string) apply_filters( 'vcex_contact_form_mail_to_address', $email_address, $data );
		}

	}
}

new VCEX_Contact_Form_Shortcode;

if ( class_exists( 'WPBakeryShortCode' ) && ! class_exists( 'WPBakeryShortCode_Vcex_Contact_Form' ) ) {
	class WPBakeryShortCode_Vcex_Contact_Form extends WPBakeryShortCode {}
}
