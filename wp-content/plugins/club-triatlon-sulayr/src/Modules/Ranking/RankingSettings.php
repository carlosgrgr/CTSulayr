<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Ranking\Admin\RankingMenu;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Baremo;
use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Ranking → Ajustes» y baremo de cada temporada (SPEC-RANKING-INTERNO §2.6, §3.1 y §4.7).
 *
 * Página de opciones de ACF con post_id POST_ID. Los ajustes son siempre los
 * de la temporada en curso: al guardarlos se copian a su baremo
 * (sulayr_ranking_baremo_<año>), se registra cada cambio y se recalcula solo
 * esa temporada (R35). El cálculo lee siempre for_season(), nunca los campos.
 */
class RankingSettings {

    public const POST_ID = 'sulayr_ranking';
    public const PAGE_SLUG = 'sulayr-ranking-ajustes';
    public const SEASON_OPTION = 'sulayr_ranking_baremo_';

    private const GROUPS_FIELD = 'field_sulayr_ranking_age_groups';
    private const INTRO_FIELD = 'field_sulayr_ranking_intro';

    /** Baremo antes de guardar, para registrar los cambios. */
    private static ?array $before = null;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'acf/init', [ $this, 'register_page' ] );
        add_action( 'acf/init', [ $this, 'register_fields' ] );
        add_action( 'acf/save_post', [ $this, 'before_save' ], 5 );
        add_action( 'acf/save_post', [ $this, 'after_save' ], 20 );
        add_filter( 'acf/validate_value/key=' . self::GROUPS_FIELD, [ $this, 'validate_groups' ], 10, 2 );
        add_filter( 'acf/load_field/key=' . self::INTRO_FIELD, [ $this, 'load_intro' ] );
    }

    /**
     * Año de la temporada en curso (zona horaria del sitio).
     *
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function current_season(): int {
        return (int) current_time( 'Y' );
    }

    /**
     * Nombre de la pestaña de Mi cuenta.
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function account_label(): string {
        $value = function_exists( 'get_field' ) ? trim( (string) get_field( 'account_label', self::POST_ID ) ) : '';

        return '' !== $value ? $value : __( 'Ranking interno', SULAYR_TEXTDOMAIN );
    }

    /**
     * Baremo de los campos de la página (los de la temporada en curso).
     *
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function current(): array {
        if ( false === function_exists( 'get_field' ) ) {
            return Baremo::defaults();
        }

        $baremo = [
            'base' => get_field( 'base_points', self::POST_ID ),
            'bonus' => get_field( 'finish_bonus', self::POST_ID ),
            'best_n' => get_field( 'best_n', self::POST_ID ),
            'coefficients' => [],
            'age_groups' => [],
        ];
        foreach ( Keys::MODALITIES as $modality ) {
            $baremo['coefficients'][ $modality ] = (array) get_field( 'coef_' . $modality, self::POST_ID );
        }
        foreach ( (array) get_field( 'age_groups', self::POST_ID ) as $row ) {
            $baremo['age_groups'][] = [
                'key' => (string) ( $row['key'] ?? '' ),
                'label' => (string) ( $row['label'] ?? '' ),
                'min' => $row['min'] ?? null,
                'max' => $row['max'] ?? null,
                'code' => (string) ( $row['code'] ?? '' ),
            ];
        }

        return Baremo::normalize( $baremo );
    }

    /**
     * Baremo de una temporada.
     *
     * Si la temporada aún no tiene copia guardada, se devuelven los ajustes
     * actuales sin guardar nada (lectura). Con $create = true (escrituras:
     * recalcular, guardar ajustes) la copia se guarda antes de usarla.
     *
     * @param   int  $season Año.
     * @param   bool $create Guardar la copia si no existe.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_season( int $season, bool $create = false ): array {
        $saved = get_option( self::SEASON_OPTION . $season );
        if ( is_array( $saved ) ) {
            return Baremo::normalize( $saved );
        }

        $baremo = self::current();
        if ( $create ) {
            add_option( self::SEASON_OPTION . $season, $baremo, '', 'no' );
        }

        return $baremo;
    }

    /**
     * Grupos de edad de una temporada.
     *
     * @param   int $season Año.
     * @return 	AgeGroups
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function groups( int $season ): AgeGroups {
        return new AgeGroups( self::for_season( $season )['age_groups'] );
    }

    /**
     * Años con baremo guardado, del más reciente al más antiguo.
     *
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function saved_seasons(): array {
        global $wpdb;

        $names = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( self::SEASON_OPTION ) . '%' ) );
        $years = [];
        foreach ( $names as $name ) {
            $year = (int) substr( (string) $name, strlen( self::SEASON_OPTION ) );
            if ( $year > 2000 ) {
                $years[] = $year;
            }
        }
        rsort( $years );

        return $years;
    }

    /**
     * Guarda el baremo anterior antes de que ACF escriba los campos.
     *
     * @param   mixed $post_id Destino de ACF.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function before_save( $post_id ): void {
        if ( self::POST_ID !== $post_id ) {
            return;
        }

        // Antes de que ACF escriba los campos: los valores actuales son los de la temporada anterior
        // si esta aún no tiene copia (solo se cambian desde esta pantalla).
        self::for_season( self::current_season() - 1, true );
        self::$before = self::for_season( self::current_season(), true );
    }

    /**
     * Copia los ajustes al baremo de la temporada en curso, registra los cambios y recalcula esa temporada.
     *
     * @param   mixed $post_id Destino de ACF.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function after_save( $post_id ): void {
        if ( self::POST_ID !== $post_id || null === self::$before ) {
            return;
        }

        $season = self::current_season();
        $old = self::flatten( self::$before );
        $baremo = self::current();
        $new = self::flatten( $baremo );
        self::$before = null;

        update_option( self::SEASON_OPTION . $season, $baremo, 'no' );

        $changed = false;
        RankingLog::with_note( sprintf( __( 'Temporada %d', SULAYR_TEXTDOMAIN ), $season ), function () use ( $old, $new, &$changed ) {
            foreach ( array_unique( array_merge( array_keys( $old ), array_keys( $new ) ) ) as $key ) {
                $before = $old[ $key ] ?? '';
                $after = $new[ $key ] ?? '';
                if ( (string) $before !== (string) $after ) {
                    RankingLog::record( RankingLog::ENTITY_SETTING, 0, 0, 0, $key, $before, $after );
                    $changed = true;
                }
            }
        } );

        if ( $changed ) {
            RankingService::recalculate_season( $season, __( 'Cambio de ajustes', SULAYR_TEXTDOMAIN ) );
        }
    }

    /**
     * Comprueba los grupos de edad: claves únicas y tramos que no se solapan.
     *
     * @param   mixed $valid Resultado de ACF.
     * @param   mixed $value Filas del repetidor (claves de subcampo).
     * @return 	mixed
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_groups( $valid, $value ) {
        if ( true !== $valid || false === is_array( $value ) ) {
            return $valid;
        }

        $rows = [];
        foreach ( $value as $row ) {
            $rows[] = [
                'key' => $row[ self::GROUPS_FIELD . '_key' ] ?? '',
                'min' => $row[ self::GROUPS_FIELD . '_min' ] ?? '',
                'max' => $row[ self::GROUPS_FIELD . '_max' ] ?? '',
            ];
        }
        $problems = AgeGroups::problems( $rows );

        return empty( $problems ) ? $valid : implode( ' ', $problems );
    }

    /**
     * Texto de la cabecera de los ajustes, con la temporada y los baremos guardados.
     *
     * @param   array $field Campo.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_intro( array $field ): array {
        $season = self::current_season();
        $html = '<p>' . esc_html( sprintf( __( 'Estos ajustes se aplican a la temporada %d. Al guardar, se recalculan sus puntos. Las temporadas anteriores conservan su baremo.', SULAYR_TEXTDOMAIN ), $season ) ) . '</p>';

        $past = array_filter( self::saved_seasons(), function ( $year ) use ( $season ) {
            return $year !== $season;
        } );
        if ( ! empty( $past ) ) {
            $html .= '<details><summary>' . esc_html__( 'Ver el baremo de temporadas anteriores', SULAYR_TEXTDOMAIN ) . '</summary>';
            foreach ( $past as $year ) {
                $b = self::for_season( $year );
                $html .= '<p><strong>' . esc_html( (string) $year ) . '</strong>: ' . esc_html( sprintf( __( 'base %1$s, bonus %2$s, cuentan %3$d pruebas.', SULAYR_TEXTDOMAIN ), number_format_i18n( $b['base'] ), number_format_i18n( $b['bonus'] ), $b['best_n'] ) ) . '</p><ul>';
                foreach ( Labels::modalities() as $modality => $label ) {
                    $parts = [];
                    foreach ( Labels::distances() as $distance => $distance_label ) {
                        $parts[] = $distance_label . ' ×' . Labels::coef( $b['coefficients'][ $modality ][ $distance ] );
                    }
                    $html .= '<li>' . esc_html( $label . ': ' . implode( ', ', $parts ) ) . '</li>';
                }
                $html .= '</ul>';
            }
            $html .= '</details>';
        }

        $field['message'] = $html;

        return $field;
    }

    /**
     * Página de opciones bajo el menú Ranking.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_page(): void {
        if ( false === function_exists( 'acf_add_options_sub_page' ) ) {
            return;
        }

        acf_add_options_sub_page( [
            'page_title' => __( 'Ajustes del ranking', SULAYR_TEXTDOMAIN ),
            'menu_title' => __( 'Ajustes', SULAYR_TEXTDOMAIN ),
            'menu_slug' => self::PAGE_SLUG,
            'parent_slug' => RankingMenu::SLUG,
            'capability' => MemberRoles::MANAGE_RANKING,
            'post_id' => self::POST_ID,
            'autoload' => false,
            'update_button' => __( 'Guardar ajustes', SULAYR_TEXTDOMAIN ),
            'updated_message' => __( 'Ajustes guardados. Se han recalculado los puntos de la temporada en curso.', SULAYR_TEXTDOMAIN ),
        ] );
    }

    /**
     * Campos de la página.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_fields(): void {
        if ( false === function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        $defaults = Baremo::defaults();

        $fields = [
            [
                'key' => self::INTRO_FIELD,
                'label' => '',
                'name' => '',
                'type' => 'message',
                'message' => '',
                'esc_html' => 0,
            ],
            $this->tab( 'baremo', __( 'Baremo', SULAYR_TEXTDOMAIN ) ),
            $this->number( 'base_points', __( 'Puntos base', SULAYR_TEXTDOMAIN ), $defaults['base'], __( '1000 por defecto.', SULAYR_TEXTDOMAIN ), 1, 1 ),
            $this->number( 'finish_bonus', __( 'Bonus por prueba terminada', SULAYR_TEXTDOMAIN ), $defaults['bonus'], __( 'Se suma después del coeficiente. 50 por defecto.', SULAYR_TEXTDOMAIN ), 1, 0 ),
            $this->number( 'best_n', __( 'Pruebas que cuentan (N)', SULAYR_TEXTDOMAIN ), $defaults['best_n'], __( 'Mejores resultados de cada socio que suman en la temporada. 6 por defecto.', SULAYR_TEXTDOMAIN ), 1, 1 ),
            $this->tab( 'coeficientes', __( 'Coeficientes', SULAYR_TEXTDOMAIN ) ),
        ];

        foreach ( Labels::modalities() as $modality => $label ) {
            $sub = [];
            foreach ( Labels::distances() as $distance => $distance_label ) {
                $sub[] = [
                    'key' => 'field_sulayr_ranking_coef_' . $modality . '_' . $distance,
                    'label' => $distance_label,
                    'name' => $distance,
                    'type' => 'number',
                    'default_value' => $defaults['coefficients'][ $modality ][ $distance ],
                    'min' => 0.1,
                    'step' => 0.05,
                    'required' => 1,
                ];
            }
            $fields[] = [
                'key' => 'field_sulayr_ranking_coef_' . $modality,
                'label' => $label,
                'name' => 'coef_' . $modality,
                'type' => 'group',
                'layout' => 'table',
                'sub_fields' => $sub,
            ];
        }

        $fields[] = $this->tab( 'grupos', __( 'Grupos de edad', SULAYR_TEXTDOMAIN ) );
        $fields[] = [
            'key' => self::GROUPS_FIELD,
            'label' => __( 'Grupos de edad', SULAYR_TEXTDOMAIN ),
            'name' => 'age_groups',
            'type' => 'repeater',
            'instructions' => __( 'Edad que se cumple el 31 de diciembre de la temporada. Deja la mínima o la máxima vacía para «sin límite». El código son las dos letras de la columna «Cat» de las clasificaciones de la federación (V1, SN…), sin la M o la F. Si se deja vacío se usan las categorías de la federación.', SULAYR_TEXTDOMAIN ),
            'layout' => 'table',
            'button_label' => __( 'Añadir grupo', SULAYR_TEXTDOMAIN ),
            'sub_fields' => [
                [ 'key' => self::GROUPS_FIELD . '_key', 'label' => __( 'Clave', SULAYR_TEXTDOMAIN ), 'name' => 'key', 'type' => 'text', 'required' => 1, 'instructions' => __( 'Minúsculas y guiones. No la cambies una vez usada.', SULAYR_TEXTDOMAIN ) ],
                [ 'key' => self::GROUPS_FIELD . '_label', 'label' => __( 'Nombre', SULAYR_TEXTDOMAIN ), 'name' => 'label', 'type' => 'text', 'required' => 1 ],
                [ 'key' => self::GROUPS_FIELD . '_min', 'label' => __( 'Edad mínima', SULAYR_TEXTDOMAIN ), 'name' => 'min', 'type' => 'number', 'min' => 0, 'step' => 1 ],
                [ 'key' => self::GROUPS_FIELD . '_max', 'label' => __( 'Edad máxima', SULAYR_TEXTDOMAIN ), 'name' => 'max', 'type' => 'number', 'min' => 0, 'step' => 1 ],
                [ 'key' => self::GROUPS_FIELD . '_code', 'label' => __( 'Código', SULAYR_TEXTDOMAIN ), 'name' => 'code', 'type' => 'text', 'maxlength' => 2 ],
            ],
            'default_value' => '',
        ];

        $fields[] = $this->tab( 'cuenta', __( 'Mi cuenta', SULAYR_TEXTDOMAIN ) );
        $fields[] = [
            'key' => 'field_sulayr_ranking_account_label',
            'label' => __( 'Nombre de la pestaña', SULAYR_TEXTDOMAIN ),
            'name' => 'account_label',
            'type' => 'text',
            'placeholder' => __( 'Ranking interno', SULAYR_TEXTDOMAIN ),
            'instructions' => __( 'Vacío: «Ranking interno».', SULAYR_TEXTDOMAIN ),
        ];

        acf_add_local_field_group( [
            'key' => 'group_sulayr_ranking_settings',
            'title' => __( 'Ajustes del ranking', SULAYR_TEXTDOMAIN ),
            'fields' => $fields,
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => self::PAGE_SLUG,
                    ],
                ],
            ],
        ] );

        // Grupos por defecto en el repetidor vacío, para que se vean y se puedan editar.
        add_filter( 'acf/load_value/key=' . self::GROUPS_FIELD, [ $this, 'default_groups' ], 10, 3 );
    }

    /**
     * Rellena el repetidor con las categorías de la federación si está vacío.
     *
     * @param   mixed $value   Valor.
     * @param   mixed $post_id Destino.
     * @param   array $field   Campo.
     * @return 	mixed
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function default_groups( $value, $post_id, $field ) {
        if ( self::POST_ID !== $post_id || ! empty( $value ) ) {
            return $value;
        }

        $rows = [];
        foreach ( AgeGroups::DEFAULTS as $group ) {
            $rows[] = [
                self::GROUPS_FIELD . '_key' => $group['key'],
                self::GROUPS_FIELD . '_label' => $group['label'],
                self::GROUPS_FIELD . '_min' => $group['min'],
                self::GROUPS_FIELD . '_max' => $group['max'],
                self::GROUPS_FIELD . '_code' => $group['code'],
            ];
        }

        return $rows;
    }

    /**
     * Baremo como lista plana clave => valor, para registrar los cambios.
     *
     * @param   array $baremo Baremo.
     * @return 	array<string, string>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function flatten( array $baremo ): array {
        $flat = [
            'base_points' => (string) (float) $baremo['base'],
            'finish_bonus' => (string) (float) $baremo['bonus'],
            'best_n' => (string) (int) $baremo['best_n'],
        ];
        foreach ( $baremo['coefficients'] as $modality => $distances ) {
            foreach ( $distances as $distance => $value ) {
                $flat[ 'coef_' . $modality . '_' . $distance ] = (string) (float) $value;
            }
        }
        $flat['age_groups'] = (string) wp_json_encode( ( new AgeGroups( $baremo['age_groups'] ) )->rows(), JSON_UNESCAPED_UNICODE );

        return $flat;
    }

    /** Pestaña. */
    private function tab( string $key, string $label ): array {
        return [
            'key' => 'field_sulayr_ranking_tab_' . $key,
            'label' => $label,
            'name' => '',
            'type' => 'tab',
            'placement' => 'left',
        ];
    }

    /** Campo numérico. */
    private function number( string $name, string $label, $default, string $instructions, $step, $min ): array {
        return [
            'key' => 'field_sulayr_ranking_' . $name,
            'label' => $label,
            'name' => $name,
            'type' => 'number',
            'default_value' => $default,
            'instructions' => $instructions,
            'step' => $step,
            'min' => $min,
            'required' => 1,
        ];
    }
}
