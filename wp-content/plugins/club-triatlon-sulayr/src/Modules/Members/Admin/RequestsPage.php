<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Join\DuplicateFinder;
use ClubTriatlonSulayr\Modules\Members\Join\Identity;
use ClubTriatlonSulayr\Modules\Members\Join\JoinApproval;
use ClubTriatlonSulayr\Modules\Members\Join\JoinForm;
use ClubTriatlonSulayr\Modules\Members\Join\JoinRequests;
use ClubTriatlonSulayr\Modules\Members\Join\PublicJoinForm;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Socios → Solicitudes y Socios → Nueva alta (spec §5.2, §5.3).
 *
 * Los formularios envían a admin-post.php: los gestores de socios pueden
 * entrar en wp-admin (UserService no los saca).
 */
class RequestsPage {

    public const SLUG = 'sulayr-socios-solicitudes';
    public const NEW_SLUG = 'sulayr-socios-nueva-alta';

    private const ACTION_REQUEST = 'sulayr_join_request';
    private const ACTION_REJECT = 'sulayr_join_reject';
    private const ACTION_NEW = 'sulayr_join_new';

    /** Aviso y datos del último envío, por usuario, para la siguiente carga. */
    private const NOTICE_TRANSIENT = 'sulayr_join_notice_';
    private const STATE_TRANSIENT = 'sulayr_join_state_';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION_REQUEST, [ $this, 'handle_request' ] );
        add_action( 'admin_post_' . self::ACTION_REJECT, [ $this, 'handle_reject' ] );
        add_action( 'admin_post_' . self::ACTION_NEW, [ $this, 'handle_new' ] );
    }

    /**
     * Submenús.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        $pending = current_user_can( MemberRoles::MANAGE_MEMBERS ) ? JoinRequests::count_pending() : 0;
        $bubble = $pending > 0 ? sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>', $pending ) : '';

        add_submenu_page( MembersMenu::SLUG, __( 'Solicitudes de alta', SULAYR_TEXTDOMAIN ), __( 'Solicitudes', SULAYR_TEXTDOMAIN ) . $bubble, MemberRoles::MANAGE_MEMBERS, self::SLUG, [ $this, 'render' ] );
        add_submenu_page( MembersMenu::SLUG, __( 'Nueva alta', SULAYR_TEXTDOMAIN ), __( 'Nueva alta', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_MEMBERS, self::NEW_SLUG, [ $this, 'render_new' ] );
    }

    /**
     * URL de una solicitud.
     *
     * @param   int $id Solicitud.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url( int $id = 0, string $status = '' ): string {
        $args = [ 'page' => self::SLUG ];
        if ( $id > 0 ) {
            $args['solicitud'] = $id;
        }
        if ( '' !== $status ) {
            $args['estado'] = $status;
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * Página de solicitudes: listado o detalle.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        $this->check_access();

        $id = isset( $_GET['solicitud'] ) ? absint( $_GET['solicitud'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo lectura.

        JoinForm::enqueue_script();
        echo '<div class="wrap sulayr-members">';
        $this->styles();
        $this->print_notice();
        if ( $id > 0 ) {
            $this->render_detail( $id );
        } else {
            $this->render_list();
        }
        echo '</div>';
    }

    /**
     * Listado por estado.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_list(): void {
        $statuses = [ JoinRequests::STATUS_PENDING, JoinRequests::STATUS_APPROVED, JoinRequests::STATUS_REJECTED, JoinRequests::STATUS_EXPIRED ];
        $filter = isset( $_GET['estado'] ) ? sanitize_key( wp_unslash( $_GET['estado'] ) ) : JoinRequests::STATUS_PENDING; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Filtro.
        if ( false === in_array( $filter, $statuses, true ) ) {
            $filter = JoinRequests::STATUS_PENDING;
        }

        printf( '<h1 class="wp-heading-inline">%s</h1> <a class="page-title-action" href="%s">%s</a><hr class="wp-header-end">', esc_html__( 'Solicitudes de alta', SULAYR_TEXTDOMAIN ), esc_url( add_query_arg( 'page', self::NEW_SLUG, admin_url( 'admin.php' ) ) ), esc_html__( 'Nueva alta', SULAYR_TEXTDOMAIN ) );

        $links = [];
        foreach ( $statuses as $status ) {
            $links[] = sprintf( '<li><a href="%s"%s>%s</a></li>', esc_url( self::url( 0, $status ) ), $status === $filter ? ' class="current"' : '', esc_html( JoinRequests::status_label( $status ) ) );
        }
        echo '<ul class="subsubsub">' . implode( ' | ', $links ) . '</ul><br class="clear">'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.

        $rows = JoinRequests::list( $filter );
        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No hay solicitudes en este estado.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped"><thead><tr>';
        foreach ( [ __( 'Nº', SULAYR_TEXTDOMAIN ), __( 'Recibida', SULAYR_TEXTDOMAIN ), __( 'Nombre', SULAYR_TEXTDOMAIN ), __( 'Email', SULAYR_TEXTDOMAIN ), __( 'Servicios', SULAYR_TEXTDOMAIN ), __( 'Inicio', SULAYR_TEXTDOMAIN ), __( 'Origen', SULAYR_TEXTDOMAIN ), __( 'Coincidencias', SULAYR_TEXTDOMAIN ) ] as $i => $heading ) {
            printf( '<th scope="col"%s>%s</th>', 0 === $i ? ' style="width:50px"' : '', esc_html( $heading ) );
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $row ) {
            $f = $row->fields;
            $matches = '';
            if ( JoinRequests::STATUS_PENDING === $row->status ) {
                $found = DuplicateFinder::find( Identity::normalize_email( (string) ( $f['email'] ?? '' ) ), Identity::normalize_dni( (string) ( $f['dni'] ?? '' ) ) );
                $kinds = array_unique( array_map( function ( $m ) {
                    return DuplicateFinder::kind_label( $m['kind'] );
                }, $found ) );
                $matches = implode( ', ', $kinds );
            }
            printf(
                '<tr><td>%1$d</td><td>%2$s</td><td><a href="%3$s"><strong>%4$s</strong></a>%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td><td>%9$s</td><td>%10$s</td></tr>',
                (int) $row->id,
                esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) ),
                esc_url( self::url( (int) $row->id ) ),
                esc_html( JoinRequests::full_name( $row ) ?: '—' ),
                ! empty( $f['minor'] ) ? ' <span class="sulayr-tag">' . esc_html__( 'menor', SULAYR_TEXTDOMAIN ) . '</span>' : '',
                esc_html( (string) ( $f['email'] ?? '' ) ),
                esc_html( self::services_text( (array) ( $f['services'] ?? [] ) ) ),
                esc_html( self::date( (string) ( $f['start_date'] ?? '' ) ) ),
                esc_html( JoinForm::CONTEXT_ADMIN === ( $f['origin'] ?? '' ) ? __( 'Gestor', SULAYR_TEXTDOMAIN ) : __( 'Formulario', SULAYR_TEXTDOMAIN ) ),
                esc_html( $matches ?: '—' )
            );
        }
        echo '</tbody></table>';
    }

    /**
     * Detalle de una solicitud.
     *
     * @param   int $id Solicitud.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_detail( int $id ): void {
        $request = JoinRequests::get( $id );
        printf( '<p><a href="%s">← %s</a></p>', esc_url( self::url() ), esc_html__( 'Volver a solicitudes', SULAYR_TEXTDOMAIN ) );
        if ( null === $request ) {
            echo '<p>' . esc_html__( 'Solicitud no encontrada.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $f = $request->fields;
        printf(
            '<h1>%s</h1><p>%s</p>',
            esc_html( sprintf( __( 'Solicitud #%1$d: %2$s', SULAYR_TEXTDOMAIN ), $id, JoinRequests::full_name( $request ) ) ),
            esc_html( sprintf(
                /* translators: 1: estado, 2: fecha, 3: origen. */
                __( '%1$s · recibida el %2$s · %3$s', SULAYR_TEXTDOMAIN ),
                JoinRequests::status_label( (string) $request->status ),
                date_i18n( 'd/m/Y H:i', strtotime( (string) $request->created_at ) ),
                JoinForm::CONTEXT_ADMIN === ( $f['origin'] ?? '' ) ? __( 'creada por un gestor', SULAYR_TEXTDOMAIN ) : __( 'formulario público', SULAYR_TEXTDOMAIN )
            ) )
        );

        if ( JoinRequests::STATUS_PENDING !== $request->status ) {
            $this->render_closed( $request );
            return;
        }

        $email = Identity::normalize_email( (string) ( $f['email'] ?? '' ) );
        $matches = DuplicateFinder::find( $email, Identity::normalize_dni( (string) ( $f['dni'] ?? '' ) ) );
        $this->render_matches( $request, $matches );

        $state = $this->take_state();
        $values = $state['data'] ?? $f;
        $errors = $state['errors'] ?? [];

        if ( ! empty( $f['notes'] ) ) {
            echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Observaciones del solicitante:', SULAYR_TEXTDOMAIN ) . '</strong> ' . nl2br( esc_html( (string) $f['notes'] ) ) . '</p></div>';
        }

        printf( '<form class="sulayr-join-form sulayr-join-admin" method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        wp_nonce_field( self::ACTION_REQUEST . '_' . $id );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="request_id" value="%d">', esc_attr( self::ACTION_REQUEST ), $id );

        echo '<h2>' . esc_html__( '1. Datos de la solicitud', SULAYR_TEXTDOMAIN ) . '</h2>';
        echo '<p class="description">' . esc_html__( 'Puedes corregirlos antes de aprobar. Los consentimientos son los que dio el solicitante y no se cambian aquí.', SULAYR_TEXTDOMAIN ) . '</p>';
        JoinForm::render( $values, [], $errors, JoinForm::CONTEXT_ADMIN, false );
        $this->render_request_consents( $id );
        printf( '<p><button type="submit" class="button" name="do" value="save">%s</button></p>', esc_html__( 'Guardar cambios', SULAYR_TEXTDOMAIN ) );

        echo '<h2>' . esc_html__( '2. Aprobar', SULAYR_TEXTDOMAIN ) . '</h2>';
        if ( DuplicateFinder::has_member( $matches ) ) {
            echo '<div class="notice notice-error inline"><p>' . esc_html__( 'El email o el DNI ya son de un socio en vigor. No se puede aprobar: corrige los datos si es otra persona, o rechaza la solicitud.', SULAYR_TEXTDOMAIN ) . '</p></div>';
        } else {
            $this->render_approval_fields( $request, $matches, $email );
            printf( '<p><button type="submit" class="button button-primary" name="do" value="approve">%s</button></p>', esc_html__( 'Aprobar y enviar la bienvenida', SULAYR_TEXTDOMAIN ) );
        }
        echo '</form>';

        echo '<h2>' . esc_html__( '3. Rechazar', SULAYR_TEXTDOMAIN ) . '</h2>';
        printf( '<form method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        wp_nonce_field( self::ACTION_REJECT . '_' . $id );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="request_id" value="%d">', esc_attr( self::ACTION_REJECT ), $id );
        printf( '<p><label for="sulayr-reject-reason">%s</label><br><textarea id="sulayr-reject-reason" name="reason" rows="2" class="large-text" required></textarea></p>', esc_html__( 'Motivo (se envía al solicitante)', SULAYR_TEXTDOMAIN ) );
        printf( '<p><button type="submit" class="button button-link-delete">%s</button></p>', esc_html__( 'Rechazar y avisar', SULAYR_TEXTDOMAIN ) );
        echo '</form>';
    }

    /**
     * Coincidencias en la red y otras solicitudes pendientes.
     *
     * @param   object $request Solicitud.
     * @param   array  $matches Coincidencias.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_matches( object $request, array $matches ): void {
        $f = $request->fields;
        echo '<h2>' . esc_html__( 'Coincidencias en la red', SULAYR_TEXTDOMAIN ) . '</h2>';
        if ( empty( $matches ) ) {
            echo '<p>' . esc_html__( 'Ninguna cuenta de la red tiene este email ni este DNI: se creará una cuenta nueva.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Cuenta', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Nombre', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Email', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'DNI', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Webs', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Situación', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Coincide', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
            foreach ( $matches as $user_id => $match ) {
                $user = $match['user'];
                $blogs = array_map( function ( $blog ) {
                    return $blog->blogname;
                }, get_blogs_of_user( $user_id ) );
                $number = (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true );
                $situation = DuplicateFinder::kind_label( $match['kind'] );
                if ( '' !== $match['status'] ) {
                    $situation .= ' · ' . StatusPeriods::label( $match['status'] );
                }
                if ( '' !== $number ) {
                    $situation .= ' · ' . sprintf( __( 'nº %s', SULAYR_TEXTDOMAIN ), $number );
                }
                $by = array_map( function ( $key ) {
                    return 'dni' === $key ? __( 'DNI', SULAYR_TEXTDOMAIN ) : __( 'email', SULAYR_TEXTDOMAIN );
                }, $match['by'] );
                $name = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
                printf(
                    '<tr><td>#%1$d %2$s</td><td>%3$s</td><td>%4$s</td><td>%5$s</td><td>%6$s</td><td>%7$s</td><td>%8$s</td></tr>',
                    (int) $user_id,
                    DuplicateFinder::KIND_OTHER !== $match['kind'] ? '<a href="' . esc_url( MembersMenu::member_url( (int) $user_id ) ) . '">' . esc_html__( 'ver ficha', SULAYR_TEXTDOMAIN ) . '</a>' : esc_html( $user->user_login ),
                    esc_html( $name ),
                    esc_html( $user->user_email ),
                    esc_html( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_DNI, true ) ?: '—' ),
                    esc_html( implode( ', ', $blogs ) ?: '—' ),
                    esc_html( $situation ),
                    esc_html( implode( ' + ', $by ) )
                );
            }
            echo '</tbody></table>';
        }

        $others = JoinRequests::pending_matches( Identity::normalize_email( (string) ( $f['email'] ?? '' ) ), Identity::normalize_dni( (string) ( $f['dni'] ?? '' ) ), (int) $request->id );
        if ( ! empty( $others ) ) {
            $links = array_map( function ( $other ) {
                return sprintf( '<a href="%s">#%d</a>', esc_url( self::url( (int) $other->id ) ), (int) $other->id );
            }, $others );
            echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Hay otras solicitudes pendientes con el mismo email o DNI:', SULAYR_TEXTDOMAIN ) . ' ' . implode( ', ', $links ) . '</p></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado arriba.
        }
    }

    /**
     * Campos de aprobación: cuenta, fecha, tipo y matrícula.
     *
     * @param   object $request Solicitud.
     * @param   array  $matches Coincidencias.
     * @param   string $email   Email de la solicitud.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_approval_fields( object $request, array $matches, string $email ): void {
        $f = $request->fields;
        $email_taken = false !== email_exists( $email );

        echo '<table class="form-table" role="presentation"><tbody>';

        // Cuenta.
        echo '<tr><th scope="row">' . esc_html__( 'Cuenta', SULAYR_TEXTDOMAIN ) . '</th><td><fieldset>';
        $default = '';
        foreach ( $matches as $user_id => $match ) {
            if ( DuplicateFinder::KIND_FORMER === $match['kind'] || in_array( 'email', $match['by'], true ) ) {
                $default = JoinApproval::MODE_LINK . ':' . $user_id;
                break;
            }
        }
        if ( '' === $default ) {
            $default = $email_taken ? '' : JoinApproval::MODE_CREATE;
        }
        printf(
            '<label><input type="radio" name="approve[mode]" value="%1$s"%2$s%3$s> %4$s</label>%5$s<br>',
            esc_attr( JoinApproval::MODE_CREATE ),
            checked( $default, JoinApproval::MODE_CREATE, false ),
            disabled( $email_taken, true, false ),
            esc_html__( 'Es otra persona: crear una cuenta nueva', SULAYR_TEXTDOMAIN ),
            $email_taken ? ' <span class="description">' . esc_html__( '(no se puede: el email ya tiene cuenta; cámbialo arriba si es otra persona)', SULAYR_TEXTDOMAIN ) . '</span>' : ''
        );
        foreach ( $matches as $user_id => $match ) {
            $is_return = DuplicateFinder::is_return( $match['user'] );
            printf(
                '<label><input type="radio" name="approve[mode]" value="%1$s"%2$s> %3$s</label><br>',
                esc_attr( JoinApproval::MODE_LINK . ':' . $user_id ),
                checked( $default, JoinApproval::MODE_LINK . ':' . $user_id, false ),
                esc_html( sprintf(
                    $is_return ? __( 'Vincular con la cuenta #%1$d (%2$s): reingreso, recupera su número', SULAYR_TEXTDOMAIN ) : __( 'Vincular con la cuenta #%1$d (%2$s): se le añade el rol de deportista', SULAYR_TEXTDOMAIN ),
                    (int) $user_id,
                    $match['user']->user_email
                ) )
            );
            if ( strtolower( $match['user']->user_email ) !== $email ) {
                printf(
                    '<label class="sulayr-indent"><input type="checkbox" name="approve[update_email][%1$d]" value="1"%2$s> %3$s</label><br>',
                    (int) $user_id,
                    disabled( $email_taken, true, false ),
                    esc_html( sprintf( __( 'Cambiar el email de esa cuenta a %s', SULAYR_TEXTDOMAIN ), $email ) )
                );
            }
        }
        echo '</fieldset></td></tr>';

        // Fecha de alta.
        printf(
            '<tr><th scope="row"><label for="sulayr-approve-start">%1$s</label></th><td><input type="date" id="sulayr-approve-start" name="approve[start_date]" value="%2$s" required><p class="description">%3$s</p></td></tr>',
            esc_html__( 'Fecha de alta', SULAYR_TEXTDOMAIN ),
            esc_attr( (string) ( $f['start_date'] ?? '' ) ),
            esc_html__( 'Por defecto, la que pidió. Si es posterior a hoy, queda pendiente y se activa ese día.', SULAYR_TEXTDOMAIN )
        );

        // Tipo de socio.
        $this->render_type_select( 'approve[member_type]', '' );

        // Matrícula.
        $this->render_fee_fields( [
            JoinApproval::MODE_CREATE => JoinApproval::proposed_fee( false ),
            'reingreso' => JoinApproval::proposed_fee( true ),
        ] );

        echo '</tbody></table>';
        echo '<p class="description">' . esc_html__( 'Al aprobar se guardan también los cambios de los datos de arriba, se crean los pasos de alta y se envía el email de bienvenida (al tutor también si es menor).', SULAYR_TEXTDOMAIN ) . '</p>';
    }

    /**
     * Selector del tipo de socio.
     *
     * @param   string $name    Nombre del campo.
     * @param   string $current Tipo elegido.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_type_select( string $name, string $current ): void {
        $types = MemberSettings::member_types();
        if ( '' === $current || false === isset( $types[ $current ] ) ) {
            $current = (string) array_key_first( $types );
        }

        printf( '<tr><th scope="row"><label for="sulayr-approve-type">%1$s</label></th><td><select id="sulayr-approve-type" name="%2$s">', esc_html__( 'Tipo de socio', SULAYR_TEXTDOMAIN ), esc_attr( $name ) );
        foreach ( $types as $key => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $current, $key, false ), esc_html( $label ) );
        }
        echo '</select></td></tr>';
    }

    /**
     * Campos de matrícula.
     *
     * @param   array<string, float> $proposals Propuesta por caso.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_fee_fields( array $proposals ): void {
        $text = sprintf(
            /* translators: 1: matrícula de alta, 2: matrícula de reingreso. */
            __( 'Propuesta: %1$s € en un alta y %2$s € en un reingreso (Socios → Ajustes). Déjalo vacío para usar la propuesta.', SULAYR_TEXTDOMAIN ),
            number_format_i18n( $proposals[ JoinApproval::MODE_CREATE ], 2 ),
            number_format_i18n( $proposals['reingreso'], 2 )
        );
        printf(
            '<tr><th scope="row"><label for="sulayr-approve-fee">%1$s</label></th><td><input type="number" id="sulayr-approve-fee" name="approve[fee]" min="0" step="0.01" class="small-text"> € <p class="description">%2$s</p></td></tr>',
            esc_html__( 'Matrícula', SULAYR_TEXTDOMAIN ),
            esc_html( $text )
        );
        printf(
            '<tr><th scope="row"><label for="sulayr-approve-fee-reason">%1$s</label></th><td><input type="text" id="sulayr-approve-fee-reason" name="approve[fee_reason]" class="regular-text"><p class="description">%2$s</p></td></tr>',
            esc_html__( 'Motivo del cambio', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Obligatorio si la matrícula no es la propuesta.', SULAYR_TEXTDOMAIN )
        );
    }

    /**
     * Consentimientos guardados con la solicitud.
     *
     * @param   int $request_id Solicitud.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_request_consents( int $request_id ): void {
        echo '<fieldset class="sulayr-join-group"><legend>' . esc_html__( 'Consentimientos', SULAYR_TEXTDOMAIN ) . '</legend><ul>';
        foreach ( MemberConsents::rows( 0, $request_id ) as $row ) {
            printf(
                '<li><strong>%s:</strong> %s · %s</li>',
                esc_html( MemberConsents::label( (string) $row->type ) ),
                esc_html( MemberConsents::describe( (bool) $row->accepted, (string) $row->version, (string) $row->given_by ) ),
                esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $row->created_at ) ) )
            );
        }
        echo '</ul></fieldset>';
    }

    /**
     * Solicitud aprobada, rechazada o caducada.
     *
     * @param   object $request Solicitud.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_closed( object $request ): void {
        $f = $request->fields;
        $resolver = (int) $request->resolved_by > 0 ? get_userdata( (int) $request->resolved_by ) : false;

        echo '<table class="form-table" role="presentation"><tbody>';
        $rows = [];
        if ( null !== $request->resolved_at ) {
            $rows[ __( 'Resuelta', SULAYR_TEXTDOMAIN ) ] = date_i18n( 'd/m/Y H:i', strtotime( (string) $request->resolved_at ) ) . ( false !== $resolver ? ' · ' . $resolver->display_name : '' );
        }
        if ( JoinRequests::STATUS_REJECTED === $request->status ) {
            $rows[ __( 'Motivo', SULAYR_TEXTDOMAIN ) ] = (string) $request->reason;
        }
        if ( ! empty( $f['approval'] ) ) {
            $a = $f['approval'];
            $types = MemberSettings::member_types();
            $rows[ __( 'Socio', SULAYR_TEXTDOMAIN ) ] = sprintf( __( 'nº %1$s (cuenta #%2$d)', SULAYR_TEXTDOMAIN ), (string) $a['member_number'], (int) $a['user_id'] );
            $rows[ __( 'Cuenta', SULAYR_TEXTDOMAIN ) ] = JoinApproval::MODE_CREATE === $a['mode'] ? __( 'Nueva', SULAYR_TEXTDOMAIN ) : ( ! empty( $a['is_return'] ) ? __( 'Reingreso', SULAYR_TEXTDOMAIN ) : __( 'Vinculada', SULAYR_TEXTDOMAIN ) );
            $rows[ __( 'Fecha de alta', SULAYR_TEXTDOMAIN ) ] = self::date( (string) ( $f['start_date'] ?? '' ) );
            $rows[ __( 'Tipo', SULAYR_TEXTDOMAIN ) ] = $types[ $a['member_type'] ] ?? (string) $a['member_type'];
            $rows[ __( 'Servicios', SULAYR_TEXTDOMAIN ) ] = self::services_text( (array) ( $f['services'] ?? [] ) );
            $rows[ __( 'Matrícula', SULAYR_TEXTDOMAIN ) ] = number_format_i18n( (float) $a['fee'], 2 ) . ' €' . ( '' !== $a['fee_reason'] ? ' (' . $a['fee_reason'] . ')' : '' );
        } elseif ( ! empty( $f['anonimizada'] ) ) {
            $rows[ __( 'Datos', SULAYR_TEXTDOMAIN ) ] = __( 'Borrados por el plazo de conservación.', SULAYR_TEXTDOMAIN );
        } else {
            $rows[ __( 'Email', SULAYR_TEXTDOMAIN ) ] = (string) ( $f['email'] ?? '' );
            $rows[ __( 'Servicios', SULAYR_TEXTDOMAIN ) ] = self::services_text( (array) ( $f['services'] ?? [] ) );
        }
        foreach ( $rows as $label => $value ) {
            printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( $value ) );
        }
        echo '</tbody></table>';

        if ( ! empty( $f['approval']['user_id'] ) ) {
            printf( '<p><a class="button" href="%s">%s</a></p>', esc_url( MembersMenu::member_url( (int) $f['approval']['user_id'] ) ), esc_html__( 'Ver ficha del socio', SULAYR_TEXTDOMAIN ) );
        }
    }

    /**
     * Página «Nueva alta».
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_new(): void {
        $this->check_access();
        JoinForm::enqueue_script();

        $state = $this->take_state();

        echo '<div class="wrap sulayr-members">';
        $this->styles();
        echo '<h1>' . esc_html__( 'Nueva alta', SULAYR_TEXTDOMAIN ) . '</h1>';
        echo '<p>' . esc_html__( 'Crea una solicitud con los datos del socio. «Crear y aprobar» la aprueba en el mismo paso si nadie de la red tiene ese email ni ese DNI; si hay coincidencias, te lleva a la solicitud para decidir.', SULAYR_TEXTDOMAIN ) . '</p>';
        $this->print_notice();

        printf( '<form class="sulayr-join-form sulayr-join-admin" method="post" action="%s">', esc_url( admin_url( 'admin-post.php' ) ) );
        wp_nonce_field( self::ACTION_NEW );
        printf( '<input type="hidden" name="action" value="%s">', esc_attr( self::ACTION_NEW ) );
        JoinForm::render( $state['data'] ?? [], $state['consents'] ?? [], $state['errors'] ?? [], JoinForm::CONTEXT_ADMIN );

        echo '<h2>' . esc_html__( 'Para aprobar en el mismo paso', SULAYR_TEXTDOMAIN ) . '</h2><table class="form-table" role="presentation"><tbody>';
        $this->render_type_select( 'approve[member_type]', '' );
        $this->render_fee_fields( [
            JoinApproval::MODE_CREATE => JoinApproval::proposed_fee( false ),
            'reingreso' => JoinApproval::proposed_fee( true ),
        ] );
        echo '</tbody></table>';

        printf(
            '<p><button type="submit" class="button" name="do" value="save">%s</button> <button type="submit" class="button button-primary" name="do" value="approve">%s</button></p>',
            esc_html__( 'Crear solicitud', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Crear y aprobar', SULAYR_TEXTDOMAIN )
        );
        echo '</form></div>';
    }

    /**
     * Guardar o aprobar una solicitud.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_request(): void {
        $id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        check_admin_referer( self::ACTION_REQUEST . '_' . $id );
        $this->check_access();

        $request = JoinRequests::get( $id );
        if ( null === $request || JoinRequests::STATUS_PENDING !== $request->status ) {
            $this->redirect( self::url( $id ), 'error', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        $parsed = JoinForm::parse( (array) wp_unslash( $_POST[ JoinForm::INPUT ] ?? [] ), JoinForm::CONTEXT_ADMIN ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JoinForm::parse limpia cada campo.
        // Los consentimientos no se editan aquí.
        $errors = array_filter( $parsed['errors'], function ( $key ) {
            return 0 !== strpos( (string) $key, 'consent_' );
        }, ARRAY_FILTER_USE_KEY );
        $data = $parsed['data'];
        $data['origin'] = $request->fields['origin'] ?? JoinForm::CONTEXT_PUBLIC;

        if ( ! empty( $errors ) ) {
            $this->save_state( [ 'data' => $data, 'errors' => $errors ] );
            $this->redirect( self::url( $id ), 'error', __( 'Revisa los datos marcados. No se ha guardado nada.', SULAYR_TEXTDOMAIN ) );
        }

        if ( $data !== $request->fields ) {
            JoinRequests::update_data( $id, $data );
        }

        if ( 'approve' !== ( $_POST['do'] ?? '' ) ) {
            $this->redirect( self::url( $id ), 'success', __( 'Cambios guardados.', SULAYR_TEXTDOMAIN ) );
        }

        $result = JoinApproval::approve( $id, $this->choice( $data ) );
        if ( is_wp_error( $result ) ) {
            $this->redirect( self::url( $id ), 'error', $result->get_error_message() );
        }

        $this->redirect( MembersMenu::member_url( (int) $result ), 'success', __( 'Solicitud aprobada. Se ha enviado el email de bienvenida.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Rechazar una solicitud.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_reject(): void {
        $id = isset( $_POST['request_id'] ) ? absint( $_POST['request_id'] ) : 0;
        check_admin_referer( self::ACTION_REJECT . '_' . $id );
        $this->check_access();

        $result = JoinApproval::reject( $id, sanitize_textarea_field( wp_unslash( $_POST['reason'] ?? '' ) ) );
        if ( is_wp_error( $result ) ) {
            $this->redirect( self::url( $id ), 'error', $result->get_error_message() );
        }

        $this->redirect( self::url(), 'success', __( 'Solicitud rechazada. Se ha avisado al solicitante.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Alta desde el formulario del gestor.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_new(): void {
        check_admin_referer( self::ACTION_NEW );
        $this->check_access();

        $new_url = add_query_arg( 'page', self::NEW_SLUG, admin_url( 'admin.php' ) );
        $parsed = JoinForm::parse( (array) wp_unslash( $_POST[ JoinForm::INPUT ] ?? [] ), JoinForm::CONTEXT_ADMIN ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JoinForm::parse limpia cada campo.
        if ( ! empty( $parsed['errors'] ) ) {
            $this->save_state( $parsed );
            $this->redirect( $new_url, 'error', __( 'Revisa los datos marcados. No se ha guardado nada.', SULAYR_TEXTDOMAIN ) );
        }

        // Antes de crear nada: si se va a aprobar, la matrícula cambiada necesita motivo.
        if ( 'approve' === ( $_POST['do'] ?? '' ) ) {
            $choice = $this->choice( $parsed['data'] );
            if ( abs( $choice['fee'] - JoinApproval::proposed_fee( false ) ) > 0.004 && '' === $choice['fee_reason'] && empty( DuplicateFinder::find( $parsed['data']['email'], $parsed['data']['dni'] ) ) ) {
                $this->save_state( $parsed );
                $this->redirect( $new_url, 'error', __( 'Si cambias la matrícula propuesta, escribe el motivo. No se ha guardado nada.', SULAYR_TEXTDOMAIN ) );
            }
        }

        $id = PublicJoinForm::save( $parsed, '' );

        if ( 'approve' !== ( $_POST['do'] ?? '' ) ) {
            $this->redirect( self::url( $id ), 'success', __( 'Solicitud creada. Revísala y apruébala aquí.', SULAYR_TEXTDOMAIN ) );
        }

        $data = $parsed['data'];
        $matches = DuplicateFinder::find( $data['email'], $data['dni'] );
        if ( ! empty( $matches ) ) {
            $this->redirect( self::url( $id ), 'warning', __( 'Solicitud creada, pero hay cuentas de la red con ese email o DNI. Elige si vincular o crear y apruébala aquí.', SULAYR_TEXTDOMAIN ) );
        }

        $_POST['approve']['mode'] = JoinApproval::MODE_CREATE;
        $result = JoinApproval::approve( $id, $this->choice( $data ) );
        if ( is_wp_error( $result ) ) {
            $this->redirect( self::url( $id ), 'error', sprintf( __( 'Solicitud creada, pero no se ha podido aprobar: %s', SULAYR_TEXTDOMAIN ), $result->get_error_message() ) );
        }

        $this->redirect( MembersMenu::member_url( (int) $result ), 'success', __( 'Alta hecha. Se ha enviado el email de bienvenida.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Decisión del gestor a partir de $_POST['approve'].
     *
     * @param   array $data Datos de la solicitud.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function choice( array $data ): array {
        $input = (array) wp_unslash( $_POST['approve'] ?? [] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce comprobado antes; cada valor se limpia abajo.

        $mode = sanitize_text_field( (string) ( $input['mode'] ?? '' ) );
        $user_id = 0;
        if ( 0 === strpos( $mode, JoinApproval::MODE_LINK . ':' ) ) {
            $user_id = absint( substr( $mode, strlen( JoinApproval::MODE_LINK ) + 1 ) );
            $mode = JoinApproval::MODE_LINK;
        }

        $start = sanitize_text_field( (string) ( $input['start_date'] ?? '' ) );
        if ( '' === $start ) {
            $start = (string) $data['start_date'];
        }

        $fee = trim( (string) ( $input['fee'] ?? '' ) );
        if ( '' === $fee ) {
            $is_return = JoinApproval::MODE_LINK === $mode && false !== get_userdata( $user_id ) && DuplicateFinder::is_return( get_userdata( $user_id ) );
            $fee = JoinApproval::proposed_fee( $is_return );
        }

        return [
            'mode' => $mode,
            'user_id' => $user_id,
            'start_date' => $start,
            'member_type' => sanitize_key( (string) ( $input['member_type'] ?? '' ) ),
            'services' => $data['services'],
            'fee' => (float) str_replace( ',', '.', (string) $fee ),
            'fee_reason' => sanitize_text_field( (string) ( $input['fee_reason'] ?? '' ) ),
            'update_email' => ! empty( $input['update_email'][ $user_id ] ),
        ];
    }

    /**
     * Texto con los servicios de una solicitud.
     *
     * @param   array $services Servicios.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function services_text( array $services ): string {
        return implode( ', ', array_map( function ( $item ) {
            return MemberServices::label( (int) $item['service_id'], (string) $item['option_key'] );
        }, $services ) ) ?: '—';
    }

    /**
     * Fecha d/m/Y o «—».
     *
     * @param   string $date Y-m-d.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function date( string $date ): string {
        $time = '' !== $date ? strtotime( $date ) : false;

        return false !== $time ? date_i18n( 'd/m/Y', $time ) : '—';
    }

    /**
     * Corta si no hay permiso.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function check_access(): void {
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }
    }

    /**
     * Guarda un aviso y redirige.
     *
     * @param   string $url  Destino.
     * @param   string $type success, warning o error.
     * @param   string $text Texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function redirect( string $url, string $type, string $text ): void {
        set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), [ 'type' => $type, 'text' => $text ], 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( $url );
        exit;
    }

    /**
     * Pinta y borra el aviso pendiente.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function print_notice(): void {
        $key = self::NOTICE_TRANSIENT . get_current_user_id();
        $notice = get_transient( $key );
        if ( false === is_array( $notice ) ) {
            return;
        }
        delete_transient( $key );

        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $notice['type'] ), esc_html( $notice['text'] ) );
    }

    /**
     * Guarda lo enviado para volver a pintarlo con los errores.
     *
     * @param   array $state Datos y errores.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function save_state( array $state ): void {
        set_transient( self::STATE_TRANSIENT . get_current_user_id(), $state, 5 * MINUTE_IN_SECONDS );
    }

    /**
     * Recupera y borra lo enviado.
     *
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function take_state(): array {
        $key = self::STATE_TRANSIENT . get_current_user_id();
        $state = get_transient( $key );
        delete_transient( $key );

        return is_array( $state ) ? $state : [];
    }

    /**
     * Estilos de las pantallas de alta.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function styles(): void {
        echo '<style>
            .sulayr-join-admin .sulayr-join-group{border:1px solid #c3c4c7;background:#fff;padding:8px 16px 16px;margin:0 0 16px;max-width:900px}
            .sulayr-join-admin legend{font-weight:600;padding:0 4px}
            .sulayr-join-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 16px}
            .sulayr-join-field label{display:block;font-weight:600;margin-bottom:2px}
            .sulayr-join-field input,.sulayr-join-field select{width:100%;max-width:100%}
            .sulayr-join-service{padding:4px 0}
            .sulayr-join-service.is-blocked{opacity:.5}
            .sulayr-join-options{margin:4px 0 0 24px}
            .sulayr-join-note{color:#646970}
            .sulayr-join-consent-text{color:#646970;margin-left:24px}
            .sulayr-join-error{color:#b32d2e;margin:2px 0 0}
            .sulayr-indent{margin-left:24px}
            .sulayr-tag{background:#f0f0f1;border-radius:3px;padding:0 6px;font-size:12px}
            @media (max-width:782px){.sulayr-join-grid{grid-template-columns:1fr}}
        </style>';
    }
}
