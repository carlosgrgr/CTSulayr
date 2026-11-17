<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Front;

use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;
use ClubTriatlonSulayr\Modules\Ranking\Results;
use ClubTriatlonSulayr\Services\TemplateService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Vistas del ranking en el front (SPEC-RANKING-INTERNO §8.1).
 *
 * Lee los parámetros GET, comprueba el acceso y prepara los datos de la
 * plantilla: clasificación, ficha del deportista o ficha de la competición.
 * Las plantillas solo pintan y escapan. La usan la pestaña de Mi cuenta y el
 * shortcode.
 */
class RankingView {

    /**
     * True si el usuario actual puede ver el ranking (R29, R37): socio activo o congelado.
     *
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function can_view(): bool {
        return is_user_logged_in() && RankingRoster::is_visible( get_current_user_id() );
    }

    /**
     * HTML de la vista que piden los parámetros GET.
     *
     * @param   string $base_url URL de la pestaña o de la página del shortcode.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function render( string $base_url ): string {
        if ( false === is_user_logged_in() ) {
            return self::notice( __( 'El ranking es para socios del club. Entra con tu cuenta para verlo.', SULAYR_TEXTDOMAIN ), function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : wp_login_url( $base_url ), __( 'Entrar', SULAYR_TEXTDOMAIN ) );
        }
        if ( false === self::can_view() ) {
            return self::notice( __( 'El ranking es para socios del club.', SULAYR_TEXTDOMAIN ) );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Vista de solo lectura.
        $athlete = absint( $_GET['deportista'] ?? 0 );
        $competition = absint( $_GET['competicion'] ?? 0 );
        $season = absint( $_GET['temporada'] ?? 0 );
        // phpcs:enable

        if ( $competition > 0 ) {
            return self::competition( $competition, $base_url );
        }

        $seasons = Competitions::seasons( true );
        if ( empty( $seasons ) ) {
            return self::notice( __( 'Todavía no hay competiciones publicadas en el ranking.', SULAYR_TEXTDOMAIN ) );
        }
        if ( false === in_array( $season, $seasons, true ) ) {
            $current = RankingSettings::current_season();
            $season = in_array( $current, $seasons, true ) ? $current : $seasons[0];
        }

        if ( $athlete > 0 ) {
            return self::athlete( $athlete, $season, $base_url );
        }

        return self::standings( $season, $seasons, $base_url );
    }

    /**
     * Clasificación.
     *
     * @param   int    $season   Año.
     * @param   int[]  $seasons  Temporadas con competiciones publicadas.
     * @param   string $base_url URL base.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function standings( int $season, array $seasons, string $base_url ): string {
        $me = get_current_user_id();
        $my_info = RankingRoster::info( $me );
        $groups = RankingSettings::groups( $season );
        $options = $groups->options();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filtros de lectura.
        $view = 'grupo' === ( $_GET['vista'] ?? '' ) ? 'grupo' : 'absoluta';
        $sex = sanitize_key( wp_unslash( $_GET['sexo'] ?? '' ) );
        $group = sanitize_key( wp_unslash( $_GET['grupo'] ?? '' ) );
        // phpcs:enable
        if ( false === in_array( $sex, Keys::SEXES, true ) ) {
            $sex = '' !== $my_info['sex'] ? $my_info['sex'] : Keys::SEX_MALE;
        }
        if ( false === array_key_exists( $group, $options ) ) {
            $mine = $groups->for_birth( $my_info['birth'], $season );
            $group = '' !== $mine ? $mine : (string) array_key_first( $options );
        }

        $standings = RankingService::standings( $season );
        $lines = 'grupo' === $view ? ( $standings['groups'][ $sex ][ $group ] ?? [] ) : ( $standings['absolute'][ $sex ] ?? [] );

        $rows = [];
        foreach ( $lines as $line ) {
            $info = RankingRoster::info( $line['user_id'] );
            $member_group = $groups->for_birth( $info['birth'], $season );
            $rows[] = [
                'rank' => (int) $line['rank'],
                'name' => $info['name'],
                'url' => add_query_arg( [ 'deportista' => $line['user_id'], 'temporada' => $season ], $base_url ),
                'photo' => RankingRoster::photo_url( $line['user_id'] ),
                'initials' => RankingRoster::initials( $info['name'] ),
                'group' => '' !== $member_group ? $groups->label( $member_group ) : '—',
                'finished' => (int) $line['finished'],
                'counted' => count( $line['counted'] ),
                'results' => (int) $line['results'],
                'total' => Labels::points( $line['total'] ),
                'is_me' => $line['user_id'] === $me,
            ];
        }

        $competitions = [];
        foreach ( Competitions::for_season( $season, Keys::COMPETITION_PUBLISHED ) as $c ) {
            $competitions[] = self::competition_summary( $c, $base_url );
        }

        $season_options = [];
        foreach ( $seasons as $year ) {
            $season_options[ $year ] = (string) $year;
        }

        $title = 'grupo' === $view
            ? sprintf( '%s · %s', $groups->label( $group ), Labels::of( Labels::sexes(), $sex ) )
            : sprintf( __( 'Absoluta · %s', SULAYR_TEXTDOMAIN ), Labels::of( Labels::sexes(), $sex ) );

        return self::wrap( TemplateService::render( 'ranking/standings', [
            'filters' => [
                'action' => $base_url,
                'hidden' => self::hidden_query_args( $base_url ),
                'season' => $season,
                'seasons' => $season_options,
                'view' => $view,
                'views' => [ 'absoluta' => __( 'Absoluta', SULAYR_TEXTDOMAIN ), 'grupo' => __( 'Grupo de edad', SULAYR_TEXTDOMAIN ) ],
                'sex' => $sex,
                'sexes' => Labels::sexes(),
                'group' => $group,
                'groups' => $options,
            ],
            'title' => $title,
            'season' => $season,
            'best_n' => (int) $standings['best_n'],
            'rows' => $rows,
            'competitions' => $competitions,
        ] ) );
    }

    /**
     * Ficha del deportista.
     *
     * @param   int    $user_id  Socio.
     * @param   int    $season   Año.
     * @param   string $base_url URL base.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function athlete( int $user_id, int $season, string $base_url ): string {
        if ( false === RankingRoster::is_visible( $user_id ) ) {
            return self::notice( __( 'Deportista no encontrado.', SULAYR_TEXTDOMAIN ), add_query_arg( 'temporada', $season, $base_url ), __( 'Volver a la clasificación', SULAYR_TEXTDOMAIN ) );
        }

        $info = RankingRoster::info( $user_id );
        $baremo = RankingSettings::for_season( $season );
        $groups = new AgeGroups( $baremo['age_groups'] );
        $group = $groups->for_birth( $info['birth'], $season );
        $standings = RankingService::standings( $season );
        $position = $standings['positions'][ $user_id ] ?? [];
        $counted = $standings['counted'];

        $results = [];
        foreach ( Results::for_season( $season, true, $user_id ) as $r ) {
            $results[] = [
                'date' => date_i18n( 'd/m/Y', strtotime( (string) $r->race_date ) ),
                'competition' => (string) $r->competition_name,
                'url' => add_query_arg( 'competicion', (int) $r->competition_id, $base_url ),
                'type' => Labels::of( Labels::modalities(), (string) $r->modality ) . ' · ' . Labels::of( Labels::distances(), (string) $r->distance ),
                'interest' => Labels::interest( $r->interest_bonus, (string) $r->interest_label ),
                'status' => Labels::status_short( (string) $r->status ),
                'finished' => Keys::STATUS_FINISHED === $r->status,
                'time' => RaceTime::format( $r->time_s ) ?: '—',
                'position_sex' => null !== $r->position_sex ? (string) $r->position_sex : '—',
                'race_category' => '' !== (string) $r->race_category ? (string) $r->race_category : '—',
                'position_age_group' => null !== $r->position_age_group ? (string) $r->position_age_group : '—',
                'points_absolute' => Labels::points( $r->points_absolute ),
                'points_age_group' => Labels::points( $r->points_age_group ),
                'breakdown_absolute' => self::breakdown( $r->points_absolute, (string) $r->method_absolute, (float) $r->coefficient, (int) $r->interest_bonus, $baremo ),
                'breakdown_age_group' => self::breakdown( $r->points_age_group, (string) $r->method_age_group, (float) $r->coefficient, (int) $r->interest_bonus, $baremo ),
                'counts_absolute' => ! empty( $counted[ (int) $r->id ]['absolute'] ),
                'counts_age_group' => ! empty( $counted[ (int) $r->id ]['group'] ),
            ];
        }

        $seasons = [];
        foreach ( Results::seasons_for_user( $user_id ) as $year ) {
            $seasons[ $year ] = add_query_arg( [ 'deportista' => $user_id, 'temporada' => $year ], $base_url );
        }

        $abs = $position['absolute'] ?? null;
        $grp = $position['group'] ?? null;

        return self::wrap( TemplateService::render( 'ranking/athlete', [
            'back_url' => add_query_arg( [ 'temporada' => $season, 'sexo' => $info['sex'] ], $base_url ),
            'season' => $season,
            'seasons' => $seasons,
            'best_n' => (int) $baremo['best_n'],
            'athlete' => [
                'name' => $info['name'],
                'photo' => RankingRoster::photo_url( $user_id ),
                'initials' => RankingRoster::initials( $info['name'] ),
                'sex' => Labels::of( Labels::sexes(), $info['sex'] ),
                'group' => '' !== $group ? $groups->label( $group ) : '—',
                'absolute_rank' => null !== $abs ? sprintf( __( '%1$dº de %2$d', SULAYR_TEXTDOMAIN ), $abs['rank'], $abs['of'] ) : '—',
                'absolute_total' => null !== $abs ? Labels::points( $abs['total'] ) : '—',
                'group_rank' => null !== $grp ? sprintf( __( '%1$dº de %2$d', SULAYR_TEXTDOMAIN ), $grp['rank'], $grp['of'] ) : '—',
                'group_total' => null !== $grp ? Labels::points( $grp['total'] ) : '—',
                /* translators: 1: pruebas que cuentan, 2: pruebas con resultado. */
                'counted' => null !== $abs ? sprintf( __( '%1$d de %2$d', SULAYR_TEXTDOMAIN ), $abs['counted'], $abs['results'] ) : '—',
                'is_me' => get_current_user_id() === $user_id,
            ],
            'results' => $results,
        ] ) );
    }

    /**
     * Ficha de la competición.
     *
     * @param   int    $id       Competición.
     * @param   string $base_url URL base.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function competition( int $id, string $base_url ): string {
        $c = Competitions::get( $id );
        if ( null === $c || Keys::COMPETITION_PUBLISHED !== $c->status ) {
            return self::notice( __( 'Competición no encontrada.', SULAYR_TEXTDOMAIN ), $base_url, __( 'Volver a la clasificación', SULAYR_TEXTDOMAIN ) );
        }

        $season = (int) $c->season;
        $groups = RankingSettings::groups( $season );
        $format = (string) $c->format;
        $me = get_current_user_id();

        $references = [];
        foreach ( Competitions::references( $id ) as $ref ) {
            if ( null === $ref->winner_time ) {
                continue;
            }
            $prefix = '' !== (string) $ref->team_category ? Labels::of( Labels::team_categories(), (string) $ref->team_category ) . ' · ' : '';
            if ( Keys::FORMAT_TEAM === $format ) {
                $label = $prefix . __( 'Equipo ganador', SULAYR_TEXTDOMAIN );
            } elseif ( Keys::SCOPE_ABSOLUTE === $ref->scope ) {
                $label = $prefix . ( Keys::SEX_FEMALE === $ref->sex ? __( 'Ganadora femenina', SULAYR_TEXTDOMAIN ) : __( 'Ganador masculino', SULAYR_TEXTDOMAIN ) );
            } else {
                $label = $prefix . $groups->label( (string) $ref->age_group ) . ' · ' . Labels::of( Labels::sexes(), (string) $ref->sex );
            }
            $references[] = [
                'label' => $label,
                'time' => RaceTime::format( $ref->winner_time ),
                'participants' => null !== $ref->participants ? (string) $ref->participants : '',
            ];
        }

        $sections = [];
        foreach ( Results::for_competition( $id ) as $r ) {
            if ( false === RankingRoster::is_visible( (int) $r->user_id ) ) {
                continue;
            }
            $info = RankingRoster::info( (int) $r->user_id );
            $section = Keys::has_team( $format ) ? Labels::of( Labels::team_categories(), (string) $r->team_category ) : '';
            $sections[ $section ][] = [
                'name' => $info['name'],
                'url' => add_query_arg( [ 'deportista' => (int) $r->user_id, 'temporada' => $season ], $base_url ),
                'sex' => Labels::of( Labels::sexes(), (string) $r->sex ),
                'group' => '' !== (string) $r->age_group ? $groups->label( (string) $r->age_group ) : '—',
                'race_category' => '' !== (string) $r->race_category ? (string) $r->race_category : '—',
                'time' => RaceTime::format( $r->time_s ) ?: '—',
                'position_sex' => null !== $r->position_sex ? (string) $r->position_sex : '—',
                'position_age_group' => null !== $r->position_age_group ? (string) $r->position_age_group : '—',
                'status' => Labels::status_short( (string) $r->status ),
                'finished' => Keys::STATUS_FINISHED === $r->status,
                'points_absolute' => Labels::points( $r->points_absolute ),
                'points_age_group' => Labels::points( $r->points_age_group ),
                'is_me' => (int) $r->user_id === $me,
            ];
        }

        return self::wrap( TemplateService::render( 'ranking/competition', [
            'back_url' => add_query_arg( 'temporada', $season, $base_url ),
            'competition' => self::competition_summary( $c, $base_url ) + [
                'location' => (string) $c->location,
                'format' => Labels::of( Labels::formats(), $format ),
            ],
            'references' => $references,
            'sections' => $sections,
        ] ) );
    }

    /**
     * Datos de una competición para listas y cabeceras.
     *
     * @param   object $c        Competición.
     * @param   string $base_url URL base.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function competition_summary( object $c, string $base_url ): array {
        return [
            'name' => (string) $c->name,
            'date' => date_i18n( 'd/m/Y', strtotime( (string) $c->race_date ) ),
            'type' => Labels::of( Labels::modalities(), (string) $c->modality ) . ' · ' . Labels::of( Labels::distances(), (string) $c->distance ),
            'interest' => Labels::interest( $c->interest_bonus ?? 0, (string) ( $c->interest_label ?? '' ) ),
            'url' => add_query_arg( 'competicion', (int) $c->id, $base_url ),
        ];
    }

    /**
     * Desglose de los puntos: «1000 × 0,8333 × 1,10 + 50», y «+ 100 interés del club» si la prueba tiene bonus (§15.5).
     *
     * @param   float|null $points   Puntos.
     * @param   string     $method   Método.
     * @param   float      $coef     Coeficiente.
     * @param   int        $interest Bonus por interés aplicado (copia del resultado).
     * @param   array      $baremo   Baremo de la temporada.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function breakdown( $points, string $method, float $coef, int $interest, array $baremo ): string {
        if ( null === $points ) {
            return __( 'No puntúa en grupo de edad.', SULAYR_TEXTDOMAIN );
        }
        if ( Keys::METHOD_STATUS === $method ) {
            return __( 'No terminó: 0 puntos.', SULAYR_TEXTDOMAIN );
        }
        if ( Keys::METHOD_NONE === $method || $coef <= 0 ) {
            return __( 'Sin referencia: 0 puntos.', SULAYR_TEXTDOMAIN );
        }

        $base = (float) $baremo['base'];
        $bonus = (float) $baremo['bonus'];
        $ratio = Keys::METHOD_ABSOLUTE_CATEGORY === $method ? 1.0 : ( (float) $points - $bonus - $interest ) / ( $base * $coef );
        $text = sprintf( '%s × %s × %s + %s', number_format_i18n( $base ), number_format_i18n( $ratio, 4 ), number_format_i18n( $coef, 2 ), number_format_i18n( $bonus ) );
        if ( $interest > 0 ) {
            /* translators: %s: puntos del bonus por interés del club. */
            $text .= ' ' . sprintf( __( '+ %s interés del club', SULAYR_TEXTDOMAIN ), number_format_i18n( $interest ) );
        }

        if ( Keys::METHOD_POSITION === $method ) {
            $text .= ' (' . __( 'por posición', SULAYR_TEXTDOMAIN ) . ')';
        } elseif ( Keys::METHOD_ABSOLUTE_CATEGORY === $method ) {
            $text .= ' (' . __( 'categoría absoluta', SULAYR_TEXTDOMAIN ) . ')';
        }

        return $text;
    }

    /**
     * Parámetros de la URL base que el formulario GET tiene que conservar (p. ej. ?page_id=).
     *
     * @param   string $base_url URL base.
     * @return 	array<string, string>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function hidden_query_args( string $base_url ): array {
        $query = (string) wp_parse_url( $base_url, PHP_URL_QUERY );
        $args = [];
        parse_str( $query, $args );

        return array_map( 'strval', array_filter( $args, 'is_scalar' ) );
    }

    /**
     * Aviso.
     *
     * @param   string $text  Texto.
     * @param   string $url   Enlace opcional.
     * @param   string $label Texto del enlace.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function notice( string $text, string $url = '', string $label = '' ): string {
        return self::wrap( TemplateService::render( 'ranking/notice', [ 'text' => $text, 'url' => $url, 'label' => $label ] ) );
    }

    /**
     * Contenedor común.
     *
     * @param   string $html Contenido.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function wrap( string $html ): string {
        return '<div class="sulayr-ranking">' . $html . '</div>';
    }
}
