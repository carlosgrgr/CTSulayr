<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\Standings;
use PHPUnit\Framework\TestCase;

/**
 * T13 a T17 de SPEC-RANKING-INTERNO §10.1.
 */
class StandingsTest extends TestCase {

    private function rows( int $user_id, array $points, array $finished = [] ): array {
        $rows = [];
        foreach ( $points as $i => $p ) {
            $rows[] = [
                'result_id' => $user_id * 100 + $i,
                'user_id' => $user_id,
                'points' => $p,
                'finished' => $finished[ $i ] ?? true,
                'race_date' => sprintf( '2027-%02d-01', $i + 1 ),
            ];
        }
        return $rows;
    }

    public function test_t13_mejores_6_de_8(): void {
        $rows = $this->rows( 1, [ 1150, 966.67, 0, 853.92, 1050, 1100, 1116.67, 900 ], [ true, true, false, true, true, true, true, true ] );
        $line = Standings::build( $rows, 6 )[0];
        $this->assertSame( 6283.34, $line['total'] );
        $this->assertSame( 7, $line['finished'] );
        $this->assertSame( 1150.0, $line['best'] );
        $this->assertCount( 6, $line['counted'] );
        $this->assertNotContains( 102, $line['counted'] );
        $this->assertNotContains( 103, $line['counted'] );
    }

    public function test_t14_menos_de_n(): void {
        $line = Standings::build( $this->rows( 1, [ 900, 800, 700 ] ), 6 )[0];
        $this->assertSame( 2400.0, $line['total'] );
    }

    public function test_t15_desempate_por_terminadas(): void {
        $rows = array_merge(
            $this->rows( 1, [ 1000, 1000, 1000, 0 ], [ true, true, true, true ] ),
            $this->rows( 2, [ 1000, 1000, 1000, 0, 0 ], [ true, true, true, true, true ] )
        );
        $lines = Standings::build( $rows, 6 );
        $this->assertSame( 2, $lines[0]['user_id'] );
        $this->assertSame( 1, $lines[0]['rank'] );
        $this->assertSame( 2, $lines[1]['rank'] );
    }

    public function test_t16_desempate_por_mejor_resultado(): void {
        $rows = array_merge(
            $this->rows( 1, [ 1100, 950, 950 ] ),
            $this->rows( 2, [ 1050, 1000, 950 ] )
        );
        $lines = Standings::build( $rows, 6 );
        $this->assertSame( 1, $lines[0]['user_id'] );
    }

    public function test_t17_empate_total(): void {
        $rows = array_merge(
            $this->rows( 3, [ 1000, 900 ] ),
            $this->rows( 1, [ 1000, 900 ] ),
            $this->rows( 4, [ 800 ] )
        );
        $lines = Standings::build( $rows, 6, [ 1 => 'alvarez', 3 => 'zapata', 4 => 'martin' ] );
        $this->assertSame( [ 1, 1, 3 ], array_column( $lines, 'rank' ) );
        $this->assertSame( 1, $lines[0]['user_id'] );
        $this->assertSame( 3, $lines[1]['user_id'] );
    }

    public function test_resultados_sin_puntos_no_entran(): void {
        $rows = $this->rows( 1, [ null, null ] );
        $this->assertSame( [], Standings::build( $rows, 6 ) );
    }

    public function test_empate_en_el_corte_cuenta_la_fecha_mas_antigua(): void {
        $line = Standings::build( $this->rows( 1, [ 900, 900 ] ), 1 )[0];
        $this->assertSame( [ 100 ], $line['counted'] );
    }
}
