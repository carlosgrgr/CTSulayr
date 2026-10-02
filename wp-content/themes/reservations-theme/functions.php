<?php
/**
 * Tema hijo de Astra para el sitio de reservas.
 *
 * @package ReservationsTheme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * True en la página que contiene el panel de reservas.
 *
 * @return bool
 */
function reservations_theme_is_panel() {
	if ( ! is_singular() ) {
		return false;
	}

	$post = get_post();

	return $post instanceof WP_Post && has_shortcode( $post->post_content, 'sulayr_reservas' );
}

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style(
			'reservations-theme',
			get_stylesheet_uri(),
			array( 'astra-theme-css' ),
			wp_get_theme()->get( 'Version' )
		);
	},
	20
);

add_filter(
	'astra_get_content_layout',
	function ( $layout ) {
		return reservations_theme_is_panel() ? 'page-builder' : $layout;
	}
);

add_filter(
	'astra_page_layout',
	function ( $layout ) {
		return reservations_theme_is_panel() ? 'no-sidebar' : $layout;
	}
);

add_filter(
	'astra_the_title_enabled',
	function ( $enabled ) {
		return reservations_theme_is_panel() ? false : $enabled;
	}
);

add_filter(
	'body_class',
	function ( $classes ) {
		if ( reservations_theme_is_panel() ) {
			$classes[] = 'reservations-panel-page';
		}

		return $classes;
	}
);

add_filter(
	'astra_header_display',
	function ( $display ) {
		return reservations_theme_is_panel() ? 'disabled' : $display;
	}
);

add_filter(
	'astra_footer_bar_display',
	function ( $display ) {
		return reservations_theme_is_panel() ? 'disabled' : $display;
	}
);

add_filter(
	'astra_disable_mobile_popup_markup',
	function ( $disable ) {
		return reservations_theme_is_panel() ? true : $disable;
	}
);

add_action(
	'wp',
	function () {
		if ( ! reservations_theme_is_panel() ) {
			return;
		}

		remove_action( 'astra_header', 'astra_header_markup' );
		remove_action( 'astra_footer', 'astra_footer_markup' );

		if ( class_exists( 'Astra_Builder_Header' ) ) {
			$header = Astra_Builder_Header::get_instance();
			remove_action( 'astra_header', array( $header, 'header_builder_markup' ) );
			remove_action( 'astra_footer', array( $header, 'mobile_popup' ) );
			remove_action( 'astra_footer', array( $header, 'mobile_cart_flyout' ) );
		}

		if ( class_exists( 'Astra_Builder_Footer' ) ) {
			remove_action( 'astra_footer', array( Astra_Builder_Footer::get_instance(), 'footer_markup' ) );
		}
	}
);

/**
 * Logo y nombre del sitio, dentro de main#main.
 */
function reservations_theme_brand() {
	if ( ! reservations_theme_is_panel() ) {
		return;
	}

	echo '<div class="reservations-brand">';

	if ( has_custom_logo() ) {
		echo get_custom_logo(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	echo '<a class="reservations-brand-name" href="' . esc_url( home_url( '/' ) ) . '">';
	echo esc_html( get_bloginfo( 'name' ) );
	echo '</a>';
	echo '</div>';
}

add_action( 'astra_entry_top', 'reservations_theme_brand', 5 );
