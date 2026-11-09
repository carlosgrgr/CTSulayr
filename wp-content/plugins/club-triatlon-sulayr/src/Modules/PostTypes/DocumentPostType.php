<?php

namespace ClubTriatlonSulayr\Modules\PostTypes;

use ClubTriatlonSulayr\Modules\Documents\DocumentCapabilities;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT Documentos: cada post = un documento publicado para los deportistas.
 *
 * No es público: el archivo solo se sirve desde Mi cuenta tras comprobar permisos.
 * Las capacidades son propias (capability_type sulayr_document), no las de "post".
 */
class DocumentPostType extends PostType {

    public const POST_TYPE_NAME = 'sulayr_document';

    private const NOTICE_QUERY_ARG = 'sulayr_document_notice';
    private const NOTICE_MISSING_TITLE = 'missing_title';

    protected string $singular = 'Documento';
    protected string $plural = 'Documentos';
    protected bool $public = false;
    protected bool $exclude_from_search = true;
    protected bool $publicly_queryable = false;
    protected bool $show_ui = true;
    protected bool $show_in_nav_menus = false;
    protected bool $show_in_menu = true;
    protected int $menu_position = 58;
    protected string $menu_icon = 'dashicons-media-document';
    protected bool $has_archive = false;
    protected bool $query_var = false;
    protected bool $show_in_rest = false;
    protected array $support = [ 'title', 'page-attributes' ];

    /** True si el último guardado devolvió el documento a borrador por falta de título. */
    private bool $title_was_missing = false;

    /**
     * __construct()
     *
     * @param   array $args Arguments to create the post type.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( array $args = [] ) {
        parent::__construct( $args );

        add_filter( 'wp_insert_post_data', [ $this, 'keep_untitled_as_draft' ], 10, 2 );
        add_filter( 'redirect_post_location', [ $this, 'add_missing_title_notice_arg' ], 10, 2 );
        add_action( 'admin_notices', [ $this, 'render_missing_title_notice' ] );
        add_filter( 'enter_title_here', [ $this, 'title_placeholder' ], 10, 2 );
        add_filter( 'post_updated_messages', [ $this, 'updated_messages' ] );
    }

    /**
     * Registra el CPT sin hacer flush_rewrite_rules en cada carga.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_custom_post_type(): void {
        $args = [
            'labels' => $this->get_labels(),
            'public' => $this->public,
            'exclude_from_search' => $this->exclude_from_search,
            'publicly_queryable' => $this->publicly_queryable,
            'show_ui' => $this->show_ui,
            'show_in_nav_menus' => $this->show_in_nav_menus,
            'show_in_menu' => $this->show_in_menu,
            'menu_position' => $this->menu_position,
            'menu_icon' => $this->menu_icon,
            'hierarchical' => $this->hierarchical,
            'supports' => $this->support,
            'taxonomies' => $this->taxonomies,
            'has_archive' => $this->has_archive,
            'rewrite' => false,
            'query_var' => $this->query_var,
            'can_export' => $this->can_export,
            'delete_with_user' => $this->delete_with_user,
            'show_in_rest' => $this->show_in_rest,
            'capability_type' => DocumentCapabilities::CAPABILITY_TYPE,
            'map_meta_cap' => true,
        ];

        register_post_type( self::POST_TYPE_NAME, $args );
    }

    /**
     * Etiquetas en español (las de la clase base están en inglés).
     *
     * @return 	array
     * @access 	protected
     * @package	club-triatlon-sulayr
     */
    protected function get_labels(): array {
        return [
            'name' => __( 'Documentos', SULAYR_TEXTDOMAIN ),
            'singular_name' => __( 'Documento', SULAYR_TEXTDOMAIN ),
            'menu_name' => __( 'Documentos Sulayr', SULAYR_TEXTDOMAIN ),
            'name_admin_bar' => __( 'Documento', SULAYR_TEXTDOMAIN ),
            'add_new' => __( 'Añadir documento', SULAYR_TEXTDOMAIN ),
            'add_new_item' => __( 'Añadir documento', SULAYR_TEXTDOMAIN ),
            'edit_item' => __( 'Editar documento', SULAYR_TEXTDOMAIN ),
            'new_item' => __( 'Nuevo documento', SULAYR_TEXTDOMAIN ),
            'view_item' => __( 'Ver documento', SULAYR_TEXTDOMAIN ),
            'view_items' => __( 'Ver documentos', SULAYR_TEXTDOMAIN ),
            'search_items' => __( 'Buscar documentos', SULAYR_TEXTDOMAIN ),
            'not_found' => __( 'No hay documentos', SULAYR_TEXTDOMAIN ),
            'not_found_in_trash' => __( 'No hay documentos en la papelera', SULAYR_TEXTDOMAIN ),
            'all_items' => __( 'Todos los documentos', SULAYR_TEXTDOMAIN ),
            'attributes' => __( 'Orden', SULAYR_TEXTDOMAIN ),
            'filter_items_list' => __( 'Filtrar documentos', SULAYR_TEXTDOMAIN ),
            'items_list_navigation' => __( 'Navegación de documentos', SULAYR_TEXTDOMAIN ),
            'items_list' => __( 'Lista de documentos', SULAYR_TEXTDOMAIN ),
            'item_published' => __( 'Documento publicado.', SULAYR_TEXTDOMAIN ),
            'item_reverted_to_draft' => __( 'Documento devuelto a borrador.', SULAYR_TEXTDOMAIN ),
            'item_scheduled' => __( 'Documento programado.', SULAYR_TEXTDOMAIN ),
            'item_updated' => __( 'Documento actualizado.', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Un documento sin título no se publica ni se programa: se guarda como borrador.
     *
     * @param   array $data    Datos del post (slashed).
     * @param   array $postarr Datos originales.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function keep_untitled_as_draft( array $data, array $postarr ): array {
        if ( self::POST_TYPE_NAME !== $data['post_type'] ) {
            return $data;
        }

        if ( false === in_array( $data['post_status'], [ 'publish', 'future' ], true ) ) {
            return $data;
        }

        $title = trim( wp_strip_all_tags( wp_unslash( (string) $data['post_title'] ) ) );
        if ( '' !== $title ) {
            return $data;
        }

        $data['post_status'] = 'draft';
        $this->title_was_missing = true;

        return $data;
    }

    /**
     * Lleva el aviso de falta de título a la pantalla de edición tras guardar.
     *
     * @param   string $location URL de redirección.
     * @param   int    $post_id  ID del post.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_missing_title_notice_arg( string $location, int $post_id ): string {
        if ( false === $this->title_was_missing || self::POST_TYPE_NAME !== get_post_type( $post_id ) ) {
            return $location;
        }

        // El mensaje nativo diría "publicado"; se sustituye por el aviso propio.
        $location = remove_query_arg( 'message', $location );

        return add_query_arg( self::NOTICE_QUERY_ARG, self::NOTICE_MISSING_TITLE, $location );
    }

    /**
     * Aviso en la pantalla de edición.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_missing_title_notice(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- solo lectura de un aviso.
        $notice = isset( $_GET[ self::NOTICE_QUERY_ARG ] ) ? sanitize_key( wp_unslash( $_GET[ self::NOTICE_QUERY_ARG ] ) ) : '';
        if ( self::NOTICE_MISSING_TITLE !== $notice ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( null === $screen || self::POST_TYPE_NAME !== $screen->post_type ) {
            return;
        }

        printf(
            '<div class="notice notice-error"><p>%s</p></div>',
            esc_html__( 'El documento no tiene título. Se ha guardado como borrador y no es visible para los deportistas.', SULAYR_TEXTDOMAIN )
        );
    }

    /**
     * Texto de ayuda del campo de título.
     *
     * @param   string   $text Texto por defecto.
     * @param   \WP_Post $post Post en edición.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function title_placeholder( string $text, \WP_Post $post ): string {
        if ( self::POST_TYPE_NAME !== $post->post_type ) {
            return $text;
        }

        return __( 'Título del documento', SULAYR_TEXTDOMAIN );
    }

    /**
     * Mensajes tras guardar, sin enlaces "Ver" (el CPT no tiene URL pública).
     *
     * @param   array $messages Mensajes por tipo de post.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function updated_messages( array $messages ): array {
        $messages[ self::POST_TYPE_NAME ] = [
            0 => '',
            1 => __( 'Documento actualizado.', SULAYR_TEXTDOMAIN ),
            4 => __( 'Documento actualizado.', SULAYR_TEXTDOMAIN ),
            6 => __( 'Documento publicado.', SULAYR_TEXTDOMAIN ),
            7 => __( 'Documento guardado.', SULAYR_TEXTDOMAIN ),
            8 => __( 'Documento enviado.', SULAYR_TEXTDOMAIN ),
            9 => __( 'Documento programado.', SULAYR_TEXTDOMAIN ),
            10 => __( 'Borrador del documento actualizado.', SULAYR_TEXTDOMAIN ),
        ];

        return $messages;
    }
}
