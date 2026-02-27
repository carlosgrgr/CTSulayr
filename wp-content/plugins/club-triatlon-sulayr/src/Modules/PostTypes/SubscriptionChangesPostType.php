<?php

namespace ClubTriatlonSulayr\Modules\PostTypes;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * CPT for subscription/plan change history.
 * Title is auto-generated: "{user display name} - {change date}".
 */
class SubscriptionChangesPostType extends PostType {

    public const POST_TYPE_NAME = 'sulayr_subscription_changes';

    protected string $singular = 'Cambio de tarifa';
    protected string $plural = 'Cambios de tarifa';
    protected bool $public = false;
    protected bool $exclude_from_search = true;
    protected bool $publicly_queryable = false;
    protected bool $show_ui = true;
    protected bool $show_in_nav_menus = false;
    protected bool $show_in_menu = true;
    protected int $menu_position = 57;
    protected string $menu_icon = 'dashicons-admin-generic';
    protected bool $has_archive = false;
    protected bool $show_in_rest = false;
    protected array $support = [ 'title', 'custom-fields' ];

    public function __construct( array $args = [] ) {
        $this->rewrite['slug'] = '';
        parent::__construct( $args );
        add_action( 'save_post_' . self::POST_TYPE_NAME, [ $this, 'maybe_set_title' ], 10, 3 );
    }

    public function add_custom_post_type(): void {
        $args = [
            'labels'              => $this->get_labels(),
            'public'              => $this->public,
            'exclude_from_search' => $this->exclude_from_search,
            'publicly_queryable'  => $this->publicly_queryable,
            'show_ui'             => $this->show_ui,
            'show_in_nav_menus'   => $this->show_in_nav_menus,
            'show_in_menu'        => $this->show_in_menu,
            'menu_position'       => $this->menu_position,
            'menu_icon'           => $this->menu_icon,
            'hierarchical'        => $this->hierarchical,
            'supports'            => $this->support,
            'taxonomies'          => $this->taxonomies,
            'has_archive'         => $this->has_archive,
            'rewrite'             => $this->rewrite,
            'query_var'           => $this->query_var,
            'can_export'          => $this->can_export,
            'delete_with_user'    => $this->delete_with_user,
            'show_in_rest'        => $this->show_in_rest,
            'map_meta_cap'        => $this->map_meta_cap,
        ];
        register_post_type( self::POST_TYPE_NAME, $args );
    }

    /**
     * Set post title to "{user name} - {date}" when saving if not already set.
     *
     * @param int      $post_id
     * @param \WP_Post $post
     * @param bool     $update
     */
    public function maybe_set_title( int $post_id, \WP_Post $post, bool $update ): void {
        if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
            return;
        }
        $user_id = (int) get_post_meta( $post_id, CustomFields::SUB_CHANGE_USER, true );
        $date    = get_post_meta( $post_id, CustomFields::SUB_CHANGE_DATE, true );
        if ( ! $user_id || ! $date ) {
            return;
        }
        $user = get_userdata( $user_id );
        $name = $user ? ( $user->display_name ?: $user->user_login ) : (string) $user_id;
        $title = $name . ' - ' . ( $date ? date_i18n( get_option( 'date_format' ), strtotime( $date ) ) : '' );
        if ( $post->post_title !== $title ) {
            remove_action( 'save_post_' . self::POST_TYPE_NAME, [ $this, 'maybe_set_title' ], 10 );
            wp_update_post( [
                'ID'         => $post_id,
                'post_title' => $title,
            ] );
            add_action( 'save_post_' . self::POST_TYPE_NAME, [ $this, 'maybe_set_title' ], 10, 3 );
        }
    }
}
