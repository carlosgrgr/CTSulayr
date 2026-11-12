<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Modules\Members\Lifecycle\FeesAccount;
use ClubTriatlonSulayr\Modules\Members\MemberDates;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mi cuenta → Mis recibos (D53).
 *
 * Todos los recibos del socio, del más reciente al más antiguo, en cualquier
 * estado (también «No aplica» y «Anulado»), con líneas, pagos, pendiente y
 * PDF. El PDF se abre con ?recibo=ID en esta pestaña y solo para recibos del
 * propio socio (D6).
 */
class ReceiptsAccount {

    public const ENDPOINT = 'mis-recibos';

    private const ENDPOINT_VERSION = 1;
    private const ENDPOINT_VERSION_OPTION = 'sulayr_receipts_endpoint_version';

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
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 13 );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', [ $this, 'title' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render' ] );
        add_action( 'template_redirect', [ $this, 'maybe_pdf' ], 5 );
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
     * URL del PDF de un recibo en Mis recibos.
     *
     * @param   int $id Recibo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pdf_url( int $id ): string {
        return add_query_arg( 'recibo', $id, self::url() );
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
     * «Mis recibos» en el menú, justo detrás de «Mis cuotas».
     *
     * @param   array $items Ítems.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_menu_item( array $items ): array {
        if ( false === FeesAccount::is_athlete() ) {
            return $items;
        }

        $new = [];
        $added = false;
        foreach ( $items as $key => $label ) {
            $new[ $key ] = $label;
            if ( FeesAccount::ENDPOINT === $key ) {
                $new[ self::ENDPOINT ] = __( 'Mis recibos', SULAYR_TEXTDOMAIN );
                $added = true;
            }
        }
        if ( false === $added ) {
            $new[ self::ENDPOINT ] = __( 'Mis recibos', SULAYR_TEXTDOMAIN );
        }

        return $new;
    }

    /**
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function title(): string {
        return __( 'Mis recibos', SULAYR_TEXTDOMAIN );
    }

    /**
     * «Ver recibo» desde Mis recibos: solo los recibos propios (8.6, D6).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_pdf(): void {
        if ( empty( $_GET['recibo'] ) || false === function_exists( 'is_wc_endpoint_url' ) || false === is_wc_endpoint_url( self::ENDPOINT ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lectura; se comprueba el dueño.
            return;
        }
        if ( false === is_user_logged_in() ) {
            auth_redirect();
        }

        $receipt = Receipts::get( absint( $_GET['recibo'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ( null === $receipt || (int) $receipt->user_id !== get_current_user_id() || false === FeesAccount::is_athlete() ) {
            wp_die( esc_html__( 'No puedes ver este recibo.', SULAYR_TEXTDOMAIN ), esc_html__( 'Recibo', SULAYR_TEXTDOMAIN ), [ 'response' => 403 ] );
        }

        ReceiptPdf::stream( $receipt );
    }

    /**
     * Contenido de la pestaña: todos los recibos, sea cual sea su estado.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        if ( false === FeesAccount::is_athlete() ) {
            return;
        }

        $receipts = Receipts::for_user( get_current_user_id() );

        echo '<div class="sulayr-fees"><div class="sulayr-info-block sulayr-receipts-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Tus recibos', SULAYR_TEXTDOMAIN ) . '</h3>';
        if ( empty( $receipts ) ) {
            echo '<p>' . esc_html__( 'Todavía no tienes recibos.', SULAYR_TEXTDOMAIN ) . '</p></div></div>';
            return;
        }

        foreach ( $receipts as $receipt ) {
            $status = Receipts::display_status( $receipt );
            $outstanding = Receipts::outstanding( $receipt );
            printf(
                '<details class="sulayr-receipt"><summary><span class="sulayr-receipt-month">%1$s</span> <span class="sulayr-receipt-total">%2$s</span> <span class="sulayr-receipt-badge sulayr-receipt-%3$s">%4$s</span></summary>',
                esc_html( ucfirst( Receipts::month_label( (string) $receipt->period ) ) ),
                esc_html( Receipts::money( (float) $receipt->total ) ),
                esc_attr( $status ),
                esc_html( Receipts::status_label( $status ) )
            );
            echo '<table class="sulayr-receipt-lines"><tbody>';
            foreach ( Receipts::lines( (int) $receipt->id ) as $line ) {
                $cancelled = (int) $line->cancelled > 0;
                printf(
                    '<tr%s><td>%s%s</td><td class="sulayr-num">%s</td></tr>',
                    $cancelled ? ' class="sulayr-line-cancelled"' : '',
                    esc_html( (string) $line->concept ),
                    $cancelled ? ' <small>(' . esc_html__( 'anulada', SULAYR_TEXTDOMAIN ) . ')</small>' : '',
                    esc_html( Receipts::money( (float) $line->amount ) )
                );
            }
            printf( '</tbody><tfoot><tr><th>%s</th><th class="sulayr-num">%s</th></tr></tfoot></table>', esc_html__( 'Total', SULAYR_TEXTDOMAIN ), esc_html( Receipts::money( (float) $receipt->total ) ) );
            foreach ( Receipts::payments( (int) $receipt->id ) as $payment ) {
                printf( '<p class="sulayr-fees-note">%s</p>', esc_html( sprintf( __( 'Pagado %1$s el %2$s (%3$s).', SULAYR_TEXTDOMAIN ), Receipts::money( (float) $payment->amount ), MemberDates::show( (string) $payment->paid_on ), Receipts::method_label( (string) $payment->method ) ) ) );
            }
            if ( $outstanding > 0 ) {
                printf( '<p class="sulayr-fees-note">%s</p>', esc_html( sprintf( __( 'Pendiente: %s', SULAYR_TEXTDOMAIN ), Receipts::money( $outstanding ) ) ) );
            }
            printf( '<p><a class="button" href="%s" target="_blank" rel="noopener">%s</a></p></details>', esc_url( self::pdf_url( (int) $receipt->id ) ), esc_html( sprintf( __( 'Ver recibo %s', SULAYR_TEXTDOMAIN ), $receipt->number ) ) );
        }
        echo '</div></div>';
    }
}
