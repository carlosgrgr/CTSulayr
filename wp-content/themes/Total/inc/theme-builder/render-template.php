<?php

namespace TotalTheme\Theme_Builder;

\defined( 'ABSPATH' ) || exit;

/**
 * Renders a theme builder template.
 */
if ( \class_exists( 'TotalTheme\Theme_Builder' ) ) {
	class Render_Template extends \TotalTheme\Theme_Builder {

		/**
		 * Template to render.
		 */
		protected $template;

		/**
		 * Location where template is being displayed.
		 */
		protected $location;

		/**
		 * Render the template.
		 */
		public function __construct( $template, $location ) {
			$this->template = $template;
			$this->location = $location;
		}

		/**
		 * Render the template.
		 */
		public function render(): bool {
			if ( empty( $this->template ) ) {
				return false;
			}

			if ( 'single' === $this->location && \post_password_required() ) {
				\the_content();
				return true;
			}

			$template_content = (string) $this->get_template_content();

			if ( empty( $template_content ) ) {
				if ( $this->template && \is_numeric( $this->template ) && \is_customize_preview() ) {
					?>
					<div class="wpex-alert wpex-alert-warning">
						<div class="wpex-alert__heading"><?php \esc_html_e( 'Template Notice', 'total' ); ?></div>
						<?php \esc_html_e( 'Your selected template is empty so your live site will fallback to the default template. Please go to Theme Panel > Dynamic Templates to customize your template.', 'total' ); ?>
					</div>
					<?php
					return false;
				} else {
					return false;
				}
			}

			$this->before_template();

			if ( 'single' === $this->location ) {
				$parse_content = ( 'elementor' !== self::$current_template_builder ); // already parsed!
				/**
				 * Loop is very important, otherwise frontend editor won't work. And we must
				 * check to ensure we aren't already in the loop to prevent infinite loop.
				 */
				if ( ! \in_the_loop() ) {
					while ( \have_posts() ) : \the_post();
						\totaltheme_call_static( 'Theme_Builder\Post_Template', 'render_template', $template_content, $parse_content );
					endwhile;
				} else {
					\totaltheme_call_static( 'Theme_Builder\Post_Template', 'render_template', $template_content, $parse_content );
				}
			} else {
				if ( 'elementor' === self::$current_template_builder ) {
					echo $template_content; // already parsed!
				} else {
					echo \totaltheme_render_content( $template_content );
				}
			}

			$this->after_template();

			return true;
		}

		/**
		 * Returns the template content.
		 */
		protected function get_template_content() {
			$content = '';

			if ( $translated_id = \wpex_parse_obj_id( $this->template, 'page' ) ) {
				$post = \get_post( $translated_id );
			}

			$post = $post ?? \get_post( $this->template );

			if ( $post && 'publish' === \get_post_status( $post ) && ! empty( $post->post_content ) ) {
				$content = $post->post_content;
			}

			self::$current_template_builder = \totaltheme_get_post_builder_type( $this->template, $content );

			if ( 'elementor' === self::$current_template_builder ) {
				$content = \wpex_get_elementor_content_for_display( $this->template );
			}

			return $content;
		}

		/**
		 * Before template content.
		 */
		protected function before_template() {
			if ( 'wpbakery' === self::$current_template_builder ) {

				// Page JS.
				$page_js = (string) get_post_meta( $this->template, '_wpb_post_custom_js_header', true );
				$page_js .= (string) get_post_meta( $this->template, '_wpb_post_custom_js_footer', true );

				if ( $page_js && $page_js_parsed = trim( wp_unslash( $page_js ) ) ) {
					echo '<script data-type="vc_custom-js">' . $page_js_parsed . '</script>';
				}

				// Page CSS.
				if ( \function_exists( '\vc_modules_manager' ) && \vc_modules_manager()->is_module_on( 'vc-custom-css' ) ) {
					\vc_modules_manager()->get_module( 'vc-custom-css' )->output_custom_css_to_page( $this->template );
				}

				// Shortcode CSS.
				\totaltheme_call_non_static( 'Integration\WPBakery\Shortcode_Inline_Style', 'render_style', $this->template, true );
			}
		}

		/**
		 * After template content.
		 */
		protected function after_template() {
			// Nothing yet.
		}

	}
}
