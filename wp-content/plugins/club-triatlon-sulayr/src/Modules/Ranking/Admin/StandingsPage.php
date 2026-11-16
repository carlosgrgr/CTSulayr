<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Modules\Members\Admin\MembersMenu;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Clasificación vista desde wp-admin (SPEC-RANKING-INTERNO §4.4).
 *
 * La misma que Mi cuenta. Debajo, los socios de baja que tienen resultados en
 * la temporada, fuera de la numeración.
 */
class StandingsPage {

    public const SLUG = 'sulayr-ranking-clasificacion';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
    }

    /**
     * Submenú.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( RankingMenu::SLUG, __( 'Clasificación', SULAYR_TEXTDOMAIN ), __( 'Clasificación', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
    }

    /**
     * Pantalla.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        Notices::require_cap();

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filtros de lectura.
        $season = RankingMenu::requested_season();
        $view = 'grupo' === ( $_GET['vista'] ?? '' ) ? 'grupo' : 'absoluta';
        $sex = in_array( $_GET['sexo'] ?? '', Keys::SEXES, true ) ? (string) $_GET['sexo'] : Keys::SEX_MALE;
        $groups = RankingSettings::groups( $season );
        $group = sanitize_key( wp_unslash( $_GET['grupo'] ?? '' ) );
        // phpcs:enable
        if ( false === array_key_exists( $group, $groups->options() ) ) {
            $group = (string) array_key_first( $groups->options() );
        }

        $standings = RankingService::standings( $season );
        $lines = 'grupo' === $view ? ( $standings['groups'][ $sex ][ $group ] ?? [] ) : ( $standings['absolute'][ $sex ] ?? [] );
        $published = count( Competitions::for_season( $season, Keys::COMPETITION_PUBLISHED ) );

        echo '<div class="wrap sulayr-brand">';
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Ranking interno', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<h1>' . esc_html__( 'Clasificación', SULAYR_TEXTDOMAIN ) . '</h1>';
        Notices::render();

        printf( '<form method="get"><input type="hidden" name="page" value="%s">', esc_attr( self::SLUG ) );
        echo '<p>';
        printf( '<label>%s <input type="number" name="temporada" value="%d" min="2000" max="2100" style="width:90px"></label> ', esc_html__( 'Temporada', SULAYR_TEXTDOMAIN ), $season );
        printf( '<label>%s <select name="vista"><option value="absoluta"%s>%s</option><option value="grupo"%s>%s</option></select></label> ', esc_html__( 'Clasificación', SULAYR_TEXTDOMAIN ), selected( $view, 'absoluta', false ), esc_html__( 'Absoluta', SULAYR_TEXTDOMAIN ), selected( $view, 'grupo', false ), esc_html__( 'Grupo de edad', SULAYR_TEXTDOMAIN ) );
        echo '<label>' . esc_html__( 'Sexo', SULAYR_TEXTDOMAIN ) . ' <select name="sexo">';
        foreach ( Labels::sexes() as $value => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $sex, $value, false ), esc_html( $label ) );
        }
        echo '</select></label> <label>' . esc_html__( 'Grupo', SULAYR_TEXTDOMAIN ) . ' <select name="grupo">';
        foreach ( $groups->options() as $value => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $group, $value, false ), esc_html( $label ) );
        }
        printf( '</select></label> <button class="button">%s</button></p></form>', esc_html__( 'Ver', SULAYR_TEXTDOMAIN ) );

        /* translators: 1: competiciones publicadas, 2: pruebas que cuentan. */
        echo '<p class="description">' . esc_html( sprintf( __( '%1$d competiciones publicadas. Cuentan las %2$d mejores de cada socio.', SULAYR_TEXTDOMAIN ), $published, $standings['best_n'] ) ) . '</p>';

        $this->table( $lines, $groups, 'grupo' !== $view );

        // Socios de baja con resultados, fuera de la numeración.
        $all = RankingService::standings( $season, true );
        $hidden_lines = array_values( array_filter( 'grupo' === $view ? ( $all['groups'][ $sex ][ $group ] ?? [] ) : ( $all['absolute'][ $sex ] ?? [] ), function ( $line ) use ( $all ) {
            return isset( $all['hidden'][ $line['user_id'] ] );
        } ) );
        if ( ! empty( $hidden_lines ) ) {
            echo '<h2>' . esc_html__( 'De baja (no salen en Mi cuenta)', SULAYR_TEXTDOMAIN ) . '</h2>';
            $this->table( $hidden_lines, $groups, 'grupo' !== $view, true );
        }
        echo '</div>';
    }

    /**
     * Tabla de una clasificación.
     *
     * @param   array                                                  $lines      Líneas de Standings::build().
     * @param   \ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups     $groups     Grupos.
     * @param   bool                                                   $with_group Columna de grupo.
     * @param   bool                                                   $hidden     Socios de baja (sin puesto).
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function table( array $lines, $groups, bool $with_group, bool $hidden = false ): void {
        if ( empty( $lines ) ) {
            echo '<p>' . esc_html__( 'Todavía no hay nadie en esta clasificación.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $can_members = current_user_can( MemberRoles::MANAGE_MEMBERS );
        echo '<table class="widefat striped"><thead><tr>';
        $headers = [ $hidden ? '' : __( 'Puesto', SULAYR_TEXTDOMAIN ), __( 'Socio', SULAYR_TEXTDOMAIN ) ];
        if ( $with_group ) {
            $headers[] = __( 'Grupo', SULAYR_TEXTDOMAIN );
        }
        $headers = array_merge( $headers, [ __( 'Terminadas', SULAYR_TEXTDOMAIN ), __( 'Cuentan', SULAYR_TEXTDOMAIN ), __( 'Puntos', SULAYR_TEXTDOMAIN ) ] );
        foreach ( $headers as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ( $lines as $line ) {
            $info = RankingRoster::info( $line['user_id'] );
            $name = esc_html( $info['name'] );
            if ( $can_members ) {
                $name = sprintf( '<a href="%s">%s</a>', esc_url( MembersMenu::member_url( $line['user_id'] ) ), $name );
            }
            echo '<tr>';
            echo '<td>' . ( $hidden ? '—' : (int) $line['rank'] ) . '</td>';
            echo '<td>' . $name . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
            if ( $with_group ) {
                $group = $groups->for_birth( $info['birth'], (int) RankingMenu::requested_season() );
                echo '<td>' . esc_html( '' !== $group ? $groups->label( $group ) : '—' ) . '</td>';
            }
            printf( '<td>%d</td><td>%d / %d</td><td><strong>%s</strong></td></tr>', (int) $line['finished'], count( $line['counted'] ), (int) $line['results'], esc_html( Labels::points( $line['total'] ) ) );
        }
        echo '</tbody></table>';
    }
}
