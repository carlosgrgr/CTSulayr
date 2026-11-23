<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Join\MemberMailer;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysNotification;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysOrder;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRequest;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pago de un recibo con tarjeta desde Mis recibos (SPEC-COBROS §5.1, C20, C21).
 *
 * 1. start() crea la operación y devuelve los campos del formulario de Redsys.
 * 2. Redsys llama a la URL de notificación; handle_notification() verifica la
 *    firma y, si está autorizada, apunta el pago y guarda la tarjeta.
 * 3. La vuelta a Mis recibos no apunta nada: solo enseña el estado.
 *
 * La URL de notificación es /wp-json/sulayr/v1/redsys (§5.3).
 */
class CardPayments {

    public const REST_NAMESPACE = 'sulayr/v1';
    public const REST_ROUTE = '/redsys';

    /** Resultados de handle_notification(). */
    public const RESULT_INVALID = 'firma_no_valida';
    public const RESULT_UNKNOWN = 'operacion_desconocida';
    public const RESULT_DUPLICATE = 'ya_procesada';
    public const RESULT_AUTHORIZED = 'autorizada';
    public const RESULT_DENIED = 'denegada';
    public const RESULT_MISMATCH = 'importe_distinto';

    /** @var RedsysConfig|null Para las pruebas. */
    private static $config = null;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_route' ] );
        add_action( 'sulayr_payment_card_removed', [ $this, 'notify_card_removed' ], 10, 2 );
    }

    /**
     * Credenciales en uso.
     *
     * @return 	RedsysConfig
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function config(): RedsysConfig {
        return self::$config ?? RedsysConfig::from_constants();
    }

    /**
     * Cambia las credenciales (solo para las comprobaciones locales).
     *
     * @param   RedsysConfig|null $config Credenciales, o null para volver a wp-config.php.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function use_config( ?RedsysConfig $config ): void {
        self::$config = $config;
    }

    /**
     * El pago con tarjeta está disponible (hay credenciales).
     *
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_enabled(): bool {
        return self::config()->is_configured();
    }

    /**
     * URL de notificación.
     *
     * SULAYR_REDSYS_NOTIFY_URL (wp-config.php, solo en local) la sustituye,
     * p. ej. por la de un túnel, para que Redsys llegue a un sitio local. En
     * PROD no se define.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function notify_url(): string {
        if ( defined( 'SULAYR_REDSYS_NOTIFY_URL' ) && '' !== trim( (string) SULAYR_REDSYS_NOTIFY_URL ) ) {
            return trim( (string) SULAYR_REDSYS_NOTIFY_URL );
        }

        return rest_url( self::REST_NAMESPACE . self::REST_ROUTE );
    }

    /**
     * El socio está de baja: puede pagar, pero no se guarda su tarjeta (C14).
     *
     * @param   int $user_id Socio.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_left( int $user_id ): bool {
        return CustomFields::STATUS_LEFT === (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
    }

    /**
     * El socio tiene tutor (es menor, D6): la autorización la puede dar el tutor.
     *
     * @param   int $user_id Socio.
     * @return 	string Nombre del tutor, o ''.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function guardian_name( int $user_id ): string {
        return trim( (string) get_user_meta( $user_id, CustomFields::MEMBER_GUARDIAN . '_name', true ) );
    }

    /**
     * Texto de la autorización de cargos con el día de cobro.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function authorization_text(): string {
        return str_replace( '{dia_cobro}', (string) (int) MemberSettings::get( 'tpv_charge_day' ), wp_strip_all_tags( MemberConsents::text( MemberConsents::RECURRING_CHARGES ), true ) );
    }

    /**
     * Empieza el pago de un recibo propio (C21).
     *
     * @param   int    $user_id    Socio que paga (el usuario conectado).
     * @param   int    $receipt_id Recibo.
     * @param   bool   $authorized Marcó la autorización de cargos recurrentes (C12).
     * @param   string $given_by   MemberConsents::BY_MEMBER o BY_GUARDIAN.
     * @return 	array{url: string, fields: array<string, string>, transaction: object}|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function start( int $user_id, int $receipt_id, bool $authorized, string $given_by = MemberConsents::BY_MEMBER ) {
        $config = self::config();
        if ( false === $config->is_configured() ) {
            return new \WP_Error( 'sulayr_tpv_disabled', __( 'El pago con tarjeta todavía no está disponible.', SULAYR_TEXTDOMAIN ) );
        }

        $receipt = Receipts::get( $receipt_id );
        if ( null === $receipt || (int) $receipt->user_id !== $user_id ) {
            return new \WP_Error( 'sulayr_tpv_receipt', __( 'Recibo no encontrado.', SULAYR_TEXTDOMAIN ) );
        }
        $outstanding = Receipts::outstanding( $receipt );
        if ( false === Receipts::is_editable( $receipt ) || $outstanding <= 0 ) {
            return new \WP_Error( 'sulayr_tpv_paid', __( 'Este recibo no tiene nada pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        // Se guarda la tarjeta salvo en una baja (C14), y para guardarla hace falta la autorización (C12).
        $save_card = false === self::is_left( $user_id );
        if ( $save_card && false === $authorized ) {
            return new \WP_Error( 'sulayr_tpv_authorization', __( 'Marca la autorización de cargos para pagar con tarjeta.', SULAYR_TEXTDOMAIN ) );
        }
        if ( MemberConsents::BY_GUARDIAN === $given_by && '' === self::guardian_name( $user_id ) ) {
            $given_by = MemberConsents::BY_MEMBER;
        }

        $transaction = TpvTransactions::create( $user_id, TpvTransactions::TYPE_MANUAL, $outstanding, $receipt_id );
        if ( is_wp_error( $transaction ) ) {
            return $transaction;
        }

        if ( $save_card ) {
            MemberConsents::record( MemberConsents::RECURRING_CHARGES, true, $given_by, $user_id );
        }

        $back = ReceiptsAccount::url();
        $fields = RedsysRequest::redirect_fields(
            $config,
            (string) $transaction->ds_order,
            RedsysOrder::cents( $outstanding ),
            sprintf( __( 'Recibo %1$s (%2$s)', SULAYR_TEXTDOMAIN ), $receipt->number, Receipts::month_label( (string) $receipt->period ) ),
            self::notify_url(),
            add_query_arg( [ 'pago' => 'ok', 'op' => (int) $transaction->id ], $back ),
            add_query_arg( [ 'pago' => 'ko', 'op' => (int) $transaction->id ], $back ),
            $save_card
        );

        return [
            'url' => $config->redirect_url(),
            'fields' => $fields,
            'transaction' => $transaction,
        ];
    }

    /**
     * Procesa una notificación de Redsys (§5.1, paso 3). Idempotente.
     *
     * @param   array<string, mixed> $post Campos recibidos.
     * @return 	string Una de las constantes RESULT_*.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function handle_notification( array $post ): string {
        $notification = RedsysNotification::from_request( self::config(), $post );
        if ( null === $notification ) {
            return self::RESULT_INVALID;
        }

        $transaction = TpvTransactions::find_by_order( $notification->order() );
        if ( null === $transaction ) {
            return self::RESULT_UNKNOWN;
        }
        if ( TpvTransactions::STATUS_SENT !== $transaction->status ) {
            return self::RESULT_DUPLICATE;
        }

        return self::apply( $transaction, $notification );
    }

    /**
     * Aplica una respuesta firmada de Redsys a su operación: la de la
     * notificación (§5.1) o la de la API REST en un cobro sin el socio (§5.2).
     *
     * Cierra la operación antes de apuntar nada, así dos respuestas a la vez
     * no crean dos pagos. Solo los pagos con el socio delante guardan la
     * tarjeta; en ellos el autor del pago es el socio, y en los cobros sin
     * él, 0 (§4.4).
     *
     * @param   object             $transaction  Operación «enviada».
     * @param   RedsysNotification $notification Respuesta verificada.
     * @return 	string Una de las constantes RESULT_*.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function apply( object $transaction, RedsysNotification $notification ): string {
        $params = $notification->params();
        if ( false === $notification->is_authorized() ) {
            return TpvTransactions::close( (int) $transaction->id, TpvTransactions::STATUS_DENIED, $params ) ? self::RESULT_DENIED : self::RESULT_DUPLICATE;
        }

        // Autorizada, pero por otro importe: no se apunta nada y se avisa al club.
        if ( $notification->cents() !== RedsysOrder::cents( (float) $transaction->amount ) ) {
            if ( false === TpvTransactions::close( (int) $transaction->id, TpvTransactions::STATUS_ERROR, $params ) ) {
                return self::RESULT_DUPLICATE;
            }
            self::notify_club(
                __( 'Cobro con tarjeta por un importe distinto', SULAYR_TEXTDOMAIN ),
                sprintf( __( 'Redsys ha autorizado la operación %1$s por %2$s, pero la web esperaba %3$s. No se ha apuntado ningún pago. Revísala en el portal de Redsys.', SULAYR_TEXTDOMAIN ), $transaction->ds_order, Receipts::money( RedsysOrder::euros( max( 0, $notification->cents() ) ) ), Receipts::money( (float) $transaction->amount ) )
            );
            return self::RESULT_MISMATCH;
        }

        // Primero se cierra la operación: si otra notificación llega a la vez, solo una sigue.
        if ( false === TpvTransactions::close( (int) $transaction->id, TpvTransactions::STATUS_AUTHORIZED, $params ) ) {
            return self::RESULT_DUPLICATE;
        }

        $user_id = (int) $transaction->user_id;
        $receipt_id = (int) $transaction->receipt_id;
        $with_member = self::is_with_member( (string) $transaction->type );
        if ( $receipt_id > 0 ) {
            $payment_id = Receipts::add_tpv_payment( $receipt_id, (float) $transaction->amount, (string) $transaction->ds_order, $with_member ? $user_id : 0 );
            if ( is_wp_error( $payment_id ) ) {
                // P. ej. un gestor apuntó el pago a mano mientras el socio pagaba: hay que devolverlo.
                self::notify_club(
                    __( 'Cobro con tarjeta sin recibo pendiente', SULAYR_TEXTDOMAIN ),
                    sprintf( __( 'Redsys ha cobrado %1$s (operación %2$s), pero no se ha podido apuntar en el recibo: %3$s Revisa el recibo y, si hace falta, devuelve el cobro en el portal de Redsys o pásalo al monedero.', SULAYR_TEXTDOMAIN ), Receipts::money( (float) $transaction->amount ), $transaction->ds_order, $payment_id->get_error_message() )
                );
            } else {
                TpvTransactions::set_payment( (int) $transaction->id, (int) $payment_id );
            }
        }

        if ( $with_member && '' !== $notification->identifier() && false === self::is_left( $user_id ) ) {
            PaymentCards::save( $user_id, $notification->identifier(), $notification->cof_txnid(), $notification->masked_card(), $notification->expiry(), (int) $transaction->id );
        }

        return self::RESULT_AUTHORIZED;
    }

    /**
     * La operación se hizo con el socio delante (redirección), no con la
     * tarjeta guardada.
     *
     * @param   string $type Tipo de la operación.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_with_member( string $type ): bool {
        return in_array( $type, [ TpvTransactions::TYPE_MANUAL, TpvTransactions::TYPE_VALIDATION, TpvTransactions::TYPE_REGISTER ], true );
    }

    /**
     * Ruta REST de la notificación.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_route(): void {
        register_rest_route( self::REST_NAMESPACE, self::REST_ROUTE, [
            'methods' => 'POST',
            'callback' => [ $this, 'rest_notification' ],
            // Pública: la autenticidad la da la firma de Redsys.
            'permission_callback' => '__return_true',
        ] );
    }

    /**
     * Responde a Redsys. Con firma no válida, 400; en lo demás, 200.
     *
     * @param   \WP_REST_Request $request Petición.
     * @return 	\WP_REST_Response
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function rest_notification( \WP_REST_Request $request ): \WP_REST_Response {
        $post = [];
        foreach ( [ 'Ds_SignatureVersion', 'Ds_MerchantParameters', 'Ds_Signature' ] as $name ) {
            $value = $request->get_param( $name );
            $post[ $name ] = is_scalar( $value ) ? (string) $value : '';
        }

        $result = self::handle_notification( $post );

        return new \WP_REST_Response( [ 'result' => $result ], self::RESULT_INVALID === $result ? 400 : 200 );
    }

    /**
     * El socio quitó su tarjeta: si cobra con tarjeta, se avisa al club (C13).
     *
     * @param   int    $user_id Socio.
     * @param   object $card    Tarjeta quitada.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function notify_card_removed( $user_id, $card ): void {
        $user_id = (int) $user_id;
        if ( false === ChargeMethod::is_tpv( $user_id ) ) {
            return;
        }

        $user = get_userdata( $user_id );
        $number = (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true );
        self::notify_club(
            __( 'Un socio ha quitado su tarjeta', SULAYR_TEXTDOMAIN ),
            sprintf( __( '%1$s (socio n.º %2$s) ha quitado su tarjeta (%3$s). Su medio de cobro es «Tarjeta (TPV)»: no entrará en el cobro automático hasta que guarde otra.', SULAYR_TEXTDOMAIN ), false !== $user ? $user->display_name : '#' . $user_id, '' !== $number ? $number : '—', PaymentCards::describe( is_object( $card ) ? $card : null ) )
        );
    }

    /**
     * Email al club con el diseño de marca.
     *
     * @param   string $subject Asunto.
     * @param   string $text    Texto.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function notify_club( string $subject, string $text ): void {
        $to = sanitize_email( (string) MemberSettings::get( 'club_email' ) );
        if ( '' === $to ) {
            return;
        }

        wp_mail( $to, $subject, MemberMailer::layout( wpautop( esc_html( $text ) ), $subject ), [ 'Content-Type: text/html; charset=UTF-8' ] );
    }
}
