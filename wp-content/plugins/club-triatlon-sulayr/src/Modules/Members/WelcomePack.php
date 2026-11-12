<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Entregas del welcome pack (D55, 9.3). Artículos de Socios → Ajustes; varios
 * artículos registrados a la vez forman un pack (mismo pack_id). Sin stock y
 * sin relación con la tarifa.
 */
class WelcomePack {

    /**
     * Registra una entrega.
     *
     * @param   int      $user_id Socio.
     * @param   string[] $items   Artículos.
     * @param   string   $date    Fecha Y-m-d.
     * @return 	string|\WP_Error  pack_id.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function deliver( int $user_id, array $items, string $date ) {
        global $wpdb;

        $allowed = MemberSettings::labels( 'welcome_items' );
        $items = array_values( array_intersect( $allowed, array_map( 'strval', $items ) ) );
        if ( empty( $items ) ) {
            return new \WP_Error( 'sulayr_welcome_items', __( 'Marca al menos un artículo.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === MemberDates::acf_to_iso( $date ) ) {
            return new \WP_Error( 'sulayr_welcome_date', __( 'Fecha no válida.', SULAYR_TEXTDOMAIN ) );
        }

        $pack_id = wp_generate_password( 12, false, false );
        foreach ( $items as $item ) {
            $wpdb->insert( self::table(), [
                'user_id' => $user_id,
                'item' => substr( $item, 0, 100 ),
                'delivered_on' => $date,
                'pack_id' => $pack_id,
                'delivered_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ] );
        }
        MemberLog::record( $user_id, MemberLog::AREA_DATA, __( 'Welcome pack', SULAYR_TEXTDOMAIN ), '', sprintf( '%s · %s', implode( ', ', $items ), MemberDates::show( $date ) ) );

        return $pack_id;
    }

    /**
     * Borra una entrega registrada por error.
     *
     * @param   int    $user_id Socio.
     * @param   string $pack_id Pack.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function delete( int $user_id, string $pack_id ): bool {
        global $wpdb;

        $rows = self::packs( $user_id )[ $pack_id ] ?? [];
        if ( empty( $rows ) ) {
            return false;
        }

        $wpdb->delete( self::table(), [ 'user_id' => $user_id, 'pack_id' => $pack_id ] );
        MemberLog::record( $user_id, MemberLog::AREA_DATA, __( 'Welcome pack', SULAYR_TEXTDOMAIN ), sprintf( '%s · %s', implode( ', ', wp_list_pluck( $rows, 'item' ) ), MemberDates::show( (string) $rows[0]->delivered_on ) ), __( 'Entrega borrada', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * Entregas de un socio agrupadas por pack, de la más reciente a la más antigua.
     *
     * @param   int $user_id Socio.
     * @return 	array<string, object[]>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function packs( int $user_id ): array {
        global $wpdb;

        $table = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY delivered_on DESC, id ASC", $user_id ) );
        $packs = [];
        foreach ( $rows as $row ) {
            $packs[ (string) $row->pack_id ][] = $row;
        }

        return $packs;
    }

    /**
     * Últimas entregas de todos los socios.
     *
     * @param   int $limit Máximo de filas.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recent( int $limit = 200 ): array {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY delivered_on DESC, id DESC LIMIT %d", $limit ) );
    }

    /**
     * Socios activos hoy sin ninguna entrega.
     *
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function members_without(): array {
        global $wpdb;

        $table = self::table();
        $with = array_map( 'intval', $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table}" ) );
        $today = current_time( 'Y-m-d' );
        $out = [];
        foreach ( get_users( [ 'role' => 'athlete', 'fields' => 'ID', 'number' => -1 ] ) as $user_id ) {
            $period = StatusPeriods::on( (int) $user_id, $today );
            if ( null !== $period && CustomFields::STATUS_ACTIVE === $period->status && false === in_array( (int) $user_id, $with, true ) ) {
                $out[] = (int) $user_id;
            }
        }

        return $out;
    }

    /**
     * Tabla.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function table(): string {
        return MembersSchema::table( MembersSchema::WELCOME_DELIVERIES );
    }
}
