<?php

namespace TotalTheme\Integration\WooCommerce;

use TotalThemeCore\Shortcodes\Shortcode_Cart_Link;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Quantity Plus/Minus Buttons.
 */
final class Quantity_Plus_Minus {

	/**
	 * Store style.
	 */
	protected static $style = null;

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Init.
	 */
	public static function init(): void {
		// We must always register the actions or the changes aren't visible in the customizer
		if ( is_customize_preview() || in_array( self::style(), [ 'vertical', 'horizontal' ], true ) ) {
			add_action( 'woocommerce_before_quantity_input_field', [ self::class, 'on_woocommerce_before_quantity_input_field' ] );
			add_action( 'woocommerce_after_quantity_input_field', [ self::class, 'on_woocommerce_after_quantity_input_field' ] );
		}
	}

	/**
	 * Returns the plus, minus button style.
	 */
	protected static function style(): string {
		if ( null === self::$style ) {
			self::$style = sanitize_text_field( (string) get_theme_mod( 'woo_quantity_buttons_style', 'vertical' ) );
		}
		return self::$style;
	}

	/**
	 * Returns the icon size.
	 */
	protected static function get_icon_size(): string {
		return 'vertical' === self::style() ? 'xs' : '';
	}

	/**
	 * Returns the plus icon.
	 */
	protected static function get_plus_icon(): string {
		$plus_icon = 'vertical' === self::style() ? 'material-arrow-up-ios' : 'material-add';
		return (string) apply_filters( 'wpex_woocommerce_quantity_button_plus_icon', $plus_icon );
	}

	/**
	 * Returns the minus icon.
	 */
	protected static function get_minus_icon(): string {
		$minus_icon = 'vertical' === self::style() ? 'material-arrow-down-ios' : 'material-remove';
		return (string) apply_filters( 'wpex_woocommerce_quantity_button_plus_icon', $minus_icon );
	}

	/**
	 * Returns the plus button.
	 */
	protected static function get_plus_button(): string {
		return '<a href="#" class="plus" aria-hidden="true">' . totaltheme_get_icon( self::get_plus_icon(), 'wpex-quantity-btns__icon', self::get_icon_size() ) . '</span></a>';
	}

	/**
	 * Returns the minus button.
	 */
	protected static function get_minus_button(): string {
		return '<a href="#" class="minus" aria-hidden="true">' . totaltheme_get_icon( self::get_minus_icon(), 'wpex-quantity-btns__icon', self::get_icon_size() ) . '</a>';
	}

	/**
	 * Hooks into woocommerce_before_quantity_input_field.
	 */
	public static function on_woocommerce_before_quantity_input_field() {
		switch ( self::style() ) {
			case 'vertical':
				echo '<div class="wpex-quantity-btns-wrap wpex-quantity-btns-wrap--' . esc_attr( self::style() ) . '">';
				break;
			case 'horizontal':
				echo '<div class="wpex-quantity-btns wpex-quantity-btns--' . esc_attr( self::style() ) . '">';
				echo self::get_minus_button();
				break;
		}
	}

	/**
	 * Hooks into woocommerce_after_quantity_input_field.
	 */
	public static function on_woocommerce_after_quantity_input_field() {
		switch ( self::style() ) {
			case 'vertical':
				echo '<div class="wpex-quantity-btns wpex-quantity-btns--vertical">';
					echo self::get_plus_button();
					echo self::get_minus_button();
				echo '</div>';
				echo '</div>'; // Close wrapper
				break;
			case 'horizontal':
				echo self::get_plus_button();
				echo '</div>'; // Close wrapper
				break;
		}
	}
	
}
