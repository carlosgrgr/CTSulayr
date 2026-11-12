<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Periodos de estado de cada socio (activo, congelado, baja), con fechas.
 *
 * start_date nulo = fecha desconocida (bajas antiguas migradas).
 * end_date nulo = periodo en curso. Las fechas son días incluidos.
 */
class StatusPeriods {

    /**
     * Abre un periodo.
     *
     * @param   int         $user_id     Socio.
     * @param   string      $status      Estado (CustomFields::STATUS_*).
     * @param   string|null $start_date  Primer día Y-m-d, o null si se desconoce.
     * @param   string      $reason      Motivo.
     * @param   string      $end_date    Último día Y-m-d, o '' si sigue abierto.
     * @param   string      $planned_end Fin previsto (congelaciones).
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function open( int $user_id, string $status, ?string $start_date, string $reason = '', string $end_date = '', string $planned_end = '' ): int {
        global $wpdb;

        $wpdb->insert(
            MembersSchema::table( MembersSchema::STATUS_PERIODS ),
            [
                'user_id' => $user_id,
                'status' => $status,
                'start_date' => $start_date,
                'end_date' => '' !== $end_date ? $end_date : null,
                'planned_end_date' => '' !== $planned_end ? $planned_end : null,
                'reason' => substr( $reason, 0, 255 ),
                'created_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        $id = (int) $wpdb->insert_id;

        $period = null !== $start_date ? sprintf( __( 'desde %s', SULAYR_TEXTDOMAIN ), $start_date ) : __( 'desde fecha desconocida', SULAYR_TEXTDOMAIN );
        if ( '' !== $end_date ) {
            $period .= ' ' . sprintf( __( 'hasta %s', SULAYR_TEXTDOMAIN ), $end_date );
        }
        MemberLog::record( $user_id, MemberLog::AREA_STATUS, self::label( $status ), '', $period, $reason );

        return $id;
    }

    /**
     * Cierra un periodo abierto.
     *
     * @param   int    $period_id Periodo.
     * @param   string $end_date  Último día Y-m-d.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function close( int $period_id, string $end_date ): void {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $period_id ) );
        if ( null === $row || null !== $row->end_date ) {
            return;
        }

        $wpdb->update( $table, [ 'end_date' => $end_date ], [ 'id' => $period_id ], [ '%s' ], [ '%d' ] );

        MemberLog::record( (int) $row->user_id, MemberLog::AREA_STATUS, self::label( (string) $row->status ), '', sprintf( __( 'hasta %s', SULAYR_TEXTDOMAIN ), $end_date ) );
    }

    /**
     * Cambia el fin previsto de un periodo (vuelta de una congelación).
     *
     * @param   int    $period_id   Periodo.
     * @param   string $planned_end Último día previsto Y-m-d.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function set_planned_end( int $period_id, string $planned_end ): void {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $period_id ) );
        if ( null === $row || (string) $row->planned_end_date === $planned_end ) {
            return;
        }

        $wpdb->update( $table, [ 'planned_end_date' => $planned_end ], [ 'id' => $period_id ], [ '%s' ], [ '%d' ] );
        MemberLog::record( (int) $row->user_id, MemberLog::AREA_STATUS, __( 'Fin previsto', SULAYR_TEXTDOMAIN ) . ' (' . self::label( (string) $row->status ) . ')', (string) $row->planned_end_date, $planned_end );
    }

    /**
     * Periodos abiertos de un estado cuyo fin previsto ya pasó.
     *
     * @param   string $status Estado.
     * @param   string $before Y-m-d: fin previsto anterior a esta fecha.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function overdue( string $status, string $before ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE status = %s AND end_date IS NULL AND planned_end_date IS NOT NULL AND planned_end_date < %s ORDER BY id ASC", $status, $before ) );
    }

    /**
     * Periodo abierto más reciente, o null.
     *
     * @param   int $user_id Socio.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function current( int $user_id ): ?object {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND end_date IS NULL ORDER BY id DESC LIMIT 1", $user_id ) );
    }

    /**
     * Periodo que cubre una fecha, o null. Un inicio desconocido cuenta como «desde siempre».
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Y-m-d.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function on( int $user_id, string $date ): ?object {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND ( start_date IS NULL OR start_date <= %s ) AND ( end_date IS NULL OR end_date >= %s ) ORDER BY id DESC LIMIT 1",
            $user_id,
            $date,
            $date
        ) );
    }

    /**
     * True si el socio tiene un periodo que empieza después de $date.
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Y-m-d.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_future( int $user_id, string $date ): bool {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND start_date > %s", $user_id, $date ) );
    }

    /**
     * Todos los periodos de un socio.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY COALESCE(start_date, '0000-00-00') ASC, id ASC", $user_id ) );
    }

    /**
     * Nombre legible de un estado.
     *
     * @param   string $status Estado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function label( string $status ): string {
        $labels = [
            CustomFields::STATUS_PENDING => __( 'Pendiente', SULAYR_TEXTDOMAIN ),
            CustomFields::STATUS_ACTIVE => __( 'Activo', SULAYR_TEXTDOMAIN ),
            CustomFields::STATUS_FROZEN => __( 'Congelado', SULAYR_TEXTDOMAIN ),
            CustomFields::STATUS_LEFT => __( 'Baja', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $status ] ?? $status;
    }
}
