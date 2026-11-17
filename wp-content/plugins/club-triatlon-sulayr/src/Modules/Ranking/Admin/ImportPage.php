<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Admin;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportMerger;
use ClubTriatlonSulayr\Modules\Ranking\Calc\ImportParser;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\NameMatcher;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use ClubTriatlonSulayr\Modules\Ranking\Competitions;
use ClubTriatlonSulayr\Modules\Ranking\Labels;
use ClubTriatlonSulayr\Modules\Ranking\RankingRoster;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Ranking → Importar resultados (SPEC-RANKING-INTERNO §14).
 *
 * Tres pasos en la misma pantalla:
 * 1. Instrucciones y el JSON (pegado o subido). «Analizar» lo lee, unifica las
 *    clasificaciones y empareja a los deportistas con los socios por nombre.
 * 2. Revisión: los emparejamientos seguros vienen elegidos; las dudas hay que decidirlas.
 * 3. «Rellenar la competición» abre «Añadir competición» con todo relleno y sin guardar.
 *    El gestor revisa y guarda allí, con las mismas validaciones de siempre.
 *
 * La importación en curso se guarda en un transient por usuario.
 */
class ImportPage {

    public const SLUG = 'sulayr-ranking-importar';
    private const ACTION_ANALYSE = 'sulayr_ranking_import_analyse';
    private const ACTION_APPLY = 'sulayr_ranking_import_apply';
    private const ACTION_RESET = 'sulayr_ranking_import_reset';
    private const TRANSIENT = 'sulayr_ranking_import_';
    private const TTL = 2 * HOUR_IN_SECONDS;
    private const MAX_BYTES = 2 * MB_IN_BYTES;

    /** Valor del desplegable «No es socio». */
    private const NOT_MEMBER = '0';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION_ANALYSE, [ $this, 'handle_analyse' ] );
        add_action( 'admin_post_' . self::ACTION_APPLY, [ $this, 'handle_apply' ] );
        add_action( 'admin_post_' . self::ACTION_RESET, [ $this, 'handle_reset' ] );
    }

    /**
     * URL de la pantalla.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url(): string {
        return add_query_arg( [ 'page' => self::SLUG ], admin_url( 'admin.php' ) );
    }

    /**
     * Submenú «Importar resultados».
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( RankingMenu::SLUG, __( 'Importar resultados', SULAYR_TEXTDOMAIN ), __( 'Importar resultados', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_RANKING, self::SLUG, [ $this, 'render' ] );
    }

    /**
     * Textos que identifican un equipo del club en la columna «Club» de la clasificación.
     *
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function club_keywords(): array {
        return (array) apply_filters( 'sulayr_ranking_import_club_keywords', [ 'SULAYR' ] );
    }

    /**
     * Prompt para la IA (el mismo que usa la skill /ranking-importar).
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function prompt(): string {
        $file = SULAYR_DIR . 'admin/ranking-import-prompt.md';

        return is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Archivo local del plugin.
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
        $data = self::load();

        echo '<div class="wrap sulayr-brand">';
        printf( '<p><a href="%s">← %s</a></p>', esc_url( RankingMenu::url() ), esc_html__( 'Volver a competiciones', SULAYR_TEXTDOMAIN ) );
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Ranking interno', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<h1>' . esc_html__( 'Importar resultados', SULAYR_TEXTDOMAIN ) . '</h1>';
        Notices::render();

        if ( null === $data ) {
            self::render_start();
        } else {
            self::render_review( $data );
        }

        echo '<style>
            .sulayr-import-steps li{margin-bottom:8px}
            .sulayr-import-prompt{width:100%;min-height:220px;font-family:"JetBrains Mono",Consolas,monospace;font-size:12px}
            .sulayr-import-json{width:100%;min-height:260px;font-family:"JetBrains Mono",Consolas,monospace;font-size:12px}
            .sulayr-import-table td,.sulayr-import-table th{vertical-align:top}
            .sulayr-import-table select{max-width:340px}
            .sulayr-import-doubt td{background:#FFF8E5!important}
            .sulayr-import-meta{display:block;font-size:12px;color:#566079;margin-top:4px}
            .sulayr-import-errors li{margin:2px 0}
            .sulayr-import-rules li{margin-bottom:4px}
        </style>';
        echo '</div>';
    }

    /**
     * Paso 1: instrucciones y JSON.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_start(): void {
        $state = Notices::take_state();
        $json = (string) ( $state['json'] ?? '' );
        $errors = (array) ( $state['import_errors'] ?? [] );

        if ( ! empty( $errors ) ) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'El JSON tiene errores. No se ha importado nada.', SULAYR_TEXTDOMAIN ) . '</strong></p><ul class="sulayr-import-errors">';
            foreach ( array_slice( $errors, 0, 40 ) as $error ) {
                echo '<li>' . esc_html( (string) $error ) . '</li>';
            }
            if ( count( $errors ) > 40 ) {
                echo '<li>' . esc_html( sprintf( __( '… y %d más.', SULAYR_TEXTDOMAIN ), count( $errors ) - 40 ) ) . '</li>';
            }
            echo '</ul></div>';
        }

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Cómo se importa una competición', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<ol class="sulayr-import-steps">';
        echo '<li>' . wp_kses( __( '<strong>Descarga los PDF oficiales</strong> de la clasificación desde la web de la federación. Todos los que tengan socios: en individual, la general masculina y la femenina; en relevos, todas (federado y open, masculina, femenina y mixta).', SULAYR_TEXTDOMAIN ), [ 'strong' => [] ] ) . '</li>';
        echo '<li>' . wp_kses( __( '<strong>Conviértelos a JSON con Claude.</strong> Lo mejor es la skill <code>ranking-importar</code> (app de escritorio, claude.ai o Claude Code): pásale los PDF, adjuntos o con su ruta, y te devuelve <code>resultados.json</code> ya comprobado para descargar. Sin la skill, abre una conversación nueva en claude.ai, adjunta los PDF y pega el prompt de abajo.', SULAYR_TEXTDOMAIN ), [ 'strong' => [], 'code' => [] ] ) . '</li>';
        echo '<li>' . wp_kses( __( '<strong>Pega el JSON</strong> (o sube el archivo) y pulsa «Analizar». Se empareja a cada deportista con los socios por nombre, sexo y categoría.', SULAYR_TEXTDOMAIN ), [ 'strong' => [] ] ) . '</li>';
        echo '<li>' . wp_kses( __( '<strong>Revisa las dudas</strong>: para cada una, elige el socio que corresponde o «No es socio».', SULAYR_TEXTDOMAIN ), [ 'strong' => [] ] ) . '</li>';
        echo '<li>' . wp_kses( __( '<strong>Pulsa «Rellenar la competición».</strong> Se abre «Añadir competición» con los datos, los resultados y las referencias rellenos, pero sin guardar. Revísalo, pulsa «Guardar», mira los puntos y, si todo está bien, «Guardar y publicar».', SULAYR_TEXTDOMAIN ), [ 'strong' => [] ] ) . '</li>';
        echo '</ol>';

        echo '<h3>' . esc_html__( 'Reglas que aplica la importación', SULAYR_TEXTDOMAIN ) . '</h3><ul class="sulayr-import-rules">';
        echo '<li>' . esc_html__( 'Para el ranking interno se juntan todas las clasificaciones de la misma categoría de equipo y el mismo sexo (por ejemplo, federado y open). El ganador es el tiempo más rápido de todas ellas.', SULAYR_TEXTDOMAIN ) . '</li>';
        echo '<li>' . esc_html__( '«Pos» y «Pos Cat» se recalculan por tiempo dentro de ese conjunto, y los finalizados son la suma de todas.', SULAYR_TEXTDOMAIN ) . '</li>';
        echo '<li>' . esc_html__( 'El primero de cada grupo de edad es el más rápido con el código de la federación de ese grupo (Ranking → Ajustes → Grupos de edad).', SULAYR_TEXTDOMAIN ) . '</li>';
        echo '<li>' . esc_html( sprintf( __( 'Se consideran equipos del club los que llevan «%s» en el nombre. A sus deportistas se les busca socio aunque el nombre se parezca poco.', SULAYR_TEXTDOMAIN ), implode( '», «', self::club_keywords() ) ) ) . '</li>';
        echo '<li>' . esc_html__( 'La contrarreloj por equipos todavía no se puede importar: métela a mano.', SULAYR_TEXTDOMAIN ) . '</li>';
        echo '</ul></div>';

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Prompt para Claude', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Cópialo tal cual y pégalo en claude.ai junto con los PDF.', SULAYR_TEXTDOMAIN ) . '</p>';
        printf( '<textarea id="sulayr-import-prompt" class="sulayr-import-prompt" readonly>%s</textarea>', esc_textarea( self::prompt() ) );
        printf( '<p><button type="button" class="button" id="sulayr-import-copy">%s</button> <span id="sulayr-import-copied" aria-live="polite"></span></p>', esc_html__( 'Copiar el prompt', SULAYR_TEXTDOMAIN ) );
        echo '</div>';

        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'JSON con los resultados', SULAYR_TEXTDOMAIN ) . '</h2>';
        printf( '<form method="post" action="%s" enctype="multipart/form-data">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION_ANALYSE ) );
        wp_nonce_field( self::ACTION_ANALYSE );
        printf( '<p><label for="sulayr-import-json">%s</label></p>', esc_html__( 'Pega aquí el JSON:', SULAYR_TEXTDOMAIN ) );
        printf( '<textarea id="sulayr-import-json" name="json" class="sulayr-import-json" spellcheck="false">%s</textarea>', esc_textarea( $json ) );
        printf( '<p><label for="sulayr-import-file">%s</label> <input type="file" id="sulayr-import-file" name="json_file" accept=".json,application/json"></p>', esc_html__( 'O sube el archivo .json:', SULAYR_TEXTDOMAIN ) );
        printf( '<p class="submit"><button type="submit" class="button button-primary">%s</button></p></form></div>', esc_html__( 'Analizar', SULAYR_TEXTDOMAIN ) );

        ?>
        <script>
        ( function () {
            var button = document.getElementById( 'sulayr-import-copy' );
            var area = document.getElementById( 'sulayr-import-prompt' );
            var out = document.getElementById( 'sulayr-import-copied' );
            if ( ! button || ! area ) {
                return;
            }
            button.addEventListener( 'click', function () {
                var done = function () { out.textContent = <?php echo wp_json_encode( __( 'Copiado.', SULAYR_TEXTDOMAIN ) ); ?>; };
                if ( navigator.clipboard && window.isSecureContext ) {
                    navigator.clipboard.writeText( area.value ).then( done );
                } else {
                    area.select();
                    document.execCommand( 'copy' );
                    done();
                }
            } );
        } )();
        </script>
        <?php
    }

    /**
     * Paso 2: revisión de los emparejamientos.
     *
     * @param   array $data Importación en curso.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_review( array $data ): void {
        $parsed = $data['parsed'];
        $merged = $data['merged'];
        $matches = $data['matches'];
        $c = $parsed['competition'];
        $season = (int) $data['season'];
        $groups = RankingSettings::groups( $season );
        $members = self::selectable_members( $season );
        $assign = (array) ( $data['assign'] ?? [] );

        if ( ! empty( $data['applied'] ) ) {
            echo '<div class="notice notice-info"><p>' . esc_html__( 'Ya rellenaste una competición con esta importación. Si no llegaste a guardarla, puedes volver a rellenarla desde aquí. Si ya la guardaste, pulsa «Descartar» para importar otra.', SULAYR_TEXTDOMAIN ) . '</p></div>';
        }

        // Datos y clasificaciones.
        echo '<div class="sulayr-brand-block"><h2>' . esc_html( $c['name'] ) . '</h2>';
        printf(
            '<p>%s · %s · %s</p>',
            esc_html( date_i18n( 'd/m/Y', strtotime( $c['race_date'] ) ) ),
            esc_html( '' !== $c['location'] ? $c['location'] : '—' ),
            esc_html( Labels::of( Labels::modalities(), $c['modality'] ) . ' · ' . Labels::of( Labels::distances(), $c['distance'] ) . ' · ' . Labels::of( Labels::formats(), $c['format'] ) )
        );

        foreach ( Competitions::for_season( $season ) as $existing ) {
            if ( (string) $existing->race_date === $c['race_date'] ) {
                printf(
                    '<div class="notice notice-warning inline"><p>%s <a href="%s">%s</a></p></div>',
                    esc_html__( 'Ya hay una competición con esa fecha:', SULAYR_TEXTDOMAIN ),
                    esc_url( CompetitionEditPage::url( (int) $existing->id ) ),
                    esc_html( (string) $existing->name )
                );
            }
        }

        echo '<table class="widefat striped sulayr-import-table"><thead><tr>';
        foreach ( [ __( 'Conjunto del ranking', SULAYR_TEXTDOMAIN ), __( 'Clasificaciones del PDF', SULAYR_TEXTDOMAIN ), __( 'Deportistas', SULAYR_TEXTDOMAIN ), __( 'Finalizados', SULAYR_TEXTDOMAIN ), __( 'Ganador', SULAYR_TEXTDOMAIN ) ] as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';
        foreach ( $merged['groups'] as $g ) {
            $titles = array_map( function ( $index ) use ( $parsed ) {
                return $parsed['classifications'][ $index ]['title'];
            }, $g['sources'] );
            printf(
                '<tr><td>%s</td><td>%s</td><td>%d</td><td>%d</td><td>%s</td></tr>',
                esc_html( self::group_label( $g['team_category'], $g['sex'] ) ),
                esc_html( implode( ' + ', $titles ) ),
                (int) $g['athletes'],
                (int) $g['participants'],
                esc_html( null !== $g['winner'] ? RaceTime::format( $g['winner'] ) : '—' )
            );
        }
        echo '</tbody></table>';

        if ( ! empty( $merged['warnings'] ) ) {
            echo '<div class="notice notice-warning inline"><ul class="sulayr-import-errors">';
            foreach ( $merged['warnings'] as $warning ) {
                echo '<li>' . esc_html( $warning ) . '</li>';
            }
            echo '</ul></div>';
        }
        echo '</div>';

        // Filas que interesan: dudas, seguros y deportistas de equipos del club sin socio.
        $doubts = [];
        $sure = [];
        $club_none = [];
        $ignored = 0;
        foreach ( $matches as $index => $match ) {
            if ( NameMatcher::DOUBT === $match['status'] ) {
                $doubts[] = $index;
            } elseif ( NameMatcher::SURE === $match['status'] ) {
                $sure[] = $index;
            } elseif ( $match['club_team'] ) {
                $club_none[] = $index;
            } else {
                $ignored++;
            }
        }

        printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION_APPLY ) );
        wp_nonce_field( self::ACTION_APPLY );

        // Bonus por interés del club (§15.4): lo decide el club, no viene en el JSON.
        $interest = (array) ( $data['interest'] ?? [] );
        echo '<div class="sulayr-brand-block"><h2>' . esc_html__( 'Bonus por interés del club', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<table class="form-table sulayr-ranking-data" role="presentation"><tbody>';
        CompetitionEditPage::render_interest( $interest, [], RankingSettings::interest_values( $season ), 'interest' );
        echo '</tbody></table></div>';

        self::render_match_table(
            sprintf( __( 'Dudas (%d)', SULAYR_TEXTDOMAIN ), count( $doubts ) ),
            __( 'Se parecen a algún socio, pero no lo bastante. Elige el socio que corresponde o «No es socio».', SULAYR_TEXTDOMAIN ),
            $doubts,
            $data,
            $members,
            $assign,
            ''
        );
        self::render_match_table(
            sprintf( __( 'Socios encontrados (%d)', SULAYR_TEXTDOMAIN ), count( $sure ) ),
            __( 'Coinciden el nombre, el sexo y la categoría. Si alguno no es, cámbialo.', SULAYR_TEXTDOMAIN ),
            $sure,
            $data,
            $members,
            $assign,
            'first'
        );
        self::render_match_table(
            sprintf( __( 'De equipos del club, sin socio (%d)', SULAYR_TEXTDOMAIN ), count( $club_none ) ),
            __( 'Corren en un equipo del club, pero su nombre no se parece al de ningún socio. Si alguno es socio, elígelo.', SULAYR_TEXTDOMAIN ),
            $club_none,
            $data,
            $members,
            $assign,
            self::NOT_MEMBER
        );

        echo '<p class="description">' . esc_html( sprintf( _n( '%d deportista de otros equipos no se parece a ningún socio y no se muestra.', '%d deportistas de otros equipos no se parecen a ningún socio y no se muestran.', $ignored, SULAYR_TEXTDOMAIN ), $ignored ) ) . '</p>';
        $without_sex = count( RankingRoster::members() ) - count( $members );
        if ( $without_sex > 0 ) {
            printf( '<p class="description">%s <a href="%s">%s</a></p>', esc_html( sprintf( _n( '%d socio no se puede emparejar porque no tiene sexo.', '%d socios no se pueden emparejar porque no tienen sexo.', $without_sex, SULAYR_TEXTDOMAIN ), $without_sex ) ), esc_url( AthletesPage::url() ), esc_html__( 'Rellénalo en Deportistas.', SULAYR_TEXTDOMAIN ) );
        }

        printf( '<p class="submit"><button type="submit" class="button button-primary sulayr-cta">%s</button></p></form>', esc_html__( 'Rellenar la competición', SULAYR_TEXTDOMAIN ) );

        printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION_RESET ) );
        wp_nonce_field( self::ACTION_RESET );
        printf( '<button type="submit" class="button">%s</button></form>', esc_html__( 'Descartar e importar otro JSON', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Una tabla de emparejamientos.
     *
     * @param   string $title   Título.
     * @param   string $help    Explicación.
     * @param   int[]  $indexes Deportistas.
     * @param   array  $data    Importación.
     * @param   array  $members Socios elegibles (id => datos).
     * @param   array  $assign  Elecciones anteriores (índice => valor).
     * @param   string $default '' (hay que elegir), 'first' (el primer candidato) o NOT_MEMBER.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function render_match_table( string $title, string $help, array $indexes, array $data, array $members, array $assign, string $default ): void {
        if ( empty( $indexes ) ) {
            return;
        }

        $athletes = $data['merged']['athletes'];
        $classifications = $data['parsed']['classifications'];

        echo '<div class="sulayr-brand-block"><h2>' . esc_html( $title ) . '</h2>';
        echo '<p class="description">' . esc_html( $help ) . '</p>';
        echo '<table class="widefat striped sulayr-import-table"><thead><tr>';
        foreach ( [ __( 'En el PDF', SULAYR_TEXTDOMAIN ), __( 'Equipo', SULAYR_TEXTDOMAIN ), __( 'Cat', SULAYR_TEXTDOMAIN ), __( 'Tiempo', SULAYR_TEXTDOMAIN ), __( 'Socio', SULAYR_TEXTDOMAIN ) ] as $th ) {
            echo '<th scope="col">' . esc_html( $th ) . '</th>';
        }
        echo '</tr></thead><tbody>';

        foreach ( $indexes as $index ) {
            $a = $athletes[ $index ];
            $match = $data['matches'][ $index ];
            $candidates = [];
            foreach ( $match['candidates'] as $candidate ) {
                if ( isset( $members[ $candidate['user_id'] ] ) ) {
                    $candidates[ (int) $candidate['user_id'] ] = $candidate['reason'];
                }
            }

            if ( isset( $assign[ $index ] ) ) {
                $selected = (string) $assign[ $index ];
            } elseif ( 'first' === $default && ! empty( $candidates ) ) {
                $selected = (string) array_key_first( $candidates );
            } else {
                $selected = '' === $default || 'first' === $default ? '' : $default;
            }

            $time = Keys::STATUS_FINISHED === $a['status'] ? ( null !== $a['time_s'] ? RaceTime::format( $a['time_s'] ) : '—' ) : strtoupper( $a['status'] );
            printf(
                '<tr class="%1$s"><td><strong>%2$s</strong><span class="sulayr-import-meta">%3$s</span></td><td>%4$s</td><td>%5$s</td><td>%6$s</td><td>',
                NameMatcher::DOUBT === $match['status'] ? 'sulayr-import-doubt' : '',
                esc_html( $a['name'] ),
                esc_html( $classifications[ $a['classification'] ]['title'] . ( null !== $a['pos'] ? ' · ' . sprintf( __( 'Pos %d en el ranking', SULAYR_TEXTDOMAIN ), $a['pos'] ) : '' ) ),
                esc_html( $a['club'] ),
                esc_html( $a['cat'] ),
                esc_html( $time )
            );

            printf( '<select name="assign[%d]" aria-label="%s"%s>', (int) $index, esc_attr( sprintf( __( 'Socio para %s', SULAYR_TEXTDOMAIN ), $a['name'] ) ), NameMatcher::DOUBT === $match['status'] ? ' required' : '' );
            if ( NameMatcher::DOUBT === $match['status'] ) {
                printf( '<option value=""%s>%s</option>', selected( $selected, '', false ), esc_html__( '— Elige —', SULAYR_TEXTDOMAIN ) );
            }
            foreach ( $candidates as $user_id => $reason ) {
                printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $user_id, selected( $selected, (string) $user_id, false ), esc_html( $members[ $user_id ]['label'] ) );
            }
            printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( self::NOT_MEMBER ), selected( $selected, self::NOT_MEMBER, false ), esc_html__( 'No es socio', SULAYR_TEXTDOMAIN ) );
            echo '<optgroup label="' . esc_attr__( 'Otro socio', SULAYR_TEXTDOMAIN ) . '">';
            foreach ( $members as $user_id => $m ) {
                if ( ! isset( $candidates[ $user_id ] ) && $m['sex'] === $a['sex'] ) {
                    printf( '<option value="%1$d"%2$s>%3$s</option>', (int) $user_id, selected( $selected, (string) $user_id, false ), esc_html( $m['label'] ) );
                }
            }
            echo '</optgroup></select>';

            foreach ( $candidates as $user_id => $reason ) {
                printf( '<span class="sulayr-import-meta">%s: %s · %s</span>', esc_html( $members[ $user_id ]['label'] ), esc_html( $members[ $user_id ]['meta'] ), esc_html( $reason ) );
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    /**
     * Analizar el JSON.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_analyse(): void {
        check_admin_referer( self::ACTION_ANALYSE );
        Notices::require_cap();

        // El JSON se lee tal cual: cada texto se limpia al leerlo y se escapa al pintarlo.
        $json = (string) wp_unslash( $_POST['json'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $file = $_FILES['json_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        if ( is_array( $file ) && UPLOAD_ERR_OK === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) && is_uploaded_file( (string) $file['tmp_name'] ) ) {
            if ( (int) $file['size'] > self::MAX_BYTES ) {
                Notices::redirect( self::url(), 'error', __( 'El archivo es demasiado grande (máximo 2 MB).', SULAYR_TEXTDOMAIN ) );
            }
            $json = (string) file_get_contents( (string) $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Archivo subido.
        }
        if ( strlen( $json ) > self::MAX_BYTES ) {
            Notices::redirect( self::url(), 'error', __( 'El JSON es demasiado grande (máximo 2 MB).', SULAYR_TEXTDOMAIN ) );
        }

        $parsed = ImportParser::parse( $json, current_time( 'Y-m-d' ) );
        if ( ! empty( $parsed['errors'] ) ) {
            Notices::save_state( [ 'json' => $json, 'import_errors' => $parsed['errors'] ] );
            Notices::redirect( self::url(), 'error', __( 'No se ha podido leer el JSON.', SULAYR_TEXTDOMAIN ) );
        }

        $season = (int) substr( $parsed['competition']['race_date'], 0, 4 );
        $merged = ImportMerger::merge( $parsed );
        $matches = self::matcher( $season )->match_all( array_map( function ( $a ) {
            return [ 'name' => $a['name'], 'club' => $a['club'], 'sex' => $a['sex'], 'cat' => $a['cat'] ];
        }, $merged['athletes'] ) );

        self::store( [
            'parsed' => $parsed,
            'merged' => $merged,
            'matches' => $matches,
            'season' => $season,
            'assign' => [],
            'applied' => false,
        ] );

        $sure = count( array_filter( $matches, function ( $m ) {
            return NameMatcher::SURE === $m['status'];
        } ) );
        $doubts = count( array_filter( $matches, function ( $m ) {
            return NameMatcher::DOUBT === $m['status'];
        } ) );
        Notices::redirect(
            self::url(),
            $doubts > 0 ? 'warning' : 'success',
            sprintf( __( 'JSON leído: %1$d deportistas, %2$d socios encontrados y %3$d dudas.', SULAYR_TEXTDOMAIN ), count( $merged['athletes'] ), $sure, $doubts )
        );
    }

    /**
     * Rellenar la competición con las elecciones.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_apply(): void {
        check_admin_referer( self::ACTION_APPLY );
        Notices::require_cap();

        $data = self::load();
        if ( null === $data ) {
            Notices::redirect( self::url(), 'error', __( 'La importación ha caducado. Vuelve a pegar el JSON.', SULAYR_TEXTDOMAIN ) );
        }

        $raw = (array) wp_unslash( $_POST['assign'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Cada valor se pasa a entero o ''.
        $assign = [];
        foreach ( $raw as $index => $value ) {
            $index = absint( $index );
            if ( isset( $data['matches'][ $index ] ) ) {
                $value = trim( (string) $value );
                $assign[ $index ] = '' === $value ? '' : (string) absint( $value );
            }
        }
        $data['assign'] = $assign;
        $season = (int) $data['season'];
        $raw_interest = (array) wp_unslash( $_POST['interest'] ?? [] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Entero y texto limpiado abajo.
        $data['interest'] = [
            'interest_bonus' => absint( $raw_interest['interest_bonus'] ?? 0 ),
            'interest_label' => sanitize_text_field( (string) ( $raw_interest['interest_label'] ?? '' ) ),
        ];
        self::store( $data );
        if ( ! in_array( $data['interest']['interest_bonus'], RankingSettings::interest_values( $season ), true ) ) {
            Notices::redirect( self::url(), 'error', __( 'Bonus por interés del club: elige un bonus de la lista.', SULAYR_TEXTDOMAIN ) );
        }

        $members = self::selectable_members( $season );
        $athletes = $data['merged']['athletes'];

        $pending = [];
        $used = [];
        $repeated = [];
        foreach ( $data['matches'] as $index => $match ) {
            if ( NameMatcher::DOUBT === $match['status'] && '' === ( $assign[ $index ] ?? '' ) ) {
                $pending[] = $athletes[ $index ]['name'];
            }
        }
        foreach ( $assign as $index => $user_id ) {
            $user_id = (int) $user_id;
            if ( $user_id <= 0 ) {
                continue;
            }
            if ( ! isset( $members[ $user_id ] ) ) {
                Notices::redirect( self::url(), 'error', sprintf( __( '«%s»: el socio elegido no se puede usar (no está activo o congelado, o no tiene sexo).', SULAYR_TEXTDOMAIN ), $athletes[ $index ]['name'] ) );
            }
            if ( isset( $used[ $user_id ] ) ) {
                $repeated[] = $members[ $user_id ]['label'];
            }
            $used[ $user_id ] = true;
        }
        if ( ! empty( $pending ) ) {
            Notices::redirect( self::url(), 'error', sprintf( __( 'Decide antes estas dudas: %s.', SULAYR_TEXTDOMAIN ), implode( ', ', $pending ) ) );
        }
        if ( ! empty( $repeated ) ) {
            Notices::redirect( self::url(), 'error', sprintf( __( 'Este socio está elegido en más de una fila: %s.', SULAYR_TEXTDOMAIN ), implode( ', ', array_unique( $repeated ) ) ) );
        }
        if ( empty( $used ) ) {
            Notices::redirect( self::url(), 'error', __( 'No has elegido ningún socio: no hay nada que rellenar.', SULAYR_TEXTDOMAIN ) );
        }

        $map = [];
        foreach ( $members as $user_id => $m ) {
            $map[ $user_id ] = [ 'sex' => $m['sex'], 'group' => $m['group'] ];
        }
        $titles = array_column( $data['parsed']['classifications'], 'title' );
        /* translators: 1: fecha, 2: clasificaciones. */
        $notes = sprintf( __( 'Importada el %1$s de: %2$s.', SULAYR_TEXTDOMAIN ), date_i18n( 'd/m/Y' ), implode( ', ', $titles ) );
        $notes = function_exists( 'mb_substr' ) ? mb_substr( $notes, 0, 255 ) : substr( $notes, 0, 255 );

        $built = ImportMerger::build_input( $data['parsed'], $data['merged'], $assign, $map, RankingSettings::groups( $season ), $notes, $data['interest'] );

        $data['applied'] = true;
        self::store( $data );
        Notices::save_state( [ 'input' => $built['input'], 'errors' => [] ] );

        $count = count( $built['input']['results'] );
        $text = sprintf( _n( 'Importado %d socio.', 'Importados %d socios.', $count, SULAYR_TEXTDOMAIN ), $count )
            . ' ' . __( 'Todavía no se ha guardado nada: revisa los resultados y las referencias y pulsa «Guardar».', SULAYR_TEXTDOMAIN );
        if ( ! empty( $built['warnings'] ) ) {
            $text .= ' ' . implode( ' ', $built['warnings'] );
        }
        Notices::redirect( CompetitionEditPage::url(), empty( $built['warnings'] ) ? 'info' : 'warning', $text );
    }

    /**
     * Descartar la importación en curso.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_reset(): void {
        check_admin_referer( self::ACTION_RESET );
        Notices::require_cap();
        delete_transient( self::TRANSIENT . get_current_user_id() );
        Notices::redirect( self::url(), 'success', __( 'Importación descartada.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Socios que se pueden elegir, con su sexo, grupo y textos.
     *
     * @param   int $season Temporada.
     * @return 	array<int, array{sex: string, group: string, code: string, label: string, meta: string, first: string, last: string, group_label: string}>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function selectable_members( int $season ): array {
        $groups = RankingSettings::groups( $season );
        $out = [];
        foreach ( RankingRoster::members() as $id => $info ) {
            if ( '' === $info['sex'] ) {
                continue;
            }
            $group = $groups->for_birth( $info['birth'], $season );
            $out[ (int) $id ] = [
                'sex' => $info['sex'],
                'group' => $group,
                'code' => '' !== $group ? $groups->code( $group ) : '',
                'group_label' => '' !== $group ? $groups->label( $group ) : '',
                'first' => $info['first'],
                'last' => $info['last'],
                'label' => $info['name'] . ( '' !== $info['number'] ? ' (nº ' . $info['number'] . ')' : '' ),
                'meta' => Labels::of( Labels::sexes(), $info['sex'] ) . ' · ' . ( '' !== $group ? $groups->label( $group ) : __( 'sin grupo', SULAYR_TEXTDOMAIN ) ),
            ];
        }

        return $out;
    }

    /**
     * Emparejador con los socios de la temporada.
     *
     * @param   int $season Temporada.
     * @return 	NameMatcher
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function matcher( int $season ): NameMatcher {
        $members = [];
        foreach ( self::selectable_members( $season ) as $id => $m ) {
            // Sin nombre y apellidos separados, se usa el nombre completo como apellidos.
            $members[ $id ] = [
                'first' => '' !== $m['last'] ? $m['first'] : '',
                'last' => '' !== $m['last'] ? $m['last'] : RankingRoster::info( $id )['name'],
                'sex' => $m['sex'],
                'code' => $m['code'],
                'group_label' => $m['group_label'],
            ];
        }

        return new NameMatcher( $members, self::club_keywords() );
    }

    /**
     * Nombre de un conjunto (categoría de equipo y sexo).
     *
     * @param   string $team_category '' en individual.
     * @param   string $sex           m|f.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function group_label( string $team_category, string $sex ): string {
        $sex_label = Keys::SEX_FEMALE === $sex ? __( 'mujeres', SULAYR_TEXTDOMAIN ) : __( 'hombres', SULAYR_TEXTDOMAIN );
        if ( '' === $team_category ) {
            return sprintf( __( 'General, %s', SULAYR_TEXTDOMAIN ), $sex_label );
        }

        return Labels::of( Labels::team_categories(), $team_category ) . ', ' . $sex_label;
    }

    /**
     * Importación en curso del usuario, o null.
     *
     * @return 	array|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function load(): ?array {
        $data = get_transient( self::TRANSIENT . get_current_user_id() );

        return is_array( $data ) && isset( $data['parsed'], $data['merged'], $data['matches'] ) ? $data : null;
    }

    /**
     * Guarda la importación en curso.
     *
     * @param   array $data Datos.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function store( array $data ): void {
        set_transient( self::TRANSIENT . get_current_user_id(), $data, self::TTL );
    }
}
