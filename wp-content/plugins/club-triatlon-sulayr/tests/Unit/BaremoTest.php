<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\Baremo;
use PHPUnit\Framework\TestCase;

/**
 * Bonus por interés del club en el baremo (SPEC-RANKING-INTERNO §15.3, T38 y T39).
 */
class BaremoTest extends TestCase {

    public function test_valores_por_defecto(): void {
        $this->assertSame( [ 0, 50, 100, 150, 200, 250, 300 ], Baremo::interest_values( Baremo::defaults() ) );
        // Baremo guardado antes del §15: los mismos valores.
        $this->assertSame( [ 0, 50, 100, 150, 200, 250, 300 ], Baremo::interest_values( Baremo::normalize( [ 'base' => 1000 ] ) ) );
    }

    public function test_maximo_cero_sin_bonus(): void {
        $this->assertSame( [ 0 ], Baremo::interest_values( Baremo::normalize( [ 'interest_step' => 50, 'interest_max' => 0 ] ) ) );
    }

    public function test_maximo_no_multiplo_se_redondea_al_leer(): void {
        $b = Baremo::normalize( [ 'interest_step' => 40, 'interest_max' => 130 ] );
        $this->assertSame( 120, $b['interest_max'] );
        $this->assertSame( [ 0, 40, 80, 120 ], Baremo::interest_values( $b ) );
    }

    public function test_t38_valores_cerrados(): void {
        $values = Baremo::interest_values( Baremo::defaults() );
        foreach ( [ 0, 50, 100, 150, 200, 250, 300 ] as $ok ) {
            $this->assertContains( $ok, $values );
        }
        foreach ( [ 75, 350, -50 ] as $bad ) {
            $this->assertNotContains( $bad, $values );
        }
    }

    public function test_validacion_de_ajustes(): void {
        $this->assertSame( [], Baremo::interest_problems( '50', '300' ) );
        $this->assertSame( [], Baremo::interest_problems( 50, 0 ) );
        $this->assertCount( 1, Baremo::interest_problems( 0, 300 ) );
        $this->assertCount( 1, Baremo::interest_problems( 501, 0 ) );
        $this->assertCount( 1, Baremo::interest_problems( 50, 1050 ) );
        $this->assertCount( 1, Baremo::interest_problems( 50, -50 ) );
        $this->assertCount( 1, Baremo::interest_problems( 12.5, 100 ) );
        $this->assertStringContainsString( 'múltiplo', Baremo::interest_problems( 50, 275 )[0] );
    }

    public function test_t39_bajar_el_maximo_con_una_competicion_que_lo_usa(): void {
        $competitions = [
            [ 'name' => 'Triatlón de Motril', 'interest_bonus' => 300 ],
            [ 'name' => 'Duatlón de Úbeda', 'interest_bonus' => 0 ],
            [ 'name' => 'Acuatlón de Almuñécar', 'interest_bonus' => 100 ],
        ];
        $values = Baremo::interest_values( [ 'interest_step' => 50, 'interest_max' => 250 ] );
        $this->assertSame( [ 'Triatlón de Motril (+300)' ], Baremo::interest_conflicts( $values, $competitions ) );
        $this->assertSame( [], Baremo::interest_conflicts( Baremo::interest_values( Baremo::defaults() ), $competitions ) );
        // Cambiar el salto también puede dejar valores fuera.
        $this->assertSame( [ 'Acuatlón de Almuñécar (+100)' ], Baremo::interest_conflicts( Baremo::interest_values( [ 'interest_step' => 75, 'interest_max' => 300 ] ), $competitions ) );
    }
}
