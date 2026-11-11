<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Coincidencias de email y DNI en toda la red antes de aprobar (D13, §6.6).
 *
 * Usuarios y metas son de la red, así que se busca en todas las webs. El
 * tipo de cada coincidencia decide qué puede hacer el gestor:
 * - socio en vigor (activo, congelado o pendiente): no se puede aprobar;
 * - antiguo socio (de baja): se vincula como reingreso;
 * - cualquier otro usuario de la red: se vincula y se le añade athlete.
 */
class DuplicateFinder {

    public const KIND_MEMBER = 'socio';
    public const KIND_FORMER = 'antiguo';
    public const KIND_OTHER = 'otro';

    /**
     * Usuarios de la red con ese email o DNI.
     *
     * @param   string $email Email normalizado.
     * @param   string $dni   DNI normalizado.
     * @return 	array<int, array{user: \WP_User, by: string[], kind: string, status: string}> Por ID de usuario.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function find( string $email, string $dni ): array {
        global $wpdb;

        $found = [];

        if ( '' !== $email ) {
            $user = get_user_by( 'email', $email );
            if ( false !== $user ) {
                $found[ (int) $user->ID ] = [ 'email' ];
            }
        }

        if ( '' !== $dni ) {
            $ids = $wpdb->get_col( $wpdb->prepare(
                "SELECT DISTINCT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND UPPER(REPLACE(REPLACE(REPLACE(meta_value, ' ', ''), '-', ''), '.', '')) = %s",
                CustomFields::USER_PROFILE_DNI,
                $dni
            ) );
            $ids = array_merge( $ids, self::swimmer_ids_by_dni( $dni ) );
            foreach ( $ids as $id ) {
                $found[ (int) $id ][] = 'dni';
            }
        }

        $matches = [];
        foreach ( $found as $user_id => $by ) {
            $user = get_userdata( $user_id );
            if ( false === $user ) {
                continue;
            }

            $classified = self::classify( $user );
            $matches[ $user_id ] = [
                'user' => $user,
                'by' => array_values( array_unique( $by ) ),
                'kind' => $classified['kind'],
                'status' => $classified['status'],
            ];
        }

        return $matches;
    }

    /**
     * Usuarios con ese DNI en la ficha local de reservas (solo lectura).
     * Reservas guarda el DNI de sus nadadores en su propia tabla.
     *
     * @param   string $dni DNI normalizado.
     * @return 	int[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function swimmer_ids_by_dni( string $dni ): array {
        global $wpdb;

        $blog_id = (int) get_site_option( 'sulayr_reservas_bookings_blog_id' );
        if ( $blog_id <= 0 || false === is_multisite() ) {
            return [];
        }

        $table = $wpdb->get_blog_prefix( $blog_id ) . 'sulayr_swim_swimmers';
        if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
            return [];
        }

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table} WHERE UPPER(REPLACE(REPLACE(REPLACE(dni, ' ', ''), '-', ''), '.', '')) = %s",
            $dni
        ) ) );
    }

    /**
     * Tipo de coincidencia de un usuario, mirado en la web del club.
     *
     * @param   \WP_User $user Usuario.
     * @return 	array{kind: string, status: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function classify( \WP_User $user ): array {
        $is_athlete = is_user_member_of_blog( $user->ID, get_current_blog_id() ) && in_array( 'athlete', (array) $user->roles, true );
        if ( false === $is_athlete ) {
            return [ 'kind' => self::KIND_OTHER, 'status' => '' ];
        }

        $status = (string) get_user_meta( $user->ID, CustomFields::USER_PROFILE_ATHLETE_STATUS, true );
        if ( '' === $status ) {
            $status = CustomFields::STATUS_ACTIVE;
        }

        if ( CustomFields::STATUS_LEFT === $status ) {
            return [ 'kind' => self::KIND_FORMER, 'status' => $status ];
        }

        // Deportista sin histórico ni estado de baja: sigue siendo socio en vigor.
        return [ 'kind' => self::KIND_MEMBER, 'status' => $status ];
    }

    /**
     * True si alguna coincidencia es un socio en vigor.
     *
     * @param   array $matches Resultado de find().
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_member( array $matches ): bool {
        foreach ( $matches as $match ) {
            if ( self::KIND_MEMBER === $match['kind'] ) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si vincular a este usuario es un reingreso (antiguo socio con historial).
     *
     * @param   \WP_User $user Usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_return( \WP_User $user ): bool {
        return self::KIND_FORMER === self::classify( $user )['kind'] || ( in_array( 'athlete', (array) $user->roles, true ) && ! empty( StatusPeriods::for_user( $user->ID ) ) );
    }

    /**
     * Nombre legible de un tipo de coincidencia.
     *
     * @param   string $kind KIND_*.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function kind_label( string $kind ): string {
        $labels = [
            self::KIND_MEMBER => __( 'Ya es socio', SULAYR_TEXTDOMAIN ),
            self::KIND_FORMER => __( 'Antiguo socio (reingreso)', SULAYR_TEXTDOMAIN ),
            self::KIND_OTHER => __( 'Usuario de la red (otra web o tienda)', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $kind ] ?? $kind;
    }
}
