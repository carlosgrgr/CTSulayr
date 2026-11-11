<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Subvenciones por socio (D35): porcentaje o importe mensual sobre unos
 * servicios (ninguno = todos), entre dos fechas y con motivo.
 *
 * - Porcentaje: sobre lo que se cobra de esos servicios en el mes, ya
 *   descontada la bonificación.
 * - Importe: se descuenta entero cada mes, sin pasar de lo que se cobra de
 *   esos servicios.
 */
class Subsidies {

    public const MODE_PERCENT = 'porcentaje';
    public const MODE_AMOUNT = 'importe';

    /**
     * Crea una subvención.
     *
     * @param   int    $user_id     Socio.
     * @param   string $mode        MODE_*.
     * @param   float  $value       Porcentaje o importe.
     * @param   int[]  $service_ids Servicios (vacío = todos).
     * @param   string $start       Desde (Y-m-d).
     * @param   string $end         Hasta (Y-m-d) o ''.
     * @param   string $reason      Motivo.
     * @return 	int|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function create( int $user_id, string $mode, float $value, array $service_ids, string $start, string $end, string $reason ) {
        global $wpdb;

        $reason = trim( $reason );
        if ( false === in_array( $mode, [ self::MODE_PERCENT, self::MODE_AMOUNT ], true ) || $value <= 0 || ( self::MODE_PERCENT === $mode && $value > 100 ) ) {
            return new \WP_Error( 'sulayr_subsidy_value', __( 'Indica un porcentaje entre 0 y 100 o un importe mayor que 0.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === $start || ( '' !== $end && $end < $start ) ) {
            return new \WP_Error( 'sulayr_subsidy_dates', __( 'Revisa las fechas de la subvención.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === $reason ) {
            return new \WP_Error( 'sulayr_subsidy_reason', __( 'Escribe el motivo de la subvención.', SULAYR_TEXTDOMAIN ) );
        }

        $wpdb->insert( self::table(), [
            'user_id' => $user_id,
            'mode' => $mode,
            'value' => round( $value, 2 ),
            'service_ids' => implode( ',', array_map( 'intval', $service_ids ) ),
            'start_date' => $start,
            'end_date' => '' !== $end ? $end : null,
            'reason' => substr( $reason, 0, 255 ),
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );
        $id = (int) $wpdb->insert_id;

        MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, __( 'Subvención', SULAYR_TEXTDOMAIN ), '', self::describe( self::get( $id ) ), $reason );
        do_action( 'sulayr_member_history_changed', $user_id );

        return $id;
    }

    /**
     * Termina una subvención en una fecha.
     *
     * @param   int    $id  Subvención.
     * @param   string $end Último día (Y-m-d).
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function end( int $id, string $end ) {
        global $wpdb;

        $row = self::get( $id );
        if ( null === $row ) {
            return new \WP_Error( 'sulayr_subsidy', __( 'Subvención no encontrada.', SULAYR_TEXTDOMAIN ) );
        }
        if ( $end < (string) $row->start_date ) {
            return new \WP_Error( 'sulayr_subsidy_dates', __( 'El fin no puede ser anterior al inicio.', SULAYR_TEXTDOMAIN ) );
        }

        $wpdb->update( self::table(), [ 'end_date' => $end ], [ 'id' => $id ] );
        MemberLog::record( (int) $row->user_id, MemberLog::AREA_RECEIPTS, __( 'Subvención', SULAYR_TEXTDOMAIN ), self::describe( $row ), sprintf( __( 'hasta %s', SULAYR_TEXTDOMAIN ), $end ) );
        do_action( 'sulayr_member_history_changed', (int) $row->user_id );

        return true;
    }

    /**
     * Subvención por ID.
     *
     * @param   int $id Subvención.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Subvenciones de un socio.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY start_date ASC, id ASC", $user_id ) );
    }

    /**
     * Subvenciones vigentes algún día entre dos fechas.
     *
     * @param   int    $user_id Socio.
     * @param   string $first   Y-m-d.
     * @param   string $last    Y-m-d.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function in_range( int $user_id, string $first, string $last ): array {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND start_date <= %s AND ( end_date IS NULL OR end_date >= %s ) ORDER BY id ASC",
            $user_id,
            $last,
            $first
        ) );
    }

    /**
     * Servicios afectados (vacío = todos).
     *
     * @param   object $row Subvención.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function service_ids( object $row ): array {
        return array_values( array_filter( array_map( 'intval', explode( ',', (string) $row->service_ids ) ) ) );
    }

    /**
     * Texto corto.
     *
     * @param   object|null $row Subvención.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function describe( ?object $row ): string {
        if ( null === $row ) {
            return '';
        }

        $value = self::MODE_PERCENT === $row->mode ? number_format_i18n( (float) $row->value, 2 ) . ' %' : number_format_i18n( (float) $row->value, 2 ) . ' €/mes';
        $ids = self::service_ids( $row );
        $services = empty( $ids ) ? __( 'todos los servicios', SULAYR_TEXTDOMAIN ) : implode( ', ', array_map( [ MemberServices::class, 'label' ], $ids ) );

        return sprintf(
            /* translators: 1: valor, 2: servicios, 3: desde, 4: hasta. */
            __( '%1$s sobre %2$s, del %3$s al %4$s', SULAYR_TEXTDOMAIN ),
            $value,
            $services,
            MemberDates::show( (string) $row->start_date ),
            null === $row->end_date ? __( 'sin fin', SULAYR_TEXTDOMAIN ) : MemberDates::show( (string) $row->end_date )
        );
    }

    /**
     * Tabla.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function table(): string {
        return MembersSchema::table( MembersSchema::SUBSIDIES );
    }
}
