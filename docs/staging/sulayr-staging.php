<?php
/**
 * Plugin Name: Sulayr Staging Guard
 * Description: Solo en staging. Bloquea todos los correos (quedan en el log), impide la indexación y muestra un aviso de entorno. No hace nada fuera de staging.
 *
 * Se copia a wp-content/mu-plugins/ del servidor de staging (ver docs/STAGING.md).
 * No se sube a PROD; aun así, si llegara allí, no haría nada porque PROD no define
 * WP_ENVIRONMENT_TYPE = 'staging'.
 *
 * PHP 7.4.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_get_environment_type' ) || 'staging' !== wp_get_environment_type() ) {
	return;
}

/*
 * 1. Ningún correo sale de staging. pre_wp_mail (WP 5.7+) corta wp_mail()
 *    antes de que llegue a WP Mail SMTP o a PHPMailer. Devolver true hace
 *    creer al código que el envío fue bien, así los flujos siguen igual.
 */
add_filter(
	'pre_wp_mail',
	static function ( $return, $atts ) {
		$to = isset( $atts['to'] ) ? $atts['to'] : '';
		if ( is_array( $to ) ) {
			$to = implode( ', ', $to );
		}
		$subject = isset( $atts['subject'] ) ? $atts['subject'] : '';
		error_log( sprintf( '[staging] Correo NO enviado. Para: %s | Asunto: %s', $to, $subject ) );
		return true;
	},
	1,
	2
);

/*
 * 2. Que ningún buscador indexe staging, aunque alguien active
 *    «Visibilidad en buscadores» en Ajustes → Lectura.
 */
add_filter( 'pre_option_blog_public', '__return_zero' );
add_action(
	'send_headers',
	static function () {
		header( 'X-Robots-Tag: noindex, nofollow', true );
	}
);

/*
 * 3. Aviso visible para no confundir staging con PROD.
 */
add_action(
	'admin_bar_menu',
	static function ( $bar ) {
		$bar->add_node(
			array(
				'id'    => 'sulayr-staging',
				'title' => 'STAGING · los correos no se envían',
				'meta'  => array( 'html' => '<style>#wp-admin-bar-sulayr-staging>.ab-item{background:#F46F5C!important;color:#fff!important;font-weight:700}</style>' ),
			)
		);
	},
	1
);
