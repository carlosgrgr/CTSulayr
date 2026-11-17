<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Puntos de un resultado (SPEC-RANKING-INTERNO §3.3).
 *
 * Por tiempo: base × (tiempo del ganador / tiempo del socio) × coeficiente + bonus + interés.
 * Respaldo por posición: base × (1 − (posición − 1) / finalizados) × coeficiente + bonus + interés.
 * El interés es el bonus por interés del club de la competición (§15, R42).
 * DNF, DSQ, DNS y NC: 0, sin bonus. Se redondea a 2 decimales solo al final.
 */
class PointsCalculator {

    /**
     * Calcula los puntos de la absoluta y del grupo de edad.
     *
     * Entrada:
     * - status, time_s (int|null), position_sex (int|null), position_age_group (int|null)
     * - coef (float), base (float), bonus (float)
     * - interest (float): bonus por interés del club de la competición (0 si es normal).
     * - ref_abs, ref_grp: null o [ 'winner_time' => int|null, 'participants' => int|null ]
     * - has_group (bool): el socio tiene grupo de edad.
     * - absolute_category (bool): corrió en la categoría AB (R29).
     * - group_from_absolute (bool): contrarreloj por equipos (R31).
     *
     * @param   array $in Datos.
     * @return 	array{points_absolute: float, points_age_group: float|null, method_absolute: string, method_age_group: string, notes: string[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function calculate( array $in ): array {
        $base = (float) ( $in['base'] ?? 1000 );
        // El bonus por terminar y el de interés se suman igual: solo a quien termina y tiene referencia.
        $bonus = (float) ( $in['bonus'] ?? 50 ) + max( 0.0, (float) ( $in['interest'] ?? 0 ) );
        $coef = (float) ( $in['coef'] ?? 1 );
        $time = isset( $in['time_s'] ) ? (int) $in['time_s'] : 0;
        $has_group = ! empty( $in['has_group'] );
        $notes = [];

        if ( Keys::STATUS_FINISHED !== ( $in['status'] ?? '' ) ) {
            return [
                'points_absolute' => 0.0,
                'points_age_group' => $has_group ? 0.0 : null,
                'method_absolute' => Keys::METHOD_STATUS,
                'method_age_group' => $has_group ? Keys::METHOD_STATUS : Keys::METHOD_NONE,
                'notes' => [],
            ];
        }

        list( $absolute, $method_absolute ) = self::points( $in['ref_abs'] ?? null, $time, (int) ( $in['position_sex'] ?? 0 ), $base, $coef, $bonus );
        if ( null === $absolute ) {
            $absolute = 0.0;
            $notes[] = 'Sin referencia absoluta: 0 puntos.';
        }

        $group = null;
        $method_group = Keys::METHOD_NONE;
        if ( $has_group ) {
            if ( ! empty( $in['group_from_absolute'] ) ) {
                $group = $absolute;
                $method_group = $method_absolute;
            } elseif ( ! empty( $in['absolute_category'] ) ) {
                $group = $base * $coef + $bonus;
                $method_group = Keys::METHOD_ABSOLUTE_CATEGORY;
            } else {
                list( $group, $method_group ) = self::points( $in['ref_grp'] ?? null, $time, (int) ( $in['position_age_group'] ?? 0 ), $base, $coef, $bonus );
                if ( null === $group ) {
                    $notes[] = 'No puntúa en grupo de edad: falta la referencia del grupo.';
                }
            }
        }

        return [
            'points_absolute' => round( $absolute, 2 ),
            'points_age_group' => null === $group ? null : round( $group, 2 ),
            'method_absolute' => $method_absolute,
            'method_age_group' => $method_group,
            'notes' => $notes,
        ];
    }

    /**
     * Puntos frente a una referencia, por tiempo o por posición.
     *
     * @param   array|null $ref      Referencia.
     * @param   int        $time     Segundos del socio (0 = sin tiempo).
     * @param   int        $position Posición (0 = sin posición).
     * @param   float      $base     Puntos base.
     * @param   float      $coef     Coeficiente.
     * @param   float      $bonus    Bonus.
     * @return 	array{0: float|null, 1: string}
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function points( ?array $ref, int $time, int $position, float $base, float $coef, float $bonus ): array {
        if ( null === $ref ) {
            return [ null, Keys::METHOD_NONE ];
        }

        $winner = (int) ( $ref['winner_time'] ?? 0 );
        if ( $winner > 0 && $time > 0 ) {
            return [ $base * ( $winner / $time ) * $coef + $bonus, Keys::METHOD_TIME ];
        }

        $participants = (int) ( $ref['participants'] ?? 0 );
        if ( $participants >= 1 && $position >= 1 && $position <= $participants ) {
            return [ $base * ( 1 - ( $position - 1 ) / $participants ) * $coef + $bonus, Keys::METHOD_POSITION ];
        }

        return [ null, Keys::METHOD_NONE ];
    }
}
