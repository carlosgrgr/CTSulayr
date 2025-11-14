<?php

namespace TotalThemeCore;

use PTU\Taxonomies as PTU_Taxonomies;

defined( 'ABSPATH' ) || exit;

/**
 * Adds support for Term colors.
 */
class Term_Colors {

	/**
	 * Check if we should cache the CSS.
	 */
	private const ENABLE_CACHE = true;

	/**
	 * Transient name for cached CSS.
	 */
	private const TRANSIENT_NAME = 'wpex_term_colors_css';
	
	/**
	 * Style handle used when registering the inline css.
	 */
	private const STYLE_HANDLE = 'wpex-term-colors';

	/**
	 * The meta_id used to store the term color.
	 */
	protected const META_KEY = 'wpex_color';

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Init.
	 */
	public static function init() {
		if ( is_admin() ) {
			add_filter( 'wpex_term_meta_options', [ self::class, 'filter_wpex_term_meta_options' ] );
		}
		add_action( 'wp_enqueue_scripts', [ self::class, 'on_wp_enqueue_scripts_early' ], 5 ); // before stylesheets
	}

	/**
	 * Hooks into wp_enqueue_scripts.
	 */
	public static function on_wp_enqueue_scripts_early(): void {
		$css = self::get_terms_colors_css();
		if ( $css ) {
			wp_register_style( self::STYLE_HANDLE, false );
			wp_add_inline_style( self::STYLE_HANDLE, $css );
			wp_enqueue_style( self::STYLE_HANDLE );
		}
	}

	/**
	 * Returns an array of supported taxonomies for the color option.
	 */
	public static function supported_taxonomies() {
		$taxonomies = [ 'category' ];
		if ( is_callable( 'PTU\Taxonomies::get_registered_items' ) ) {
			$ptu_taxonomies = PTU_Taxonomies::get_registered_items();
			if ( is_array( $ptu_taxonomies ) && function_exists( 'wpex_get_ptu_tax_mod' ) ) {
				foreach ( $ptu_taxonomies as $ptu_taxonomy_post ) {
					$taxonomy = get_post_meta( $ptu_taxonomy_post, '_ptu_name', true );
					if ( taxonomy_exists( $taxonomy ) && wp_validate_boolean( wpex_get_ptu_tax_mod( $taxonomy, 'term_colors' ) ) ) {
						$taxonomies[] = $taxonomy;
					}
				}
			}
		}
		return (array) apply_filters( 'wpex_term_colors_supported_taxonomies', $taxonomies );
	}

	/**
	 * Adds a new term option for defining the term color.
	 */
	public static function filter_wpex_term_meta_options( $options ) {
		$supported_taxonomies = (array) self::supported_taxonomies();
		if ( $supported_taxonomies ) {
			$meta_options = [
				'label'          => esc_html__( 'Color', 'total-theme-core' ),
				'type'            => 'color',
				'has_admin_col'   => true,
				'show_on_create'  => true,
				'taxonomies'      => $supported_taxonomies,
				'exclude'         => 'extra,theme',
				'args'            => [
					'type'              => 'color',
					'single'            => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			];
			if ( self::ENABLE_CACHE ) {
				$meta_options['flush_transient'] = self::TRANSIENT_NAME;
			}
			$options = array_merge( [ self::META_KEY => $meta_options ], $options );
		}
		return $options;
	}

	/**
	 * Returns the color for a given term.
	 */
	public static function get_term_color( $term ) {
		$term = get_term( $term );
		if ( $term && ! is_wp_error( $term ) ) {
			return get_term_meta( $term->term_id, self::META_KEY, true );
		}
	}

	/**
	 * Sanitizes the color.
	 */
	private static function sanitize_color( $color ) {
		$color = sanitize_text_field( $color );
		if ( str_starts_with( $color, 'palette-' ) && class_exists( 'WPEX_Color_Palette' ) ) {
			$color = "var(--wpex-{$color}-color)";
		}
		return $color;
	}

	/**
	 * Generate css.
	 */
	private static function generate_css() {
		$taxonomies = self::supported_taxonomies();

		if ( ! is_array( $taxonomies ) || 0 === count( $taxonomies ) ) {
			return '';
		}

		$terms_colors = [];

		$terms = get_terms( [
			'taxonomy'   => $taxonomies,
			'hide_empty' => true,
			'meta_key'   => self::META_KEY,
		] );

		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$term_color = self::get_term_color( $term );
				if ( $term_color ) {
					$terms_colors[ $term->term_id ] = $term_color;
				}
			}
		}

		if ( ! $terms_colors ) {
			return '';
		}

		$variables = '';
		$classes   = '';

		foreach ( $terms_colors as $term_id => $term_color ) {
			$term_color_safe = self::sanitize_color( $term_color );
			$term_id_safe    = absint( $term_id );
			if ( $term_id_safe ) {
				$variables .= "--wpex-term-{$term_id_safe}-color:{$term_color_safe};";
				$classes .= ".has-term-{$term_id_safe}-color,.has-term-{$term_id_safe}-hover-color:hover{color:var(--wpex-term-{$term_id_safe}-color)!important;}";
				$classes .= ".has-term-{$term_id_safe}-background-color,.has-term-{$term_id_safe}-hover-background-color:hover{background-color:var(--wpex-term-{$term_id_safe}-color)!important;}";
			}
		}

		$root_css = ":root{{$variables}}";

		return "{$root_css}{$classes}";
	}

	/**
	 * Returns all term colors CSS.
	 */
	public static function get_terms_colors_css(): string {
		if ( self::ENABLE_CACHE ) {
			$css = get_transient( self::TRANSIENT_NAME );
			if ( is_string( $css ) ) {
				return $css;
			}
		}
		$css = self::generate_css();
		if ( self::ENABLE_CACHE ) {
			set_transient( self::TRANSIENT_NAME, $css, MONTH_IN_SECONDS );
		}
		return $css;
	}

	/**
	 * Hooks into "totaltheme/inline_css".
	 */
	public static function on_totaltheme_inline_css() {
		_deprecated_function( __METHOD__, 'Total Theme Core 2.4' );
	}

}
