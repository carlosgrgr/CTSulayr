<?php

namespace TotalTheme\Helpers;

\defined( 'ABSPATH' ) || exit;

/**
 * Font Face Generator.
 */
class Font_Face_Generator {

	/**
	 * The font name.
	 */
	protected $font = '';

	/**
	 * Font arguments.
	 */
	protected $args = '';

	/**
	 * CSS output.
	 */
	protected $css  = '';

	/**
	 * Constructor.
	 */
	public function __construct( $font, $args ) {
		$this->font = $font;
		$this->args = $args;
	}

	/**
	 * Render the Custom CSS.
	 */
	public function render() {
		$variants = $this->args['variants'] ?? $this->args['custom_fonts'] ?? [];
		if ( $variants && \is_array( $variants ) ) {
			foreach ( $variants as $variant ) {
				$this->render_variant( $variant );
			}
		}
		return $this->css;
	}

	/**
	 * Generate font-face for variant.
	 */
	private function render_variant( $variant ) {
		$css = '';

		$urls = [];

		if ( ! empty( $variant['woff2'] ) ) {
			$woff2_escaped = \set_url_scheme( \esc_url( $variant[ 'woff2' ] ) );
			$urls[] = 'url(' . $woff2_escaped . ") format('woff2')";
		}

		if ( ! empty( $variant['woff'] ) ) {
			$woff_escaped = \set_url_scheme( \esc_url( $variant[ 'woff' ] ) );
			$urls[] = 'url(' . $woff_escaped . ") format('woff')";
		}

		if ( ! empty( $variant['ttf'] ) ) {
			$ttf_escaped = \set_url_scheme( \esc_url( $variant[ 'ttf' ] ) );
			$urls[] = 'url(' . $ttf_escaped . ") format('truetype')";
		}

		if ( $urls ) {
			$css  .= '@font-face {';
				$css .= 'font-family:\'' . \esc_attr( $this->font ) . '\';';
				$css .= 'src:' . \implode( ', ', $urls ) . ';';
				if ( ! empty( $variant['weight'] ) ) {
					$css .= 'font-weight:' . \esc_attr( $variant['weight'] ) . ';';
				}
				if ( ! empty( $variant['style'] ) ) {
					$css .= 'font-style:' . \esc_attr( $variant['style'] ) . ';';
				}
				if ( ! empty( $this->args['display'] ) ) {
					$css .= 'font-display:' . \esc_attr( $this->args['display'] ) . ';';
				}
			$css .= '}';
		}

		if ( $css ) {
			$this->css .= $css;
		}
	}

}
