<?php

namespace TotalTheme\Integration;

defined( 'ABSPATH' ) || exit;

/**
 * Page Optimize Integration.
 * 
 * Page Optimize is a plugin installed by default on WordPress.com
 * we need to exclude all the theme's scripts from their concat function
 * because the plugind doesn't properly respect script attributes like defer or media.
 */
final class Page_Optimize {

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Init.
	 */
	public static function init(): void {
		add_filter( 'css_do_concat', [ self::class, '_maybe_disable_concat' ], 999, 2 );
		add_filter( 'js_do_concat', [ self::class, '_maybe_disable_concat' ], 999, 2 );
	}

	/**
	 * Hooks into the css_do_concact and js_do_concat hooks.
	 */
	public static function _maybe_disable_concat( $check, $handle ) {
		if ( $handle
			&& is_string( $handle )
			&& ( str_starts_with( $handle, 'wpex' )
				|| str_starts_with( $handle, 'vcex' )
				|| str_starts_with( $handle, 'totaltheme' )
				|| str_starts_with( $handle, 'js_composer' )
				|| 'parent-style' === $handle
			)
		) {
			return false;
		}
		return $check;
	}

}
