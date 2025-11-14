<?php

namespace TotalThemeCore\Widgets;

use WP_Query;
use TotalThemeCore\WidgetBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Post With Thumbnails widget.
 */
class Widget_Recent_Posts_Thumb extends WidgetBuilder {

	/**
	 * Widget args.
	 */
	private $args;

	/**
	 * Register widget with WordPress.
	 *
	 * @since 1.0
	 */
	public function __construct() {
		$this->args = array(
			'id_base' => 'wpex_recent_posts_thumb',
			'name'    => $this->branding() . esc_html__( 'Posts With Thumbnails', 'total-theme-core' ),
			'options' => array(
				'customize_selective_refresh' => true,
			),
			'fields' => array(
				array(
					'id'    => 'title',
					'label' => esc_html__( 'Title', 'total-theme-core' ),
					'type'  => 'text',
				),
				array(
					'id'      => 'number',
					'label'   => esc_html__( 'Number', 'total-theme-core' ),
					'type'    => 'number',
					'default' => 3,
				),
				array(
					'id'      => 'style',
					'label'   => esc_html__( 'Style', 'total-theme-core' ),
					'type'    => 'select',
					'default' => 'default',
					'choices' => array(
						'default' => esc_html__( 'Small Image', 'total-theme-core' ),
						'fullimg' => esc_html__( 'Full Image', 'total-theme-core' )
					),
				),
				array(
					'id'       => 'post_type',
					'label'    => esc_html__( 'Post Type', 'total-theme-core' ),
					'type'     => 'select',
					'choices'  => 'post_types',
					'default'  => 'post',
				),
				array(
					'id'      => 'taxonomy',
					'label'   => esc_html__( 'Query By Taxonomy', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'taxonomies',
				),
				array(
					'id'          => 'terms',
					'label'       => esc_html__( 'Include Terms', 'total-theme-core' ),
					'type'        => 'text',
					'description' => esc_html__( 'Enter a comma seperated list of terms.', 'total-theme-core' ),
				),
				array(
					'id'          => 'terms_exclude',
					'label'       => esc_html__( 'Exclude Terms', 'total-theme-core' ),
					'type'        => 'text',
					'description' => esc_html__( 'Enter a comma seperated list of terms.', 'total-theme-core' ),
				),
				array(
					'id'      => 'order',
					'label'   => esc_html__( 'Order', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'query_order',
					'default' => 'DESC',
				),
				array(
					'id'      => 'orderby',
					'label'   => esc_html__( 'Order by', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'query_orderby',
					'default' => 'date',
				),
				array(
					'id'          => 'custom_query_args',
					'label'       => esc_html__( 'Custom Query Callback', 'total-theme-core' ),
					'type'        => 'text',
					'description' => esc_html__( 'Enter a custom callback function name to return your own arguments for the query. Your callback function should return an array of arguments for the WP_Query class.', 'total-theme-core' ),
				),
				array(
					'id'    => 'date',
					'label' => esc_html__( 'Disable Date?', 'total-theme-core' ),
					'type'  => 'checkbox',
				),
				array(
					'id'          => 'excerpt_length',
					'label'       => esc_html__( 'Excerpt Length', 'total-theme-core' ),
					'type'        => 'number',
					'default'     => 0,
					'description' => esc_html__( 'Enter a value to display an excerpt with chose number of words.', 'total-theme-core' ),
				),
				array(
					'id'    => 'items_center',
					'label' => esc_html__( 'Vertical Align?', 'total-theme-core' ),
					'type'  => 'checkbox',
				),
				array(
					'id'    => 'thumbnail_query',
					'label' => esc_html__( 'Post With Thumbnails Only?', 'total-theme-core' ),
					'type'  => 'checkbox',
					'std'   => 'on',
				),
				array(
					'id'          => 'img_container_width',
					'label'       => esc_html__( 'Image Container Width', 'total-theme-core' ),
					'type'        => 'text',
					'placeholder' => '65px',
					'description' => esc_html__( 'Used to constrain your image size without cropping. Used with the "Small image" style only.', 'total-theme-core' ),
				),
				array(
					'id'      => 'img_aspect_ratio',
					'label'   => esc_html__( 'Image Aspect Ratio', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'aspect_ratio',
				),
				array(
					'id'      => 'img_border_radius',
					'label'   => esc_html__( 'Image Border Radius', 'total-theme-core' ),
					'type'    => 'select',
					'default' => 'square',
					'choices' => 'border_radius',
				),
				array(
					'id'      => 'img_hover',
					'label'   => esc_html__( 'Image Hover', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'image_hovers',
				),
				array(
					'id'      => 'img_filter',
					'label'   => esc_html__( 'Image Filter', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'image_filters',
				),
				array(
					'id'      => 'img_size',
					'label'   => esc_html__( 'Image Size', 'total-theme-core' ),
					'type'    => 'select',
					'default' => 'wpex-custom',
					'choices' => 'intermediate_image_sizes',
				),
				array(
					'id'    => 'img_width',
					'label' => esc_html__( 'Image Crop Width', 'total-theme-core' ),
					'type'  => 'text',
				),
				array(
					'id'    => 'img_height',
					'label' => esc_html__( 'Image Crop Height', 'total-theme-core' ),
					'type'  => 'text',
				),
				array(
					'id'      => 'img_crop_location',
					'label'   => esc_html__( 'Image Crop Location', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'image_crop_locations',
				),
				array(
					'id'    => 'add_img_width',
					'label' => esc_html__( 'Apply width? (Deprecated)', 'total-theme-core' ),
					'type'  => 'checkbox',
					'description' => esc_html__( 'To adjust the width of your image, please use the new "Image Container Width" field above.', 'total-theme-core' ),
				),
			),
		);

		$this->create_widget( $this->args );
	}

	/**
	 * Front-end display of widget.
	 *
	 * @see WP_Widget::widget()
	 * @since 1.0
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$instance = $this->parse_instance( $instance );

		extract( $instance );

		// Before widget hook
		echo wp_kses_post( $args['before_widget'] );

		// Display widget title
		$this->widget_title( $args, $instance );

		// Define widget output
		$output = '';
		$post_type = ! empty( $post_type ) ? sanitize_key( $post_type ) : 'post';
		$style = ! empty( $style ) ? sanitize_text_field( $style ) : 'default';

		// Custom Query args
		if ( ! empty( $custom_query_args ) && is_callable( $custom_query_args ) ) {
			$query_args = (array) call_user_func( $custom_query_args );
		}

		// Widget query args
		else {
			
			// Define query args
			$query_args = [
				'post_type'      => $post_type,
				'posts_per_page' => intval( $number ),
				'no_found_rows'  => true,
			];

			// Define tax query
			$tax_query = [];

			// Query by thumbnail meta_key
			if ( $thumbnail_query ) {
				$query_args['meta_query'] = [ [ 'key' => '_thumbnail_id' ] ];
			}

			// Order params - needs FALLBACK don't remove ( orderby used to be called order ).
			if ( ! empty( $orderby ) ) {
				$query_args['order'] = in_array( strtolower( sanitize_key( $order ) ), array_keys( $this->choices_query_order() ), true ) ? $order : 'desc';
				$query_args['orderby'] = in_array( $orderby, array_keys( $this->choices_query_orderby() ), true ) ? $orderby : 'date';
			} else {
				$query_args['orderby'] = in_array( $order, array_keys( $this->choices_query_orderby() ), true ) ? $order : 'date';
			}

			// Tax Query
			if ( ! empty( $taxonomy ) ) {
				$taxonomy = sanitize_key( $taxonomy );
				if ( taxonomy_exists( $taxonomy ) ) {
					if ( ! empty( $terms ) && $include_list = $this->parse_terms( $terms, $taxonomy ) ) {
						$tax_query['include'] = [
							'taxonomy' => $taxonomy,
							'terms'    => $include_list,
							'operator' => 'IN',
						];
					}
					if ( ! empty( $terms_exclude ) && $exclude_list = $this->parse_terms( $terms_exclude, $taxonomy ) ) {
						$tax_query['exclude'] = [
							'taxonomy' => $taxonomy,
							'terms'    => $exclude_list,
							'operator' => 'NOT IN',
						];
					}
				}
			}

			// Exclude current post
			if ( is_singular() ) {
				$query_args['post__not_in'] = [ get_the_ID() ];
			}

		}

		// Add tax query to args
		if ( $tax_query ) {
			$query_args['tax_query'] = array_merge( [ 'relation' => 'AND' ], $tax_query );
		}

		// Query posts
		if ( 'tribe_events' === $post_type && function_exists( 'tribe_get_events' ) ) {
			if ( apply_filters( 'wpex_recent_posts_thumb_widget_exclude_past_events', true ) ) {
				$query_args['ends_after'] = 'now';
			}
			unset( $query_args['order'] );
			unset( $query_args['orderby'] );
			$wpex_query = tribe_get_events( $query_args, true );
		} else {
			$wpex_query = new WP_Query( $query_args );
		}

		// If there are posts loop through them
		if ( $wpex_query->have_posts() ) :

			// Begin entries output
			$output .= '<ul class="wpex-widget-recent-posts style-' . esc_attr( $style ) . '">';

					// Loop through posts
					$count = 0;
					while ( $wpex_query->have_posts() ) : $wpex_query->the_post();
						$count++;

						$li_classes = 'wpex-widget-recent-posts-li';

						if ( 'fullimg' === $style ) {
							$li_classes .= ' wpex-mb-15';
						} else{
							$li_classes .= ' wpex-py-15 wpex-border-b wpex-border-solid wpex-border-main';
						}

						if ( 1 === $count ) {
							$li_classes .= ' wpex-border-t';
						}

						// Output entry
						$output .= '<li class="' . esc_attr( $li_classes ) . '">';

							// Open post link
							$post_link = function_exists( 'wpex_get_permalink' ) ? wpex_get_permalink() : get_permalink();

							if ( 'fullimg' === $style ) {
								$link_classes = 'wpex-block';
							} else {
								$link_classes = 'wpex-flex';
								if ( true === wp_validate_boolean( $items_center ) ) {
									$link_classes .= ' wpex-items-center';
								}
							}

							$link_classes .= ' wpex-inherit-color-important wpex-no-underline';

							$output .= '<a href="' . esc_url( $post_link ) . '" class="' . esc_attr( $link_classes ) . '">';

								// Get post title attribute
								if ( function_exists( 'wpex_get_esc_title' ) ) {
									$esc_title = wpex_get_esc_title();
								} else {
									$esc_title = the_title_attribute( array(
										'echo' => false,
									) );
								}

								// Entry thumbnail
								if ( has_post_thumbnail() ) {

									// Inline CSS
									$inline_css = '';
									if ( $add_img_width && $img_width ) {
										$inline_css = ' style="width:' . intval( $img_width ) . 'px"';
									} elseif ( 'fullimg' !== $style ) {
										if ( ! empty( $img_container_width ) ) {
											$img_container_width = sanitize_text_field( $img_container_width );
											if ( is_numeric( $img_container_width ) ) {
												$img_container_width = "{$img_container_width}px";
											}
										}
										if ( empty( $img_container_width ) ) {
											$img_container_width = '65px';
										}
										$inline_css = ' style="width:' . esc_attr( $img_container_width ) . '"';
									}

									// Thumb chasses
									$thumb_classes = 'wpex-widget-recent-posts-thumbnail wpex-self-start';

									if ( 'fullimg' === $style ) {
										$thumb_classes .= ' wpex-mb-15';
									} else {
										$thumb_classes .= ' wpex-flex-shrink-0 wpex-mr-15';
									}

									if ( $img_hover && function_exists( 'wpex_image_hover_classes' ) ) {
										$thumb_classes .= ' ' . wpex_image_hover_classes( $img_hover );
									}

									if ( $img_filter && function_exists( 'wpex_image_filter_class' ) ) {
										$thumb_classes .= ' ' . wpex_image_filter_class( $img_filter );
									}

									$output .= '<div class="' . esc_attr( $thumb_classes ) . '"' . $inline_css . '>';

										$image_class = 'wpex-align-middle';

										if ( $img_border_radius && 'square' !== $img_border_radius ) {
											$image_class .= ' wpex-' . sanitize_html_class( $img_border_radius );
										}

										if ( ! empty( $img_aspect_ratio )
											&& function_exists( 'totaltheme_get_aspect_ratio_choices' )
											&& array_key_exists( $img_aspect_ratio, totaltheme_get_aspect_ratio_choices() )
										) {
											$image_class .= ' wpex-object-cover wpex-aspect-' . str_replace( '/', '-', $img_aspect_ratio );
										}

										if ( function_exists( 'wpex_get_post_thumbnail' ) ) {
											$output .= wpex_get_post_thumbnail( [
												'size'   => $img_size,
												'width'  => $img_width,
												'height' => $img_height,
												'crop'   => $img_crop_location,
												'class'  => $image_class,
											] );
										} else {
											$output .= get_the_post_thumbnail( get_the_ID(), $img_size, [
												'class' => $image_class,
											] );
										}

									$output .= '</div>';

								}

								// Entry details
								$output .= '<div class="wpex-widget-recent-posts-details wpex-flex-grow">';

									// Display Title
									$output .= '<div class="wpex-widget-recent-posts-title wpex-heading wpex-widget-heading">' . esc_html( get_the_title() ) . '</div>';

									// Display date if enabled
									if ( '1' != $date ) {
										if ( class_exists( 'Tribe__Events__Main', false )
											&& 'tribe_events' == get_post_type()
											&& function_exists( 'wpex_get_tribe_event_date' )
										) {
											$the_date = wpex_get_tribe_event_date( 'wpex_recent_posts_thumb_widget' );
										} else {
											$the_date = get_the_date();
										}
										$output .= '<div class="wpex-widget-recent-posts-date wpex-text-sm wpex-text-3">' . esc_html( $the_date ) . '</div>';
									}

									// Display excerpt
									if ( intval( $excerpt_length ) && 0 !== $excerpt_length ) {
										if ( function_exists( 'wpex_get_excerpt' ) ) {
											$excerpt = totaltheme_get_post_excerpt( [
												'length'          => $excerpt_length,
												'context'         => 'wpex_recent_posts_thumb_widget',
												'custom_excerpts' => false,
											] );
										} else {
											$excerpt = wp_trim_words( get_the_excerpt(), absint( $excerpt_length ) );
										}
										if ( $excerpt ) {
											$output .= '<div class="wpex-widget-recent-posts-excerpt wpex-mt-5 wpex-last-mb-0">' . $excerpt . '</div>';
										}
									}

								$output .= '</div>';

							$output .= '</a>';

						$output .= '</li>';

					endwhile;

			$output .= '</ul>';

			wp_reset_postdata();

		endif;

		// Echo output.
		echo $output;

		echo wp_kses_post( $args['after_widget'] );
	}

}

register_widget( 'TotalThemeCore\\Widgets\\Widget_Recent_Posts_Thumb' );

