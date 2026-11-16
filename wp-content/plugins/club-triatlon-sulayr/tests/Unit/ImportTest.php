<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportMerger;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportParser;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ResultValidator;
use PHPUnit\Framework\TestCase;

/**
 * Importación desde JSON: lectura, unificación de clasificaciones y formulario
 * (SPEC-RANKING-INTERNO §14, R39 y R40).
 */
class ImportTest extends TestCase {

    private const TODAY = '2026-11-16';

    /**
     * Relevos con una clasificación federada y otra open, las dos masculinas.
     */
    private function relay_json( array $override = [] ): string {
        $data = [
            'schema' => ImportParser::SCHEMA,
            'competition' => [ 'name' => 'I Duatlón por relevos', 'race_date' => '2026-11-15', 'location' => 'Úbeda', 'modality' => 'duatlon', 'distance' => 'supersprint', 'format' => 'relevos' ],
            'classifications' => [
                [
                    'title' => 'FEDERADO MASCULINA',
                    'team_category' => 'm',
                    'athletes' => [
                        [ 'pos' => 1, 'name' => 'ÍÑIGO MOLINA ROZÚA', 'club' => 'C.D. TRICLUB', 'cat' => 'CDM', 'pos_cat' => 1, 'time' => '0:19:52' ],
                        [ 'pos' => 2, 'name' => 'MANUEL ANDRÉS PÉREZ DIOSDADO', 'club' => 'C.T. SULAYR', 'cat' => 'SNM', 'pos_cat' => 1, 'time' => '0:20:58' ],
                        [ 'pos' => 3, 'name' => 'ESTEBAN VIDAL PÉREZ', 'club' => 'C.T. SULAYR', 'cat' => 'V1M', 'pos_cat' => 1, 'time' => '0:22:43' ],
                    ],
                ],
                [
                    'title' => 'OPEN MASCULINA',
                    'team_category' => 'm',
                    'athletes' => [
                        [ 'pos' => 1, 'name' => 'ÁLVARO LÓPEZ BAUTISTA', 'club' => 'UBEDA - NAZARI', 'cat' => 'JVM', 'pos_cat' => 1, 'time' => '0:19:58' ],
                        [ 'pos' => 2, 'name' => 'DANIEL VALVERDE RUIZ', 'club' => 'SANCHO CASUAL BURGER-CT SULAYR', 'cat' => 'SNM', 'pos_cat' => 1, 'time' => '0:21:33' ],
                        [ 'pos' => 3, 'name' => 'EMILIO RODRÍGUEZ LOPEZ', 'club' => 'TEAM QS SULAYR', 'cat' => 'V1M', 'pos_cat' => 1, 'time' => '0:24:45' ],
                        [ 'name' => 'PEPE DNF', 'club' => 'TEAM QS SULAYR', 'cat' => 'SNM', 'status' => 'dnf' ],
                    ],
                ],
            ],
        ];

        return (string) json_encode( array_replace_recursive( $data, $override ) );
    }

    public function test_lee_un_json_valido(): void {
        $p = ImportParser::parse( $this->relay_json(), self::TODAY );
        $this->assertSame( [], $p['errors'] );
        $this->assertSame( 'relevos', $p['competition']['format'] );
        $this->assertCount( 7, $p['athletes'] );
        $this->assertSame( 'm', $p['athletes'][0]['sex'] );
        $this->assertSame( 1192, $p['athletes'][0]['time_s'] );
        $this->assertSame( 'dnf', $p['athletes'][6]['status'] );
        $this->assertNull( $p['athletes'][6]['time_s'] );
    }

    public function test_acepta_bloque_de_codigo(): void {
        $p = ImportParser::parse( "```json\n" . $this->relay_json() . "\n```", self::TODAY );
        $this->assertSame( [], $p['errors'] );
    }

    public function test_errores_de_formato(): void {
        $this->assertNotEmpty( ImportParser::parse( 'no es json', self::TODAY )['errors'] );
        $this->assertNotEmpty( ImportParser::parse( '{"schema":"otro"}', self::TODAY )['errors'] );

        $bad = json_decode( $this->relay_json(), true );
        $bad['competition']['format'] = 'equipos';
        $bad['classifications'][0]['team_category'] = 'x';
        $bad['classifications'][0]['athletes'][0]['time'] = '19,52';
        $bad['classifications'][0]['athletes'][1]['cat'] = 'SENIOR';
        $p = ImportParser::parse( (string) json_encode( $bad ), self::TODAY );
        $text = implode( "\n", $p['errors'] );
        $this->assertStringContainsString( 'contrarreloj por equipos', $text );
        $this->assertStringContainsString( '"cat"', $text );
    }

    public function test_fecha_futura(): void {
        $p = ImportParser::parse( $this->relay_json( [ 'competition' => [ 'race_date' => '2026-12-01' ] ] ), self::TODAY );
        $this->assertStringContainsString( 'posterior a hoy', implode( ' ', $p['errors'] ) );
    }

    public function test_sexo_obligatorio_si_no_hay_categoria(): void {
        $data = json_decode( $this->relay_json(), true );
        $data['classifications'][0]['athletes'][0]['cat'] = '';
        $p = ImportParser::parse( (string) json_encode( $data ), self::TODAY );
        $this->assertStringContainsString( 'no se sabe su sexo', implode( ' ', $p['errors'] ) );
    }

    /**
     * R39: federado y open se unifican; las posiciones se recalculan por tiempo.
     */
    public function test_unifica_federado_y_open(): void {
        $m = ImportMerger::merge( ImportParser::parse( $this->relay_json(), self::TODAY ) );
        $this->assertCount( 1, $m['groups'] );
        $g = $m['groups']['m|m'];
        $this->assertSame( 1192, $g['winner'] );              // 0:19:52, el más rápido de las dos.
        $this->assertSame( 6, $g['finished'] );
        $this->assertSame( 6, $g['participants'] );
        $this->assertSame( [ 0, 1 ], $g['sources'] );

        $by_name = array_column( $m['athletes'], null, 'name' );
        $this->assertSame( 1, $by_name['ÍÑIGO MOLINA ROZÚA']['pos'] );
        $this->assertSame( 2, $by_name['ÁLVARO LÓPEZ BAUTISTA']['pos'] );
        $this->assertSame( 3, $by_name['MANUEL ANDRÉS PÉREZ DIOSDADO']['pos'] );
        $this->assertSame( 1, $by_name['MANUEL ANDRÉS PÉREZ DIOSDADO']['pos_cat'] );
        $this->assertSame( 4, $by_name['DANIEL VALVERDE RUIZ']['pos'] );
        $this->assertSame( 2, $by_name['DANIEL VALVERDE RUIZ']['pos_cat'] );   // Segundo SNM de las dos.
        $this->assertSame( 2, $by_name['EMILIO RODRÍGUEZ LOPEZ']['pos_cat'] ); // Segundo V1M.
        $this->assertNull( $by_name['PEPE DNF']['pos'] );

        $this->assertSame( 1258, $g['cats']['SNM']['first'] );
        $this->assertSame( 2, $g['cats']['SNM']['finished'] );
    }

    public function test_empate_misma_posicion(): void {
        $data = json_decode( $this->relay_json(), true );
        $data['classifications'][1]['athletes'][0]['time'] = '0:19:52';
        $m = ImportMerger::merge( ImportParser::parse( (string) json_encode( $data ), self::TODAY ) );
        $by_name = array_column( $m['athletes'], null, 'name' );
        $this->assertSame( 1, $by_name['ÍÑIGO MOLINA ROZÚA']['pos'] );
        $this->assertSame( 1, $by_name['ÁLVARO LÓPEZ BAUTISTA']['pos'] );
        $this->assertSame( 3, $by_name['MANUEL ANDRÉS PÉREZ DIOSDADO']['pos'] );
    }

    public function test_finalizados_declarados_y_aviso(): void {
        $data = json_decode( $this->relay_json(), true );
        $data['classifications'][0]['finishers'] = 3;
        $data['classifications'][1]['finishers'] = 4;
        $m = ImportMerger::merge( ImportParser::parse( (string) json_encode( $data ), self::TODAY ) );
        $this->assertSame( 7, $m['groups']['m|m']['participants'] );
        $this->assertStringContainsString( 'dice 7 finalizados y el JSON trae 6', implode( ' ', $m['warnings'] ) );
    }

    /**
     * El formulario sale relleno y pasa el validador sin errores ni referencias pendientes.
     */
    public function test_formulario_relleno_y_valido(): void {
        $parsed = ImportParser::parse( $this->relay_json(), self::TODAY );
        $merged = ImportMerger::merge( $parsed );
        $groups = new AgeGroups( AgeGroups::DEFAULTS );
        $members = [
            10 => [ 'sex' => 'm', 'group' => 'senior' ],
            11 => [ 'sex' => 'm', 'group' => 'veterano-1' ],
            12 => [ 'sex' => 'm', 'group' => 'senior' ],
            13 => [ 'sex' => 'm', 'group' => 'veterano-1' ],
            14 => [ 'sex' => 'm', 'group' => 'senior' ],
        ];
        $assign = [ 1 => 10, 2 => 11, 4 => 12, 5 => 13, 6 => 14, 0 => 0 ];

        $built = ImportMerger::build_input( $parsed, $merged, $assign, $members, $groups, 'Importado' );
        $input = $built['input'];
        $this->assertSame( [], $built['warnings'] );
        $this->assertCount( 5, $input['results'] );
        $this->assertSame( '10', $input['results'][0]['user_id'] );      // Pos 3 unificada: el primero de los socios.
        $this->assertSame( '3', $input['results'][0]['position_sex'] );
        $this->assertSame( '0:20:58', $input['results'][0]['time'] );
        $this->assertSame( 'm', $input['results'][0]['team_category'] );
        $this->assertSame( 'dnf', $input['results'][4]['status'] );

        $abs = $input['references'][ Keys::ref_key( 'absoluta', 'm', 'm', '' ) ];
        $this->assertSame( '0:19:52', $abs['winner_time'] );
        $this->assertSame( '6', $abs['participants'] );
        $v1 = $input['references'][ Keys::ref_key( 'grupo', 'm', 'm', 'veterano-1' ) ];
        $this->assertSame( '0:22:43', $v1['winner_time'] );
        $this->assertSame( '2', $v1['participants'] );

        $v = ResultValidator::validate( $input, $members, $groups, self::TODAY );
        $this->assertFalse( ResultValidator::has_errors( $v['errors'] ), print_r( $v['errors'], true ) );
        $this->assertSame( [], $v['missing'] );
        $this->assertSame( 'Importado', $v['competition']['notes'] );
    }

    public function test_grupo_sin_codigo_avisa(): void {
        $parsed = ImportParser::parse( $this->relay_json(), self::TODAY );
        $merged = ImportMerger::merge( $parsed );
        $rows = AgeGroups::DEFAULTS;
        foreach ( $rows as &$row ) {
            if ( 'senior' === $row['key'] ) {
                $row['code'] = '';
            }
        }
        unset( $row );
        $built = ImportMerger::build_input( $parsed, $merged, [ 1 => 10 ], [ 10 => [ 'sex' => 'm', 'group' => 'senior' ] ], new AgeGroups( $rows ), '' );
        $this->assertStringContainsString( 'no tiene código', implode( ' ', $built['warnings'] ) );
        $this->assertSame( '', $built['input']['references'][ Keys::ref_key( 'grupo', 'm', 'm', 'senior' ) ]['winner_time'] );
    }

    public function test_individual_sin_categoria_de_equipo(): void {
        $data = [
            'schema' => ImportParser::SCHEMA,
            'competition' => [ 'name' => 'Duatlón cros', 'race_date' => '2026-11-08', 'location' => 'Guadix', 'modality' => 'duatlon_cros', 'distance' => 'sprint', 'format' => 'individual' ],
            'classifications' => [
                [ 'title' => 'GENERAL MASCULINA', 'finishers' => 2, 'athletes' => [
                    [ 'pos' => 1, 'name' => 'A B C', 'club' => 'X', 'cat' => 'SNM', 'pos_cat' => 1, 'time' => '1:00:00' ],
                    [ 'pos' => 2, 'name' => 'D E F', 'club' => 'CT SULAYR', 'cat' => 'V1M', 'pos_cat' => 1, 'time' => '1:05:00' ],
                ] ],
            ],
        ];
        $parsed = ImportParser::parse( (string) json_encode( $data ), self::TODAY );
        $this->assertSame( [], $parsed['errors'] );
        $this->assertSame( '', $parsed['athletes'][0]['team_category'] );
        $merged = ImportMerger::merge( $parsed );
        $this->assertSame( 2, $merged['groups']['|m']['participants'] );
        $this->assertSame( [], $merged['warnings'] );
    }
}
