<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Solicitudes de alta guardadas en sulayr_member_requests (spec §5).
 *
 * Los datos del formulario van en la columna data como JSON. Al aprobar se
 * añade la clave «approval» con lo que decidió el gestor (modo, usuario,
 * tipo, matrícula). La parte D lee de ahí la matrícula del primer recibo.
 */
class JoinRequests {

    public const TYPE_JOIN = 'alta';

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_APPROVED = 'aprobada';
    public const STATUS_REJECTED = 'rechazada';
    /** Pendiente sin tocar que superó el plazo (D16); ya sin datos personales. */
    public const STATUS_EXPIRED = 'caducada';

    public const ORIGIN_PUBLIC = 'publico';
    public const ORIGIN_MANAGER = 'gestor';

    /**
     * Guarda una solicitud nueva.
     *
     * @param   array  $data Datos validados.
     * @param   string $ip   IP de quien la envía ('' desde wp-admin).
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function create( array $data, string $ip = '' ): int {
        global $wpdb;

        $now = current_time( 'mysql' );
        $wpdb->insert(
            self::table(),
            [
                'type' => self::TYPE_JOIN,
                'user_id' => 0,
                'status' => self::STATUS_PENDING,
                'data' => (string) wp_json_encode( $data ),
                'effective_date' => $data['start_date'] ?? null,
                'ip' => substr( $ip, 0, 45 ),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [ '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Solicitud de alta con sus datos decodificados en ->fields, o null.
     *
     * @param   int $id Solicitud.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $table = self::table();
        $row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d AND type = %s", $id, self::TYPE_JOIN ) );

        return null !== $row ? self::decode( $row ) : null;
    }

    /**
     * Cambia los datos de una solicitud pendiente.
     *
     * @param   int   $id   Solicitud.
     * @param   array $data Datos validados.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function update_data( int $id, array $data ): void {
        global $wpdb;

        $wpdb->update(
            self::table(),
            [
                'data' => (string) wp_json_encode( $data ),
                'effective_date' => $data['start_date'] ?? null,
                'updated_at' => current_time( 'mysql' ),
            ],
            [ 'id' => $id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Cierra una solicitud (aprobada o rechazada).
     *
     * @param   int    $id             Solicitud.
     * @param   string $status         STATUS_APPROVED o STATUS_REJECTED.
     * @param   array  $data           Datos (con «approval» si se aprueba).
     * @param   int    $user_id        Socio creado o vinculado (0 si se rechaza).
     * @param   string $reason         Motivo del rechazo.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function resolve( int $id, string $status, array $data, int $user_id = 0, string $reason = '' ): void {
        global $wpdb;

        $now = current_time( 'mysql' );
        $wpdb->update(
            self::table(),
            [
                'status' => $status,
                'user_id' => $user_id,
                'data' => (string) wp_json_encode( $data ),
                'effective_date' => $data['start_date'] ?? null,
                'resolved_by' => get_current_user_id(),
                'resolved_at' => $now,
                'reason' => substr( $reason, 0, 255 ),
                'updated_at' => $now,
            ],
            [ 'id' => $id ],
            [ '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s' ],
            [ '%d' ]
        );
    }

    /**
     * Solicitudes de alta en un estado, de la más reciente a la más antigua.
     *
     * @param   string $status Estado.
     * @param   int    $limit  Máximo.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function list( string $status, int $limit = 200 ): array {
        global $wpdb;

        $table = self::table();
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE type = %s AND status = %s ORDER BY id DESC LIMIT %d", self::TYPE_JOIN, $status, $limit ) );

        return array_map( [ self::class, 'decode' ], $rows );
    }

    /**
     * Número de solicitudes de alta pendientes.
     *
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function count_pending(): int {
        global $wpdb;

        $table = self::table();

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE type = %s AND status = %s", self::TYPE_JOIN, self::STATUS_PENDING ) );
    }

    /**
     * Solicitudes enviadas desde una IP en la última hora.
     *
     * @param   string $ip IP.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recent_from_ip( string $ip ): int {
        global $wpdb;

        if ( '' === $ip ) {
            return 0;
        }

        $table = self::table();
        $since = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - HOUR_IN_SECONDS );

        return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE ip = %s AND created_at >= %s", $ip, $since ) );
    }

    /**
     * Otras solicitudes pendientes con el mismo email o DNI.
     *
     * @param   string $email      Email normalizado.
     * @param   string $dni        DNI normalizado.
     * @param   int    $exclude_id Solicitud que se está mirando.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pending_matches( string $email, string $dni, int $exclude_id ): array {
        $matches = [];
        foreach ( self::list( self::STATUS_PENDING ) as $row ) {
            if ( (int) $row->id === $exclude_id ) {
                continue;
            }
            $f = $row->fields;
            if ( ( '' !== $email && Identity::normalize_email( (string) ( $f['email'] ?? '' ) ) === $email ) || ( '' !== $dni && Identity::normalize_dni( (string) ( $f['dni'] ?? '' ) ) === $dni ) ) {
                $matches[] = $row;
            }
        }

        return $matches;
    }

    /**
     * Borra los datos personales de las solicitudes caducadas (D16): las
     * rechazadas tras su plazo y las pendientes sin tocar tras el suyo. Queda
     * la fila con tipo, estado, fechas, motivo y servicios elegidos.
     *
     * @param   int $rejected_days Días desde el rechazo.
     * @param   int $pending_days  Días desde la última modificación.
     * @return 	int Solicitudes anonimizadas.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function purge_expired( int $rejected_days, int $pending_days ): int {
        global $wpdb;

        $table = self::table();
        $now = current_time( 'timestamp' );
        $rejected_before = gmdate( 'Y-m-d H:i:s', $now - max( 1, $rejected_days ) * DAY_IN_SECONDS );
        $pending_before = gmdate( 'Y-m-d H:i:s', $now - max( 1, $pending_days ) * DAY_IN_SECONDS );

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE type = %s AND (
                ( status = %s AND resolved_at < %s )
                OR ( status = %s AND COALESCE(updated_at, created_at) < %s )
            ) AND data NOT LIKE %s",
            self::TYPE_JOIN,
            self::STATUS_REJECTED,
            $rejected_before,
            self::STATUS_PENDING,
            $pending_before,
            '%"anonimizada":true%'
        ) );

        foreach ( $rows as $row ) {
            $fields = json_decode( (string) $row->data, true );
            $fields = is_array( $fields ) ? $fields : [];
            $kept = [
                'anonimizada' => true,
                'origin' => $fields['origin'] ?? '',
                'start_date' => $fields['start_date'] ?? '',
                'minor' => ! empty( $fields['minor'] ),
                'services' => $fields['services'] ?? [],
            ];

            $wpdb->update(
                $table,
                [
                    'data' => (string) wp_json_encode( $kept ),
                    'ip' => '',
                    'reason' => '',
                    'status' => self::STATUS_PENDING === $row->status ? self::STATUS_EXPIRED : (string) $row->status,
                    'updated_at' => current_time( 'mysql' ),
                ],
                [ 'id' => (int) $row->id ],
                [ '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
        }

        return count( $rows );
    }

    /**
     * Nombre completo de una solicitud.
     *
     * @param   object $request Solicitud decodificada.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function full_name( object $request ): string {
        if ( ! empty( $request->fields['anonimizada'] ) ) {
            return __( '(datos borrados)', SULAYR_TEXTDOMAIN );
        }

        return trim( ( $request->fields['first_name'] ?? '' ) . ' ' . ( $request->fields['last_name'] ?? '' ) );
    }

    /**
     * Nombre legible de un estado.
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
            self::STATUS_EXPIRED => __( 'Caducada', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $status ] ?? $status;
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
