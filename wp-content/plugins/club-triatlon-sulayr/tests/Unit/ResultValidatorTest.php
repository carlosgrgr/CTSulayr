<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ResultValidator;
use PHPUnit\Framework\TestCase;

/**
 * T21 a T23, T26, T29 y T30 de SPEC-RANKING-INTERNO §10.1, y las referencias obligatorias.
 */
class ResultValidatorTest extends TestCase {

    private const MEMBERS = [
        10 => [ 'sex' => 'm', 'group' => 'veterano-1' ],
        11 => [ 'sex' => 'f', 'group' => 'senior' ],
        12 => [ 'sex' => 'm', 'group' => 'senior' ],
    ];

    private function validate( array $results, array $references = [], string $format = 'individual' ): array {
        return ResultValidator::validate( [
            'competition' => [ 'name' => 'Duatlón cros Guadix', 'race_date' => '2026-11-08', 'modality' => 'duatlon_cros', 'distance' => 'sprint', 'format' => $format ],
            'references' => $references,
            'results' => $results,
        ], self::MEMBERS, new AgeGroups( AgeGroups::DEFAULTS ), '2026-11-16' );
    }

    private function abs( string $sex, string $time, string $participants = '41' ): array {
        return [ 'scope' => 'absoluta', 'sex' => $sex, 'winner_time' => $time, 'participants' => $participants ];
    }

    public function test_fila_correcta(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:35:53', 'race_category' => 'V1M', 'position_sex' => '6', 'position_age_group' => '1' ] ], [ $this->abs( 'm', '1:30:43' ), [ 'scope' => 'grupo', 'sex' => 'm', 'age_group' => 'veterano-1', 'winner_time' => '1:35:53' ] ] );
        $this->assertFalse( ResultValidator::has_errors( $v['errors'] ) );
        $this->assertSame( [], $v['missing'] );
        $this->assertSame( 5753, $v['results'][0]['time_s'] );
    }

    public function test_t21_mas_rapido_que_el_ganador(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '0:59:59', 'position_sex' => '2' ] ], [ $this->abs( 'm', '1:00:00' ) ] );
        $this->assertArrayHasKey( 'time', $v['errors']['results'][0] );
    }

    public function test_t22_empate_con_el_ganador(): void {
        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '1:00:00', 'position_sex' => '2' ] ], [ $this->abs( 'm', '1:00:00' ) ] );
        $this->assertArrayHasKey( 'position_sex', $v['errors']['results'][0] );

        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '1:00:00', 'position_sex' => '1' ] ], [ $this->abs( 'm', '1:00:00' ) ] );
        $this->assertSame( [], $v['errors']['results'] );
    }

    public function test_t23_posicion_imposible(): void {
        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '1:10:00', 'position_sex' => '52' ] ], [ $this->abs( 'm', '1:00:00', '51' ) ] );
        $this->assertArrayHasKey( 'position_sex', $v['errors']['results'][0] );
    }

    public function test_t26_absoluta_puede_mejorar_al_primero_de_su_categoria(): void {
        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '1:30:43', 'race_category' => 'ABM', 'position_sex' => '1', 'position_age_group' => '1' ] ], [ $this->abs( 'm', '1:30:43' ), [ 'scope' => 'grupo', 'sex' => 'm', 'age_group' => 'senior', 'winner_time' => '1:34:35' ] ] );
        $this->assertSame( [], $v['errors']['results'] );
        $this->assertArrayNotHasKey( 'grupo||m|senior', $v['required'] );
    }

    public function test_t29_aviso_de_categoria(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:40:00', 'race_category' => 'V2M', 'position_sex' => '10' ] ], [ $this->abs( 'm', '1:30:43' ) ] );
        $this->assertSame( [], $v['errors']['results'] );
        $this->assertNotEmpty( $v['warnings'][0] );
    }

    public function test_t30_codigo_de_categoria_invalido(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:40:00', 'race_category' => 'V1F' ] ] );
        $this->assertArrayHasKey( 'race_category', $v['errors']['results'][0] );
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:40:00', 'race_category' => 'XX' ] ] );
        $this->assertArrayHasKey( 'race_category', $v['errors']['results'][0] );
    }

    public function test_socio_repetido_o_no_valido(): void {
        $v = $this->validate( [
            [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:40:00' ],
            [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:41:00' ],
            [ 'user_id' => 99, 'status' => 'finalizado', 'time' => '1:41:00' ],
        ] );
        $this->assertArrayHasKey( 'user_id', $v['errors']['results'][1] );
        $this->assertArrayHasKey( 'user_id', $v['errors']['results'][2] );
    }

    public function test_finalizado_sin_tiempo_ni_posicion(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado' ] ] );
        $this->assertArrayHasKey( 'time', $v['errors']['results'][0] );
    }

    public function test_dnf_ignora_tiempo_y_posiciones(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'dnf', 'time' => '0:10:00', 'position_sex' => '3' ] ], [ $this->abs( 'm', '1:00:00' ) ] );
        $this->assertSame( [], $v['errors']['results'] );
        $this->assertNull( $v['results'][0]['time_s'] );
        $this->assertSame( [], $v['required'] );
    }

    public function test_referencias_obligatorias(): void {
        $v = $this->validate( [
            [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:40:00' ],
            [ 'user_id' => 11, 'status' => 'finalizado', 'position_sex' => '3', 'position_age_group' => '2' ],
        ] );
        $this->assertSame( [ 'absoluta||m|', 'grupo||m|veterano-1', 'absoluta||f|', 'grupo||f|senior' ], array_keys( $v['required'] ) );
        $this->assertFalse( $v['required']['grupo||m|veterano-1']['participants_required'] );
        $this->assertTrue( $v['required']['grupo||f|senior']['participants_required'] );
        $this->assertCount( 4, $v['missing'] );
    }

    public function test_referencia_de_grupo_mejor_que_la_absoluta(): void {
        $v = $this->validate( [], [ $this->abs( 'm', '1:30:43' ), [ 'scope' => 'grupo', 'sex' => 'm', 'age_group' => 'senior', 'winner_time' => '1:20:00' ] ] );
        $this->assertArrayHasKey( 'grupo||m|senior', $v['errors']['references'] );
    }

    public function test_equipos_y_relevos(): void {
        $v = $this->validate( [ [ 'user_id' => 10, 'status' => 'finalizado', 'time' => '1:11:42', 'team_category' => 'm', 'position_sex' => '4' ] ], [ [ 'scope' => 'absoluta', 'team_category' => 'm', 'winner_time' => '1:00:44', 'participants' => '13' ] ], 'equipos' );
        $this->assertSame( [], $v['errors']['results'] );
        $this->assertSame( [ 'absoluta|m||' ], array_keys( $v['required'] ) );
        $this->assertSame( [], $v['missing'] );

        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '0:21:33', 'race_category' => 'SNM', 'team_category' => 'm', 'position_sex' => '4', 'position_age_group' => '3' ] ], [], 'relevos' );
        $this->assertSame( [ 'absoluta|m|m|', 'grupo|m|m|senior' ], array_keys( $v['required'] ) );

        $v = $this->validate( [ [ 'user_id' => 12, 'status' => 'finalizado', 'time' => '0:21:33' ] ], [], 'relevos' );
        $this->assertArrayHasKey( 'team_category', $v['errors']['results'][0] );
    }

    public function test_competicion(): void {
        $v = ResultValidator::validate( [ 'competition' => [ 'name' => '', 'race_date' => '2026-12-01', 'modality' => 'x', 'distance' => 'sprint', 'format' => 'individual' ] ], [], new AgeGroups( [] ), '2026-11-16' );
        $this->assertSame( [ 'name', 'race_date', 'modality' ], array_keys( $v['errors']['competition'] ) );
    }

    /** T38 en el formulario: solo valen 0 y los múltiplos del salto hasta el máximo (§15.6). */
    public function test_t38_bonus_por_interes(): void {
        $values = [ 0, 50, 100, 150, 200, 250, 300 ];
        $c = [ 'name' => 'Triatlón de Motril', 'race_date' => '2026-09-01', 'modality' => 'triatlon', 'distance' => 'sprint', 'format' => 'individual' ];
        $check = function ( $bonus, string $label = '' ) use ( $c, $values ) {
            return ResultValidator::validate( [ 'competition' => $c + [ 'interest_bonus' => $bonus, 'interest_label' => $label ] ], [], new AgeGroups( [] ), '2026-11-16', $values );
        };

        foreach ( [ '', '0', '50', '300' ] as $ok ) {
            $this->assertArrayNotHasKey( 'interest_bonus', $check( $ok )['errors']['competition'], $ok );
        }
        foreach ( [ '75', '350', '-50', 'cien' ] as $bad ) {
            $this->assertSame( 'Elige un bonus de la lista.', $check( $bad )['errors']['competition']['interest_bonus'] ?? null, $bad );
        }

        $v = $check( '100', "  Circuito   Granadino\n de Triatlón <b>2026</b>  " );
        $this->assertSame( 100, $v['competition']['interest_bonus'] );
        $this->assertSame( 'Circuito Granadino de Triatlón 2026', $v['competition']['interest_label'] );
        // Sin bonus no hay motivo.
        $this->assertSame( '', $check( '0', 'Circuito' )['competition']['interest_label'] );
        // Sin valores de la temporada (llamadas antiguas): solo 0.
        $this->assertArrayHasKey( 'interest_bonus', ResultValidator::validate( [ 'competition' => $c + [ 'interest_bonus' => '50' ] ], [], new AgeGroups( [] ), '2026-11-16' )['errors']['competition'] );
    }
}
