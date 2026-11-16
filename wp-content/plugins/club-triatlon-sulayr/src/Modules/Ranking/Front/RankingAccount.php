<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Front;

use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mi cuenta → «Ranking interno» (SPEC-RANKING-INTERNO §8.1, R16).
 *
 * Endpoint de WooCommerce con el mismo patrón que ReceiptsAccount. La
 * pestaña solo la ven los socios activos y congelados (R37).
 */
class RankingAccount {

    public const ENDPOINT = 'ranking-interno';

    private const ENDPOINT_VERSION = 1;
    private const ENDPOINT_VERSION_OPTION = 'sulayr_ranking_endpoint_version';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_filter( 'woocommerce_get_query_vars', [ $this, 'add_query_var' ] );
        add_action( 'init', [ $this, 'maybe_request_rewrite_flush' ], 20 );
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 14 );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', [ $this, 'title' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render' ] );
    }

    /**
     * URL de la pestaña.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url(): string {
        return function_exists( 'wc_get_endpoint_url' ) ? wc_get_endpoint_url( self::ENDPOINT, '', wc_get_page_permalink( 'myaccount' ) ) : home_url( '/' );
    }

    /**
     * @param   array $vars Query vars de WooCommerce.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_query_var( array $vars ): array {
        $vars[ self::ENDPOINT ] = self::ENDPOINT;
        return $vars;
    }

    /**
     * Pide regenerar los enlaces la primera vez (WooCommerceIntegration lo hace en init).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_request_rewrite_flush(): void {
        if ( self::ENDPOINT_VERSION === (int) get_option( self::ENDPOINT_VERSION_OPTION ) ) {
            return;
        }

        update_option( self::ENDPOINT_VERSION_OPTION, self::ENDPOINT_VERSION );
        update_option( 'sulayr_wc_flush_rewrite_rules', '1' );
    }

    /**
     * Pestaña detrás de «Mis recibos», solo para socios activos y congelados.
     *
     * @param   array $items Ítems.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_menu_item( array $items ): array {
        if ( false === RankingView::can_view() ) {
            return $items;
        }

        $label = RankingSettings::account_label();
        $new = [];
        $added = false;
        foreach ( $items as $key => $value ) {
            $new[ $key ] = $value;
            if ( ReceiptsAccount::ENDPOINT === $key ) {
                $new[ self::ENDPOINT ] = $label;
                $added = true;
            }
        }
        if ( false === $added ) {
            // Antes de «Salir».
            $logout = $new['customer-logout'] ?? null;
            unset( $new['customer-logout'] );
            $new[ self::ENDPOINT ] = $label;
            if ( null !== $logout ) {
                $new['customer-logout'] = $logout;
            }
        }

        return $new;
    }

    /**
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function title(): string {
        return RankingSettings::account_label();
    }

    /**
     * Contenido de la pestaña.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        echo RankingView::render( self::url() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Las plantillas escapan.
    }
}
