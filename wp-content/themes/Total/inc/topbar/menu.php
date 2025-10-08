<?php

namespace TotalTheme\Topbar;

defined( 'ABSPATH' ) || exit;

/**
 * Topbar Menu.
 */
class Menu {

	/**
	 * Classic layout check.
	 */
	private static $has_classic_layout = null;

	/**
	 * Checks if it's using classic layout or not.
	 * 
	 * @since 6.4
	 */
	private static function has_classic_layout(): bool {
		if ( null === self::$has_classic_layout ) {
			self::$has_classic_layout = apply_filters(
				'totaltheme/topbar/menu/has_classic_layout',
				totaltheme_version_check( 'initial', '6.4', '<' )
			);
		}
		return self::$has_classic_layout;
	}

	/**
	 * Returns menu theme location.
	 */
	public static function get_theme_location(): string {
		return (string) apply_filters( 'totaltheme/topbar/menu/theme_location', 'topbar_menu' );
	}

	/**
	 * Get the menu.
	 */
	public static function get_menu() {
		return wp_nav_menu( [
			'fallback_cb'    => false,
			'container'      => false,
			'link_before'    => '<span class="link-inner">',
			'link_after'     => '</span>',
			'theme_location' => self::get_theme_location(),
			'menu_class'     => implode( ' ', self::get_class() ),
			'echo'           => false,
		] );
	}

	/**
	 * Output wrapper class.
	 */
	public static function wrapper_class(): void {
		$class = [ 'top-bar-nav', 'wpex-inline-block' ];
		if ( ! self::has_classic_layout() && totaltheme_call_static( 'Topbar\Core', 'get_content' ) ) {
			$class[] = 'wpex-mr-20';
		}
		if ( $class ) {
			echo 'class="' . esc_attr( implode( ' ', array_unique( $class ) ) ) . '"';
		}
	}

	/**
	 * Returns the topbar menu class.
	 */
	protected static function get_class(): array {
		$class = [
			'top-bar-menu',
			// @todo add support for dropdowns? It creates a lot of complications and bloat...
		//	'wpex-dropdown-menu',
		//	'wpex-dropdown-menu--onclick',
			'wpex-inline-block',
			'wpex-m-0',
			'wpex-list-none',
			'wpex-last-mr-0',
		];
		if ( self::has_classic_layout() && totaltheme_call_static( 'Topbar\Core', 'get_content' ) ) {
			$class[] = 'wpex-mr-20';
		}
		return (array) apply_filters( 'totaltheme/topbar/menu/class', $class );
	}

	/**
	 * Renders the Topbar menu.
	 * 
	 * @deprecated 6.4
	 */
	public static function render() {
		wp_nav_menu( [
			'fallback_cb'    => false,
			'container'      => false,
			'link_before'    => '<span class="link-inner">',
			'link_after'     => '</span>',
			'theme_location' => self::get_theme_location(),
			'menu_class'     => implode( ' ', self::get_class(), ),
		] );
	}


}
