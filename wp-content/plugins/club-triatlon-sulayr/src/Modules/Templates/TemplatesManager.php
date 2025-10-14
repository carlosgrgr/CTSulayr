<?php
namespace ClubTriatlonSulayr\Modules\Templates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class TemplatesManager {

    public function __construct() {
        add_filter('theme_page_templates', [$this, 'register_custom_template']);
        add_filter('template_include', [$this, 'load_custom_template']);
    }

    /**
     * Añade la plantilla al selector del admin
     */
    public function register_custom_template($templates) {
        $templates['page-user-profile.php'] = __('Mi Perfil', SULAYR_TEXTDOMAIN);
        return $templates;
    }

    /**
     * Indica a WP que use la plantilla del plugin
     */
    public function load_custom_template($template) {
        if (is_page_template('page-user-profile.php')) {
            $plugin_template = plugin_dir_path( SULAYR_FILE ) . 'templates/page-user-profile.php';
            if (file_exists($plugin_template)) {
                return $plugin_template;
            }
        }
        return $template;
    }
}