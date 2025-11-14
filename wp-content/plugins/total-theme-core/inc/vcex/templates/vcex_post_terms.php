<?php

/**
 * vcex_post_terms shortcode output.
 *
 * @package Total WordPress Theme
 * @subpackage Total Theme Core
 * @version 2.4
 */

defined( 'ABSPATH' ) || exit;

extract( $atts ); // @todo remove

// Define vars needed to get terms
$post_id            = vcex_get_the_ID();
$taxonomy           = ! empty( $atts['taxonomy'] ) ? sanitize_text_field( $atts['taxonomy'] ) : '';
$first_term_only    = vcex_validate_att_boolean( 'first_term_only', $atts );
$template_edit_mode = vcex_is_template_edit_mode();

// Locate taxonomy if one isn't defined
if ( ! $template_edit_mode && ! $taxonomy && function_exists( 'wpex_get_post_primary_taxonomy' ) ) {
	$taxonomy = wpex_get_post_primary_taxonomy( $post_id );
}

// Taxonomy is required
if ( ! $template_edit_mode && ( empty( $taxonomy ) || ! taxonomy_exists( $taxonomy ) ) ) {
	return;
}

// Define terms
$terms = [];

// Dummy terms for dynamic templates
if ( $template_edit_mode ) {
	$dummy_link = '#';
	$sample_terms_number = $first_term_only ? 1 : 2;
	for ($i = 0; $i < $sample_terms_number; $i++) {
		$sample_term              = new stdClass();
		$sample_term->term_id     = $i;
		$sample_term->name        = esc_html__( 'Sample Term', 'total-theme-core' ) . ' ' . ( $i + 1 );
		$sample_term->slug        = "sample-term-{$i}";
		$sample_term->taxonomy    = 'sample-taxonomy';
		$sample_term->description = '';
		$sample_term->parent      = 0;
		$sample_term->count       = 1;
		$terms[]                  = $sample_term;
	}
}

// Get featured term
if ( ! $terms
	&& $first_term_only
	&& function_exists( 'totaltheme_get_post_primary_term' )
	&& $primary_term = totaltheme_get_post_primary_term( '', $taxonomy )
) {
	$terms = [ $primary_term ];
}

// If terms is empty lets query them
if ( ! $terms ) {

	// Sanitize order
	if ( ! in_array( strtoupper( $order ), [ 'ASC', 'DESC' ], true ) ) {
		$order = 'DESC';
	}

	// Sanitize orderby
	if ( ! in_array( $orderby, [ 'name', 'slug', 'term_group', 'term_id', 'id', 'description' ], true ) ) {
		$orderby = 'name';
	}

	// Query arguments
	$query_args = [
		'order'   => $order,
		'orderby' => $orderby,
		'fields'  => 'all',
	];

	// Filters the vcex_post_terms shortcode query args
	$query_args = (array) apply_filters( 'vcex_post_terms_query_args', $query_args, $atts );

	$terms = wp_get_post_terms( $post_id, $taxonomy, $query_args );

	// Get first term only
	if ( $first_term_only && isset( $terms[0] ) ) {
		$terms = [ $terms[0] ];
	}

}

// Terms needed
if ( ! $terms || is_wp_error( $terms ) ) {
	return;
}

// Main element vars
$style = ! empty( $atts['style'] ) ? sanitize_text_field( $atts['style'] ) : 'buttons';

// Define output var
$output = '';

// Wrap classes
$wrap_class = [
	'vcex-post-terms',
	'vcex-module',
];

if ( 'buttons' === $style ) {
	$wrap_class[] = 'wpex-flex wpex-flex-wrap';
	if ( ! empty( $atts['spacing'] ) ) {
		$wrap_class[] = vcex_parse_gap_class( $atts['spacing'] );
	} else  {
		$wrap_class[] = 'wpex-gap-5';
	}
	if ( ! empty( $atts['button_align'] ) ) {
		$wrap_class[] = vcex_parse_justify_content_class( $atts['button_align'] );
	}
}

// Alignment
if ( ! empty( $atts['max_width'] ) ) {
	$wrap_class[] = 'wpex-max-w-100';
	$wrap_class[] = vcex_parse_align_class( ! empty( $atts['align'] ) ? $atts['align'] : 'center' );
}

// Add extra classes
$extra_classes = vcex_get_shortcode_extra_classes( $atts, 'vcex_post_terms' );

if ( $extra_classes ) {
	$wrap_class = array_merge( $wrap_class, $extra_classes );
}

$wrap_class = vcex_parse_shortcode_classes( $wrap_class, 'vcex_post_terms', $atts );

// Begin output
$output .= '<div class="' . esc_attr( $wrap_class ) . '"' . vcex_get_unique_id( $unique_id ) . '>';

	// Define link vars
	$link_class = [
		'vcex-post-terms__item',
	];
	$link_class_xtra = [];

	// Button Style Classes and inline styles
	if ( 'buttons' === $style ) {
		$link_class_xtra[] = 'vcex-post-terms__item--btn';
		$link_class_xtra[] = vcex_get_button_classes( $button_style, $button_color_style, $button_size );

		if ( 'false' == $atts['archive_link'] || ! $atts['archive_link'] ) {
			$link_class_xtra[] = 'wpex-cursor-default';
		}
	}

	// Get child_of value
	if ( ! empty( $child_of ) ) {
		$get_child_of = get_term_by( 'slug', trim( $child_of ), $taxonomy );
		if ( $get_child_of ) {
			$child_of_id = $get_child_of->term_id;
		}
	}

	// Get excluded terms
	if ( ! empty( $exclude_terms ) ) {
		$exclude_terms = preg_split( '/\,[\s]*/', $exclude_terms );
	} else {
		$exclude_terms = [];
	}

	// Before Text or Open list
	if ( 'inline' === $style && ! empty( $before_text ) ) {
		$output .= '<span class="vcex-post-terms__label vcex-label">' . vcex_parse_text_safe( $before_text ) . '</span> ';
	} elseif ( 'ul' === $style ) {
		$output .= '<ul class="vcex-post-terms__list">';
	} elseif ( 'ol' === $style ) {
		$output .= '<ol class="vcex-post-terms__list">';
	}

	// Loop through terms
	if ( is_array( $terms ) ) {
		$terms_count = 0;
		$first_run = true;
		foreach ( $terms as $term ) :

			// Skip items that aren't a child of a specific parent
			if ( ! empty( $child_of_id ) && $term->parent != $child_of_id ) {
				continue;
			}

			// Skip excluded terms
			if ( in_array( $term->slug, $exclude_terms ) ) {
				continue;
			}

			// Get term id
			$term_id = absint( $term->term_id );

			// Set link class in loop to prevent issues with added term classes
			$item_link_class = $link_class;
			$item_link_class[] = "vcex-post-terms__item--{$term_id}";

			if ( $link_class_xtra ) {
				$item_link_class = array_merge( $item_link_class, $link_class_xtra );
			}

			// Add to counter
			$terms_count ++;

			// Add li tags
			if ( in_array( $style, [ 'ul', 'ol' ] ) ) {
				$output .= '<li>';
			}

			// Filters the vcex_post_terms element link class
			$item_link_class = (array) apply_filters( 'vcex_post_terms_link_class', $item_link_class, $term, $atts );

			// Add term color classes
			if ( in_array( 'term_color', $atts, true ) && (bool) vcex_get_term_color( $term ) ) {
				if ( isset( $atts['button_color'] ) && 'term_color' === $atts['button_color'] ) {
					$item_link_class[] = "has-term-{$term_id}-color";
				} else {
					if ( isset( $atts['button_hover_color'] ) && 'term_color' === $atts['button_hover_color'] ) {
						$item_link_class[] = "has-term-{$term_id}-hover-color";
					}
				}
				if ( isset( $atts['button_background'] ) && 'term_color' === $atts['button_background'] ) {
					$item_link_class[] = "has-term-{$term_id}-background-color";
				} else {
					if ( isset( $atts['button_hover_background'] ) && 'term_color' === $atts['button_hover_background'] ) {
						$item_link_class[] = "has-term-{$term_id}-hover-background-color";
					}
				}
			}

			// Open term element
			if ( 'true' == $atts['archive_link'] ) {
				$output .= '<a' . vcex_parse_html_attributes( [
					'href'   => $dummy_link ?? get_term_link( $term, $taxonomy ),
					'class'  => $item_link_class,
					'target' => $atts['archive_link_target'] ?? '',
				] ) . '>';
			} else {
				$output .= '<span' . vcex_parse_html_attributes( [
					'class' => $item_link_class,
				] ) . '>';
			}

			// Display title
			$output .= esc_html( $term->name );

			// Close term element
			if ( 'true' == $atts['archive_link'] ) {
				$output .= '</a>';
			} else {
				$output .= '</span>';
			}

			// Add spacer for inline style
			if ( 'inline' === $style && $terms_count < count( $terms ) ) {

				if ( ! empty( $atts['spacer'] ) ) {
					$output .= ' ';
					// Parse custom spacer, don't use sanitize_text_field because that will trim spaces
					$spacer = do_shortcode( wp_strip_all_tags( $atts['spacer'] ) );
				} else {
					$spacer = apply_filters( 'vcex_post_terms_default_spacer', '&comma;' );
				}

				$output .= '<span class="vcex-post-terms__separator vcex-spacer">' . $spacer . '</span> ';

			}

			// Close li tags
			if ( in_array( $style, [ 'ul', 'ol' ] ) ) {
				$output .= '</li>';
			}

			$first_run = false;

		endforeach;
	}

	// Close UL list
	if ( 'ul' === $style ) {
		$output .= '</ul>';
	}

	// Open OL list
	elseif ( 'ol' === $style ) {
		$output .= '</ol>';
	}

// Close main wrapper
$output .= '</div>';

// @codingStandardsIgnoreLine
echo $output;
