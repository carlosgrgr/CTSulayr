<?php
namespace CTSulayrTheme\Services;

/**
 * CommentsService
 *
 * @package	ct_sulayr_theme
 */
class CommentsService
{

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct()
    {        
        $this->init();
    }
    
    /**
     * init
     *
     * @return  void
     * @access 	private
     * @package	ct_sulayr_theme
     */
    private function init(): void
    {
        add_action( 'admin_init', [ $this, 'disable_comments_post_types_support' ] );
        add_action( 'admin_menu', [ $this, 'disable_comments_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'disable_comments_admin_menu_redirect' ] );
        add_action( 'admin_bar_menu', [ $this, 'remove_nodes_admin_bar' ], 999 );
        add_action( 'init', [ $this, 'disable_comments_admin_bar' ] );
        add_action( 'admin_init', [ $this, 'disable_comments_dashboard' ] );

        add_filter( 'comments_array', [ $this, 'disable_comments_hide_existing_comments' ], 10, 1 );
        add_filter( 'the_comments', [ $this, 'remove_queried_comments' ], 10 );
        add_filter( 'comments_open', '__return_false', 20 );
        add_filter( 'pings_open', '__return_false', 20 );
    }

    /**
     * disable_comments_post_types_support
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_post_types_support(): void
    {
        $post_types = get_post_types();
        foreach ( $post_types as $post_type ) {
            if ( true === post_type_supports( $post_type, 'comments' ) ) {
                remove_post_type_support( $post_type, 'comments' );
                remove_post_type_support( $post_type, 'trackbacks' );
            }
        }
    }

    /**
     * disable_comments_admin_menu
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_admin_menu(): void
    {
        remove_menu_page( 'edit-comments.php' );
        remove_submenu_page( 'options-general.php', 'options-discussion.php' );
    }

    /**
     * disable_comments_admin_menu_redirect
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_admin_menu_redirect(): void
    {
        global $pagenow;
        if ( true === in_array( $pagenow, [ 'edit-comments.php', 'options-discussion.php' ] ) ) {
            wp_redirect( admin_url() );
            exit;
        }
    }

    /**
     * remove_nodes_admin_bar
     *
     * @param   \WP_Admin_Bar   $wp_admin_bar
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function remove_nodes_admin_bar( \WP_Admin_Bar $wp_admin_bar ): void
    {
        $wp_admin_bar->remove_node( 'comments' );
    }

    /**
     * disable_comments_admin_bar
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_admin_bar(): void
    {
        if ( true === is_admin_bar_showing() ) {
            remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
        }
    }

    /**
     * disable_comments_dashboard
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_dashboard(): void
    {
        remove_meta_box( 'dashboard_recent_comments', 'dashboard', 'normal' );
    }

    /**
     * disable_comments_hide_existing_comments
     *
     * @param   array   $comments
     * @return  array
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_comments_hide_existing_comments( $comments ): array
    {
        return [];
    }
    
    /**
     * remove_queried_comments
     *
     * @return  array
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function remove_queried_comments(): array
    {
        return [];
    }
}
