<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Calc;

/**
 * Unifica las clasificaciones de un JSON de importación y prepara el formulario
 * de la competición (SPEC-RANKING-INTERNO §14.3, R39).
 *
 * Para el ranking interno da igual la clasificación del PDF en la que salga un
 * deportista (federado u open, por ejemplo): se juntan todas las de la misma
 * categoría de equipo y el mismo sexo. Dentro de cada grupo:
 *
 * - «Pos» y «Pos Cat» se recalculan por tiempo (empate = misma posición);
 * - el ganador es el tiempo más rápido y el primero de una categoría, el más rápido de esa categoría;
 * - los finalizados son la suma de los «finishers» de cada clasificación si todas los traen,
 *   o los deportistas con estado finalizado que hay en el JSON.
 *
 * Sin WordPress, para poder probarlo.
 */
class ImportMerger {

    /**
     * Posiciones unificadas y estadísticas por grupo.
     *
     * @param   array $parsed Resultado de ImportParser::parse() sin errores.
     * @return 	array{athletes: array, groups: array, warnings: string[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function merge( array $parsed ): array {
        $athletes = $parsed['athletes'];
        $classifications = $parsed['classifications'];
        $warnings = [];

        // Sexos presentes en cada clasificación: los finalizados declarados solo valen si es de un solo sexo.
        $sexes_by_classification = [];
        foreach ( $athletes as $a ) {
            $sexes_by_classification[ $a['classification'] ][ $a['sex'] ] = true;
        }

        $groups = [];
        foreach ( $athletes as $i => $a ) {
            $key = self::group_key( $a['team_category'], $a['sex'] );
            if ( ! isset( $groups[ $key ] ) ) {
                $groups[ $key ] = [ 'team_category' => $a['team_category'], 'sex' => $a['sex'], 'sources' => [], 'members' => [] ];
            }
            $groups[ $key ]['sources'][ $a['classification'] ] = true;
            $groups[ $key ]['members'][] = $i;
        }

        $stats = [];
        foreach ( $groups as $key => $g ) {
            $finished = [];
            $timed = [];
            foreach ( $g['members'] as $i ) {
                if ( Keys::STATUS_FINISHED !== $athletes[ $i ]['status'] ) {
                    continue;
                }
                $finished[] = $i;
                if ( null !== $athletes[ $i ]['time_s'] ) {
                    $timed[] = $i;
                }
            }
            $merged = count( $g['sources'] ) > 1;

            // Posiciones por tiempo: 1 + los que fueron más rápidos.
            foreach ( $finished as $i ) {
                $time = $athletes[ $i ]['time_s'];
                if ( null === $time ) {
                    if ( $merged ) {
                        $warnings[] = sprintf( '«%s» terminó sin tiempo y su clasificación se ha unido a otra: su «Pos» es la del PDF, revísala.', $athletes[ $i ]['name'] );
                    }
                    continue;
                }
                $pos = 1;
                $pos_cat = 1;
                foreach ( $timed as $j ) {
                    if ( $athletes[ $j ]['time_s'] < $time ) {
                        $pos++;
                        if ( '' !== $athletes[ $i ]['cat'] && $athletes[ $j ]['cat'] === $athletes[ $i ]['cat'] ) {
                            $pos_cat++;
                        }
                    }
                }
                $athletes[ $i ]['pos'] = $pos;
                $athletes[ $i ]['pos_cat'] = '' !== $athletes[ $i ]['cat'] ? $pos_cat : null;
            }

            // Finalizados: los declarados en el PDF si todas las clasificaciones del grupo son de un solo sexo y los traen.
            $declared = 0;
            foreach ( array_keys( $g['sources'] ) as $c ) {
                $value = $classifications[ $c ]['finishers'] ?? null;
                if ( null === $value || count( $sexes_by_classification[ $c ] ?? [] ) > 1 ) {
                    $declared = null;
                    break;
                }
                $declared += (int) $value;
            }
            if ( null !== $declared && $declared !== count( $finished ) ) {
                $warnings[] = sprintf( '%s: el PDF dice %d finalizados y el JSON trae %d. Puede faltar algún deportista en el JSON; se usa la cifra del PDF.', self::group_label( $g['team_category'], $g['sex'] ), $declared, count( $finished ) );
            }

            $winner = null;
            $cats = [];
            foreach ( $finished as $i ) {
                $time = $athletes[ $i ]['time_s'];
                $cat = $athletes[ $i ]['cat'];
                if ( null !== $time && ( null === $winner || $time < $winner ) ) {
                    $winner = $time;
                }
                if ( '' === $cat ) {
                    continue;
                }
                if ( ! isset( $cats[ $cat ] ) ) {
                    $cats[ $cat ] = [ 'first' => null, 'finished' => 0 ];
                }
                $cats[ $cat ]['finished']++;
                if ( null !== $time && ( null === $cats[ $cat ]['first'] || $time < $cats[ $cat ]['first'] ) ) {
                    $cats[ $cat ]['first'] = $time;
                }
            }

            $stats[ $key ] = [
                'team_category' => $g['team_category'],
                'sex' => $g['sex'],
                'sources' => array_keys( $g['sources'] ),
                'winner' => $winner,
                'finished' => count( $finished ),
                'participants' => null !== $declared ? $declared : count( $finished ),
                'athletes' => count( $g['members'] ),
                'cats' => $cats,
            ];
        }
        ksort( $stats );

        return [ 'athletes' => $athletes, 'groups' => $stats, 'warnings' => $warnings ];
    }

    /**
     * Datos del formulario de la competición (mismo formato que envía la pantalla).
     *
     * @param   array     $parsed      De ImportParser::parse().
     * @param   array     $merged      De merge().
     * @param   array     $assignments Índice del deportista => user_id elegido.
     * @param   array     $members     user_id => [ sex, group ] de los socios que se pueden elegir.
     * @param   AgeGroups $groups      Grupos de la temporada.
     * @param   string    $notes       Nota interna.
     * @param   array     $interest    [ interest_bonus, interest_label ] elegidos en la revisión (§15.4).
     * @return 	array{input: array, warnings: string[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function build_input( array $parsed, array $merged, array $assignments, array $members, AgeGroups $groups, string $notes, array $interest = [] ): array {
        $format = $parsed['competition']['format'];
        $team = Keys::has_team( $format );
        $warnings = [];

        $chosen = [];
        foreach ( $assignments as $index => $user_id ) {
            $user_id = (int) $user_id;
            if ( $user_id > 0 && isset( $merged['athletes'][ $index ], $members[ $user_id ] ) ) {
                $chosen[ (int) $index ] = $user_id;
            }
        }
        // Orden: categoría de equipo, terminados por posición y después los que no terminaron.
        uksort( $chosen, function ( $a, $b ) use ( $merged ) {
            $ra = $merged['athletes'][ $a ];
            $rb = $merged['athletes'][ $b ];
            return [ $ra['team_category'], null === $ra['pos'] ? PHP_INT_MAX : $ra['pos'], $ra['name'] ] <=> [ $rb['team_category'], null === $rb['pos'] ? PHP_INT_MAX : $rb['pos'], $rb['name'] ];
        } );

        $results = [];
        $clean = [];
        foreach ( $chosen as $index => $user_id ) {
            $a = $merged['athletes'][ $index ];
            $results[] = [
                'user_id' => (string) $user_id,
                'status' => $a['status'],
                'time' => null !== $a['time_s'] ? RaceTime::format( $a['time_s'] ) : '',
                'race_category' => $a['cat'],
                'position_sex' => null !== $a['pos'] ? (string) $a['pos'] : '',
                'position_age_group' => null !== $a['pos_cat'] ? (string) $a['pos_cat'] : '',
                'team_category' => $team ? $a['team_category'] : '',
            ];
            $clean[] = [
                'status' => $a['status'],
                'sex' => (string) $members[ $user_id ]['sex'],
                'age_group' => (string) $members[ $user_id ]['group'],
                'time_s' => $a['time_s'],
                'race_category' => $a['cat'],
                'team_category' => $team ? $a['team_category'] : '',
            ];
        }

        $references = [];
        foreach ( ResultValidator::required_references( $clean, $format ) as $key => $need ) {
            $ref = [
                'scope' => $need['scope'],
                'team_category' => $need['team_category'],
                'sex' => $need['sex'],
                'age_group' => $need['age_group'],
                'winner_time' => '',
                'participants' => '',
            ];
            $stats = $merged['groups'][ self::group_key( $need['team_category'], $need['sex'] ) ] ?? null;

            if ( null !== $stats && Keys::SCOPE_ABSOLUTE === $need['scope'] ) {
                $ref['winner_time'] = null !== $stats['winner'] ? RaceTime::format( $stats['winner'] ) : '';
                $ref['participants'] = (string) $stats['participants'];
            } elseif ( null !== $stats ) {
                $code = $groups->code( $need['age_group'] );
                $cat = '' !== $code ? $code . strtoupper( $need['sex'] ) : '';
                if ( '' === $code ) {
                    $warnings[] = sprintf( 'El grupo «%s» no tiene código de la federación en Ajustes: rellena a mano su referencia.', $groups->label( $need['age_group'] ) );
                } elseif ( ! isset( $stats['cats'][ $cat ] ) || null === $stats['cats'][ $cat ]['first'] ) {
                    $warnings[] = sprintf( 'No hay nadie de la categoría %1$s con tiempo en el JSON: rellena a mano la referencia de «%2$s».', $cat, $groups->label( $need['age_group'] ) );
                } else {
                    $ref['winner_time'] = RaceTime::format( $stats['cats'][ $cat ]['first'] );
                    $ref['participants'] = (string) $stats['cats'][ $cat ]['finished'];
                }
            }
            $references[ $key ] = $ref;
        }

        $competition = $parsed['competition'];
        $competition['notes'] = $notes;
        $competition['interest_bonus'] = (string) (int) ( $interest['interest_bonus'] ?? 0 );
        $competition['interest_label'] = 0 === (int) $competition['interest_bonus'] ? '' : (string) ( $interest['interest_label'] ?? '' );

        return [
            'input' => [ 'competition' => $competition, 'references' => $references, 'results' => $results ],
            'warnings' => array_values( array_unique( $warnings ) ),
        ];
    }

    /**
     * Clave de grupo: categoría de equipo y sexo.
     *
     * @param   string $team_category '' en individual.
     * @param   string $sex           m|f.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function group_key( string $team_category, string $sex ): string {
        return $team_category . '|' . $sex;
    }

    /**
     * Nombre de un grupo para los avisos.
     *
     * @param   string $team_category '' en individual.
     * @param   string $sex           m|f.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function group_label( string $team_category, string $sex ): string {
        $sex_label = Keys::SEX_FEMALE === $sex ? 'mujeres' : 'hombres';
        $teams = [ 'm' => 'Relevos masculinos', 'f' => 'Relevos femeninos', 'mixto' => 'Relevos mixtos' ];

        return '' === $team_category ? 'General de ' . $sex_label : ( $teams[ $team_category ] ?? $team_category ) . ', ' . $sex_label;
    }
}
