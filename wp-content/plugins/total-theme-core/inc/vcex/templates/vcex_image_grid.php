<?php

/**
 * vcex_image_grid shortcode output.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

// Define main vars
$output           = '';
$entry_count      = ! empty( $atts['entry_count'] ) ? absint( $atts['entry_count'] ) : 0;
$overlay_style    = ! empty( $atts['overlay_style'] ) ? sanitize_text_field( $atts['overlay_style'] ) : 'none';
$pagination_type  = ! empty( $atts['pagination'] ) ? sanitize_text_field( $atts['pagination'] ) : 'numbered';
$cf_gallery       = ! empty( $atts['custom_field_gallery'] ) ? sanitize_text_field( $atts['custom_field_gallery'] ) : false;
$post_gallery     = $cf_gallery ? false : vcex_validate_att_boolean( 'post_gallery', $atts );
$include_featured =  vcex_validate_att_boolean( 'include_featured', $atts );

// Get current post or term ID
if ( ! empty( $atts['term_id'] ) ) {
	$current_post_id = 0;
	$current_term_id = absint( $atts['term_id'] );
} else {
	if ( $cf_gallery || $include_featured || $post_gallery ) {
		$card_instance = vcex_card_instance();
		if ( $card_instance ) {
			if ( ! empty( $card_instance->term ) ) {
				$current_term_id = $card_instance->term->term_id ?? $card_instance->term;
			} else {
				$current_post_id = ! empty( $card_instance->post_id ) ? $card_instance->post_id : vcex_get_the_ID();
			}
		} else {
			if ( ! wp_doing_ajax() && ( is_tax() || is_tag() || is_category() ) ) {
				$current_term_id = get_queried_object_id();
			} else {
				$current_post_id = ! empty( $atts['post_id'] ) ? absint( $atts['post_id'] ) : vcex_get_the_ID();
			}
		}
	}
}

// Get images from custom field
if ( $cf_gallery ) {
	$image_ids  = [];
	if ( function_exists( 'acf_is_field_key' ) && acf_is_field_key( $cf_gallery ) ) {
		$meta_value = '';
		$meta_id    = ! empty( $current_term_id ) ? get_term( $current_term_id ) : $current_post_id;
		if ( $meta_id && ! is_wp_error( $meta_id ) ) {
			$meta_value = vcex_get_acf_field( $cf_gallery, $meta_id );
		}
	} else {
		if ( ! empty( $current_term_id ) ) {
			$meta_value = get_term_meta( $current_term_id, $cf_gallery, true );
		} else {
			$meta_value = get_post_meta( $current_post_id, $cf_gallery, true );
		}
	}
	if ( $meta_value ) {
		if ( is_array( $meta_value ) ) {
			foreach ( $meta_value as $k => $v ) {
				$image_id = $v['id'] ?? $v['ID'] ?? $v;
				if ( is_numeric( $image_id ) && wp_attachment_is_image( $image_id ) ) {
					$image_ids[] = $image_id;
				}
			}
		} else {
			$image_ids = $meta_value;
		}
	}
	if ( ! $image_ids && vcex_is_template_edit_mode() ) {
		$image_ids = $atts['image_ids'] ?? null;
	}
}

// Get images from post gallery
elseif ( $post_gallery ) {
	$image_ids = vcex_get_post_gallery_ids( $current_post_id, $atts['image_ids'] ?? null );
}

// Get images based on Real Media folder
elseif ( defined( 'RML_VERSION' ) && ! empty( $atts['rml_folder'] ) ) {
	$rml_query = new WP_Query( [
		'post_status'    => 'inherit',
		'posts_per_page' => -1,
		'post_type'      => 'attachment',
		'orderby'        => 'rml', // Order by custom order of RML
		'rml_folder'     => sanitize_text_field( $atts['rml_folder'] ),
		'fields'         => 'ids',
	] );
	if ( $rml_query->have_posts() ) {
		$image_ids = $rml_query->posts;
	}
}

// Get images from shortcode param
else {
	$image_ids = $atts['image_ids'] ?? null;
}

// Parse image ids
$attachment_ids = [];
if ( ! empty( $image_ids ) ) {
	if ( is_string( $image_ids ) ) {
		$attachment_ids = explode( ',', $image_ids );
	} elseif ( is_array( $image_ids ) ) {
		$attachment_ids = [];
		foreach ( $image_ids as $image_id ) {
			$attachment_ids[] = $image_id['id'] ?? $image_id;
		}
	}
}

// Add featured image to array
if ( $include_featured ) {
	if ( ! empty( $current_term_id ) ) {
		$featured_image = vcex_get_term_thumbnail_id( $current_term_id );
	} else {
		$featured_image = get_post_thumbnail_id( $current_post_id );
	}
	if ( $featured_image ) {
		if ( ! empty( $attachment_ids ) ) {
			if ( ! in_array( $featured_image, $attachment_ids ) ) {
				array_unshift( $attachment_ids, $featured_image );
			}
		} else {
			$attachment_ids = [ $featured_image ];
		}
	}
}

if ( ! empty( $attachment_ids ) && ! empty( $atts['orderby'] ) && $atts['orderby'] === 'exif_date_time_original' ) {
	$attachment_ids  = vcex_call_helper(
		'Sort_Attachments_by_Exif',
		'sort', $attachment_ids,
		'DateTimeOriginal',
		$atts['order'] ?? 'DESC'
	);
	$atts['orderby'] = 'post__in'; // set correct order for WP_Query
}

/**
 * Filters the vcex_image_grid list of attachment ID's
 *
 * @param array $attachment_ids
 * @param array $shortcode_atts
 */
$attachment_ids = (array) apply_filters( 'vcex_image_grid_attachment_ids', $attachment_ids, $atts );

// No images - bail!
if ( empty( $attachment_ids ) ) {
	return;
}

// Main vars
$is_isotope          = false;
$wrap_css_args       = [];
$grid_is_responsive  = vcex_validate_att_boolean( 'responsive_columns', $atts, true );
$columns             = ! empty( $atts['columns'] ) ? absint( $atts['columns'] ) : 4;
$grid_style          = ! empty( $atts['grid_style'] ) ? sanitize_text_field( $atts['grid_style'] ) : 'fit-rows';
$link_type           = ! empty( $atts['thumbnail_link'] ) ? sanitize_text_field( $atts['thumbnail_link'] ) : 'lightbox';
$has_title           = vcex_validate_att_boolean( 'title', $atts );
$has_excerpt         = vcex_validate_att_boolean( 'excerpt', $atts );
$has_link_title_attr = vcex_validate_att_boolean( 'link_title_tag', $atts );

// Remove duplicate images
$attachment_ids = array_unique( $attachment_ids );

// Get custom links
if ( ! empty( $atts['custom_links'] ) && is_string( $atts['custom_links'] ) ) {
	$custom_links = wp_parse_list( $atts['custom_links'] ); // used to support WPBakery and Elementor
} else {
	$custom_links = [];
}

// Count items
$attachment_ids_count = count( $attachment_ids );
$custom_links_count   = count( $custom_links );

// Add empty values to custom_links array for images without links
if ( $attachment_ids_count > $custom_links_count ) {
	$count = 0;
	foreach ( $attachment_ids as $val ) {
		$count++;
		if ( ! isset( $custom_links[ $count ] ) ) {
			$custom_links[ $count ] = '#';
		}
	}
}

// New custom links count.
$custom_links_count = count( $custom_links );

// Remove extra custom links
if ( $custom_links_count > $attachment_ids_count ) {
	$count = 0;
	foreach ( $custom_links as $key => $val ) {
		$count ++;
		if ( $count > $attachment_ids_count ) {
			unset( $custom_links[$key] );
		}
	}
}

// Set links as the keys for the images
$images_links_array = array_combine( $attachment_ids, $custom_links );

// Pagination
$posts_per_page = ! empty( $atts['posts_per_page'] )
	? vcex_call_helper( 'Sanitize_Query_Parameter', 'posts_per_page', $atts['posts_per_page'], -1 )
	: -1;

if ( -1 === $posts_per_page ) {
	$no_found_rows = true;
} else {
	$no_found_rows = false;
	$paged = 1;
	if ( ! empty( $atts['paged'] ) ) {
		$paged = absint( $atts['paged'] );
	} elseif ( get_query_var( 'paged' ) ) {
		$paged = absint( get_query_var( 'paged' ) );
	} elseif ( get_query_var( 'page' ) ) {
		$paged = absint( get_query_var( 'page' ) );
	}
}

// Order
$orderby = ! empty( $atts['orderby'] )
	? vcex_call_helper( 'Sanitize_Query_Parameter', 'orderby', $atts['orderby'], 'post__in' )
	: 'post__in';

$order = ! empty( $atts['order'] )
	? vcex_call_helper( 'Sanitize_Query_Parameter', 'order', $atts['order'], 'DESC' )
	: 'DESC';

// Query the attachments
$vcex_query = new WP_Query( [
	'post_type'      => 'attachment',
	'post_status'    => 'any', // required for attachments
	'posts_per_page' => $posts_per_page,
	'paged'          => ! empty( $paged ) ? $paged : 1,
	'post__in'       => $attachment_ids,
	'no_found_rows'  => $no_found_rows,
	'orderby'        => $orderby,
	'order'          => $order,
] );

// Display images if we found some
if ( $vcex_query && $vcex_query->have_posts() ) :
	$wrap_class = 'vcex-image-grid-wrap';
	
	if ( ! empty( $atts['bottom_margin'] ) ) {
		$wrap_class .= ' ' . vcex_parse_margin_class( $atts['bottom_margin'], 'bottom' );
	}

	$output .= '<div class="' . esc_attr( trim( $wrap_class ) ) . '">';

	// Define grid style settings and enqueue scripts
	if ( 'justified' === $grid_style ) {
		vcex_enqueue_justified_gallery_scripts();
	} elseif ( 'masonry' === $grid_style || 'no-margins' === $grid_style ) {
		$is_isotope = true;
		vcex_enqueue_isotope_scripts();
	}

	// Link target
	$atts['link_target'] = $atts['custom_links_target'] ?? '_self';

	// Wrap Classes
	$wrap_classes = [
		'vcex-module',
		'vcex-image-grid',
	];

	// Justified grid wrapper classes
	if ( 'justified' === $grid_style ) {
		$wrap_classes[] = 'vcex-justified-gallery';
		$wrap_classes[] = 'wpex-clr';
	} else {

		// CSS grid wrapper classes
		if ( 'css-grid' === $grid_style ) {
			$wrap_classes[] = 'wpex-grid';
			if ( $grid_is_responsive ) {
				if ( ! empty( $atts['columns_responsive_settings'] ) ) {
					$r_grid_columns = vcex_parse_multi_attribute( $atts['columns_responsive_settings'] );
					if ( $r_grid_columns && is_array( $r_grid_columns ) ) {
						$r_grid_columns['d'] = $columns;
						$columns = $r_grid_columns;
					}
				}
			}
			if ( $grid_is_responsive && function_exists( 'wpex_grid_columns_class' ) ) {
				$wrap_classes[] = wpex_grid_columns_class( $columns );
			} else {
				$wrap_classes[] = "wpex-grid-cols-{$columns}";
			}
			if ( empty( $atts['columns_gap'] ) ) {
				$wrap_classes[] = 'wpex-gap-20';
			}
			if ( '-1' !== $posts_per_page && 'disabled' !== $pagination_type ) {
				$wrap_classes[] = 'wpex-mb-20';
			}
		}

		// Masonry & Fit Rows grid classes
		else {
			$wrap_classes[] = "grid-style-{$grid_style}";
			$wrap_classes[] = 'wpex-row';
			$wrap_classes[] = 'wpex-clr';
		}
	}

	if ( $is_isotope ) {
		$wrap_classes[] = 'vcex-isotope-grid';
		$wrap_classes[] = 'no-transition';
		$wrap_classes[] = 'wpex-overflow-hidden';
	}

	if ( 'no-margins' === $grid_style ) {
		$wrap_classes[] = 'vcex-no-margin-grid';
	}

	if ( 'lightbox' === $link_type ) {
		if ( vcex_validate_att_boolean( 'lightbox_gallery', $atts, true ) ) {
			$wrap_classes[] = 'wpex-lightbox-group';
		}
	}

	if ( ! empty( $atts['classes'] ) ) {
		$wrap_classes[] = vcex_get_extra_class( $atts['classes'] );
	}

	if ( ! empty( $atts['visibility'] ) ) {
		$wrap_classes[] = vcex_parse_visibility_class( $atts['visibility'] );
	}

	if ( 'justified' !== $grid_style
		&& ! empty( $atts['css_animation'] )
		&& 'none' !== $atts['css_animation']
		&& vcex_validate_att_boolean( 'animation_sequential', $atts )
	) {
		$wrap_classes[] = 'wpb-animate-in-sequence';
	}

	// Wrap data attributes
	$wrap_data = [];

	switch ( $grid_style ) {
		case 'justified':
			$justified_gallery_settings = [
				'selector'  => 'vcex-image-grid-entry',
				'margins'   => ! empty( $atts['justified_row_margin'] ) ? absint( $atts['justified_row_margin'] ) : 5,
				'rowHeight' => ! empty( $atts['justified_row_height'] ) ? absint( $atts['justified_row_height'] ) : 200,
				'lastRow'   => ! empty( $atts['justified_last_row'] ) ? sanitize_text_field( $atts['justified_last_row'] ) : 'justified',
				'captions'  => false,
			];
			if ( is_rtl() ) {
				$justified_gallery_settings['rtl'] = true;
			}
			$justified_gallery_settings = (array) apply_filters( 'vcex_image_grid_justified_gallery_settings', $justified_gallery_settings, $atts );
			$wrap_data[] = 'data-justified-gallery="' . esc_attr( htmlspecialchars( wp_json_encode( $justified_gallery_settings ) ) ) . '"';
			break;
		case 'masonry':
		case 'no-margins':
			$wrap_data[] = 'data-transition-duration="0.0"';
			if ( ! vcex_validate_att_boolean( 'masonry_horizontal_order', $atts, true ) ) {
				$wrap_data[] = 'data-horizontal-order="false"';
			}
			break;
	}

	if ( 'lightbox' === $link_type ) {
		vcex_enqueue_lightbox_scripts();
		$lightbox_data = [];
		// @note this param has been deprecated since we have a global option in the Customizer now
		if ( isset( $atts['lightbox_path'] ) ) {
			if ( 'disabled' === $atts['lightbox_path'] ) {
				$lightbox_data[] = 'data-thumbnails="false"';
			}
		}
		if ( empty( $atts['lightbox_title'] ) || 'false' === $atts['lightbox_title'] ) {
			$lightbox_data[] = 'data-show_title="false"';
		}
		$wrap_data = array_merge( $wrap_data, $lightbox_data );
	}

	// Columns classes
	if ( 'css_grid' !== $grid_style ) {
		$columns_class = vcex_get_grid_column_class( $atts );
	}

	// Entry Classes
	$entry_classes = [
		'vcex-image-grid-entry',
		'vcex-grid-item',
	];

	if ( $is_isotope ) {
		$entry_classes[] = 'vcex-isotope-entry';
	}

	if ( isset( $atts['content_alignment'] ) && 'none' !== $atts['content_alignment'] ) {
		$entry_classes[] = vcex_parse_text_align_class( $atts['content_alignment'] );
	}

	if ( 'justified' !== $grid_style ) {

		if ( 'no-margins' === $grid_style ) {
			$entry_classes[] = 'vcex-no-margin-entry';
		}

		if ( 'css-grid' == $grid_style ) {
			$entry_classes[] = 'wpex-flex';
			$entry_classes[] = 'wpex-flex-col';
			$entry_classes[] = 'wpex-flex-grow';
		} else {
			if ( $columns && isset( $columns_class ) ) {
				$entry_classes[] = $columns_class;
			}
			if ( $grid_is_responsive ) {
				$entry_classes[] = 'col';
			} else {
				$entry_classes[] = 'nr-col';
			}
		}

	}

	if ( 'justified' !== $grid_style && ! empty( $atts['css_animation'] ) && 'none' !== $atts['css_animation'] ) {
		$entry_classes[] = vcex_get_css_animation( $atts['css_animation'] );
	}

	// Figure classes - image + caption
	$figure_classes = [
		'vcex-image-grid-entry-figure',
		'wpex-last-mb-0',
		'wpex-clr',
	];

	if ( ! empty( $atts['vertical_align'] ) && 'none' !== $atts['vertical_align'] ) {
		$vertical_align = sanitize_html_class( $atts['vertical_align'] );
		switch ( $vertical_align ) {
			case 'top':
				$vertical_align = 'start';
				break;
			case 'bottom':
				$vertical_align = 'end';
				break;
		}
		$figure_classes[] = "wpex-flex wpex-flex-col wpex-flex-grow wpex-justify-{$vertical_align}";
	}

	if ( ! empty( $atts['entry_css'] ) ) {
		$figure_classes[] = vcex_vc_shortcode_custom_css_class( $atts['entry_css'] );
	}

	// Image class
	$image_class = [];
	if ( ! empty( $atts['img_aspect_ratio'] ) ) {
		$image_class[] = vcex_parse_aspect_ratio_class( $atts['img_aspect_ratio'] );
		if ( ! empty( $atts['img_object_fit'] ) ) {
			$image_class[] = vcex_parse_object_fit_class( $atts['img_object_fit'] );
		}
	}
	if ( ! empty( $atts['img_el_class'] ) ) {
		$image_class[] = esc_attr( sanitize_text_field( $atts['img_el_class'] ) );
	}

	// Lightbox class - we need to always check this because users can filter the link_type on a per image basis
	if ( vcex_validate_att_boolean( 'lightbox_gallery', $atts, true ) ) {
		$lightbox_class = 'wpex-lightbox-group-item';
	} else {
		$lightbox_class = 'wpex-lightbox';
	}

	// Title style & title related vars
	if ( $has_title ) {
		$title_tag_escaped = ! empty( $atts['title_tag'] ) ? tag_escape( $atts['title_tag'] ) : 'h2';
	}

	// Link attributes
	$link_attributes = '';
	if ( ! empty( $atts['link_attributes'] ) ) {
		$link_attributes_array = wp_parse_list( $atts['link_attributes'] );
		if ( is_array( $link_attributes_array ) ) {
			foreach ( $link_attributes_array as $attribute ) {
				if ( false !== strpos( $attribute, '|' ) ) {
					$attribute = explode( '|', $attribute );
					$link_attributes .= ' ' . esc_attr( $attribute[0] ) .'="' . esc_attr( do_shortcode( $attribute[1] ) ) . '"';
				}
			}
		}
	}

	// Convert arrays to strings
	$wrap_classes = implode( ' ', $wrap_classes );

	// Apply filters
	$wrap_classes = vcex_parse_shortcode_classes( $wrap_classes, 'vcex_image_grid', $atts );

	// Wrap attributes
	$wrap_attrs = [
		'id'    => ! empty( $atts['unique_id'] ) ? $atts['unique_id'] : null,
		'class' => $wrap_classes,
		'data'  => implode( ' ', $wrap_data ),
	];

	// Open CSS div
	if ( ! empty( $atts['css'] ) ) {
		$output .= '<div class="vcex-image-grid-css-wrapper ' . vcex_vc_shortcode_custom_css_class( $atts['css'] ) . '">';
	}

	/*--------------------------------*/
	/* [Header ]
	/*--------------------------------*/
	if ( ! empty( $atts['header'] ) ) {
		$output .= vcex_get_module_header( [
			'style'   => $atts['header_style'] ?? '',
			'content' => $atts['header'],
			'classes' => [
				'vcex-module-heading',
				'vcex_image_grid-heading'
			],
		] );
	}

	/*--------------------------------*/
	/* [ Begin Grid output ]
	/*--------------------------------*/
	$output .= '<div' . vcex_parse_html_attributes( $wrap_attrs ) . '>';

		// Loop through images
		while ( $vcex_query->have_posts() ) :

			// Reset thubmnail type at start - important!
			$entry_link_type = $link_type;

			// Add to entry count
			$entry_count++;

			// Get post from query
			$vcex_query->the_post();

			// Get post data and define main vars
			$attachment_id    = get_the_ID();
			$post_data        = vcex_get_attachment_data( $attachment_id );
			$link_url         = '';
			$link_title_att   = '';
			$post_alt_escaped = ! empty( $post_data['alt'] ) ? esc_attr( sanitize_text_field( $post_data['alt'] ) ) : '';
			$og_attachment_id = $attachment_id; // Original attachment ID used to locate it's custom link

			// Get original attachment ID - fix for WPML
			if ( $custom_links_count && function_exists( 'icl_object_id' ) ) {
				global $sitepress;
				if ( $sitepress ) {
					$_icl_lang_duplicate_of = get_post_meta( $attachment_id, '_icl_lang_duplicate_of', true );
					$wpml_attachment_id = icl_object_id( $attachment_id, 'attachment', false, $sitepress->get_default_language() );
					if ( ! array_key_exists( $wpml_attachment_id, $images_links_array ) ) {
						$wpml_attachment_id = icl_object_id( $attachment_id, 'attachment', false, apply_filters( 'wpml_current_language', NULL ) );
					}
					if ( array_key_exists( $wpml_attachment_id, $images_links_array ) ) {
						$og_attachment_id = $wpml_attachment_id;
					}
				}
			}

			// Pluck array to see if item has custom link
			if ( array_key_exists( $og_attachment_id, $images_links_array ) ) {
				$link_url = $images_links_array[ $og_attachment_id ];
			}

			// Remove links if the post_url is a # symbol
			if ( '#' === $link_url ) {
				$link_url = '';
			}

			// Check for custom meta links (if the link is defined by the attachment meta)
			if ( 'custom_link' === $entry_link_type && ! empty( $atts['link_meta_key'] ) ) {
				$meta_custom_link = get_post_meta( $attachment_id, sanitize_text_field( $atts['link_meta_key'] ), true );
				if ( ! empty( $meta_custom_link ) ) {
					$link_url = $meta_custom_link;
				}
			} else {
				$meta_custom_link = get_post_meta( $attachment_id, '_wpex_custom_link', true );
				if ( ! empty( $meta_custom_link ) ) {
					$entry_link_type = 'custom_link';
					$link_url = $meta_custom_link;
				}
			}

			// If $entry_link_type is set to custom_link but there is no link set the $entry_link_type to null
			if ( 'custom_link' === $entry_link_type && empty( $link_url ) ) {
				$entry_link_type = null;
			}

			$thumbnail_class = vcex_get_entry_thumbnail_class( $image_class, 'vcex_image_grid', $atts );

			// Define thumbnail args
			$thumbnail_args = [
				'attachment'    => $attachment_id,
				'alt'           => $post_alt_escaped,
				'size'          => $atts['img_size'] ?? null,
				'width'         => $atts['img_width'] ?? null,
				'height'        => $atts['img_height'] ?? null,
				'crop'          => $atts['img_crop'] ?? null,
				'class'         => implode( ' ', $thumbnail_class ),
				'apply_filters' => 'vcex_image_grid_thumbnail_args',
				'filter_arg1'   => $atts,
			];

			// Disable lazy loading
			if ( $is_isotope || 'justified' == $grid_style ) {
				$thumbnail_args['lazy'] = false;
			}

			// Set image HTML since we'll use it a lot later on
			$post_thumbnail = vcex_get_post_thumbnail( $thumbnail_args );

			// Entry classes (don't set to entry_classes because this isn't reset on every item)
			$loop_entry_classes = implode( ' ', $entry_classes );

			if ( 'justified' !== $grid_style && 'css-grid' !== $grid_style && is_array( $entry_classes ) ) {
				$loop_entry_classes .= ' col-' . sanitize_html_class( $entry_count );
			}

			// Begin entry output
			$output .= '<div class="id-' . esc_attr( $attachment_id ) . ' ' . esc_attr( $loop_entry_classes ) . '">';

				// Open figure element
				$output .= '<figure class="' . esc_attr( implode( ' ', $figure_classes ) ) . '">';

					// Define media type
					$atts['media_type'] = 'thumbnail';

					// Image wrap
					$output .= '<div class="' . esc_attr( implode( ' ', vcex_get_entry_media_class( [ 'vcex-image-grid-entry-img' ], 'vcex_image_grid', $atts ) ) ) . '">';

						switch ( $entry_link_type ) :

							// Lightbox link
							case 'lightbox':

								// Define lightbox vars
								$atts['lightbox_data'] = $lightbox_data;
								$lightbox_image        = vcex_get_lightbox_image( $attachment_id );
								$lightbox_url          = $lightbox_image;
								$video_url             = $post_data['video'] ?? '';

								// Lightbox title
								if ( ! isset( $has_lightbox_title ) ) {
									$has_lightbox_title = isset( $atts['lightbox_title'] ) && 'false' !== $atts['lightbox_title'];
								}
								if ( $has_lightbox_title ) {
									if ( 'title' === $atts['lightbox_title'] ) {
										$data_title = get_the_title( $attachment_id );
										if ( $data_title ) {
											$atts['lightbox_data']['data-title'] = 'data-title="' . esc_attr( wp_strip_all_tags( $data_title ) ) . '"';
										}
									} elseif ( 'alt' === $atts['lightbox_title'] && $post_alt_escaped ) {
										$atts['lightbox_data']['data-title'] = 'data-title="' . esc_attr( $post_alt_escaped ) . '"';
									}
								}

								// Lightbox caption
								if ( ! isset( $has_lightbox_caption ) ) {
									$has_lightbox_caption = vcex_validate_att_boolean( 'lightbox_caption', $atts, true );
								}
								if ( $has_lightbox_caption && ! empty( $post_data['caption'] ) ) {
									$atts['lightbox_data']['data-caption'] = 'data-caption="' . str_replace( '"',"'", $post_data['caption'] ) . '"';
								}

								// Video data
								if ( $video_url ) {
									$video_embed_url = vcex_get_video_embed_url( $video_url );
									$lightbox_url    = $video_embed_url ?: $video_url;
									$atts['lightbox_data']['data-thumb'] = 'data-thumb="' . esc_attr( $lightbox_image ) . '"';
								}

								// Apply filters to lightbox data
								$atts['lightbox_data'] = apply_filters( 'vcex_image_grid_lightbox_data', $atts['lightbox_data'], $atts, $attachment_id );

								// Convert data attributes to array
								$atts['lightbox_data'] = ' ' . implode( ' ', $atts['lightbox_data'] );

								// Add lightbox class to atts
								$atts['lightbox_class'] = $lightbox_class;

								// Get title tag if enabled
								if ( $has_link_title_attr ) {
									$link_title_att = vcex_html( 'title_attr', $post_alt_escaped, false );
								}

								// Open link tag
								$output .= '<a href="' . esc_url( $lightbox_url ) . '" class="vcex-image-grid-entry-link ' . $atts['lightbox_class'] . '"' . $link_title_att . $atts["lightbox_data"] . $link_attributes .'>';

									// Display image
									$output .= $post_thumbnail;

									// Video icon overlay
									if ( $video_url && 'none' === $overlay_style ) {
										$output .= vcex_get_image_overlay( 'inside_link', 'video-icon' );
									}

									// Inner link overlay HTML
									$output .= vcex_get_entry_image_overlay( 'inside_link', 'vcex_image_grid', $atts );

								$output .= '</a>';

								break;

							// Attachment link
							case 'attachment_page':
							case 'full_image':

								// Get URL
								if ( 'attachment_page' === $entry_link_type ) {
									$url = get_permalink();
								} else {
									$url = wp_get_attachment_url( $attachment_id );
								}

								// Set title tag if enabled
								if ( $has_link_title_attr && $post_alt_escaped ) {
									$link_title_att = vcex_html( 'title_attr', $post_alt_escaped, false );
								}

								// Link target
								$link_target = vcex_html( 'target_attr', $atts['link_target'], false );

								// Open link tag
								$output .= '<a href="' . esc_url( $url ) . '" class="vcex-image-grid-entry-link"' . $link_title_att . $link_target . $link_attributes . '>';

									// Display image
									$output .= $post_thumbnail;

									// Inner link overlay HTML
									$output .= vcex_get_entry_image_overlay( 'inside_link', 'vcex_image_grid', $atts );

								$output .= '</a>';

								break;

							// Custom link
							case 'custom_link':

								$atts['overlay_link'] = $link_url ?: 'disable';

								// Set title tag if enabled
								if ( $has_link_title_attr ) {
									$link_title_att = vcex_html( 'title_attr', $post_alt_escaped, false );
								}

								// Link target
								$link_target = vcex_html( 'target_attr', $atts['link_target'], false );

								// Open link tag
								$output .= '<a href="' . esc_url( $link_url ) . '" class="vcex-image-grid-entry-link"' . $link_title_att . $link_target . $link_attributes . '>';

									// Display image
									$output .= $post_thumbnail;

									// Inner link overlay HTML
									$output .= vcex_get_entry_image_overlay( 'inside_link', 'vcex_image_grid', $atts );

								$output .= '</a>';

								break;

							// Parent page (Uploaded to link)
							case 'parent_page':
								$post_parent = get_post_parent( $attachment_id );

								// Open link
								if ( $post_parent ) {
									$output .= '<a href="' . esc_url( get_permalink( $post_parent ) ) . '" class="vcex-image-grid-entry-link"' . $link_attributes . '>';
								}
									$output .= $post_thumbnail;
									$output .= vcex_get_entry_image_overlay( 'inside_link', 'vcex_image_grid', $atts );

								// Close link
								if ( $post_parent ) {
									$output .= '</a>';
								}

								break;

						// Just the Image - no link
						default:
							$output .= $post_thumbnail;
							$output .= vcex_get_entry_image_overlay( 'inside_link', 'vcex_image_grid', $atts );

						endswitch;

							if ( 'none' !== $overlay_style ) {
								if ( 'custom_link' === $link_type ) {
									$atts['overlay_link'] = $link_url ?: 'disable';
								} elseif( 'lightbox' === $entry_link_type && $lightbox_url ) {
									$atts['lightbox_link'] = $lightbox_url;
								}
								$output .= vcex_get_entry_image_overlay( 'outside_link', 'vcex_image_grid', $atts );
							}
							
					// Close image wrap
					$output .= '</div>';


					// Title
					if ( $has_title && 'justified' !== $grid_style ) {
						$title_type = $atts['title_type'] ?? 'title';

						// Get title
						switch ( $title_type ) {
							case 'title':
								$post_title_display = get_the_title();
								break;
							case 'alt':
								$post_title_display = $post_alt_escaped;
								break;
							case 'caption':
								$post_title_display = wp_get_attachment_caption();
								break;
							case 'description':
								$post_title_display = get_the_content();
								break;
							default:
								$post_title_display = '';
								break;
						}

						// Display title
						if ( $post_title_display ) {
							$output .= '<figcaption class="vcex-image-grid-entry-title wpex-mb-10 wpex-clr">';
								$output .= '<'. $title_tag_escaped .' class="entry-title">';
									$output .= wp_kses_post( $post_title_display );
								$output .= '</'. $title_tag_escaped .'>';
							$output .= '</figcaption>';
						}

					}

					// Excerpt
					if ( $has_excerpt && 'justified' !== $grid_style ) {
						$excerpt_type = $atts['excerpt_type'] ?? 'caption';
						switch ( $excerpt_type ) {
							case 'caption':
								$excerpt_display = wp_get_attachment_caption();
								break;
							case 'description':
								$excerpt_display = get_the_content();
								break;
							default:
								$excerpt_display = '';
								break;
						}
						if ( $excerpt_display ) {
							$output .= '<div class="vcex-image-grid-entry-excerpt entry-excerpt wpex-mb-20 wpex-clr">';
								$output .= wp_kses_post( $excerpt_display );
							$output .= '</div>';
						}
					}

				$output .= '</figure>';

			$output .= '</div>';

			// Clear counter
			if ( $entry_count === (int) $columns ) {
				$entry_count = 0;
			}

		// End while loop
		endwhile;

	$output .= '</div>';

	// Close CSS div
	if ( ! empty( $atts['css'] ) ) {
		$output .= '</div>';
	}

	// Display pagination if enabled
	if ( 'disabled' !== $pagination_type && -1 !== $posts_per_page ) {
		switch ( $pagination_type ) {
			case 'numbered':
				$output .= vcex_pagination( $vcex_query, false );
				break;
			case 'loadmore':
			case 'infinite_scroll':
				if ( ! empty( $vcex_query->max_num_pages ) ) {
					vcex_loadmore_scripts();
					if ( ! empty( $current_post_id ) ) {
						$raw_atts['post_id'] = $current_post_id;
					}
					if ( ! empty( $current_term_id ) ) {
						$raw_atts['term_id'] = $current_term_id;
					}
					$raw_atts['entry_count'] = $entry_count;
					$infinite_scroll = ( 'infinite_scroll' === $pagination_type ) ? true : false;
					$output .= vcex_get_loadmore_button( 'vcex_image_grid', $raw_atts, $vcex_query, $infinite_scroll );
				}
				break;
				break;
		}
	}

$output .= '</div>'; // end wrap

endif; // End Query

// Reset post data
vcex_reset_postdata();

// @codingStandardsIgnoreLine
echo $output;
