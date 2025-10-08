<?php

namespace TotalTheme\Footer;

defined( 'ABSPATH' ) || exit;

/**
 * Footer.
 */
class Core {

	/**
	 * Footer is enabled or not.
	 */
	protected static $is_enabled;

	/**
	 * Footer is using classic layout or not.
	 */
	protected static $has_classic_layout;

	/**
	 * Footer Template ID.
	 */
	protected static $template_id;

	/**
	 * Footer is custom, aka using the footer builder.
	 */
	protected static $is_custom;

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Checks if the footer is enabled or not.
	 */
	public static function is_enabled(): bool {
		if ( null !== self::$is_enabled ) {
			return self::$is_enabled;
		}
		$check = true;
		if ( is_page_template( [ 'templates/landing-page.php', 'templates/blank.php' ] ) ) {
			$check = false;
		}
		$post_id = wpex_get_current_post_id();
		if ( $post_id && $meta = get_post_meta( $post_id, 'wpex_disable_footer', true ) ) {
			if ( 'on' === $meta ) {
				$check = false;
			} elseif ( 'enable' === $meta ) {
				$check = true;
			}
		}
		$check = apply_filters( 'wpex_display_footer', $check ); // @deprecated
		self::$is_enabled = (bool) apply_filters( 'totaltheme/footer/is_enabled', $check );
		return self::$is_enabled;
	}

	/**
	 * Classic layout check.
	 */
	public static function has_classic_layout(): bool {
		if ( null === self::$has_classic_layout ) {
			self::$has_classic_layout = wp_validate_boolean( get_theme_mod( 'classic_footer_enable', false ) );
		}
		return self::$has_classic_layout;
	}

	/**
	 * Check if currently editing the footer.
	 */
	public static function is_edit_mode( $editor = 'any' ): bool {
		if ( ! self::is_custom() ) {
			return false;
		}
		switch ( $editor ) {
			case 'wpbakery':
				$editor_check = totaltheme_is_wpb_frontend_editor();
				break;
			case 'elementor':
				$editor_check = totaltheme_is_elementor_editor();
				break;
			default:
				$editor_check = totaltheme_is_wpb_frontend_editor() || totaltheme_is_elementor_editor();
				break;
		}
		return $editor_check && self::get_template_id() === wpex_get_current_post_id();
	}

	/**
	 * Checks if the footer is custom or not.
	 */
	public static function is_custom(): bool {
		if ( null === self::$is_custom ) {
			self::$is_custom = (bool) self::get_template_id();
		}
		return self::$is_custom;
	}

	/**
	 * Return template ID.
	 */
	public static function get_template_id(): int {
		if ( null === self::$template_id ) {
			self::$template_id = 0;
			if ( get_theme_mod( 'footer_builder_enable', true ) ) {
				$id = get_theme_mod( 'footer_builder_page_id' );
				if ( $id && 'publish' === get_post_status( $id ) ) {
					self::$template_id = $id;
				}
			}
		}
		if ( self::$template_id ) {
			self::$template_id = (int) apply_filters( 'wpex_footer_builder_page_id', self::$template_id );
		}
		return self::$template_id;
	}

	/**
	 * Checks if the footer has reveal on scroll or not.
	 */
	public static function has_reveal(): bool {
		if ( ! self::is_enabled()
			|| 'boxed' === wpex_site_layout()
			|| 'six' === totaltheme_call_static( 'Footer\Core', 'style' )
			|| totaltheme_is_wpb_frontend_editor()
		) {
			return false;
		}
		$check   = get_theme_mod( 'footer_reveal', false );
		$post_id = wpex_get_current_post_id();
		if ( $post_id && $meta = get_post_meta( $post_id, 'wpex_footer_reveal', true ) ) {
			if ( 'on' === $meta ) {
				$check = true;
			} elseif ( 'off' === $meta ) {
				$check = false;
			}
		}
		$check = apply_filters( 'wpex_has_footer_reveal', $check ); // @deprecated
		return (bool) apply_filters( 'totaltheme/footer/has_reveal', $check );
	}

	/**
	 * Returns wrapper classes.
	 */
	public static function get_wrapper_classes(): array {
		if ( self::has_classic_layout() ) {
			$class = array_merge( [ 'site-footer' ], self::get_inner_classes() );
		} else {
			$class = [];
		}
		$class[] = 'wpex-print-hidden';
		$class = apply_filters( 'wpex_footer_class', $class ); // @deprecated
		return (array) apply_filters( 'totaltheme/footer/wrapper_class', $class );
	}

	/**
	 * Returns the wrapper class.
	 */
	public static function get_wrapper_class(): string {
		$class = '';
		if ( $classes = self::get_wrapper_classes() ) {
			$class = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		return $class;
	}

	/**
	 * Echo the wrapper class.
	 */
	public static function wrapper_class(): void {
		echo self::get_wrapper_class();
	}

	/**
	 * Returns inner classes.
	 *
	 * @note - In classic layout this function is called inside get_wrapper_classes!
	 */
	public static function get_inner_classes(): array {
		$class = [];
		if ( get_theme_mod( 'footer_dark_surface', true ) ) {
			$class[] = 'wpex-surface-dark';
		}
		$class[] = 'wpex-link-decoration-vars-none';
		if ( get_theme_mod( 'footer_bg_img' )
			&& $bg_style = (string) get_theme_mod( 'footer_bg_img_style' )
		) {
			$class[] = 'bg-' . sanitize_html_class( $bg_style ); // @deprecated
			$class[] = wpex_parse_background_style_class( $bg_style );
		}
		return $class;
	}

	/**
	 * Returns the inner class.
	 */
	public static function get_inner_class(): string {
		$class = '';
		if ( $classes = self::get_inner_classes() ) {
			$class = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		return $class;
	}

}
