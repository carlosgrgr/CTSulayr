<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingLog;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Ranking → Registro»: quién cambió qué (SPEC-RANKING-INTERNO §4.6).
 */
class LogPage {

    public const SLUG = 'sulayr-ranking-registro';

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
     * URL con filtros.
     *
     * @param   array $filters competicion, socio, tipo, pagina.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url( array $filters = [] ): string {
        return add_query_arg( array_merge( [ 'page' => self::SLUG ], array_filter( $filters ) ), admin_url( 'admin.php' ) );
    }

    /**
     * Submenú.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( RankingMenu::SLUG, __( 'Registro del ranking', SULAYR_TEXTDOMAIN ), __( 'Registro', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
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
        $competition_id = absint( $_GET['competicion'] ?? 0 );
        $user_id = absint( $_GET['socio'] ?? 0 );
        $entity = sanitize_key( wp_unslash( $_GET['tipo'] ?? '' ) );
        $page = max( 1, absint( $_GET['pagina'] ?? 1 ) );
        // phpcs:enable
        if ( false === array_key_exists( $entity, Labels::log_entities() ) ) {
            $entity = '';
        }

        $data = RankingLog::query( [ 'competition_id' => $competition_id, 'user_id' => $user_id, 'entity' => $entity ], $page );
        $pages = (int) ceil( $data['total'] / RankingLog::PER_PAGE );

        echo '<div class="wrap sulayr-brand">';
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Ranking interno', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<h1>' . esc_html__( 'Registro', SULAYR_TEXTDOMAIN ) . '</h1>';

        printf( '<form method="get"><input type="hidden" name="page" value="%s"><p>', esc_attr( self::SLUG ) );
        if ( $competition_id > 0 ) {
            printf( '<input type="hidden" name="competicion" value="%d">', $competition_id );
        }
        if ( $user_id > 0 ) {
            printf( '<input type="hidden" name="socio" value="%d">', $user_id );
        }
        echo '<label>' . esc_html__( 'Tipo', SULAYR_TEXTDOMAIN ) . ' <select name="tipo"><option value="">' . esc_html__( 'Todos', SULAYR_TEXTDOMAIN ) . '</option>';
        foreach ( Labels::log_entities() as $value => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $entity, $value, false ), esc_html( $label ) );
        }
        printf( '</select></label> <button class="button">%s</button>', esc_html__( 'Filtrar', SULAYR_TEXTDOMAIN ) );
        if ( $competition_id > 0 || $user_id > 0 || '' !== $entity ) {
            printf( ' <a href="%s">%s</a>', esc_url( self::url() ), esc_html__( 'Quitar filtros', SULAYR_TEXTDOMAIN ) );
        }
        echo '</p></form>';

        if ( empty( $data['rows'] ) ) {
            echo '<p>' . esc_html__( 'No hay entradas.', SULAYR_TEXTDOMAIN ) . '</p></div>';
            return;
        }

        $names = [];
        echo '<table class="widefat striped"><thead><tr>';
        foreach ( [ __( 'Fecha', SULAYR_TEXTDOMAIN ), __( 'Autor', SULAYR_TEXTDOMAIN ), __( 'Tipo', SULAYR_TEXTDOMAIN ), __( 'Competición', SULAYR_TEXTDOMAIN ), __( 'Socio', SULAYR_TEXTDOMAIN ), __( 'Campo', SULAYR_TEXTDOMAIN ), __( 'Antes', SULAYR_TEXTDOMAIN ), __( 'Después', SULAYR_TEXTDOMAIN ), __( 'Nota', SULAYR_TEXTDOMAIN ) ] as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $data['rows'] as $row ) {
            $author = (int) $row->author_id;
            if ( ! isset( $names[ $author ] ) ) {
                $names[ $author ] = $author > 0 ? RankingRoster::info( $author )['name'] : __( 'Sistema', SULAYR_TEXTDOMAIN );
            }
            $competition = '—';
            if ( (int) $row->competition_id > 0 ) {
                $c = Competitions::get( (int) $row->competition_id );
                $competition = sprintf( '<a href="%s">%s</a>', esc_url( self::url( [ 'competicion' => (int) $row->competition_id ] ) ), esc_html( null !== $c ? (string) $c->name : sprintf( __( 'Competición %d (borrada)', SULAYR_TEXTDOMAIN ), (int) $row->competition_id ) ) );
            }
            $member = (int) $row->user_id > 0 ? sprintf( '<a href="%s">%s</a>', esc_url( self::url( [ 'socio' => (int) $row->user_id ] ) ), esc_html( RankingRoster::info( (int) $row->user_id )['name'] ) ) : '—';
            printf(
                '<tr><td>%1$s</td><td>%2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td><code>%6$s</code></td><td>%7$s</td><td>%8$s</td><td>%9$s</td></tr>',
                esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) ),
                esc_html( $names[ $author ] ),
                esc_html( Labels::of( Labels::log_entities(), (string) $row->entity ) ),
                $competition, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
                $member, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
                esc_html( (string) $row->field ),
                self::value( (string) $row->old_value ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en value().
                self::value( (string) $row->new_value ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                esc_html( (string) $row->note )
            );
        }
        echo '</tbody></table>';

        if ( $pages > 1 ) {
            echo '<p>';
            for ( $p = 1; $p <= $pages; $p++ ) {
                if ( $p === $page ) {
                    printf( '<strong class="sulayr-inline">%d</strong>', $p );
                } else {
                    printf( '<a class="sulayr-inline" href="%s">%d</a>', esc_url( self::url( [ 'competicion' => $competition_id, 'socio' => $user_id, 'tipo' => $entity, 'pagina' => $p ] ) ), $p );
                }
            }
            echo '</p>';
        }
        echo '</div>';
    }

    /**
     * Valor del registro: los largos (borrados en JSON) van plegados.
     *
     * @param   string $value Texto.
     * @return 	string HTML escapado.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function value( string $value ): string {
        if ( strlen( $value ) <= 80 ) {
            return esc_html( '' !== $value ? $value : '—' );
        }

        return '<details><summary>' . esc_html__( 'Ver', SULAYR_TEXTDOMAIN ) . '</summary><code style="white-space:pre-wrap;word-break:break-all">' . esc_html( $value ) . '</code></details>';
    }
}
