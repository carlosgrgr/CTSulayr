<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\NameMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Emparejamiento por nombre de la importación (SPEC-RANKING-INTERNO §14.4).
 */
class NameMatcherTest extends TestCase {

    private function matcher(): NameMatcher {
        return new NameMatcher(
            [
                1 => [ 'first' => 'Jorge', 'last' => 'Arranz Ortiz', 'sex' => 'm', 'code' => 'V1', 'group_label' => 'Veterano 1' ],
                2 => [ 'first' => 'Gabriel', 'last' => 'del Moral Lázaro', 'sex' => 'm', 'code' => 'SN', 'group_label' => 'Sénior' ],
                3 => [ 'first' => 'Francisco', 'last' => 'Bartolomé Rodríguez', 'sex' => 'm', 'code' => 'SN', 'group_label' => 'Sénior' ],
                4 => [ 'first' => 'Carlos', 'last' => 'García', 'sex' => 'm', 'code' => 'SN', 'group_label' => 'Sénior' ],
                5 => [ 'first' => 'Carlos', 'last' => 'García López', 'sex' => 'm', 'code' => 'V1', 'group_label' => 'Veterano 1' ],
                6 => [ 'first' => 'María', 'last' => 'Pérez Gil', 'sex' => 'f', 'code' => 'SN', 'group_label' => 'Sénior' ],
                7 => [ 'first' => 'Esteban', 'last' => 'Vidal Pérez', 'sex' => 'm', 'code' => 'SN', 'group_label' => 'Sénior' ],
                8 => [ 'first' => 'Hugo', 'last' => 'Fernández Porcuna', 'sex' => 'm', 'code' => '23', 'group_label' => 'Sub-23' ],
            ],
            [ 'SULAYR' ]
        );
    }

    public function test_normaliza(): void {
        $this->assertSame( 'inigo molina rozua', NameMatcher::normalize( 'ÍÑIGO MOLINA ROZÚA' ) );
        $this->assertSame( [ 'gabriel', 'moral', 'lazaro' ], NameMatcher::tokens( 'GABRIEL DEL MORAL LÁZARO' ) );
    }

    public function test_equipo_del_club(): void {
        $m = $this->matcher();
        $this->assertTrue( $m->is_club_team( 'SANCHO CASUAL BURGER-CT SULAYR' ) );
        $this->assertTrue( $m->is_club_team( 'C.T. Sulayr' ) );
        $this->assertFalse( $m->is_club_team( 'ESPEJO BIKE TEAM' ) );
    }

    public function test_nombre_identico_sin_tildes_ni_orden(): void {
        $r = $this->matcher()->match( 'GABRIEL DEL MORAL LÁZARO', 'SANCHO CASUAL BURGER-CT SULAYR', 'm', 'SNM' );
        $this->assertSame( NameMatcher::SURE, $r['status'] );
        $this->assertSame( 2, $r['candidates'][0]['user_id'] );
    }

    public function test_segundo_nombre_en_el_pdf(): void {
        $r = $this->matcher()->match( 'JORGE DAVID ARRANZ ORTIZ', 'SANCHO CASUAL BURGER-CT SULAYR', 'm', 'V1M' );
        $this->assertSame( NameMatcher::SURE, $r['status'] );
        $this->assertSame( 1, $r['candidates'][0]['user_id'] );
    }

    public function test_categoria_que_no_cuadra_es_duda(): void {
        $r = $this->matcher()->match( 'ESTEBAN VIDAL PÉREZ', 'C.T. SULAYR', 'm', 'V1M' );
        $this->assertSame( NameMatcher::DOUBT, $r['status'] );
        $this->assertStringContainsString( 'V1M', $r['candidates'][0]['reason'] );
    }

    public function test_sexo_distinto_no_empareja(): void {
        $r = $this->matcher()->match( 'MARÍA PÉREZ GIL', 'C.T. SULAYR', 'm', 'SNM' );
        $this->assertSame( NameMatcher::NONE, $r['status'] );
    }

    public function test_dos_candidatos_es_duda(): void {
        // «Carlos García» (un apellido) y «Carlos García López» encajan los dos.
        $r = $this->matcher()->match( 'CARLOS GARCÍA LÓPEZ', 'MAOC - SULAYR', 'm', '' );
        $this->assertSame( NameMatcher::DOUBT, $r['status'] );
        $this->assertSame( 5, $r['candidates'][0]['user_id'] );
        $this->assertCount( 2, $r['candidates'] );
    }

    public function test_solo_apellidos_es_duda(): void {
        // «Paco» por «Francisco».
        $r = $this->matcher()->match( 'PACO BARTOLOMÉ RODRÍGUEZ', 'TAPADOS SULAYR', 'm', 'SNM' );
        $this->assertSame( NameMatcher::DOUBT, $r['status'] );
        $this->assertSame( 3, $r['candidates'][0]['user_id'] );
    }

    public function test_errata_es_duda(): void {
        $r = $this->matcher()->match( 'HUGO FERNANDEZ PORCUNNA', 'MAOC - SULAYR', 'm', '23M' );
        $this->assertSame( NameMatcher::DOUBT, $r['status'] );
        $this->assertSame( 8, $r['candidates'][0]['user_id'] );
    }

    public function test_otro_club(): void {
        $m = $this->matcher();
        // Nombre idéntico en otro equipo: seguro, con el aviso.
        $full = $m->match( 'HUGO FERNÁNDEZ PORCUNA', 'ESPEJO BIKE TEAM', 'm', '23M' );
        $this->assertSame( NameMatcher::SURE, $full['status'] );
        $this->assertStringContainsString( 'otro club', $full['candidates'][0]['reason'] );
        // Parecido solo por apellidos y nombre en otro equipo: duda.
        $strong = $m->match( 'JORGE DAVID ARRANZ ORTIZ', 'ESPEJO BIKE TEAM', 'm', 'V1M' );
        $this->assertSame( NameMatcher::DOUBT, $strong['status'] );
        // Parecido flojo en otro equipo: no se enseña.
        $loose = $m->match( 'ESTEBAN VIDAL SÁNCHEZ', 'ESPEJO BIKE TEAM', 'm', 'SNM' );
        $this->assertSame( NameMatcher::NONE, $loose['status'] );
    }

    public function test_mismo_socio_en_dos_filas(): void {
        $out = $this->matcher()->match_all( [
            [ 'name' => 'GABRIEL DEL MORAL LÁZARO', 'club' => 'TAPADOS SULAYR', 'sex' => 'm', 'cat' => 'SNM' ],
            [ 'name' => 'GABRIEL MORAL LAZARO', 'club' => 'TEAM QS SULAYR', 'sex' => 'm', 'cat' => 'SNM' ],
        ] );
        $this->assertSame( NameMatcher::DOUBT, $out[0]['status'] );
        $this->assertSame( NameMatcher::DOUBT, $out[1]['status'] );
    }
}
