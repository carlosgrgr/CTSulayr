<?php

namespace ClubTriatlonSulayr\Modules\PostTypes;

use ClubTriatlonSulayr\Modules\Members\Admin\MembersMenu;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT Monedero: cada post = un movimiento de monedero del club.
 */
class WalletPostType extends PostType {

    public const POST_TYPE_NAME = 'sulayr_wallet';

    protected string $singular = 'Monedero';
    protected string $plural = 'Monedero';
    protected bool $public = false;
    protected bool $exclude_from_search = true;
    protected bool $publicly_queryable = false;
    protected bool $show_ui = true;
    protected bool $show_in_nav_menus = false;
    protected bool $show_in_menu = true;
    protected int $menu_position = 56;
    protected string $menu_icon = 'dashicons-money-alt';
    protected bool $has_archive = false;
    protected bool $show_in_rest = false;
    protected array $support = [ 'title', 'custom-fields' ];

    public function __construct( array $args = [] ) {
        $this->rewrite['slug'] = '';
        parent::__construct( $args );
    }

    /**
     * Etiquetas en español (las genéricas de PostType están en inglés: «All Monedero»).
     */
    protected function get_labels(): array {
        return array_merge( parent::get_labels(), [
            'name' => __( 'Movimientos del monedero', SULAYR_TEXTDOMAIN ),
            'singular_name' => __( 'Movimiento del monedero', SULAYR_TEXTDOMAIN ),
            'menu_name' => __( 'Monedero', SULAYR_TEXTDOMAIN ),
            'all_items' => __( 'Movimientos del monedero', SULAYR_TEXTDOMAIN ),
            'add_new' => __( 'Añadir movimiento', SULAYR_TEXTDOMAIN ),
            'add_new_item' => __( 'Añadir movimiento', SULAYR_TEXTDOMAIN ),
            'edit_item' => __( 'Editar movimiento', SULAYR_TEXTDOMAIN ),
            'new_item' => __( 'Nuevo movimiento', SULAYR_TEXTDOMAIN ),
            'search_items' => __( 'Buscar movimientos', SULAYR_TEXTDOMAIN ),
            'not_found' => __( 'No hay movimientos', SULAYR_TEXTDOMAIN ),
            'not_found_in_trash' => __( 'No hay movimientos en la papelera', SULAYR_TEXTDOMAIN ),
            'filter_items_list' => __( 'Filtrar movimientos', SULAYR_TEXTDOMAIN ),
            'items_list_navigation' => __( 'Navegación de movimientos', SULAYR_TEXTDOMAIN ),
            'items_list' => __( 'Lista de movimientos', SULAYR_TEXTDOMAIN ),
            'item_published' => __( 'Movimiento guardado', SULAYR_TEXTDOMAIN ),
            'item_updated' => __( 'Movimiento actualizado', SULAYR_TEXTDOMAIN ),
        ] );
    }

    /**
     * Registra el CPT sin hacer flush_rewrite_rules en cada carga.
     */
    public function add_custom_post_type(): void {
        $args = [
            'labels' => $this->get_labels(),
            'public' => $this->public,
            'exclude_from_search' => $this->exclude_from_search,
            'publicly_queryable' => $this->publicly_queryable,
            'show_ui' => $this->show_ui,
            'show_in_nav_menus' => $this->show_in_nav_menus,
            // Bajo el menú Socios, con capacidades propias (spec de altas §9.4): solo administrador y gestor de socios.
            'show_in_menu' => MembersMenu::SLUG,
            'capability_type' => MemberRoles::WALLET_CAP_TYPE,
            'menu_position' => $this->menu_position,
            'menu_icon' => $this->menu_icon,
            'hierarchical' => $this->hierarchical,
            'supports' => $this->support,
            'taxonomies' => $this->taxonomies,
            'has_archive' => $this->has_archive,
            'rewrite' => $this->rewrite,
            'query_var' => $this->query_var,
            'can_export' => $this->can_export,
            'delete_with_user' => $this->delete_with_user,
            'show_in_rest' => $this->show_in_rest,
            'map_meta_cap' => $this->map_meta_cap,
        ];

        register_post_type( self::POST_TYPE_NAME, $args );
    }
}
