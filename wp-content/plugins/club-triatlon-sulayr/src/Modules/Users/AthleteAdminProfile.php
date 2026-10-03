<?php
namespace ClubTriatlonSulayr\Modules\Users;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AthleteAdminProfile
 * Configuración de perfil de deportistas en el panel de administración
 * 
 */
class AthleteAdminProfile
{
    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init()
     * Registra los filtros de WordPress para la configuración de perfil de deportistas en el panel de administración
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function init(): void
    {
        add_filter( 'admin_head-user-edit.php', [ $this, 'remove_color_scheme_selector' ] );
        add_filter( 'user_contactmethods', [ $this, 'remove_contact_methods' ], 10, 1 );
    }   

    /**
     * remove_color_scheme_selector()
     * Quitar el selector de esquema de color
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remove_color_scheme_selector(): void
    { 
        if ( ! is_user_logged_in() ) {
            return;
        }

        // $user = wp_get_current_user();

        // if ( ! in_array( 'athlete', (array) $user->roles, true ) ) {
        //     return;
        // }
        ?>
        <style>
            .user-admin-color-wrap,
            .user-rich-editing-wrap,
            .user-comment-shortcuts-wrap,
            .user-admin-bar-front-wrap,
            h2:first-of-type { display:none !important; }
        </style>
        <?php
    }

    /**
     * remove_contact_methods()
     * Quitar los métodos de contacto
     *
     * @param   array  $methods  Métodos de contacto
     * @return  array  Métodos de contacto
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remove_contact_methods( array $methods ): array
    {
        unset( $methods['url'] );
        unset( $methods['facebook'] );
        unset( $methods['twitter'] );
        unset( $methods['linkedin'] );
        unset( $methods['instagram'] );
        unset( $methods['jabber'] );
        unset( $methods['yim'] );
        unset( $methods['aim'] );

        return $methods;
    }
}
