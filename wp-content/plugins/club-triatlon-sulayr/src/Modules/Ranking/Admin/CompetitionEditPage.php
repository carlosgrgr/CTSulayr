<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ResultValidator;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;
use ClubTriatlonSulayr\Modules\Ranking\Results;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Añadir o editar una competición: datos, resultados y referencias
 * (SPEC-RANKING-INTERNO §4.2 y §4.3).
 *
 * Un solo formulario a admin-post.php. Si hay errores no se guarda nada y la
 * pantalla vuelve con lo enviado y los errores fila por fila.
 */
class CompetitionEditPage {

    public const SLUG = 'sulayr-ranking-competicion';
    private const ACTION_SAVE = 'sulayr_ranking_save_competition';
    private const ACTION_DELETE = 'sulayr_ranking_delete_competition';

    /** Filas vacías que se añaden a la tabla de resultados. */
    private const EMPTY_ROWS = 5;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION_SAVE, [ $this, 'handle_save' ] );
        add_action( 'admin_post_' . self::ACTION_DELETE, [ $this, 'handle_delete' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
    }

    /**
     * URL de alta (sin id) o de edición.
     *
     * @param   int $id Competición.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url( int $id = 0 ): string {
        $args = [ 'page' => self::SLUG ];
        if ( $id > 0 ) {
            $args['id'] = $id;
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Submenú «Añadir competición».
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( RankingMenu::SLUG, __( 'Competición', SULAYR_TEXTDOMAIN ), __( 'Añadir competición', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
    }

    /**
     * JavaScript de la tabla de resultados, solo en esta pantalla.
     *
     * @param   string $hook Pantalla.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function enqueue( $hook ): void {
        if ( self::SLUG !== sanitize_key( wp_unslash( $_GET['page'] ?? '' ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        wp_enqueue_script( 'sulayr-ranking-results', SULAYR_ADMIN_ASSETS . '/js/ranking-results.js', [], SULAYR_VERSION, true );
    }

    /**
     * Referencias obligatorias que faltan en una competición guardada.
     *
     * @param   int $id Competición.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function missing_count( int $id ): int {
        $competition = Competitions::get( $id );
        if ( null === $competition ) {
            return 0;
        }

        $rows = [];
        foreach ( Results::for_competition( $id ) as $r ) {
            $rows[] = [ 'status' => $r->status, 'sex' => $r->sex, 'age_group' => $r->age_group, 'time_s' => $r->time_s, 'race_category' => $r->race_category, 'team_category' => $r->team_category ];
        }
        $references = [];
        foreach ( Competitions::references( $id ) as $key => $ref ) {
            $references[ $key ] = [ 'winner_time' => $ref->winner_time, 'participants' => $ref->participants ];
        }

        return count( ResultValidator::missing_references( ResultValidator::required_references( $rows, (string) $competition->format ), $references ) );
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

        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $competition = $id > 0 ? Competitions::get( $id ) : null;
        if ( $id > 0 && null === $competition ) {
            echo '<div class="wrap sulayr-brand"><p>' . esc_html__( 'Competición no encontrada.', SULAYR_TEXTDOMAIN ) . '</p></div>';
            return;
        }

        $state = Notices::take_state();
        $input = $state['input'] ?? self::input_from_db( $competition );
        $season = self::season_of( (string) ( $input['competition']['race_date'] ?? '' ) );
        $groups = RankingSettings::groups( $season );
        $members = self::members( $groups, $season, $id );
        $validation = ResultValidator::validate( $input, self::member_map( $members ), $groups, current_time( 'Y-m-d' ) );
        $errors = $state['errors'] ?? ( null === $competition ? [ 'competition' => [], 'references' => [], 'results' => [] ] : $validation['errors'] );
        $format = $validation['competition']['format'];
        $calc = [];
        if ( null !== $competition ) {
            foreach ( Results::for_competition( $id ) as $row ) {
                $calc[ (int) $row->user_id ] = $row;
            }
        }

        echo '<div class="wrap sulayr-brand">';
        printf( '<p><a href="%s">← %s</a></p>', esc_url( RankingMenu::url( null !== $competition ? (int) $competition->season : 0 ) ), esc_html__( 'Volver a competiciones', SULAYR_TEXTDOMAIN ) );
        echo '<p class="sulayr-eyebrow">' . esc_html( null !== $competition ? Labels::of( Labels::competition_statuses(), (string) $competition->status ) : __( 'Nueva competición', SULAYR_TEXTDOMAIN ) ) . '</p>';
        printf( '<h1>%s</h1>', esc_html( null !== $competition ? (string) $competition->name : __( 'Añadir competición', SULAYR_TEXTDOMAIN ) ) );
        Notices::render();
        if ( null === $competition && empty( $state ) ) {
            printf( '<p class="description">%s <a href="%s">%s</a></p>', esc_html__( '¿Tienes los PDF de la federación?', SULAYR_TEXTDOMAIN ), esc_url( ImportPage::url() ), esc_html__( 'Importa los resultados en vez de meterlos a mano.', SULAYR_TEXTDOMAIN ) );
        }

        if ( ! empty( $state['errors'] ) ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'No se ha guardado nada. Corrige los errores marcados.', SULAYR_TEXTDOMAIN ) . '</p>';
            foreach ( self::error_summary( $errors, $input['results'] ?? [] ) as $line ) {
                echo '<p>' . esc_html( $line ) . '</p>';
            }
            echo '</div>';
        }
        if ( null !== $competition && ! empty( $validation['missing'] ) ) {
            echo '<div class="notice notice-warning"><p>' . esc_html( sprintf( _n( 'Falta %d referencia para poder publicar: está marcada abajo.', 'Faltan %d referencias para poder publicar: están marcadas abajo.', count( $validation['missing'] ), SULAYR_TEXTDOMAIN ), count( $validation['missing'] ) ) ) . '</p></div>';
        }

        printf( '<form method="post" action="%s" id="sulayr-ranking-form">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d">', esc_attr( self::ACTION_SAVE ), $id );
        wp_nonce_field( self::ACTION_SAVE . '_' . $id );

        self::render_data( $input['competition'] ?? [], $errors['competition'] ?? [], null !== $competition && (int) count( $calc ) > 0 );
        self::render_results( $input['results'] ?? [], $errors['results'] ?? [], $validation['warnings'], $format, $members, $groups, $calc );
        self::render_references( $input['references'] ?? [], $errors['references'] ?? [], $validation, $format, $groups );

        echo '<p class="submit">';
        printf( '<button type="submit" name="do" value="save" class="button button-primary">%s</button> ', esc_html__( 'Guardar', SULAYR_TEXTDOMAIN ) );
        if ( null === $competition || Keys::COMPETITION_DRAFT === $competition->status ) {
            printf( '<button type="submit" name="do" value="publish" class="button button-primary sulayr-cta">%s</button>', esc_html__( 'Guardar y publicar', SULAYR_TEXTDOMAIN ) );
        } else {
            printf( '<button type="submit" name="do" value="unpublish" class="button">%s</button>', esc_html__( 'Volver a borrador', SULAYR_TEXTDOMAIN ) );
        }
        echo '</p></form>';

        if ( null !== $competition ) {
            printf( '<form method="post" action="%s" class="sulayr-ranking-delete">', esc_url( admin_url( 'admin-post.php' ) ) );
            printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="id" value="%d">', esc_attr( self::ACTION_DELETE ), $id );
            wp_nonce_field( self::ACTION_DELETE . '_' . $id );
            printf(
                '<button type="submit" class="button button-link-delete" data-sulayr-confirm="%s">%s</button></form>',
                esc_attr__( '¿Borrar la competición con todos sus resultados? Quedará copia en el registro, pero dejará de contar.', SULAYR_TEXTDOMAIN ),
                esc_html__( 'Borrar competición', SULAYR_TEXTDOMAIN )
            );
        }

        echo '<style>
            .sulayr-ranking-table td,.sulayr-ranking-table th{vertical-align:top}
            .sulayr-ranking-table input[type=text]{width:100%;min-width:60px}
            .sulayr-ranking-table .sulayr-num{width:70px}
            .sulayr-ranking-table select{max-width:100%}
            .sulayr-ranking-row-error td{box-shadow:inset 0 -2px 0 #F46F5C}
            .sulayr-ranking-missing td{background:#FDECEA!important}
            .sulayr-ranking-field-error{display:block;color:#0B1638;font-weight:600;border-left:3px solid #F46F5C;padding-left:6px;margin-top:4px;font-size:12px}
            .sulayr-ranking-warning{display:block;color:#566079;border-left:3px solid #4FE6F2;padding-left:6px;margin-top:4px;font-size:12px}
            .sulayr-ranking-meta{display:block;font-family:"JetBrains Mono",Consolas,monospace;font-size:11px;color:#566079;margin-top:4px}
            .sulayr-ranking-data th{width:180px}
            .sulayr-ranking-delete{margin-top:24px}
        </style>';
        echo '</div>';
    }

    /**
     * Bloque «Datos».
     *
     * @param   array $c          Valores.
     * @param   array $errors     Errores por campo.
     * @param   bool  $has_results Hay resultados guardados (el formato no se cambia).
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_data( array $c, array $errors, bool $has_results ): void {
        $v = function ( string $key ) use ( $c ): string {
            return (string) ( $c[ $key ] ?? '' );
        };
        $error = function ( string $key ) use ( $errors ): string {
            return isset( $errors[ $key ] ) ? '<span class="sulayr-ranking-field-error" role="alert">' . esc_html( $errors[ $key ] ) . '</span>' : '';
        };
        $select = function ( string $key, array $options ) use ( $v ): string {
            $html = sprintf( '<select id="sulayr-c-%1$s" name="competition[%1$s]" required><option value="">%2$s</option>', esc_attr( $key ), esc_html__( '— Elige —', SULAYR_TEXTDOMAIN ) );
            foreach ( $options as $value => $label ) {
                $html .= sprintf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $v( $key ), $value, false ), esc_html( $label ) );
            }
            return $html . '</select>';
        };

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Datos', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Copia los datos de la cabecera de la clasificación oficial.', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<table class="form-table sulayr-ranking-data" role="presentation"><tbody>';
        printf( '<tr><th scope="row"><label for="sulayr-c-name">%s</label></th><td><input type="text" class="regular-text" id="sulayr-c-name" name="competition[name]" value="%s" maxlength="190" required>%s</td></tr>', esc_html__( 'Nombre', SULAYR_TEXTDOMAIN ), esc_attr( $v( 'name' ) ), $error( 'name' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado en $error.
        printf( '<tr><th scope="row"><label for="sulayr-c-race_date">%s</label></th><td><input type="date" id="sulayr-c-race_date" name="competition[race_date]" value="%s" max="%s" required>%s</td></tr>', esc_html__( 'Fecha', SULAYR_TEXTDOMAIN ), esc_attr( $v( 'race_date' ) ), esc_attr( current_time( 'Y-m-d' ) ), $error( 'race_date' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        printf( '<tr><th scope="row"><label for="sulayr-c-location">%s</label></th><td><input type="text" class="regular-text" id="sulayr-c-location" name="competition[location]" value="%s" maxlength="190">%s</td></tr>', esc_html__( 'Lugar', SULAYR_TEXTDOMAIN ), esc_attr( $v( 'location' ) ), $error( 'location' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        printf( '<tr><th scope="row"><label for="sulayr-c-modality">%s</label></th><td>%s%s</td></tr>', esc_html__( 'Modalidad', SULAYR_TEXTDOMAIN ), $select( 'modality', Labels::modalities() ), $error( 'modality' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        printf( '<tr><th scope="row"><label for="sulayr-c-distance">%s</label></th><td>%s%s</td></tr>', esc_html__( 'Distancia', SULAYR_TEXTDOMAIN ), $select( 'distance', Labels::distances() ), $error( 'distance' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        printf(
            '<tr><th scope="row"><label for="sulayr-c-format">%s</label></th><td>%s%s%s</td></tr>',
            esc_html__( 'Formato', SULAYR_TEXTDOMAIN ),
            $select( 'format', Labels::formats() ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            $has_results ? '<p class="description">' . esc_html__( 'Con resultados guardados no se puede cambiar el formato. Para cambiarlo, borra antes los resultados.', SULAYR_TEXTDOMAIN ) . '</p>' : '',
            $error( 'format' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
        printf( '<tr><th scope="row"><label for="sulayr-c-notes">%s</label></th><td><input type="text" class="large-text" id="sulayr-c-notes" name="competition[notes]" value="%s" maxlength="255"><p class="description">%s</p></td></tr>', esc_html__( 'Nota interna', SULAYR_TEXTDOMAIN ), esc_attr( $v( 'notes' ) ), esc_html__( 'Solo la ven los gestores.', SULAYR_TEXTDOMAIN ) );
        echo '</tbody></table></div>';
    }

    /**
     * Bloque «Resultados»: tabla editable.
     *
     * @param   array     $rows     Filas (textos).
     * @param   array     $errors   Errores por índice.
     * @param   array     $warnings Avisos por índice.
     * @param   string    $format   Formato.
     * @param   array     $members  Socios elegibles (user_id => datos).
     * @param   AgeGroups $groups   Grupos.
     * @param   array     $calc     user_id => resultado guardado.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_results( array $rows, array $errors, array $warnings, string $format, array $members, AgeGroups $groups, array $calc ): void {
        $team = Keys::has_team( $format );
        $without_sex = array_filter( RankingRoster::members(), function ( $m ) {
            return '' === $m['sex'];
        } );

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Resultados', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Una fila por socio, con los datos de la clasificación general de su sexo: «Pos», «Cat», «Pos Cat» y tiempo. Si un socio terminó y no tiene tiempo, pídeselo; si no lo da, deja el tiempo vacío y se calcula por posición.', SULAYR_TEXTDOMAIN ) . '</p>';
        if ( ! empty( $without_sex ) ) {
            printf( '<p class="description">%s <a href="%s">%s</a></p>', esc_html( sprintf( _n( '%d socio no aparece en la lista porque no tiene sexo.', '%d socios no aparecen en la lista porque no tienen sexo.', count( $without_sex ), SULAYR_TEXTDOMAIN ), count( $without_sex ) ) ), esc_url( AthletesPage::url() ), esc_html__( 'Rellénalo en Deportistas.', SULAYR_TEXTDOMAIN ) );
        }

        $headers = [ __( 'Socio', SULAYR_TEXTDOMAIN ), __( 'Pos', SULAYR_TEXTDOMAIN ), __( 'Cat', SULAYR_TEXTDOMAIN ), __( 'Pos Cat', SULAYR_TEXTDOMAIN ), __( 'Tiempo', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ) ];
        if ( $team ) {
            $headers[] = __( 'Categoría del equipo', SULAYR_TEXTDOMAIN );
        }
        $headers[] = __( 'Puntos (absoluta / grupo)', SULAYR_TEXTDOMAIN );
        $headers[] = '';

        echo '<table class="widefat striped sulayr-ranking-table" id="sulayr-ranking-results"><thead><tr>';
        foreach ( $headers as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        // Un socio ya elegido en una fila no aparece en las demás (SPEC §4.2).
        $used = [];
        foreach ( $rows as $row ) {
            $own = (int) ( ( (array) $row )['user_id'] ?? 0 );
            if ( $own > 0 ) {
                $used[ $own ] = true;
            }
        }

        $index = 0;
        foreach ( $rows as $i => $row ) {
            $row = (array) $row;
            $own = (int) ( $row['user_id'] ?? 0 );
            self::render_result_row( (int) $i, $row, $errors[ $i ] ?? [], $warnings[ $i ] ?? [], $team, self::available_members( $members, $used, $own ), $groups, $calc );
            $index = max( $index, (int) $i + 1 );
        }
        $free = self::available_members( $members, $used, 0 );
        for ( $k = 0; $k < self::EMPTY_ROWS; $k++ ) {
            self::render_result_row( $index + $k, [], [], [], $team, $free, $groups, [] );
        }
        echo '</tbody></table>';

        // La plantilla lleva a todos los socios: ranking-results.js quita los que ya están en otra fila.
        echo '<template id="sulayr-ranking-row-template">';
        self::render_result_row( -1, [], [], [], $team, $members, $groups, [] );
        echo '</template>';
        printf( '<p><button type="button" class="button" id="sulayr-ranking-add-row" data-next="%d">%s</button></p>', $index + self::EMPTY_ROWS, esc_html__( 'Añadir fila', SULAYR_TEXTDOMAIN ) );
        echo '</div>';
    }

    /**
     * Resumen de errores por fila de resultados, para ver de un vistazo dónde está el problema (SPEC §4.2).
     *
     * @param   array $errors Errores (clave 'results' por índice de fila).
     * @param   array $rows   Filas enviadas.
     * @return 	string[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function error_summary( array $errors, array $rows ): array {
        $lines = [];
        foreach ( (array) ( $errors['results'] ?? [] ) as $index => $row_errors ) {
            if ( empty( $row_errors ) ) {
                continue;
            }
            $user_id = (int) ( ( (array) ( $rows[ $index ] ?? [] ) )['user_id'] ?? 0 );
            $who = $user_id > 0 ? RankingRoster::info( $user_id )['name'] : __( 'fila sin socio', SULAYR_TEXTDOMAIN );
            $count = count( (array) $row_errors );
            $lines[] = sprintf(
                /* translators: 1: número de errores, 2: nombre del socio de la fila */
                _n( '%1$d error en la fila de %2$s.', '%1$d errores en la fila de %2$s.', $count, SULAYR_TEXTDOMAIN ),
                $count,
                $who
            );
        }
        return $lines;
    }

    /**
     * Socios que se pueden elegir en una fila: los que no están ya en otra fila (SPEC §4.2).
     *
     * @param   array $members Socios (id => nombre).
     * @param   array $used    Ids ya usados (id => true).
     * @param   int   $own     Id elegido en esta fila (se conserva aunque esté en $used).
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function available_members( array $members, array $used, int $own ): array {
        return array_filter(
            $members,
            function ( $id ) use ( $used, $own ) {
                return ! isset( $used[ $id ] ) || (int) $id === $own;
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Una fila de la tabla de resultados.
     *
     * @param   int       $i        Índice (-1 en la plantilla).
     * @param   array     $row      Valores.
     * @param   array     $errors   Errores por campo.
     * @param   array     $warnings Avisos.
     * @param   bool      $team     Formato con categoría de equipo.
     * @param   array     $members  Socios.
     * @param   AgeGroups $groups   Grupos.
     * @param   array     $calc     user_id => resultado guardado.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_result_row( int $i, array $row, array $errors, array $warnings, bool $team, array $members, AgeGroups $groups, array $calc ): void {
        $key = $i < 0 ? '__i__' : (string) $i;
        $name = function ( string $field ) use ( $key ): string {
            return 'results[' . $key . '][' . $field . ']';
        };
        $v = function ( string $field ) use ( $row ): string {
            return (string) ( $row[ $field ] ?? '' );
        };
        $error = function ( string $field ) use ( $errors ): string {
            return isset( $errors[ $field ] ) ? '<span class="sulayr-ranking-field-error" role="alert">' . esc_html( $errors[ $field ] ) . '</span>' : '';
        };
        $text = function ( string $field, string $class, string $placeholder = '', int $max = 10 ) use ( $name, $v, $error ): string {
            return sprintf( '<input type="text" class="%s" name="%s" value="%s" placeholder="%s" maxlength="%d" aria-label="%s">%s', esc_attr( $class ), esc_attr( $name( $field ) ), esc_attr( $v( $field ) ), esc_attr( $placeholder ), $max, esc_attr( $field ), $error( $field ) );
        };

        $user_id = (int) $v( 'user_id' );
        printf( '<tr class="%s">', ! empty( $errors ) ? 'sulayr-ranking-row-error' : '' );

        // Socio.
        $select = sprintf( '<select name="%s" class="sulayr-ranking-member"><option value="">%s</option>', esc_attr( $name( 'user_id' ) ), esc_html__( '— Socio —', SULAYR_TEXTDOMAIN ) );
        foreach ( $members as $id => $m ) {
            $select .= sprintf(
                '<option value="%1$d" data-meta="%2$s"%3$s>%4$s</option>',
                (int) $id,
                esc_attr( $m['meta'] ),
                selected( $user_id, (int) $id, false ),
                esc_html( $m['label'] )
            );
        }
        $select .= '</select>';
        $meta = isset( $members[ $user_id ] ) ? $members[ $user_id ]['meta'] : '';
        printf( '<td>%s<span class="sulayr-ranking-meta" data-meta-target>%s</span>%s</td>', $select, esc_html( $meta ), $error( 'user_id' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado al construir.

        echo '<td>' . $text( 'position_sex', 'sulayr-num', '6' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $text( 'race_category', 'sulayr-num', 'V1M', 3 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $text( 'position_age_group', 'sulayr-num', '1' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '<td>' . $text( 'time', '', 'h:mm:ss', 9 ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        $status = $v( 'status' ) ?: Keys::STATUS_FINISHED;
        echo '<td><select name="' . esc_attr( $name( 'status' ) ) . '">';
        foreach ( Labels::statuses() as $value => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $status, $value, false ), esc_html( $label ) );
        }
        echo '</select>' . $error( 'status' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

        if ( $team ) {
            echo '<td><select name="' . esc_attr( $name( 'team_category' ) ) . '"><option value="">' . esc_html__( '— Elige —', SULAYR_TEXTDOMAIN ) . '</option>';
            foreach ( Labels::team_categories() as $value => $label ) {
                printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $v( 'team_category' ), $value, false ), esc_html( $label ) );
            }
            echo '</select>' . $error( 'team_category' ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }

        // Puntos guardados y avisos.
        echo '<td>';
        $saved = $calc[ $user_id ] ?? null;
        if ( null !== $saved ) {
            printf(
                '<strong class="sulayr-ranking-points">%s / %s</strong><span class="sulayr-ranking-meta">%s · ×%s</span>',
                esc_html( Labels::points( $saved->points_absolute ) ),
                esc_html( Labels::points( $saved->points_age_group ) ),
                esc_html( Labels::of( Labels::methods(), (string) $saved->method_absolute ) . ( '' !== (string) $saved->age_group ? ' · ' . $groups->label( (string) $saved->age_group ) : '' ) ),
                esc_html( Labels::coef( $saved->coefficient ) )
            );
            if ( '' !== (string) $saved->calc_note ) {
                echo '<span class="sulayr-ranking-warning">' . esc_html( (string) $saved->calc_note ) . '</span>';
            }
        } else {
            echo '—';
        }
        foreach ( $warnings as $warning ) {
            if ( null === $saved || false === strpos( (string) $saved->calc_note, $warning ) ) {
                echo '<span class="sulayr-ranking-warning">' . esc_html( $warning ) . '</span>';
            }
        }
        echo '</td>';

        printf( '<td><button type="button" class="button-link sulayr-ranking-remove" aria-label="%1$s">%2$s</button></td></tr>', esc_attr__( 'Quitar fila', SULAYR_TEXTDOMAIN ), esc_html__( 'Quitar', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Bloque «Referencias».
     *
     * @param   array     $raw        Referencias (textos) por clave.
     * @param   array     $errors     Errores por clave.
     * @param   array     $validation Resultado de ResultValidator::validate().
     * @param   string    $format     Formato.
     * @param   AgeGroups $groups     Grupos.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_references( array $raw, array $errors, array $validation, string $format, AgeGroups $groups ): void {
        $required = $validation['required'];
        $missing = array_flip( $validation['missing'] );

        // Filas: las obligatorias, las que ya tienen datos y, en individual, siempre los dos ganadores absolutos.
        $rows = [];
        if ( Keys::FORMAT_INDIVIDUAL === $format ) {
            foreach ( Keys::SEXES as $sex ) {
                $rows[ Keys::ref_key( Keys::SCOPE_ABSOLUTE, '', $sex, '' ) ] = [ 'scope' => Keys::SCOPE_ABSOLUTE, 'team_category' => '', 'sex' => $sex, 'age_group' => '' ];
            }
        }
        foreach ( $required as $key => $need ) {
            $rows[ $key ] = $need;
        }
        foreach ( $raw as $key => $ref ) {
            if ( '' !== trim( (string) ( $ref['winner_time'] ?? '' ) . (string) ( $ref['participants'] ?? '' ) ) && ! isset( $rows[ $key ] ) ) {
                $rows[ $key ] = $ref;
            }
        }
        $order = array_flip( array_keys( $groups->options() ) );
        uksort( $rows, function ( $a, $b ) use ( $rows, $order ) {
            $ra = $rows[ $a ];
            $rb = $rows[ $b ];
            return [ $ra['scope'], $ra['team_category'], $ra['sex'], $order[ $ra['age_group'] ] ?? -1 ] <=> [ $rb['scope'], $rb['team_category'], $rb['sex'], $order[ $rb['age_group'] ] ?? -1 ];
        } );

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Referencias', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Ganador de cada sexo y «FINALIZADOS», de la clasificación general. Primero de cada categoría con socios, de la clasificación por categorías. Los finalizados de una categoría solo hacen falta si algún socio de esa categoría no tiene tiempo. Las filas se completan al guardar los resultados.', SULAYR_TEXTDOMAIN ) . '</p>';

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Guarda primero los resultados: aquí aparecerán las referencias que hacen falta.', SULAYR_TEXTDOMAIN ) . '</p></div>';
            return;
        }

        printf(
            '<table class="widefat striped sulayr-ranking-table"><thead><tr><th scope="col">%s</th><th scope="col">%s</th><th scope="col">%s</th></tr></thead><tbody>',
            esc_html__( 'Referencia', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Tiempo del primero', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Finalizados', SULAYR_TEXTDOMAIN )
        );
        foreach ( $rows as $key => $ref ) {
            $values = $raw[ $key ] ?? [];
            $need = $required[ $key ] ?? null;
            $err = $errors[ $key ] ?? [];
            $field_error = function ( string $field ) use ( $err ): string {
                return isset( $err[ $field ] ) ? '<span class="sulayr-ranking-field-error" role="alert">' . esc_html( $err[ $field ] ) . '</span>' : '';
            };
            $base = 'references[' . $key . ']';

            printf( '<tr class="%s"><td>', isset( $missing[ $key ] ) ? 'sulayr-ranking-missing' : '' );
            foreach ( [ 'scope', 'team_category', 'sex', 'age_group' ] as $part ) {
                printf( '<input type="hidden" name="%s[%s]" value="%s">', esc_attr( $base ), esc_attr( $part ), esc_attr( (string) $ref[ $part ] ) );
            }
            echo esc_html( self::reference_label( $ref, $format, $groups ) );
            if ( isset( $missing[ $key ] ) ) {
                echo '<span class="sulayr-ranking-field-error">' . esc_html__( 'Falta para publicar', SULAYR_TEXTDOMAIN ) . '</span>';
            }
            echo '</td>';
            printf(
                '<td><input type="text" class="sulayr-num" name="%s[winner_time]" value="%s" placeholder="h:mm:ss" maxlength="9" aria-label="%s">%s</td>',
                esc_attr( $base ),
                esc_attr( (string) ( $values['winner_time'] ?? '' ) ),
                esc_attr__( 'Tiempo del primero', SULAYR_TEXTDOMAIN ),
                $field_error( 'winner_time' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            );
            printf(
                '<td><input type="text" class="sulayr-num" name="%s[participants]" value="%s" maxlength="6" aria-label="%s">%s%s</td></tr>',
                esc_attr( $base ),
                esc_attr( (string) ( $values['participants'] ?? '' ) ),
                esc_attr__( 'Finalizados', SULAYR_TEXTDOMAIN ),
                null !== $need && $need['participants_required'] ? '<span class="sulayr-ranking-meta">' . esc_html__( 'obligatorio', SULAYR_TEXTDOMAIN ) . '</span>' : '',
                $field_error( 'participants' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            );
        }
        echo '</tbody></table></div>';
    }

    /**
     * Nombre visible de una referencia.
     *
     * @param   array     $ref    Referencia.
     * @param   string    $format Formato.
     * @param   AgeGroups $groups Grupos.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function reference_label( array $ref, string $format, AgeGroups $groups ): string {
        $prefix = '' !== (string) $ref['team_category'] ? Labels::of( Labels::team_categories(), (string) $ref['team_category'] ) . ' · ' : '';
        if ( Keys::FORMAT_TEAM === $format ) {
            return $prefix . __( 'Equipo ganador', SULAYR_TEXTDOMAIN );
        }

        $female = Keys::SEX_FEMALE === $ref['sex'];
        if ( Keys::SCOPE_ABSOLUTE === $ref['scope'] ) {
            return $prefix . ( $female ? __( 'Ganadora femenina', SULAYR_TEXTDOMAIN ) : __( 'Ganador masculino', SULAYR_TEXTDOMAIN ) );
        }

        /* translators: %s: grupo de edad. */
        return $prefix . sprintf( $female ? __( 'Primera de %s femenina', SULAYR_TEXTDOMAIN ) : __( 'Primero de %s masculino', SULAYR_TEXTDOMAIN ), $groups->label( (string) $ref['age_group'] ) );
    }

    /**
     * Guardar (y publicar o volver a borrador).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_save(): void {
        global $wpdb;

        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        check_admin_referer( self::ACTION_SAVE . '_' . $id );
        Notices::require_cap();

        $competition = $id > 0 ? Competitions::get( $id ) : null;
        if ( $id > 0 && null === $competition ) {
            Notices::redirect( RankingMenu::url(), 'error', __( 'Competición no encontrada.', SULAYR_TEXTDOMAIN ) );
        }

        $input = [
            'competition' => self::clean( (array) wp_unslash( $_POST['competition'] ?? [] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- clean() limpia cada valor.
            'references' => self::clean( (array) wp_unslash( $_POST['references'] ?? [] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            'results' => self::clean( (array) wp_unslash( $_POST['results'] ?? [] ) ), // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        ];
        unset( $input['results']['__i__'] );
        $do = sanitize_key( wp_unslash( $_POST['do'] ?? 'save' ) );

        $season = self::season_of( (string) ( $input['competition']['race_date'] ?? '' ) );
        $groups = RankingSettings::groups( $season );
        $v = ResultValidator::validate( $input, self::member_map( self::members( $groups, $season, $id ) ), $groups, current_time( 'Y-m-d' ) );
        $errors = $v['errors'];

        // Con resultados guardados, el formato no se cambia (SPEC §4.2).
        if ( null !== $competition && (string) $competition->format !== (string) $v['competition']['format'] && ! empty( Results::for_competition( $id ) ) ) {
            $errors['competition']['format'] = __( 'Para cambiar a este formato, borra antes los resultados.', SULAYR_TEXTDOMAIN );
        }

        if ( ResultValidator::has_errors( $errors ) ) {
            Notices::save_state( [ 'input' => $input, 'errors' => $errors ] );
            Notices::redirect( self::url( $id ), 'error', __( 'Hay errores: no se ha guardado nada.', SULAYR_TEXTDOMAIN ) );
        }

        // Todo o nada: competición, referencias, resultados, puntos y estado van en una transacción.
        $wpdb->query( 'START TRANSACTION' );
        if ( null === $competition ) {
            $id = Competitions::insert( $v['competition'] );
            $ok = $id > 0;
        } else {
            $ok = Competitions::update( $id, $v['competition'] );
        }
        $ok = $ok && Competitions::save_references( $id, $v['references'] );
        $ok = $ok && Results::save_rows( $id, array_values( $v['results'] ) );
        $ok = $ok && RankingService::recalculate_competition( $id ) >= 0;
        if ( false === $ok ) {
            self::abort_save( $id, $input );
        }

        $count = count( $v['results'] );
        $current = Competitions::get( $id );
        $type = 'success';
        /* translators: %d: resultados. */
        $text = sprintf( _n( 'Competición guardada: %d resultado.', 'Competición guardada: %d resultados.', $count, SULAYR_TEXTDOMAIN ), $count );

        if ( 'publish' === $do ) {
            if ( 0 === $count ) {
                $type = 'warning';
                $text .= ' ' . __( 'No se ha publicado: no tiene resultados.', SULAYR_TEXTDOMAIN );
            } elseif ( ! empty( $v['missing'] ) ) {
                $type = 'warning';
                $text .= ' ' . __( 'No se ha publicado: faltan referencias.', SULAYR_TEXTDOMAIN );
            } else {
                $ok = Competitions::set_status( $id, Keys::COMPETITION_PUBLISHED );
                $text .= ' ' . __( 'Publicada: ya se ve en Mi cuenta.', SULAYR_TEXTDOMAIN );
            }
        } elseif ( 'unpublish' === $do ) {
            $ok = Competitions::set_status( $id, Keys::COMPETITION_DRAFT );
            $text .= ' ' . __( 'Ha vuelto a borrador: ya no cuenta ni se ve.', SULAYR_TEXTDOMAIN );
        } elseif ( null !== $current && Keys::COMPETITION_PUBLISHED === $current->status && ( ! empty( $v['missing'] ) || 0 === $count ) ) {
            $ok = Competitions::set_status( $id, Keys::COMPETITION_DRAFT );
            $type = 'warning';
            $text .= ' ' . __( 'Ha vuelto a borrador porque faltan referencias o resultados.', SULAYR_TEXTDOMAIN );
        }
        if ( false === $ok ) {
            self::abort_save( $id, $input );
        }

        $wpdb->query( 'COMMIT' );
        RankingService::bump_cache();
        Notices::redirect( self::url( $id ), $type, $text );
    }

    /**
     * Borrar la competición.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_delete(): void {
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        check_admin_referer( self::ACTION_DELETE . '_' . $id );
        Notices::require_cap();

        $competition = Competitions::get( $id );
        if ( null === $competition ) {
            Notices::redirect( RankingMenu::url(), 'error', __( 'Competición no encontrada.', SULAYR_TEXTDOMAIN ) );
        }

        Competitions::delete( $id );
        RankingService::bump_cache();
        Notices::redirect( RankingMenu::url( (int) $competition->season ), 'success', sprintf( __( 'Competición «%s» borrada. Queda copia en el registro.', SULAYR_TEXTDOMAIN ), $competition->name ) );
    }

    /**
     * Deshace la transacción y vuelve al formulario con los datos enviados. No se guarda nada.
     *
     * @param   int   $id    Competición (0 si era nueva).
     * @param   array $input Datos enviados.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function abort_save( int $id, array $input ): void {
        global $wpdb;

        $wpdb->query( 'ROLLBACK' );
        Notices::save_state( [ 'input' => $input, 'errors' => [] ] );
        Notices::redirect( self::url( $id ), 'error', __( 'No se ha guardado nada: falló la base de datos. Revisa los datos e inténtalo de nuevo.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Datos del formulario a partir de lo guardado.
     *
     * @param   object|null $competition Competición.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function input_from_db( ?object $competition ): array {
        if ( null === $competition ) {
            return [ 'competition' => [ 'format' => Keys::FORMAT_INDIVIDUAL, 'distance' => 'sprint' ], 'references' => [], 'results' => [] ];
        }

        $input = [ 'competition' => [], 'references' => [], 'results' => [] ];
        foreach ( Competitions::FIELDS as $field ) {
            $input['competition'][ $field ] = (string) $competition->$field;
        }
        foreach ( Competitions::references( (int) $competition->id ) as $key => $ref ) {
            $input['references'][ $key ] = [
                'scope' => $ref->scope,
                'team_category' => $ref->team_category,
                'sex' => $ref->sex,
                'age_group' => $ref->age_group,
                'winner_time' => RaceTime::format( $ref->winner_time ),
                'participants' => null === $ref->participants ? '' : (string) $ref->participants,
            ];
        }
        foreach ( Results::for_competition( (int) $competition->id ) as $row ) {
            $input['results'][] = [
                'user_id' => (string) $row->user_id,
                'status' => $row->status,
                'time' => RaceTime::format( $row->time_s ),
                'race_category' => $row->race_category,
                'position_sex' => null === $row->position_sex ? '' : (string) $row->position_sex,
                'position_age_group' => null === $row->position_age_group ? '' : (string) $row->position_age_group,
                'team_category' => $row->team_category,
            ];
        }

        return $input;
    }

    /**
     * Socios del desplegable: los que salen en el ranking con sexo y, además,
     * los que ya tienen resultado en esta competición (p. ej. de baja), para no perderlo.
     *
     * @param   AgeGroups $groups         Grupos.
     * @param   int       $season         Año.
     * @param   int       $competition_id Competición (0 = nueva).
     * @return 	array<int, array{sex: string, group: string, label: string, meta: string}>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function members( AgeGroups $groups, int $season, int $competition_id ): array {
        $ids = array_keys( RankingRoster::selectable( $groups, $season ) );
        if ( $competition_id > 0 ) {
            foreach ( Results::for_competition( $competition_id ) as $row ) {
                $ids[] = (int) $row->user_id;
            }
        }

        $out = [];
        foreach ( array_unique( $ids ) as $id ) {
            $info = RankingRoster::info( (int) $id );
            if ( '' === $info['sex'] ) {
                continue;
            }
            $group = $groups->for_birth( $info['birth'], $season );
            $hidden = false === RankingRoster::is_visible( (int) $id );
            $out[ (int) $id ] = [
                'sex' => $info['sex'],
                'group' => $group,
                'sort' => $info['sort'],
                'label' => $info['name'] . ( '' !== $info['number'] ? ' (nº ' . $info['number'] . ')' : '' ) . ( $hidden ? ' — ' . __( 'de baja', SULAYR_TEXTDOMAIN ) : '' ),
                'meta' => Labels::of( Labels::sexes(), $info['sex'] ) . ' · ' . ( '' !== $group ? $groups->label( $group ) : __( 'sin grupo', SULAYR_TEXTDOMAIN ) ),
            ];
        }
        uasort( $out, function ( $a, $b ) {
            return strcmp( $a['sort'], $b['sort'] );
        } );

        return $out;
    }

    /**
     * Socios en el formato de ResultValidator.
     *
     * @param   array $members De members().
     * @return 	array<int, array{sex: string, group: string}>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function member_map( array $members ): array {
        return array_map( function ( $m ) {
            return [ 'sex' => $m['sex'], 'group' => $m['group'] ];
        }, $members );
    }

    /**
     * Temporada de una fecha; la en curso si la fecha no vale.
     *
     * @param   string $date Y-m-d.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function season_of( string $date ): int {
        return 1 === preg_match( '/^(\d{4})-\d{2}-\d{2}$/', $date, $m ) ? (int) $m[1] : RankingSettings::current_season();
    }

    /**
     * Limpia un array de textos del formulario (recursivo).
     *
     * @param   array $data Datos.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function clean( array $data ): array {
        $out = [];
        foreach ( $data as $key => $value ) {
            $key = is_int( $key ) ? $key : preg_replace( '/[^a-z0-9_|\-]/i', '', (string) $key );
            $out[ $key ] = is_array( $value ) ? self::clean( $value ) : sanitize_text_field( (string) $value );
        }

        return $out;
    }
}
