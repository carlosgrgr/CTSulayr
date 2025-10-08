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

define( 'SULAYR_LANG_DIR', plugin_dir_path( __FILE__ ) . '/languages' );
define( 'SULAYR_FILE', __FILE__ );
define( 'SULAYR_DIR', plugin_dir_path( __FILE__ ) );
define( 'SULAYR_URL', plugin_dir_url( __FILE__ ) );
define( 'SULAYR_PUBLIC_ASSETS', SULAYR_URL . '/public' );
define( 'SULAYR_ADMIN_ASSETS', SULAYR_URL . '/admin' );

define( 'SULAYR_NAME', 'Plugin Boilerplate' );
define( 'SULAYR_VERSION', '0.3.0' );
define( 'SULAYR_TEXTDOMAIN', 'club-triatlon-sulayr' );
