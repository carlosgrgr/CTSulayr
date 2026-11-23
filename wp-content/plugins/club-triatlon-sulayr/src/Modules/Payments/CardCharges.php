<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysOrder;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRequest;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRestResult;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cobro de un recibo con la tarjeta guardada, sin el socio (SPEC-COBROS §5.2).
 *
 * Lo usarán el cobro mensual, los reintentos y el cobro de un recibo concreto
 * (parte C). Llama a la API REST de Redsys y la respuesta llega en la misma
 * llamada:
 *
 * - Firmada: se aplica como una notificación (CardPayments::apply()).
 * - Error SIS: Redsys no hizo el cargo; la operación queda en «error».
 * - Sin respuesta clara: la operación se queda «enviada», bloquea el recibo
 *   y se avisa al club para revisarla en el portal de Redsys (§5.2, paso 3).
 */
class CardCharges {

    /** Resultados además de los de CardPayments::RESULT_*. */
    public const RESULT_REJECTED = 'rechazada';
    public const RESULT_PENDING = 'sin_respuesta';

    /** Segundos de espera de la API REST. */
    private const TIMEOUT = 45;

    /** @var callable|null Para las pruebas: fn( string $url, string $body ): array{code: int, body: string}|\WP_Error */
    private static $transport = null;

    /**
     * Cambia el envío HTTP (solo para las comprobaciones locales).
     *
     * @param   callable|null $transport Función de envío, o null para usar wp_remote_post().
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function use_transport( ?callable $transport ): void {
        self::$transport = $transport;
    }

    /**
     * Cobra lo pendiente de un recibo con la tarjeta activa del socio.
     *
     * @param   int    $receipt_id Recibo.
     * @param   string $type       TpvTransactions::TYPE_RECURRING o TYPE_RETRY.
     * @param   int    $attempt    Intento (1 el cobro, 2 y 3 los reintentos).
     * @return 	array{result: string, transaction: object, error: string}|\WP_Error
     *          result: CardPayments::RESULT_AUTHORIZED, RESULT_DENIED, RESULT_MISMATCH,
     *          RESULT_REJECTED o RESULT_PENDING. WP_Error si no se llegó a enviar.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function charge( int $receipt_id, string $type = TpvTransactions::TYPE_RECURRING, int $attempt = 1 ) {
        global $wpdb;

        $config = CardPayments::config();
        if ( false === $config->is_configured() ) {
            return new \WP_Error( 'sulayr_tpv_disabled', __( 'El pago con tarjeta no está configurado.', SULAYR_TEXTDOMAIN ) );
        }
        if ( false === in_array( $type, [ TpvTransactions::TYPE_RECURRING, TpvTransactions::TYPE_RETRY ], true ) ) {
            return new \WP_Error( 'sulayr_tpv_type', __( 'Tipo de cobro no válido.', SULAYR_TEXTDOMAIN ) );
        }

        // Un cobro a la vez por recibo: dos ejecuciones simultáneas no pasan juntas de aquí.
        $lock = 'sulayr_tpv_receipt_' . $receipt_id;
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
            return new \WP_Error( 'sulayr_tpv_in_flight', __( 'Este recibo ya tiene un cobro con tarjeta en curso.', SULAYR_TEXTDOMAIN ) );
        }

        try {
            $receipt = Receipts::get( $receipt_id );
            if ( null === $receipt ) {
                return new \WP_Error( 'sulayr_tpv_receipt', __( 'Recibo no encontrado.', SULAYR_TEXTDOMAIN ) );
            }
            $outstanding = Receipts::outstanding( $receipt );
            if ( false === Receipts::is_editable( $receipt ) || $outstanding <= 0 ) {
                return new \WP_Error( 'sulayr_tpv_paid', __( 'Este recibo no tiene nada pendiente.', SULAYR_TEXTDOMAIN ) );
            }

            $user_id = (int) $receipt->user_id;
            $card = PaymentCards::active( $user_id );
            if ( null === $card || '' === trim( (string) $card->identifier ) ) {
                return new \WP_Error( 'sulayr_tpv_no_card', __( 'El socio no tiene tarjeta guardada.', SULAYR_TEXTDOMAIN ) );
            }
            if ( PaymentCards::is_expired( $card, current_time( 'Y-m-d' ) ) ) {
                return new \WP_Error( 'sulayr_tpv_card_expired', __( 'La tarjeta guardada está caducada.', SULAYR_TEXTDOMAIN ) );
            }

            $transaction = TpvTransactions::create( $user_id, $type, $outstanding, $receipt_id, $attempt );
            if ( is_wp_error( $transaction ) ) {
                return $transaction;
            }

            $order = (string) $transaction->ds_order;
            $body = RedsysRequest::mit_body(
                $config,
                $order,
                RedsysOrder::cents( $outstanding ),
                (string) $card->identifier,
                (string) $card->cof_txnid,
                sprintf( __( 'Recibo %1$s (%2$s)', SULAYR_TEXTDOMAIN ), $receipt->number, Receipts::month_label( (string) $receipt->period ) )
            );

            $result = self::send( $config->rest_url(), $order, $body );

            return [
                'result' => self::record( $transaction, $result ),
                'transaction' => TpvTransactions::get( (int) $transaction->id ),
                'error' => $result->error(),
            ];
        } finally {
            $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
        }
    }

    /**
     * Envía la petición y lee la respuesta.
     *
     * @param   string $url   URL de la API REST.
     * @param   string $order Ds_Order enviado.
     * @param   string $body  Cuerpo JSON.
     * @return 	RedsysRestResult
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function send( string $url, string $order, string $body ): RedsysRestResult {
        if ( null !== self::$transport ) {
            $response = call_user_func( self::$transport, $url, $body );
        } else {
            $raw = wp_remote_post( $url, [
                'timeout' => self::TIMEOUT,
                'headers' => [ 'Content-Type' => 'application/json' ],
                'body' => $body,
            ] );
            $response = is_wp_error( $raw ) ? $raw : [
                'code' => (int) wp_remote_retrieve_response_code( $raw ),
                'body' => (string) wp_remote_retrieve_body( $raw ),
            ];
        }

        if ( is_wp_error( $response ) ) {
            return RedsysRestResult::unknown( 'red: ' . $response->get_error_message() );
        }

        return RedsysRestResult::parse( CardPayments::config(), $order, (int) ( $response['code'] ?? 0 ), (string) ( $response['body'] ?? '' ) );
    }

    /**
     * Guarda el resultado en la operación y, si se cobró, el pago en el recibo.
     *
     * @param   object           $transaction Operación «enviada».
     * @param   RedsysRestResult $result      Respuesta.
     * @return 	string Resultado.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function record( object $transaction, RedsysRestResult $result ): string {
        $notification = $result->notification();
        if ( null !== $notification ) {
            return CardPayments::apply( $transaction, $notification );
        }

        if ( RedsysRestResult::OUTCOME_REJECTED === $result->outcome() ) {
            TpvTransactions::close( (int) $transaction->id, TpvTransactions::STATUS_ERROR, $result->params() );
            return self::RESULT_REJECTED;
        }

        // No se sabe si se cobró: la operación sigue «enviada» y bloquea el recibo.
        TpvTransactions::note_unknown( (int) $transaction->id, [ 'sin_respuesta' => $result->error() ] + $result->params() );
        CardPayments::notify_club(
            __( 'Cobro con tarjeta sin respuesta de Redsys', SULAYR_TEXTDOMAIN ),
            sprintf( __( 'El cobro de %1$s con la tarjeta guardada (operación %2$s) no ha tenido una respuesta clara de Redsys (%3$s). No se sabe si se ha cobrado: búscala en el portal de Redsys. Mientras no se resuelva, el recibo no se vuelve a cobrar con tarjeta.', SULAYR_TEXTDOMAIN ), Receipts::money( (float) $transaction->amount ), $transaction->ds_order, $result->error() )
        );

        return self::RESULT_PENDING;
    }
}
