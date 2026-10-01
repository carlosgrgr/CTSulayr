<?php
/**
 * Plugin Name:       Sulayr Reservas de natación
 * Description:       Reservas de sesiones de natación para el sitio de CN Nazarí, con sincronización desde el sitio del club.
 * Version:           0.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Club Triatlón Sulayr
 * Text Domain:       sulayr-reservas
 * Network:           true
 *
 * @package SulayrReservas
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SULAYR_RESERVAS_VERSION', '0.1.0' );
define( 'SULAYR_RESERVAS_FILE', __FILE__ );
define( 'SULAYR_RESERVAS_DIR', plugin_dir_path( __FILE__ ) );

spl_autoload_register(
	static function ( $class ) {
		$prefix = 'SulayrReservas\\';
		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$path     = SULAYR_RESERVAS_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require $path;
		}
	}
);

register_activation_hook( __FILE__, array( 'SulayrReservas\\Activation\\Activator', 'activate' ) );

add_action(
	'plugins_loaded',
	static function () {
		( new SulayrReservas\Plugin() )->init();
	}
);
