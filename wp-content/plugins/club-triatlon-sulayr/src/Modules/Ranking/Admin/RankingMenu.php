<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Menú «Ranking» de wp-admin y listado de competiciones (SPEC-RANKING-INTERNO §4.1).
 *
 * Todo con la capacidad sulayr_manage_ranking. Las pantallas van dentro de
 * .sulayr-brand (Members\Admin\BrandStyles reconoce las páginas sulayr-ranking*).
 */
class RankingMenu {

    public const SLUG = 'sulayr-ranking';
    private const ACTION_RECALC = 'sulayr_ranking_recalculate';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 9 );
        add_action( 'admin_menu', [ $this, 'order_submenu' ], 1000 );
        add_action( 'admin_post_' . self::ACTION_RECALC, [ $this, 'handle_recalculate' ] );
        new CompetitionEditPage();
        new StandingsPage();
        new AthletesPage();
        new LogPage();
    }

    /**
     * URL del listado de una temporada.
     *
     * @param   int $season Año (0 = la en curso).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url( int $season = 0 ): string {
        $args = [ 'page' => self::SLUG ];
        if ( $season > 0 ) {
            $args['temporada'] = $season;
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Menú principal y listado.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_menu_page(
            __( 'Ranking', SULAYR_TEXTDOMAIN ),
            __( 'Ranking', SULAYR_TEXTDOMAIN ),
            MemberRoles::MANAGE_RANKING,
            self::SLUG,
            [ $this, 'render' ],
            'dashicons-awards',
            4
        );
        add_submenu_page( self::SLUG, __( 'Competiciones', SULAYR_TEXTDOMAIN ), __( 'Competiciones', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
    }

    /**
     * Orden del submenú: competiciones, añadir, clasificación, deportistas, registro y ajustes.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function order_submenu(): void {
        global $submenu;

        if ( empty( $submenu[ self::SLUG ] ) ) {
            return;
        }

        $order = array_flip( [ self::SLUG, CompetitionEditPage::SLUG, StandingsPage::SLUG, AthletesPage::SLUG, LogPage::SLUG, RankingSettings::PAGE_SLUG ] );
        $keyed = [];
        foreach ( array_values( $submenu[ self::SLUG ] ) as $i => $item ) {
            $keyed[] = [ $order[ $item[2] ] ?? count( $order ), $i, $item ];
        }
        sort( $keyed );

        $submenu[ self::SLUG ] = array_column( $keyed, 2 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordenar el submenú propio.
    }

    /**
     * Temporada elegida en un filtro GET (por defecto, la en curso).
     *
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function requested_season(): int {
        $season = isset( $_GET['temporada'] ) ? absint( $_GET['temporada'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtro de lectura.

        return $season >= 2000 && $season <= 2100 ? $season : RankingSettings::current_season();
    }

    /**
     * Selector de temporada (formulario GET).
     *
     * @param   string $page   Página de destino.
     * @param   int    $season Temporada elegida.
     * @param   array  $extra  Otros parámetros que se conservan.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function season_picker( string $page, int $season, array $extra = [] ): void {
        $seasons = Competitions::seasons();
        $seasons[] = RankingSettings::current_season();
        $seasons[] = $season;
        $seasons = array_unique( $seasons );
        rsort( $seasons );

        echo '<form method="get" class="sulayr-inline">';
        printf( '<input type="hidden" name="page" value="%s">', esc_attr( $page ) );
        foreach ( $extra as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
        }
        printf( '<label for="sulayr-ranking-season">%s</label> <select id="sulayr-ranking-season" name="temporada">', esc_html__( 'Temporada', SULAYR_TEXTDOMAIN ) );
        foreach ( $seasons as $year ) {
            printf( '<option value="%1$d"%2$s>%1$d</option>', (int) $year, selected( $season, $year, false ) );
        }
        printf( '</select> <button class="button">%s</button></form>', esc_html__( 'Ver', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Listado de competiciones de una temporada.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        Notices::require_cap();
        $season = self::requested_season();
        $competitions = Competitions::for_season( $season );

        echo '<div class="wrap sulayr-brand">';
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Ranking interno', SULAYR_TEXTDOMAIN ) . '</p>';
        printf( '<h1 class="wp-heading-inline">%s</h1> <a class="page-title-action" href="%s">%s</a><hr class="wp-header-end">', esc_html__( 'Competiciones', SULAYR_TEXTDOMAIN ), esc_url( CompetitionEditPage::url() ), esc_html__( 'Añadir competición', SULAYR_TEXTDOMAIN ) );
        Notices::render();

        echo '<p>';
        self::season_picker( self::SLUG, $season );
        echo '</p>';

        if ( empty( $competitions ) ) {
            echo '<p>' . esc_html( sprintf( __( 'No hay competiciones en %d.', SULAYR_TEXTDOMAIN ), $season ) ) . '</p>';
        } else {
            echo '<table class="widefat striped sulayr-t-ranking"><thead><tr>';
            foreach ( [ __( 'Fecha', SULAYR_TEXTDOMAIN ), __( 'Competición', SULAYR_TEXTDOMAIN ), __( 'Lugar', SULAYR_TEXTDOMAIN ), __( 'Tipo', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ), __( 'Resultados', SULAYR_TEXTDOMAIN ), __( 'Avisos', SULAYR_TEXTDOMAIN ) ] as $th ) {
                echo '<th scope="col">' . esc_html( $th ) . '</th>';
            }
            echo '</tr></thead><tbody>';
            foreach ( $competitions as $c ) {
                $missing = CompetitionEditPage::missing_count( (int) $c->id );
                printf(
                    '<tr><td>%1$s</td><td><a href="%2$s"><strong>%3$s</strong></a></td><td>%4$s</td><td>%5$s</td><td><span class="sulayr-tag">%6$s</span></td><td>%7$d</td><td>%8$s</td></tr>',
                    esc_html( date_i18n( 'd/m/Y', strtotime( (string) $c->race_date ) ) ),
                    esc_url( CompetitionEditPage::url( (int) $c->id ) ),
                    esc_html( (string) $c->name ),
                    esc_html( (string) $c->location ?: '—' ),
                    esc_html( Labels::of( Labels::modalities(), (string) $c->modality ) . ' · ' . Labels::of( Labels::distances(), (string) $c->distance ) . ' · ' . Labels::of( Labels::formats(), (string) $c->format ) ),
                    esc_html( Labels::of( Labels::competition_statuses(), (string) $c->status ) ),
                    (int) $c->result_count,
                    $missing > 0 ? esc_html( sprintf( _n( 'Falta %d referencia', 'Faltan %d referencias', $missing, SULAYR_TEXTDOMAIN ), $missing ) ) : '—'
                );
            }
            echo '</tbody></table>';
        }

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Recalcular', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Recalcula los puntos de todas las competiciones, cada temporada con su propio baremo. No cambia ningún dato. Úsalo si algo no cuadra.', SULAYR_TEXTDOMAIN ) . '</p>';
        printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION_RECALC ) );
        wp_nonce_field( self::ACTION_RECALC );
        printf( '<button class="button">%s</button></form></div>', esc_html__( 'Recalcular todo', SULAYR_TEXTDOMAIN ) );
        echo '</div>';
    }

    /**
     * «Recalcular todo» (R22: PROD no tiene WP-CLI). Es idempotente.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_recalculate(): void {
        check_admin_referer( self::ACTION_RECALC );
        Notices::require_cap();

        $changed = RankingService::recalculate_all( __( 'Recalcular todo', SULAYR_TEXTDOMAIN ) );
        /* translators: %d: resultados que cambiaron. */
        Notices::redirect( self::url(), 'success', sprintf( _n( 'Recalculado: cambió %d resultado.', 'Recalculado: cambiaron %d resultados.', $changed, SULAYR_TEXTDOMAIN ), $changed ) );
    }
}
