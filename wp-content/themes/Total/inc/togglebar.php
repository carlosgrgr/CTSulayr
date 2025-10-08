<?php

namespace TotalTheme;

defined( 'ABSPATH' ) || exit;

/**
 * ToggleBar Class.
 */
class ToggleBar {

	/**
	 * Is enabled or not.
	 */
	protected static $is_enabled;

	/**
	 * Current state.
	 */
	protected static $state;

	/**
	 * Check if state is persistant.
	 */
	protected static $is_state_persistent;

	/**
	 * Local storage key.
	 */
	protected static $localstorage_key;

	/**
	 * Template ID.
	 */
	protected static $template_id;

	/**
	 * Content.
	 */
	protected static $content;

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Return template ID.
	 */
	public static function get_template_id() {
		if ( null === self::$template_id ) {
			$template_id = get_theme_mod( 'toggle_bar_page' );
			$template_id = (int) apply_filters( 'wpex_toggle_bar_content_id', $template_id ); // @deprecated 6.4
			if ( $template_id ) {
				$template_id = wpex_parse_obj_id( $template_id );
			}
			self::$template_id = $template_id;
		}
		return self::$template_id;
	}

	/**
	 * Get togglebar style.
	 */
	public static function style(): string {
		$style = ( $style = get_theme_mod( 'toggle_bar_display' ) ) ? sanitize_text_field( $style ) : 'overlay';
		if ( ! in_array( $style, [ 'overlay', 'inline' ], true ) ) {
			$style = 'overlay';
		}
		$style = apply_filters( 'wpex_togglebar_style', $style );
		return (string) apply_filters( 'totaltheme/togglebar/style', $style );
	}

	/**
	 * Checks if the togglebar is enabled or not.
	 */
	public static function is_enabled(): bool {
		if ( null === self::$is_enabled ) {
			if ( ! self::get_content() && ! totaltheme_call_static( 'Integration\Elementor', 'location_exists', 'togglebar' ) ) {
				$check = false;
			} else {
				$check   = wp_validate_boolean( get_theme_mod( 'toggle_bar', true ) );
				$post_id = wpex_get_current_post_id();
				if ( $post_id ) {
					$meta = get_post_meta( $post_id, 'wpex_disable_toggle_bar', true );
					if ( 'enable' === $meta ) {
						$check = true;
					} elseif ( 'on' === $meta ) {
						$check = false;
					}
				}
				$check = apply_filters( 'wpex_toggle_bar_active', $check ); // @deprecated 5.1.3
				$check = apply_filters( 'wpex_has_togglebar', $check ); // @deprecated 5.1.3
			}
			self::$is_enabled = (bool) apply_filters( 'totaltheme/togglebar/is_enabled', $check );
		}
		return self::$is_enabled;
	}

	/**
	 * Check if the togglebar is dismissible.
	 */
	public static function is_dismissible(): bool {
		return 'visible' === self::get_state() && get_theme_mod( 'toggle_bar_enable_dismiss', false );
	}

	/**
	 * Check if the toggle bar state is persistent.
	 */
	public static function is_state_persistent(): bool {
		if ( null === self::$is_state_persistent ) {
			self::$is_state_persistent = wp_validate_boolean( get_theme_mod( 'toggle_bar_remember_state', false ) );
		}
		return self::$is_state_persistent;
	}

	/**
	 * Returns the default state.
	 */
	public static function get_state(): string {
		if ( null === self::$state ) {
			$state = get_theme_mod( 'toggle_bar_default_state', 'hidden' );
			$state = (string) apply_filters( 'wpex_togglebar_state', $state );
			switch ( $state ) {
				case 'open':
					$state = 'visible';
					break;
				case 'closed':
					$state = 'hidden';
					break;
			}
			self::$state = $state;
		}
		return self::$state;
	}

	/**
	 * Check if currently open.
	 */
	public static function is_open(): bool {
		return 'visible' === self::get_state();
	}

	/**
	 * Returns the togglebar content.
	 */
	public static function get_content(): string {
		if ( null !== self::$content ) {
			return self::$content;
		}
		$content = '';
		if ( $post_id = wpex_get_current_post_id() ) {
			$content = get_post_meta( $post_id, 'wpex_togglebar_content', true );
		}
		if ( ! $content ) {
			if ( $togglebar_id = self::get_template_id() ) {
				$content = totaltheme_shortcode_unautop( get_post_field( 'post_content', $togglebar_id ) );
			}
			if ( ! $content ) {
				$content = get_theme_mod( 'toggle_bar_content' );
			}
		}
		$content = apply_filters( 'wpex_togglebar_content', $content ); // @deprecated 6.4
		self::$content = (string) apply_filters( 'totaltheme/togglebar/content', $content );
		return self::$content;
	}

	/**
	 * Returns the visibility class.
	 */
	public static function get_visibility_class() {
		$visibility = ( $visibility = get_theme_mod( 'toggle_bar_visibility' ) ) ? sanitize_text_field( $visibility ) : '';
		$visibility = (string) apply_filters( 'wpex_togglebar_visibility', $visibility ); // @deprecated 6.4
		if ( $visibility ) {
			return totaltheme_get_visibility_class( $visibility );
		}
	}

	/**
	 * Wrapper classes.
	 */
	public static function get_wrapper_classes() {
		$style       = self::style();
		$state       = self::get_state();
		$is_builder  = totaltheme_call_static( 'Integration\Elementor', 'location_exists', 'togglebar' );
		$animation   = get_theme_mod( 'toggle_bar_animation', 'fade' );
		$padding_y   = get_theme_mod( 'toggle_bar_padding_y' );
		$dismissable = false;

		if ( get_theme_mod( 'toggle_bar_enable_dismiss', false ) && 'visible' === get_theme_mod( 'toggle_bar_default_state', 'hidden' ) ) {
			$dismissable = true;
		}

		if ( $padding_y && '0px' !== $padding_y ) {
			$padding_y = absint( $padding_y );
		}

		/*** Add theme classes ***/
		$classes = [];

		$classes[] = sanitize_html_class( "toggle-bar-{$style}" );

		if ( 'overlay' === $style && $animation ) {
			$classes[] = sanitize_html_class( "toggle-bar-{$animation}" );
		}

		if ( 'visible' === $state ) {
			$classes[] = 'active-bar';
		} elseif( ! self::is_state_persistent() ) {
			$classes[] = 'close-on-doc-click';
		}

		if ( $visibility_class = totaltheme_call_static( 'ToggleBar', 'get_visibility_class' ) ) {
			$classes[] = $visibility_class;
		}

		/*** Add utility classes ***/

		// Default
		$classes[] = 'wpex-invisible';
		$classes[] = 'wpex-opacity-0';
		$classes[] = 'wpex-w-100';
		$classes[] = 'wpex-surface-1';

		// Style specific classes
		switch ( $style ) {
			case 'overlay':
				$classes[] = 'wpex-fixed';
			//	$classes[] = 'wpex-top-0'; // we use CSS to offset things like admin bar and site frame
				$classes[] = 'wpex-inset-x-0';
				$classes[] = 'wpex-z-overlay';
				$classes[] = 'wpex-max-h-100';
				$classes[] = 'wpex-overflow-auto';
				$classes[] = 'wpex-shadow';
				if ( ! $padding_y ) {
					$padding_y = '40';
				}
				break;
			case 'inline':
				if ( $dismissable ) {
					$classes[] = 'wpex-relative';
				}
				$classes[] = 'wpex-hidden';
				$classes[] = 'wpex-border-b';
				$classes[] = 'wpex-border-solid';
				$classes[] = 'wpex-border-main';
				if ( ! $padding_y ) {
					$padding_y = '20';
				}
				break;
		}

		// Add vertical padding
		if ( ! $is_builder && $padding_y && '0px' !== $padding_y ) {
			$classes[] = 'wpex-py-' . sanitize_html_class( $padding_y );
		}

		// Add animation classes
		if ( 'overlay' === $style && $animation ) {
			$classes[] = 'wpex-transition-all';
			$classes[] = 'wpex-duration-300';
			if ( 'fade-slide' === $animation ) {
				$classes[] = '-wpex-translate-y-50';
			}
		}

		$classes = apply_filters( 'wpex_togglebar_classes', $classes ); // @deprecated 6.4

		return (array) apply_filters( 'totaltheme/togglebar/wrapper_class', $classes );
	}

	/**
	 * Wrapper class.
	 */
	public static function wrapper_class(): void {
		if ( $classes = self::get_wrapper_classes() ) {
			echo 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
	}

	/**
	 * Wrapper data attributes.
	 */
	public static function wrapper_data(): void {
		$attrs = [];
		$attrs['data-state'] = self::get_state();
		if ( self::is_state_persistent() ) {
			$attrs['data-remember-state'] = 'true';
			$attrs['data-localstorage-key'] = self::get_localstorage_key();
		}
		if ( get_theme_mod( 'toggle_bar_enable_dismiss', false ) && 'visible' === get_theme_mod( 'toggle_bar_default_state', 'hidden' ) ) {
			$attrs['data-allow-toggle'] = 'false';
		} else {
			$attrs['data-allow-toggle'] = 'true';
		}
		$attrs = apply_filters( 'wpex_togglebar_data_attributes', $attrs ); // @deprecated 6.4
		foreach ( $attrs as $k => $v ) {
			echo ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}
	}

	/**
	 * Output inner class.
	 */
	public static function inner_class(): void {
		$classes = [
			'wpex-flex',
			'wpex-flex-col',
			'wpex-justify-center',
		];

		$align = get_theme_mod( 'toggle_bar_align' );

		if ( $align && in_array( $align, [ 'start', 'center', 'end' ], true ) ) {
			$classes[] = "wpex-items-{$align}";
		}

		if ( get_theme_mod( 'toggle_bar_fullwidth' ) ) {
			$classes[] = 'wpex-px-30';
		} else {
			$classes[] = 'container';
		}

		$classes = apply_filters( 'wpex_togglebar_inner_class', $classes ); // @deprecated
		$classes = (array) apply_filters( 'totaltheme/togglebar/inner_class', $classes );

		if ( $classes ) {
			echo 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
	}

	/**
	 * Returns the local storage key name.
	 */
	protected static function get_localstorage_key(): string {
		if ( null !== self::$localstorage_key ) {
			return self::$localstorage_key;
		}
		self::$localstorage_key = 'wpex_togglebar_state';
		if ( 'visible' === self::get_state() ) {
			$content = self::get_content();
			if ( $content ) {
				self::$localstorage_key = 'wpex_togglebar_state_' . md5( wp_strip_all_tags( $content ) );
			}
		}
		return self::$localstorage_key;
	}

	/**
	 * Returns the inline JS for the header.
	 */
	public static function get_inline_js(): string {
		$local_storage_key_safe = sanitize_text_field( self::get_localstorage_key() );
		$is_dismissible = self::is_dismissible() ? 'true' : 'false';
		return "(function(){const e=document.documentElement;if(typeof localStorage!=='undefined'){const t=localStorage.getItem('{$local_storage_key_safe}'),n={$is_dismissible};if(t==='visible'){e.classList.add('togglebar-is-open-temp')}else if(t==='hidden'){n?e.classList.add('togglebar-is-dismissed-temp'):e.classList.add('togglebar-is-closed-temp')}}const o=document.querySelector('#wpex-toggle-bar-inline-js-after');o&&o.remove()})();";
	}

}
