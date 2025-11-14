<?php

/**
 * vcex_feature_box shortcode output.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

// Define main vars
$output         = '';
$unique_class   = $atts['vcex_class'] ?? vcex_element_unique_classname();
$style          = ! empty( $atts['style'] ) ? sanitize_text_field( $atts['style'] ) : 'left-content-right-image';
$has_bleed      = vcex_validate_att_boolean( 'media_bleed', $atts );
$image          = vcex_get_image_from_source( $atts['image_source'] ?? 'media_library', $atts );
$video          = ! empty( $atts['video'] ) ? sanitize_text_field( $atts['video'] ) : '';
$media_align    = ! empty( $atts['media_align'] ) ? sanitize_text_field( $atts['media_align'] ) : '';
$justify        = ( ! $has_bleed && ! empty( $atts['justify'] ) ) ? sanitize_text_field( $atts['justify'] ) : 'space-between';
$align_center   = vcex_validate_att_boolean( 'content_vertical_align', $atts );
$breakpoint     = $atts['breakpoint'] ?? '';
$has_custom_bk  = $breakpoint && ! in_array( $breakpoint, [ 'sm', 'md', 'lg', 'xl' ], true );
$reverse_layout = 'left-content-right-image' === $style;
$equal_heights  = $has_bleed || ( vcex_validate_att_boolean( 'equal_heights', $atts ) && ! $video );

// Use placeholder if image isn't set
if ( ! $image ) {
	$image = 'placeholder';
}

// Default media align
if ( ! $media_align || ! in_array( $media_align, [ 'left', 'center', 'right' ], true ) ) {
	$media_align = $reverse_layout ? 'right' : 'left';
}

// Get onclick attributes
if ( ! empty( $atts['onclick'] ) ) {
	$onclick_attrs = vcex_get_shortcode_onclick_attributes( $atts, 'vcex_feature_box' );
} else {
	$onclick_attrs = [];
}

// Check if the container link has been enabled
$has_container_link = false;

if ( ! empty( $onclick_attrs['href'] ) && ! empty( $atts['onclick_el'] ) && 'container' === $atts['onclick_el'] ) {
	$has_container_link = true;
}

// Classes
$wrap_class = [
	'vcex-module',
	'vcex-feature-box',
];

if ( $has_bleed ) {
	$wrap_class[] = 'vcex-feature-box--has-bleed';
}

if ( $equal_heights ) {
	$wrap_class[] = 'vcex-feature-box--eq-height';
}

if ( $breakpoint ) {
	$wrap_class[] = 'vcex-feature-box--stackable';
	if ( $has_custom_bk ) {
		$wrap_class[] = 'wpex-flex';
		$wrap_class[] = 'wpex-flex-col';
	} else {
		$wrap_class[] = 'wpex-flex';
		$wrap_class[] = 'wpex-flex-col';
		if ( $reverse_layout ) {
			$wrap_class[] =  sanitize_html_class( "wpex-{$breakpoint}-flex-row-reverse" );
		} else {
			$wrap_class[] = sanitize_html_class( "wpex-{$breakpoint}-flex-row" );
		}
	}
} else {
	$wrap_class[] = 'wpex-flex';
	if ( $reverse_layout ) {
		$wrap_class[] = 'wpex-flex-row-reverse';
	}
}

if ( $justify ) {
	$wrap_class[] = vcex_parse_justify_content_class( $justify );
}

if ( ! $has_bleed && empty( $atts['content_background'] ) && empty( $atts['gap'] ) ) {
	$wrap_class[] = 'wpex-gap-30';
}

if ( ! empty( $atts['bottom_margin'] ) ) {
	$wrap_class[] = vcex_parse_margin_class( $atts['bottom_margin'], 'bottom' );
}

if ( ! empty( $atts['shadow'] ) ) {
	$wrap_class[] = vcex_parse_shadow_class( $atts['shadow'] );
}

if ( ! empty( $atts['shadow_hover'] ) ) {
	$wrap_class[] = 'wpex-hover-' . sanitize_html_class( $atts['shadow_hover'] );
	if ( empty( $atts['hover_animation'] ) ) {
		$wrap_class[] = 'wpex-transition-shadow';
		$wrap_class[] = 'wpex-duration-300';
	}
}

if ( ! empty( $atts['hover_animation'] ) ) {
	$wrap_class[] = vcex_hover_animation_class( $atts['hover_animation'] );
}

if ( ! empty( $atts['visibility'] ) ) {
	$wrap_class[] = vcex_parse_visibility_class( $atts['visibility'] );
}

if ( ! empty( $atts['css_animation'] ) && 'none' !== $atts['css_animation'] ) {
	$wrap_class[] = vcex_get_css_animation( $atts['css_animation'] );
}

if ( ! empty( $atts['classes'] ) ) {
	$wrap_class[] = vcex_get_extra_class( $atts['classes'] );
}

if ( ! empty( $atts['style'] ) ) {
	$wrap_class[] = sanitize_html_class( $atts['style'] );
}

// Add old breakpoint classes (pre 1.4.3) - @deprecated since 6.4
if ( ! empty( $atts['stack_bk'] )
	&& in_array( $atts['stack_bk'], [ 'md', 'custom' ], true )
	&& vcex_theme_version_check( 'initial', '6.4', '<' )
) {
	switch ( $atts['stack_bk'] ) {
		case 'md':
			$wrap_class[] = 'vcex-phone-collapse';
			break;
		case 'custom':
			if ( ! empty( $atts['custom_stack_bk'] ) && '960px' === $atts['custom_stack_bk'] ) {
				$wrap_class[] = 'vcex-tablet-collapse';
			}
			break;
	}
}

if ( $align_center ) {
	if ( vcex_theme_version_check( 'initial', '6.4', '<' ) ) {
		$wrap_class[] = 'v-align-middle'; // old class
	}
	if ( $breakpoint && ! $has_custom_bk ) {
		$wrap_class[] = "wpex-{$breakpoint}-items-center";
	} else {
		if ( ! $has_custom_bk ) {
			$wrap_class[] = 'wpex-items-center';
		}
	}
}

// Parse shortcode class
$wrap_class = vcex_parse_shortcode_classes( $wrap_class, 'vcex_feature_box', $atts );

// Begin shortcode output
if ( $has_container_link && ! empty( $onclick_attrs['href'] ) ) {
	$onclick_attrs['class'][] = 'wpex-inherit-color wpex-no-underline';
	$onclick_attrs['class'][] = $wrap_class;
	$output .= '<a' . vcex_parse_html_attributes( $onclick_attrs );
} else {
	$output .= '<div class="' . esc_attr( $wrap_class ) . '"';
}

$output .= vcex_get_unique_id( $atts['unique_id'] ) . '>'; // close opening element

	// Image/Video check
	if ( $image || $video ) {
		$media_classes = [
			'vcex-feature-box-media',
		];

		if ( ! $has_bleed ) {
			$media_width = ! empty( $atts['media_width'] ) ? sanitize_text_field( $atts['media_width'] ) : '50%';
			if ( ! $has_custom_bk && ( ! $media_width || '50%' === $media_width ) ) {
				if ( $breakpoint ) {
					$media_classes[] = "wpex-{$breakpoint}-w-50";
				} else {
					$media_classes[] = 'wpex-w-50';
				}
			}
		}

		if ( $equal_heights || ( $has_bleed && $video ) ) {
			$media_classes[] = 'wpex-relative';
			$media_classes[] = 'wpex-self-stretch';
			$media_classes[] = 'wpex-overflow-hidden';
		}

		if ( ! $video && $media_align && ! $has_custom_bk ) {
			if ( $breakpoint ) {
				$media_classes[] = "wpex-{$breakpoint}-text-{$media_align}";
			} else {
				$media_classes[] = "wpex-text-{$media_align}";
			}
		}

		// Media style
		$output .= '<div class="' . esc_attr( implode( ' ', $media_classes ) ) . '">';

			// Display Video
			if ( $video ) {
				$video_html = '';

				if ( apply_filters( 'wpex_has_oembed_cache', true ) ) {
					global $wp_embed;
					if ( $wp_embed && is_object( $wp_embed ) ) {
						$video_html = $wp_embed->shortcode( [], $video );
						// Check if output is a shortcode because if the URL is self hosted
						// it will pass through wp_embed_handler_video which returns a video shortcode
						if ( $video_html && is_string( $video_html ) && str_contains( $video_html, '[video' ) ) {
							$video_html = do_shortcode( $video_html );
						}
					}
				} else {
					$video_html = wp_oembed_get( $video );
				}

				if ( $video_html && ! is_wp_error( $video_html ) ) {
					if ( ! str_contains( $video_html, 'wpex-responsive-media' ) ) {
						$video_html = '<div class="wpex-responsive-media">' . $video_html . '</div>';
					}
					if ( $has_bleed ) {
						// The video should probably always do this or we should add a stretch option
						// but it's def needed for bleed always
						$video_html = str_replace(
							'wpex-responsive-media',
							'wpex-responsive-media wpex-w-100 wpex-h-100 wpex-object-cover',
							$video_html
						);
					}
					$output .= $video_html;
				}

			}

			// Display Image
			elseif ( $image ) {

				$thumbnail_args = [
					'attachment' => $image,
					'size'       => $atts['img_size'] ?? 'full',
					'width'      => $atts['img_width'] ?? '',
					'height'     => $atts['img_height'] ?? '',
					'crop'       => $atts['img_crop'] ?? '',
					'class'      => [ 'wpex-align-middle' ],
				];

				if ( ! vcex_validate_att_boolean( 'img_lazy_load', $atts, true ) ) {
					$thumbnail_args['lazy'] = false;
				}

				if ( ! empty( $atts['img_fetchpriority'] ) && 'auto' !== $atts['img_fetchpriority'] ) {
					$thumbnail_args['attributes']['fetchpriority'] = esc_attr( $atts['img_fetchpriority'] );
				}

				// Image classes
				$image_classes = [
					'vcex-feature-box-image',
					'wpex-relative', // used for overlays
				];

				if ( $equal_heights ) {
					$image_classes[] = 'wpex-w-100 wpex-h-100'; // otherwise the image won't stretch
				}

				if ( ! empty( $atts['img_filter'] ) ) {
					$image_classes[] = vcex_image_filter_class( $atts['img_filter'] );
				}

				if ( ! empty( $atts['img_hover_style'] ) ) {
					$image_classes[] = vcex_image_hover_classes( $atts['img_hover_style'] );
				}

				if ( $equal_heights ) {
					$thumbnail_args['class'][] = 'wpex-block wpex-w-100 wpex-h-100';
					if ( empty( $atts['img_object_fit'] ) ) {
						$atts['img_object_fit'] = 'cover';
					}
				}

				if ( ! empty( $atts['img_aspect_ratio'] ) ) {
					$thumbnail_args['class'][] = vcex_parse_aspect_ratio_class( $atts['img_aspect_ratio'] );
				}

				if ( ! empty( $atts['img_object_fit'] ) ) {
					$thumbnail_args['class'][] = vcex_parse_object_fit_class( $atts['img_object_fit'] );
				}

				if ( ! empty( $atts['img_object_position'] ) ) {
					$thumbnail_args['class'][] = vcex_parse_object_position_class( $atts['img_object_position'] );
				}

				if ( ! empty( $atts['img_mix_blend_mode'] ) ) {
					$thumbnail_args['class'][] = vcex_parse_mix_blend_mode_class( $atts['img_mix_blend_mode'] );
				}

				// Image URL
				if ( ! empty( $atts['image_url'] ) || 'image' === $atts['image_lightbox'] ) {

					// Standard URL
					$link     = vcex_build_link( $atts['image_url'] );
					$a_href   = $link['url'] ?? '';
					$a_title  = $link['title'] ?? '';
					$a_target = $link['target'] ?? '';
					$a_target = ( false !== strpos( $a_target, 'blank' ) ) ? ' target="_blank"' : '';

					// Image lightbox
					$data_attributes = '';

					if ( ! empty( $atts['image_lightbox'] ) ) {

						$image_lightbox = $atts['image_lightbox'];

						vcex_enqueue_lightbox_scripts();

						switch ( $image_lightbox ) {
							case 'image':
							case 'self':
								$a_href = vcex_get_lightbox_image( $image );
								break;
							case 'url':
							case 'iframe':
								$data_attributes .= ' data-type="iframe"';
								break;
							case 'video_embed':
								$a_href = vcex_get_video_embed_url( $a_href );
								break;
							case 'inline':
								$data_attributes .= ' data-type="inline"';
								break;
						}

						if ( $a_href ) {
							$image_classes[] = 'wpex-lightbox';
						}

						// Add lightbox dimensions
						if ( ! empty( $atts['lightbox_dimensions'] )
							&& in_array( $image_lightbox, array( 'video_embed', 'url', 'html5', 'iframe', 'inline' ) )
						) {
							$lightbox_dims = vcex_parse_lightbox_dims( $atts['lightbox_dimensions'], 'array' );
							if ( $lightbox_dims ) {
								$data_attributes .= ' data-width="' . $lightbox_dims['width'] . '"';
								$data_attributes .= ' data-height="' . $lightbox_dims['height'] . '"';
							}
						}

					}

				}

				// Open link if defined
				if ( ! empty( $a_href ) ) {

					$link_classes = [
						'vcex-feature-box-image-link',
						'wpex-overflow-hidden', // used for border radius or other mods to the image
					];

					$link_classes = array_merge( $link_classes, $image_classes );

					$output .= '<a href="' . esc_url( $a_href ) . '" title="' . esc_attr( $a_title ) . '" class=" ' . esc_attr( implode( ' ', $link_classes ) ) . '"' . $data_attributes . '' . $a_target . '>';


				// Link isn't defined open div
				} else {
					$output .= '<div class="' . esc_attr( implode( ' ', $image_classes ) ) . '">';
				}

					// Display image
					$output .= vcex_get_post_thumbnail( $thumbnail_args );

					// Video icon
					if ( ! empty( $onclick_attrs['href'] )
						&& 'lightbox_video' === $atts['onclick']
						&& ! empty( $atts['video_icon'] )
					) {
						if ( '1' === $atts['video_icon'] ) {
							$video_icon_overlay = 'video-icon';
						} else {
							$video_icon_overlay = 'video-icon_' . absint( $atts['video_icon'] );
						}
						ob_start();
							vcex_image_overlay( 'inside_link', $video_icon_overlay );
						$output .= ob_get_clean();
					}

				// Close vcex-featured-box-image element
				if ( ! empty( $a_href ) ) {
					$output .= '</a>';
				} else {
					$output .= '</div>';
				}

				} // End video check

			$output .= '</div>'; // close media

		} // $video or $image check

		// Content area
		if ( ! empty( $content ) || ! empty( $atts['heading'] ) ) {
			$add_content_padding_el = ! empty( $atts['content_padding'] ) || ! empty( $atts['stack_content_padding'] );

			$content_classes = [
				'vcex-feature-box-content',
			];

			if ( ! $has_bleed ) {
				$content_width  = ! empty( $atts['content_width'] ) ? sanitize_text_field( $atts['content_width'] ) : '50%';
				if ( ! $has_custom_bk && ( ! $content_width || '50%' === $content_width ) ) {
					if ( $breakpoint ) {
						$content_classes[] = "wpex-{$breakpoint}-w-50";
					} else {
						$content_classes[] = 'wpex-w-50';
					}
				}
			}

			if ( ! empty( $atts['content_background'] ) ) {
				$content_classes[] = 'wpex-p-30';
			}

			$output .= '<div class="' . esc_attr( implode( ' ', $content_classes ) ) . '">';

			if ( $add_content_padding_el ) {
				$output .= '<div class="vcex-feature-box-padding-container">';
			}

			// Heading
			if ( ! empty( $atts['heading'] ) ) {

				if ( empty( $atts['heading_type'] ) ) {
					$atts['heading_type'] = apply_filters( 'vcex_feature_box_heading_default_tag', 'h2' );
				}

				$safe_heading_tag = tag_escape( $atts['heading_type'] );

				// Classes
				$heading_attrs = [
					'class' => '',
				];

				$heading_class = [
					'vcex-feature-box-heading',
				];

				if ( ! empty( $atts['heading_typography_style'] ) ) {
					$heading_class[] = vcex_parse_typography_style_class( $atts['heading_typography_style'] );
					$heading_class[] = 'wpex-m-0';
					if ( 'span' === $safe_heading_tag ) {
						$heading_class[] = 'wpex-block';
					}
				} else {
					$heading_class[] = 'wpex-heading';
					if ( empty( $atts['heading_size'] ) ) {
						$heading_class[] = vcex_has_classic_styles() ? 'wpex-text-lg' : 'wpex-text-xl';
					}
					if ( empty( $atts['heading_margin_bottom'] ) ) {
						$heading_class[] = 'wpex-mb-20';
					}
				}

				// Heading URL
				$a_href = '';
				if ( ! empty( $atts['heading_url'] ) && '||' !== $atts['heading_url'] ) {
					$link     = vcex_build_link( $atts['heading_url'] );
					$a_href   = $link['url'] ?? '';
					$a_title  = $link['title'] ?? '';
					$a_target = $link['target'] ?? '';
					$a_target = ( false !== strpos( $a_target, 'blank' ) ) ? ' target="_blank"' : '';
				}

				if ( isset( $a_href ) && $a_href ) {
					$output .= '<a href="' . esc_url( do_shortcode( $a_href ) ) . '" title="' . esc_attr( do_shortcode( $a_title ) ) . '"class="vcex-feature-box-heading-link wpex-no-underline"' . $a_target . '>';
				}

				$heading_attrs['class'] = $heading_class;
				$heading_attrs = apply_filters( 'vcex_feature_box_heading_attrs', $heading_attrs, $atts );

				// Display the heading
				$output .= '<' . $safe_heading_tag . vcex_parse_html_attributes( $heading_attrs ) . '>';
					$output .= vcex_parse_text_safe( $atts['heading'] );
				$output .= '</' . $safe_heading_tag .'>';

				if ( isset( $a_href ) && $a_href ) {
					$output .= '</a>';
				}

			} //  End heading

			// Text
			if ( ! empty( $content ) ) {

				$content_text_class = [
					'vcex-feature-box-text',
					'wpex-last-mb-0',
					'wpex-clr',
				];

				if ( empty( $atts['heading_margin_bottom'] ) && ! empty( $atts['heading_typography_style'] ) ) {
					$content_text_class[] = 'wpex-mt-20';
				}

				$output .= '<div class="' . esc_attr( implode( ' ', $content_text_class ) ) . '">';
					$output .= vcex_the_content( $content );
				$output .= '</div>';

			}

			// Button
			if ( ! $has_container_link && ! empty( $onclick_attrs['href'] ) ) {
				$onclick_attrs['class'][] = 'theme-button';
				if ( ! empty( $atts['button_el_class'] ) ) {
					$onclick_attrs['class'][] = esc_attr( $atts['button_el_class'] );
				}
				$output .= '<div class="vcex-feature-box-button wpex-mt-25">';
					$button_text = ! empty( $atts['button_text'] ) ? $atts['button_text'] : esc_html( 'Learn more' );
					$output .= '<a' . vcex_parse_html_attributes( $onclick_attrs ) . '>' . vcex_parse_text_safe( $button_text ) . '</a>';
				$output .= '</div>';
			}

			// Close padding container
			if ( $add_content_padding_el ) {
				$output .= '</div>';
			}

		$output .= '</div>';

	} // End content + Heading wrap

if ( $has_container_link && ! empty( $onclick_attrs['href'] ) ) {
	$output .= '</a>';
} else {
	$output .= '</div>';
}

// @codingStandardsIgnoreLine
echo $output;
