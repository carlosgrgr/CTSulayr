<?php
namespace ClubTriatlonSulayr\Modules\Members;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Fechas de socios. Los campos date_picker de ACF guardan Ymd; el historial
 * usa Y-m-d.
 */
class MemberDates {

    /**
     * Fecha de ACF (Ymd) a Y-m-d. Deja igual lo que ya es Y-m-d.
     *
     * @param   string $value Valor guardado.
     * @return 	string Y-m-d o ''.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function acf_to_iso( string $value ): string {
        $value = trim( $value );
        if ( 1 === preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
    }

    /**
     * Y-m-d al formato que guarda ACF (Ymd).
     *
     * @param   string $iso Y-m-d.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function iso_to_acf( string $iso ): string {
        return str_replace( '-', '', $iso );
    }

    /**
     * Suma días a una fecha Y-m-d.
     *
     * @param   string $date Y-m-d.
     * @param   int    $days Días (negativos para restar).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_days( string $date, int $days ): string {
        return ( new \DateTimeImmutable( $date ) )->modify( sprintf( '%+d day', $days ) )->format( 'Y-m-d' );
    }

    /**
     * Día 1 del mes siguiente a $date (o $months meses después).
     *
     * @param   string $date   Y-m-d.
     * @param   int    $months Meses.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function first_of_month_after( string $date, int $months = 1 ): string {
        return ( new \DateTimeImmutable( substr( $date, 0, 7 ) . '-01' ) )->modify( sprintf( '+%d month', $months ) )->format( 'Y-m-d' );
    }

    /**
     * Fecha efectiva de un cambio o baja pedido el día $date (spec 6.1, D25, D26):
     * el día 1 del mes siguiente si faltan al menos $notice_days días para fin
     * de mes (último día − día de la solicitud); si no, el día 1 del otro.
     *
     * @param   string $date        Día de la solicitud Y-m-d.
     * @param   int    $notice_days Días de aviso.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function effective_for( string $date, int $notice_days ): string {
        $d = new \DateTimeImmutable( $date );
        $remaining = (int) $d->format( 't' ) - (int) $d->format( 'j' );

        return self::first_of_month_after( $date, $remaining >= $notice_days ? 1 : 2 );
    }

    /**
     * True si $date es un día 1.
     *
     * @param   string $date Y-m-d.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_first_of_month( string $date ): bool {
        $d = \DateTime::createFromFormat( '!Y-m-d', $date );

        return false !== $d && $d->format( 'Y-m-d' ) === $date && '01' === $d->format( 'd' );
    }

    /**
     * d/m/Y para mostrar, o «—».
     *
     * @param   string|null $date Y-m-d.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function show( ?string $date ): string {
        $time = null !== $date && '' !== $date ? strtotime( $date ) : false;

        return false !== $time ? date_i18n( 'd/m/Y', $time ) : '—';
    }
}
