<?php
defined( 'ABSPATH' ) || exit;

$this->sections['wpex_header_menu'] = [
	'title' => esc_html__( 'Menu', 'total' ),
	'panel' => 'wpex_header',
	'settings' => [
		[
			'id' => 'header_menu_disable_outline',
			'transport' => 'postMessage',
			'control' => [
				'label' => esc_html__( 'Disable Outline', 'total' ),
				'type' => 'totaltheme_toggle',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'two', 'three', 'four' ],
			],
		],
		[
			'id' => 'header_menu_disable_borders',
			'transport' => 'postMessage',
			'control' => [
				'label' => esc_html__( 'Disable Inner Borders', 'total' ),
				'type' => 'totaltheme_toggle',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'two', 'six' ],
			],
		],
		[
			'id' => 'header_menu_center',
			'transport' => 'postMessage',
			'control' => [
				'label' => esc_html__( 'Center Menu Items', 'total' ),
				'type' => 'totaltheme_toggle',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'two',
			],
		],
		[
			'id' => 'header_menu_stretch_items',
			'transport' => 'postMessage',
			'control' => [
				'label' => esc_html__( 'Fill Space', 'total' ),
				'type' => 'totaltheme_toggle',
				'description' => esc_html__( 'Enable to make your menu items fill up the available navigation area.', 'total' ),
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'two', 'three', 'four', 'five' ],
			],
		],
		[
			'id' => 'menu_flush_dropdowns',
			'control' => [
				'label' => esc_html__( 'Full-Height', 'total' ),
				'type' => 'totaltheme_toggle',
				'description' => esc_html__( 'When enabled your menu li elements will display at the same height as your header so that your dropdowns line up with the bottom of the header.', 'total' ),
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'seven', 'eight', 'nine', 'ten' ],
			],
		],
		[
			'id' => 'menu_hover_on_current',
			'transport' => 'postMessage',
			'default' => true,
			'control' => [
				'label' => esc_html__( 'Enable Hover on Current Items', 'total' ),
				'type' => 'totaltheme_toggle',
				'description' => esc_html__( 'When enabled, hover styles will also apply to the currently active menu item. When disabled, active item styles take priority and hovers will not override them.', 'total' ),
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'header_menu_height',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'units' => [ 'px' ],
				'label' => esc_html__( 'Custom Menu Height', 'total' ),
				'placeholder' => '50',
			],
			'inline_css' => [
				'target' => ':root',
				'alter' => [
					'--wpex-main-nav-height',
					'--wpex-main-nav-line-height',
				],
				'sanitize' => 'px',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'five' ],
			],
		],
		[
			'id' => 'main_nav_gutter',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'exclude_units' => [ '%' ],
				'label' => esc_html__( 'Gutter', 'total' ),
				'description' => esc_html__( 'Applies a left margin to the top level menu items.', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap', // must target this to override certain things.
				'alter' => '--wpex-main-nav-gutter',
				'sanitize' => 'fallback_px',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'seven', 'eight', 'nine', 'ten' ],
			],
		],
		[
			'id' => 'main_nav_link_padding_y',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'exclude_units' => [ '%' ],
				'label' => esc_html__( 'Menu Item Vertical Padding', 'total' ),
			],
			'inline_css' => [
				'target' => '.navbar-style-seven,.navbar-style-eight,.navbar-style-nine,.navbar-style-ten',
				'alter' => '--wpex-main-nav-link-padding-y',
				'sanitize' => 'padding',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'seven', 'eight', 'nine', 'ten' ],
			],
		],
		[
			'id' => 'main_nav_link_padding_x',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'exclude_units' => [ '%' ],
				'label' => esc_html__( 'Menu Item Horizontal Padding', 'total' ),
				'description' => esc_html__( 'Applies a left and right padding to the top level menu items.', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap', // must target this to override certain things.
				'alter' => '--wpex-main-nav-link-padding-x',
				'sanitize' => 'padding',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_transition_duration',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'select',
				'label' => esc_html__( 'Link Hover Speed', 'total' ),
				'description' => esc_html__( 'Set how quickly the menu links transition when changing color or background on hover.', 'total' ),
				'choices' => 'transition_duration',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-transition-duration',
			],
		],
		/*** Active Item ***/
		[
			'id' => 'menu_active_underline',
			//'transport' => 'postMessage', // can't
			'control' => [
				'label' => esc_html__( 'Hover and Active Underline', 'total' ),
				'type' => 'totaltheme_toggle',
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::can_menu_active_underline', // can't use control display, cause the next setting needs to check for this one via active_callback.
			],
		],
		[
			'id' => 'menu_active_underline_style',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'select',
				'label' => esc_html__( 'Underline Style', 'total' ),
				'choices' => [
					''     => esc_html__( 'Animated (grow from center)', 'total' ),
					'none' => esc_html__( 'Static (no grow effect)', 'total' ),
				],
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::has_menu_active_underline',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-underline-transform',
			],
		],
		[
			'id' => 'menu_active_underline_speed',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'select',
				'label' => esc_html__( 'Underline Animation Speed', 'total' ),
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::has_menu_active_underline',
				'choices' => 'transition_duration',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-underline-transition-duration',
			],
		],
		[
			'id' => 'menu_active_underline_height',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'units' => [ 'px' ],
				'label' => esc_html__( 'Underline Size', 'total' ),
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::has_menu_active_underline',
				'placeholder' => '3',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-underline-height',
				'sanitize' => 'px',
			],
		],
		[
			'id' => 'menu_active_underline_offset',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_length_unit',
				'units' => [ 'px' ],
				'label' => esc_html__( 'Underline Offset', 'total' ),
				'description' => esc_html__( 'Adjust the vertical position of the underline relative to the text. Positive values move it closer to the text, negative values move it further away.', 'total' ),
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::has_menu_active_underline',
				'placeholder' => '0',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-underline-offset',
				'sanitize' => 'px',
			],
		],
		[
			'id' => 'menu_active_underline_color',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Underline Color', 'total' ),
				'active_callback' => 'TotalTheme\Customizer\Active_Callbacks::has_menu_active_underline',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-link-underline-color',
			],
		],
		[
			'id' => 'menu_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Background', 'total' ),
				'description' => esc_html__( 'Not applied to the transparent header.', 'total' ),
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-bg',
			],
		],
		[
			'id' => 'menu_borders',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Borders', 'total' ),
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'two', 'three', 'four', 'six' ],
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-main-nav-border-color',
			],
		],
		// Menu Link
		[
			'id' => 'menu_link_color',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Color', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => [
					'--wpex-main-nav-link-color',
					'--wpex-hover-main-nav-link-color',
					'--wpex-active-main-nav-link-color',
				],
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_color_hover',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Color: Hover', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-hover-main-nav-link-color',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_color_active',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Color: Current Menu Item', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-active-main-nav-link-color',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_border_radius',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'select',
				'label' => esc_html__( 'Link Border Radius', 'total' ),
				'choices' => 'border_radius',
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'sanitize' => 'utl_border_radius',
				'alter' => [
					'--wpex-main-nav-link-border-radius',
				],
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Background', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => [
					'--wpex-main-nav-link-bg',
					'--wpex-hover-main-nav-link-bg',
					'--wpex-active-main-nav-link-bg',
				],
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_hover_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Background: Hover', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-hover-main-nav-link-bg',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		[
			'id' => 'menu_link_active_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Background: Current Menu Item', 'total' ),
			],
			'inline_css' => [
				'target' => '#site-navigation-wrap',
				'alter' => '--wpex-active-main-nav-link-bg',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => 'dev',
				'compare' => 'not_equal',
			],
		],
		// Menu Link Inner
		[
			'id' => 'menu_link_span_border_radius',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'select',
				'label' => esc_html__( 'Link Inner Border Radius', 'total' ),
				'choices' => 'border_radius',
			],
			'inline_css' => [
				'target' => '.navbar-allows-inner-bg',
				'sanitize' => 'utl_border_radius',
				'alter' => [
					'--wpex-main-nav-link-inner-border-radius',
				],
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'five', 'six' ],
			],
		],
		[
			'id' => 'menu_link_span_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Inner Background', 'total' ),
			],
			'inline_css' => [
				'target' => '.navbar-allows-inner-bg',
				'alter' => [
					'--wpex-main-nav-link-inner-bg',
					'--wpex-hover-main-nav-link-inner-bg',
					'--wpex-active-main-nav-link-inner-bg'
				],
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'five', 'six' ],
			],
		],
		[
			'id' => 'menu_link_span_hover_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Inner Background: Hover', 'total' ),
			],
			'inline_css' => [
				'target' => '.navbar-allows-inner-bg',
				'alter' => '--wpex-hover-main-nav-link-inner-bg',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'five', 'six' ],
			],
		],
		[
			'id' => 'menu_link_span_active_background',
			'transport' => 'postMessage',
			'control' => [
				'type' => 'totaltheme_color',
				'label' => esc_html__( 'Link Inner Background: Current Menu Item', 'total' ),
			],
			'inline_css' => [
				'target' => '.navbar-allows-inner-bg',
				'alter' => '--wpex-active-main-nav-link-inner-bg',
			],
			'control_display' => [
				'check' => 'header_style',
				'value' => [ 'one', 'two', 'three', 'four', 'five', 'six' ],
			],
		],
	],
];
