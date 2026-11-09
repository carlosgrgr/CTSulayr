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
        add_filter( 'user_contactmethods', [ $this, 'remove_third_party_contact_methods' ], PHP_INT_MAX, 1 );

        foreach ( [ 'load-user-edit.php', 'load-profile.php', 'load-user-new.php' ] as $screen_hook ) {
            add_action( $screen_hook, [ $this, 'remove_third_party_profile_sections' ] );
        }
    }

    /**
     * Hooks de la pantalla de usuario donde otros plugins pintan o guardan campos.
     */
    private const PROFILE_HOOKS = [
        'personal_options',
        'profile_personal_options',
        'show_user_profile',
        'edit_user_profile',
        'user_new_form',
        'personal_options_update',
        'edit_user_profile_update',
    ];

    /**
     * remove_third_party_profile_sections()
     * En la edición de usuarios solo se muestran los campos de WordPress y los de
     * los plugins del club (club-triatlon-sulayr, sulayr-reservas y ACF, que pinta
     * los campos de este plugin). Quita las secciones de WooCommerce, Yoast, etc.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remove_third_party_profile_sections(): void
    {
        global $wp_filter;

        foreach ( self::PROFILE_HOOKS as $hook_name ) {
            if ( empty( $wp_filter[ $hook_name ] ) ) {
                continue;
            }

            foreach ( $wp_filter[ $hook_name ]->callbacks as $priority => $callbacks ) {
                foreach ( $callbacks as $callback ) {
                    $file = $this->get_callback_file( $callback['function'] );

                    if ( null !== $file && false === $this->is_allowed_file( $file ) ) {
                        remove_action( $hook_name, $callback['function'], $priority );
                    }
                }
            }
        }
    }

    /**
     * remove_third_party_contact_methods()
     * WordPress no trae métodos de contacto propios y el club no usa ninguno:
     * los que lleguen aquí son de otros plugins (p. ej. redes sociales de Yoast).
     *
     * @param   array  $methods  Métodos de contacto
     * @return  array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remove_third_party_contact_methods( $methods ): array
    {
        return [];
    }

    /**
     * Fichero donde está definido un callback, o null si no se puede saber.
     *
     * @param   mixed  $callback
     * @return  string|null
     */
    private function get_callback_file( $callback ): ?string
    {
        try {
            if ( is_string( $callback ) && false !== strpos( $callback, '::' ) ) {
                $callback = explode( '::', $callback, 2 );
            }

            if ( is_array( $callback ) && 2 === count( $callback ) ) {
                $reflection = new \ReflectionMethod( $callback[0], $callback[1] );
            } elseif ( $callback instanceof \Closure || is_string( $callback ) ) {
                $reflection = new \ReflectionFunction( $callback );
            } elseif ( is_object( $callback ) && method_exists( $callback, '__invoke' ) ) {
                $reflection = new \ReflectionMethod( $callback, '__invoke' );
            } else {
                return null;
            }
        } catch ( \ReflectionException $e ) {
            return null;
        }

        $file = $reflection->getFileName();

        return $file ? wp_normalize_path( $file ) : null;
    }

    /**
     * El fichero pertenece al núcleo de WordPress o a un plugin permitido.
     *
     * @param   string  $file
     * @return  bool
     */
    private function is_allowed_file( string $file ): bool
    {
        $plugins_dir = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
        $allowed = [
            wp_normalize_path( ABSPATH . 'wp-admin/' ),
            wp_normalize_path( ABSPATH . WPINC . '/' ),
            $plugins_dir . 'club-triatlon-sulayr/',
            $plugins_dir . 'sulayr-reservas/',
            $plugins_dir . 'advanced-custom-fields-pro/',
        ];

        foreach ( $allowed as $dir ) {
            if ( 0 === strpos( $file, $dir ) ) {
                return true;
            }
        }

        return false;
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
