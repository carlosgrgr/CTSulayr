<?php

namespace ClubTriatlonSulayr\Modules\Documents;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Fields\DocumentFields\DocumentFields;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;
use ClubTriatlonSulayr\Modules\Taxonomies\DocumentCategoryTaxonomy;
use ClubTriatlonSulayr\Services\DocumentService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Listado de administración de Documentos: columnas, avisos, orden por
 * defecto, filtro por visibilidad y acción «Ver archivo».
 */
class DocumentAdminList {

    private const COLUMN_AUDIENCE = 'sulayr_document_audience';
    private const COLUMN_FILE = 'sulayr_document_file';
    private const COLUMN_ORDER = 'menu_order';
    private const FILTER_AUDIENCE = 'sulayr_doc_audience';

    /** Nombres que se muestran en el title de «N deportistas». */
    private const MAX_NAMES_IN_TITLE = 10;

    private DocumentService $documents;

    /**
     * __construct()
     *
     * @param   DocumentService $documents Servicio de documentos.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( DocumentService $documents ) {
        $this->documents = $documents;

        $type = DocumentPostType::POST_TYPE_NAME;
        add_filter( 'manage_edit-' . $type . '_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_' . $type . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_filter( 'manage_edit-' . $type . '_sortable_columns', [ $this, 'sortable_columns' ] );
        add_action( 'restrict_manage_posts', [ $this, 'add_category_filter' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'add_audience_filter' ], 11, 2 );
        add_action( 'pre_get_posts', [ $this, 'adjust_list_query' ] );
        // Después de los plugins que añaden acciones (WPBakery, 10).
        add_filter( 'post_row_actions', [ $this, 'add_row_actions' ], 20, 2 );
        add_action( 'admin_head-edit.php', [ $this, 'print_styles' ] );
    }

    /**
     * Columnas: título, categoría, visible para, archivo, orden y fecha.
     *
     * @param   array $columns Columnas por defecto.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_columns( array $columns ): array {
        $category = 'taxonomy-' . DocumentCategoryTaxonomy::TAXONOMY_NAME;

        $new = [];
        $new['cb'] = $columns['cb'] ?? '';
        $new['title'] = $columns['title'] ?? __( 'Título', SULAYR_TEXTDOMAIN );
        if ( isset( $columns[ $category ] ) ) {
            $new[ $category ] = $columns[ $category ];
        }
        $new[ self::COLUMN_AUDIENCE ] = __( 'Visible para', SULAYR_TEXTDOMAIN );
        $new[ self::COLUMN_FILE ] = __( 'Archivo', SULAYR_TEXTDOMAIN );
        $new[ self::COLUMN_ORDER ] = __( 'Orden', SULAYR_TEXTDOMAIN );
        $new['date'] = $columns['date'] ?? __( 'Fecha', SULAYR_TEXTDOMAIN );

        return $new;
    }

    /**
     * Contenido de las columnas propias.
     *
     * @param   string $column  Columna.
     * @param   int    $post_id ID del documento.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_column( string $column, int $post_id ): void {
        if ( self::COLUMN_AUDIENCE === $column ) {
            $this->render_audience( $post_id );
            return;
        }

        if ( self::COLUMN_FILE === $column ) {
            $file = $this->documents->get_file( $post_id );
            if ( null === $file ) {
                $this->render_warning( __( 'Sin archivo', SULAYR_TEXTDOMAIN ), __( 'Los deportistas no ven este documento hasta que tenga un archivo.', SULAYR_TEXTDOMAIN ) );
                return;
            }

            $label = 'pdf' === $file['ext'] ? 'PDF' : strtoupper( $file['ext'] );
            echo esc_html( $label . ' · ' . size_format( $file['size'], $file['size'] < KB_IN_BYTES ? 0 : 1 ) );
            return;
        }

        if ( self::COLUMN_ORDER === $column ) {
            echo esc_html( (string) (int) get_post_field( 'menu_order', $post_id ) );
        }
    }

    /**
     * La columna Orden se puede ordenar (orderby=menu_order es nativo).
     *
     * @param   array $columns Columnas ordenables.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function sortable_columns( array $columns ): array {
        $columns[ self::COLUMN_ORDER ] = 'menu_order';

        return $columns;
    }

    /**
     * Desplegable de categorías. WordPress solo lo pinta para la taxonomía category.
     * Usa taxonomy + term, los mismos parámetros que los enlaces de la columna
     * Categorías (la taxonomía no tiene query var propia).
     *
     * @param   string $post_type Tipo de post del listado.
     * @param   string $which     Posición (top / bottom).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_category_filter( string $post_type, string $which = 'top' ): void {
        if ( DocumentPostType::POST_TYPE_NAME !== $post_type || 'top' !== $which ) {
            return;
        }

        if ( 0 === (int) wp_count_terms( [ 'taxonomy' => DocumentCategoryTaxonomy::TAXONOMY_NAME, 'hide_empty' => false ] ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del listado.
        $current = isset( $_GET['term'] ) ? sanitize_title( wp_unslash( $_GET['term'] ) ) : '';

        printf( '<input type="hidden" name="taxonomy" value="%s">', esc_attr( DocumentCategoryTaxonomy::TAXONOMY_NAME ) );
        printf(
            '<label class="screen-reader-text" for="sulayr-doc-category-filter">%s</label>',
            esc_html__( 'Filtrar por categoría', SULAYR_TEXTDOMAIN )
        );
        wp_dropdown_categories( [
            'taxonomy' => DocumentCategoryTaxonomy::TAXONOMY_NAME,
            'name' => 'term',
            'id' => 'sulayr-doc-category-filter',
            'value_field' => 'slug',
            'selected' => $current,
            'show_option_all' => __( 'Todas las categorías', SULAYR_TEXTDOMAIN ),
            'hide_empty' => false,
            'hierarchical' => true,
            'orderby' => 'name',
        ] );
    }

    /**
     * Desplegable «Visible para» encima del listado.
     *
     * @param   string $post_type Tipo de post del listado.
     * @param   string $which     Posición (top / bottom).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_audience_filter( string $post_type, string $which = 'top' ): void {
        if ( DocumentPostType::POST_TYPE_NAME !== $post_type || 'top' !== $which ) {
            return;
        }

        $current = $this->requested_audience();
        $options = [
            '' => __( 'Cualquier visibilidad', SULAYR_TEXTDOMAIN ),
            DocumentFields::AUDIENCE_ALL => __( 'Todos los deportistas', SULAYR_TEXTDOMAIN ),
            DocumentFields::AUDIENCE_USERS => __( 'Deportistas concretos', SULAYR_TEXTDOMAIN ),
        ];

        printf(
            '<label class="screen-reader-text" for="sulayr-doc-audience-filter">%s</label>',
            esc_html__( 'Filtrar por visibilidad', SULAYR_TEXTDOMAIN )
        );
        echo '<select name="' . esc_attr( self::FILTER_AUDIENCE ) . '" id="sulayr-doc-audience-filter">';
        foreach ( $options as $value => $label ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( $value ),
                selected( $current, $value, false ),
                esc_html( $label )
            );
        }
        echo '</select>';
    }

    /**
     * Listado: orden por defecto (Orden y título) y filtro por visibilidad.
     *
     * @param   \WP_Query $query Consulta.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function adjust_list_query( \WP_Query $query ): void {
        global $pagenow;

        if ( false === is_admin() || false === $query->is_main_query() || 'edit.php' !== $pagenow ) {
            return;
        }

        if ( DocumentPostType::POST_TYPE_NAME !== $query->get( 'post_type' ) ) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del listado.
        if ( empty( $_GET['orderby'] ) ) {
            $query->set( 'orderby', [
                'menu_order' => 'ASC',
                'title' => 'ASC',
            ] );
        }

        $audience = $this->requested_audience();
        if ( '' !== $audience ) {
            $meta_query = (array) $query->get( 'meta_query' );
            $meta_query[] = [
                'key' => CustomFields::DOCUMENT_AUDIENCE,
                'value' => $audience,
                'compare' => '=',
            ];
            $query->set( 'meta_query', $meta_query );
        }
    }

    /**
     * Acción de fila «Ver archivo»: abre la descarga controlada en otra pestaña.
     *
     * @param   array    $actions Acciones de la fila.
     * @param   \WP_Post $post    Documento.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_row_actions( array $actions, \WP_Post $post ): array {
        if ( DocumentPostType::POST_TYPE_NAME !== $post->post_type ) {
            return $actions;
        }

        // El documento no tiene editor de contenido: el enlace de WPBakery no sirve.
        unset( $actions['edit_vc'] );

        if ( false === function_exists( 'wc_get_endpoint_url' ) ) {
            return $actions;
        }

        if ( 'trash' === $post->post_status || false === current_user_can( 'edit_post', $post->ID ) || null === $this->documents->get_file( $post->ID ) ) {
            return $actions;
        }

        $actions['sulayr_view_file'] = sprintf(
            '<a href="%s" target="_blank" rel="noopener">%s<span class="screen-reader-text"> %s</span></a>',
            esc_url( $this->documents->download_url( $post->ID ) ),
            esc_html__( 'Ver archivo', SULAYR_TEXTDOMAIN ),
            esc_html__( '(se abre en una pestaña nueva)', SULAYR_TEXTDOMAIN )
        );

        return $actions;
    }

    /**
     * Estilos mínimos del listado.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function print_styles(): void {
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( null === $screen || 'edit-' . DocumentPostType::POST_TYPE_NAME !== $screen->id ) {
            return;
        }

        echo '<style>'
            . '.sulayr-doc-warning{color:#b32d2e;font-weight:600}'
            . '.fixed .column-' . esc_attr( self::COLUMN_ORDER ) . '{width:4.5em}'
            . '.fixed .column-' . esc_attr( self::COLUMN_FILE ) . '{width:9em}'
            . '</style>';
    }

    /**
     * Columna «Visible para».
     *
     * @param   int $post_id ID del documento.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_audience( int $post_id ): void {
        $audience = (string) get_post_meta( $post_id, CustomFields::DOCUMENT_AUDIENCE, true );

        if ( DocumentFields::AUDIENCE_ALL === $audience ) {
            esc_html_e( 'Todos', SULAYR_TEXTDOMAIN );
            return;
        }

        if ( DocumentFields::AUDIENCE_USERS !== $audience ) {
            $this->render_warning( __( 'Sin definir', SULAYR_TEXTDOMAIN ), __( 'Nadie ve este documento. Edítalo y elige «Visible para».', SULAYR_TEXTDOMAIN ) );
            return;
        }

        $ids = array_map( 'intval', get_post_meta( $post_id, CustomFields::DOCUMENT_USER_INDEX ) );
        if ( empty( $ids ) ) {
            $this->render_warning( __( 'Sin destinatarios', SULAYR_TEXTDOMAIN ), __( 'Nadie ve este documento. Edítalo y elige al menos un deportista.', SULAYR_TEXTDOMAIN ) );
            return;
        }

        $users = get_users( [
            'include' => $ids,
            'orderby' => 'display_name',
            'fields' => [ 'display_name' ],
        ] );
        $names = wp_list_pluck( $users, 'display_name' );
        $shown = array_slice( $names, 0, self::MAX_NAMES_IN_TITLE );
        $title = implode( ', ', $shown );
        if ( count( $names ) > count( $shown ) ) {
            /* translators: %d: número de deportistas no listados */
            $title .= ' ' . sprintf( __( 'y %d más', SULAYR_TEXTDOMAIN ), count( $names ) - count( $shown ) );
        }

        $count = count( $ids );
        printf(
            '<span title="%s">%s</span>',
            esc_attr( $title ),
            esc_html(
                1 === $count && ! empty( $names )
                    ? $names[0]
                    /* translators: %d: número de deportistas */
                    : sprintf( _n( '%d deportista', '%d deportistas', $count, SULAYR_TEXTDOMAIN ), $count )
            )
        );
    }

    /**
     * Aviso en rojo dentro de una celda.
     *
     * @param   string $label   Texto visible.
     * @param   string $explain Explicación en el title.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_warning( string $label, string $explain ): void {
        printf(
            '<span class="sulayr-doc-warning" title="%s">%s</span>',
            esc_attr( $explain ),
            esc_html( $label )
        );
    }

    /**
     * Valor del filtro de visibilidad pedido, o cadena vacía.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function requested_audience(): string {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura del listado.
        $value = isset( $_GET[ self::FILTER_AUDIENCE ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER_AUDIENCE ] ) ) : '';

        return in_array( $value, [ DocumentFields::AUDIENCE_ALL, DocumentFields::AUDIENCE_USERS ], true ) ? $value : '';
    }
}
