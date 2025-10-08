<?php

namespace TotalTheme\Footer;

defined( 'ABSPATH' ) || exit;

/**
 * Footer Widgets.
 */
class Widgets {

	/**
	 * Are the footer widgets enabled or not.
	 */
	protected static $is_enabled;

	/**
	 * Checks if the footer widgets are enabled or not.
	 */
	public static function is_enabled(): bool {
		if ( null !== self::$is_enabled ) {
			return self::$is_enabled;
		}
		if ( totaltheme_call_static( 'Footer\Core', 'is_custom' ) ) {
			$check = get_theme_mod( 'footer_builder_footer_widgets', false );
		} else {
			$check = get_theme_mod( 'footer_widgets', true );
		}
		$post_id = wpex_get_current_post_id();
		if ( $post_id && $meta = get_post_meta( $post_id, 'wpex_disable_footer_widgets', true ) ) {
			if ( 'on' === $meta ) {
				$check = false;
			} elseif ( 'enable' === $meta ) {
				$check = true;
			}
		}
		$check = apply_filters( 'wpex_display_footer_widgets', $check ); // @deprecated
		self::$is_enabled = (bool) apply_filters( 'totaltheme/footer/widgets/is_enabled', $check );
		return self::$is_enabled;
	}

	/**
	 * Returns footer widgets widget title tag arguments.
	 */
	public static function widget_title_args(): array {
		$tag_escaped = ( $tag = get_theme_mod( 'footer_headings' ) ) ? \tag_escape( $tag ) : 'div';
		$font_size = totaltheme_has_classic_styles() ? 'wpex-text-md' : 'wpex-text-lg';
		$class = "widget-title wpex-heading {$font_size} wpex-mb-20";
		if ( ! is_customize_preview() ) {
			$fit_content = wp_validate_boolean( get_theme_mod( 'footer_headings_fit_content' ) );
			if ( $fit_content ) {
				$class .= ' wpex-block wpex-w-fit';
			}
			$align = get_theme_mod( 'footer_headings_align' );
			if ( $align && in_array ( $align, [ 'left', 'center', 'right' ], true ) ) {
				if ( is_rtl() ) {
					if ( 'left' === $align ) {
						$align = 'right';
					} elseif ( 'right' === $align ) {
						$align = 'left';
					}
				}
				$class .= " wpex-text-{$align}";
				if ( $fit_content ) {
					switch ( $align ) {
						case 'left':
							$class .= ' wpex-ml-auto';
							break;
						case 'center':
							$class .= ' wpex-mx-auto';
							break;
						case 'right':
							$class .= ' wpex-mr-auto';
							break;
					}
				}
			}
		}
		return [
			'before' => "<{$tag_escaped} class='{$class}'>",
			'after'  => "</{$tag_escaped}>",
		];
	}

	/**
	 * Returns wrapper classes.
	 */
	public static function get_wrapper_classes(): array {
		if ( totaltheme_call_static( 'Footer\Core', 'has_classic_layout' ) ) {
			$class = apply_filters( 'wpex_footer_widgets_class', self::get_row_classes() );
		} else {
			$class = [ 'container', 'wpex-pt-40' ];
		}
		return (array) apply_filters( 'totaltheme/footer/widgets/wrapper_class', $class );
	}

	/**
	 * Returns the wrapper class.
	 */
	public static function wrapper_class(): void {
		if ( $classes = self::get_wrapper_classes() ) {
			$classes = implode( ' ', $classes );
			if ( totaltheme_call_static( 'Footer\Core', 'has_classic_layout' ) ) {
				$classes = apply_filters( 'wpex_footer_widget_row_classes', $classes );
			}
			echo 'class="' . esc_attr( $classes ) . '"';
		}
	}

	/**
	 * Returns the row classes.
	 */
	public static function get_row_classes(): array {
		$columns = (int) get_theme_mod( 'footer_widgets_columns', 4 );
		$classes = [
			'wpex-row',
			'wpex-clr',
		];
		if ( 1 === $columns ) {
			$classes[] = 'single-col-footer'; // legacy class
		}
		if ( $gap = get_theme_mod( 'footer_widgets_gap', '30' ) ) {
			$classes[] = wpex_gap_class( $gap );
		}
		return $classes;
	}

	/**
	 * Returns the row class.
	 */
	public static function get_row_class(): string {
		$class = '';
		if ( $classes = self::get_row_classes() ) {
			$class = 'class="' . esc_attr( implode( ' ', $classes ) ) . '"';
		}
		return $class;
	}

}
