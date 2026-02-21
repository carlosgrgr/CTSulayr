<?php
/**
 * Integración con WooCommerce: panel de deportista unificado con Mi cuenta.
 *
 * - Usa una plantilla propia de "Mi cuenta" para usuarios con rol athlete (mismo layout que Mi perfil).
 * - Añade clase body para estilos específicos si se necesitan.
 */

namespace ClubTriatlonSulayr\Modules\WooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerceIntegration {

    private const ATHLETE_ROLE = 'athlete';

    /** Ruta relativa del template override dentro del plugin. */
    private const MY_ACCOUNT_TEMPLATE = 'woocommerce/myaccount/my-account.php';

    public function __construct() {
        add_filter( 'woocommerce_locate_template', [ $this, 'locate_athlete_my_account_template' ], 10, 3 );
        add_filter( 'body_class', [ $this, 'add_athlete_body_class' ] );
    }

    /**
     * Para deportistas, usar la plantilla del plugin que integra el menú del panel Sulayr.
     */
    public function locate_athlete_my_account_template( string $template, string $template_name, string $template_path ): string {
        if ( $template_name !== self::MY_ACCOUNT_TEMPLATE || ! $this->is_athlete() ) {
            return $template;
        }

        $plugin_template = SULAYR_DIR . 'templates/' . $template_name;
        return file_exists( $plugin_template ) ? $plugin_template : $template;
    }

    /**
     * Añade clase CSS al body para usuarios con rol athlete.
     */
    public function add_athlete_body_class( array $classes ): array {
        if ( $this->is_athlete() ) {
            $classes[] = 'sulayr-athlete';
        }
        return $classes;
    }

    private function is_athlete(): bool {
        $user = wp_get_current_user();
        return $user->exists() && in_array( self::ATHLETE_ROLE, (array) $user->roles, true );
    }
}
