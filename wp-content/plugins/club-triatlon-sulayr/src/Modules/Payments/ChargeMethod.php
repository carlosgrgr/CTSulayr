<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\MemberSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Medio de cobro del socio (SPEC-COBROS C6 a C9, §4.1).
 *
 * Meta de usuario META: «tpv» u «otro». Si no existe, vale el ajuste de
 * Socios → Ajustes → Pago con tarjeta (C8). Así, al terminar el piloto basta
 * con cambiar el ajuste para que todos pasen a «Tarjeta (TPV)» (C26), y las
 * excepciones son las fichas con «otro» puesto a mano.
 */
class ChargeMethod {

    public const META = 'sulayr_charge_method';

    public const TPV = 'tpv';
    public const OTHER = 'otro';

    /**
     * Medios con su nombre.
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function choices(): array {
        return [
            self::TPV => __( 'Tarjeta (TPV)', SULAYR_TEXTDOMAIN ),
            self::OTHER => __( 'Otro medio', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Nombre de un medio.
     *
     * @param   string $method Medio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function label( string $method ): string {
        return self::choices()[ $method ] ?? $method;
    }

    /**
     * Medio de cobro de las fichas sin medio propio (C8).
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function default_method(): string {
        return self::TPV === MemberSettings::get( 'charge_method_default' ) ? self::TPV : self::OTHER;
    }

    /**
     * Medio guardado en la ficha, o '' si sigue el ajuste.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function stored( int $user_id ): string {
        $value = (string) get_user_meta( $user_id, self::META, true );

        return isset( self::choices()[ $value ] ) ? $value : '';
    }

    /**
     * Medio de cobro efectivo de un socio.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $user_id ): string {
        $stored = self::stored( $user_id );

        return '' !== $stored ? $stored : self::default_method();
    }

    /**
     * El socio entra en el cobro automático (C6).
     *
     * @param   int $user_id Socio.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_tpv( int $user_id ): bool {
        return self::TPV === self::get( $user_id );
    }
}
