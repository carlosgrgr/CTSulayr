<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Ranking → Deportistas»: sexo, fecha de nacimiento y grupo de edad de los
 * socios del ranking (SPEC-RANKING-INTERNO §4.5).
 *
 * El gestor de ranking no tiene edit_users, así que el sexo se rellena aquí.
 * MemberLog registra cada cambio y RankingService recalcula sus resultados.
 */
class AthletesPage {

    public const SLUG = 'sulayr-ranking-deportistas';
    private const ACTION = 'sulayr_ranking_save_sexes';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
    }

    /**
     * URL de la pantalla.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url(): string {
        return add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
    }

    /**
     * Submenú.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( RankingMenu::SLUG, __( 'Deportistas del ranking', SULAYR_TEXTDOMAIN ), __( 'Deportistas', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
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

        $season = RankingMenu::requested_season();
        $groups = RankingSettings::groups( $season );
        $members = RankingRoster::members();
        $without_sex = array_filter( $members, function ( $m ) {
            return '' === $m['sex'];
        } );
        $without_birth = array_filter( $members, function ( $m ) {
            return '' === $m['birth'];
        } );

        echo '<div class="wrap sulayr-brand">';
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Ranking interno', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<h1>' . esc_html__( 'Deportistas', SULAYR_TEXTDOMAIN ) . '</h1>';
        Notices::render();

        echo '<p class="description">' . esc_html__( 'Socios activos y congelados. Un socio sin sexo no puede tener resultados. La fecha de nacimiento la cambia un gestor de socios o un administrador.', SULAYR_TEXTDOMAIN ) . '</p>';
        if ( ! empty( $without_sex ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( _n( '%d socio sin sexo.', '%d socios sin sexo.', count( $without_sex ), SULAYR_TEXTDOMAIN ), count( $without_sex ) ) ) . '</p></div>';
        }
        if ( ! empty( $without_birth ) ) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html( sprintf( _n( '%d socio sin fecha de nacimiento: no puntúa en grupo de edad.', '%d socios sin fecha de nacimiento: no puntúan en grupo de edad.', count( $without_birth ), SULAYR_TEXTDOMAIN ), count( $without_birth ) ) ) . '</p></div>';
        }

        echo '<p>';
        RankingMenu::season_picker( self::SLUG, $season );
        echo '</p>';

        // Primero los que tienen avisos.
        uasort( $members, function ( $a, $b ) {
            $wa = ( '' === $a['sex'] || '' === $a['birth'] ) ? 0 : 1;
            $wb = ( '' === $b['sex'] || '' === $b['birth'] ) ? 0 : 1;
            return [ $wa, $a['sort'] ] <=> [ $wb, $b['sort'] ];
        } );

        printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION ) );
        wp_nonce_field( self::ACTION );
        echo '<table class="widefat striped"><thead><tr>';
        foreach ( [ __( 'Socio', SULAYR_TEXTDOMAIN ), __( 'Nº', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ), __( 'Sexo', SULAYR_TEXTDOMAIN ), __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ), sprintf( __( 'Grupo en %d', SULAYR_TEXTDOMAIN ), $season ) ] as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $members as $id => $m ) {
            $group = $groups->for_birth( $m['birth'], $season );
            echo '<tr>';
            printf( '<td>%s</td><td>%s</td><td>%s</td>', esc_html( $m['name'] ), esc_html( $m['number'] ?: '—' ), esc_html( StatusPeriods::label( $m['status'] ) ) );
            printf( '<td><select name="sexes[%d]" aria-label="%s"><option value="">%s</option>', (int) $id, esc_attr( sprintf( __( 'Sexo de %s', SULAYR_TEXTDOMAIN ), $m['name'] ) ), esc_html__( '— Sin rellenar —', SULAYR_TEXTDOMAIN ) );
            foreach ( Labels::sexes() as $value => $label ) {
                printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $m['sex'], $value, false ), esc_html( $label ) );
            }
            echo '</select></td>';
            printf( '<td>%s</td><td>%s</td></tr>', esc_html( '' !== $m['birth'] ? date_i18n( 'd/m/Y', strtotime( $m['birth'] ) ) : '—' ), esc_html( '' !== $group ? $groups->label( $group ) . ' (' . AgeGroups::age( $m['birth'], $season ) . ')' : '—' ) );
        }
        echo '</tbody></table>';
        printf( '<p class="submit"><button class="button button-primary sulayr-cta">%s</button></p></form></div>', esc_html__( 'Guardar sexos', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Guarda los sexos que cambiaron.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        check_admin_referer( self::ACTION );
        Notices::require_cap();

        $members = RankingRoster::members();
        $changed = 0;
        foreach ( (array) wp_unslash( $_POST['sexes'] ?? [] ) as $user_id => $sex ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se comprueba contra una lista cerrada.
            $user_id = absint( $user_id );
            $sex = sanitize_key( (string) $sex );
            if ( ! isset( $members[ $user_id ] ) || $members[ $user_id ]['sex'] === $sex ) {
                continue;
            }
            if ( '' === $sex ) {
                delete_user_meta( $user_id, CustomFields::MEMBER_SEX );
            } elseif ( in_array( $sex, [ CustomFields::SEX_MALE, CustomFields::SEX_FEMALE ], true ) ) {
                update_user_meta( $user_id, CustomFields::MEMBER_SEX, $sex );
            } else {
                continue;
            }
            $changed++;
        }

        RankingRoster::flush();
        /* translators: %d: socios. */
        Notices::redirect( self::url(), 'success', sprintf( _n( 'Sexo guardado en %d socio. Sus resultados se han recalculado.', 'Sexo guardado en %d socios. Sus resultados se han recalculado.', $changed, SULAYR_TEXTDOMAIN ), $changed ) );
    }
}
