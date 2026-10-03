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
