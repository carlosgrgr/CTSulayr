<?php

namespace TotalThemeCore\Widgets;

use TotalThemeCore\WidgetBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Template widget.
 */
class Widget_Templatera extends WidgetBuilder {

	/**
	 * Widget args.
	 */
	private $args;

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$this->args = [
			'id_base' => 'wpex_templatera',
			'name'    => $this->branding() . esc_html__( 'Template', 'total-theme-core' ),
			'options' => [
				'customize_selective_refresh' => true,
			],
			'fields'  => [
				[
					'id'    => 'title',
					'label' => esc_html__( 'Title', 'total-theme-core' ),
					'type'  => 'text',
				],
				[
					'id'    => 'template',
					'label' => esc_html__( 'Template', 'total-theme-core' ),
					'type'  => 'select_template',
				],
			],
		];

		$this->create_widget( $this->args );
	}

	/**
	 * Front-end display of widget.
	 */
	public function widget( $args, $instance ) {
		extract( $this->parse_instance( $instance ) );

		echo wp_kses_post( $args['before_widget'] );

		$this->widget_title( $args, $instance );

		if ( ! empty( $template ) ) {
			if ( function_exists( 'totaltheme_render_template' ) ) {
				$template_content = totaltheme_render_template( $template );
			} else {
				$temp_post = get_post( $template );
				$template_content = ! empty( $temp_post->post_content )
					? do_shortcode( wp_kses_post( $temp_post->post_content ) )
					: '';
			}
			if ( $template_content ) {
				echo '<div class="wpex-templatera-widget-content wpex-clr">' . $template_content . '</div>';
			}
		}

		echo wp_kses_post( $args['after_widget'] );
	}

}

register_widget( 'TotalThemeCore\\Widgets\\Widget_Templatera' );
