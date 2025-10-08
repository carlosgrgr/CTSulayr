<?php

namespace TotalTheme\Integration;

\defined( 'ABSPATH' ) || exit;

/**
 * W3 Total Cache Configuration Class.
 */
final class W3_Total_cache {

	/**
	 * Static only class.
	 */
	private function __construct() {}

	/**
	 * Init.
	 */
	public static function init() {
		\add_filter( 'w3tc_minify_css_do_tag_minification', [ self::class, 'exclude_css_from_minify' ], 10, 3 );
	}

	/**
	 * Exclude certain theme files from the minification process.
	 */
	public static function exclude_css_from_minify( $do_tag_minification, $style_tag, $file ) {
		if ( \is_string( $style_tag ) && '' !== $style_tag ) {
			$exclude_files = [
				'wpex-mobile-menu-breakpoint-max',
				'wpex-mobile-menu-breakpoint-min',
				'wpex-overlay-header-css',
				'wpex-vertical-header',
			];
			foreach ( $exclude_files as $excluded_file ) {
				if ( \str_contains( $style_tag, $excluded_file ) ) {
					return false;
				}
			}
		}
		return $do_tag_minification;
	}

}
