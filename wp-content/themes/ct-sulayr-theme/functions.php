<?php

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'parent-style', get_template_directory_uri() . '/style.css' );

		$deps = array();
		if ( wp_style_is( 'club-triatlon-sulayr-front', 'registered' ) ) {
			$deps[] = 'club-triatlon-sulayr-front';
		}

		$path = get_stylesheet_directory() . '/profile-bento.css';
		wp_enqueue_style(
			'ct-sulayr-profile-bento',
			get_stylesheet_directory_uri() . '/profile-bento.css',
			$deps,
			is_readable( $path ) ? (string) filemtime( $path ) : '1.0.0'
		);
	},
	30
);

add_action(
	'login_enqueue_scripts',
	function () {
		$path = get_stylesheet_directory() . '/assets/img/CTSulayrlogo.png';
		if ( ! is_readable( $path ) ) {
			return;
		}

		$url = get_stylesheet_directory_uri() . '/assets/img/CTSulayrlogo.png';
		$css = '.login h1 a{background-image:url("' . esc_url( $url ) . '");background-position:center;background-repeat:no-repeat;background-size:contain;height:74px;width:80px;}';
		wp_add_inline_style( 'login', $css );
	}
);

add_filter(
	'login_headerurl',
	function () {
		return home_url( '/' );
	}
);

add_filter(
	'login_headertext',
	function () {
		return get_bloginfo( 'name' );
	}
);
