<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Número de pedido, importes y códigos de respuesta de Redsys (SPEC-COBROS §5.3).
 */
class RedsysOrder {

    /** Letra que separa los pedidos de cuotas de los de la tienda (mismo terminal, C2). */
    public const PREFIX_LETTER = 'Q';

    /** Cifras del id de la transacción. */
    private const ID_DIGITS = 7;

    /**
     * Ds_Order: AAMM + Q + id de la transacción con 7 cifras (12 caracteres).
     *
     * Redsys pide de 4 a 12 caracteres con los 4 primeros numéricos.
     *
     * @param   int    $transaction_id Id de la transacción (1 a 9.999.999).
     * @param   string $date           Fecha Y-m-d (o Y-m-d H:i:s) de la transacción.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function number( int $transaction_id, string $date ): string {
        if ( $transaction_id < 1 || $transaction_id >= 10 ** self::ID_DIGITS ) {
            throw new \InvalidArgumentException( 'Id de transacción fuera de rango para Ds_Order.' );
        }
        if ( 1 !== preg_match( '/^\d{2}(\d{2})-(\d{2})-\d{2}/', $date, $m ) ) {
            throw new \InvalidArgumentException( 'Fecha no válida para Ds_Order.' );
        }

        return $m[1] . $m[2] . self::PREFIX_LETTER . str_pad( (string) $transaction_id, self::ID_DIGITS, '0', STR_PAD_LEFT );
    }

    /**
     * Id de la transacción de un Ds_Order de cuotas, o 0 si no es de cuotas.
     *
     * @param   string $order Ds_Order.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function transaction_id( string $order ): int {
        if ( 1 !== preg_match( '/^\d{4}' . self::PREFIX_LETTER . '(\d{' . self::ID_DIGITS . '})$/', $order, $m ) ) {
            return 0;
        }

        return (int) $m[1];
    }

    /**
     * Importe en céntimos, como lo pide Redsys.
     *
     * @param   float $euros Importe en euros.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function cents( float $euros ): int {
        return (int) round( $euros * 100 );
    }

    /**
     * Importe en euros de un importe de Redsys en céntimos.
     *
     * @param   int|string $cents Céntimos.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function euros( $cents ): float {
        return round( (int) $cents / 100, 2 );
    }

    /**
     * Ds_Response autorizado: de 0000 a 0099 (§5.1, paso 3).
     *
     * @param   string|int|null $response Ds_Response.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_authorized( $response ): bool {
        $response = trim( (string) $response );
        if ( 1 !== preg_match( '/^\d{1,4}$/', $response ) ) {
            return false;
        }

        return (int) $response <= 99;
    }
}
