<?php
namespace CTSulayrTheme\Services;

/**
 * HelperService
 *
 * @package	ct_sulayr_theme
 */
class HelperService
{
    const DEVICE_IOS     = "ios";
    const DEVICE_ANDROID = "android";
    const DEVICE_OTHER   = "other";

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
    }

    /**
     * add_body_classes
     *
     * @param   array   $classes
     * @return  array
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function add_body_classes( $classes ): array
    {
        if ( is_user_logged_in() && is_admin_bar_showing() ) {
            $classes[] = 'is-logged';     
        }

        return $classes;
    }
    
    /**
     * restrict_users
     *
     * @return  void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function restrict_users(): void
    {
        $this->set_editor_menu_capabilities();
        add_action( 'admin_menu', [ $this, 'disable_theme_admin_menu' ] );
    }

    /**
     * set_editor_menu_capabilities
     *
     * @return  void
     * @access 	private
     * @package	ct_sulayr_theme
     */
    public function set_editor_menu_capabilities( ): void
    {
        //TODO: Refactorizar el código de editor de menú para ubicarlo en un lugar más apropiado
        $role_object = get_role( 'editor' );
        $role_object->add_cap( 'edit_theme_options' );

        $current_user = wp_get_current_user();
        $allowed_roles = "administrator";
        if ( !in_array( $allowed_roles, $current_user->roles ) ) {
            remove_submenu_page( 'themes.php', 'themes.php' );
        }
    }

    /**
     * disable_theme_admin_menu
     *
     * @return  void
     * @access 	private
     * @package	ct_sulayr_theme
     */
    public function disable_theme_admin_menu( ): void
    {
        $current_user = wp_get_current_user();
        $allowed_roles = "administrator";
        if ( !in_array( $allowed_roles, $current_user->roles ) ) {
            remove_submenu_page( 'themes.php', 'themes.php' );
        }
    }

    /**
     * get_device
     *
     * @return  string current user device
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function get_device( ): string
    {
        if ( false !== stristr( $_SERVER['HTTP_USER_AGENT'], 'android' ) ) {
            $device = $this::DEVICE_ANDROID;
        } else if ( false !== stristr( $_SERVER['HTTP_USER_AGENT'], 'ipad' ) || false !== stristr( $_SERVER['HTTP_USER_AGENT'], 'iphone' ) || false !== strstr( $_SERVER['HTTP_USER_AGENT'], 'iphone' ) ) {
            $device = $this::DEVICE_IOS;
        } else {
            $device = $this::DEVICE_OTHER;
        }
        return $device;
    }
}
