<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Clasificación de una temporada a partir de los puntos (SPEC-RANKING-INTERNO §3.4).
 *
 * Cuentan los N mejores resultados de cada socio. Orden: total, pruebas
 * terminadas y mejor resultado; con los tres iguales se comparte puesto
 * (1, 2, 2, 4) y se ordena por nombre solo para pintar.
 */
class Standings {

    /**
     * Construye la clasificación.
     *
     * @param   array $rows       Resultados: [ result_id, user_id, points (float|null), finished (bool), race_date (Y-m-d) ].
     *                            Los de points null no entran.
     * @param   int   $n          Resultados que cuentan.
     * @param   array $sort_names user_id => texto para ordenar los empates, ya normalizado (sin tildes y en minúsculas).
     * @return 	array<int, array{rank: int, user_id: int, total: float, finished: int, best: float, results: int, counted: int[]}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function build( array $rows, int $n, array $sort_names = [] ): array {
        $n = max( 1, $n );
        $by_user = [];
        foreach ( $rows as $row ) {
            if ( null === $row['points'] ) {
                continue;
            }
            $by_user[ (int) $row['user_id'] ][] = $row;
        }

        $lines = [];
        foreach ( $by_user as $user_id => $results ) {
            // Mayor puntuación primero; con los mismos puntos, la fecha más antigua.
            usort( $results, function ( $a, $b ) {
                $cmp = $b['points'] <=> $a['points'];
                if ( 0 !== $cmp ) {
                    return $cmp;
                }
                $cmp = strcmp( (string) $a['race_date'], (string) $b['race_date'] );
                return 0 !== $cmp ? $cmp : ( (int) $a['result_id'] <=> (int) $b['result_id'] );
            } );

            $counted = array_slice( $results, 0, $n );
            $total = 0.0;
            foreach ( $counted as $row ) {
                $total += (float) $row['points'];
            }

            $finished = 0;
            foreach ( $results as $row ) {
                if ( ! empty( $row['finished'] ) ) {
                    $finished++;
                }
            }

            $lines[] = [
                'rank' => 0,
                'user_id' => (int) $user_id,
                'total' => round( $total, 2 ),
                'finished' => $finished,
                'best' => round( (float) $results[0]['points'], 2 ),
                'results' => count( $results ),
                'counted' => array_map( function ( $row ) {
                    return (int) $row['result_id'];
                }, $counted ),
            ];
        }

        usort( $lines, function ( $a, $b ) use ( $sort_names ) {
            $cmp = self::compare( $a, $b );
            if ( 0 !== $cmp ) {
                return $cmp;
            }
            $cmp = strcmp( (string) ( $sort_names[ $a['user_id'] ] ?? '' ), (string) ( $sort_names[ $b['user_id'] ] ?? '' ) );
            return 0 !== $cmp ? $cmp : ( $a['user_id'] <=> $b['user_id'] );
        } );

        foreach ( $lines as $i => $line ) {
            $lines[ $i ]['rank'] = ( $i > 0 && 0 === self::compare( $lines[ $i - 1 ], $line ) ) ? $lines[ $i - 1 ]['rank'] : $i + 1;
        }

        return $lines;
    }

    /**
     * Orden por total, terminadas y mejor resultado (R10).
     *
     * @param   array $a Línea.
     * @param   array $b Línea.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function compare( array $a, array $b ): int {
        // Se comparan en céntimos para no depender de decimales de coma flotante.
        $cmp = (int) round( $b['total'] * 100 ) <=> (int) round( $a['total'] * 100 );
        if ( 0 !== $cmp ) {
            return $cmp;
        }
        $cmp = $b['finished'] <=> $a['finished'];
        if ( 0 !== $cmp ) {
            return $cmp;
        }

        return (int) round( $b['best'] * 100 ) <=> (int) round( $a['best'] * 100 );
    }
}
