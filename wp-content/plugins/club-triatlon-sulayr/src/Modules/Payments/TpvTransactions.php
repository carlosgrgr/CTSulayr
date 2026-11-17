<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysOrder;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Operaciones enviadas a Redsys (SPEC-COBROS §4.2, tabla sulayr_tpv_transactions).
 *
 * Cada operación nace «enviada» y se cierra una sola vez: close() solo
 * actualiza si sigue «enviada», así una notificación repetida no hace nada
 * (§5.3, notificación idempotente).
 */
class TpvTransactions {

    public const TYPE_REGISTER = 'registro';
    public const TYPE_MANUAL = 'pago_manual';
    public const TYPE_RECURRING = 'recurrente';
    public const TYPE_RETRY = 'reintento';
    public const TYPE_VALIDATION = 'validacion';

    public const STATUS_SENT = 'enviada';
    public const STATUS_AUTHORIZED = 'autorizada';
    public const STATUS_DENIED = 'denegada';
    public const STATUS_ERROR = 'error';

    /** Claves de la respuesta de Redsys que no se guardan (referencia de la tarjeta). */
    private const SENSITIVE_KEYS = '/identifier|cof_txnid|signature|card_number|_pan$/i';

    /**
     * Crea una operación «enviada» y le pone su Ds_Order.
     *
     * @param   int    $user_id    Socio.
     * @param   string $type       Una de las constantes TYPE_*.
     * @param   float  $amount     Importe en euros (0 en una validación).
     * @param   int    $receipt_id Recibo, o 0 en una validación.
     * @param   int    $attempt    Intento (1 el cobro, 2 y 3 los reintentos).
     * @return 	object|\WP_Error La fila creada.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function create( int $user_id, string $type, float $amount, int $receipt_id = 0, int $attempt = 1 ) {
        global $wpdb;

        if ( $receipt_id > 0 && self::in_flight( $receipt_id ) ) {
            return new \WP_Error( 'sulayr_tpv_in_flight', __( 'Este recibo ya tiene un cobro con tarjeta en curso.', SULAYR_TEXTDOMAIN ) );
        }

        $now = current_time( 'mysql' );
        $inserted = $wpdb->insert( self::table(), [
            'receipt_id' => $receipt_id,
            'user_id' => $user_id,
            'type' => $type,
            'attempt' => max( 1, $attempt ),
            'amount' => round( $amount, 2 ),
            'status' => self::STATUS_SENT,
            'response' => '',
            'created_by' => get_current_user_id(),
            'created_at' => $now,
        ], [ '%d', '%d', '%s', '%d', '%f', '%s', '%s', '%d', '%s' ] );
        if ( false === $inserted ) {
            return new \WP_Error( 'sulayr_tpv_insert', __( 'No se pudo crear la operación con tarjeta.', SULAYR_TEXTDOMAIN ) );
        }

        $id = (int) $wpdb->insert_id;
        $wpdb->update( self::table(), [ 'ds_order' => RedsysOrder::number( $id, $now ) ], [ 'id' => $id ], [ '%s' ], [ '%d' ] );

        return self::get( $id );
    }

    /**
     * Una operación por id.
     *
     * @param   int $id Id.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ) );

        return $row ?: null;
    }

    /**
     * Una operación por su Ds_Order.
     *
     * @param   string $ds_order Ds_Order.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function find_by_order( string $ds_order ): ?object {
        global $wpdb;

        if ( '' === $ds_order ) {
            return null;
        }
        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE ds_order = %s', $ds_order ) );

        return $row ?: null;
    }

    /**
     * El recibo tiene una operación «enviada» sin cerrar (§5.2, paso 3).
     *
     * @param   int $receipt_id Recibo.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function in_flight( int $receipt_id ): bool {
        global $wpdb;

        return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM ' . self::table() . ' WHERE receipt_id = %d AND status = %s LIMIT 1', $receipt_id, self::STATUS_SENT ) );
    }

    /**
     * Operaciones de un socio, de la más reciente a la más antigua.
     *
     * @param   int $user_id Socio.
     * @return 	array<int, object>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY id DESC', $user_id ) ) ?: [];
    }

    /**
     * Cierra una operación «enviada». No hace nada si ya estaba cerrada.
     *
     * @param   int                  $id          Operación.
     * @param   string               $status      STATUS_AUTHORIZED, STATUS_DENIED o STATUS_ERROR.
     * @param   array<string, mixed> $response    Respuesta de Redsys decodificada.
     * @param   int                  $payment_id  Pago del recibo creado, si lo hay.
     * @return 	bool true si se cerró ahora; false si ya estaba cerrada.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function close( int $id, string $status, array $response = [], int $payment_id = 0 ): bool {
        global $wpdb;

        $updated = $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::table() . ' SET status = %s, ds_response = %s, auth_code = %s, response = %s, payment_id = %d, updated_at = %s WHERE id = %d AND status = %s',
            $status,
            substr( self::pick( $response, [ 'Ds_Response', 'DS_RESPONSE' ] ), 0, 8 ),
            substr( self::pick( $response, [ 'Ds_AuthorisationCode', 'DS_AUTHORISATIONCODE' ] ), 0, 16 ),
            (string) wp_json_encode( self::sanitize_response( $response ) ),
            $payment_id,
            current_time( 'mysql' ),
            $id,
            self::STATUS_SENT
        ) );

        return 1 === (int) $updated;
    }

    /**
     * Respuesta sin la referencia de la tarjeta ni la firma (§4.2).
     *
     * @param   array<string, mixed> $response Respuesta decodificada.
     * @return 	array<string, mixed>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sanitize_response( array $response ): array {
        foreach ( array_keys( $response ) as $key ) {
            if ( 1 === preg_match( self::SENSITIVE_KEYS, (string) $key ) ) {
                unset( $response[ $key ] );
            }
        }

        return $response;
    }

    /**
     * Primer valor escalar de varias claves posibles.
     *
     * @param   array<string, mixed> $data  Datos.
     * @param   string[]             $keys  Claves.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function pick( array $data, array $keys ): string {
        foreach ( $keys as $key ) {
            if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ) {
                return trim( (string) $data[ $key ] );
            }
        }

        return '';
    }

    /**
     * Nombre de la tabla.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function table(): string {
        return MembersSchema::table( MembersSchema::TPV_TRANSACTIONS );
    }
}
