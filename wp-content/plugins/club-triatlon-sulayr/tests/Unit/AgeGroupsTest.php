<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use PHPUnit\Framework\TestCase;

/**
 * T18 a T20, T29 y T31 de SPEC-RANKING-INTERNO §10.1.
 */
class AgeGroupsTest extends TestCase {

    private function groups(): AgeGroups {
        return new AgeGroups( AgeGroups::DEFAULTS );
    }

    public function test_t18_veterano_1(): void {
        $this->assertSame( 'veterano-1', $this->groups()->for_birth( '1986-12-31', 2027 ) );
    }

    public function test_t19_limite_de_grupo(): void {
        $this->assertSame( 'senior', $this->groups()->for_birth( '1988-01-01', 2027 ) );
    }

    public function test_t20_sin_fecha_de_nacimiento(): void {
        $this->assertSame( '', $this->groups()->for_birth( '', 2027 ) );
    }

    public function test_t31_juvenil(): void {
        $this->assertSame( 'juvenil', $this->groups()->for_birth( '2011-03-10', 2027 ) );
    }

    public function test_extremos(): void {
        $g = $this->groups();
        $this->assertSame( 'prebenjamin', $g->for_birth( '2021-06-01', 2027 ) );
        $this->assertSame( 'veterano-5', $g->for_birth( '1940-06-01', 2027 ) );
        $this->assertSame( 'sub23', $g->for_birth( '2004-01-01', 2027 ) );
    }

    public function test_t29_aviso_de_categoria(): void {
        $g = $this->groups();
        $this->assertTrue( $g->mismatch( 'V2M', 'veterano-1' ) );
        $this->assertFalse( $g->mismatch( 'V1M', 'veterano-1' ) );
        $this->assertFalse( $g->mismatch( 'ABM', 'senior' ) );
        $this->assertFalse( $g->mismatch( 'V1M', 'veterano-5' ) );
    }

    public function test_problemas_de_configuracion(): void {
        $this->assertSame( [], AgeGroups::problems( AgeGroups::DEFAULTS ) );
        $problems = AgeGroups::problems( [
            [ 'key' => 'a', 'min' => 10, 'max' => 20 ],
            [ 'key' => 'b', 'min' => 20, 'max' => 30 ],
            [ 'key' => 'a', 'min' => 40, 'max' => 30 ],
        ] );
        $this->assertCount( 3, $problems );
    }
}
