<?php

namespace TotalThemeCore\Shortcodes;

defined( 'ABSPATH' ) || exit;

final class Shortcode_Header_Search_Icon {

	/**
	 * Register the shortcode and add filters.
	 */
	public function __construct() {
		add_shortcode( 'header_search_icon', [ self::class, 'output' ] );
	}

	/**
	 * Shortcode output.
	 */
	public static function output( $atts = array() ) {
		if (  ! function_exists( 'totaltheme_call_static' )
			|| 'disabled' === totaltheme_call_static( 'Header\Menu\Search', 'style' )
		) {
			return;
		}

		$atts = shortcode_atts( [
			'style'                 => 'default',
			'class'                 => '',
			'label_class'           => '',
			'button_class'          => '',
			'icon_class'            => '',
			'visibility'            => '',
			'label'                 => '',
			'label_margin'          => '',
			'label_position'        => 'right',
			'label_hide_breakpoint' => '',
			'aria_label'            => '',
		], $atts, 'header_search_icon' );

		$class = '';
		$style = ! empty( $atts['style'] ) ? $atts['style'] : 'default';
		$label = self::get_label( $atts );

		if ( ! empty( $atts['class'] ) ) {
			$class .= ' ' . str_replace( '.', '', trim( $atts['class'] ) );
		}

		if ( ! empty( $atts['visibility'] )
			&& function_exists( 'totaltheme_get_visibility_class' )
			&& $visibility_class = totaltheme_get_visibility_class( $atts['visibility'] )
		) {
			$class .= " {$visibility_class}";
		}

		$search_style = totaltheme_call_static( 'Header\Menu\Search', 'style' );

		$aria_map = [
			'drop_down'      => 'searchform-dropdown',
			'modal'          => 'wpex-search-modal',
			'overlay'        => 'wpex-searchform-overlay',
			'header_replace' => 'searchform-header-replace',
		];

		$aria_controls = $aria_map[ $search_style ] ?? '';

		// Begin element output
		$html = '<span class="wpex-header-search-icon wpex-header-search-icon--style-' . sanitize_html_class( $style ) . ' wpex-inline-flex wpex-items-center' . esc_attr( $class ) . '">';

			// Button output
			$button_class      = 'wpex-header-search-icon__button';
			$button_inline_css = '';

			if ( 'modal' === $search_style ) {
				$button_class .= ' wpex-open-modal';
			}

			if ( empty( $atts['style'] ) || 'default' === $atts['style'] ) {
				if ( 'custom_link' === $search_style ) {
					$button_class .= ' wpex-inline-flex wpex-text-current wpex-hover-link-color wpex-no-underline';
				} else {
					$button_class .= ' wpex-unstyled-button wpex-inline-flex wpex-hover-link-color';
				}
			}

			if ( $label ) {
				$aria_label = '';
				$button_class .= ' wpex-flex wpex-items-center';
				if ( ! empty( $atts['label_position'] ) && 'left' === $atts['label_position'] ) {
					$button_class .= ' wpex-flex-row-reverse';
				}
				if ( ! empty( $atts['label_margin'] ) ) {
					if ( is_numeric( $atts['label_margin'] ) ) {
						$atts['label_margin'] = $atts['label_margin'] . 'px';
					}
					$button_inline_css = ' style="gap:' . esc_attr( $atts['label_margin'] ) . ';"';
				} else {
					$button_class .= ' wpex-gap-10';
				}
			} else {
				$aria_label = ! empty( $atts['aria_label'] ) ? sanitize_text_field( $atts['aria_label'] ) : esc_html__( 'Search', 'total-theme-core' );
				$aria_label = ' aria-label="' . esc_attr( $aria_label ) . '"';
			}

			if ( ! empty( $atts['button_class'] ) ) {
				$button_class_safe = sanitize_text_field( $atts['button_class'] );
				if ( $button_class_safe ) {
					$button_class .= " {$button_class_safe}";
					if ( str_contains( $button_class_safe, 'wpex-hover-text-' ) ) {
						$button_class = str_replace( 'wpex-hover-link-color', '', $button_class );
					}
				}
			}

			if ( 'custom_link' === $search_style ) {
				$custom_link = get_theme_mod( 'menu_search_custom_link' ) ?: '#';
				if ( str_starts_with( $custom_link, '/' ) ) {
					$custom_link = home_url( $custom_link );
				}
				$html .= '<a href="' . esc_url( $custom_link ) . '" class="' . esc_attr( trim( $button_class ) ) . '"' . $aria_label . $button_inline_css . '>';
			} else {
				$html .= '<button type="button" class="' . esc_attr( trim( $button_class ) ) . '" aria-expanded="false" aria-controls="' . esc_attr( $aria_controls ) . '"' . $aria_label . $button_inline_css . '>';
			}

				// Display icon
				if ( function_exists( 'totaltheme_get_icon' ) ) {
					$icon = ( $icon = get_theme_mod( 'menu_search_icon' ) ) ? sanitize_text_field( (string) $icon ): 'search';
					$icon_class = 'wpex-header-search-icon__icon wpex-inline-flex wpex-items-center';
					if ( ! empty( $atts['icon_class'] ) ) {
						$icon_class_safe = sanitize_text_field( $atts['icon_class'] );
						if ( $icon_class_safe ) {
							$icon_class .= " {$icon_class_safe}";
						}
					}
					$icon_html = (string) apply_filters( 'wpex_header_search_icon_shortcode_icon_html', totaltheme_get_icon( $icon, 'wpex-flex' ) );
					$html .= '<span class="' . esc_attr( $icon_class ) . '">' . $icon_html . '</span>';
				}

				// Display label
				if ( $label ) {
					$html .= $label;
				}

			$html .= 'custom_link' === $search_style ? '</a>' : '</button>';
		$html .= '</span>';

		return $html;
	}

	/**
	 * Get label.
	 */
	public static function get_label( $atts ) {
		if ( empty( $atts['label'] ) ) {
			return;
		}
		$class = 'wpex-header-search-icon__label';
		if ( ! empty( $atts['label_hide_breakpoint'] ) && in_array( $atts['label_hide_breakpoint'], [ 'sm', 'md', 'lg', 'xl' ], true ) ) {
			$class .= " wpex-hidden wpex-{$atts['label_hide_breakpoint']}-inline";
		}
		if ( ! empty( $atts['label_class'] ) ) {
			$label_class_safe = sanitize_text_field( $atts['label_class'] );
			if ( $label_class_safe ) {
				$class .= " {$label_class_safe}";
			}
		}
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $atts['label'] ) . '</span>';
	}

}
