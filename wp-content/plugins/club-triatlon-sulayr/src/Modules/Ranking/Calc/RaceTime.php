<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Tiempos de carrera: h:mm:ss, hh:mm:ss o mm:ss ↔ segundos (SPEC-RANKING-INTERNO §5).
 */
class RaceTime {

    /** Máximo admitido: menos de 24 horas. */
    private const MAX = 86400;

    /**
     * Segundos de un tiempo escrito, o null si el formato no vale.
     *
     * @param   string $value Texto.
     * @return 	int|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function parse( string $value ): ?int {
        $value = trim( $value );
        if ( 1 === preg_match( '/^(\d{1,2}):(\d{2}):(\d{2})$/', $value, $m ) ) {
            $h = (int) $m[1];
            $min = (int) $m[2];
            $s = (int) $m[3];
            // En h:mm:ss los minutos no pasan de 59.
            if ( $min > 59 ) {
                return null;
            }
        } elseif ( 1 === preg_match( '/^(\d{1,3}):(\d{2})$/', $value, $m ) ) {
            // En mm:ss los minutos sí pueden pasar de 59 (62:03).
            $h = 0;
            $min = (int) $m[1];
            $s = (int) $m[2];
        } else {
            return null;
        }

        if ( $s > 59 ) {
            return null;
        }

        $total = $h * 3600 + $min * 60 + $s;

        return $total > 0 && $total < self::MAX ? $total : null;
    }

    /**
     * Segundos como h:mm:ss, o '' si no hay tiempo.
     *
     * @param   int|null $seconds Segundos.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function format( ?int $seconds ): string {
        if ( null === $seconds || $seconds <= 0 ) {
            return '';
        }

        return sprintf( '%d:%02d:%02d', intdiv( $seconds, 3600 ), intdiv( $seconds % 3600, 60 ), $seconds % 60 );
    }
}
