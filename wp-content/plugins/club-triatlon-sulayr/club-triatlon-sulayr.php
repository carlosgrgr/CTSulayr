<?php
/*
Plugin Name: Plugin Club Triatlon Sulayr
Plugin URI: https://clubtriatlonsulayr.com
Description: Plugin with functions for Club Triatlon Sulayr webpage
Version: 1.0.0
Author: Carlos García Rodríguez
Author URI: https://clubtriatlonsulayr.com
License: GPLv2 or later
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  club-triatlon-sulayr
Domain Path:  /languages
*/

if ( false === defined( 'ABSPATH' ) ) {
    exit;
}

require_once 'vendor/autoload.php';

use ClubTriatlonSulayr\App\App;

$sulayr = new App();

register_activation_hook( __FILE__, function () {
    update_option( 'sulayr_wc_flush_rewrite_rules', '1' );
} );

define( 'SULAYR_LANG_DIR', plugin_dir_path( __FILE__ ) . '/languages' );
define( 'SULAYR_FILE', __FILE__ );
define( 'SULAYR_DIR', plugin_dir_path( __FILE__ ) );
define( 'SULAYR_URL', plugin_dir_url( __FILE__ ) );
define( 'SULAYR_PUBLIC_ASSETS', SULAYR_URL . '/public' );
define( 'SULAYR_ADMIN_ASSETS', SULAYR_URL . '/admin' );
define( 'SULAYR_TEMPLATE_DIR', SULAYR_DIR . 'templates' );

define( 'SULAYR_NAME', 'Plugin Club Triatlon Sulayr' );
define( 'SULAYR_VERSION', '0.3.0' );
define( 'SULAYR_TEXTDOMAIN', 'club-triatlon-sulayr' );

/**
 * Función helper global para cargar plantillas (wrapper de TemplateService)
 * 
 * Esta función permite usar las plantillas desde cualquier lugar,
 * incluyendo dentro de las propias plantillas.
 * 
 * Se define en el archivo principal del plugin para asegurar que esté
 * disponible inmediatamente cuando se necesite.
 *
 * @param string $template_name Nombre de la plantilla (sin extensión .php)
 * @param array  $vars          Variables a pasar a la plantilla
 * @return void
 */
if ( ! function_exists( 'sulayr_load_template' ) ) {
    function sulayr_load_template( string $template_name, array $vars = [] ): void {
        \ClubTriatlonSulayr\Services\TemplateService::load( $template_name, $vars );
    }
}
