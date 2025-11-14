<?php

/**
 * vcex_icon_box shortcode output.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

// Sanitize data & declare main vars
$output = '';
$has_lightbox = false;
$el_tag_safe = 'div';
$clickable_boxes = [ 'four', 'five', 'six' ];
$style = ! empty( $atts['style'] ) ? sanitize_text_field( $atts['style'] ) : 'one';
$has_side_icon = in_array( $style, [ 'one', 'seven' ], true );
$has_top_icon = in_array( $style, [ 'two', 'three', 'four', 'five', 'six', 'eight' ], true );
$has_bottom_icon = ( 'nine' === $style ); // technically 8 does also but we use col-reverse for that style
$transition_duration = ! empty( $atts['transition_duration'] ) ? absint( $atts['transition_duration'] ) : '';
$heading = ! empty( $atts['heading'] ) ? vcex_parse_text( $atts['heading'] ) : '';
$url_wrap = ( in_array( $style, $clickable_boxes, true ) || vcex_validate_att_boolean( 'url_wrap', $atts ) ) && ! str_contains( $content, '<a' );
$icon_spacing = ! empty( $atts['icon_spacing'] ) ? absint( $atts['icon_spacing'] ) : '20';
$stack_bk_safe = ! empty( $atts['stack_bk'] ) ? sanitize_html_class( $atts['stack_bk'] ) : false;
$show_button = vcex_validate_att_boolean( 'show_button', $atts );
$image_source = ! empty( $atts['image_source'] ) ? sanitize_text_field( $atts['image_source'] ) : 'media_library';
$image = vcex_get_image_from_source( $image_source, $atts );
$icon_alt_classes = ! empty( $atts['icon_alternative_classes'] ) ? sanitize_text_field( $atts['icon_alternative_classes'] ) : '';
$icon_alt_char = ! empty( $atts['icon_alternative_character'] ) ? sanitize_text_field( $atts['icon_alternative_character'] ) : '';

// Get icon html
if ( $image
	|| $icon_alt_classes
	|| $icon_alt_char
	|| 'custom_field' === $image_source // even if custom field image is empty.
	|| 'featured' === $image_source // even if featured image is empty.
) {
	$icon_html = '';
} else {
	$icon_class = 'wpex-flex';
	if ( $has_side_icon && empty( $atts['icon_width'] ) && empty( $atts['icon_background'] ) ) {
		$icon_class .= ' wpex-icon--w';
	}
	$icon_html = vcex_get_icon_html( $atts, 'icon', $icon_class );
}

// Check if the element has an icon
$has_icon = ( $icon_html || $icon_alt_classes || $icon_alt_char );

// Get shortcode link attributes early
$onclick_attrs = vcex_get_shortcode_onclick_attributes( $atts, 'vcex_icon_box' );
$url = ! empty( $onclick_attrs['href'] ) ? $onclick_attrs['href'] : '';

// Define main wrap attributes
$wrap_attrs = [
	'id'    => $atts['unique_id'] ?? '',
	'class' => '',
];

$wrap_class = [
	'vcex-module',
	'vcex-icon-box',
	"vcex-icon-box-{$style}",
];

if ( vcex_validate_att_boolean( 'hover_white_text', $atts, false ) ) {
	$wrap_class[] = 'vcex-icon-box-hover-text-white';
}

if ( ! empty( $url ) && $url_wrap ) {
	$el_tag_safe = 'a';
	$wrap_class[] = 'vcex-icon-box-has-link';
	if ( 'six' !== $style ) {
		$wrap_class[] = 'wpex-inherit-color';
	}
	$wrap_class[] = 'wpex-no-underline';
	if ( is_array( $onclick_attrs ) ) {
		if ( ! empty( $onclick_attrs['class'] ) && is_array( $onclick_attrs['class'] ) ) {
			$wrap_class = array_merge( $wrap_class, $onclick_attrs['class'] );
		}
		unset( $onclick_attrs['class'] );
		$wrap_attrs = array_merge( $wrap_attrs, $onclick_attrs );
	}
}

// Float classes applied only when a custom width is added
if ( ! empty( $atts['width'] ) ) {
	$wrap_class['class'] = 'wpex-max-w-100';
	$wrap_class[] = vcex_parse_align_class( ! empty( $atts['float'] ) ? $atts['float'] : 'center' );
}

// Flex styles
if ( $has_side_icon ) {
	if ( $stack_bk_safe ) {
		$wrap_class[] = "wpex-flex wpex-flex-col wpex-{$stack_bk_safe}-flex-row";
		$wrap_class[] = "wpex-gap-{$icon_spacing}";
	} else {
		$wrap_class[] = 'wpex-flex';
	}
	if ( vcex_validate_att_boolean( 'align_center', $atts ) ) {
		if ( $stack_bk_safe ) {
			$wrap_class[] = "wpex-{$stack_bk_safe}-items-center";
		} else {
			$wrap_class[] = 'wpex-items-center';
		}
	}
}

// Bottom Icon
if ( 'eight' === $style ) {
	$wrap_class[] = 'wpex-flex';
	$wrap_class[] = 'wpex-flex-col';
	$wrap_class[] = 'wpex-flex-col-reverse';
} elseif ( $has_bottom_icon ) {
	$wrap_class[] = 'wpex-flex';
	$wrap_class[] = 'wpex-flex-col';
}

// Add block class if flex isn't added to prevent issues with URL wrap
if ( ! in_array( 'wpex-flex', $wrap_class, true ) ) {
	if ( $has_top_icon && ! empty( $atts['icon_top_margin'] ) ) {
		$wrap_class[] = 'wpex-flow-root'; // prevent's collapsing!
	} else {
		$wrap_class[] = 'wpex-block';
	}
}

// Old CSS check
if ( empty( $atts['css'] ) && ! empty( $atts['background_image'] ) && in_array( $style, $clickable_boxes, true ) ) {
	$wrap_class[] = 'wpex-bg-cover';
}

// No icon class
if ( ! $icon_html && ! $image && ! $icon_alt_classes && ! $icon_alt_char ) {
	$wrap_class[] = 'vcex-icon-box-wo-icon';
}

// Add flex when button is enabled if flex not already added
if ( $url && $show_button && ! in_array( 'wpex-flex', $wrap_class, true ) ) {
	if ( false !== ( $key = array_search( 'wpex-block', $wrap_class ) ) ) {
    	unset( $wrap_class[ $key ] );
	}
	$wrap_class[] = 'wpex-flex wpex-flex-col';
}

// Shadow
if ( ! empty( $atts['shadow'] ) ) {
	$wrap_class[] = sanitize_html_class( "wpex-{$atts['shadow']}" );
}

// Shadow: Hover
if ( ! empty( $atts['shadow_hover'] ) ) {
	$wrap_class[] = sanitize_html_class( "wpex-hover-{$atts['shadow_hover']}" );
	if ( empty( $atts['hover_animation'] ) && empty( $atts['hover_background'] ) && empty( $atts['transition_duration'] ) ) {
		$wrap_class[] = 'wpex-transition-shadow';
		$wrap_class[] = 'wpex-duration-300';
	}
}

// Transition duration
if ( $transition_duration && empty( $atts['hover_animation'] ) ) {
	$wrap_class[] = "wpex-duration-{$transition_duration}";
}

// Bottom Margin
if ( ! empty( $atts['bottom_margin'] ) ) {
	$wrap_class[] = vcex_parse_margin_class( $atts['bottom_margin'], 'bottom' );
}

// Padding
if ( ! empty( $atts['padding_y'] ) ) {
	$wrap_class[] = vcex_parse_padding_class( $atts['padding_y'], 'block' );
}

if ( ! empty( $atts['padding_x'] ) ) {
	$wrap_class[] = vcex_parse_padding_class( $atts['padding_x'], 'inline' );
}

// Custom text align for Top/Bottom Icon only
if ( $atts['alignment'] && ( 'two' === $style || 'eight' === $style || 'nine' === $style ) ) {
	$wrap_class[] = vcex_parse_text_align_class( $atts['alignment'] );
}

// Default alignments
else {
	switch ( $style ) {
		case 'one':
			$wrap_class[] = 'wpex-text-left';
			break;
		case 'seven':
			$wrap_class[] = 'wpex-text-right';
			break;
		default:
			if ( $has_top_icon ) {
				$wrap_class[] = 'wpex-text-center';
			}
			break;
	}
}

if ( ! empty( $atts['visibility'] ) ) {
	$wrap_class[] = vcex_parse_visibility_class( $atts['visibility'] );
}

// Style specific classes
switch ( $style ) {

	// Right Icon
	case 'seven':
		if ( $stack_bk_safe ) {
			$wrap_class[] = "wpex-{$stack_bk_safe}-flex-row-reverse";
		} else {
			$wrap_class[] = 'wpex-flex-row-reverse';
		}
		break;
	// Top Icon Bordered
	case 'four':
		if ( empty( $atts['wpex_padding'] ) ) {
			$wrap_class[] = 'wpex-p-30';
		}
		$wrap_class[] = 'wpex-bordered';
		break;
	// Top Icon w Gray Background
	case 'five':
		if ( empty( $atts['wpex_padding'] ) ) {
			$wrap_class[] = 'wpex-p-30';
		}
		$wrap_class[] = 'wpex-surface-2';
		break;
	// Black background
	case 'six':
		$wrap_class[] = 'wpex-bg-black';
		$wrap_class[] = 'wpex-text-white';
		$wrap_class[] = 'wpex-hover-text-white';
		$wrap_class[] = 'wpex-child-inherit-color';
		if ( empty( $atts['wpex_padding'] ) ) {
			$wrap_class[] = 'wpex-p-30';
		}
		break;
}

if ( ! empty( $atts['border_width'] ) ) {
	$wrap_class[] = vcex_parse_border_width_class( $atts['border_width'] );
	if ( 'four' !== $style ) {
		$wrap_class[] = 'wpex-p-20';
		$wrap_class[] = 'wpex-border-solid';
	}
}

if ( ! empty( $atts['border_style'] ) ) {
	// added outside of border width check because it's also needed for style four
	$wrap_class[] = vcex_parse_border_style_class( $atts['border_style'] );
}

if ( ! empty( $atts['hover_animation'] ) ) {
	$wrap_class[] = vcex_hover_animation_class( $atts['hover_animation'] );
}

if ( ! empty( $atts['css_animation'] ) ) {
	$wrap_class[] = vcex_get_css_animation( $atts['css_animation'] );
}

// Add Design Options CSS class to proper container
if ( ! empty( $atts['css'] ) ) {
	$wrap_class[] = vcex_vc_shortcode_custom_css_class( $atts['css'] );
}

// Add custom classes last
if ( ! empty( $atts['classes'] ) ) {
	$wrap_class[] = vcex_get_extra_class( $atts['classes'] );
}

// Make sure classes are unique
$wrap_class = array_unique( $wrap_class );

// Apply filters to wrap class and add to wrap_attrs
$wrap_attrs['class'] = vcex_parse_shortcode_classes( $wrap_class, 'vcex_icon_box', $atts );

/*-------------------------------------------------------------------------------*/
/* [ Output Starts here ]
/*-------------------------------------------------------------------------------*/
$el_attrs_string = vcex_parse_html_attributes( $wrap_attrs );

$output .= "<{$el_tag_safe}{$el_attrs_string}>";

	$symbol_html = '';

	/*-------------------------------------------------------------------------------*/
	/* [ Container for Icon/Image ]
	/*-------------------------------------------------------------------------------*/
	if ( $image || $has_icon ) {

		$symbol_classes = [
			'vcex-icon-box-symbol',
		];

		if ( $image ) {
			$symbol_classes[] = 'vcex-icon-box-symbol--image';
		} else {
			$symbol_classes[] = 'vcex-icon-box-symbol--icon';
		}

		// Prevent flex shrink on side icon styles
		if ( $has_side_icon ) {
			$symbol_classes[] = 'wpex-flex-shrink-0';
		}

		// Add icon spacing
		if ( $icon_spacing ) {
			switch ( $style ) {
				case 'one':
					if ( $stack_bk_safe ) {
						$symbol_classes[] = 'wpex-mr-auto';
					} else {
						$symbol_classes[] = "wpex-mr-{$icon_spacing}";
					}
					break;
				case 'seven':
					if ( $stack_bk_safe ) {
						$symbol_classes[] = 'wpex-ml-auto';
					} else {
						$symbol_classes[] = "wpex-ml-{$icon_spacing}";
					}
					break;
				default:
					if ( $has_top_icon ) {
						if ( 'eight' === $style ) {
							$symbol_classes[] = "wpex-mt-{$icon_spacing}";
						} else {
							$symbol_classes[] = "wpex-mb-{$icon_spacing}";
						}
					} elseif ( $has_bottom_icon ) {
						$symbol_classes[] = "wpex-my-{$icon_spacing}";
					}
					break;
			}
		}

		// Apply filters to classes
		$symbol_classes = (array) apply_filters( 'vcex_icon_box_symbol_class', $symbol_classes );

		// Open .vcex-icon-box-symbol element
		$symbol_html .= '<div class="' . esc_attr( implode( ' ', $symbol_classes ) )  . '">';

		$symbol_link = (string) apply_filters( 'vcex_icon_box_symbol_link', $url, $atts );

		// Add link to symbol
		if ( $symbol_link && ! $url_wrap && ! $show_button ) {
			if ( $symbol_link === $onclick_attrs['href'] ) {
				$symbol_link_attrs = $onclick_attrs;
			}
			$symbol_link_attrs['href'] = esc_url( $symbol_link );
			$symbol_link_attrs['class'][] = 'wpex-no-underline';
			$symbol_html .= '<a' . vcex_parse_html_attributes( $symbol_link_attrs ) . '>';
		}

	}

	/*-------------------------------------------------------------------------------*/
	/* [ Image ]
	/*-------------------------------------------------------------------------------*/
	if ( $image ) {
		$resize_image = vcex_validate_att_boolean( 'resize_image', $atts, true );

		$image_classes = [
			'vcex-icon-box-image',
			'wpex-align-middle',
		];

		if ( ! empty( $atts['image_shadow'] ) ) {
			$image_classes[] = vcex_parse_shadow_class( $atts['image_shadow'] );
		}

		if ( apply_filters( 'vcex_icon_box_image_auto_alt', false ) && $heading ) {
			$image_alt = wp_strip_all_tags( $heading );
		} else {
			$image_alt = vcex_get_attachment_data( $image, 'alt' );
		}

		if ( ! empty( $atts['image_fit'] ) && ! $resize_image ) {
			$image_classes[] = 'wpex-object-' . sanitize_html_class( $atts['image_fit'] );
		}

		// Image with custom resizing
		if ( $resize_image && is_numeric( $image ) ) {

			$thumbnail_args = [
				'size'       => 'wpex-custom',
				'attachment' => $image,
				'alt'        => $image_alt,
				'width'      => $atts['image_width'] ?? '',
				'height'     => $atts['image_height'] ?? '',
				'crop'       => 'center-center',
				'class'      => array_filter( $image_classes ),
			];

			if ( ! vcex_validate_att_boolean( 'img_lazy_load', $atts, true ) ) {
				$thumbnail_args['lazy'] = false;
			}

			if ( ! empty( $atts['img_fetchpriority'] ) && 'auto' !== $atts['img_fetchpriority'] ) {
				$thumbnail_args['attributes']['fetchpriority'] = esc_attr( $atts['img_fetchpriority'] );
			}

			$symbol_html .= vcex_get_post_thumbnail( $thumbnail_args );

		}

		// Image with inline sizing
		else {

			$img_args = [
				'src'   => '',
				'alt'   => $image_alt,
				'class' => array_filter( $image_classes ),
			];

			if ( ! empty( $atts['img_fetchpriority'] ) && 'auto' !== $atts['img_fetchpriority'] ) {
				$img_args['fetchpriority'] = esc_attr( $atts['img_fetchpriority'] );
			}

			if ( vcex_validate_att_boolean( 'img_lazy_load', $atts, true ) ) {
				$img_args['loading'] = 'lazy';
				$img_args['decoding'] = 'async';
			}

			if ( is_numeric( $image ) ) {
				$image_src = wp_get_attachment_image_src( $image, 'full' );
				if ( ! empty( $image_src[0] ) ) {
					$img_args['src'] = esc_url( $image_src[0] );
				}

				$attachment_mime_type = get_post_mime_type( $image );
				if ( 'image/svg+xml' === $attachment_mime_type ) {
					if ( ! empty( $atts['image_width'] ) ) {
						$img_args['width'] = absint( $atts['image_width'] );
					}
					if ( ! empty( $atts['image_height'] ) ) {
						$img_args['height'] = absint( $atts['image_height'] );
					}
				} else {
					if ( ! empty( $image_src[1] ) && 0 !== absint( $image_src[1] ) ) {
						$img_args['width'] = $image_src[1];
					}
					if ( ! empty( $image_src[2] ) && 0 !== absint( $image_src[1] ) ) {
						$img_args['height'] = $image_src[2];
					}
				}
			} elseif ( is_string( $image ) ) {
				$img_args['src'] = esc_url( $image );
			}

			if ( ! empty( $img_args['src'] ) ) {
				$symbol_html .= '<img' . vcex_parse_html_attributes( $img_args ) . '>';
			}

		}

	}

	/*-------------------------------------------------------------------------------*/
	/* [ Icon ]
	/*-------------------------------------------------------------------------------*/
	elseif ( $has_icon ) {

		// Define icon attributes
		$icon_classes = [
			'vcex-icon-box-icon',
			'wpex-items-center',
			'wpex-justify-center',
			'wpex-child-inherit-color',
			'wpex-text-center',
			'wpex-leading-none',
		];

		if ( $transition_duration ) {
			$icon_classes[] = "wpex-duration-{$transition_duration}";
		}

		if ( $has_side_icon ) {
			$icon_classes[] = 'wpex-flex'; // inline-flex can cause some extra space at top from line-height
			if ( ! empty( $atts['icon_width'] ) ) {
				$icon_classes[] = 'wpex-w-100';
			}
		} else {
			$icon_classes[] = 'wpex-inline-flex';
		}

		// Add default icon color
		if ( 'six' !== $style ) {
			$icon_classes[] = 'wpex-text-1';
		}

		// Icon border
		if ( ! empty( $atts['icon_border_width'] ) && $icon_border_width_class = vcex_parse_border_width_class( $atts['icon_border_width'] ) ) {
			$icon_classes[] = 'wpex-border-solid';
			$icon_classes[] = $icon_border_width_class;
			if ( empty( $atts['icon_width'] ) && empty( $atts['icon_height'] ) ) {
				$icon_classes[] = 'wpex-p-15';
			}
		}

		// Icon shadow
		if ( ! empty( $atts['icon_shadow'] ) ) {
			$icon_classes[] = vcex_parse_shadow_class( $atts['icon_shadow'] );
		}

		// Icon alt character classes
		if ( $icon_alt_char ) {
			if ( ! empty( $atts['icon_font_weight'] ) && $icon_font_weight_class = vcex_parse_font_weight_class( $atts['icon_font_weight'] ) ) {
				$icon_classes[] = $icon_font_weight_class;
			} else {
				$icon_classes[] = 'wpex-font-semibold';
			}
		}

		// Add padding when custom height is empty so custom backgrounds look ok
		if ( empty( $atts['icon_height'] ) && ! empty( $atts['icon_background'] ) ) {
			$icon_classes[] = 'wpex-p-15';
		}

		// Remove empty and classes
		$icon_classes = array_unique( array_filter( $icon_classes ) );

		// Apply filters to icon classes
		$icon_classes = (array) apply_filters( 'vcex_icon_box_icon_class', $icon_classes );

		// Display Icon
		$symbol_html .= '<div class="' . esc_attr( implode( ' ', $icon_classes ) ) . '">';
			if ( $icon_alt_classes ) {
				$symbol_html .= '<span class="' . esc_attr( do_shortcode( $icon_alt_classes ) ) . '" aria-hidden="true"></span>';
			} elseif ( $icon_alt_char ) {
				$symbol_html .= vcex_parse_text_safe( $icon_alt_char );
			} else {
				$symbol_html .= $icon_html;
			}
		$symbol_html .= '</div>';
	}

	// Close symbol link
	if ( ! empty( $symbol_link ) && ! $url_wrap && ! $show_button ) {
		$symbol_html .= '</a>';
	}

	// Close symbol div (icon/image)
	if ( $image || $has_icon ) {
		$symbol_html .= '</div>';
	}

	if ( ! $has_bottom_icon ) {
		$output .= $symbol_html;
	}

	/*-------------------------------------------------------------------------------*/
	/* [ Container for Heading + Content ]
	/*-------------------------------------------------------------------------------*/

	$text_classes = [
		'vcex-icon-box-text'
	];

	if ( $has_side_icon || ( $url && $show_button ) ) {
		$text_classes[] = 'wpex-flex-grow';
	}

	if ( $url && $show_button ) {
		$text_classes[] = 'wpex-flex';
		$text_classes[] = 'wpex-flex-col';
	}

	$output .= '<div class="' . esc_attr( implode( ' ', $text_classes ) )  . '">';

		/*-------------------------------------------------------------------------------*/
		/* [ Heading ]
		/*-------------------------------------------------------------------------------*/
		if ( $heading ) {
			$has_heading_link = ( $url && ! $url_wrap && ! $show_button );

			$heading_tag = ! empty( $atts['heading_type'] ) ? $atts['heading_type'] : apply_filters( 'vcex_icon_box_heading_default_tag', 'h2' );
			$heading_tag_escaped = tag_escape( $heading_tag );

			$heading_class = [
				'vcex-icon-box-heading',
			];

			if ( $transition_duration ) {
				$heading_class[] = "wpex-duration-{$transition_duration}";
			}

			if ( ! empty( $atts['heading_typography_style'] ) ) {
				$heading_class[] = vcex_parse_typography_style_class( $atts['heading_typography_style'] );
				$heading_class[] = 'wpex-m-0';
				if ( 'span' === $heading_tag_escaped ) {
					$heading_class[] = 'wpex-block';
				}
			} else {
				$heading_class[] = 'wpex-heading';
				if ( empty( $atts['heading_size'] ) ) {
					$heading_class[] = vcex_has_classic_styles() ? 'wpex-text-md' : 'wpex-text-lg';
				}
				if ( empty( $atts['heading_bottom_margin'] ) ) {
					$heading_class[] = 'wpex-mb-10';
				}
			}

			if ( 'six' === $style ) {
				$heading_class[] = ! empty( $atts['heading_color'] ) ? 'wpex-inherit-color' : 'wpex-inherit-color-important';
			}

			if ( $has_heading_link && ! empty( $atts['heading_color'] ) ) {
				$heading_class[] = 'wpex-child-inherit-color';
			}

			$heading_class = (array) apply_filters( 'vcex_icon_box_heading_class', $heading_class );
			$heading_attrs = (array) apply_filters( 'vcex_icon_box_heading_attrs', [ 'class' => $heading_class ], $atts );

			// Begin heading output
			$output .= '<' . $heading_tag_escaped . vcex_parse_html_attributes( $heading_attrs ) . '>';

				// Open heading link
				if ( $has_heading_link ) {
					$link_attrs = $onclick_attrs;
					$link_attrs['class'][] = 'vcex-icon-box-link';
					$link_attrs['class'][] = 'wpex-no-underline';
					$output .= '<a' . vcex_parse_html_attributes( $link_attrs ) . '>';
				}

				// Heading text
				$output .= wp_kses_post( $heading );

				// Badge
				if ( ! empty( $atts['heading_badge'] ) && $heading_badge_safe = vcex_parse_text_safe( $atts['heading_badge'] ) ) {
					$output .= ' <span class="wpex-badge">' . $heading_badge_safe . '</span>';
				}

				// Close link around heading and icon
				if ( $has_heading_link ) {
					$output .= '</a>';
				}

			$output .= '</' . $heading_tag_escaped . '>';

		} // End heading

		/*-------------------------------------------------------------------------------*/
		/* [ Content ]
		/*-------------------------------------------------------------------------------*/
		if ( $content ) {

			$content_class = [
				'vcex-icon-box-content',
				'wpex-last-mb-0',
				'wpex-clr',
			];

			if ( $transition_duration ) {
				$content_class[] = "wpex-duration-{$transition_duration}";
			}

			if ( empty( $atts['heading_bottom_margin'] ) && ! empty( $atts['heading_typography_style'] ) ) {
				$content_class[] = 'wpex-mt-10';
			}

			if ( $url && $show_button && ! $has_bottom_icon ) {
				$content_class[] = 'wpex-mb-20';
			}

			// Define content attributes
			$content_attrs = [
				'class' => apply_filters( 'vcex_icon_box_content_class', $content_class ),
			];

			// Content output
			$output .= '<div' . vcex_parse_html_attributes( $content_attrs ) . '>';
				$output .= vcex_the_content( $content );
			$output .= '</div>';

		}

		/*-------------------------------------------------------------------------------*/
		/* [ Button ]
		/*-------------------------------------------------------------------------------*/
		if ( $url && $show_button ) {
			$button_html = '';
			$button_html .= '<div class="vcex-icon-box-button-wrap wpex-mt-auto">';
				$button_class = 'vcex-icon-box-button';
				$button_class .= ' ' . vcex_get_button_classes( [
					'style' => ! empty( $atts['button_style'] ) ? sanitize_text_field( $atts['button_style'] ) : '',
					'color' => ! empty( $atts['button_style_color'] ) ? sanitize_text_field( $atts['button_style_color'] ) : '',
				] );
				if ( ! empty( $atts['button_class'] ) && is_string( $atts['button_class'] ) ) {
					$button_class .= ' ' . wp_strip_all_tags( $atts['button_class'] );
				}
				$button_text_safe = ! empty( $atts['button_text'] ) ? vcex_parse_text_safe( $atts['button_text'] ) : esc_html__( 'Learn more', 'total-theme-core' );
				if ( ! empty( $atts['button_hover_background'] ) ) {
					$btn_hover_bg = $atts['button_hover_background'];
				}
				if ( ! empty( $atts['button_hover_color'] ) ) {
					$btn_hover_color = $atts['button_hover_color'];
				}
				if ( ! empty( $atts['button_icon'] ) ) {
					$button_icon_html = vcex_render_icon( $atts['button_icon'], 'ticons' );
					if ( $button_icon_html ) {
						$button_placement = ( ! empty( $atts['button_icon_placement'] ) && 'start' === $atts['button_icon_placement'] ) ? 'start' : 'end';
						$button_icon_class = 'vcex-icon-box-button__icon theme-button-icon-' . ( 'start' === $button_placement ? 'left' : 'right' );
						$button_icon_html  = "<span class=\"{$button_icon_class}\">{$button_icon_html}</span>";
						if ( 'start' === $button_placement ) {
							$button_text_safe = $button_icon_html . $button_text_safe;
						} else {
							$button_text_safe .= $button_icon_html;
						}
					}
				}
				if ( $url_wrap ) {
					$button_html .= '<span class="' . esc_attr( trim(  $button_class ) ) . '">' . $button_text_safe . '</span>';
				} elseif ( ! empty( $onclick_attrs ) ) {
					$link_attrs = $onclick_attrs;
					$link_attrs['class'][] = $button_class;
					$button_html .= '<a' . vcex_parse_html_attributes( $link_attrs ) . '>' . $button_text_safe . '</a>';
				}
			$button_html .= '</div>';
			if ( ! $has_bottom_icon ) {
				$output .= $button_html;
			}
		}

	// Close heading/text wrapper
	$output .= '</div>';

	// Insert button and icon for style 9
	if ( $has_bottom_icon ) {
		if ( isset( $symbol_html ) ) {
			$output .= $symbol_html;
		}
		if ( isset( $button_html ) ) {
			$output .= $button_html;
		}
	}

// Close Icon Box element
$output .= "</{$el_tag_safe}>";

// @codingStandardsIgnoreLine
echo $output;
