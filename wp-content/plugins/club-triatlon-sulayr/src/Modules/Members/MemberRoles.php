<?php
namespace ClubTriatlonSulayr\Modules\Members;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Roles «Gestor de socios», «Gestor de tienda» y «Gestor de ranking» y sus
 * capacidades (spec de altas §10; SPEC-RANKING-INTERNO R14).
 *
 * Los dos roles se suman al rol del usuario (normalmente athlete). Las
 * comprobaciones usan capacidades, no nombres de rol. add_role() no modifica
 * un rol existente, así que las capacidades se sincronizan con add_cap() y se
 * vuelven a aplicar al cambiar VERSION, igual que DocumentCapabilities.
 */
class MemberRoles {

    public const ROLE_MEMBER_MANAGER = 'sulayr_member_manager';
    public const ROLE_SHOP_MANAGER = 'sulayr_shop_manager';
    public const ROLE_RANKING_MANAGER = 'sulayr_ranking_manager';

    /** Menú Socios: solicitudes, fichas, catálogo, ajustes, recibos, listados, monedero, tareas. */
    public const MANAGE_MEMBERS = 'sulayr_manage_members';
    public const VIEW_LOG = 'sulayr_view_member_log';
    /** Marca del gestor de tienda; solo sirve para abrirle wp-admin. */
    public const MANAGE_SHOP = 'sulayr_manage_shop';
    /** Menú Ranking: competiciones, resultados, deportistas, registro y ajustes del ranking interno. */
    public const MANAGE_RANKING = 'sulayr_manage_ranking';

    /** capability_type de los CPT de socios. */
    public const SERVICE_CAP_TYPE = [ 'sulayr_service', 'sulayr_services' ];
    public const WALLET_CAP_TYPE = [ 'sulayr_wallet', 'sulayr_wallets' ];

    private const VERSION = 2;
    private const VERSION_OPTION = 'sulayr_member_roles_version';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'init', [ $this, 'maybe_sync' ], 20 );
    }

    /**
     * Primitivas de un capability_type con map_meta_cap.
     *
     * @param   string[] $type [ singular, plural ].
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function post_type_caps( array $type ): array {
        list( , $plural ) = $type;

        return [
            "edit_{$plural}",
            "edit_others_{$plural}",
            "edit_published_{$plural}",
            "edit_private_{$plural}",
            "publish_{$plural}",
            "delete_{$plural}",
            "delete_others_{$plural}",
            "delete_published_{$plural}",
            "delete_private_{$plural}",
            "read_private_{$plural}",
        ];
    }

    /**
     * Capacidades de WooCommerce para pedidos, productos, cupones e informes.
     * Sin manage_woocommerce (ajustes) ni edit_users.
     *
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function shop_caps(): array {
        $caps = [ 'view_woocommerce_reports', 'upload_files' ];

        foreach ( [ 'product', 'shop_order', 'shop_coupon' ] as $type ) {
            $caps = array_merge( $caps, [
                "edit_{$type}",
                "read_{$type}",
                "delete_{$type}",
                "edit_{$type}s",
                "edit_others_{$type}s",
                "publish_{$type}s",
                "read_private_{$type}s",
                "delete_{$type}s",
                "delete_private_{$type}s",
                "delete_published_{$type}s",
                "delete_others_{$type}s",
                "edit_private_{$type}s",
                "edit_published_{$type}s",
                "manage_{$type}_terms",
                "edit_{$type}_terms",
                "delete_{$type}_terms",
                "assign_{$type}_terms",
            ] );
        }

        return $caps;
    }

    /**
     * Capacidades que gestiona esta clase en cada rol.
     *
     * @return 	array<string, string[]>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function role_map(): array {
        $members = array_merge(
            [ self::MANAGE_MEMBERS, self::VIEW_LOG ],
            self::post_type_caps( self::SERVICE_CAP_TYPE ),
            self::post_type_caps( self::WALLET_CAP_TYPE )
        );

        return [
            'administrator' => array_merge( $members, [ self::MANAGE_RANKING ] ),
            self::ROLE_MEMBER_MANAGER => array_merge( [ 'read', 'view_admin_dashboard' ], $members ),
            self::ROLE_SHOP_MANAGER => array_merge( [ 'read', 'view_admin_dashboard', self::MANAGE_SHOP ], self::shop_caps() ),
            self::ROLE_RANKING_MANAGER => [ 'read', 'view_admin_dashboard', self::MANAGE_RANKING ],
        ];
    }

    /**
     * Nombres visibles de los roles adicionales.
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function extra_roles(): array {
        return [
            self::ROLE_MEMBER_MANAGER => __( 'Gestor de socios', SULAYR_TEXTDOMAIN ),
            self::ROLE_SHOP_MANAGER => __( 'Gestor de tienda', SULAYR_TEXTDOMAIN ),
            self::ROLE_RANKING_MANAGER => __( 'Gestor de ranking', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * True si el usuario puede usar wp-admin por ser gestor o administrador.
     *
     * @param   \WP_User|null $user Usuario; por defecto, el actual.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function can_use_admin( ?\WP_User $user = null ): bool {
        $user = $user ?? wp_get_current_user();
        if ( false === $user->exists() ) {
            return false;
        }

        return user_can( $user, 'manage_options' )
            || user_can( $user, self::MANAGE_MEMBERS )
            || user_can( $user, self::MANAGE_SHOP )
            || user_can( $user, self::MANAGE_RANKING );
    }

    /**
     * Sincroniza si cambió la versión o falta algún rol.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_sync(): void {
        if (
            self::VERSION === (int) get_option( self::VERSION_OPTION )
            && null !== get_role( self::ROLE_MEMBER_MANAGER )
            && null !== get_role( self::ROLE_SHOP_MANAGER )
            && null !== get_role( self::ROLE_RANKING_MANAGER )
        ) {
            return;
        }

        self::sync();
    }

    /**
     * Crea los roles que falten, añade las capacidades del mapa y quita las
     * capacidades de socios que ya no estén en él.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sync(): void {
        foreach ( self::extra_roles() as $role_name => $label ) {
            if ( null === get_role( $role_name ) ) {
                add_role( $role_name, $label, [ 'read' => true ] );
            }
        }

        $managed = array_unique( array_merge(
            [ self::MANAGE_MEMBERS, self::VIEW_LOG, self::MANAGE_SHOP, self::MANAGE_RANKING ],
            self::post_type_caps( self::SERVICE_CAP_TYPE ),
            self::post_type_caps( self::WALLET_CAP_TYPE )
        ) );

        $all_found = true;
        foreach ( self::role_map() as $role_name => $caps ) {
            $role = get_role( $role_name );
            if ( null === $role ) {
                $all_found = false;
                continue;
            }

            foreach ( $caps as $cap ) {
                if ( empty( $role->capabilities[ $cap ] ) ) {
                    $role->add_cap( $cap );
                }
            }

            foreach ( array_keys( $role->capabilities ) as $cap ) {
                if ( in_array( $cap, $managed, true ) && false === in_array( $cap, $caps, true ) ) {
                    $role->remove_cap( $cap );
                }
            }
        }

        if ( true === $all_found ) {
            update_option( self::VERSION_OPTION, self::VERSION );
        }
    }
}
