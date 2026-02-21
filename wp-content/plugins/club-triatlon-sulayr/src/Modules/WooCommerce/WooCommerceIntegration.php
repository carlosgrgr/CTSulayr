<?php
/**
 * Opción 2: Un solo panel = WooCommerce My Account.
 * Integra la página de deportista (Mi perfil) como endpoint de Mi cuenta.
 * Solo usuarios con rol athlete ven este ítem en el menú.
 */

namespace ClubTriatlonSulayr\Modules\WooCommerce;

use ClubTriatlonSulayr\Services\UserService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerceIntegration {

    private const ATHLETE_ROLE = 'athlete';

    /** Endpoint de Mi cuenta para athletes (perfil ACF del deportista). */
    private const ENDPOINT_PERFIL_DEPORTISTA = 'perfil-deportista';

    public function __construct() {
        add_filter( 'woocommerce_get_query_vars', [ $this, 'add_account_query_vars' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_athlete_menu_items' ], 10, 2 );
        add_filter( 'woocommerce_login_redirect', [ $this, 'redirect_athlete_to_my_account' ], 10, 2 );
        add_filter( 'body_class', [ $this, 'add_athlete_body_class' ] );

        add_action( 'woocommerce_account_' . self::ENDPOINT_PERFIL_DEPORTISTA . '_endpoint', [ $this, 'output_perfil_deportista_endpoint' ] );

        add_action( 'template_redirect', [ $this, 'acf_form_head_on_perfil_deportista' ], 5 );
        add_action( 'template_redirect', [ $this, 'redirect_old_athlete_pages_to_wc' ], 15 );

        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_PERFIL_DEPORTISTA . '_title', [ $this, 'endpoint_title_perfil_deportista' ], 10, 3 );

        add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 999 );
    }

    /**
     * Si está marcado (tras activar el plugin), hacer flush de rewrite rules para que los endpoints de athlete se registren.
     */
    public function maybe_flush_rewrite_rules(): void {
        if ( get_option( 'sulayr_wc_flush_rewrite_rules' ) !== '1' ) {
            return;
        }
        delete_option( 'sulayr_wc_flush_rewrite_rules' );
        flush_rewrite_rules();
    }

    /**
     * Añade el endpoint Perfil de deportista a la lista de WooCommerce.
     */
    public function add_account_query_vars( array $vars ): array {
        $vars[ self::ENDPOINT_PERFIL_DEPORTISTA ] = self::ENDPOINT_PERFIL_DEPORTISTA;
        return $vars;
    }

    /**
     * Añade "Perfil de deportista" al menú de Mi cuenta solo para athletes.
     */
    public function add_athlete_menu_items( array $items, array $endpoints ): array {
        if ( ! $this->is_athlete() ) {
            return $items;
        }

        $new = [];
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( 'dashboard' === $key ) {
                $new[ self::ENDPOINT_PERFIL_DEPORTISTA ] = __( 'Perfil de deportista', SULAYR_TEXTDOMAIN );
            }
        }

        return $new;
    }

    /**
     * Tras login en Mi cuenta, redirigir athletes a My Account.
     */
    public function redirect_athlete_to_my_account( string $redirect, \WP_User $user ): string {
        if ( $user->exists() && in_array( self::ATHLETE_ROLE, (array) $user->roles, true ) ) {
            return wc_get_page_permalink( 'myaccount' );
        }
        return $redirect;
    }

    /**
     * Llamar acf_form_head() cuando se visita el endpoint Perfil de deportista (antes de cualquier output).
     */
    public function acf_form_head_on_perfil_deportista(): void {
        global $wp;

        if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
            return;
        }
        if ( ! isset( $wp->query_vars[ self::ENDPOINT_PERFIL_DEPORTISTA ] ) ) {
            return;
        }
        if ( ! function_exists( 'acf_form_head' ) ) {
            return;
        }
        acf_form_head();
    }

    /**
     * Contenido del endpoint Perfil de deportista (perfil ACF).
     */
    public function output_perfil_deportista_endpoint(): void {
        if ( ! $this->is_athlete() ) {
            return;
        }

        $current_user_id = get_current_user_id();
        $current_user    = wp_get_current_user();
        $user_service    = new UserService();
        $user_data      = $user_service->get_athlete_formatted_data( $current_user_id );

        $user_name                 = $current_user->display_name ?: $current_user->user_login;
        $user_full_name            = $user_data['name'] ?: $user_name;
        $user_email                = $current_user->user_email;
        $user_profile_photo_url    = $user_data['photo_url'];
        $user_profile_phone        = $user_data['phone'];
        $user_profile_dni          = $user_data['dni'];
        $user_profile_birth_formatted = $user_data['birth_date_formatted'] ?: '-';
        $user_profile_plan_label   = $user_data['plan'] ?: '-';
        $user_profile_plan_value   = $user_data['plan_value'] ?: '';
        $user_profile_training_type = $user_data['training_type'] ?: '';
        $user_profile_swimming_label = $user_data['swimming_label'] ?: '-';
        $user_profile_join_date_formatted = $user_data['join_date_formatted'] ?: '-';
        $show_training_type        = $user_profile_plan_value === 'premium';

        $active_tab = 'info';
        if ( isset( $_GET['tab'] ) && $_GET['tab'] === 'edit' ) {
            $active_tab = 'edit';
        } elseif ( isset( $_GET['updated'] ) && $_GET['updated'] === 'true' ) {
            $active_tab = 'edit';
        }

        $return_url = add_query_arg(
            [ 'updated' => 'true', 'tab' => 'edit' ],
            wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) )
        );

        $profile_part = SULAYR_TEMPLATE_DIR . '/parts/content-athlete-profile.php';
        if ( file_exists( $profile_part ) ) {
            include $profile_part;
        }
    }

    /**
     * Redirige athletes que visiten la página standalone de perfil al panel WC (/mi-cuenta/perfil-deportista/).
     */
    public function redirect_old_athlete_pages_to_wc(): void {
        if ( ! $this->is_athlete() || ! function_exists( 'wc_get_page_permalink' ) ) {
            return;
        }

        $target_url = wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) );

        if ( is_page( 'perfil-deportista' ) || is_page( 'mi-perfil' ) ) {
            wp_safe_redirect( $target_url );
            exit;
        }
    }

    public function endpoint_title_perfil_deportista( $title, $endpoint, $action ): string {
        return __( 'Perfil de deportista', SULAYR_TEXTDOMAIN );
    }

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
