<?php

namespace ClubTriatlonSulayr\Modules\Taxonomies;

use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Categorías de documentos (sulayr_doc_category).
 *
 * Privada: sin URL, sin REST y sin caja nativa en la edición. El documento
 * elige una sola categoría con un campo ACF de tipo radio (DocumentFields).
 */
class DocumentCategoryTaxonomy extends Taxonomy {

    public const TAXONOMY_NAME = 'sulayr_doc_category';

    protected string $singular = 'Categoría';
    protected string $plural = 'Categorías';
    protected bool $public = false;
    protected bool $publicly_queryable = false;
    protected bool $show_in_nav_menus = false;
    protected bool $show_in_rest = false;
    protected bool $show_tagcloud = false;
    // La edición rápida y en bloque usaría casillas y permitiría varias categorías.
    protected bool $show_in_quick_edit = false;
    protected bool $show_admin_column = true;
    protected bool $hierarchical = true;

    /**
     * __construct()
     *
     * @param   array $args Argumentos de la clase base.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( array $args = [] ) {
        parent::__construct( $args );
    }

    /**
     * Registra la taxonomía. La clase base no admite meta_box_cb ni rewrite false.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_custom_taxonomy(): void {
        register_taxonomy( self::TAXONOMY_NAME, DocumentPostType::POST_TYPE_NAME, [
            'labels' => $this->get_spanish_labels(),
            'public' => $this->public,
            'publicly_queryable' => $this->publicly_queryable,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => $this->show_in_nav_menus,
            'show_in_rest' => $this->show_in_rest,
            'show_tagcloud' => $this->show_tagcloud,
            'show_in_quick_edit' => $this->show_in_quick_edit,
            'show_admin_column' => $this->show_admin_column,
            'hierarchical' => $this->hierarchical,
            'meta_box_cb' => false,
            'query_var' => false,
            'rewrite' => false,
            'capabilities' => [
                'manage_terms' => 'edit_others_sulayr_documents',
                'edit_terms' => 'edit_others_sulayr_documents',
                'delete_terms' => 'edit_others_sulayr_documents',
                'assign_terms' => 'edit_sulayr_documents',
            ],
        ] );
    }

    /**
     * Etiquetas en español (las de la clase base están en inglés).
     *
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function get_spanish_labels(): array {
        return [
            'name' => __( 'Categorías', SULAYR_TEXTDOMAIN ),
            'singular_name' => __( 'Categoría', SULAYR_TEXTDOMAIN ),
            'menu_name' => __( 'Categorías', SULAYR_TEXTDOMAIN ),
            'all_items' => __( 'Todas las categorías', SULAYR_TEXTDOMAIN ),
            'edit_item' => __( 'Editar categoría', SULAYR_TEXTDOMAIN ),
            'view_item' => __( 'Ver categoría', SULAYR_TEXTDOMAIN ),
            'update_item' => __( 'Actualizar categoría', SULAYR_TEXTDOMAIN ),
            'add_new_item' => __( 'Añadir categoría', SULAYR_TEXTDOMAIN ),
            'new_item_name' => __( 'Nombre de la categoría', SULAYR_TEXTDOMAIN ),
            'parent_item' => __( 'Categoría superior', SULAYR_TEXTDOMAIN ),
            'parent_item_colon' => __( 'Categoría superior:', SULAYR_TEXTDOMAIN ),
            'search_items' => __( 'Buscar categorías', SULAYR_TEXTDOMAIN ),
            'not_found' => __( 'No hay categorías', SULAYR_TEXTDOMAIN ),
            'no_terms' => __( 'Sin categoría', SULAYR_TEXTDOMAIN ),
            'back_to_items' => __( '← Volver a las categorías', SULAYR_TEXTDOMAIN ),
            'filter_by_item' => __( 'Filtrar por categoría', SULAYR_TEXTDOMAIN ),
        ];
    }
}
