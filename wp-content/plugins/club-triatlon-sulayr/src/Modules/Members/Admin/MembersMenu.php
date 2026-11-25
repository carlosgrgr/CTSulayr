<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServicePostType;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;
use ClubTriatlonSulayr\Modules\Wallet\WalletBalancePage;
use ClubTriatlonSulayr\Modules\Members\Join\JoinRequests;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\MembersDailyTask;
use ClubTriatlonSulayr\Modules\Members\MemberSummary;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberFields;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberTasks;
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
 * vive aquí y no en user-edit.php. Solicitudes y alta están en RequestsPage
 * (parte B); los recibos, en ReceiptsPage, y los listados, en ListsPage
 * (parte D).
 */
class MembersMenu {

    public const SLUG = 'sulayr-socios';

    public const ACTION_TASK_DONE = 'sulayr_member_task_done';
    private const ACTION_RUN_DAILY = 'sulayr_members_run_daily';

    private MemberActions $actions;
    private ReceiptsPage $receipts;
    private ListsPage $lists;

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
        add_action( 'admin_post_' . self::ACTION_TASK_DONE, [ $this, 'handle_task_done' ] );
        add_action( 'admin_post_' . self::ACTION_RUN_DAILY, [ $this, 'handle_run_daily' ] );
        new BrandStyles();
        $this->actions = new MemberActions();
        $this->receipts = new ReceiptsPage();
        $this->lists = new ListsPage();
    }

    /**
     * Ejecuta la tarea diaria a mano (PROD no tiene WP-CLI). Es idempotente.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_run_daily(): void {
        check_admin_referer( self::ACTION_RUN_DAILY );
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }

        $r = MembersDailyTask::run();
        RequestsPage::set_notice( 'success', sprintf(
            /* translators: 1: cambios aplicados, 2: vueltas de congelación, 3: altas activadas, 4: solicitudes caducadas, 5: retribuciones de la junta, 6: recibos generados, 7: cobros con tarjeta, 8: cobros fallidos. */
            __( 'Tarea diaria ejecutada: %1$d cambios aplicados, %2$d vueltas de congelación, %3$d altas activadas, %4$d solicitudes caducadas, %5$d retribuciones de la junta apuntadas, %6$d recibos generados y %7$d cobros con tarjeta (%8$d fallidos).', SULAYR_TEXTDOMAIN ),
            $r['applied'],
            $r['returned'],
            $r['updated'],
            $r['purged'],
            $r['board'],
            $r['receipts'],
            $r['tpv']['charged'],
            $r['tpv']['failed']
        ) );
        wp_safe_redirect( add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) ) );
        exit;
    }

    /**
     * Marca un paso como hecho desde la ficha.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_task_done(): void {
        $task_id = isset( $_POST['task_id'] ) ? absint( $_POST['task_id'] ) : 0;
        check_admin_referer( self::ACTION_TASK_DONE . '_' . $task_id );
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }

        $task = MemberTasks::mark_done( $task_id );
        $url = null !== $task ? self::member_url( (int) $task->user_id ) . '#pasos' : add_query_arg( 'page', self::SLUG, admin_url( 'admin.php' ) );
        // Desde «Tareas pendientes» se vuelve a esa lista.
        if ( ! empty( $_POST['back'] ) ) {
            $url = esc_url_raw( wp_unslash( $_POST['back'] ) );
        }
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Menú principal y página del listado.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        // El globo de solicitudes también en el menú padre. El primer submenú se queda sin él.
        add_menu_page(
            __( 'Socios', SULAYR_TEXTDOMAIN ),
            __( 'Socios', SULAYR_TEXTDOMAIN ) . RequestsPage::bubble(),
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
     * Ordena el submenú: socios, catálogo, monedero y, al final, ajustes.
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
            RequestsPage::SLUG,
            RequestsPage::NEW_SLUG,
            ReceiptsPage::SLUG,
            ListsPage::TASKS_SLUG,
            ListsPage::COACH_SLUG,
            ListsPage::WELCOME_SLUG,
            'edit.php?post_type=' . ServicePostType::POST_TYPE_NAME,
            'post-new.php?post_type=' . ServicePostType::POST_TYPE_NAME,
            'edit.php?post_type=' . WalletPostType::POST_TYPE_NAME,
            'post-new.php?post_type=' . WalletPostType::POST_TYPE_NAME,
            WalletBalancePage::PAGE_SLUG,
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

        // Los campos antiguos (tarifa, natación, estado...) están ocultos: esto es lo que vale.
        $summary = MemberSummary::for_user( $user->ID );
        $types = MemberSettings::member_types();
        printf(
            '<div class="notice notice-info inline"><p><strong>%1$s</strong> %2$s · %3$s · %4$s</p><p>%5$s <a class="button" href="%6$s">%7$s</a></p></div>',
            esc_html__( 'Socio:', SULAYR_TEXTDOMAIN ),
            esc_html( StatusPeriods::label( $summary['status'] ) ),
            esc_html( $types[ $summary['type'] ] ?? ( $summary['type'] ?: '—' ) ),
            esc_html( implode( ', ', $summary['services'] ) ?: __( 'sin servicios', SULAYR_TEXTDOMAIN ) ),
            esc_html__( 'Servicios, tipo, estado, congelaciones y bajas se cambian en la ficha de socio.', SULAYR_TEXTDOMAIN ),
            esc_url( self::member_url( $user->ID ) ),
            esc_html__( 'Ver ficha de socio', SULAYR_TEXTDOMAIN )
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

        echo '<div class="wrap sulayr-members sulayr-brand">';
        RequestsPage::print_notice();
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

        // D36: antiguos socios con más años de baja que el plazo de conservación (solo aviso).
        $retention_years = max( 1, (int) MemberSettings::get( 'retention_former_years' ) );
        $retention_limit = gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -' . $retention_years . ' years' ) );
        $retention_only = ! empty( $_GET['conservacion'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtro de lectura.
        $retention_count = 0;
        if ( $retention_only ) {
            $filter = CustomFields::STATUS_LEFT;
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
            $leave = (string) get_user_meta( $user->ID, CustomFields::MEMBER_LEAVE_DATE, true );
            $over_retention = CustomFields::STATUS_LEFT === $status && 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $leave ) && $leave < $retention_limit;
            if ( $over_retention ) {
                $retention_count++;
            }
            if ( 'todos' !== $filter && $status !== $filter ) {
                continue;
            }
            if ( $retention_only && false === $over_retention ) {
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

        echo '<p class="sulayr-eyebrow">' . esc_html( (string) MemberSettings::get( 'club_name' ) ) . '</p><h1>' . esc_html__( 'Socios', SULAYR_TEXTDOMAIN ) . '</h1>';

        if ( $retention_count > 0 ) {
            printf(
                '<div class="notice notice-warning inline"><p>%s <a href="%s">%s</a></p></div>',
                esc_html( sprintf( _n( '%1$d antiguo socio lleva más de %2$d años de baja (plazo de conservación de datos). El sistema no borra nada.', '%1$d antiguos socios llevan más de %2$d años de baja (plazo de conservación de datos). El sistema no borra nada.', $retention_count, SULAYR_TEXTDOMAIN ), $retention_count, $retention_years ) ),
                esc_url( add_query_arg( [ 'page' => self::SLUG, 'conservacion' => 1 ], admin_url( 'admin.php' ) ) ),
                esc_html__( 'Ver cuáles', SULAYR_TEXTDOMAIN )
            );
        }

        $last = get_option( MembersDailyTask::LAST_RUN_OPTION );
        printf(
            '<form method="post" action="%1$s" class="sulayr-daily"><p class="description">%2$s %3$s <input type="hidden" name="action" value="%4$s">%5$s<button type="submit" class="button button-small">%6$s</button></p></form>',
            esc_url( admin_url( 'admin-post.php' ) ),
            esc_html__( 'Tarea diaria (cambios, congelaciones, bajas y altas del día; retribución de la junta, recibos del mes y cobro con tarjeta):', SULAYR_TEXTDOMAIN ),
            esc_html( is_array( $last ) ? sprintf( __( 'última ejecución el %s.', SULAYR_TEXTDOMAIN ), date_i18n( 'd/m/Y H:i', strtotime( (string) $last['at'] ) ) ) : __( 'aún no se ha ejecutado.', SULAYR_TEXTDOMAIN ) ),
            esc_attr( self::ACTION_RUN_DAILY ),
            wp_nonce_field( self::ACTION_RUN_DAILY, '_wpnonce', true, false ),
            esc_html__( 'Ejecutar ahora', SULAYR_TEXTDOMAIN )
        );

        $pending = JoinRequests::count_pending();
        if ( $pending > 0 ) {
            printf(
                '<div class="notice notice-info inline"><p><a href="%s">%s</a></p></div>',
                esc_url( RequestsPage::url() ),
                esc_html( sprintf( _n( 'Hay %d solicitud de alta pendiente.', 'Hay %d solicitudes de alta pendientes.', $pending, SULAYR_TEXTDOMAIN ), $pending ) )
            );
        }

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

        echo '<table class="wp-list-table widefat fixed striped sulayr-t-members"><thead><tr>';
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
        $number = $meta( CustomFields::MEMBER_NUMBER );
        printf( '<p class="sulayr-eyebrow">%s</p><h1>%s</h1>', esc_html( '' !== $number ? sprintf( __( 'Socio nº %s', SULAYR_TEXTDOMAIN ), $number ) : __( 'Socio', SULAYR_TEXTDOMAIN ) ), esc_html( HelperService::get_user_full_name( $user_id ) ?: $user->display_name ) );

        $data = [
            __( 'Nº de socio', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::MEMBER_NUMBER ) ?: '—',
            __( 'Tipo de socio', SULAYR_TEXTDOMAIN ) => $types[ $type ] ?? ( $type ?: '—' ),
            __( 'Estado', SULAYR_TEXTDOMAIN ) => StatusPeriods::label( $status ),
            __( 'Email', SULAYR_TEXTDOMAIN ) => $user->user_email ?: '—',
            __( 'Teléfono', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::USER_PROFILE_PHONE ) ?: '—',
            __( 'DNI', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::USER_PROFILE_DNI ) ?: '—',
            __( 'Sexo', SULAYR_TEXTDOMAIN ) => MemberFields::sex_label( $meta( CustomFields::MEMBER_SEX ) ),
            __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::USER_PROFILE_BIRTH ) ),
            __( 'Fecha de alta', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::USER_PROFILE_JOIN_DATE ) ),
            __( 'Fecha de baja', SULAYR_TEXTDOMAIN ) => $date( $meta( CustomFields::MEMBER_LEAVE_DATE ) ),
            __( 'Talla de camiseta', SULAYR_TEXTDOMAIN ) => $meta( CustomFields::MEMBER_TSHIRT_SIZE ) ?: '—',
        ];
        $guardian = array_filter( [
            $meta( CustomFields::MEMBER_GUARDIAN . '_name' ),
            $meta( CustomFields::MEMBER_GUARDIAN . '_dni' ),
            $meta( CustomFields::MEMBER_GUARDIAN . '_phone' ),
            $meta( CustomFields::MEMBER_GUARDIAN . '_email' ),
        ] );
        if ( ! empty( $guardian ) ) {
            $data[ __( 'Tutor', SULAYR_TEXTDOMAIN ) ] = implode( ' · ', $guardian );
        }
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
            echo '<table class="widefat striped sulayr-t-dates"><thead><tr><th>' . esc_html__( 'Servicio', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Desde', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Hasta', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Precio hoy', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Origen', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
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
            echo '<table class="widefat striped sulayr-t-dates"><thead><tr><th>' . esc_html__( 'Estado', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Desde', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Hasta', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Motivo', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
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

        $this->actions->render( $user_id );
        $this->receipts->render_member_block( $user_id );
        $this->render_tasks( $user_id );
        $this->lists->render_member_block( $user_id );
        $this->render_consents( $user_id );
        $this->render_requests( $user_id );

        if ( current_user_can( MemberRoles::VIEW_LOG ) ) {
            $this->render_log( $user_id );
        }
    }

    /**
     * Pasos del socio, con botón para marcarlos como hechos.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_tasks( int $user_id ): void {
        echo '<h2 id="pasos">' . esc_html__( 'Pasos', SULAYR_TEXTDOMAIN ) . '</h2>';
        $tasks = MemberTasks::for_user( $user_id );
        if ( empty( $tasks ) ) {
            echo '<p>' . esc_html__( 'Sin pasos.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Paso', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'De', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Creado', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Hecho', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
        foreach ( $tasks as $task ) {
            if ( null === $task->done_at ) {
                $done = sprintf(
                    '<form method="post" action="%1$s">%2$s<input type="hidden" name="action" value="%3$s"><input type="hidden" name="task_id" value="%4$d"><button type="submit" class="button button-small">%5$s</button></form>',
                    esc_url( admin_url( 'admin-post.php' ) ),
                    wp_nonce_field( self::ACTION_TASK_DONE . '_' . (int) $task->id, '_wpnonce', true, false ),
                    esc_attr( self::ACTION_TASK_DONE ),
                    (int) $task->id,
                    esc_html__( 'Marcar hecho', SULAYR_TEXTDOMAIN )
                );
            } else {
                $by = (int) $task->done_by > 0 ? get_userdata( (int) $task->done_by ) : false;
                $done = esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $task->done_at ) ) . ( false !== $by ? ' · ' . $by->display_name : '' ) );
            }
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( (string) $task->label ),
                esc_html( (string) $task->context ),
                esc_html( date_i18n( 'd/m/Y', strtotime( (string) $task->created_at ) ) ),
                $done // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
            );
        }
        echo '</tbody></table>';
    }

    /**
     * Consentimientos del socio (todas las filas, la última manda).
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_consents( int $user_id ): void {
        echo '<h2>' . esc_html__( 'Consentimientos', SULAYR_TEXTDOMAIN ) . '</h2>';
        $rows = MemberConsents::rows( $user_id );
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Sin consentimientos guardados.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Consentimiento', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Resultado', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Registrado por', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
        foreach ( array_reverse( $rows ) as $row ) {
            $by = (int) $row->created_by > 0 ? get_userdata( (int) $row->created_by ) : false;
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) ),
                esc_html( MemberConsents::label( (string) $row->type ) ),
                esc_html( MemberConsents::describe( (bool) $row->accepted, (string) $row->version, (string) $row->given_by ) ),
                esc_html( false !== $by ? $by->display_name : __( 'Formulario público', SULAYR_TEXTDOMAIN ) )
            );
        }
        echo '</tbody></table>';
    }

    /**
     * Solicitudes de alta aprobadas para este socio.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_requests( int $user_id ): void {
        $links = [];
        foreach ( JoinRequests::list( JoinRequests::STATUS_APPROVED ) as $request ) {
            if ( (int) $request->user_id === $user_id ) {
                $links[] = sprintf( '<a href="%s">#%d</a> (%s)', esc_url( RequestsPage::url( (int) $request->id ) ), (int) $request->id, esc_html( date_i18n( 'd/m/Y', strtotime( (string) $request->created_at ) ) ) );
            }
        }
        if ( ! empty( $links ) ) {
            echo '<p>' . esc_html__( 'Solicitudes de alta:', SULAYR_TEXTDOMAIN ) . ' ' . implode( ', ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
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
