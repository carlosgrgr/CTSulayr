<?php
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportMerger;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportParser;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use PHPUnit\Framework\TestCase;

/**
 * La skill ranking-importar (.claude/skills/ranking-importar/) va aparte del plugin para poder
 * usarla en la app de escritorio. Estos tests comprueban que no se separa de la web:
 * el mismo formato y las mismas reglas de validación y unificación (SPEC §14).
 */
class ImportSkillTest extends TestCase {

    private const SKILL = __DIR__ . '/../../../../../.claude/skills/ranking-importar';
    private const TODAY = '2026-11-16';

    protected function setUp(): void {
        if ( ! is_dir( self::SKILL ) ) {
            $this->markTestSkipped( 'Sin la carpeta de la skill (fuera del repositorio).' );
        }
    }

    public function test_formato_identico_al_prompt_de_la_web(): void {
        $this->assertFileEquals( __DIR__ . '/../../admin/ranking-import-prompt.md', self::SKILL . '/formato.md' );
    }

    /**
     * @dataProvider casos
     */
    public function test_validar_py_coincide_con_la_web( array $data, bool $valid ): void {
        if ( '' === trim( (string) shell_exec( 'command -v python3' ) ) ) {
            $this->markTestSkipped( 'Sin python3.' );
        }
        $file = tempnam( sys_get_temp_dir(), 'imp' );
        file_put_contents( $file, json_encode( $data ) );
        exec( 'python3 ' . escapeshellarg( self::SKILL . '/scripts/validar.py' ) . ' ' . escapeshellarg( $file ) . ' ' . self::TODAY . ' 2>&1', $out, $code );
        unlink( $file );
        $out = implode( "\n", $out );

        $parsed = ImportParser::parse( (string) json_encode( $data ), self::TODAY );
        $this->assertSame( $valid, empty( $parsed['errors'] ), 'PHP' );
        $this->assertSame( $valid ? 0 : 1, $code, $out );

        if ( ! $valid ) {
            foreach ( $parsed['errors'] as $error ) {
                $this->assertStringContainsString( $error, $out );
            }
            return;
        }
        foreach ( ImportMerger::merge( $parsed )['groups'] as $g ) {
            $this->assertStringContainsString( sprintf( '%s: ', ImportMerger::group_label( $g['team_category'], $g['sex'] ) ), $out );
            $this->assertStringContainsString( sprintf( 'ganador %s · finalizados %d', RaceTime::format( $g['winner'] ), $g['participants'] ), $out );
        }
    }

    public function casos(): array {
        $base = [
            'schema' => ImportParser::SCHEMA,
            'competition' => [ 'name' => 'Relevos', 'race_date' => '2026-11-15', 'location' => 'Úbeda', 'modality' => 'duatlon', 'distance' => 'supersprint', 'format' => 'relevos' ],
            'classifications' => [
                [ 'title' => 'FEDERADO MASCULINA', 'team_category' => 'm', 'finishers' => 2, 'athletes' => [
                    [ 'pos' => 1, 'name' => 'A', 'club' => 'X', 'cat' => 'CDM', 'pos_cat' => 1, 'time' => '0:19:52' ],
                    [ 'pos' => 2, 'name' => 'B', 'club' => 'C.T. SULAYR', 'cat' => 'SNM', 'pos_cat' => 1, 'time' => '0:20:58' ],
                ] ],
                [ 'title' => 'OPEN MASCULINA', 'team_category' => 'm', 'finishers' => 3, 'athletes' => [
                    [ 'pos' => 1, 'name' => 'C', 'club' => 'Y', 'cat' => 'JVM', 'pos_cat' => 1, 'time' => '0:19:58' ],
                    [ 'pos' => 2, 'name' => 'D', 'club' => 'TAPADOS SULAYR', 'cat' => 'V1M', 'pos_cat' => 1, 'time' => '24:45' ],
                    [ 'name' => 'E', 'club' => 'Y', 'cat' => 'SNM', 'status' => 'dnf' ],
                ] ],
                [ 'title' => 'OPEN FEMENINA', 'team_category' => 'f', 'athletes' => [
                    [ 'pos' => 1, 'name' => 'F', 'club' => 'Z', 'sex' => 'f', 'time' => '0:25:13' ],
                ] ],
            ],
        ];

        $bad = $base;
        $bad['competition']['race_date'] = '2026-12-01';
        $bad['competition']['format'] = 'equipos';
        $bad['classifications'][0]['team_category'] = 'x';
        $bad['classifications'][0]['athletes'][0]['time'] = '0:61:00';
        $bad['classifications'][0]['athletes'][1]['cat'] = 'SENIOR';
        $bad['classifications'][1]['athletes'][0]['sex'] = 'f';
        $bad['classifications'][1]['athletes'][1]['pos'] = 0;
        $bad['classifications'][1]['athletes'][2]['status'] = 'retirado';
        $bad['classifications'][2]['athletes'][0]['sex'] = '';

        return [ 'válido' => [ $base, true ], 'con errores' => [ $bad, false ] ];
    }
}
