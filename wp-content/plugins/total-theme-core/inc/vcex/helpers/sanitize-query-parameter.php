<?php

namespace TotalThemeCore\Vcex\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Sanitizes query parameters.
 */
class Sanitize_Query_Parameter {

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Sanitize order.
	 */
	public static function order( $order, $fallback = 'DESC' ) {
		if ( ! is_string( $order ) ) {
			return $fallback;
		}
		$order = strtoupper( $order );
		return in_array( $order, [ 'ASC', 'DESC' ], true ) ? $order : $fallback;
	}

	/**
	 * Sanitize orderby.
	 */
	public static function orderby( $orderby, $fallback = 'post_date' ) {
		if ( empty( $orderby ) ) {
			return $fallback;
		}
		$sanitized = sanitize_sql_orderby( $orderby );
		return ( $sanitized !== false ) ? $sanitized : $fallback;
	}

	/**
	 * Sanitize posts per page.
	 */
	public static function posts_per_page( $value, $fallback = 50 ) {
		if ( empty( $value ) ) {
			return (int) $fallback;
		}
		$value = filter_var( $value, FILTER_VALIDATE_INT );
		return ( false !== $value ) ? $value : (int) $fallback;
	}

}
