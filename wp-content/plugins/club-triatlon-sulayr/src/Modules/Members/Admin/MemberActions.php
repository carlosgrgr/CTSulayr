<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Join\JoinForm;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\MemberChanges;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Acciones del gestor en la ficha del socio (spec §6): aprobar o rechazar lo
 * que pide el socio, anular cambios programados, cambiar servicios o tipo,
 * congelar, cambiar la vuelta de una congelación y dar de baja.
 */
class MemberActions {

    private const ACTION = 'sulayr_member_lifecycle';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
    }

    /**
     * Bloques de la ficha.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render( int $user_id ): void {
        $status = (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ?: CustomFields::STATUS_ACTIVE;
        $scheduled = MemberChanges::scheduled_for_user( $user_id );
        $scheduled_types = array_map( function ( $row ) {
            return (string) $row->type;
        }, $scheduled );
        $next_month = MemberDates::first_of_month_after( current_time( 'Y-m-d' ) );

        echo '<h2 id="acciones">' . esc_html__( 'Cambios, congelación y baja', SULAYR_TEXTDOMAIN ) . '</h2>';
        JoinForm::enqueue_script();

        // Petición pendiente del socio.
        $pending = MemberChanges::pending_for_user( $user_id );
        if ( null !== $pending ) {
            echo '<div class="notice notice-warning inline sulayr-actions-box"><p><strong>' . esc_html__( 'Solicitud del socio pendiente', SULAYR_TEXTDOMAIN ) . ':</strong> ' . esc_html( MemberChanges::describe( $pending ) ) . '</p>';
            printf( '<p>%s</p>', esc_html( sprintf( __( 'Pedida el %1$s. Fecha calculada: %2$s.', SULAYR_TEXTDOMAIN ), MemberDates::show( substr( (string) $pending->created_at, 0, 10 ) ), MemberDates::show( (string) $pending->effective_date ) ) ) );
            $this->open_form( $user_id, 'aprobar', [ 'change_id' => (int) $pending->id ] );
            $this->effective_fields( MemberChanges::min_date_for( $pending ), true );
            printf( '<p><button type="submit" class="button button-primary">%s</button></p></form>', esc_html__( 'Aprobar y avisar al socio', SULAYR_TEXTDOMAIN ) );
            $this->open_form( $user_id, 'rechazar', [ 'change_id' => (int) $pending->id ] );
            printf( '<p><label>%s<br><input type="text" name="reason" class="regular-text" required></label> <button type="submit" class="button button-link-delete">%s</button></p></form></div>', esc_html__( 'Motivo del rechazo (se envía al socio)', SULAYR_TEXTDOMAIN ), esc_html__( 'Rechazar', SULAYR_TEXTDOMAIN ) );
        }

        // Programados.
        if ( ! empty( $scheduled ) ) {
            echo '<table class="widefat striped sulayr-actions-table"><thead><tr><th>' . esc_html__( 'Desde', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Cambio programado', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Origen', SULAYR_TEXTDOMAIN ) . '</th><th></th></tr></thead><tbody>';
            foreach ( $scheduled as $row ) {
                echo '<tr><td>' . esc_html( MemberDates::show( (string) $row->effective_date ) ) . '</td><td>' . esc_html( MemberChanges::describe( $row ) ) . '</td><td>' . esc_html( MemberChanges::BY_MEMBER === ( $row->fields['origin'] ?? '' ) ? __( 'Solicitud del socio', SULAYR_TEXTDOMAIN ) : ( ! empty( $row->fields['origin'] ) ? __( 'Gestor', SULAYR_TEXTDOMAIN ) : __( 'Migración', SULAYR_TEXTDOMAIN ) ) ) . '</td><td>';
                $this->open_form( $user_id, 'anular', [ 'change_id' => (int) $row->id ] );
                printf( '<button type="submit" class="button button-small" onclick="return confirm(\'%s\');">%s</button></form></td></tr>', esc_js( __( '¿Anular este cambio programado?', SULAYR_TEXTDOMAIN ) ), esc_html__( 'Anular', SULAYR_TEXTDOMAIN ) );
            }
            echo '</tbody></table>';
        }

        if ( CustomFields::STATUS_LEFT === $status ) {
            echo '<p>' . esc_html__( 'El socio está de baja. Para que vuelva, crea una nueva alta (reingreso) en Socios → Nueva alta.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        // Cambiar servicios o tipo.
        $selected = [];
        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            $selected[ (int) $row->service_id ] = (string) $row->option_key;
        }
        $type = (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true );
        echo '<details class="sulayr-actions-box"><summary>' . esc_html__( 'Cambiar servicios o tipo de socio', SULAYR_TEXTDOMAIN ) . '</summary>';
        $this->open_form( $user_id, 'cambio', [], 'sulayr-join-form sulayr-join-admin' );
        JoinForm::render_services( ServiceCatalog::all(), $selected, current_time( 'Y-m-d' ), '' !== $type ? $type : ServiceCatalog::GENERAL );
        echo '<p><label>' . esc_html__( 'Tipo de socio', SULAYR_TEXTDOMAIN ) . ' <select name="member_type">';
        foreach ( MemberSettings::member_types() as $key => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $key ), selected( $type, $key, false ), esc_html( $label ) );
        }
        echo '</select></label></p>';
        $this->effective_fields( $next_month, true, MemberChanges::proposed_date() );
        printf( '<p><button type="submit" class="button button-primary">%s</button></p></form></details>', esc_html__( 'Guardar el cambio', SULAYR_TEXTDOMAIN ) );

        // Congelar.
        $period = StatusPeriods::current( $user_id );
        $frozen = null !== $period && CustomFields::STATUS_FROZEN === $period->status;
        if ( CustomFields::STATUS_ACTIVE === $status && false === in_array( MemberChanges::TYPE_FREEZE, $scheduled_types, true ) ) {
            echo '<details class="sulayr-actions-box"><summary>' . esc_html__( 'Congelar', SULAYR_TEXTDOMAIN ) . '</summary>';
            $this->open_form( $user_id, 'congelar' );
            printf( '<p><label>%s<br><input type="text" name="reason" class="regular-text" required></label></p>', esc_html__( 'Motivo', SULAYR_TEXTDOMAIN ) );
            printf( '<p><label>%1$s <input type="month" name="start_month" min="%2$s" value="%2$s" required></label> <label>%3$s <input type="month" name="return_month" min="%4$s" required></label></p>', esc_html__( 'Desde el 1 de', SULAYR_TEXTDOMAIN ), esc_attr( substr( $next_month, 0, 7 ) ), esc_html__( 'Vuelve el 1 de', SULAYR_TEXTDOMAIN ), esc_attr( substr( MemberDates::first_of_month_after( $next_month ), 0, 7 ) ) );
            printf( '<p><label><input type="checkbox" name="cancel_current" value="1"> %s</label></p>', esc_html( sprintf( __( 'Congelación inmediata (solo si empieza el %s): en el recibo de este mes se anulan los servicios marcados «se anula al congelar». Sigue con reservas hasta fin de mes.', SULAYR_TEXTDOMAIN ), MemberDates::show( $next_month ) ) ) );
            printf( '<p><button type="submit" class="button button-primary">%s</button></p></form></details>', esc_html__( 'Programar la congelación', SULAYR_TEXTDOMAIN ) );
        }

        // Cambiar la vuelta.
        if ( $frozen || in_array( MemberChanges::TYPE_FREEZE, $scheduled_types, true ) ) {
            $current_return = $frozen && null !== $period->planned_end_date ? MemberDates::add_days( (string) $period->planned_end_date, 1 ) : '';
            echo '<details class="sulayr-actions-box"' . ( $frozen ? ' open' : '' ) . '><summary>' . esc_html__( 'Adelantar o alargar la vuelta', SULAYR_TEXTDOMAIN ) . '</summary>';
            $this->open_form( $user_id, 'vuelta' );
            printf( '<p><label>%1$s <input type="month" name="return_month" min="%2$s" value="%3$s" required></label></p>', esc_html__( 'Vuelve el 1 de', SULAYR_TEXTDOMAIN ), esc_attr( substr( $next_month, 0, 7 ) ), esc_attr( substr( $current_return, 0, 7 ) ) );
            printf( '<p><button type="submit" class="button">%s</button></p></form></details>', esc_html__( 'Cambiar la vuelta', SULAYR_TEXTDOMAIN ) );
        }

        // Baja.
        if ( false === in_array( MemberChanges::TYPE_LEAVE, $scheduled_types, true ) ) {
            echo '<details class="sulayr-actions-box"><summary>' . esc_html__( 'Dar de baja', SULAYR_TEXTDOMAIN ) . '</summary>';
            $this->open_form( $user_id, 'baja' );
            printf( '<p><label>%s<br><input type="text" name="reason" class="regular-text"></label></p>', esc_html__( 'Motivo (opcional)', SULAYR_TEXTDOMAIN ) );
            $this->effective_fields( $next_month, true, MemberChanges::proposed_date() );
            printf( '<p class="description">%s</p>', esc_html__( 'Se envía al socio el email de confirmación de baja.', SULAYR_TEXTDOMAIN ) );
            printf( '<p><button type="submit" class="button button-primary">%s</button></p></form></details>', esc_html__( 'Programar la baja', SULAYR_TEXTDOMAIN ) );
        }

        echo '<style>.sulayr-actions-box{background:#fff;border:1px solid #c3c4c7;padding:8px 16px;margin:0 0 12px;max-width:900px}.sulayr-actions-box summary{cursor:pointer;font-weight:600}.sulayr-actions-table{max-width:900px;margin-bottom:12px}.sulayr-join-service{padding:4px 0}.sulayr-join-service.is-blocked{opacity:.5}.sulayr-join-options{margin:4px 0 0 24px}.sulayr-join-note{color:#646970}</style>';
    }

    /**
     * Procesa cualquier acción de la ficha.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        check_admin_referer( self::ACTION . '_' . $user_id );
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }
        $user = get_userdata( $user_id );
        if ( false === $user || false === in_array( 'athlete', (array) $user->roles, true ) ) {
            $this->back( $user_id, 'error', __( 'Socio no encontrado.', SULAYR_TEXTDOMAIN ) );
        }

        $do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
        $change_id = isset( $_POST['change_id'] ) ? absint( $_POST['change_id'] ) : 0;
        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );

        // Una acción sobre un cambio tiene que ser de este socio.
        if ( $change_id > 0 ) {
            $change = MemberChanges::get( $change_id );
            if ( null === $change || (int) $change->user_id !== $user_id ) {
                $this->back( $user_id, 'error', __( 'Cambio no encontrado.', SULAYR_TEXTDOMAIN ) );
            }
        }

        switch ( $do ) {
            case 'aprobar':
                $effective = $this->read_effective();
                $result = is_wp_error( $effective ) ? $effective : MemberChanges::approve( $change_id, $effective['date'], $effective['reason'] );
                $done = __( 'Solicitud aprobada. Se ha avisado al socio.', SULAYR_TEXTDOMAIN );
                break;
            case 'rechazar':
                $result = MemberChanges::reject( $change_id, $reason );
                $done = __( 'Solicitud rechazada. Se ha avisado al socio.', SULAYR_TEXTDOMAIN );
                break;
            case 'anular':
                $result = MemberChanges::annul( $change_id );
                $done = __( 'Cambio programado anulado.', SULAYR_TEXTDOMAIN );
                break;
            case 'cambio':
                $result = $this->schedule_change( $user_id );
                $done = __( 'Cambio guardado.', SULAYR_TEXTDOMAIN );
                break;
            case 'congelar':
                $result = $this->schedule_freeze( $user_id, $reason );
                $done = __( 'Congelación programada.', SULAYR_TEXTDOMAIN );
                break;
            case 'vuelta':
                $result = MemberChanges::set_freeze_return( $user_id, $this->month_to_date( (string) wp_unslash( $_POST['return_month'] ?? '' ) ) );
                $done = __( 'Vuelta cambiada.', SULAYR_TEXTDOMAIN );
                break;
            case 'baja':
                $effective = $this->read_effective();
                $result = is_wp_error( $effective ) ? $effective : MemberChanges::schedule( $user_id, MemberChanges::TYPE_LEAVE, [ 'reason' => $reason ], $effective['date'], $effective['reason'] );
                $done = __( 'Baja guardada. Se ha enviado la confirmación al socio.', SULAYR_TEXTDOMAIN );
                break;
            default:
                $result = new \WP_Error( 'sulayr_action', __( 'Acción desconocida.', SULAYR_TEXTDOMAIN ) );
                $done = '';
        }

        if ( is_wp_error( $result ) ) {
            $this->back( $user_id, 'error', $result->get_error_message() );
        }

        $this->back( $user_id, 'success', $done );
    }

    /**
     * Cambio de servicios y/o de tipo.
     *
     * @param   int $user_id Socio.
     * @return 	true|\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function schedule_change( int $user_id ) {
        $parsed = JoinForm::parse_services( (array) wp_unslash( $_POST[ JoinForm::INPUT ] ?? [] ), JoinForm::CONTEXT_ADMIN, ServiceCatalog::all() ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce comprobado en handle(); parse_services limpia cada valor.
        if ( '' !== $parsed['error'] ) {
            return new \WP_Error( 'sulayr_services', $parsed['error'] );
        }

        $type = sanitize_key( wp_unslash( $_POST['member_type'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( false === isset( MemberSettings::member_types()[ $type ] ) ) {
            return new \WP_Error( 'sulayr_type', __( 'Elige el tipo de socio.', SULAYR_TEXTDOMAIN ) );
        }

        $current = array_map( function ( $row ) {
            return (int) $row->service_id . '|' . (string) $row->option_key;
        }, MemberServices::open_rows( $user_id ) );
        $desired = array_map( function ( $item ) {
            return (int) $item['service_id'] . '|' . (string) $item['option_key'];
        }, $parsed['services'] );
        sort( $current );
        sort( $desired );
        $services_changed = $current !== $desired;
        $type_changed = (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true ) !== $type;

        if ( false === $services_changed && false === $type_changed ) {
            return new \WP_Error( 'sulayr_nothing', __( 'No has cambiado nada.', SULAYR_TEXTDOMAIN ) );
        }

        $effective = $this->read_effective();
        if ( is_wp_error( $effective ) ) {
            return $effective;
        }

        if ( $services_changed ) {
            $result = MemberChanges::schedule( $user_id, MemberChanges::TYPE_SERVICES, [ 'services' => $parsed['services'] ], $effective['date'], $effective['reason'] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }
        if ( $type_changed ) {
            $result = MemberChanges::schedule( $user_id, MemberChanges::TYPE_MEMBER_TYPE, [ 'member_type' => $type ], $effective['date'], $effective['reason'] );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
        }

        return true;
    }

    /**
     * Congelación (6.4, D29, D30).
     *
     * @param   int    $user_id Socio.
     * @param   string $reason  Motivo.
     * @return 	true|\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function schedule_freeze( int $user_id, string $reason ) {
        if ( '' === trim( $reason ) ) {
            return new \WP_Error( 'sulayr_freeze_reason', __( 'Escribe el motivo de la congelación.', SULAYR_TEXTDOMAIN ) );
        }

        $start = $this->month_to_date( (string) wp_unslash( $_POST['start_month'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $return = $this->month_to_date( (string) wp_unslash( $_POST['return_month'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        if ( '' === $start || '' === $return || $return <= $start ) {
            return new \WP_Error( 'sulayr_freeze_dates', __( 'La vuelta tiene que ser al menos un mes después del inicio.', SULAYR_TEXTDOMAIN ) );
        }

        $next_month = MemberDates::first_of_month_after( current_time( 'Y-m-d' ) );
        $cancel_month = '';
        if ( ! empty( $_POST['cancel_current'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            if ( $start !== $next_month ) {
                return new \WP_Error( 'sulayr_freeze_immediate', sprintf( __( 'La congelación inmediata tiene que empezar el %s.', SULAYR_TEXTDOMAIN ), MemberDates::show( $next_month ) ) );
            }
            $cancel_month = current_time( 'Y-m' );
        }

        $result = MemberChanges::schedule( $user_id, MemberChanges::TYPE_FREEZE, [ 'reason' => $reason, 'return' => $return, 'cancel_month' => $cancel_month ], $start );

        return is_wp_error( $result ) ? $result : true;
    }

    /**
     * Fecha elegida en los campos de fecha efectiva.
     *
     * @return 	array{date: string, reason: string}|\WP_Error date '' = al momento.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function read_effective() {
        $input = (array) wp_unslash( $_POST['eff'] ?? [] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Se limpia abajo.
        if ( 'ahora' === ( $input['mode'] ?? '' ) ) {
            $reason = sanitize_text_field( (string) ( $input['reason'] ?? '' ) );
            if ( empty( $input['confirm'] ) || '' === $reason ) {
                return new \WP_Error( 'sulayr_immediate', __( 'Para aplicarlo al momento, confirma que el recibo de este mes no está pagado y escribe el motivo.', SULAYR_TEXTDOMAIN ) );
            }
            return [ 'date' => '', 'reason' => $reason ];
        }

        $date = $this->month_to_date( (string) ( $input['month'] ?? '' ) );
        if ( '' === $date ) {
            return new \WP_Error( 'sulayr_date', __( 'Elige el mes desde el que se aplica.', SULAYR_TEXTDOMAIN ) );
        }

        return [ 'date' => $date, 'reason' => '' ];
    }

    /**
     * Campos de fecha efectiva: un mes (día 1) o al momento (D27).
     *
     * @param   string $min       Primer día permitido.
     * @param   bool   $immediate Mostrar «al momento».
     * @param   string $default   Mes propuesto ('' = $min).
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function effective_fields( string $min, bool $immediate, string $default = '' ): void {
        $default = '' !== $default && $default >= $min ? $default : $min;
        echo '<fieldset><p>';
        printf(
            '<label><input type="radio" name="eff[mode]" value="fecha" checked> %1$s</label> <input type="month" name="eff[month]" min="%2$s" value="%3$s"> <span class="description">%4$s</span>',
            esc_html__( 'Desde el 1 de', SULAYR_TEXTDOMAIN ),
            esc_attr( substr( $min, 0, 7 ) ),
            esc_attr( substr( $default, 0, 7 ) ),
            esc_html( sprintf( __( '(propuesta con el plazo de aviso: %s)', SULAYR_TEXTDOMAIN ), MemberDates::show( MemberChanges::proposed_date() ) ) )
        );
        echo '</p>';
        if ( $immediate ) {
            printf(
                '<p><label><input type="radio" name="eff[mode]" value="ahora"> %1$s</label><br><label class="sulayr-indent"><input type="checkbox" name="eff[confirm]" value="1"> %2$s</label><br><label class="sulayr-indent">%3$s <input type="text" name="eff[reason]" class="regular-text"></label></p>',
                esc_html__( 'Al momento (hoy)', SULAYR_TEXTDOMAIN ),
                esc_html__( 'Confirmo que el recibo de este mes no está pagado', SULAYR_TEXTDOMAIN ),
                esc_html__( 'Motivo', SULAYR_TEXTDOMAIN )
            );
        }
        echo '</fieldset>';
    }

    /**
     * Abre un formulario de acción (sin cerrar).
     *
     * @param   int    $user_id Socio.
     * @param   string $do      Acción.
     * @param   array  $hidden  Campos ocultos extra.
     * @param   string $class   Clase del formulario.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function open_form( int $user_id, string $do, array $hidden = [], string $class = '' ): void {
        printf( '<form method="post" action="%s" class="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $class ) );
        wp_nonce_field( self::ACTION . '_' . $user_id );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="user_id" value="%d"><input type="hidden" name="do" value="%s">', esc_attr( self::ACTION ), $user_id, esc_attr( $do ) );
        foreach ( $hidden as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
        }
    }

    /**
     * «2026-12» → «2026-12-01», o '' si no es válido.
     *
     * @param   string $month Y-m.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function month_to_date( string $month ): string {
        $month = sanitize_text_field( $month );

        return 1 === preg_match( '/^\d{4}-\d{2}$/', $month ) && MemberDates::is_first_of_month( $month . '-01' ) ? $month . '-01' : '';
    }

    /**
     * Vuelve a la ficha con un aviso.
     *
     * @param   int    $user_id Socio.
     * @param   string $type    success o error.
     * @param   string $text    Texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function back( int $user_id, string $type, string $text ): void {
        RequestsPage::set_notice( $type, $text );
        wp_safe_redirect( MembersMenu::member_url( $user_id ) . '#acciones' );
        exit;
    }
}
