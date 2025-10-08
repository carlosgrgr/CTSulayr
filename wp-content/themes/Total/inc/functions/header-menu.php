<?php

defined( 'ABSPATH' ) || exit;

/*-------------------------------------------------------------------------------*/
/* [ Drop Widgets ]
/*-------------------------------------------------------------------------------*/

/**
 * Get header Menu dropdown widget class.
 */
function wpex_get_header_drop_widget_class(): array {
	$dropdown_style = totaltheme_call_static( 'Header\Menu', 'get_dropdown_style' );
	$animate        = wpex_validate_boolean( get_theme_mod( 'menu_drodown_animate' ) );
	$has_border     = true;

	if ( 'black' === $dropdown_style ) {
		$has_border = get_theme_mod( 'menu_dropdown_top_border' );
	}

	$class = [
		'header-drop-widget',
	];

	if ( $has_border ) {
		$class[] ='header-drop-widget--colored-top-border';
	}

	if ( $animate ) {
		$class[] = 'header-drop-widget--animate';
	}

	// Utility classes
	$class[] = 'wpex-invisible';
	$class[] = 'wpex-opacity-0';
	$class[] = 'wpex-absolute';
	if ( ! $animate ) {
		$class[] = 'wpex-transition-all';
		$class[] = 'wpex-duration-200';
	}
	$class[] = 'wpex-translate-Z-0';
	$class[] = 'wpex-text-initial';
	$class[] = 'wpex-z-dropdown';

	if ( totaltheme_call_static( 'Header\Vertical', 'is_enabled' ) ) {
		$class[] = 'wpex-top-0';
		$vh_position = sanitize_html_class( totaltheme_call_static( 'Header\Vertical', 'position' ) );
		$class[] = "wpex-{$vh_position}-100"; // left/right 100%
	} else {
		$class[] = 'wpex-top-100';
		$class[] = 'wpex-right-0';
	}

	$class[] = 'wpex-surface-1';
	$class[] = 'wpex-text-2';

	return (array) apply_filters( 'wpex_get_header_drop_widget_class', $class );
}

/**
 * Header Menu dropdown widget class.
 */
function wpex_header_drop_widget_class(): void {
	if ( $class = wpex_get_header_drop_widget_class() ) {
		echo 'class="' . esc_attr( implode( ' ', $class ) ) . '"';
	}
}

/**
 * Check if header menu dropdown widgets should be added inline (insite the li element).
 */
function wpex_maybe_add_header_drop_widget_inline( $widget = '' ): bool {
	$check        = true;
	$header_style = totaltheme_call_static( 'Header\Core', 'style' );
	if ( 'one' === $header_style || totaltheme_call_static( 'Header\Core', 'has_flex_container' ) ) {
		$check = false; // set items flush with the header bottom.
	}
	if ( function_exists( 'max_mega_menu_is_enabled' ) && max_mega_menu_is_enabled( 'main_menu' ) ) {
		$check = true;
	}
	return (bool) apply_filters( 'wpex_maybe_add_header_drop_widget_inline', $check, $widget );
}

/*-------------------------------------------------------------------------------*/
/* [ Mobile ]
/*-------------------------------------------------------------------------------*/

/**
 * Conditional check for alternative menu.
 */
function wpex_has_mobile_menu_alt() {
	$check = ( has_nav_menu( 'mobile_menu_alt' ) && ! totaltheme_call_static( 'Header\Menu', 'get_wp_menu' ) );
	return (bool) apply_filters( 'wpex_has_mobile_menu_alt', $check );
}

/**
 * Return header mobile menu classes.
 */
function wpex_header_mobile_menu_classes() {
	$header_style = totaltheme_call_static( 'Header\Core', 'style' );
	$flex_header  = totaltheme_call_static( 'Header\Core', 'has_flex_container' );
	$style        = totaltheme_call_static( 'Mobile\Menu', 'toggle_style' );
	$mm_style     = totaltheme_call_static( 'Mobile\Menu', 'style' );

	$classes = [
		'wpex-mobile-menu-toggle',
		'show-at-mm-breakpoint',
		// Add flex styles to all toggles.
		'wpex-flex',
		'wpex-items-center',
	];

	switch ( $style ) {
		case 'icon_buttons':
			if ( ! $flex_header ) {
				$classes[] = 'wpex-absolute wpex-top-50 -wpex-translate-y-50 wpex-right-0';
			}
			break;
		case 'icon_buttons_under_logo':
			if ( $flex_header ) {
				$classes[] = 'wpex-mt-10';
			} else {
				$classes[] = 'wpex-mt-20';
			}
			$classes[] = 'wpex-justify-center';
			break;
		case 'navbar':
			$classes[] = 'wpex-bg-gray-A900';
			break;
		case 'fixed_top':
			$classes[] = 'wpex-fixed';
			$classes[] = 'wpex-sticky-el-offset';
			$classes[] = 'wpex-ls-offset';
			$classes[] = 'wpex-z-dropdown'; // supports dropdown menu and search dropdown.
			$classes[] = 'wpex-top-0';
			$classes[] = 'wpex-inset-x-0';
			if ( 'toggle_full' === $mm_style ) {
				$classes[] = 'wpex-bg-gray-A900';
			} else {
				$classes[] = 'wpex-surface-dark';
			}
			break;
	}

	$styles_w_icons = [
		'icon_buttons',
		'icon_buttons_under_logo',
		'centered_logo',
		'next_to_logo',
	];

	if ( $flex_header && in_array( $style, $styles_w_icons ) ) {
		$classes[] = 'wpex-h-100';
	}

	$classes = (array) apply_filters( 'wpex_mobile_menu_toggle_class', $classes, $style );

	return esc_attr( implode( ' ', $classes ) );
}

/**
 * Returns classes for the header menu mobile toggle.
 */
function wpex_mobile_menu_toggle_class() {
	if ( $classes = wpex_header_mobile_menu_classes() ) {
		echo 'class="' . esc_attr( $classes ) . '"';
	}
}

/**
 * Return sidr menu source.
 */
function wpex_sidr_menu_source( $deprecated = '' ) {
	$items = [];
	if ( wpex_has_mobile_menu_alt() ) {
		$items['nav'] = '#mobile-menu-alternative';
	} else {
		$items['nav'] = '#site-navigation';
	}
	if ( get_theme_mod( 'mobile_menu_search', true ) ) {
		$items['search'] = '#mobile-menu-search';
	}
	$items = (array) apply_filters( 'wpex_mobile_menu_source', $items );
	return implode( ', ', $items );
}

/**
 * Return mobile toggle icon html.
 */
function wpex_get_mobile_menu_toggle_icon() {
	$mobile_menu_style = totaltheme_call_static( 'Mobile\Menu', 'style' );
	$text              = wpex_get_translated_theme_mod( 'mobile_menu_icon_label' );
	$toggle_state      = get_theme_mod( 'mobile_menu_icon_toggle_state', true );
	$toggle_state      = apply_filters( 'wpex_mobile_menu_icon_toggle_state', $toggle_state );

	$args = [
		'toggle_state' => $toggle_state,
		'animate'      => get_theme_mod( 'mobile_menu_icon_animate', true ),
		'rounded'      => get_theme_mod( 'mobile_menu_icon_rounded', false ),
	];

	$icon = totaltheme_call_static( 'Hamburger_Icon', 'render', $args );

	if ( $icon ) {
		$icon = '<span class="mobile-menu-toggle__icon wpex-flex">' . $icon . '</span>';
	}

	$el_class = 'mobile-menu-toggle';

	if ( $text ) {
		$el_class .= ' wpex-gap-10';
		$label_position = get_theme_mod( 'mobile_menu_icon_label_position' );
		if ( 'left' === $label_position ) {
			$el_class .= ' wpex-flex-row-reverse';
		}
		$text = '<span class="mobile-menu-toggle__label">' . esc_html( $text ) . '</span>';
	}

	// Begin output.
	$html = '<a href="#" class="' . esc_attr( $el_class ) . '" role="button" aria-expanded="false">';
		$button_text = $icon . $text;
		$html .= apply_filters( 'wpex_mobile_menu_open_button_text', $button_text );
		$html .= '<span class="screen-reader-text" data-open-text>' . esc_html( wpex_get_aria_label( 'mobile_menu_open' ) ) . '</span>';
		$html .= '<span class="screen-reader-text" data-open-text>' . esc_html( wpex_get_aria_label( 'mobile_menu_close' ) ) . '</span>';
	$html .= '</a>';

	$html = apply_filters( 'wpex_get_mobile_menu_toggle_icon', $html ); // @todo deprecate
	$html = apply_filters( 'wpex_mobile_menu_toggle_icon', $html );

	return $html;
}

/**
 * Return mobile menu extra icons.
 */
function wpex_mobile_menu_toggle_extra_icons( $echo = true ) {
	if ( ! class_exists( 'TotalTheme\Walkers\Mobile_Icons_Nav_Menu') ) {
		return '';
	}
	$icons = wp_nav_menu( [
        'theme_location'  => 'mobile_menu',
        'echo'            => false,
        'fallback_cb'     => false,
		'container'       => false,
		'items_wrap'      => '%3$s',
        'walker'          => new TotalTheme\Walkers\Mobile_Icons_Nav_Menu(),
		'placement'       => totaltheme_call_static( 'Mobile\Menu', 'icons_menu_placement' ),
    ] );
    $icons = apply_filters( 'wpex_get_mobile_menu_extra_icons', $icons ); // @deprecated
    $icons = (string) apply_filters( 'wpex_header_menu_mobile_toggle_icons', $icons );
    if ( $icons ) {
		if ( $echo ) {
       		echo '<div class="wpex-mobile-menu-toggle-extra-icons">' . $icons . '</div>';
		} else {
			return $icons;
		}
    }
}
