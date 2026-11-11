<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Modules\Members\MemberRoles;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Acceso de los gestores a wp-admin y casillas de roles adicionales (D56).
 *
 * - WooCommerce no redirige a quien puede gestionar socios o tienda.
 * - En la ficha de usuario, solo un administrador ve y guarda las casillas
 *   «Gestor de socios» y «Gestor de tienda». Se guardan en profile_update,
 *   después de que WordPress aplique el desplegable de rol, que borra los
 *   roles adicionales.
 */
class ManagerAccess {

    private const NONCE = 'sulayr_extra_roles_nonce';
    private const FIELD = 'sulayr_extra_roles';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_filter( 'woocommerce_prevent_admin_access', [ $this, 'allow_managers' ] );
        add_action( 'show_user_profile', [ $this, 'render_checkboxes' ], 5 );
        add_action( 'edit_user_profile', [ $this, 'render_checkboxes' ], 5 );
        add_action( 'profile_update', [ $this, 'save_checkboxes' ], 20 );
    }

    /**
     * woocommerce_prevent_admin_access
     *
     * @param   bool $prevent Valor de WooCommerce.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function allow_managers( $prevent ) {
        if ( true === MemberRoles::can_use_admin() ) {
            return false;
        }

        return $prevent;
    }

    /**
     * Casillas en la ficha de usuario.
     *
     * @param   \WP_User $user Usuario editado.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_checkboxes( \WP_User $user ): void {
        if ( false === $this->can_assign( $user->ID ) ) {
            return;
        }

        wp_nonce_field( self::NONCE . '_' . $user->ID, self::NONCE );
        ?>
        <h2><?php esc_html_e( 'Roles adicionales del club', SULAYR_TEXTDOMAIN ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><?php esc_html_e( 'Gestión', SULAYR_TEXTDOMAIN ); ?></th>
                <td>
                    <input type="hidden" name="<?php echo esc_attr( self::FIELD ); ?>[]" value="">
                    <?php foreach ( MemberRoles::extra_roles() as $role => $label ) : ?>
                        <label style="display:block;margin-bottom:4px">
                            <input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>[]" value="<?php echo esc_attr( $role ); ?>" <?php checked( in_array( $role, (array) $user->roles, true ) ); ?>>
                            <?php echo esc_html( $label ); ?>
                        </label>
                    <?php endforeach; ?>
                    <p class="description"><?php esc_html_e( 'Se suman al rol principal. Un deportista que sea gestor sigue usando Mi cuenta y además entra en wp-admin.', SULAYR_TEXTDOMAIN ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Guarda las casillas.
     *
     * @param   int $user_id Usuario editado.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function save_checkboxes( $user_id ): void {
        $user_id = (int) $user_id;

        if ( ! isset( $_POST[ self::NONCE ], $_POST[ self::FIELD ] ) ) {
            return;
        }

        if ( false === wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE . '_' . $user_id ) ) {
            return;
        }

        if ( false === $this->can_assign( $user_id ) ) {
            return;
        }

        $user = get_userdata( $user_id );
        if ( false === $user ) {
            return;
        }

        $wanted = array_map( 'sanitize_key', (array) wp_unslash( $_POST[ self::FIELD ] ) );

        // Evita volver a procesar si wp_update_user dispara profile_update de nuevo.
        unset( $_POST[ self::FIELD ] );

        foreach ( array_keys( MemberRoles::extra_roles() ) as $role ) {
            $has = in_array( $role, (array) $user->roles, true );
            $want = in_array( $role, $wanted, true );

            if ( $want && false === $has ) {
                $user->add_role( $role );
            } elseif ( false === $want && $has ) {
                $user->remove_role( $role );
            }
        }
    }

    /**
     * Solo un administrador del sitio puede asignar los roles adicionales.
     *
     * @param   int $user_id Usuario editado.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function can_assign( int $user_id ): bool {
        return current_user_can( 'manage_options' ) && current_user_can( 'promote_user', $user_id );
    }
}
