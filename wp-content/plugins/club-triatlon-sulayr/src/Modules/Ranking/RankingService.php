<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Baremo;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\PointsCalculator;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Standings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Recálculo de puntos y clasificaciones (SPEC-RANKING-INTERNO §3.7 y §6.3).
 *
 * Los puntos se guardan en cada resultado; las clasificaciones se construyen
 * al leer y se guardan en un transient por temporada. La caché se invalida
 * subiendo una versión (sulayr_ranking_cache_version).
 */
class RankingService {

    private const CACHE_VERSION_OPTION = 'sulayr_ranking_cache_version';
    private const CACHE_PREFIX = 'sulayr_ranking_standings_';

    /** Metas del socio que cambian sus puntos (sexo y grupo de edad). */
    private const RECALC_META = [ CustomFields::MEMBER_SEX, CustomFields::USER_PROFILE_BIRTH ];

    /** Metas que solo cambian lo que se ve (nombre, foto, estado). */
    private const DISPLAY_META = [ 'first_name', 'last_name', CustomFields::USER_PROFILE_PHOTO, CustomFields::USER_PROFILE_ATHLETE_STATUS ];

    /** Socios pendientes de recalcular al final de la petición. */
    private static array $pending_users = [];
    private static bool $pending_bump = false;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'added_user_meta', [ $this, 'on_meta' ], 10, 3 );
        add_action( 'updated_user_meta', [ $this, 'on_meta' ], 10, 3 );
        add_action( 'deleted_user_meta', [ $this, 'on_meta' ], 10, 3 );
        add_action( 'sulayr_member_history_changed', [ $this, 'on_history_changed' ] );
        add_action( 'shutdown', [ $this, 'flush_pending' ] );
    }

    /**
     * Anota los cambios de metas que afectan al ranking.
     *
     * @param   mixed  $meta_id  Meta.
     * @param   int    $user_id  Usuario.
     * @param   string $meta_key Clave.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_meta( $meta_id, $user_id, $meta_key ): void {
        if ( in_array( $meta_key, self::RECALC_META, true ) ) {
            self::$pending_users[ (int) $user_id ] = true;
        } elseif ( in_array( $meta_key, self::DISPLAY_META, true ) ) {
            self::$pending_bump = true;
        }
    }

    /**
     * Alta, baja o congelación: cambia quién sale en el ranking.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_history_changed(): void {
        self::$pending_bump = true;
    }

    /**
     * Recalcula los socios anotados e invalida la caché, una vez por petición.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function flush_pending(): void {
        if ( empty( self::$pending_users ) && false === self::$pending_bump ) {
            return;
        }

        $users = array_keys( self::$pending_users );
        self::$pending_users = [];
        self::$pending_bump = false;

        RankingRoster::flush();
        foreach ( $users as $user_id ) {
            self::recalculate_user( (int) $user_id );
        }
        self::bump_cache();
    }

    /**
     * Recalcula los resultados de una competición con el baremo de su temporada.
     *
     * @param   int    $competition_id Competición.
     * @param   bool   $log            Registrar los puntos que cambien.
     * @return 	int    Resultados que cambiaron, o -1 si falla una escritura (el llamador debe deshacer la transacción).
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recalculate_competition( int $competition_id, bool $log = false ): int {
        $competition = Competitions::get( $competition_id );
        if ( null === $competition ) {
            return 0;
        }

        $season = (int) $competition->season;
        $baremo = RankingSettings::for_season( $season, true );
        $groups = new AgeGroups( $baremo['age_groups'] );
        $coef = Baremo::coefficient( $baremo, (string) $competition->modality, (string) $competition->distance );
        $format = (string) $competition->format;
        $references = Competitions::references( $competition_id );
        $changed = 0;

        foreach ( Results::for_competition( $competition_id ) as $result ) {
            $data = self::calculate( $result, $format, $season, $coef, $baremo, $groups, $references );
            $diff = [];
            foreach ( $data as $field => $value ) {
                $current = $result->$field;
                $same = ( null === $current || null === $value ) ? ( null === $current && null === $value ) : ( is_float( $value ) ? abs( (float) $current - $value ) < 0.005 : (string) $current === (string) $value );
                if ( false === $same ) {
                    $diff[ $field ] = $value;
                }
            }
            if ( empty( $diff ) ) {
                continue;
            }

            if ( false === Results::update_calc( (int) $result->id, $diff ) ) {
                return -1;
            }
            $changed++;

            if ( $log && ( array_key_exists( 'points_absolute', $diff ) || array_key_exists( 'points_age_group', $diff ) ) ) {
                if ( false === RankingLog::record(
                    RankingLog::ENTITY_RESULT,
                    (int) $result->id,
                    $competition_id,
                    (int) $result->user_id,
                    'puntos',
                    self::points_text( $result->points_absolute, $result->points_age_group ),
                    self::points_text( $data['points_absolute'], $data['points_age_group'] )
                ) ) {
                    return -1;
                }
            }
        }

        self::bump_cache();

        return $changed;
    }

    /**
     * Recalcula todas las competiciones de una temporada y lo deja en el registro.
     *
     * @param   int    $season Año.
     * @param   string $reason Motivo para el registro.
     * @return 	int    Resultados que cambiaron.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recalculate_season( int $season, string $reason ): int {
        return (int) RankingLog::with_note( $reason, function () use ( $season ) {
            $changed = 0;
            foreach ( Competitions::for_season( $season ) as $competition ) {
                $changed += max( 0, self::recalculate_competition( (int) $competition->id, true ) );
            }
            RankingLog::record( RankingLog::ENTITY_RECALC, 0, 0, 0, (string) $season, '', sprintf( __( '%d resultados cambiaron', SULAYR_TEXTDOMAIN ), $changed ) );

            return $changed;
        } );
    }

    /**
     * Recalcula todas las temporadas, cada una con su baremo (botón «Recalcular todo»).
     *
     * @param   string $reason Motivo para el registro.
     * @return 	int    Resultados que cambiaron.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recalculate_all( string $reason ): int {
        $changed = 0;
        foreach ( Competitions::seasons() as $season ) {
            $changed += self::recalculate_season( $season, $reason );
        }
        self::bump_cache();

        return $changed;
    }

    /**
     * Recalcula los resultados de un socio cuyo sexo o fecha de nacimiento cambió.
     *
     * @param   int $user_id Socio.
     * @return 	int Resultados que cambiaron.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recalculate_user( int $user_id ): int {
        $competitions = Results::competitions_of_user( $user_id );
        if ( empty( $competitions ) ) {
            return 0;
        }

        return (int) RankingLog::with_note( __( 'Cambio de datos del socio', SULAYR_TEXTDOMAIN ), function () use ( $competitions ) {
            $changed = 0;
            foreach ( $competitions as $competition_id ) {
                $changed += max( 0, self::recalculate_competition( $competition_id, true ) );
            }

            return $changed;
        } );
    }

    /**
     * Clasificaciones de una temporada (competiciones publicadas, socios que salen en el ranking).
     *
     * @param   int  $season         Año.
     * @param   bool $include_hidden Incluir a los socios de baja, marcados (solo wp-admin, sin caché).
     * @return 	array{season: int, best_n: int, absolute: array, groups: array, counted: array, positions: array, hidden: array}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function standings( int $season, bool $include_hidden = false ): array {
        $key = self::CACHE_PREFIX . $season . '_' . (int) get_option( self::CACHE_VERSION_OPTION, 1 );
        if ( false === $include_hidden ) {
            $cached = get_transient( $key );
            if ( is_array( $cached ) ) {
                return $cached;
            }
        }

        $baremo = RankingSettings::for_season( $season );
        $n = (int) $baremo['best_n'];
        $visible = RankingRoster::members();
        $hidden = [];
        $sort = RankingRoster::sort_names();

        $abs_rows = [];
        $grp_rows = [];
        foreach ( Results::for_season( $season, true ) as $result ) {
            $user_id = (int) $result->user_id;
            if ( ! isset( $visible[ $user_id ] ) ) {
                if ( false === $include_hidden ) {
                    continue;
                }
                $hidden[ $user_id ] = true;
                $sort[ $user_id ] = RankingRoster::info( $user_id )['sort'];
            }
            // El sexo sale de la ficha, no de la copia del resultado: sin sexo no hay clasificación.
            $sex = $visible[ $user_id ]['sex'] ?? RankingRoster::info( $user_id )['sex'];
            if ( '' === $sex ) {
                continue;
            }
            $row = [
                'result_id' => (int) $result->id,
                'user_id' => $user_id,
                'points' => (float) $result->points_absolute,
                'finished' => Keys::STATUS_FINISHED === $result->status,
                'race_date' => (string) $result->race_date,
            ];
            $abs_rows[ $sex ][] = $row;
            if ( null !== $result->points_age_group && '' !== (string) $result->age_group ) {
                $row['points'] = (float) $result->points_age_group;
                $grp_rows[ $sex ][ (string) $result->age_group ][] = $row;
            }
        }

        $out = [ 'season' => $season, 'best_n' => $n, 'absolute' => [], 'groups' => [], 'counted' => [], 'positions' => [], 'hidden' => $hidden ];
        foreach ( Keys::SEXES as $sex ) {
            $lines = Standings::build( $abs_rows[ $sex ] ?? [], $n, $sort );
            $out['absolute'][ $sex ] = $lines;
            foreach ( $lines as $line ) {
                $out['positions'][ $line['user_id'] ]['absolute'] = [ 'rank' => $line['rank'], 'total' => $line['total'], 'counted' => count( $line['counted'] ), 'results' => $line['results'], 'of' => count( $lines ) ];
                foreach ( $line['counted'] as $result_id ) {
                    $out['counted'][ $result_id ]['absolute'] = true;
                }
            }
            foreach ( $grp_rows[ $sex ] ?? [] as $group => $rows ) {
                $lines = Standings::build( $rows, $n, $sort );
                $out['groups'][ $sex ][ $group ] = $lines;
                foreach ( $lines as $line ) {
                    $out['positions'][ $line['user_id'] ]['group'] = [ 'rank' => $line['rank'], 'total' => $line['total'], 'group' => $group, 'of' => count( $lines ) ];
                    foreach ( $line['counted'] as $result_id ) {
                        $out['counted'][ $result_id ]['group'] = true;
                    }
                }
            }
        }

        if ( false === $include_hidden ) {
            set_transient( $key, $out, DAY_IN_SECONDS );
        }

        return $out;
    }

    /**
     * Invalida las clasificaciones guardadas.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function bump_cache(): void {
        update_option( self::CACHE_VERSION_OPTION, (int) get_option( self::CACHE_VERSION_OPTION, 1 ) + 1, false );
    }

    /**
     * Columnas calculadas de un resultado.
     *
     * @param   object    $result     Resultado.
     * @param   string    $format     Formato de la competición.
     * @param   int       $season     Año.
     * @param   float     $coef       Coeficiente.
     * @param   array     $baremo     Baremo de la temporada.
     * @param   AgeGroups $groups     Grupos de la temporada.
     * @param   array     $references Referencias por clave.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function calculate( object $result, string $format, int $season, float $coef, array $baremo, AgeGroups $groups, array $references ): array {
        $info = RankingRoster::info( (int) $result->user_id );
        $sex = $info['sex'];
        $group = $groups->for_birth( $info['birth'], $season );
        $team = Keys::has_team( $format ) ? (string) $result->team_category : '';
        $is_team = Keys::FORMAT_TEAM === $format;

        // Sin sexo en la ficha no hay clasificación en la que contar (SPEC §3.6): el resultado se guarda, pero sin puntos.
        if ( '' === $sex ) {
            return [
                'sex' => '',
                'age_group' => $group,
                'coefficient' => round( $coef, 2 ),
                'points_absolute' => 0.0,
                'points_age_group' => null,
                'method_absolute' => Keys::METHOD_NONE,
                'method_age_group' => Keys::METHOD_NONE,
                'calc_note' => __( 'El socio no tiene sexo en su ficha: no cuenta hasta que se rellene.', SULAYR_TEXTDOMAIN ),
            ];
        }

        $ref = function ( string $scope, string $ref_sex, string $ref_group ) use ( $references, $team ): ?array {
            $row = $references[ Keys::ref_key( $scope, $team, $ref_sex, $ref_group ) ] ?? null;

            return null === $row ? null : [ 'winner_time' => $row->winner_time, 'participants' => $row->participants ];
        };

        $calc = PointsCalculator::calculate( [
            'status' => (string) $result->status,
            'time_s' => $result->time_s,
            'position_sex' => $result->position_sex,
            'position_age_group' => $result->position_age_group,
            'coef' => $coef,
            'base' => $baremo['base'],
            'bonus' => $baremo['bonus'],
            'ref_abs' => $ref( Keys::SCOPE_ABSOLUTE, $is_team ? '' : $sex, '' ),
            'ref_grp' => ( $is_team || '' === $group ) ? null : $ref( Keys::SCOPE_GROUP, $sex, $group ),
            'has_group' => '' !== $group,
            'absolute_category' => Keys::is_absolute_category( (string) $result->race_category ),
            'group_from_absolute' => $is_team,
        ] );

        $notes = $calc['notes'];
        if ( '' === $group ) {
            $notes[] = __( 'Sin fecha de nacimiento o fuera de los grupos: no puntúa en grupo de edad.', SULAYR_TEXTDOMAIN );
        } elseif ( $groups->mismatch( (string) $result->race_category, $group ) ) {
            /* translators: 1: código de la carrera, 2: grupo calculado. */
            $notes[] = sprintf( __( 'La carrera lo clasifica como %1$s y la web como %2$s: revisa la fecha de nacimiento o las edades de los grupos.', SULAYR_TEXTDOMAIN ), $result->race_category, $groups->label( $group ) );
        }

        return [
            'sex' => $sex,
            'age_group' => $group,
            'coefficient' => round( $coef, 2 ),
            'points_absolute' => $calc['points_absolute'],
            'points_age_group' => $calc['points_age_group'],
            'method_absolute' => $calc['method_absolute'],
            'method_age_group' => $calc['method_age_group'],
            'calc_note' => substr( implode( ' ', $notes ), 0, 255 ),
        ];
    }

    /**
     * Puntos como texto para el registro: absoluta / grupo.
     *
     * @param   mixed $absolute Absoluta.
     * @param   mixed $group    Grupo.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function points_text( $absolute, $group ): string {
        return number_format( (float) $absolute, 2, '.', '' ) . ' / ' . ( null === $group ? '—' : number_format( (float) $group, 2, '.', '' ) );
    }
}
