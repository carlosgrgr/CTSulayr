<?php

namespace TotalTheme\Helpers;

\defined( 'ABSPATH' ) || exit;

/**
 * Font Loader class enqueues a font family by name or alias.
 */
class Font_Loader {

	/**
	 * Array of already loaded fonts.
	 */
	static $loaded_fonts = [];

	/**
	 * Font to load.
	 */
	public $font = '';

	/**
	 * Font type.
	 */
	public $type = '';

	/**
	 * Font args.
	 */
	public $font_args = '';

	/**
	 * Font URL.
	 */
	public $font_url = '';

	/**
	 * Main constructor.
	 */
	public function __construct( $font, $type = '', $args = [] ) {
		$this->font      = $font;
		$this->type      = $type;
		$this->font_args = $args;

		if ( $this->font ) {
			$this->load_font();
		}
	}

	/**
	 * Load the font.
	 */
	protected function load_font() {
		if ( \array_key_exists( $this->font, self::$loaded_fonts ) ) {
			$this->font_url = self::$loaded_fonts[ $this->font ];
			return; // Already loaded
		}

		if ( empty( $this->font_args ) ) {
			$registered_font = wpex_get_registered_font( $this->font );
			if ( $registered_font ) {
				$this->type = 'registered';
				$this->font_args = $registered_font;
			}
		}

		if ( ! $this->type ) {
			$this->type = 'google'; // Google is the fallback font type
		}

		if ( 'registered' === $this->type ) {
			$this->load_custom_font();
		} else {
			$this->load_theme_font();
		}

		if ( ! empty( $this->font_url ) ) {
			self::$loaded_fonts[ $this->font ] = $this->font_url;
		}
	}

	/**
	 * Load a theme font.
	 */
	protected function load_theme_font() {
		if ( 'google' === $this->type ) {
			$gfonts = \wpex_google_fonts_array();
			if ( empty( $gfonts ) || ! \is_array( $gfonts ) ) {
				return;
			}
			if ( 'Sansita One' === $this->font ) {
				$this->font = 'Sansita'; // renamed font
			}
			if ( \in_array( $this->font, $gfonts, true ) ) {
				$this->enqueue_google_font( $this->font );
			}
		}
	}

	/**
	 * Load a custom font.
	 */
	protected function load_custom_font() {
		if ( ! empty( $this->font_args['style_handle'] ) ) {
			wp_enqueue_style( $this->font_args['style_handle'] );
			$this->font_url = $this->font_args['url'] ?? '';
		} else {
			// @deprecated 6.4
			$type = ! empty( $this->font_args['type'] ) ? $this->font_args['type'] : '';
			if ( $type ) {
				$method = "enqueue_{$type}_font";
				if ( \method_exists( $this, $method ) ) {
					return $this->$method( $this->font, $this->font_args );
				}
			}
		}
	}

	/**
	 * Enqueue google font.
	 */
	protected function enqueue_google_font( $font, $args = [] ) {
		if ( ! \wpex_has_google_services_support() ) {
			return;
		}

		// Define default Google font args
		$default_args = [
			'weights' => [ '100', '200', '300', '400', '500', '600', '700', '800', '900' ],
			'italic'  => true,
			'subset'  => \get_theme_mod( 'google_font_subsets', [ 'latin' ] ),
			'display' => (string) \get_theme_mod( 'google_font_display', 'swap' ),
		];

		// Parse args and extract
		\extract( \wp_parse_args( $args, $default_args ) );

		// Check allowed font weights
		$weights = \apply_filters( 'wpex_google_font_enqueue_weights', $weights, $font );
		$weights = \is_array( $weights ) ? $weights : \explode( ',', $weights );

		// Check if we should get italic fonts
		$italic = \apply_filters( 'wpex_google_font_enqueue_italics', $italic, $font );

		// Check the subsets to load
		$subset = \apply_filters( 'wpex_google_font_enqueue_subsets', $subset, $font );
		$subset = \is_array( $subset ) ? $subset : \explode( ',', $subset );

		// Check font display type
		$display = (string) \apply_filters( 'wpex_google_font_enqueue_display', $display, $font );

		// Define Google Font URL
		$url = \wpex_get_google_fonts_url() . '/css2?family=' . $this->sanitize_google_font_name( $font );

		// Font weights & italics
		if ( ! empty( $weights ) ) {
			if ( 1 === count( $weights ) && 400 === (int) $weights[0] ) {
				$url .= $italic ? 'ital@0;1' : '@0;1';
			} else {
				$url .= $italic ? ':ital,wght@' : ':wght@';
				$weight_axes = [];
				if ( $italic ) {
					foreach ( $weights as $weight ) {
						$weight_axes[] = "0,{$weight}";
					}
					foreach ( $weights as $weight ) {
						$weight_axes[] = "1,{$weight}";
					}
				} else {
					foreach ( $weights as $weight ) {
						$weight_axes[] = $weight;
					}
				}
				$url .= \implode( ';', $weight_axes );
			}
		}

		// Add font display
		if ( $display && in_array( $display, $this->get_display_choices(), true ) ) {
			$url .= "&display={$display}";
		}

		// Add subsets
		if ( $subset && $subset_sanitized = array_filter( $subset, [ $this, 'sanitize_subset_array' ] ) ) {
			$subset_string = implode( ', ', $subset_sanitized );
			$url .= "&subset={$subset_string}";
		}

		// Update $font_url var
		$this->font_url = \esc_url( $url );

		// Enqueue the font
		\wp_enqueue_style( 
			'wpex-google-font-' . \str_replace( ' ', '-', \strtolower( \trim( $font ) ) ),
			$this->font_url,
			[],
			null
	);
	}

	/**
	 * Enqueue adobe font.
	 *
	 * @deprecated 6.4
	 */
	protected function enqueue_adobe_font( $font, $args = [] ) {
		if ( empty( $args['project_id'] ) ) {
			return;
		}
		$project_id_safe = \sanitize_text_field( $args['project_id'] );
		if ( ! $project_id_safe ) {
			return;
		}
		$this->font_url = \esc_url( "https://use.typekit.net/{$project_id_safe}.css" );
		\wp_enqueue_style(
			"typekit-{$project_id_safe}",
			$this->font_url,
			[],
			null
		);
	}

	/**
	 * Enqueue custom font.
	 */
	protected function enqueue_custom_font( $font, $args = [] ) {
		$handle = 'wpex-hosted-' . sanitize_title( $font );
		if ( wp_style_is( $handle ) ) {
			return;
		}
		if ( \class_exists( '\TotalTheme\Helpers\Font_Face_Generator' ) ) {
			$font_face_css = (new \TotalTheme\Helpers\Font_Face_Generator( $font, $args ))->render();
			if ( $font_face_css ) {
				wp_register_style( $handle, false );
				wp_add_inline_style( $handle, $font_face_css );
				wp_enqueue_style( $handle );
			}
		}
	}

	/**
	 * Sanitize Google Font Name.
	 */
	protected function sanitize_google_font_name( string $font ): string {
		return \str_replace( ' ', '+', \trim( $font ) );
	}

	/**
	 * Returns display choices.
	 */
	protected function get_display_choices(): array {
		return [ 'auto', 'block', 'swap', 'fallback', 'optional', ];
	}

	/**
	 * Returns subset choices.
	 */
	protected function get_subset_choices(): array {
		return [ 'latin', 'latin-ext', 'cyrillic', 'cyrillic-ext', 'greek', 'greek-ext', 'vietnamese' ];
	}

	/**
	 * Sanitize 
	 */
	protected function sanitize_subset_array( $value ): bool {
		return in_array( $value, $this->get_subset_choices(), true );
	}

	/**
	 * Returns the font URL.
	 */
	public function get_url() {
		return $this->font_url;
	}

	/**
	 * Returns the font type.
	 */
	public function get_type() {
		return $this->type;
	}

	/**
	 * Helper function enqueues all registered fonts.
	 */
	public static function enqueue_all_registered_fonts(): void {
		foreach ( \wpex_get_registered_fonts() as $font_name => $font_args ) {
			if ( isset( $font_args['style_handle'] ) ) {
				wp_enqueue_style( $font_args['style_handle'] );
			} else {
				new self( $font_name, 'registered', $font_args );
			}
		}
	}

	/**
	 * Return font handle for enqueue.
	 *
	 * @deprecated 6.4
	 */
	public function get_font_handle( string $font ): string {
		return \str_replace( ' ', '-', \strtolower( \trim( $font ) ) );
	}

}
