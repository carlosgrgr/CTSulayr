<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\MemberLog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tarjetas guardadas (SPEC-COBROS C10, C13, C14 y §4.2).
 *
 * La web nunca ve el número de la tarjeta: guarda la referencia que devuelve
 * Redsys (Ds_Merchant_Identifier), el identificador de la operación inicial
 * (Ds_Merchant_Cof_Txnid), la tarjeta enmascarada y la caducidad (AAMM).
 *
 * Una sola tarjeta activa por socio: guardar otra marca la anterior como
 * «sustituida». Las filas no se borran: quedan para el histórico.
 */
class PaymentCards {

    public const STATUS_ACTIVE = 'activa';
    public const STATUS_REPLACED = 'sustituida';
    public const STATUS_REMOVED = 'quitada';
    public const STATUS_EXPIRED = 'caducada';
    public const STATUS_DELETED = 'borrada';

    /**
     * Tarjeta activa de un socio.
     *
     * @param   int $user_id Socio.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function active( int $user_id ): ?object {
        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE user_id = %d AND status = %s ORDER BY id DESC LIMIT 1', $user_id, self::STATUS_ACTIVE ) );

        return $row ?: null;
    }

    /**
     * Guarda la tarjeta que devuelve Redsys y sustituye a la anterior (C13).
     *
     * @param   int    $user_id        Socio.
     * @param   string $identifier     Ds_Merchant_Identifier.
     * @param   string $cof_txnid      Ds_Merchant_Cof_Txnid.
     * @param   string $masked         Tarjeta enmascarada.
     * @param   string $expiry         Caducidad AAMM.
     * @param   int    $transaction_id Operación con la que se guardó.
     * @return 	int|\WP_Error Id de la tarjeta.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function save( int $user_id, string $identifier, string $cof_txnid, string $masked, string $expiry, int $transaction_id ) {
        global $wpdb;

        $identifier = trim( $identifier );
        if ( '' === $identifier ) {
            return new \WP_Error( 'sulayr_card', __( 'Redsys no ha devuelto la referencia de la tarjeta.', SULAYR_TEXTDOMAIN ) );
        }

        $previous = self::active( $user_id );
        if ( null !== $previous ) {
            self::close( (int) $previous->id, self::STATUS_REPLACED );
        }

        $wpdb->insert( self::table(), [
            'user_id' => $user_id,
            'identifier' => substr( $identifier, 0, 64 ),
            'cof_txnid' => substr( trim( $cof_txnid ), 0, 64 ),
            'masked' => substr( self::mask( $masked ), 0, 32 ),
            'expiry' => self::valid_expiry( $expiry ) ? $expiry : '',
            'status' => self::STATUS_ACTIVE,
            'transaction_id' => $transaction_id,
            'created_at' => current_time( 'mysql' ),
        ], [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ] );
        $id = (int) $wpdb->insert_id;

        MemberLog::record( $user_id, MemberLog::AREA_CARD, 'tarjeta', null !== $previous ? self::describe( $previous ) : '', self::describe( self::get( $id ) ), null !== $previous ? __( 'Tarjeta sustituida', SULAYR_TEXTDOMAIN ) : __( 'Tarjeta guardada', SULAYR_TEXTDOMAIN ) );

        return $id;
    }

    /**
     * El socio quita su tarjeta (C13). Avisa con la acción
     * «sulayr_payment_card_removed» para que se pueda avisar al club.
     *
     * @param   int $user_id Socio.
     * @return 	bool false si no tenía tarjeta activa.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function remove( int $user_id ): bool {
        $card = self::active( $user_id );
        if ( null === $card ) {
            return false;
        }

        self::close( (int) $card->id, self::STATUS_REMOVED );
        MemberLog::record( $user_id, MemberLog::AREA_CARD, 'tarjeta', self::describe( $card ), '', __( 'Tarjeta quitada', SULAYR_TEXTDOMAIN ) );
        do_action( 'sulayr_payment_card_removed', $user_id, $card );

        return true;
    }

    /**
     * Baja efectiva: se borra la referencia de la tarjeta (C14). La fila
     * queda para el histórico, sin referencia ni identificador.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function erase_for_leave( int $user_id ): void {
        global $wpdb;

        $card = self::active( $user_id );
        if ( null !== $card ) {
            self::close( (int) $card->id, self::STATUS_DELETED );
            MemberLog::record( $user_id, MemberLog::AREA_CARD, 'tarjeta', self::describe( $card ), '', __( 'Referencia borrada por la baja', SULAYR_TEXTDOMAIN ) );
        }

        $wpdb->update( self::table(), [ 'identifier' => '', 'cof_txnid' => '' ], [ 'user_id' => $user_id ], [ '%s', '%s' ], [ '%d' ] );
    }

    /**
     * La tarjeta está caducada en una fecha (caduca al terminar su mes).
     *
     * @param   object $card  Tarjeta.
     * @param   string $today Fecha Y-m-d.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_expired( object $card, string $today ): bool {
        $expiry = (string) $card->expiry;
        if ( false === self::valid_expiry( $expiry ) ) {
            return false;
        }

        // AAMM → «20AA-MM», comparable como texto con el «AAAA-MM» de hoy.
        return '20' . substr( $expiry, 0, 2 ) . '-' . substr( $expiry, 2, 2 ) < substr( $today, 0, 7 );
    }

    /**
     * Texto corto: «**** 1234 (cad. 08/28)».
     *
     * @param   object|null $card Tarjeta.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function describe( ?object $card ): string {
        if ( null === $card ) {
            return '';
        }

        $expiry = (string) $card->expiry;
        $text = '' !== (string) $card->masked ? (string) $card->masked : __( 'Tarjeta', SULAYR_TEXTDOMAIN );
        if ( self::valid_expiry( $expiry ) ) {
            $text .= ' ' . sprintf( __( '(cad. %s)', SULAYR_TEXTDOMAIN ), substr( $expiry, 2, 2 ) . '/' . substr( $expiry, 0, 2 ) );
        }

        return $text;
    }

    /**
     * Una tarjeta por id.
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
     * Nombre de la tabla.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function table(): string {
        return MembersSchema::table( MembersSchema::PAYMENT_CARDS );
    }

    /**
     * Cierra una tarjeta activa con un estado.
     *
     * @param   int    $id     Tarjeta.
     * @param   string $status Estado final.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function close( int $id, string $status ): void {
        global $wpdb;

        $wpdb->update( self::table(), [ 'status' => $status, 'closed_at' => current_time( 'mysql' ) ], [ 'id' => $id, 'status' => self::STATUS_ACTIVE ], [ '%s', '%s' ], [ '%d', '%s' ] );
    }

    /**
     * Deja solo los 4 últimos dígitos visibles, venga como venga.
     *
     * @param   string $masked Tarjeta (enmascarada por Redsys).
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function mask( string $masked ): string {
        $digits = preg_replace( '/\D/', '', $masked );
        if ( '' === $digits ) {
            return '';
        }

        return '**** ' . substr( $digits, -4 );
    }

    /**
     * AAMM con un mes válido.
     *
     * @param   string $expiry Caducidad.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function valid_expiry( string $expiry ): bool {
        return 1 === preg_match( '/^\d{2}(0[1-9]|1[0-2])$/', $expiry );
    }
}
