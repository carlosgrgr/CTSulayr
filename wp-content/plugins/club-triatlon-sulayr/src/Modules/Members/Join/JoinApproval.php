<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberFields;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberTasks;
use ClubTriatlonSulayr\Modules\Members\MemberTypes;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\ReservationsAccess;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Aprobación y rechazo de solicitudes de alta (spec §5.3, §6.6).
 */
class JoinApproval {

    public const MODE_CREATE = 'crear';
    public const MODE_LINK = 'vincular';

    /** Estado transitorio mientras se aprueba, para que dos clics no aprueben dos veces. */
    private const STATUS_APPROVING = 'aprobando';

    /**
     * Matrícula propuesta (D5): la normal o la de reingreso, salvo que haya
     * una promoción vigente hoy. En un reingreso con promoción, la más baja.
     *
     * @param   bool $is_return Reingreso.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function proposed_fee( bool $is_return ): float {
        $fee = (float) MemberSettings::get( $is_return ? 'enrollment_fee_return' : 'enrollment_fee' );
        $today = current_time( 'Y-m-d' );

        foreach ( (array) MemberSettings::get( 'enrollment_promotions' ) as $promo ) {
            $start = (string) ( $promo['start'] ?? '' );
            $end = (string) ( $promo['end'] ?? '' );
            if ( '' === $start || '' === $end || $start > $today || $end < $today ) {
                continue;
            }

            $amount = (float) ( $promo['amount'] ?? 0 );
            $fee = $is_return ? min( $fee, $amount ) : $amount;
        }

        return round( max( 0, $fee ), 2 );
    }

    /**
     * Aprueba una solicitud.
     *
     * @param   int   $request_id Solicitud.
     * @param   array $choice {
     *     @type string $mode          MODE_CREATE o MODE_LINK.
     *     @type int    $user_id       Usuario a vincular (MODE_LINK).
     *     @type string $start_date    Fecha de alta Y-m-d.
     *     @type string $member_type   Tipo de socio.
     *     @type array  $services      Servicios [ [service_id, option_key] ].
     *     @type float  $fee           Matrícula aprobada.
     *     @type string $fee_reason    Motivo si difiere de la propuesta.
     *     @type bool   $update_email  Poner en la cuenta vinculada el email de la solicitud.
     * }
     * @return 	int|\WP_Error ID del socio.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function approve( int $request_id, array $choice ) {
        global $wpdb;

        $request = JoinRequests::get( $request_id );
        if ( null === $request || JoinRequests::STATUS_PENDING !== $request->status ) {
            return new \WP_Error( 'sulayr_join_state', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        $f = $request->fields;
        $email = Identity::normalize_email( (string) ( $f['email'] ?? '' ) );
        $matches = DuplicateFinder::find( $email, Identity::normalize_dni( (string) ( $f['dni'] ?? '' ) ) );

        $error = self::check_choice( $choice, $matches, $email );
        if ( null !== $error ) {
            return $error;
        }

        // Reserva la solicitud: si otra petición ya la está aprobando, no se toca.
        $claimed = $wpdb->update(
            \ClubTriatlonSulayr\Modules\Members\Database\MembersSchema::table( \ClubTriatlonSulayr\Modules\Members\Database\MembersSchema::REQUESTS ),
            [ 'status' => self::STATUS_APPROVING ],
            [ 'id' => $request_id, 'status' => JoinRequests::STATUS_PENDING ],
            [ '%s' ],
            [ '%d', '%s' ]
        );
        if ( 1 !== (int) $claimed ) {
            return new \WP_Error( 'sulayr_join_state', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        try {
            $result = MemberLog::with_note( sprintf( __( 'Alta, solicitud #%d', SULAYR_TEXTDOMAIN ), $request_id ), function () use ( $request, $choice ) {
                return self::do_approve( $request, $choice );
            } );
        } catch ( \Throwable $e ) {
            $result = new \WP_Error( 'sulayr_join_failed', $e->getMessage() );
        }

        if ( is_wp_error( $result ) ) {
            $wpdb->update(
                \ClubTriatlonSulayr\Modules\Members\Database\MembersSchema::table( \ClubTriatlonSulayr\Modules\Members\Database\MembersSchema::REQUESTS ),
                [ 'status' => JoinRequests::STATUS_PENDING ],
                [ 'id' => $request_id, 'status' => self::STATUS_APPROVING ],
                [ '%s' ],
                [ '%d', '%s' ]
            );
        }

        return $result;
    }

    /**
     * Rechaza una solicitud y avisa al solicitante (y al tutor).
     *
     * @param   int    $request_id Solicitud.
     * @param   string $reason     Motivo (obligatorio).
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function reject( int $request_id, string $reason ) {
        $reason = trim( $reason );
        if ( '' === $reason ) {
            return new \WP_Error( 'sulayr_join_reason', __( 'Escribe el motivo del rechazo.', SULAYR_TEXTDOMAIN ) );
        }

        $request = JoinRequests::get( $request_id );
        if ( null === $request || JoinRequests::STATUS_PENDING !== $request->status ) {
            return new \WP_Error( 'sulayr_join_state', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        JoinRequests::resolve( $request_id, JoinRequests::STATUS_REJECTED, $request->fields, 0, $reason );

        $f = $request->fields;
        MemberMailer::send( 'rejected', self::recipients( $f, (string) ( $f['email'] ?? '' ) ), [
            'nombre' => (string) ( $f['first_name'] ?? '' ),
            'nombre_completo' => JoinRequests::full_name( $request ),
            'motivo' => $reason,
        ] );

        return true;
    }

    /**
     * Comprueba la decisión del gestor antes de tocar nada.
     *
     * @param   array  $choice  Decisión.
     * @param   array  $matches Coincidencias.
     * @param   string $email   Email de la solicitud.
     * @return 	\WP_Error|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function check_choice( array $choice, array $matches, string $email ): ?\WP_Error {
        if ( DuplicateFinder::has_member( $matches ) ) {
            return new \WP_Error( 'sulayr_join_member', __( 'El email o el DNI ya son de un socio en vigor. Solo se puede rechazar la solicitud.', SULAYR_TEXTDOMAIN ) );
        }

        $mode = (string) ( $choice['mode'] ?? '' );
        if ( self::MODE_CREATE === $mode ) {
            if ( false !== email_exists( $email ) ) {
                return new \WP_Error( 'sulayr_join_email', __( 'Ese email ya tiene cuenta en la red: vincula esa cuenta o cambia el email de la solicitud.', SULAYR_TEXTDOMAIN ) );
            }
        } elseif ( self::MODE_LINK === $mode ) {
            if ( false === isset( $matches[ (int) ( $choice['user_id'] ?? 0 ) ] ) ) {
                return new \WP_Error( 'sulayr_join_link', __( 'Elige una de las cuentas que coinciden.', SULAYR_TEXTDOMAIN ) );
            }
        } else {
            return new \WP_Error( 'sulayr_join_mode', __( 'Elige si se crea una cuenta o se vincula una existente.', SULAYR_TEXTDOMAIN ) );
        }

        $start = (string) ( $choice['start_date'] ?? '' );
        $date = \DateTime::createFromFormat( '!Y-m-d', $start );
        if ( false === $date || $date->format( 'Y-m-d' ) !== $start ) {
            return new \WP_Error( 'sulayr_join_date', __( 'La fecha de alta no es válida.', SULAYR_TEXTDOMAIN ) );
        }

        if ( false === isset( MemberSettings::member_types()[ (string) ( $choice['member_type'] ?? '' ) ] ) ) {
            return new \WP_Error( 'sulayr_join_type', __( 'Elige el tipo de socio.', SULAYR_TEXTDOMAIN ) );
        }

        if ( empty( $choice['services'] ) ) {
            return new \WP_Error( 'sulayr_join_services', __( 'Faltan los servicios.', SULAYR_TEXTDOMAIN ) );
        }

        $fee = (float) ( $choice['fee'] ?? -1 );
        if ( $fee < 0 ) {
            return new \WP_Error( 'sulayr_join_fee', __( 'La matrícula no puede ser negativa.', SULAYR_TEXTDOMAIN ) );
        }

        $is_return = self::MODE_LINK === $mode && DuplicateFinder::is_return( $matches[ (int) $choice['user_id'] ]['user'] );
        if ( abs( $fee - self::proposed_fee( $is_return ) ) > 0.004 && '' === trim( (string) ( $choice['fee_reason'] ?? '' ) ) ) {
            return new \WP_Error( 'sulayr_join_fee_reason', __( 'Si cambias la matrícula propuesta, escribe el motivo.', SULAYR_TEXTDOMAIN ) );
        }

        return null;
    }

    /**
     * Crea o vincula la cuenta y escribe la ficha, el historial y los pasos.
     *
     * @param   object $request Solicitud.
     * @param   array  $choice  Decisión ya comprobada.
     * @return 	int|\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function do_approve( object $request, array $choice ) {
        $f = $request->fields;
        $start = (string) $choice['start_date'];
        $email = Identity::normalize_email( (string) $f['email'] );
        $full_name = trim( $f['first_name'] . ' ' . $f['last_name'] );
        $is_return = false;

        if ( self::MODE_CREATE === $choice['mode'] ) {
            $user_id = wp_insert_user( [
                'user_login' => self::unique_login( $email ),
                'user_email' => $email,
                'user_pass' => wp_generate_password( 24, true, true ),
                'first_name' => $f['first_name'],
                'last_name' => $f['last_name'],
                'display_name' => $full_name,
                'role' => 'athlete',
            ] );
            if ( is_wp_error( $user_id ) ) {
                return $user_id;
            }
            $user_id = (int) $user_id;
            $mode_label = __( 'Cuenta nueva', SULAYR_TEXTDOMAIN );
        } else {
            $user_id = (int) $choice['user_id'];
            $user = get_userdata( $user_id );
            $is_return = DuplicateFinder::is_return( $user );

            if ( false === is_user_member_of_blog( $user_id, get_current_blog_id() ) ) {
                add_user_to_blog( get_current_blog_id(), $user_id, 'athlete' );
            } elseif ( false === in_array( 'athlete', (array) $user->roles, true ) ) {
                $user->add_role( 'athlete' );
            }

            if ( ! empty( $choice['update_email'] ) && strtolower( $user->user_email ) !== $email && false === email_exists( $email ) ) {
                // El aviso de WordPress al email anterior sobra: el socio recibe solo la bienvenida (B7).
                add_filter( 'send_email_change_email', '__return_false', 99 );
                wp_update_user( [ 'ID' => $user_id, 'user_email' => $email ] );
                remove_filter( 'send_email_change_email', '__return_false', 99 );
            }

            foreach ( [ 'first_name', 'last_name' ] as $key ) {
                if ( (string) get_user_meta( $user_id, $key, true ) !== $f[ $key ] ) {
                    update_user_meta( $user_id, $key, $f[ $key ] );
                }
            }
            $mode_label = $is_return ? __( 'Reingreso', SULAYR_TEXTDOMAIN ) : __( 'Cuenta de la red vinculada', SULAYR_TEXTDOMAIN );
        }

        // Ficha.
        $meta = [
            CustomFields::USER_PROFILE_PHONE => (string) $f['phone'],
            CustomFields::USER_PROFILE_DNI => (string) $f['dni'],
            CustomFields::USER_PROFILE_BIRTH => MemberDates::iso_to_acf( (string) $f['birth_date'] ),
            CustomFields::USER_PROFILE_JOIN_DATE => MemberDates::iso_to_acf( $start ),
            CustomFields::MEMBER_TYPE => (string) $choice['member_type'],
        ];
        if ( '' !== (string) ( $f['sex'] ?? '' ) ) {
            $meta[ CustomFields::MEMBER_SEX ] = (string) $f['sex'];
        }
        if ( '' !== (string) ( $f['tshirt'] ?? '' ) ) {
            $meta[ CustomFields::MEMBER_TSHIRT_SIZE ] = (string) $f['tshirt'];
        }
        if ( ! empty( $f['minor'] ) && is_array( $f['guardian'] ?? null ) ) {
            foreach ( [ 'name', 'dni', 'phone', 'email' ] as $key ) {
                $meta[ CustomFields::MEMBER_GUARDIAN . '_' . $key ] = (string) ( $f['guardian'][ $key ] ?? '' );
            }
        }
        // Tipo con fecha de alta en el historial (los recibos lo leen de ahí).
        MemberTypes::change( $user_id, (string) $choice['member_type'], $start );
        foreach ( $meta as $key => $value ) {
            if ( (string) get_user_meta( $user_id, $key, true ) !== $value ) {
                update_user_meta( $user_id, $key, $value );
            }
        }
        if ( $is_return && '' !== (string) get_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, true ) ) {
            delete_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE );
        }

        // Número de socio (D14): se conserva el que tenga.
        $number = (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true );
        if ( '' === $number ) {
            $number = (string) MemberFields::next_number();
            update_user_meta( $user_id, CustomFields::MEMBER_NUMBER, $number );
        }

        // Estado: el periodo abierto (p. ej. la baja) se cierra el día antes del alta.
        $day_before = gmdate( 'Y-m-d', strtotime( $start . ' -1 day' ) );
        $current = StatusPeriods::current( $user_id );
        if ( null !== $current ) {
            $end = null !== $current->start_date && (string) $current->start_date > $day_before ? (string) $current->start_date : $day_before;
            StatusPeriods::close( (int) $current->id, $end );
        }
        StatusPeriods::open( $user_id, CustomFields::STATUS_ACTIVE, $start, $mode_label );

        // Servicios desde la fecha de alta. En un reingreso no queda ninguno abierto.
        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            MemberServices::close( (int) $row->id, (string) $row->start_date > $day_before ? (string) $row->start_date : $day_before );
        }
        $service_ids = [];
        foreach ( (array) $choice['services'] as $item ) {
            MemberServices::open( $user_id, (int) $item['service_id'], (string) $item['option_key'], $start, MemberServices::ORIGIN_JOIN );
            $service_ids[] = (int) $item['service_id'];
        }

        MemberConsents::attach_request( (int) $request->id, $user_id );

        foreach ( MemberTasks::join_labels( $service_ids ) as $label ) {
            MemberTasks::create( $user_id, $label, MemberTasks::CONTEXT_JOIN );
        }

        $fee = round( (float) $choice['fee'], 2 );
        $f['start_date'] = $start;
        $f['services'] = array_values( (array) $choice['services'] );
        $f['approval'] = [
            'mode' => $choice['mode'],
            'user_id' => $user_id,
            'is_return' => $is_return,
            'member_type' => (string) $choice['member_type'],
            'member_number' => (int) $number,
            'fee' => $fee,
            'fee_proposed' => self::proposed_fee( $is_return ),
            'fee_reason' => trim( (string) ( $choice['fee_reason'] ?? '' ) ),
        ];
        JoinRequests::resolve( (int) $request->id, JoinRequests::STATUS_APPROVED, $f, $user_id );

        MemberLog::record( $user_id, MemberLog::AREA_DATA, __( 'Matrícula', SULAYR_TEXTDOMAIN ), '', number_format( $fee, 2, ',', '' ) . ' €', $f['approval']['fee_reason'] );

        // Estado y natación para reservas: hoy, o la tarea diaria el día del alta.
        ReservationsAccess::apply( $user_id );

        self::send_welcome( $user_id, $f, $start, $service_ids );

        // 5.3: si los recibos del mes de alta ya están generados, el suyo también (después de la bienvenida).
        Receipts::after_join( $user_id, $start );

        return $user_id;
    }

    /**
     * Email de bienvenida (D15) al socio y, si es menor, también al tutor, en un solo envío.
     *
     * @param   int    $user_id     Socio.
     * @param   array  $f           Datos de la solicitud.
     * @param   string $start       Fecha de alta.
     * @param   int[]  $service_ids Servicios.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function send_welcome( int $user_id, array $f, string $start, array $service_ids ): bool {
        $user = get_userdata( $user_id );
        if ( false === $user ) {
            return false;
        }

        $blocks = '';
        foreach ( $service_ids as $service_id ) {
            $service = ServiceCatalog::get( (int) $service_id );
            if ( null !== $service && '' !== trim( $service->welcome_text ) ) {
                $blocks .= wp_kses_post( $service->welcome_text ) . "\n";
            }
        }

        $whatsapp = (string) MemberSettings::get( 'whatsapp_link' );

        return MemberMailer::send( 'welcome', self::recipients( $f, $user->user_email ), [
            'nombre' => (string) ( $f['first_name'] ?? $user->first_name ),
            'nombre_completo' => trim( ( $f['first_name'] ?? '' ) . ' ' . ( $f['last_name'] ?? '' ) ),
            'numero_socio' => (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true ),
            'fecha_alta' => date_i18n( 'd/m/Y', strtotime( $start ) ),
            'enlace_contrasena_html' => MemberMailer::button( self::password_url( $user ), __( 'Crear mi contraseña', SULAYR_TEXTDOMAIN ) ),
            'enlace_whatsapp_html' => MemberMailer::link( $whatsapp ),
            'bloques_servicios_html' => $blocks,
        ] );
    }

    /**
     * Enlace para crear la contraseña: en Mi cuenta si hay WooCommerce, si no en wp-login.
     *
     * @param   \WP_User $user Usuario.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function password_url( \WP_User $user ): string {
        $key = get_password_reset_key( $user );
        if ( is_wp_error( $key ) ) {
            return function_exists( 'wc_lostpassword_url' ) ? wc_lostpassword_url() : wp_lostpassword_url();
        }

        if ( function_exists( 'wc_get_endpoint_url' ) && function_exists( 'wc_get_page_permalink' ) ) {
            return add_query_arg(
                [ 'key' => $key, 'id' => $user->ID, 'login' => rawurlencode( $user->user_login ) ],
                wc_get_endpoint_url( 'lost-password', '', wc_get_page_permalink( 'myaccount' ) )
            );
        }

        return network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
    }

    /**
     * Destinatarios: el socio y, si es menor, el tutor (D6).
     *
     * @param   array  $f     Datos de la solicitud.
     * @param   string $email Email del socio.
     * @return 	string[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function recipients( array $f, string $email ): array {
        $to = [ $email ];
        if ( ! empty( $f['minor'] ) && ! empty( $f['guardian']['email'] ) ) {
            $to[] = (string) $f['guardian']['email'];
        }

        return $to;
    }

    /**
     * Nombre de usuario libre a partir del email. En multisite solo valen
     * minúsculas y números, con 4 caracteres como mínimo.
     *
     * @param   string $email Email.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function unique_login( string $email ): string {
        $base = (string) preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( (string) strtok( $email, '@' ) ) ) );
        $base = substr( $base, 0, 50 );
        if ( strlen( $base ) < 4 ) {
            $base = 'socio' . $base;
        }

        $login = $base;
        for ( $i = 2; username_exists( $login ); $i++ ) {
            $login = $base . $i;
        }

        return $login;
    }
}
