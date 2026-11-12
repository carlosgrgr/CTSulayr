<?php
namespace ClubTriatlonSulayr\Modules\Members\Lifecycle;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\Service;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Join\JoinForm;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptPdf;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Mi cuenta → Mis cuotas (6.2, D31, D53).
 *
 * Muestra los servicios actuales, los recibos (con líneas, pagos y PDF), los
 * cambios programados y la solicitud pendiente, y deja pedir cambios de
 * servicios y la baja. El PDF se abre con ?recibo=ID en esta pestaña y solo
 * para recibos del propio socio (D6).
 */
class FeesAccount {

    public const ENDPOINT = 'mis-cuotas';

    private const ENDPOINT_VERSION = 1;
    private const ENDPOINT_VERSION_OPTION = 'sulayr_fees_endpoint_version';

    private const ACTION = 'sulayr_fees_action';
    private const NOTICE_TRANSIENT = 'sulayr_fees_notice_';

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
        add_filter( 'woocommerce_account_menu_items', [ $this, 'add_menu_item' ], 12 );
        add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', [ $this, 'title' ] );
        add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', [ $this, 'render' ] );
        add_action( 'template_redirect', [ $this, 'handle' ], 6 );
        add_action( 'template_redirect', [ $this, 'maybe_pdf' ], 5 );
    }

    /**
     * URL del PDF de un recibo en Mis cuotas.
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
     * «Ver recibo» desde Mis cuotas: solo los recibos propios (8.6, D6).
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
        if ( null === $receipt || (int) $receipt->user_id !== get_current_user_id() || false === self::is_athlete() ) {
            wp_die( esc_html__( 'No puedes ver este recibo.', SULAYR_TEXTDOMAIN ), esc_html__( 'Recibo', SULAYR_TEXTDOMAIN ), [ 'response' => 403 ] );
        }

        ReceiptPdf::stream( $receipt );
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
     * «Mis cuotas» en el menú, detrás del perfil (o del escritorio si no hay perfil).
     *
     * @param   array $items Ítems.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_menu_item( array $items ): array {
        if ( false === self::is_athlete() ) {
            return $items;
        }

        $new = [];
        $added = false;
        foreach ( $items as $key => $label ) {
            if ( false === $added && in_array( $key, [ 'mi-monedero', 'orders' ], true ) ) {
                $new[ self::ENDPOINT ] = __( 'Mis cuotas', SULAYR_TEXTDOMAIN );
                $added = true;
            }
            $new[ $key ] = $label;
        }
        if ( false === $added ) {
            $new[ self::ENDPOINT ] = __( 'Mis cuotas', SULAYR_TEXTDOMAIN );
        }

        return $new;
    }

    /**
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function title(): string {
        return __( 'Mis cuotas', SULAYR_TEXTDOMAIN );
    }

    /**
     * Procesa los formularios de la pestaña.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || empty( $_POST[ self::ACTION ] ) || false === is_user_logged_in() ) {
            return;
        }

        check_admin_referer( self::ACTION );
        if ( false === self::is_athlete() ) {
            return;
        }

        $user_id = get_current_user_id();
        $status = self::status( $user_id );
        $action = sanitize_key( wp_unslash( $_POST[ self::ACTION ] ) );

        if ( 'cancelar' === $action ) {
            $id = isset( $_POST['change_id'] ) ? absint( $_POST['change_id'] ) : 0;
            $this->redirect( MemberChanges::cancel( $id, $user_id ) ? [ 'success', __( 'Solicitud cancelada.', SULAYR_TEXTDOMAIN ) ] : [ 'error', __( 'No se ha podido cancelar la solicitud.', SULAYR_TEXTDOMAIN ) ] );
        }

        if ( 'servicios' === $action && CustomFields::STATUS_ACTIVE === $status ) {
            $parsed = JoinForm::parse_services( (array) wp_unslash( $_POST[ JoinForm::INPUT ] ?? [] ), JoinForm::CONTEXT_PUBLIC, self::selectable_services( $user_id ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parse_services limpia cada valor.
            if ( '' !== $parsed['error'] ) {
                $this->redirect( [ 'error', $parsed['error'] ] );
            }
            if ( self::same_services( $parsed['services'], self::current_services( $user_id ) ) ) {
                $this->redirect( [ 'error', __( 'Has elegido los mismos servicios que ya tienes.', SULAYR_TEXTDOMAIN ) ] );
            }
            $result = MemberChanges::request( $user_id, MemberChanges::TYPE_SERVICES, [ 'services' => $parsed['services'] ] );
        } elseif ( 'baja' === $action && in_array( $status, [ CustomFields::STATUS_ACTIVE, CustomFields::STATUS_FROZEN ], true ) ) {
            if ( empty( $_POST['confirm'] ) ) {
                $this->redirect( [ 'error', __( 'Marca la casilla para confirmar que quieres darte de baja.', SULAYR_TEXTDOMAIN ) ] );
            }
            $result = MemberChanges::request( $user_id, MemberChanges::TYPE_LEAVE, [ 'reason' => substr( sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ), 0, 200 ) ] );
        } else {
            $this->redirect( [ 'error', __( 'Ahora mismo no puedes pedir eso.', SULAYR_TEXTDOMAIN ) ] );
            return;
        }

        if ( is_wp_error( $result ) ) {
            $this->redirect( [ 'error', $result->get_error_message() ] );
        }

        $this->redirect( [ 'success', __( 'Solicitud enviada. El club la revisará y te avisará por email.', SULAYR_TEXTDOMAIN ) ] );
    }

    /**
     * Contenido de la pestaña.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        if ( false === self::is_athlete() ) {
            return;
        }

        $user_id = get_current_user_id();
        $status = self::status( $user_id );
        $type = (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true );
        $types = MemberSettings::member_types();
        $today = current_time( 'Y-m-d' );

        echo '<div class="sulayr-fees">';
        $this->print_notice();

        // Situación actual.
        echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Tu situación', SULAYR_TEXTDOMAIN ) . '</h3><dl class="sulayr-info-list">';
        $rows = [
            __( 'Estado', SULAYR_TEXTDOMAIN ) => StatusPeriods::label( $status ),
            __( 'Tipo de socio', SULAYR_TEXTDOMAIN ) => $types[ $type ] ?? ( $type ?: '—' ),
        ];
        $period = StatusPeriods::current( $user_id );
        if ( null !== $period && CustomFields::STATUS_FROZEN === $period->status && null !== $period->planned_end_date ) {
            $rows[ __( 'Vuelta prevista', SULAYR_TEXTDOMAIN ) ] = MemberDates::show( MemberDates::add_days( (string) $period->planned_end_date, 1 ) );
        }
        $leave = (string) get_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, true );
        if ( CustomFields::STATUS_LEFT === $status && '' !== $leave && CustomFields::MEMBER_LEAVE_DATE_UNKNOWN !== $leave ) {
            $rows[ __( 'Último día como socio', SULAYR_TEXTDOMAIN ) ] = MemberDates::show( $leave );
        }
        foreach ( $rows as $label => $value ) {
            printf( '<div class="sulayr-info-row"><dt>%s</dt><dd>%s</dd></div>', esc_html( $label ), esc_html( $value ) );
        }
        echo '</dl></div>';

        // Servicios vigentes con su precio de este mes.
        $current = MemberServices::active_on( $user_id, $today );
        echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Tus servicios', SULAYR_TEXTDOMAIN ) . '</h3>';
        if ( empty( $current ) ) {
            echo '<p>' . esc_html__( 'No tienes servicios en vigor.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<dl class="sulayr-info-list">';
            foreach ( $current as $row ) {
                $service = ServiceCatalog::get( (int) $row->service_id );
                $price = null !== $service ? ServiceCatalog::price_for( $service, '' !== $type ? $type : ServiceCatalog::GENERAL, $today ) : null;
                printf(
                    '<div class="sulayr-info-row"><dt>%s</dt><dd>%s</dd></div>',
                    esc_html( MemberServices::label( (int) $row->service_id, (string) $row->option_key ) ),
                    esc_html( null !== $price ? number_format_i18n( $price['amount'], 2 ) . ' €/mes' : '—' )
                );
            }
            echo '</dl>';
        }
        echo '</div>';

        $this->render_receipts( $user_id );

        // Cambios programados.
        $scheduled = MemberChanges::scheduled_for_user( $user_id );
        if ( ! empty( $scheduled ) ) {
            echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Cambios programados', SULAYR_TEXTDOMAIN ) . '</h3><ul>';
            foreach ( $scheduled as $row ) {
                printf( '<li>%s: %s</li>', esc_html( MemberDates::show( (string) $row->effective_date ) ), esc_html( MemberChanges::describe( $row ) ) );
            }
            echo '</ul></div>';
        }

        // Solicitud pendiente.
        $pending = MemberChanges::pending_for_user( $user_id );
        if ( null !== $pending ) {
            echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Tu solicitud pendiente', SULAYR_TEXTDOMAIN ) . '</h3>';
            printf(
                '<p>%s<br><span class="sulayr-fees-note">%s</span></p>',
                esc_html( MemberChanges::describe( $pending ) ),
                esc_html( sprintf( __( 'Enviada el %1$s. Si el club la aprueba, se aplicará desde el %2$s.', SULAYR_TEXTDOMAIN ), MemberDates::show( substr( (string) $pending->created_at, 0, 10 ) ), MemberDates::show( (string) $pending->effective_date ) ) )
            );
            echo '<form method="post">';
            wp_nonce_field( self::ACTION );
            printf( '<input type="hidden" name="%s" value="cancelar"><input type="hidden" name="change_id" value="%d">', esc_attr( self::ACTION ), (int) $pending->id );
            printf( '<button type="submit" class="button">%s</button></form></div>', esc_html__( 'Cancelar solicitud', SULAYR_TEXTDOMAIN ) );
            echo '</div>';
            return;
        }

        $date = MemberDates::show( MemberChanges::proposed_date() );

        if ( CustomFields::STATUS_ACTIVE === $status ) {
            JoinForm::enqueue_script();
            $selected = [];
            foreach ( self::current_services( $user_id ) as $item ) {
                $selected[ (int) $item['service_id'] ] = (string) $item['option_key'];
            }
            echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Cambiar mis servicios', SULAYR_TEXTDOMAIN ) . '</h3>';
            printf( '<p class="sulayr-fees-note">%s</p>', esc_html( sprintf( __( 'Marca los servicios que quieres tener. Si el club lo aprueba, el cambio se aplica desde el %s.', SULAYR_TEXTDOMAIN ), $date ) ) );
            echo '<form method="post" class="sulayr-join-form">';
            wp_nonce_field( self::ACTION );
            printf( '<input type="hidden" name="%s" value="servicios">', esc_attr( self::ACTION ) );
            JoinForm::render_services( self::selectable_services( $user_id ), $selected, MemberChanges::proposed_date(), '' !== $type ? $type : ServiceCatalog::GENERAL );
            printf( '<p><button type="submit" class="button">%s</button></p></form></div>', esc_html__( 'Pedir el cambio', SULAYR_TEXTDOMAIN ) );
        }

        if ( in_array( $status, [ CustomFields::STATUS_ACTIVE, CustomFields::STATUS_FROZEN ], true ) ) {
            echo '<div class="sulayr-info-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Darme de baja', SULAYR_TEXTDOMAIN ) . '</h3>';
            printf( '<p class="sulayr-fees-note">%s</p>', esc_html( sprintf( __( 'Si el club la aprueba, la baja será efectiva el %s.', SULAYR_TEXTDOMAIN ), $date ) ) );
            echo '<form method="post">';
            wp_nonce_field( self::ACTION );
            printf( '<input type="hidden" name="%s" value="baja">', esc_attr( self::ACTION ) );
            printf( '<p><label for="sulayr-fees-reason">%s</label><input class="form-control" type="text" id="sulayr-fees-reason" name="reason" maxlength="200"></p>', esc_html__( 'Motivo (opcional)', SULAYR_TEXTDOMAIN ) );
            printf( '<div class="form-check"><input class="form-check-input" type="checkbox" id="sulayr-fees-confirm" name="confirm" value="1"> <label class="form-check-label" for="sulayr-fees-confirm">%s</label></div>', esc_html__( 'Quiero darme de baja del club.', SULAYR_TEXTDOMAIN ) );
            printf( '<p><button type="submit" class="button">%s</button></p></form></div>', esc_html__( 'Pedir la baja', SULAYR_TEXTDOMAIN ) );
        }

        echo '</div>';
    }

    /**
     * Recibos del socio con sus líneas, pagos y PDF (D53).
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_receipts( int $user_id ): void {
        $receipts = Receipts::for_user( $user_id );
        if ( empty( $receipts ) ) {
            return;
        }

        echo '<div class="sulayr-info-block sulayr-receipts-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Tus recibos', SULAYR_TEXTDOMAIN ) . '</h3>';
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
        echo '</div>';
    }

    /**
     * Servicios que puede marcar el socio: los públicos y los que ya tiene.
     *
     * @param   int $user_id Socio.
     * @return 	Service[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function selectable_services( int $user_id ): array {
        $own = array_map( function ( $item ) {
            return (int) $item['service_id'];
        }, self::current_services( $user_id ) );

        return array_values( array_filter( ServiceCatalog::all(), function ( Service $service ) use ( $own ) {
            return $service->is_base || $service->public || in_array( $service->id, $own, true );
        } ) );
    }

    /**
     * Servicios abiertos del socio.
     *
     * @param   int $user_id Socio.
     * @return 	array<int, array{service_id: int, option_key: string}>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function current_services( int $user_id ): array {
        return array_map( function ( $row ) {
            return [ 'service_id' => (int) $row->service_id, 'option_key' => (string) $row->option_key ];
        }, MemberServices::open_rows( $user_id ) );
    }

    /**
     * True si dos listas de servicios son iguales sin importar el orden.
     *
     * @param   array $a Lista.
     * @param   array $b Lista.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function same_services( array $a, array $b ): bool {
        $key = function ( array $list ): array {
            $keys = array_map( function ( $item ) {
                return (int) $item['service_id'] . '|' . (string) $item['option_key'];
            }, $list );
            sort( $keys );
            return $keys;
        };

        return $key( $a ) === $key( $b );
    }

    /**
     * Estado del socio para reservas y Mi cuenta.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function status( int $user_id ): string {
        return (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ?: CustomFields::STATUS_ACTIVE;
    }

    /**
     * True si el usuario actual es deportista.
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_athlete(): bool {
        $user = wp_get_current_user();

        return $user->exists() && in_array( 'athlete', (array) $user->roles, true );
    }

    /**
     * Guarda el aviso y vuelve a la pestaña.
     *
     * @param   array{0: string, 1: string} $notice Tipo y texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function redirect( array $notice ): void {
        set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( self::url() );
        exit;
    }

    /**
     * Pinta y borra el aviso pendiente.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function print_notice(): void {
        $key = self::NOTICE_TRANSIENT . get_current_user_id();
        $notice = get_transient( $key );
        if ( false === is_array( $notice ) ) {
            return;
        }
        delete_transient( $key );

        printf( '<div class="sulayr-alert sulayr-alert-%s" role="status">%s</div>', 'success' === $notice[0] ? 'success' : 'error', esc_html( $notice[1] ) );
    }
}
