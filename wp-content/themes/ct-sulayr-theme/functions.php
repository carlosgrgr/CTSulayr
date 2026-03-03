<?php
/**
 * WPTheme functions and definitions
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package ct-sulayr-theme
 */

if ( false === defined( 'ABSPATH' ) ) {
    exit;
}

require_once 'vendor/autoload.php';

define( 'CT_SULAYR_THEME_DIR', get_stylesheet_directory() );
define( 'CT_SULAYR_THEME_URL', get_stylesheet_directory_uri() );
define( 'CT_SULAYR_THEME_LANG_DIR', CT_SULAYR_THEME_URL . '/languages' );
define( 'CT_SULAYR_THEME_ASSETS_DIR', CT_SULAYR_THEME_URL . '/assets' );
define( 'CT_SULAYR_THEME_VERSION', '1.0.0' );
define( 'CT_SULAYR_THEME_NAME', 'CT Sulayr Theme' );
define( 'CT_SULAYR_THEME_SLUG', 'ct_sulayr_theme' );
define( 'CT_SULAYR_THEME_TEXTDOMAIN', 'ct_sulayr_theme' );

use CTSulayrTheme\App\App;
$ct_sulayr_theme = new App;
