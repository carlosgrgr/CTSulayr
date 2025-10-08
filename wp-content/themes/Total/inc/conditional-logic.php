<?php

namespace TotalTheme;

defined( 'ABSPATH' ) || exit;

/**
 * Handles conditional logic checks.
 */
class Conditional_Logic {

	/**
	 * Result.
	 */
	public $result = false;

	/**
	 * Constructor.
	 */
	public function __construct( $conditions ) {
		if ( is_string( $conditions ) ) {
			$conditions = str_replace( ' ', '', $conditions );
			parse_str( $conditions, $conditions );
		}

		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return;
		}

		if ( isset( $conditions['is_global'] ) ) {
			$this->result = true;
			return;
		}

		$pos_conditions = [];
    	$neg_conditions = [];

		foreach ( $conditions as $raw_condition => $check ) {
			if ( str_starts_with( $raw_condition, '!' ) ) {
				$neg_conditions[ substr( $raw_condition, 1 ) ] = $check;
			} else {
				$pos_conditions[ $raw_condition ] = $check;
			}
		}

		// Check positives: return false if none match
		// We need the extra if check incase the only condition is negative
		if ( $pos_conditions ) {
			
			// Loop through conditions
			$matched = false;
			foreach ( $pos_conditions as $condition => $check ) {
				$callback = $this->get_condition_callback( $condition );
				if ( $callback ) {
					$condition_result = $check ? (bool) call_user_func( $callback, $this->string_to_array( $check ) ) : (bool) call_user_func( $callback );
					if ( $condition_result ) {
						$matched = true;
						break;
					}
				}
			}

			// No positive matches
			if ( ! $matched ) {
				$this->result = false;
				return;
			}
		}

		// Check negations: if any is true, return false | e.g: !is_search
		foreach ( $neg_conditions as $condition => $check ) {
			$callback = $this->get_condition_callback( $condition );
			if ( $callback ) {
				$condition_result = $check ? (bool) call_user_func( $callback, $this->string_to_array( $check ) ) : (bool) call_user_func( $callback );
				if ( $condition_result ) {
					$this->result = false;
					return;
				}
			}
		}

		// If reached here, positive matched and no negation matched
		$this->result = true;
	}

	/**
	 * Get conditional tags.
	 */
	private function get_conditional_tags(): array {
		$tags = [
			// User
			'is_user_logged_in',
			'is_super_admin',
			// Core
			'is_main_site',
			// Loop
			'is_main_query',
			'is_paged',
			'not_paged',
			'in_the_loop',
			// Posts
			'is_page',
			'is_page_template',
			'is_singular',
			'is_single',
			'is_attachment',
			'is_sticky',
			'has_term',
			'has_tag',
			'has_post_thumbnail',
			// Archives
			'is_tax',
			'is_search',
			'is_tag',
			'is_category',
			'is_archive',
			'is_post_type_archive',
			'is_author',
			'is_date',
			'is_year',
			'is_month',
			'is_day',
			'is_time',
			'is_new_day',
			'is_404',
			// Homepage
			'is_home',
			'is_front_page',
			// WooCommerce
			'is_shop',
			'is_product_category',
			'is_product_tag',
			'is_woocommerce',
			'is_wc_endpoint_url',
			'wpex_is_woo_shop',
			'wpex_is_woo_tax',
			'wpex_is_woo_single',
			// Tribe Events
			'tribe_is_event',
			'tribe_is_view',
			'tec_is_view',
			'tribe_is_list_view',
			'tribe_is_event_category',
			'tribe_is_in_main_loop',
			'tribe_is_day',
			'tribe_is_month',
		];
		$tags = array_merge( $tags, self::get_class_conditions() );
		$tags = apply_filters( 'wpex_conditional_logic_callable_whitelist', $tags ); // @deprecated
		return (array) apply_filters( 'totaltheme/conditional_logic/conditional_tags', $tags );
	}

	/**
	 * Class conditions.
	 */
	private static function get_class_conditions(): array {
		return [
			'has_page_header',
			'page_header_style',
			'has_dynamic_template',
			'has_image_gallery',
		];
	}

	/**
	 * Get the condition callback.
	 */
	private function get_condition_callback( string $condition ) {
		if ( ! $this->is_condition_allowed( $condition ) ) {
			return;
		}
		$callback = $condition;
		if ( in_array( $callback, self::get_class_conditions(), true ) ) {
			$callback = [ self::class, $condition ];
		}
		if ( is_callable( $callback ) ) {
			return $callback;
		}
	}

	/**
	 * Check if condition is allowed (whitelisted).
	 */
	private function is_condition_allowed( $function = '' ): bool {
		$whitelist = (array) $this->get_conditional_tags();
		return ( $whitelist && in_array( $function, $whitelist, true ) );
	}

	/**
	 * Converts strings to arrays.
	 */
	private function string_to_array( $input ): array {
		if ( is_string( $input ) ) {
			$input = explode( ',', sanitize_text_field( $input ) );
		}
		return (array) $input;
	}

	/**
	 * Callback for the has_page_header conditional.
	 */
	private static function has_page_header(): bool {
		return (bool) totaltheme_call_static( 'Page\Header', 'is_enabled' );
	}

	/**
	 * Callback for the has_image_gallery conditional.
	 */
	private static function has_image_gallery(): bool {
		return (bool) wpex_has_post_gallery();
	}

	/**
	 * Callback for the has_page_header conditional.
	 */
	private static function page_header_style( $style = [] ): bool {
		return self::has_page_header() && in_array( totaltheme_call_static( 'Page\Header', 'style' ), $style, true );
	}

	/**
	 * Callback for the has_dynamic_template conditional.
	 */
	private static function has_dynamic_template(): bool {
		return (bool) totaltheme_location_has_template( is_singular() ? 'single' : 'archive' );
	}

}
