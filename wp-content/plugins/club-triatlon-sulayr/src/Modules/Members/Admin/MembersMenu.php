<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServicePostType;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;
use ClubTriatlonSulayr\Modules\Wallet\WalletBalancePage;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Menú «Socios» de wp-admin: listado y ficha de solo lectura.
 *
 * Los gestores de socios no tienen edit_users en multisite, así que la ficha
 * vive aquí y no en user-edit.php. Las pantallas de alta, solicitudes y
 * recibos se añaden en las partes B a D.
 */
class MembersMenu {

    public const SLUG = 'sulayr-socios';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 9 );
        add_action( 'admin_menu', [ $this, 'register_add_new_links' ], 11 );
        // Después de las páginas de opciones de ACF (prioridad 99).
        add_action( 'admin_menu', [ $this, 'order_submenu' ], 1000 );
        add_action( 'edit_user_profile', [ $this, 'render_profile_link' ], 1 );
    }

    /**
     * Menú principal y página del listado.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_menu_page(
            __( 'Socios', SULAYR_TEXTDOMAIN ),
            __( 'Socios', SULAYR_TEXTDOMAIN ),
            MemberRoles::MANAGE_MEMBERS,
            self::SLUG,
            [ $this, 'render' ],
            'dashicons-groups',
            3
        );

        add_submenu_page(
            self::SLUG,
            __( 'Socios', SULAYR_TEXTDOMAIN ),
            __( 'Socios', SULAYR_TEXTDOMAIN ),
            MemberRoles::MANAGE_MEMBERS,
            self::SLUG,
            [ $this, 'render' ]
        );
    }

    /**
     * Enlaces «Añadir» de los CPT que cuelgan de este menú.
     *
     * WordPress solo deja abrir post-new.php de un CPT colgado de otra página
     * si existe como submenú; sin esto, quien no tiene edit_posts (el gestor
     * de socios) ve «no tienes permisos».
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register_add_new_links(): void {
        $links = [
            ServicePostType::POST_TYPE_NAME => __( 'Añadir servicio', SULAYR_TEXTDOMAIN ),
            WalletPostType::POST_TYPE_NAME => __( 'Añadir movimiento', SULAYR_TEXTDOMAIN ),
        ];

        foreach ( $links as $post_type => $label ) {
            $object = get_post_type_object( $post_type );
            if ( null === $object ) {
                continue;
            }

            add_submenu_page( self::SLUG, $label, $label, $object->cap->create_posts, 'post-new.php?post_type=' . $post_type );
        }
    }

    /**
     * Ordena el submenú: socios, catálogo, monedero y, al final, migración y ajustes.
     * Lo que no esté en la lista se queda detrás, en su orden.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function order_submenu(): void {
        global $submenu;

        if ( empty( $submenu[ self::SLUG ] ) ) {
            return;
        }

        $order = [
            self::SLUG,
            'edit.php?post_type=' . ServicePostType::POST_TYPE_NAME,
            'post-new.php?post_type=' . ServicePostType::POST_TYPE_NAME,
            'edit.php?post_type=' . WalletPostType::POST_TYPE_NAME,
            'post-new.php?post_type=' . WalletPostType::POST_TYPE_NAME,
            WalletBalancePage::PAGE_SLUG,
            MigrationPage::PAGE_SLUG,
            MemberSettings::PAGE_SLUG,
        ];
        $position = array_flip( $order );

        // Clave de orden: posición en la lista y, para desempatar, la posición original.
        $keyed = [];
        foreach ( array_values( $submenu[ self::SLUG ] ) as $i => $item ) {
            $keyed[] = [ $position[ $item[2] ] ?? count( $order ), $i, $item ];
        }
        sort( $keyed );

        $submenu[ self::SLUG ] = array_column( $keyed, 2 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Reordenar el submenú propio.
    }

    /**
     * URL de la ficha de un socio.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function member_url( int $user_id ): string {
        return add_query_arg( [ 'page' => self::SLUG, 'socio' => $user_id ], admin_url( 'admin.php' ) );
    }

    /**
     * Enlace a la ficha desde la pantalla de edición de usuario.
     *
     * @param   \WP_User $user Usuario.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_profile_link( \WP_User $user ): void {
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) || false === in_array( 'athlete', (array) $user->roles, true ) ) {
            return;
        }

        printf(
            '<p><a class="button" href="%s">%s</a></p>',
            esc_url( self::member_url( $user->ID ) ),
            esc_html__( 'Ver ficha de socio (servicios, estados y registro de cambios)', SULAYR_TEXTDOMAIN )
        );
    }

    /**
     * Página: listado o ficha.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ) );
        }

        $member_id = isset( $_GET['socio'] ) ? absint( $_GET['socio'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo lectura.

        echo '<div class="wrap sulayr-members">';
        if ( $member_id > 0 ) {
            $this->render_member( $member_id );
        } else {
            $this->render_list();
        }
        echo '</div>';
    }

    /**
     * Listado de socios con filtro por estado.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_list(): void {
        $statuses = [
            CustomFields::STATUS_ACTIVE => StatusPeriods::label( CustomFields::STATUS_ACTIVE ),
            CustomFields::STATUS_FROZEN => StatusPeriods::label( CustomFields::STATUS_FROZEN ),
            CustomFields::STATUS_PENDING => StatusPeriods::label( CustomFields::STATUS_PENDING ),
            CustomFields::STATUS_LEFT => StatusPeriods::label( CustomFields::STATUS_LEFT ),
        ];
        $filter = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : CustomFields::STATUS_ACTIVE; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtro de lectura.
        if ( 'todos' !== $filter && false === isset( $statuses[ $filter ] ) ) {
            $filter = CustomFields::STATUS_ACTIVE;
        }

        $users = get_users( [ 'role' => 'athlete', 'number' => -1 ] );
        $rows = [];
        $counts = array_fill_keys( array_keys( $statuses ), 0 );
        $today = current_time( 'Y-m-d' );
        $types = MemberSettings::member_types();

        foreach ( $users as $user ) {
            $status = (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
            if ( '' === $status ) {
                $status = CustomFields::STATUS_ACTIVE;
            }
            if ( isset( $counts[ $status ] ) ) {
                $counts[ $status ]++;
            }
            if ( 'todos' !== $filter && $status !== $filter ) {
                continue;
            }

            $services = [];
            foreach ( MemberServices::active_on( $user->ID, $today ) as $row ) {
                $services[] = MemberServices::label( (int) $row->service_id, (string) $row->option_key );
            }

            $type = (string) get_user_meta( $user->ID, CustomFields::MEMBER_TYPE, true );
            $rows[] = [
                'id' => $user->ID,
                'number' => (string) get_user_meta( $user->ID, CustomFields::MEMBER_NUMBER, true ),
                'name' => HelperService::get_user_full_name( $user->ID ) ?: $user->display_name,
                'email' => $user->user_email,
                'type' => $types[ $type ] ?? $type,
                'status' => StatusPeriods::label( $status ),
                'services' => $services,
            ];
        }

        usort( $rows, function ( $a, $b ) {
            return (int) $a['number'] <=> (int) $b['number'] ?: strcmp( $a['name'], $b['name'] );
        } );

        echo '<h1>' . esc_html__( 'Socios', SULAYR_TEXTDOMAIN ) . '</h1>';

        echo '<ul class="subsubsub">';
        $links = [];
        foreach ( $statuses as $key => $label ) {
            $links[] = sprintf(
                '<li><a href="%s"%s>%s <span class="count">(%d)</span></a></li>',
                esc_url( add_query_arg( [ 'page' => self::SLUG, 'estado' => $key ], admin_url( 'admin.php' ) ) ),
                $key === $filter ? ' class="current"' : '',
                esc_html( $label ),
                (int) $counts[ $key ]
            );
        }
        $links[] = sprintf(
            '<li><a href="%s"%s>%s</a></li>',
            esc_url( add_query_arg( [ 'page' => self::SLUG, 'estado' => 'todos' ], admin_url( 'admin.php' ) ) ),
            'todos' === $filter ? ' class="current"' : '',
            esc_html__( 'Todos', SULAYR_TEXTDOMAIN )
        );
        echo implode( ' | ', $links ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
        echo '</ul><br class="clear">';

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No hay socios en este estado.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ( [ __( 'Nº', SULAYR_TEXTDOMAIN ), __( 'Nombre', SULAYR_TEXTDOMAIN ), __( 'Email', SULAYR_TEXTDOMAIN ), __( 'Tipo', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ), __( 'Servicios vigentes', SULAYR_TEXTDOMAIN ) ] as $i => $heading ) {
            printf( '<th scope="col"%s>%s</th>', 0 === $i ? ' style="width:50px"' : '', esc_html( $heading ) );
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            printf(
                '<tr><td>%s</td><td><a href="%s"><strong>%s</strong></a></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( $row['number'] ),
                esc_url( self::member_url( $row['id'] ) ),
                esc_html( $row['name'] ),
                esc_html( $row['email'] ),
                esc_html( $row['type'] ),
                esc_html( $row['status'] ),
                esc_html( implode( ', ', $row['services'] ) ?: '—' )
            );
        }
        echo '</tbody></table>';
    }

    /**
     * Ficha de solo lectura.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_member( int $user_id ): void {
        $user = get_userdata( $user_id );
        if ( false === $user || false === in_array( 'athlete', (array) $user->roles, true ) ) {
            echo '<p>' . esc_html__( 'Socio no encontrado.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $meta = function ( string $key ) use ( $user_id ): string {
            return (string) get_user_meta( $user_id, $key, true );
        };
        $date = function ( string $value ): string {
            if ( '' === $value || CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $value ) {
                return CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $value ? __( 'Desconocida', SULAYR_TEXTDOMAIN ) : '—';
            }
            $time = strtotime( $value );
            return false !== $time ? date_i18n( 'd/m/Y', $time ) : $value;
        };

        $types = MemberSettings::member_types();
        $type = $meta( CustomFields::MEMBER_TYPE );
        $status = $meta( CustomFields::USER_PROFILE_ATHLETE_STATUS ) ?: CustomFields::STATUS_ACTIVE;

        printf( '<p><a href="%s">← %s</a></p>', esc_url( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) ), esc_html__( 'Volver a socios', SULAYR_TEXTDOMAIN ) );
        printf( '<h1>%s</h1>', esc_html( HelperService::get_user_full_name( $user_id ) ?: $user->display_name ) );

        $data = [
            __( 'Nº de socio', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::MEMBER_NUMBER ) ?: '—',
            __( 'Tipo de socio', SULAYR_TEXTDOMAIN ) => $types[ $type ] ?? ( $type ?: '—' ),
            __( 'Estado', SULAYR_TEXTDOMAIN ) => StatusPeriods::label( $status ),
            __( 'Email', SULAYR_TEXTDOMAIN ) => $user->user_email ?: '—',
            __( 'Teléfono', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::USER_PROFILE_PHONE ) ?: '—',
            __( 'DNI', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::USER_PROFILE_DNI ) ?: '—',
            __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::USER_PROFILE_BIRTH ) ),
            __( 'Fecha de alta', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::USER_PROFILE_JOIN_DATE ) ),
            __( 'Fecha de baja', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::MEMBER_LEAVE_DATE ) ),
        ];
        $board_payment = $meta( CustomFields::MEMBER_BOARD_PAYMENT );
        if ( '' !== $board_payment && 0.0 !== (float) $board_payment ) {
            $data[ __( 'Retribución mensual (junta)', SULAYR_TEXTDOMAIN ) ] = number_format_i18n( (float) $board_payment, 2 ) . ' €';
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ( $data as $label => $value ) {
            printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
        }
        echo '</tbody></table>';

        if ( current_user_can( 'edit_user', $user_id ) ) {
            printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( get_edit_user_link( $user_id ) ), esc_html__( 'Editar usuario', SULAYR_TEXTDOMAIN ) );
        }

        echo '<h2>' . esc_html__( 'Servicios', SULAYR_TEXTDOMAIN ) . '</h2>';
        $services = MemberServices::for_user( $user_id );
        if ( empty( $services ) ) {
            echo '<p>' . esc_html__( 'Sin servicios registrados.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            $today = current_time( 'Y-m-d' );
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Servicio', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Desde', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Hasta', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Precio hoy', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Origen', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
            foreach ( $services as $row ) {
                $service = ServiceCatalog::get( (int) $row->service_id );
                $price = null !== $service ? ServiceCatalog::price_for( $service, $type ?: ServiceCatalog::GENERAL, $today ) : null;
                printf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html( MemberServices::label( (int) $row->service_id, (string) $row->option_key ) ),
                    esc_html( $date( (string) $row->start_date ) ),
                    esc_html( null === $row->end_date ? __( 'Vigente', SULAYR_TEXTDOMAIN ) : $date( (string) $row->end_date ) ),
                    esc_html( null !== $price ? number_format_i18n( $price['amount'], 2 ) . ' €' : '—' ),
                    esc_html( (string) $row->origin )
                );
            }
            echo '</tbody></table>';
        }

        echo '<h2>' . esc_html__( 'Estados', SULAYR_TEXTDOMAIN ) . '</h2>';
        $periods = StatusPeriods::for_user( $user_id );
        if ( empty( $periods ) ) {
            echo '<p>' . esc_html__( 'Sin periodos registrados.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Estado', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Desde', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Hasta', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Motivo', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
            foreach ( $periods as $row ) {
                printf(
                    '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html( StatusPeriods::label( (string) $row->status ) ),
                    esc_html( null === $row->start_date ? __( 'Desconocida', SULAYR_TEXTDOMAIN ) : $date( (string) $row->start_date ) ),
                    esc_html( null === $row->end_date ? __( 'En curso', SULAYR_TEXTDOMAIN ) : $date( (string) $row->end_date ) ),
                    esc_html( (string) $row->reason )
                );
            }
            echo '</tbody></table>';
        }

        if ( current_user_can( MemberRoles::VIEW_LOG ) ) {
            $this->render_log( $user_id );
        }
    }

    /**
     * Registro de cambios del socio.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_log( int $user_id ): void {
        echo '<h2>' . esc_html__( 'Registro de cambios', SULAYR_TEXTDOMAIN ) . '</h2>';

        $entries = MemberLog::get_for_user( $user_id );
        if ( empty( $entries ) ) {
            echo '<p>' . esc_html__( 'Sin cambios registrados.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th style="width:140px">' . esc_html__( 'Fecha', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Quién', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Área', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Campo', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Antes', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Después', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Nota', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
        foreach ( $entries as $entry ) {
            $author = (int) $entry->author_id > 0 ? get_userdata( (int) $entry->author_id ) : false;
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $entry->created_at ) ) ),
                esc_html( false !== $author ? $author->display_name : __( 'Sistema', SULAYR_TEXTDOMAIN ) ),
                esc_html( (string) $entry->area ),
                esc_html( MemberLog::field_label( (string) $entry->field ) ),
                esc_html( (string) $entry->old_value ),
                esc_html( (string) $entry->new_value ),
                esc_html( (string) $entry->note )
            );
        }
        echo '</tbody></table>';
    }
}
