<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\Baremo;
use ClubTriatlonSulayr\Modules\Ranking\Calc\PointsCalculator;
use PHPUnit\Framework\TestCase;

/**
 * Casos con números de SPEC-RANKING-INTERNO §10.1 (T1 a T12, T25 a T28 y T32).
 */
class PointsCalculatorTest extends TestCase {

    private function calc( array $in ): array {
        return PointsCalculator::calculate( array_merge( [
            'status' => 'finalizado',
            'base' => 1000,
            'bonus' => 50,
            'coef' => 1.0,
            'has_group' => false,
        ], $in ) );
    }

    private function ref( ?int $winner, ?int $participants = null ): array {
        return [ 'winner_time' => $winner, 'participants' => $participants ];
    }

    public function test_t1_sprint_por_tiempo(): void {
        $r = $this->calc( [ 'time_s' => 4320, 'coef' => 1.1, 'ref_abs' => $this->ref( 3600, 40 ) ] );
        $this->assertSame( 966.67, $r['points_absolute'] );
        $this->assertSame( 'tiempo', $r['method_absolute'] );
    }

    public function test_t2_olimpico_por_tiempo(): void {
        $r = $this->calc( [ 'time_s' => 9000, 'coef' => 1.2, 'ref_abs' => $this->ref( 7500, 20 ) ] );
        $this->assertSame( 1050.0, $r['points_absolute'] );
    }

    public function test_t3_ganador_de_la_prueba(): void {
        $r = $this->calc( [ 'time_s' => 3600, 'position_sex' => 1, 'coef' => 1.1, 'ref_abs' => $this->ref( 3600, 40 ) ] );
        $this->assertSame( 1150.0, $r['points_absolute'] );
    }

    public function test_t4_media_distancia(): void {
        $r = $this->calc( [ 'time_s' => 19200, 'coef' => 1.4, 'ref_abs' => $this->ref( 14400, 30 ) ] );
        $this->assertSame( 1100.0, $r['points_absolute'] );
    }

    public function test_t5_larga_distancia(): void {
        $r = $this->calc( [ 'time_s' => 45900, 'coef' => 1.6, 'ref_abs' => $this->ref( 30600, 30 ) ] );
        $this->assertSame( 1116.67, $r['points_absolute'] );
    }

    public function test_t6_respaldo_por_posicion(): void {
        $r = $this->calc( [ 'time_s' => null, 'position_sex' => 11, 'ref_abs' => $this->ref( 3600, 51 ) ] );
        $this->assertSame( 853.92, $r['points_absolute'] );
        $this->assertSame( 'posicion', $r['method_absolute'] );
    }

    public function test_t7_unico_participante(): void {
        $r = $this->calc( [ 'position_sex' => 1, 'ref_abs' => $this->ref( null, 1 ) ] );
        $this->assertSame( 1050.0, $r['points_absolute'] );
    }

    public function test_t8_ultimo_por_posicion(): void {
        $r = $this->calc( [ 'position_sex' => 51, 'ref_abs' => $this->ref( null, 51 ) ] );
        $this->assertSame( 69.61, $r['points_absolute'] );
    }

    public function test_t9_grupo_de_edad(): void {
        $r = $this->calc( [
            'time_s' => 4320,
            'coef' => 1.1,
            'has_group' => true,
            'ref_abs' => $this->ref( 3600, 40 ),
            'ref_grp' => $this->ref( 3900 ),
        ] );
        $this->assertSame( 966.67, $r['points_absolute'] );
        $this->assertSame( 1043.06, $r['points_age_group'] );
    }

    public function test_t10_dnf_dsq_dns_nc(): void {
        foreach ( [ 'dnf', 'dsq', 'dns', 'nc' ] as $status ) {
            $r = $this->calc( [ 'status' => $status, 'time_s' => 3600, 'has_group' => true, 'ref_abs' => $this->ref( 3600, 10 ), 'ref_grp' => $this->ref( 3600 ) ] );
            $this->assertSame( 0.0, $r['points_absolute'], $status );
            $this->assertSame( 0.0, $r['points_age_group'], $status );
            $this->assertSame( 'estado', $r['method_absolute'], $status );
        }
    }

    public function test_t11_sin_referencia_de_grupo(): void {
        $r = $this->calc( [ 'time_s' => 4320, 'has_group' => true, 'ref_abs' => $this->ref( 3600, 40 ), 'ref_grp' => null ] );
        $this->assertSame( 883.33, $r['points_absolute'] );
        $this->assertNull( $r['points_age_group'] );
        $this->assertNotEmpty( $r['notes'] );
    }

    public function test_t12_sin_referencias_ni_posicion(): void {
        $r = $this->calc( [ 'time_s' => 4320 ] );
        $this->assertSame( 0.0, $r['points_absolute'] );
        $this->assertSame( 'ninguno', $r['method_absolute'] );
        $this->assertNotEmpty( $r['notes'] );
    }

    public function test_t25_coeficiente_que_no_esta_en_la_tabla(): void {
        $this->assertSame( 1.0, Baremo::coefficient( Baremo::defaults(), 'triatlon', 'supersprint' ) );
        $this->assertSame( 1.1, Baremo::coefficient( Baremo::defaults(), 'triatlon', 'sprint' ) );
        $this->assertSame( 1.1, Baremo::coefficient( Baremo::defaults(), 'triatlon_cros', 'sprint' ) );
        $this->assertSame( 1.0, Baremo::coefficient( Baremo::defaults(), 'acuatlon', 'larga' ) );
        $this->assertSame( 1.0, Baremo::coefficient( [], 'triatlon', 'sprint' ) );
    }

    public function test_t26_categoria_absoluta(): void {
        $r = $this->calc( [
            'time_s' => 5443,
            'position_sex' => 1,
            'has_group' => true,
            'absolute_category' => true,
            'ref_abs' => $this->ref( 5443, 41 ),
            'ref_grp' => $this->ref( 5675 ),
        ] );
        $this->assertSame( 1050.0, $r['points_absolute'] );
        $this->assertSame( 1050.0, $r['points_age_group'] );
        $this->assertSame( 'absoluta', $r['method_age_group'] );
    }

    public function test_t27_relevos(): void {
        $r = $this->calc( [ 'time_s' => 1293, 'ref_abs' => $this->ref( 1198, 39 ) ] );
        $this->assertSame( 976.53, $r['points_absolute'] );
    }

    public function test_t28_equipos(): void {
        $r = $this->calc( [ 'time_s' => 4000, 'coef' => 1.1, 'has_group' => true, 'group_from_absolute' => true, 'ref_abs' => $this->ref( 3600, 12 ) ] );
        $this->assertSame( 1040.0, $r['points_absolute'] );
        $this->assertSame( 1040.0, $r['points_age_group'] );
    }

    public function test_t32_el_coeficiente_de_cada_temporada(): void {
        $b2027 = Baremo::defaults();
        $b2028 = Baremo::defaults();
        $b2028['coefficients']['triatlon']['sprint'] = 1.2;
        $in = [ 'time_s' => 4320, 'ref_abs' => $this->ref( 3600, 40 ) ];
        $this->assertSame( 966.67, $this->calc( $in + [ 'coef' => Baremo::coefficient( $b2027, 'triatlon', 'sprint' ) ] )['points_absolute'] );
        $this->assertSame( 1050.0, $this->calc( $in + [ 'coef' => Baremo::coefficient( $b2028, 'triatlon', 'sprint' ) ] )['points_absolute'] );
    }
}
