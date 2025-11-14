<?php

namespace TotalThemeCore\Widgets;

use TotalThemeCore\WidgetBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Simple Menu widget.
 */
class Widget_Simple_Menu extends WidgetBuilder {

	/**
	 * Widget args.
	 */
	private $args;

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		$this->args = array(
			'id_base' => 'wpex_simple_menu',
			'name'    => $this->branding() . esc_html__( 'Simple Menu', 'total-theme-core' ),
			'options' => array(
				'description' => esc_html__( 'Displays a custom menu without any toggles or styling.', 'total-theme-core' ),
				'customize_selective_refresh' => true,
			),
			'fields'  => array(
				array(
					'id'    => 'title',
					'label' => esc_html__( 'Title', 'total-theme-core' ),
					'type'  => 'text',
				),
				array(
					'id'      => 'nav_menu',
					'label'   => esc_html__( 'Select Menu', 'total-theme-core' ),
					'type'    => 'select',
					'choices' => 'menus',
				),
			),
		);
		$this->create_widget( $this->args );
	}

	/**
	 * Front-end display of widget.
	 */
	public function widget( $args, $instance ) {
		extract( $this->parse_instance( $instance ) );
		echo wp_kses_post( $args['before_widget'] );
		$this->widget_title( $args, $instance );
		if ( $nav_menu ) {
			echo wp_nav_menu( array(
				'fallback_cb' => '',
				'menu'        => $nav_menu,
			) );
		}
		echo wp_kses_post( $args['after_widget'] );
	}

}
register_widget( 'TotalThemeCore\Widgets\Widget_Simple_Menu' );
