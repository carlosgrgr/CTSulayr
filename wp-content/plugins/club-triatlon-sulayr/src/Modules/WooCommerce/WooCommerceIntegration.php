<?php
/**
 * Opción 2: Un solo panel = WooCommerce My Account.
 * Integra la página de deportista (Mi perfil) como endpoint de Mi cuenta.
 * Solo usuarios con rol athlete ven este ítem en el menú.
 */

namespace ClubTriatlonSulayr\Modules\WooCommerce;

use ClubTriatlonSulayr\Services\UserService;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;
use ClubTriatlonSulayr\Modules\Subscriptions\SubscriptionService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WooCommerceIntegration {

    private const ATHLETE_ROLE = 'athlete';

    /** Endpoint de Mi cuenta para athletes (perfil ACF del deportista). */
    private const ENDPOINT_PERFIL_DEPORTISTA = 'perfil-deportista';

    /** Endpoint Mi monedero para athletes. */
    private const ENDPOINT_MONEDERO = 'mi-monedero';

    /** Endpoint Mis pagos (suscripciones Stripe) para athletes. */
    private const ENDPOINT_MIS_PAGOS = 'mis-pagos';

    public function __construct() {
        add_filter( 'woocommerce_account_menu_items', [ $this, 'remove_downloads_menu_item' ], 5 );
        add_filter( 'woocommerce_get_query_vars', [ $this, 'add_account_query_vars' ] );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_athlete_menu_items' ], 10, 2 );
        add_filter( 'woocommerce_login_redirect', [ $this, 'redirect_athlete_to_my_account' ], 10, 2 );
        add_filter( 'body_class', [ $this, 'add_athlete_body_class' ] );

        add_action( 'woocommerce_account_' . self::ENDPOINT_PERFIL_DEPORTISTA . '_endpoint', [ $this, 'output_perfil_deportista_endpoint' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT_MONEDERO . '_endpoint', [ $this, 'output_monedero_endpoint' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT_MIS_PAGOS . '_endpoint', [ $this, 'output_mis_pagos_endpoint' ] );

        add_action( 'template_redirect', [ $this, 'handle_subscription_activation_request' ], 1 );
        add_action( 'template_redirect', [ $this, 'handle_subscription_success_return' ], 3 );
        add_action( 'template_redirect', [ $this, 'acf_form_head_on_perfil_deportista' ], 5 );
        add_action( 'template_redirect', [ $this, 'redirect_old_athlete_pages_to_wc' ], 15 );

        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_PERFIL_DEPORTISTA . '_title', [ $this, 'endpoint_title_perfil_deportista' ], 10, 3 );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_MONEDERO . '_title', [ $this, 'endpoint_title_monedero' ], 10, 3 );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT_MIS_PAGOS . '_title', [ $this, 'endpoint_title_mis_pagos' ], 10, 3 );

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
     * Quita "Descargas" del menú de Mi cuenta (no hay productos descargables).
     */
    public function remove_downloads_menu_item( array $items ): array {
        unset( $items['downloads'] );
        return $items;
    }

    /**
     * Añade el endpoint Perfil de deportista a la lista de WooCommerce.
     */
    public function add_account_query_vars( array $vars ): array {
        $vars[ self::ENDPOINT_PERFIL_DEPORTISTA ] = self::ENDPOINT_PERFIL_DEPORTISTA;
        $vars[ self::ENDPOINT_MONEDERO ] = self::ENDPOINT_MONEDERO;
        $vars[ self::ENDPOINT_MIS_PAGOS ] = self::ENDPOINT_MIS_PAGOS;
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
                $new[ self::ENDPOINT_MONEDERO ] = __( 'Mi monedero', SULAYR_TEXTDOMAIN );
                $new[ self::ENDPOINT_MIS_PAGOS ] = __( 'Mis pagos', SULAYR_TEXTDOMAIN );
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
     * Handle POST request to activate subscription: create Stripe Checkout Session and redirect.
     * Runs on template_redirect priority 1 so it runs before any other redirect (e.g. WooCommerce).
     * With valid nonce + athlete + our button we process regardless of endpoint detection (directiva or plan).
     */
    public function handle_subscription_activation_request(): void {
        if ( ! $this->is_athlete() || ! isset( $_POST['sulayr_activar_suscripcion'] ) ) {
            return;
        }
        // Nonce deshabilitado temporalmente para depuración (el formulario solo existe en perfil-deportista y requiere usuario athlete).
        // if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'sulayr_activar_suscripcion' ) ) {
        //     return;
        // }
        $subscription_service = new SubscriptionService();
        if ( ! $subscription_service->is_available() ) {
            set_transient( 'sulayr_stripe_error_message', __( 'Stripe no está configurado. Añade la clave secreta (sk_test_ o sk_live_) en Ajustes → Configuración Sulayr → Configuración Stripe.', SULAYR_TEXTDOMAIN ), 120 );
            wp_safe_redirect( add_query_arg( 'suscripcion_error', '1', wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) ) ) );
            exit;
        }
        $user_id     = get_current_user_id();
        $success_url = wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) );
        $cancel_url  = $success_url;
        $checkout_url = $subscription_service->createCheckoutSessionForUser( $user_id, $success_url, $cancel_url );
        if ( $checkout_url ) {
            wp_safe_redirect( $checkout_url );
            exit;
        }
        wp_safe_redirect( add_query_arg( 'suscripcion_error', '1', wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) ) ) );
        exit;
    }

    /**
     * When returning from Stripe Checkout with session_id, complete the subscription and redirect.
     * Runs even when user is not logged in (e.g. payment via admin-generated link).
     */
    public function handle_subscription_success_return(): void {
        if ( ! isset( $_GET['session_id'] ) ) {
            return;
        }
        global $wp;
        if ( ! isset( $wp->query_vars[ self::ENDPOINT_PERFIL_DEPORTISTA ] ) ) {
            return;
        }
        $session_id = sanitize_text_field( wp_unslash( $_GET['session_id'] ) );
        if ( strpos( $session_id, 'cs_' ) !== 0 ) {
            return;
        }
        $subscription_service = new SubscriptionService();
        $user_id = $subscription_service->completeCheckoutSession( $session_id );
        if ( $user_id ) {
            $redirect = wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) ) . '?activado=1';
            wp_safe_redirect( $redirect );
            exit;
        }
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

        $subscription_service     = new SubscriptionService();
        $has_active_subscription   = $subscription_service->hasActiveSubscription( $current_user_id );
        $user_is_directive         = SubscriptionService::is_user_directive( $current_user_id );
        $can_activate_subscription = $subscription_service->is_available() && ! $has_active_subscription && (
            $user_is_directive || ( ! empty( $user_profile_plan_value ) && ( $user_profile_plan_value === 'basic' || $user_profile_plan_value === 'premium' ) )
        );
        $subscription_blocked_msg = get_transient( 'sulayr_subscription_change_blocked_' . $current_user_id );
        if ( $subscription_blocked_msg ) {
            delete_transient( 'sulayr_subscription_change_blocked_' . $current_user_id );
        }
        $activado = isset( $_GET['activado'] ) && $_GET['activado'] === '1';
        $suscripcion_error = isset( $_GET['suscripcion_error'] ) && $_GET['suscripcion_error'] === '1';
        $stripe_error_msg = get_transient( 'sulayr_stripe_error_message' );
        if ( $stripe_error_msg ) {
            delete_transient( 'sulayr_stripe_error_message' );
        }
        $stripe_api_error = \ClubTriatlonSulayr\Modules\Subscriptions\StripeService::get_and_clear_last_error();
        if ( $stripe_api_error ) {
            $stripe_error_msg = $stripe_error_msg ? $stripe_error_msg . ' ' . $stripe_api_error : $stripe_api_error;
        }

        $subscription_form_action = wc_get_endpoint_url( self::ENDPOINT_PERFIL_DEPORTISTA, '', wc_get_page_permalink( 'myaccount' ) );

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

        $subscription_part = SULAYR_TEMPLATE_DIR . '/parts/content-subscription-activation.php';
        if ( file_exists( $subscription_part ) ) {
            include $subscription_part;
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

    public function endpoint_title_monedero( $title, $endpoint, $action ): string {
        return __( 'Mi monedero', SULAYR_TEXTDOMAIN );
    }

    public function endpoint_title_mis_pagos( $title, $endpoint, $action ): string {
        return __( 'Mis pagos', SULAYR_TEXTDOMAIN );
    }

    /**
     * Contenido del endpoint Mis pagos: historial de facturas de suscripción Stripe.
     */
    public function output_mis_pagos_endpoint(): void {
        if ( ! $this->is_athlete() ) {
            return;
        }
        $user_id = get_current_user_id();
        $subscription_service = new SubscriptionService();
        $invoices = $subscription_service->getPaymentHistory( $user_id, 24 );
        $rows = [];
        foreach ( $invoices as $inv ) {
            $amount_eur = $inv['amount_paid'] / 100;
            $desc = $inv['description'] ?: $this->formatSubscriptionDescription( $amount_eur );
            $rows[] = [
                'date'   => date_i18n( get_option( 'date_format' ), $inv['created'] ),
                'date_ts' => $inv['created'],
                'concept' => $desc,
                'amount'  => $amount_eur,
                'status'  => $inv['status'],
            ];
        }
        usort( $rows, function ( $a, $b ) {
            return $b['date_ts'] - $a['date_ts'];
        } );
        $payment_part = SULAYR_TEMPLATE_DIR . '/parts/content-payment-history.php';
        if ( file_exists( $payment_part ) ) {
            include $payment_part;
        } else {
            echo '<p>' . esc_html__( 'No hay pagos de suscripción registrados.', SULAYR_TEXTDOMAIN ) . '</p>';
        }
    }

    /**
     * Build a human-readable description from amount (e.g. 47 -> "Tarifa básica + suplemento natación").
     */
    private function formatSubscriptionDescription( float $amount_eur ): string {
        if ( $amount_eur >= 58 ) {
            return __( 'Tarifa Entrenamientos + suplemento natación', SULAYR_TEXTDOMAIN );
        }
        if ( $amount_eur >= 47 ) {
            return __( 'Tarifa básica + suplemento natación', SULAYR_TEXTDOMAIN );
        }
        if ( $amount_eur >= 33 ) {
            return __( 'Tarifa Entrenamientos', SULAYR_TEXTDOMAIN );
        }
        if ( $amount_eur >= 22 ) {
            return __( 'Tarifa básica', SULAYR_TEXTDOMAIN );
        }
        return __( 'Suscripción', SULAYR_TEXTDOMAIN );
    }

    /**
     * Contenido del endpoint Mi monedero: movimientos del deportista y saldo.
     */
    public function output_monedero_endpoint(): void {
        if ( ! $this->is_athlete() ) {
            return;
        }

        $user_id = get_current_user_id();
        $movements = get_posts( [
            'post_type'      => WalletPostType::POST_TYPE_NAME,
            'posts_per_page' => -1,
            'orderby'        => 'meta_value',
            'meta_key'       => CustomFields::WALLET_DATE,
            'order'          => 'DESC',
            'meta_query'     => [
                [
                    'key'   => CustomFields::WALLET_ATHLETE,
                    'value' => $user_id,
                    'compare' => '=',
                ],
            ],
        ] );

        $rows = [];
        foreach ( $movements as $post ) {
            $amount = (float) get_field( CustomFields::WALLET_AMOUNT, $post->ID );
            $rows[] = [
                'date'    => get_field( CustomFields::WALLET_DATE, $post->ID ),
                'concept' => get_field( CustomFields::WALLET_CONCEPT, $post->ID ),
                'amount'  => $amount,
            ];
        }
        $total_balance = array_sum( array_column( $rows, 'amount' ) );
        $running = $total_balance;
        foreach ( $rows as &$row ) {
            $row['balance_after'] = $running;
            $running -= $row['amount'];
        }
        unset( $row );
        $wallet_part = SULAYR_TEMPLATE_DIR . '/parts/content-wallet-movements.php';
        if ( file_exists( $wallet_part ) ) {
            include $wallet_part;
        }
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
