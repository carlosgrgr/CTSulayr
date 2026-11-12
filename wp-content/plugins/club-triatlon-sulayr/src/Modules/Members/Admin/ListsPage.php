<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Modules\Members\CoachList;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberTasks;
use ClubTriatlonSulayr\Modules\Members\WelcomePack;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Listados de seguimiento (§9): «Tareas pendientes» (9.1), «Entrenador»
 * (9.2, D54) y «Welcome pack» (9.3, D55), más el bloque de welcome pack de
 * la ficha del socio.
 */
class ListsPage {

    public const TASKS_SLUG = 'sulayr-socios-tareas';
    public const COACH_SLUG = 'sulayr-socios-entrenador';
    public const WELCOME_SLUG = 'sulayr-socios-welcome-pack';

    private const ACTION = 'sulayr_member_lists';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
    }

    /**
     * Submenús.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        $pending = count( MemberTasks::pending() );
        $badge = $pending > 0 ? ' <span class="awaiting-mod">' . (int) $pending . '</span>' : '';
        add_submenu_page( MembersMenu::SLUG, __( 'Tareas pendientes', SULAYR_TEXTDOMAIN ), __( 'Tareas pendientes', SULAYR_TEXTDOMAIN ) . $badge, MemberRoles::MANAGE_MEMBERS, self::TASKS_SLUG, [ $this, 'render_tasks' ] );
        add_submenu_page( MembersMenu::SLUG, __( 'Listado del entrenador', SULAYR_TEXTDOMAIN ), __( 'Entrenador', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_MEMBERS, self::COACH_SLUG, [ $this, 'render_coach' ] );
        add_submenu_page( MembersMenu::SLUG, __( 'Welcome pack', SULAYR_TEXTDOMAIN ), __( 'Welcome pack', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_MEMBERS, self::WELCOME_SLUG, [ $this, 'render_welcome' ] );
    }

    /**
     * Acciones: marcar enviado, enviar por email, registrar o borrar entrega.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        check_admin_referer( self::ACTION );
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }

        $do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $back = admin_url( 'admin.php?page=' . self::COACH_SLUG );
        $done = '';

        switch ( $do ) {
            case 'enviado':
                CoachList::mark_sent( CoachList::current() );
                $result = true;
                $done = __( 'Listado marcado como enviado.', SULAYR_TEXTDOMAIN );
                break;
            case 'email':
                $result = CoachList::email();
                $done = __( 'Listado enviado por email y marcado como enviado.', SULAYR_TEXTDOMAIN );
                break;
            case 'entrega':
                $user = get_userdata( $user_id );
                $result = false === $user || false === in_array( 'athlete', (array) $user->roles, true )
                    ? new \WP_Error( 'sulayr_member', __( 'Socio no encontrado.', SULAYR_TEXTDOMAIN ) )
                    : WelcomePack::deliver( $user_id, array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['items'] ?? [] ) ), sanitize_text_field( wp_unslash( $_POST['delivered_on'] ?? '' ) ) );
                $done = __( 'Entrega registrada.', SULAYR_TEXTDOMAIN );
                $back = MembersMenu::member_url( $user_id ) . '#welcome-pack';
                break;
            case 'borrar_entrega':
                $result = WelcomePack::delete( $user_id, (string) preg_replace( '/[^A-Za-z0-9]/', '', (string) wp_unslash( $_POST['pack_id'] ?? '' ) ) ) ? true : new \WP_Error( 'sulayr_pack', __( 'Entrega no encontrada.', SULAYR_TEXTDOMAIN ) );
                $done = __( 'Entrega borrada.', SULAYR_TEXTDOMAIN );
                $back = MembersMenu::member_url( $user_id ) . '#welcome-pack';
                break;
            default:
                $result = new \WP_Error( 'sulayr_action', __( 'Acción desconocida.', SULAYR_TEXTDOMAIN ) );
        }

        RequestsPage::set_notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : $done );
        wp_safe_redirect( $back );
        exit;
    }

    /**
     * «Tareas pendientes» de todos los socios, con filtro por paso y contexto.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_tasks(): void {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filtros de lectura.
        $label = sanitize_text_field( wp_unslash( $_GET['paso'] ?? '' ) );
        $context = sanitize_key( wp_unslash( $_GET['contexto'] ?? '' ) );
        // phpcs:enable

        echo '<div class="wrap sulayr-members sulayr-brand">';
        RequestsPage::print_notice();
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Socios · Seguimiento', SULAYR_TEXTDOMAIN ) . '</p><h1>' . esc_html__( 'Tareas pendientes', SULAYR_TEXTDOMAIN ) . '</h1>';

        printf( '<form method="get" class="sulayr-inline"><input type="hidden" name="page" value="%s"><select name="paso"><option value="">%s</option>', esc_attr( self::TASKS_SLUG ), esc_html__( 'Todos los pasos', SULAYR_TEXTDOMAIN ) );
        foreach ( MemberTasks::pending_labels() as $option ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $option, $label, false ), esc_html( $option ) );
        }
        printf( '</select> <select name="contexto"><option value="">%s</option>', esc_html__( 'Altas, cambios y bajas', SULAYR_TEXTDOMAIN ) );
        foreach ( [ MemberTasks::CONTEXT_JOIN, MemberTasks::CONTEXT_CHANGE, MemberTasks::CONTEXT_LEAVE ] as $option ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $option ), selected( $option, $context, false ), esc_html( MemberTasks::context_label( $option ) ) );
        }
        printf( '</select> <button class="button">%s</button></form>', esc_html__( 'Filtrar', SULAYR_TEXTDOMAIN ) );

        $tasks = MemberTasks::pending( $label, $context );
        if ( empty( $tasks ) ) {
            echo '<p>' . esc_html__( 'No hay pasos pendientes.', SULAYR_TEXTDOMAIN ) . '</p></div>';
            return;
        }

        $back = add_query_arg( [ 'page' => self::TASKS_SLUG, 'paso' => $label, 'contexto' => $context ], admin_url( 'admin.php' ) );
        echo '<table class="wp-list-table widefat fixed striped"><thead><tr><th>' . esc_html__( 'Socio', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Paso', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'De', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Creado', SULAYR_TEXTDOMAIN ) . '</th><th></th></tr></thead><tbody>';
        foreach ( $tasks as $task ) {
            printf(
                '<tr><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td><td><form method="post" action="%s">%s<input type="hidden" name="action" value="%s"><input type="hidden" name="task_id" value="%d"><input type="hidden" name="back" value="%s"><button type="submit" class="button button-small">%s</button></form></td></tr>',
                esc_url( MembersMenu::member_url( (int) $task->user_id ) . '#pasos' ),
                esc_html( $this->name( (int) $task->user_id ) ),
                esc_html( (string) $task->label ),
                esc_html( MemberTasks::context_label( (string) $task->context ) ),
                esc_html( date_i18n( 'd/m/Y', strtotime( (string) $task->created_at ) ) ),
                esc_url( admin_url( 'admin-post.php' ) ),
                wp_nonce_field( MembersMenu::ACTION_TASK_DONE . '_' . (int) $task->id, '_wpnonce', true, false ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML de WordPress.
                esc_attr( MembersMenu::ACTION_TASK_DONE ),
                (int) $task->id,
                esc_attr( $back ),
                esc_html__( 'Marcar hecho', SULAYR_TEXTDOMAIN )
            );
        }
        echo '</tbody></table></div>';
    }

    /**
     * «Entrenador»: listado de hoy con novedades, texto para WhatsApp, marcar
     * como enviado y enviar por email (D54).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_coach(): void {
        $current = CoachList::current();
        $changes = CoachList::changes( $current );
        $sent = CoachList::last_sent();

        echo '<div class="wrap sulayr-members sulayr-brand">';
        RequestsPage::print_notice();
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Socios · Entrenamiento', SULAYR_TEXTDOMAIN ) . '</p><h1>' . esc_html__( 'Listado del entrenador', SULAYR_TEXTDOMAIN ) . '</h1>';
        if ( null === $sent ) {
            echo '<p class="description">' . esc_html__( 'Aún no se ha marcado como enviado: las novedades se marcarán desde el primer envío.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            $by = get_userdata( (int) $sent['by'] );
            printf(
                '<p class="description">%s</p>',
                esc_html( sprintf( __( 'Último envío: %1$s por %2$s. Novedades desde entonces: %3$d altas, %4$d cambios de modalidad y %5$d bajas.', SULAYR_TEXTDOMAIN ), date_i18n( 'd/m/Y H:i', strtotime( (string) $sent['at'] ) ), false !== $by ? $by->display_name : '—', count( $changes['new'] ), count( $changes['changed'] ), count( $changes['gone'] ) ) )
            );
        }

        if ( empty( $current ) ) {
            echo '<p>' . esc_html__( 'Ningún socio activo tiene un servicio del listado del entrenador.', SULAYR_TEXTDOMAIN ) . '</p>';
        }
        foreach ( CoachList::grouped( $current ) as $group => $items ) {
            printf( '<h2>%s (%d)</h2><table class="widefat striped"><tbody>', esc_html( $group ), count( $items ) );
            foreach ( $items as $user_id => $item ) {
                $mark = CoachList::mark( (int) $user_id, $changes );
                printf( '<tr%s><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>', '' !== $mark ? ' class="sulayr-coach-new"' : '', esc_url( MembersMenu::member_url( (int) $user_id ) ), esc_html( $item['name'] ), esc_html( $item['email'] ), esc_html( trim( $mark, ' ()' ) ) );
            }
            echo '</tbody></table>';
        }
        if ( ! empty( $changes['gone'] ) ) {
            echo '<h2>' . esc_html__( 'Ya no están', SULAYR_TEXTDOMAIN ) . '</h2><ul>';
            foreach ( $changes['gone'] as $item ) {
                printf( '<li>%s (%s)</li>', esc_html( (string) $item['name'] ), esc_html( (string) $item['group'] ) );
            }
            echo '</ul>';
        }

        echo '<h2>' . esc_html__( 'Texto para WhatsApp', SULAYR_TEXTDOMAIN ) . '</h2>';
        printf( '<textarea id="sulayr-coach-text" class="large-text code" rows="12" readonly>%s</textarea>', esc_textarea( CoachList::text( $current ) ) );
        printf(
            '<p><button type="button" class="button" onclick="var t=document.getElementById(\'sulayr-coach-text\');t.select();(navigator.clipboard?navigator.clipboard.writeText(t.value):Promise.reject()).catch(function(){document.execCommand(\'copy\');});this.textContent=\'%s\';">%s</button> ',
            esc_js( __( 'Copiado', SULAYR_TEXTDOMAIN ) ),
            esc_html__( 'Copiar texto', SULAYR_TEXTDOMAIN )
        );
        $this->button( 'enviado', __( 'Marcar como enviado', SULAYR_TEXTDOMAIN ), [] );
        echo ' ';
        $to = trim( str_replace( [ "\r", "\n" ], [ '', ', ' ], (string) MemberSettings::get( 'coach_emails' ) ) );
        $this->button( 'email', __( 'Enviar por email', SULAYR_TEXTDOMAIN ), [], 'button-primary sulayr-cta', sprintf( __( '¿Enviar el listado a %1$s y al email del club (%2$s)?', SULAYR_TEXTDOMAIN ), '' !== $to ? $to : '—', (string) MemberSettings::get( 'club_email' ) ) );
        echo '</p></div>';
    }

    /**
     * «Welcome pack»: socios activos sin entrega y últimas entregas.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_welcome(): void {
        echo '<div class="wrap sulayr-members sulayr-brand">';
        RequestsPage::print_notice();
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Socios · Bienvenida', SULAYR_TEXTDOMAIN ) . '</p><h1>' . esc_html__( 'Welcome pack', SULAYR_TEXTDOMAIN ) . '</h1>';

        $without = WelcomePack::members_without();
        printf( '<h2>%s</h2>', esc_html( sprintf( __( 'Socios activos sin ninguna entrega (%d)', SULAYR_TEXTDOMAIN ), count( $without ) ) ) );
        if ( empty( $without ) ) {
            echo '<p>' . esc_html__( 'Todos los socios activos tienen alguna entrega.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<ul>';
            foreach ( $without as $user_id ) {
                printf( '<li><a href="%s">%s</a></li>', esc_url( MembersMenu::member_url( $user_id ) . '#welcome-pack' ), esc_html( $this->name( $user_id ) ) );
            }
            echo '</ul><p class="description">' . esc_html__( 'Las entregas se registran en la ficha de cada socio.', SULAYR_TEXTDOMAIN ) . '</p>';
        }

        echo '<h2>' . esc_html__( 'Últimas entregas', SULAYR_TEXTDOMAIN ) . '</h2>';
        $rows = WelcomePack::recent();
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'Sin entregas.', SULAYR_TEXTDOMAIN ) . '</p></div>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Fecha', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Socio', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Artículo', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Entregado por', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $by = get_userdata( (int) $row->delivered_by );
            printf( '<tr><td>%s</td><td><a href="%s">%s</a></td><td>%s</td><td>%s</td></tr>', esc_html( MemberDates::show( (string) $row->delivered_on ) ), esc_url( MembersMenu::member_url( (int) $row->user_id ) ), esc_html( $this->name( (int) $row->user_id ) ), esc_html( (string) $row->item ), esc_html( false !== $by ? $by->display_name : '—' ) );
        }
        echo '</tbody></table></div>';
    }

    /**
     * Bloque de la ficha: entregas y «Registrar entrega».
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_member_block( int $user_id ): void {
        echo '<div class="sulayr-brand sulayr-brand-block">';
        echo '<h2 id="welcome-pack">' . esc_html__( 'Welcome pack', SULAYR_TEXTDOMAIN ) . '</h2>';
        $packs = WelcomePack::packs( $user_id );
        if ( empty( $packs ) ) {
            echo '<p>' . esc_html__( 'Sin entregas.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped"><tbody>';
            foreach ( $packs as $pack_id => $rows ) {
                $by = get_userdata( (int) $rows[0]->delivered_by );
                printf( '<tr><td>%s</td><td>%s</td><td>%s</td><td>', esc_html( MemberDates::show( (string) $rows[0]->delivered_on ) ), esc_html( implode( ', ', wp_list_pluck( $rows, 'item' ) ) ), esc_html( false !== $by ? $by->display_name : '—' ) );
                $this->button( 'borrar_entrega', __( 'Borrar', SULAYR_TEXTDOMAIN ), [ 'user_id' => $user_id, 'pack_id' => (string) $pack_id ], 'button-small', __( '¿Borrar esta entrega?', SULAYR_TEXTDOMAIN ) );
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }

        $items = MemberSettings::labels( 'welcome_items' );
        $this->open_form( 'entrega', [ 'user_id' => $user_id ] );
        echo '<p><strong>' . esc_html__( 'Registrar entrega:', SULAYR_TEXTDOMAIN ) . '</strong> ';
        foreach ( $items as $item ) {
            printf( '<label class="sulayr-check"><input type="checkbox" name="items[]" value="%s"> %s</label> ', esc_attr( $item ), esc_html( $item ) );
        }
        printf( '<input type="date" name="delivered_on" value="%s" required> <button type="submit" class="button button-primary">%s</button></p></form></div>', esc_attr( current_time( 'Y-m-d' ) ), esc_html__( 'Registrar', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Botón en su formulario.
     *
     * @param   string $do      Acción.
     * @param   string $label   Texto.
     * @param   array  $hidden  Campos ocultos.
     * @param   string $class   Clase.
     * @param   string $confirm Pregunta.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function button( string $do, string $label, array $hidden, string $class = '', string $confirm = '' ): void {
        $this->open_form( $do, $hidden, 'sulayr-inline' );
        printf( '<button type="submit" class="button %s"%s>%s</button></form>', esc_attr( $class ), '' !== $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"' : '', esc_html( $label ) );
    }

    /**
     * Abre un formulario de acción.
     *
     * @param   string $do     Acción.
     * @param   array  $hidden Campos.
     * @param   string $class  Clase.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function open_form( string $do, array $hidden, string $class = '' ): void {
        printf( '<form method="post" action="%s" class="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $class ) );
        wp_nonce_field( self::ACTION );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="do" value="%s">', esc_attr( self::ACTION ), esc_attr( $do ) );
        foreach ( $hidden as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
        }
    }

    /**
     * Nombre completo.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function name( int $user_id ): string {
        $user = get_userdata( $user_id );

        return HelperService::get_user_full_name( $user_id ) ?: ( false !== $user ? $user->display_name : '#' . $user_id );
    }
}
