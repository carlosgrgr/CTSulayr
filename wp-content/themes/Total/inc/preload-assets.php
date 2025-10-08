<?php

namespace TotalTheme;

\defined( 'ABSPATH' ) || exit;

/**
 * Class used to insert links in the head for preloading assets.
 */
class Preload_Assets {

	/**
	 * Return array of links.
	 */
	public static function get_links(): array {
		return (array) \apply_filters( 'wpex_preload_links', [] );
	}

	/**
	 * Add links to wp_head.
	 */
	public static function add_links(): void {
		if ( \defined( 'IFRAME_REQUEST' ) && \IFRAME_REQUEST ) {
			return; // prevents preloading in iFrames where it's not needed (like widget block editor)
		}
		foreach ( self::get_links() as $link => $atts ) {
			if ( \array_key_exists( 'condition', $atts ) && false === $atts['condition'] ) {
				continue;
			}
			echo self::render_link( $atts ) . "\n";
		}
	}

	/**
	 * Renders a single link.
	 */
	private static function render_link( $atts = [] ): string {
		$link = '<link rel="preload" href="' . \esc_url( set_url_scheme( $atts['href'] ) ) . '"';
			if ( isset( $atts['type'] ) ) {
				$link .= ' type="' . \esc_attr( $atts['type'] ) . '"';
			}
			if ( isset( $atts['as'] ) ) {
				$link .= ' as="' . \esc_attr( $atts['as'] ) . '"';
			}
			if ( isset( $atts['media'] ) ) {
				$link .= ' media="' . \esc_attr( $atts['media'] ) . '"';
			}
			if ( isset( $atts['crossorigin'] ) ) {
				$link .= ' crossorigin';
			}
		$link .= '>';
		return $link;
	}

}
