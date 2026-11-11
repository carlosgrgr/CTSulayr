<?php
namespace ClubTriatlonSulayr\Modules\Members\Lifecycle;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\Join\MemberMailer;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberTasks;
use ClubTriatlonSulayr\Modules\Members\ReservationsAccess;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cambios de servicios, de tipo de socio, bajas y congelaciones (spec §6).
 *
 * Cada cambio es una fila de sulayr_member_requests con su fecha efectiva:
 * - pendiente: la pidió el socio y espera al gestor;
 * - aprobada: decidida (por el gestor o al aprobar una petición). Mientras
 *   applied_at sea nulo está programada; la aplica la tarea diaria el día
 *   efectivo, o al momento si es inmediata (D27).
 * - rechazada, cancelada (por el socio) o anulada (por el gestor antes de
 *   aplicarse).
 *
 * El historial (servicios y periodos) solo se escribe al aplicar, con las
 * fechas efectivas. Así, anular un cambio programado no toca el historial.
 */
class MemberChanges {

    public const TYPE_SERVICES = 'cambio';
    public const TYPE_MEMBER_TYPE = 'tipo';
    public const TYPE_LEAVE = 'baja';
    public const TYPE_FREEZE = 'congelacion';

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_APPROVED = 'aprobada';
    public const STATUS_REJECTED = 'rechazada';
    public const STATUS_CANCELLED = 'cancelada';
    public const STATUS_ANNULLED = 'anulada';

    public const BY_MEMBER = 'socio';
    public const BY_MANAGER = 'gestor';

    /**
     * Nombre de un tipo.
     *
     * @param   string $type Tipo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function type_label( string $type ): string {
        $labels = [
            self::TYPE_SERVICES => __( 'cambio de servicios', SULAYR_TEXTDOMAIN ),
            self::TYPE_MEMBER_TYPE => __( 'cambio de tipo de socio', SULAYR_TEXTDOMAIN ),
            self::TYPE_LEAVE => __( 'baja', SULAYR_TEXTDOMAIN ),
            self::TYPE_FREEZE => __( 'congelación', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $type ] ?? $type;
    }

    /**
     * Nombre de un estado.
     *
     * @param   string $status Estado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function status_label( string $status ): string {
        $labels = [
            self::STATUS_PENDING => __( 'Pendiente', SULAYR_TEXTDOMAIN ),
            self::STATUS_APPROVED => __( 'Aprobada', SULAYR_TEXTDOMAIN ),
            self::STATUS_REJECTED => __( 'Rechazada', SULAYR_TEXTDOMAIN ),
            self::STATUS_CANCELLED => __( 'Cancelada por el socio', SULAYR_TEXTDOMAIN ),
            self::STATUS_ANNULLED => __( 'Anulada', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $status ] ?? $status;
    }

    /**
     * Fecha efectiva propuesta para algo pedido hoy (6.1).
     *
     * @return 	string Y-m-d.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function proposed_date(): string {
        return MemberDates::effective_for( current_time( 'Y-m-d' ), (int) MemberSettings::get( 'notice_days' ) );
    }

    /**
     * Petición del socio desde Mi cuenta (6.2): queda pendiente y avisa al club.
     *
     * @param   int    $user_id Socio.
     * @param   string $type    TYPE_SERVICES o TYPE_LEAVE.
     * @param   array  $data    Datos (servicios deseados, motivo).
     * @return 	int|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function request( int $user_id, string $type, array $data ) {
        if ( false === in_array( $type, [ self::TYPE_SERVICES, self::TYPE_LEAVE ], true ) ) {
            return new \WP_Error( 'sulayr_change_type', __( 'Tipo de solicitud no válido.', SULAYR_TEXTDOMAIN ) );
        }
        if ( null !== self::pending_for_user( $user_id ) ) {
            return new \WP_Error( 'sulayr_change_pending', __( 'Ya tienes una solicitud pendiente. Cancélala si quieres pedir otra cosa.', SULAYR_TEXTDOMAIN ) );
        }

        $data['origin'] = self::BY_MEMBER;
        $id = self::insert( $user_id, $type, self::STATUS_PENDING, $data, self::proposed_date() );

        MemberMailer::send( 'request_club', [ (string) MemberSettings::get( 'club_email' ) ], [
            'tipo' => self::type_label( $type ),
            'nombre' => (string) get_user_meta( $user_id, 'first_name', true ),
            'nombre_completo' => self::name( $user_id ),
        ] );

        return $id;
    }

    /**
     * Cambio decidido por el gestor directamente (sin petición del socio).
     *
     * @param   int    $user_id   Socio.
     * @param   string $type      TYPE_*.
     * @param   array  $data      Datos.
     * @param   string $effective Fecha efectiva Y-m-d (día 1), o '' si es inmediato.
     * @param   string $immediate_reason Motivo del cambio inmediato ('' si no lo es).
     * @return 	int|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function schedule( int $user_id, string $type, array $data, string $effective, string $immediate_reason = '' ) {
        $immediate = '' === $effective;
        $error = self::check_date( $effective, $immediate, $immediate_reason, MemberDates::first_of_month_after( current_time( 'Y-m-d' ) ) );
        if ( null !== $error ) {
            return $error;
        }

        $data['origin'] = self::BY_MANAGER;
        $data['immediate'] = $immediate;
        $data['immediate_reason'] = $immediate_reason;
        $date = $immediate ? current_time( 'Y-m-d' ) : $effective;

        $id = self::insert( $user_id, $type, self::STATUS_APPROVED, $data, $date, true );
        self::after_decision( self::get( $id ), true );

        return $id;
    }

    /**
     * Aprueba la petición de un socio.
     *
     * @param   int    $id        Petición.
     * @param   string $effective Fecha efectiva (día 1), o '' si es inmediata.
     * @param   string $immediate_reason Motivo del cambio inmediato.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function approve( int $id, string $effective, string $immediate_reason = '' ) {
        $row = self::get( $id );
        if ( null === $row || self::STATUS_PENDING !== $row->status ) {
            return new \WP_Error( 'sulayr_change_state', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }

        $immediate = '' === $effective;
        $error = self::check_date( $effective, $immediate, $immediate_reason, self::min_date_for( $row ) );
        if ( null !== $error ) {
            return $error;
        }

        $data = $row->fields;
        $data['immediate'] = $immediate;
        $data['immediate_reason'] = $immediate_reason;
        self::update( $id, [
            'status' => self::STATUS_APPROVED,
            'effective_date' => $immediate ? current_time( 'Y-m-d' ) : $effective,
            'data' => (string) wp_json_encode( $data ),
            'resolved_by' => get_current_user_id(),
            'resolved_at' => current_time( 'mysql' ),
        ] );

        self::after_decision( self::get( $id ), false );

        return true;
    }

    /**
     * Rechaza la petición de un socio y le avisa (D31).
     *
     * @param   int    $id     Petición.
     * @param   string $reason Motivo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function reject( int $id, string $reason ) {
        $reason = trim( $reason );
        $row = self::get( $id );
        if ( null === $row || self::STATUS_PENDING !== $row->status ) {
            return new \WP_Error( 'sulayr_change_state', __( 'La solicitud ya no está pendiente.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === $reason ) {
            return new \WP_Error( 'sulayr_change_reason', __( 'Escribe el motivo del rechazo.', SULAYR_TEXTDOMAIN ) );
        }

        self::update( $id, [
            'status' => self::STATUS_REJECTED,
            'reason' => substr( $reason, 0, 255 ),
            'resolved_by' => get_current_user_id(),
            'resolved_at' => current_time( 'mysql' ),
        ] );
        MemberLog::record( (int) $row->user_id, MemberLog::AREA_STATUS, __( 'Solicitud', SULAYR_TEXTDOMAIN ), self::describe( $row ), __( 'Rechazada', SULAYR_TEXTDOMAIN ), $reason );

        MemberMailer::send( 'change_result', self::recipients( (int) $row->user_id ), [
            'nombre' => (string) get_user_meta( (int) $row->user_id, 'first_name', true ),
            'nombre_completo' => self::name( (int) $row->user_id ),
            'tipo' => self::type_label( (string) $row->type ),
            'resultado' => __( 'rechazada', SULAYR_TEXTDOMAIN ),
            'detalle' => sprintf( __( 'Motivo: %s', SULAYR_TEXTDOMAIN ), $reason ),
            'motivo' => $reason,
        ] );

        return true;
    }

    /**
     * El socio cancela su petición pendiente.
     *
     * @param   int $id      Petición.
     * @param   int $user_id Socio que cancela (debe ser el suyo).
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function cancel( int $id, int $user_id ): bool {
        $row = self::get( $id );
        if ( null === $row || (int) $row->user_id !== $user_id || self::STATUS_PENDING !== $row->status ) {
            return false;
        }

        self::update( $id, [ 'status' => self::STATUS_CANCELLED, 'resolved_by' => $user_id, 'resolved_at' => current_time( 'mysql' ) ] );
        MemberLog::record( $user_id, MemberLog::AREA_STATUS, __( 'Solicitud', SULAYR_TEXTDOMAIN ), self::describe( $row ), __( 'Cancelada por el socio', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * El gestor anula un cambio programado que aún no se ha aplicado.
     *
     * @param   int    $id     Cambio.
     * @param   string $reason Motivo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function annul( int $id, string $reason = '' ) {
        $row = self::get( $id );
        if ( null === $row || self::STATUS_APPROVED !== $row->status || null !== $row->applied_at ) {
            return new \WP_Error( 'sulayr_change_state', __( 'Ese cambio ya se aplicó o no está programado.', SULAYR_TEXTDOMAIN ) );
        }

        self::update( $id, [ 'status' => self::STATUS_ANNULLED, 'reason' => substr( trim( $reason ), 0, 255 ) ] );
        MemberLog::record( (int) $row->user_id, MemberLog::AREA_STATUS, __( 'Cambio programado', SULAYR_TEXTDOMAIN ), self::describe( $row ), __( 'Anulado', SULAYR_TEXTDOMAIN ), $reason );

        return true;
    }

    /**
     * Cambia la vuelta de una congelación (en curso o programada).
     *
     * @param   int    $user_id Socio.
     * @param   string $return  Día de vuelta Y-m-d (día 1).
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function set_freeze_return( int $user_id, string $return ) {
        if ( false === MemberDates::is_first_of_month( $return ) || $return <= current_time( 'Y-m-d' ) ) {
            return new \WP_Error( 'sulayr_freeze_return', __( 'La vuelta tiene que ser el día 1 de un mes próximo.', SULAYR_TEXTDOMAIN ) );
        }

        $current = StatusPeriods::current( $user_id );
        if ( null !== $current && CustomFields::STATUS_FROZEN === $current->status ) {
            if ( $return <= (string) $current->start_date ) {
                return new \WP_Error( 'sulayr_freeze_return', __( 'La vuelta tiene que ser posterior al inicio de la congelación.', SULAYR_TEXTDOMAIN ) );
            }
            StatusPeriods::set_planned_end( (int) $current->id, MemberDates::add_days( $return, -1 ) );
            return true;
        }

        foreach ( self::scheduled_for_user( $user_id ) as $row ) {
            if ( self::TYPE_FREEZE === $row->type ) {
                if ( $return <= (string) $row->effective_date ) {
                    return new \WP_Error( 'sulayr_freeze_return', __( 'La vuelta tiene que ser posterior al inicio de la congelación.', SULAYR_TEXTDOMAIN ) );
                }
                $data = $row->fields;
                $old = (string) ( $data['return'] ?? '' );
                $data['return'] = $return;
                self::update( (int) $row->id, [ 'data' => (string) wp_json_encode( $data ) ] );
                MemberLog::record( $user_id, MemberLog::AREA_STATUS, __( 'Vuelta de la congelación programada', SULAYR_TEXTDOMAIN ), $old, $return );
                return true;
            }
        }

        return new \WP_Error( 'sulayr_freeze_none', __( 'El socio no está congelado ni tiene una congelación programada.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Aplica los cambios aprobados cuya fecha ha llegado. Idempotente.
     *
     * @return 	int Cambios aplicados.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function apply_due(): int {
        global $wpdb;

        $table = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE type IN (%s, %s, %s, %s) AND status = %s AND applied_at IS NULL AND effective_date <= %s ORDER BY effective_date ASC, id ASC",
            self::TYPE_SERVICES,
            self::TYPE_MEMBER_TYPE,
            self::TYPE_LEAVE,
            self::TYPE_FREEZE,
            self::STATUS_APPROVED,
            current_time( 'Y-m-d' )
        ) );

        $applied = 0;
        foreach ( $rows as $row ) {
            if ( self::apply( self::decode( $row ) ) ) {
                $applied++;
            }
        }

        return $applied;
    }

    /**
     * Devuelve a activo a los congelados cuyo fin previsto ya pasó (6.4, C4):
     * vuelven el día siguiente al fin previsto con los mismos servicios, que
     * siguieron abiertos durante la congelación.
     *
     * @return 	int Socios que vuelven.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function return_from_freezes(): int {
        $count = 0;
        foreach ( StatusPeriods::overdue( CustomFields::STATUS_FROZEN, current_time( 'Y-m-d' ) ) as $period ) {
            $user_id = (int) $period->user_id;
            // Un fin previsto anterior al inicio no debería existir; si existe, acaba el día que empezó.
            $end = null !== $period->start_date && (string) $period->planned_end_date < (string) $period->start_date ? (string) $period->start_date : (string) $period->planned_end_date;
            MemberLog::with_note( __( 'Fin de congelación', SULAYR_TEXTDOMAIN ), function () use ( $period, $user_id, $end ) {
                StatusPeriods::close( (int) $period->id, $end );
                StatusPeriods::open( $user_id, CustomFields::STATUS_ACTIVE, MemberDates::add_days( $end, 1 ), __( 'Fin de congelación', SULAYR_TEXTDOMAIN ) );
            } );
            ReservationsAccess::apply( $user_id );
            $count++;
        }

        return $count;
    }

    /**
     * Aplica un cambio al historial con su fecha efectiva.
     *
     * @param   object $row Cambio decodificado.
     * @return 	bool True si se aplicó.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function apply( object $row ): bool {
        if ( self::STATUS_APPROVED !== $row->status || null !== $row->applied_at ) {
            return false;
        }

        $user_id = (int) $row->user_id;
        $date = (string) $row->effective_date;
        $note = sprintf( '%s #%d', ucfirst( self::type_label( (string) $row->type ) ), (int) $row->id );
        if ( ! empty( $row->fields['immediate'] ) ) {
            $note .= ' · ' . sprintf( __( 'inmediato: %s', SULAYR_TEXTDOMAIN ), (string) ( $row->fields['immediate_reason'] ?? '' ) );
        }

        $result = MemberLog::with_note( $note, function () use ( $row, $user_id, $date ) {
            $period = StatusPeriods::current( $user_id );
            if ( null !== $period && CustomFields::STATUS_LEFT === $period->status && self::TYPE_LEAVE !== $row->type ) {
                return __( 'El socio está de baja.', SULAYR_TEXTDOMAIN );
            }

            switch ( $row->type ) {
                case self::TYPE_SERVICES:
                    self::apply_services( $user_id, (array) ( $row->fields['services'] ?? [] ), $date, self::BY_MEMBER === ( $row->fields['origin'] ?? '' ) ? MemberServices::ORIGIN_REQUEST : MemberServices::ORIGIN_MANAGER );
                    return '';
                case self::TYPE_MEMBER_TYPE:
                    update_user_meta( $user_id, CustomFields::MEMBER_TYPE, (string) ( $row->fields['member_type'] ?? '' ) );
                    return '';
                case self::TYPE_LEAVE:
                    self::apply_leave( $user_id, $date );
                    return '';
                case self::TYPE_FREEZE:
                    if ( null === $period || CustomFields::STATUS_ACTIVE !== $period->status ) {
                        return __( 'El socio no estaba activo.', SULAYR_TEXTDOMAIN );
                    }
                    StatusPeriods::close( (int) $period->id, self::end_before( (string) $period->start_date, $date ) );
                    StatusPeriods::open( $user_id, CustomFields::STATUS_FROZEN, $date, (string) ( $row->fields['reason'] ?? '' ), '', MemberDates::add_days( (string) $row->fields['return'], -1 ) );
                    return '';
            }

            return __( 'Tipo desconocido.', SULAYR_TEXTDOMAIN );
        } );

        if ( '' !== $result ) {
            // No se puede aplicar: queda anulado con el motivo, sin tocar el historial.
            self::update( (int) $row->id, [ 'status' => self::STATUS_ANNULLED, 'reason' => substr( $result, 0, 255 ) ] );
            MemberLog::record( $user_id, MemberLog::AREA_STATUS, __( 'Cambio programado', SULAYR_TEXTDOMAIN ), self::describe( $row ), __( 'No aplicado', SULAYR_TEXTDOMAIN ), $result );
            return false;
        }

        self::update( (int) $row->id, [ 'applied_at' => current_time( 'mysql' ) ] );
        ReservationsAccess::apply( $user_id );

        return true;
    }

    /**
     * Cambio por ID, con ->fields, o null.
     *
     * @param   int $id Cambio.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $table = self::table();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type IN (%s, %s, %s, %s)", $id, self::TYPE_SERVICES, self::TYPE_MEMBER_TYPE, self::TYPE_LEAVE, self::TYPE_FREEZE ) );

        return null !== $row ? self::decode( $row ) : null;
    }

    /**
     * Petición pendiente del socio, o null.
     *
     * @param   int $user_id Socio.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pending_for_user( int $user_id ): ?object {
        $rows = self::query( 'user_id = %d AND status = %s', [ $user_id, self::STATUS_PENDING ] );

        return $rows[0] ?? null;
    }

    /**
     * Cambios aprobados que aún no se han aplicado, por fecha.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function scheduled_for_user( int $user_id ): array {
        return self::query( 'user_id = %d AND status = %s AND applied_at IS NULL', [ $user_id, self::STATUS_APPROVED ], 'effective_date ASC, id ASC' );
    }

    /**
     * Todos los cambios de un socio, del más reciente al más antiguo.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        return self::query( 'user_id = %d', [ $user_id ] );
    }

    /**
     * Peticiones pendientes de todos los socios.
     *
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pending(): array {
        return self::query( 'status = %s', [ self::STATUS_PENDING ], 'id ASC' );
    }

    /**
     * Texto corto de un cambio para pantallas y registro.
     *
     * @param   object $row Cambio decodificado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function describe( object $row ): string {
        $f = $row->fields;
        switch ( $row->type ) {
            case self::TYPE_SERVICES:
                $labels = array_map( function ( $item ) {
                    return MemberServices::label( (int) $item['service_id'], (string) $item['option_key'] );
                }, (array) ( $f['services'] ?? [] ) );
                return sprintf( __( 'Servicios: %s', SULAYR_TEXTDOMAIN ), implode( ', ', $labels ) );
            case self::TYPE_MEMBER_TYPE:
                $types = MemberSettings::member_types();
                $type = (string) ( $f['member_type'] ?? '' );
                return sprintf( __( 'Tipo de socio: %s', SULAYR_TEXTDOMAIN ), $types[ $type ] ?? $type );
            case self::TYPE_LEAVE:
                return __( 'Baja', SULAYR_TEXTDOMAIN ) . ( '' !== (string) ( $f['reason'] ?? '' ) ? ' (' . $f['reason'] . ')' : '' );
            case self::TYPE_FREEZE:
                return sprintf(
                    __( 'Congelación desde el %1$s, vuelta el %2$s%3$s', SULAYR_TEXTDOMAIN ),
                    MemberDates::show( (string) $row->effective_date ),
                    MemberDates::show( (string) ( $f['return'] ?? '' ) ),
                    '' !== (string) ( $f['reason'] ?? '' ) ? ' (' . $f['reason'] . ')' : ''
                ) . ( ! empty( $f['cancel_month'] ) ? ' · ' . sprintf( __( 'anula los servicios «se anula al congelar» del recibo de %s', SULAYR_TEXTDOMAIN ), (string) $f['cancel_month'] ) : '' );
        }

        return (string) $row->type;
    }

    /**
     * Fecha mínima para aprobar una petición: la calculada al pedirla, o el
     * día 1 del mes siguiente si esa ya pasó.
     *
     * @param   object $row Petición.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function min_date_for( object $row ): string {
        $stored = (string) $row->effective_date;
        $today = current_time( 'Y-m-d' );

        return $stored > $today ? $stored : MemberDates::first_of_month_after( $today );
    }

    /**
     * Pasos, emails y aplicación tras decidir un cambio.
     *
     * @param   object $row     Cambio aprobado.
     * @param   bool   $direct  Decidido por el gestor sin petición del socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function after_decision( object $row, bool $direct ): void {
        $user_id = (int) $row->user_id;

        MemberLog::record( $user_id, MemberLog::AREA_STATUS, $direct ? __( 'Cambio programado', SULAYR_TEXTDOMAIN ) : __( 'Solicitud aprobada', SULAYR_TEXTDOMAIN ), '', self::describe( $row ) . ' · ' . sprintf( __( 'desde %s', SULAYR_TEXTDOMAIN ), (string) $row->effective_date ), (string) ( $row->fields['immediate_reason'] ?? '' ) );

        $steps = [
            self::TYPE_SERVICES => [ 'steps_change', MemberTasks::CONTEXT_CHANGE ],
            self::TYPE_MEMBER_TYPE => [ 'steps_change', MemberTasks::CONTEXT_CHANGE ],
            self::TYPE_LEAVE => [ 'steps_leave', MemberTasks::CONTEXT_LEAVE ],
        ];
        if ( isset( $steps[ $row->type ] ) ) {
            foreach ( MemberSettings::labels( $steps[ $row->type ][0] ) as $label ) {
                MemberTasks::create( $user_id, $label, $steps[ $row->type ][1] );
            }
        }

        $placeholders = [
            'nombre' => (string) get_user_meta( $user_id, 'first_name', true ),
            'nombre_completo' => self::name( $user_id ),
            'tipo' => self::type_label( (string) $row->type ),
            'fecha_efectiva' => MemberDates::show( (string) $row->effective_date ),
        ];
        if ( self::TYPE_LEAVE === $row->type ) {
            MemberMailer::send( 'leave', self::recipients( $user_id ), $placeholders );
        } elseif ( false === $direct ) {
            MemberMailer::send( 'change_result', self::recipients( $user_id ), $placeholders + [
                'resultado' => __( 'aprobada', SULAYR_TEXTDOMAIN ),
                'detalle' => sprintf( __( 'Se aplica desde el %s.', SULAYR_TEXTDOMAIN ), MemberDates::show( (string) $row->effective_date ) ),
            ] );
        }

        if ( (string) $row->effective_date <= current_time( 'Y-m-d' ) ) {
            self::apply( $row );
        }
    }

    /**
     * Servicios: cierra los que ya no están el día anterior y abre los nuevos.
     *
     * @param   int    $user_id  Socio.
     * @param   array  $services Servicios deseados [ service_id, option_key ].
     * @param   string $date     Fecha efectiva.
     * @param   string $origin   MemberServices::ORIGIN_* de las filas nuevas.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function apply_services( int $user_id, array $services, string $date, string $origin ): void {
        $desired = [];
        foreach ( $services as $item ) {
            $desired[ (int) $item['service_id'] . '|' . (string) $item['option_key'] ] = $item;
        }

        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            $key = (int) $row->service_id . '|' . (string) $row->option_key;
            if ( isset( $desired[ $key ] ) ) {
                unset( $desired[ $key ] );
                continue;
            }
            MemberServices::close( (int) $row->id, self::end_before( (string) $row->start_date, $date ) );
        }

        foreach ( $desired as $item ) {
            MemberServices::open( $user_id, (int) $item['service_id'], (string) $item['option_key'], $date, $origin );
        }
    }

    /**
     * Baja (6.5): cierra servicios y periodo el día anterior y abre la baja.
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Primer día de baja.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function apply_leave( int $user_id, string $date ): void {
        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            MemberServices::close( (int) $row->id, self::end_before( (string) $row->start_date, $date ) );
        }

        $period = StatusPeriods::current( $user_id );
        if ( null !== $period && CustomFields::STATUS_LEFT === $period->status ) {
            return;
        }
        if ( null !== $period ) {
            StatusPeriods::close( (int) $period->id, self::end_before( (string) $period->start_date, $date ) );
        }
        StatusPeriods::open( $user_id, CustomFields::STATUS_LEFT, $date, __( 'Baja', SULAYR_TEXTDOMAIN ) );
        update_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, MemberDates::add_days( $date, -1 ) );
    }

    /**
     * Último día de una fila que acaba antes de $date, sin quedar antes de su inicio.
     *
     * @param   string $start Inicio de la fila ('' si se desconoce).
     * @param   string $date  Primer día del cambio.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function end_before( string $start, string $date ): string {
        $end = MemberDates::add_days( $date, -1 );

        return '' !== $start && $start > $end ? $start : $end;
    }

    /**
     * Comprueba la fecha elegida.
     *
     * @param   string $effective        Fecha (día 1) o '' si es inmediato.
     * @param   bool   $immediate        Inmediato.
     * @param   string $immediate_reason Motivo del inmediato.
     * @param   string $min              Primera fecha permitida.
     * @return 	\WP_Error|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function check_date( string $effective, bool $immediate, string $immediate_reason, string $min ): ?\WP_Error {
        if ( $immediate ) {
            return '' === trim( $immediate_reason ) ? new \WP_Error( 'sulayr_change_immediate', __( 'Un cambio al momento necesita el motivo y la confirmación de que el recibo del mes no está pagado.', SULAYR_TEXTDOMAIN ) ) : null;
        }

        if ( false === MemberDates::is_first_of_month( $effective ) ) {
            return new \WP_Error( 'sulayr_change_date', __( 'Los cambios se aplican el día 1 de un mes.', SULAYR_TEXTDOMAIN ) );
        }
        if ( $effective < $min ) {
            return new \WP_Error( 'sulayr_change_date', sprintf( __( 'La fecha no puede ser anterior al %s.', SULAYR_TEXTDOMAIN ), MemberDates::show( $min ) ) );
        }

        return null;
    }

    /**
     * Destinatarios: el socio y, si tiene tutor, también el tutor (D6).
     *
     * @param   int $user_id Socio.
     * @return 	string[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function recipients( int $user_id ): array {
        $user = get_userdata( $user_id );

        return array_filter( [
            false !== $user ? $user->user_email : '',
            (string) get_user_meta( $user_id, CustomFields::MEMBER_GUARDIAN . '_email', true ),
        ] );
    }

    /**
     * Nombre completo.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function name( int $user_id ): string {
        $user = get_userdata( $user_id );

        return HelperService::get_user_full_name( $user_id ) ?: ( false !== $user ? $user->display_name : '' );
    }

    /**
     * Inserta un cambio.
     *
     * @param   int    $user_id   Socio.
     * @param   string $type      Tipo.
     * @param   string $status    Estado.
     * @param   array  $data      Datos.
     * @param   string $effective Fecha efectiva.
     * @param   bool   $resolved  Decidido ya por quien lo crea.
     * @return 	int
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function insert( int $user_id, string $type, string $status, array $data, string $effective, bool $resolved = false ): int {
        global $wpdb;

        $now = current_time( 'mysql' );
        $wpdb->insert(
            self::table(),
            [
                'type' => $type,
                'user_id' => $user_id,
                'status' => $status,
                'data' => (string) wp_json_encode( $data ),
                'effective_date' => $effective,
                'resolved_by' => $resolved ? get_current_user_id() : 0,
                'resolved_at' => $resolved ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Actualiza columnas de un cambio.
     *
     * @param   int   $id     Cambio.
     * @param   array $values Columnas.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function update( int $id, array $values ): void {
        global $wpdb;

        $values['updated_at'] = current_time( 'mysql' );
        $wpdb->update( self::table(), $values, [ 'id' => $id ] );
    }

    /**
     * Consulta de cambios (sin altas).
     *
     * @param   string $where  Condición con marcadores.
     * @param   array  $args   Valores.
     * @param   string $order  Orden.
     * @return 	object[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function query( string $where, array $args, string $order = 'id DESC' ): array {
        global $wpdb;

        $table = self::table();
        $sql = "SELECT * FROM {$table} WHERE type IN (%s, %s, %s, %s) AND {$where} ORDER BY {$order}";
        $rows = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( [ self::TYPE_SERVICES, self::TYPE_MEMBER_TYPE, self::TYPE_LEAVE, self::TYPE_FREEZE ], $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where y $order son literales de esta clase.

        return array_map( [ self::class, 'decode' ], $rows );
    }

    /**
     * Fila con ->fields.
     *
     * @param   object $row Fila.
     * @return 	object
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function decode( object $row ): object {
        $fields = json_decode( (string) $row->data, true );
        $row->fields = is_array( $fields ) ? $fields : [];

        return $row;
    }

    /**
     * Tabla.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function table(): string {
        return MembersSchema::table( MembersSchema::REQUESTS );
    }
}
