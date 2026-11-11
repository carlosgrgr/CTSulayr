<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Servicios de cada socio con fechas: el histórico de tarifas sale de aquí.
 *
 * Una fila es un servicio (y su opción) entre start_date y end_date, ambos
 * incluidos. end_date nulo = vigente. Las filas no se borran: un cambio
 * cierra la fila anterior y abre otra.
 */
class MemberServices {

    public const ORIGIN_JOIN = 'alta';
    public const ORIGIN_REQUEST = 'solicitud';
    public const ORIGIN_MANAGER = 'gestor';
    public const ORIGIN_MIGRATION = 'migracion';

    /**
     * Abre un servicio para un socio.
     *
     * @param   int    $user_id    Socio.
     * @param   int    $service_id Servicio.
     * @param   string $option_key Opción elegida ('' si no tiene).
     * @param   string $start_date Fecha de inicio Y-m-d.
     * @param   string $origin     Una de las constantes ORIGIN_*.
     * @param   string $end_date   Fecha de fin Y-m-d, o '' si sigue vigente.
     * @return 	int    ID de la fila.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function open( int $user_id, int $service_id, string $option_key, string $start_date, string $origin, string $end_date = '' ): int {
        global $wpdb;

        $wpdb->insert(
            MembersSchema::table( MembersSchema::SERVICES ),
            [
                'user_id' => $user_id,
                'service_id' => $service_id,
                'option_key' => $option_key,
                'start_date' => $start_date,
                'end_date' => '' !== $end_date ? $end_date : null,
                'origin' => $origin,
                'created_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        $id = (int) $wpdb->insert_id;

        $period = sprintf( __( 'desde %s', SULAYR_TEXTDOMAIN ), $start_date );
        if ( '' !== $end_date ) {
            $period .= ' ' . sprintf( __( 'hasta %s', SULAYR_TEXTDOMAIN ), $end_date );
        }
        MemberLog::record( $user_id, MemberLog::AREA_SERVICES, self::label( $service_id, $option_key ), '', $period );

        return $id;
    }

    /**
     * Cierra una fila vigente en $end_date (incluido).
     *
     * @param   int    $row_id   Fila.
     * @param   string $end_date Último día Y-m-d.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function close( int $row_id, string $end_date ): void {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $row_id ) );
        if ( null === $row || null !== $row->end_date ) {
            return;
        }

        $wpdb->update( $table, [ 'end_date' => $end_date ], [ 'id' => $row_id ], [ '%s' ], [ '%d' ] );

        MemberLog::record(
            (int) $row->user_id,
            MemberLog::AREA_SERVICES,
            self::label( (int) $row->service_id, (string) $row->option_key ),
            sprintf( __( 'desde %s', SULAYR_TEXTDOMAIN ), $row->start_date ),
            sprintf( __( 'hasta %s', SULAYR_TEXTDOMAIN ), $end_date )
        );
    }

    /**
     * Todas las filas de un socio, de la más antigua a la más reciente.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY start_date ASC, id ASC", $user_id ) );
    }

    /**
     * Filas sin fecha de fin.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function open_rows( int $user_id ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND end_date IS NULL ORDER BY id ASC", $user_id ) );
    }

    /**
     * Filas vigentes en una fecha.
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Fecha Y-m-d.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function active_on( int $user_id, string $date ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND start_date <= %s AND ( end_date IS NULL OR end_date >= %s ) ORDER BY id ASC",
            $user_id,
            $date,
            $date
        ) );
    }

    /**
     * True si el socio tiene alguna fila.
     *
     * @param   int $user_id Socio.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_any( int $user_id ): bool {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );

        return 0 < (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE user_id = %d", $user_id ) );
    }

    /**
     * Nombre de un servicio con su opción, p. ej. «Entrenamiento genérico (Sprint)».
     *
     * @param   int    $service_id Servicio.
     * @param   string $option_key Opción.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function label( int $service_id, string $option_key = '' ): string {
        $service = ServiceCatalog::get( $service_id );
        $name = null !== $service ? $service->title : sprintf( __( 'Servicio %d', SULAYR_TEXTDOMAIN ), $service_id );

        if ( '' !== $option_key ) {
            $name .= ' (' . ( null !== $service ? $service->option_label( $option_key ) : $option_key ) . ')';
        }

        return $name;
    }
}
