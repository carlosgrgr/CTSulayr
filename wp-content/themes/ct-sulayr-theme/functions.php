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

		$style = get_stylesheet_directory() . '/style.css';
		wp_enqueue_style(
			'ct-sulayr-style',
			get_stylesheet_uri(),
			array( 'astra-theme-css' ),
			is_readable( $style ) ? (string) filemtime( $style ) : '1.0.0'
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

// Tipografías de marca (front y editor de bloques)
add_action( 'enqueue_block_assets', function () {
	wp_enqueue_style(
		'sulayr-fonts',
		'https://fonts.googleapis.com/css2?family=Archivo:ital,wdth,wght@0,62..125,400..900;1,62..125,400..900&family=Instrument+Sans:wght@400;600&family=JetBrains+Mono:wght@400;600&display=swap',
		array(),
		null
	);
	// Que el editor muestre también los estilos del tema hijo
	if ( is_admin() ) {
		wp_enqueue_style( 'sulayr-editor', get_stylesheet_uri(), array(), wp_get_theme()->get( 'Version' ) );
	}
} );

// Estilos de bloque de la marca
add_action( 'init', function () {
	$estilos = array(
		'core/cover'     => array( 'sulayr-cumbres', 'Sulayr · cumbres' ),
		'core/heading'   => array( 'sulayr-display', 'Sulayr · titular' ),
		'core/paragraph' => array( 'sulayr-etiqueta', 'Sulayr · etiqueta' ),
		'core/button'    => array( 'sulayr-sol', 'Sulayr · acción' ),
		'core/table'     => array( 'sulayr-convocatoria', 'Sulayr · convocatoria' ),
		'core/quote'     => array( 'sulayr-cita', 'Sulayr · cita' ),
	);
	foreach ( $estilos as $bloque => $e ) {
		register_block_style( $bloque, array( 'name' => $e[0], 'label' => $e[1] ) );
	}
} );

// En el corte en el que Astra apila el sidebar, pliega las categorías.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_shop' ) ) {
		return;
	}
	if ( ! ( is_shop() || is_product_taxonomy() || is_product() ) ) {
		return;
	}

	$path = get_stylesheet_directory() . '/assets/js/shop-categories.js';
	wp_enqueue_script(
		'ct-sulayr-shop-categories',
		get_stylesheet_directory_uri() . '/assets/js/shop-categories.js',
		array(),
		is_readable( $path ) ? (string) filemtime( $path ) : '1.0.0',
		true
	);
}, 30 );

// Limitar menú sidebar a las páginas de productos (no carrito, checkout, cuenta)
add_filter( 'astra_page_layout', function ( $layout ) {
	if ( is_shop() || is_product_taxonomy() ) {
		return 'left-sidebar';
	}
	if ( is_cart() || is_checkout() || is_account_page() ) {
		return 'no-sidebar';
	}
	return $layout;
}, 20 );
