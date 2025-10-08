<?php

namespace TotalTheme\Walkers;

use Walker_Nav_Menu;

defined( 'ABSPATH' ) || exit;

/**
 * Custom Walker_Nav_Menu for the mobile menu icons nav menu.
 */
class Mobile_Icons_Nav_Menu extends Walker_Nav_Menu {

	/**
	 * Flatten the menu.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_lvl( &$output, $depth = 0, $args = null ) {}
	public function end_el( &$output, $item, $depth = 0, $args = null ) {}

	/**
	 * Link output.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = [], $id = 0 ) {
		if ( empty( $item ) || empty( $item->url ) ) {
			return;
		}

		$icon_is_title = false;
		$icon_name     = get_post_meta( $item->ID, '_menu_item_totaltheme_icon', true );

		if ( ! $icon_name ) {
			$icon_is_title = true;
			$icon_name     = $item->title ?? '';
		}

		if ( ! $icon_name ) {
			return;
		}

		$link_attrs = [
			'href' => esc_url( $item->url ),
		];

		$title       = $item->title ?? '';
		$title_attr  = $item->attr_title ?? '';
		$description = '';
		$reader_text = '';
		$icon_html   = totaltheme_get_icon( $icon_name );

		if ( ! $icon_html ) {
			return;
		}

		$a_class = [
			'mobile-menu-extra-icons',
			'mobile-menu-' . sanitize_html_class( $icon_name ),
			'wpex-inline-block',
			'wpex-no-underline',
		];

		// Menu placement
		$placement = $args->placement ?? 'before_toggle';

		if ( 'before_toggle' === $placement ) {
			$a_class[] = 'wpex-mr-20';
		} elseif ( 'after_toggle' === $placement ) {
			$a_class[] = 'wpex-ml-20';
		}

		// Add custom classes
		$user_classes = get_post_meta( $item->ID, '_menu_item_classes', true );
		if ( $user_classes && is_array( $user_classes ) ) {
			$user_classes = array_map( 'sanitize_html_class', $user_classes );
			$a_class = array_merge( $a_class, $user_classes );
		}

		// Woo Cart icon
		if ( totaltheme_is_integration_active( 'woocommerce' )
			&& (
				'#cart' === $item->url
				|| str_contains( $icon_name, 'shopping-' )
				|| str_contains( $icon_name, 'cart' )
			)
		) {
			if ( 'icon_dot' === totaltheme_call_static( 'Integration\WooCommerce\Cart', 'header_display' ) ) {
				// @todo not currently supported because of styling, it doesn't look great, this is best done with a flex header
			//	$icon_html = '<span class="wpex-relative wpex-inline-block">' . $icon_html . '<span class="wpex-cart-badge wpex-cart-badge--dot"></span></span>';
			} else {
				wp_enqueue_script( 'wc-cart-fragments' );
				$icon_html = '<span class="wpex-relative wpex-inline-block">' . $icon_html . totaltheme_call_static( 'Integration\WooCommerce\Cart', 'get_count_bubble' ) . '</span>';
			}
			$cart_style = totaltheme_call_static( 'Integration\WooCommerce\Cart', 'style' );
			if ( 'store' === $cart_style ) {
				if ( $link_attrs['href'] === '#' && function_exists( 'wc_get_cart_url' ) ) {
					$link_attrs['href'] = wc_get_cart_url();
				}
			} elseif ( 'custom-link' === $cart_style ) {
				if ( $link_attrs['href'] === '#' ) {
					$custom_link = get_theme_mod( 'woo_menu_icon_custom_link' );
					if ( $custom_link ) {
						if ( str_starts_with( $custom_link, '/' ) ) {
							$custom_link = home_url( $custom_link );
						}
						$link_attrs['href'] = esc_url( $custom_link );
					}
				}
			} elseif ( in_array( $cart_style, [ 'drop_down', 'overlay', 'off-canvas' ], true ) ) {
				$link_attrs['role']          = 'button';
				$link_attrs['aria-expanded'] = 'false';
				switch ( $cart_style ) {
					case 'drop_down':
						$link_attrs['aria-controls'] = 'current-shop-items-dropdown';
						break;
					case 'overlay':
						$link_attrs['aria-controls'] = 'wpex-cart-overlay';
						break;
					case 'off-canvas':
						$link_attrs['data-wpex-toggle'] = 'off-canvas';
						$link_attrs['aria-controls'] = 'wpex-off-canvas-cart';
						break;
				}
				if ( $icon_is_title && ! $title_attr ) {
					$link_attrs['aria-label'] = esc_attr( wpex_get_aria_label( 'cart_open' ) );
				}
			}
		}

		// Search Icon
		if ( '#search' === $item->url || str_contains( $icon_name, 'search' ) ) {
			$link_attrs['href']          = '#';
			$link_attrs['role']          = 'button';
			$link_attrs['aria-expanded'] = 'false';
			if ( 'modal' === totaltheme_call_static( 'Header\Menu\Search', 'style' ) ) {
				$a_class[] = 'wpex-open-modal';
				$link_attrs['aria-controls'] = 'wpex-search-modal';
			}
		}

		// Add menu item description as the label
		if ( ! empty( $item->description ) ) {
			$description = '<span class="wpex-icon-label wpex-ml-10">' . esc_html( $item->description ) . '</span>';
		}

		// Add title attribute or screen reader text
		if ( $title_attr ) {
			$link_attrs['title'] = esc_attr( $title_attr );
		} elseif ( ! $icon_is_title && $title ) {
			$reader_text = '<span class="screen-reader-text">' . esc_html( $title ) . '</span>';
		}

		// Add class to link attributes
		$link_attrs['class'] = $a_class;

		// Parse menu item html
		$output .= wpex_parse_html( 'a', $link_attrs, $icon_html . $reader_text . $description );
	}

}
