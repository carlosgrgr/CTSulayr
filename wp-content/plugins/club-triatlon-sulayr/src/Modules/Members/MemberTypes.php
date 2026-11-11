<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Historial del tipo de socio (normal, junta...) con fechas (parte D, 19.6).
 *
 * La meta sulayr_member_type sigue siendo el tipo actual que leen las
 * pantallas; esta tabla dice qué tipo tenía en cada fecha, para que los
 * recibos de meses anteriores usen el tipo de entonces. start_date nulo =
 * desde siempre (periodo inicial creado al instalar).
 *
 * Los recibos usan el tipo del día 1 del mes para todo el mes (decidido el
 * 11 de noviembre de 2026).
 */
class MemberTypes {

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        // Cualquier escritura de la meta que no pase por set() (p. ej. la ficha de usuario) entra hoy en el historial.
        add_action( 'added_user_meta', [ $this, 'on_meta' ], 20, 4 );
        add_action( 'updated_user_meta', [ $this, 'on_meta' ], 20, 4 );
    }

    /**
     * Mantiene el historial al día si alguien cambia la meta directamente.
     *
     * @param   int    $meta_id  Meta.
     * @param   int    $user_id  Usuario.
     * @param   string $meta_key Clave.
     * @param   mixed  $value    Valor.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_meta( $meta_id, $user_id, $meta_key, $value ): void {
        if ( CustomFields::MEMBER_TYPE !== $meta_key || '' === (string) $value || false === self::is_enabled() ) {
            return;
        }

        $current = self::current( (int) $user_id );
        if ( null === $current || (string) $current->member_type !== (string) $value ) {
            self::change( (int) $user_id, (string) $value, current_time( 'Y-m-d' ) );
        }
    }

    /**
     * Cambia el tipo desde una fecha: historial y meta.
     *
     * @param   int    $user_id Socio.
     * @param   string $type    Clave del tipo.
     * @param   string $date    Primer día con el tipo nuevo (Y-m-d).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function set( int $user_id, string $type, string $date ): void {
        self::change( $user_id, $type, $date );
        if ( (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true ) !== $type ) {
            update_user_meta( $user_id, CustomFields::MEMBER_TYPE, $type );
        }
    }

    /**
     * Escribe el cambio en el historial (sin tocar la meta).
     *
     * @param   int    $user_id Socio.
     * @param   string $type    Tipo.
     * @param   string $date    Desde (Y-m-d).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function change( int $user_id, string $type, string $date ): void {
        global $wpdb;

        if ( '' === $type ) {
            return;
        }

        $table = self::table();
        $current = self::current( $user_id );
        if ( null !== $current ) {
            if ( (string) $current->member_type === $type ) {
                return;
            }
            if ( null !== $current->start_date && (string) $current->start_date >= $date ) {
                // Empezaba ese mismo día o después: se corrige el tipo de esa fila.
                $wpdb->update( $table, [ 'member_type' => $type ], [ 'id' => (int) $current->id ] );
                do_action( 'sulayr_member_history_changed', $user_id );
                return;
            }
            $wpdb->update( $table, [ 'end_date' => MemberDates::add_days( $date, -1 ) ], [ 'id' => (int) $current->id ] );
        }

        $wpdb->insert( $table, [
            'user_id' => $user_id,
            'member_type' => $type,
            'start_date' => null !== $current ? $date : null,
            'end_date' => null,
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );

        do_action( 'sulayr_member_history_changed', $user_id );
    }

    /**
     * Tipo para los recibos de un mes: el del día 1; si el socio aún no
     * existía ese día, el primero que tuvo dentro del mes; si no hay
     * historial, la meta.
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_month( int $user_id, string $period ): string {
        global $wpdb;

        $first = $period . '-01';
        $last = ( new \DateTimeImmutable( $first ) )->format( 'Y-m-t' );
        $table = self::table();

        $type = $wpdb->get_var( $wpdb->prepare(
            "SELECT member_type FROM {$table} WHERE user_id = %d AND ( start_date IS NULL OR start_date <= %s ) AND ( end_date IS NULL OR end_date >= %s ) ORDER BY id DESC LIMIT 1",
            $user_id,
            $first,
            $first
        ) );
        if ( null === $type ) {
            $type = $wpdb->get_var( $wpdb->prepare(
                "SELECT member_type FROM {$table} WHERE user_id = %d AND start_date > %s AND start_date <= %s ORDER BY start_date ASC, id ASC LIMIT 1",
                $user_id,
                $first,
                $last
            ) );
        }
        if ( null === $type ) {
            $type = (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true );
        }

        return (string) $type;
    }

    /**
     * Periodo abierto, o null.
     *
     * @param   int $user_id Socio.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function current( int $user_id ): ?object {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND end_date IS NULL ORDER BY id DESC LIMIT 1", $user_id ) );
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

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY COALESCE(start_date, '0000-00-00') ASC, id ASC", $user_id ) );
    }

    /**
     * Periodo inicial («desde siempre») con el tipo actual de cada socio que
     * aún no tenga historial. Se llama al instalar la versión 4 del esquema.
     *
     * @return 	int Socios con periodo nuevo.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function seed(): int {
        global $wpdb;

        $table = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> '' AND user_id NOT IN ( SELECT user_id FROM {$table} )",
            CustomFields::MEMBER_TYPE
        ) );

        foreach ( $rows as $row ) {
            $wpdb->insert( $table, [
                'user_id' => (int) $row->user_id,
                'member_type' => (string) $row->meta_value,
                'start_date' => null,
                'end_date' => null,
                'created_by' => 0,
                'created_at' => current_time( 'mysql' ),
            ] );
        }

        return count( $rows );
    }

    /**
     * True si el blog actual tiene la tabla (versión 4 o más).
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_enabled(): bool {
        return 4 <= (int) get_option( MembersSchema::VERSION_OPTION );
    }

    /**
     * Tabla.
     *
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function table(): string {
        return MembersSchema::table( MembersSchema::MEMBER_TYPES );
    }
}
