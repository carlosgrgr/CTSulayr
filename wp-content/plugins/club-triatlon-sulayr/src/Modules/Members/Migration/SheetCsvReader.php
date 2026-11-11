<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lee una pestaña de la hoja «Datos de socios» exportada a CSV.
 *
 * Busca la fila de cabecera (la que tiene «Email» y «DNI»), identifica las
 * columnas por su nombre y limpia cada valor: espacios y caracteres
 * invisibles, mayúsculas en el DNI, minúsculas en el email y fechas d/m/aaaa
 * a aaaa-mm-dd. Lo que no cuadra se anota en «issues» de la fila.
 *
 * «leave» es siempre el último día como socio: una fecha de baja en día 1
 * (como se apunta desde 2024) se convierte en el día anterior.
 */
class SheetCsvReader {

    /** Cabecera normalizada => campo. */
    private const COLUMNS = [
        'n socio' => 'number',
        'no socio' => 'number',
        'numero de socio' => 'number',
        'num socio' => 'number',
        'id' => 'number',
        'nombre' => 'name',
        'telefono' => 'phone',
        'email' => 'email',
        'dni' => 'dni',
        'fecha de nacimiento' => 'birth',
        'fecha de alta' => 'join',
        'fecha de baja' => 'leave',
    ];

    /** Caracteres invisibles que aparecen al copiar de WhatsApp. */
    private const INVISIBLE = "/[\x{00A0}\x{200B}\x{200C}\x{200D}\x{2060}\x{FEFF}]/u";

    /**
     * Lee el archivo.
     *
     * @param   string $path Ruta del CSV.
     * @return 	array<int, array> Filas con: line, number, name, phone, email, dni, birth, join, leave, issues.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function read( string $path ): array {
        if ( false === is_readable( $path ) ) {
            throw new \RuntimeException( sprintf( 'No se puede leer %s', $path ) );
        }

        $handle = fopen( $path, 'r' );
        $first = (string) fgets( $handle );
        rewind( $handle );
        $delimiter = substr_count( $first, ';' ) > substr_count( $first, ',' ) ? ';' : ',';

        $map = null;
        $rows = [];
        $line = 0;

        while ( false !== ( $cells = fgetcsv( $handle, 0, $delimiter, '"', '\\' ) ) ) {
            $line++;

            if ( null === $map ) {
                $map = self::header_map( $cells );
                continue;
            }

            $raw = [];
            foreach ( $map as $index => $field ) {
                $raw[ $field ] = self::clean( (string) ( $cells[ $index ] ?? '' ) );
            }

            if ( '' === ( $raw['name'] ?? '' ) && '' === ( $raw['email'] ?? '' ) && '' === ( $raw['dni'] ?? '' ) ) {
                continue;
            }

            $rows[] = self::normalize( $raw, $line );
        }

        fclose( $handle );

        if ( null === $map ) {
            throw new \RuntimeException( sprintf( 'No se encontró la fila de cabecera (con «Email» y «DNI») en %s', $path ) );
        }

        return $rows;
    }

    /**
     * Columnas de la fila si es la cabecera, o null.
     *
     * @param   array $cells Celdas.
     * @return 	array<int, string>|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function header_map( array $cells ): ?array {
        $map = [];
        foreach ( $cells as $index => $cell ) {
            $key = self::normalize_header( (string) $cell );
            if ( isset( self::COLUMNS[ $key ] ) && false === in_array( self::COLUMNS[ $key ], $map, true ) ) {
                $map[ $index ] = self::COLUMNS[ $key ];
            }
        }

        return in_array( 'email', $map, true ) && in_array( 'dni', $map, true ) ? $map : null;
    }

    /**
     * Texto de cabecera sin tildes, símbolos ni mayúsculas.
     *
     * @param   string $text Cabecera.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function normalize_header( string $text ): string {
        $text = strtolower( remove_accents( self::clean( $text ) ) );
        $text = str_replace( [ 'º', '°', '.', ':' ], ' ', $text );
        $text = preg_replace( '/[^a-z0-9 ]/', '', $text );

        return trim( (string) preg_replace( '/\s+/', ' ', (string) $text ) );
    }

    /**
     * Quita caracteres invisibles, BOM y espacios de los extremos.
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
     * DNI/NIE sin espacios ni guiones, en mayúsculas.
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

    /**
     * Fila normalizada.
     *
     * @param   array $raw  Valores limpios por campo.
     * @param   int   $line Línea del CSV.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function normalize( array $raw, int $line ): array {
        $issues = [];

        $number = null;
        if ( '' !== ( $raw['number'] ?? '' ) ) {
            if ( 1 === preg_match( '/^\d+$/', $raw['number'] ) && (int) $raw['number'] > 0 ) {
                $number = (int) $raw['number'];
            } else {
                $issues[] = sprintf( 'Nº de socio no válido «%s»', $raw['number'] );
            }
        }

        $email = self::normalize_email( $raw['email'] ?? '' );
        if ( '' !== $email && ( false === is_email( $email ) || '.con' === substr( $email, -4 ) ) ) {
            $issues[] = sprintf( 'Email mal formado «%s»', $email );
        }

        $dni = self::normalize_dni( $raw['dni'] ?? '' );
        if ( '' !== $dni && false === self::is_valid_dni( $dni ) ) {
            $issues[] = sprintf( 'DNI con letra incorrecta o formato raro «%s»', $dni );
        }

        $phone = (string) preg_replace( '/[^\d+]/', '', $raw['phone'] ?? '' );

        $birth = self::date( $raw['birth'] ?? '', 'nacimiento', $issues );
        $join = self::date( $raw['join'] ?? '', 'alta', $issues );

        $leave = '';
        $leave_raw = $raw['leave'] ?? '';
        if ( '' !== $leave_raw ) {
            if ( false !== strpos( $leave_raw, '1970' ) ) {
                $leave = CustomFields::MEMBER_LEAVE_DATE_UNKNOWN;
            } else {
                $leave = self::date( $leave_raw, 'baja', $issues );
                // Una fecha en día 1 es el primer día ya de baja: el último día como socio es el anterior.
                if ( '' !== $leave && '01' === substr( $leave, -2 ) ) {
                    $leave = gmdate( 'Y-m-d', strtotime( $leave . ' -1 day' ) );
                }
            }
        }

        return [
            'line' => $line,
            'number' => $number,
            'name' => (string) preg_replace( '/\s+/', ' ', $raw['name'] ?? '' ),
            'phone' => $phone,
            'email' => $email,
            'dni' => $dni,
            'birth' => $birth,
            'join' => $join,
            'join_raw' => (string) ( $raw['join'] ?? '' ),
            'leave' => $leave,
            'issues' => $issues,
        ];
    }

    /**
     * Fecha d/m/aaaa, d/m/aa o aaaa-mm-dd a aaaa-mm-dd. Vacía si no es válida.
     *
     * @param   string   $value  Texto.
     * @param   string   $label  Nombre de la fecha para el aviso.
     * @param   string[] $issues Avisos.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function date( string $value, string $label, array &$issues ): string {
        if ( '' === $value ) {
            return '';
        }

        $date = null;
        // Año con dos cifras (09/09/25): PHP lo lee como 1970-2069.
        $formats = 1 === preg_match( '#^\d{1,2}/\d{1,2}/\d{2}$#', $value ) ? [ '!d/m/y', '!j/n/y' ] : [ '!d/m/Y', '!j/n/Y', '!Y-m-d', '!d-m-Y' ];
        foreach ( $formats as $format ) {
            $parsed = \DateTime::createFromFormat( $format, $value );
            $errors = \DateTime::getLastErrors();
            if ( false !== $parsed && ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) ) ) {
                $date = $parsed;
                break;
            }
        }

        if ( null === $date ) {
            $issues[] = sprintf( 'Fecha de %s no válida «%s»', $label, $value );
            return '';
        }

        $year = (int) $date->format( 'Y' );
        if ( 0 === strpos( $format, '!d/m/y' ) || 0 === strpos( $format, '!j/n/y' ) ) {
            if ( $year > (int) current_time( 'Y' ) + 1 ) {
                $date->modify( '-100 years' );
                $year -= 100;
            }
        }
        if ( $year < 1920 || $year > (int) current_time( 'Y' ) + 1 ) {
            $issues[] = sprintf( 'Fecha de %s imposible «%s»', $label, $value );
            return '';
        }

        return $date->format( 'Y-m-d' );
    }
}
