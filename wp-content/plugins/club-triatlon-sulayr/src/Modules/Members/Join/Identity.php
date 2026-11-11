<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Limpieza y validación de DNI/NIE y email (spec §5.1, §11).
 *
 * La letra de control viene de SheetCsvReader::is_valid_dni(), retirada con
 * la migración (commit fab85c343).
 */
class Identity {

    /** Espacios y caracteres invisibles que llegan al copiar y pegar. */
    private const INVISIBLE = "/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u";

    /**
     * Quita caracteres invisibles y espacios de los extremos.
     *
     * @param   string $value Valor.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function clean( string $value ): string {
        return trim( (string) preg_replace( self::INVISIBLE, '', $value ) );
    }

    /**
     * DNI/NIE sin espacios, puntos ni guiones, en mayúsculas.
     *
     * @param   string $dni DNI.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function normalize_dni( string $dni ): string {
        return strtoupper( (string) preg_replace( '/[\s\-\.]/', '', self::clean( $dni ) ) );
    }

    /**
     * Email limpio en minúsculas.
     *
     * @param   string $email Email.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function normalize_email( string $email ): string {
        return strtolower( (string) preg_replace( '/\s+/', '', self::clean( $email ) ) );
    }

    /**
     * True si la letra de control del DNI o NIE es correcta.
     *
     * @param   string $dni DNI normalizado.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_valid_dni( string $dni ): bool {
        if ( 1 !== preg_match( '/^([XYZ]?)(\d{7,8})([A-Z])$/', $dni, $m ) ) {
            return false;
        }

        $number = $m[2];
        if ( '' !== $m[1] ) {
            $number = strtr( $m[1], [ 'X' => '0', 'Y' => '1', 'Z' => '2' ] ) . $number;
        }

        return 'TRWAGMYFPDXBNJZSQVHLCKE'[ (int) $number % 23 ] === $m[3];
    }
}
