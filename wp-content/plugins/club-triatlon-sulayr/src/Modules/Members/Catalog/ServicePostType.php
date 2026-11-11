<?php
namespace ClubTriatlonSulayr\Modules\Members\Catalog;

use ClubTriatlonSulayr\Modules\Members\Admin\MembersMenu;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT del catálogo de servicios (D18). Privado, bajo el menú Socios.
 * El slug del servicio (post_name) es su clave estable.
 */
class ServicePostType {

    public const POST_TYPE_NAME = 'sulayr_service';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'init', [ $this, 'register' ] );
        add_filter( 'manage_' . self::POST_TYPE_NAME . '_posts_columns', [ $this, 'columns' ] );
        add_action( 'manage_' . self::POST_TYPE_NAME . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
    }

    /**
     * Registra el CPT.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        register_post_type( self::POST_TYPE_NAME, [
            'labels' => [
                'name' => __( 'Servicios', SULAYR_TEXTDOMAIN ),
                'singular_name' => __( 'Servicio', SULAYR_TEXTDOMAIN ),
                'add_new' => __( 'Añadir servicio', SULAYR_TEXTDOMAIN ),
                'add_new_item' => __( 'Añadir servicio', SULAYR_TEXTDOMAIN ),
                'edit_item' => __( 'Editar servicio', SULAYR_TEXTDOMAIN ),
                'new_item' => __( 'Nuevo servicio', SULAYR_TEXTDOMAIN ),
                'search_items' => __( 'Buscar servicios', SULAYR_TEXTDOMAIN ),
                'not_found' => __( 'No hay servicios', SULAYR_TEXTDOMAIN ),
                'not_found_in_trash' => __( 'No hay servicios en la papelera', SULAYR_TEXTDOMAIN ),
                'all_items' => __( 'Servicios', SULAYR_TEXTDOMAIN ),
                'menu_name' => __( 'Servicios', SULAYR_TEXTDOMAIN ),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => MembersMenu::SLUG,
            'show_in_nav_menus' => false,
            'show_in_rest' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => false,
            'hierarchical' => false,
            'supports' => [ 'title', 'page-attributes' ],
            'capability_type' => MemberRoles::SERVICE_CAP_TYPE,
            'map_meta_cap' => true,
        ] );
    }

    /**
     * Columnas del listado.
     *
     * @param   array $columns Columnas.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function columns( array $columns ): array {
        $date = $columns['date'] ?? null;
        unset( $columns['date'] );

        $columns['sulayr_slug'] = __( 'Clave', SULAYR_TEXTDOMAIN );
        $columns['sulayr_price'] = __( 'Precio vigente', SULAYR_TEXTDOMAIN );
        $columns['sulayr_flags'] = __( 'Características', SULAYR_TEXTDOMAIN );

        if ( null !== $date ) {
            $columns['date'] = $date;
        }

        return $columns;
    }

    /**
     * Contenido de las columnas.
     *
     * @param   string $column  Columna.
     * @param   int    $post_id Servicio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_column( $column, $post_id ): void {
        $service = ServiceCatalog::get( (int) $post_id );
        if ( null === $service ) {
            return;
        }

        if ( 'sulayr_slug' === $column ) {
            echo '<code>' . esc_html( $service->slug ) . '</code>';
            return;
        }

        if ( 'sulayr_price' === $column ) {
            $parts = [];
            $today = current_time( 'Y-m-d' );
            foreach ( ServiceCatalog::member_type_labels() as $type => $label ) {
                $price = ServiceCatalog::price_for( $service, $type, $today );
                if ( null !== $price ) {
                    $parts[] = esc_html( $label ) . ': ' . esc_html( number_format_i18n( $price['amount'], 2 ) ) . ' €';
                }
            }
            echo $parts ? implode( '<br>', $parts ) : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            return;
        }

        if ( 'sulayr_flags' === $column ) {
            $flags = [];
            if ( $service->is_base ) {
                $flags[] = __( 'Cuota base', SULAYR_TEXTDOMAIN );
            }
            if ( false === $service->prorated ) {
                $flags[] = __( 'No se prorratea', SULAYR_TEXTDOMAIN );
            }
            if ( $service->public ) {
                $flags[] = __( 'En el formulario', SULAYR_TEXTDOMAIN );
            }
            if ( $service->grants_swimming ) {
                $flags[] = __( 'Da acceso a reservas', SULAYR_TEXTDOMAIN );
            }
            if ( $service->coach_list ) {
                $flags[] = __( 'Listado del entrenador', SULAYR_TEXTDOMAIN );
            }
            echo esc_html( implode( ' · ', $flags ) );
        }
    }
}
