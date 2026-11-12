<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registro de cambios del socio (D58).
 *
 * Solo inserta: no hay método para editar ni borrar entradas.
 * Escribe en el blog donde existen las tablas de socios; en cualquier otro
 * blog (p. ej. reservas tras switch_to_blog) no registra nada.
 */
class MemberLog {

    public const AREA_DATA = 'datos';
    public const AREA_SERVICES = 'servicios';
    public const AREA_STATUS = 'estado';
    public const AREA_ROLES = 'roles';
    public const AREA_RECEIPTS = 'recibos';
    public const AREA_CONSENTS = 'consentimientos';

    /** Metas que se registran y su área. */
    private const TRACKED_META = [
        'first_name' => self::AREA_DATA,
        'last_name' => self::AREA_DATA,
        CustomFields::USER_PROFILE_PHONE => self::AREA_DATA,
        CustomFields::USER_PROFILE_BIRTH => self::AREA_DATA,
        CustomFields::USER_PROFILE_JOIN_DATE => self::AREA_DATA,
        CustomFields::USER_PROFILE_DNI => self::AREA_DATA,
        CustomFields::USER_PROFILE_PHOTO => self::AREA_DATA,
        CustomFields::USER_PROFILE_MEMBERSHIP_PLAN => self::AREA_SERVICES,
        CustomFields::USER_PROFILE_TRAINING_TYPE => self::AREA_SERVICES,
        CustomFields::USER_PROFILE_SWIMMING => self::AREA_SERVICES,
        CustomFields::USER_PROFILE_IS_DIRECTIVE => self::AREA_DATA,
        CustomFields::USER_PROFILE_ATHLETE_STATUS => self::AREA_STATUS,
        CustomFields::MEMBER_NUMBER => self::AREA_DATA,
        CustomFields::MEMBER_TYPE => self::AREA_DATA,
        CustomFields::MEMBER_LEAVE_DATE => self::AREA_STATUS,
        CustomFields::MEMBER_TSHIRT_SIZE => self::AREA_DATA,
        CustomFields::MEMBER_BOARD_PAYMENT => self::AREA_DATA,
        CustomFields::MEMBER_GUARDIAN . '_name' => self::AREA_DATA,
        CustomFields::MEMBER_GUARDIAN . '_dni' => self::AREA_DATA,
        CustomFields::MEMBER_GUARDIAN . '_phone' => self::AREA_DATA,
        CustomFields::MEMBER_GUARDIAN . '_email' => self::AREA_DATA,
    ];

    /** Nota que se añade a las entradas mientras dura with_note(). */
    private static string $note = '';

    /** Blogs donde existen las tablas, para no consultar la opción en cada cambio. */
    private static array $enabled_blogs = [];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'added_user_meta', [ $this, 'on_meta_added' ], 10, 4 );
        add_action( 'update_user_meta', [ $this, 'on_meta_update' ], 10, 4 );
        add_action( 'delete_user_meta', [ $this, 'on_meta_delete' ], 10, 4 );
        add_action( 'profile_update', [ $this, 'on_profile_update' ], 10, 2 );
        add_action( 'set_user_role', [ $this, 'on_set_role' ], 10, 3 );
        add_action( 'add_user_role', [ $this, 'on_add_role' ], 10, 2 );
        add_action( 'remove_user_role', [ $this, 'on_remove_role' ], 10, 2 );
    }

    /**
     * Ejecuta $callback añadiendo $note a todas las entradas que se creen dentro.
     *
     * @param   string   $note     Texto corto, p. ej. «Migración».
     * @param   callable $callback Código a ejecutar.
     * @return 	mixed    Lo que devuelva $callback.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function with_note( string $note, callable $callback ) {
        $previous = self::$note;
        self::$note = $note;
        try {
            return $callback();
        } finally {
            self::$note = $previous;
        }
    }

    /**
     * Añade una entrada al registro.
     *
     * @param   int    $user_id Socio.
     * @param   string $area    Una de las constantes AREA_*.
     * @param   string $field   Campo o concepto.
     * @param   mixed  $old     Valor anterior.
     * @param   mixed  $new     Valor nuevo.
     * @param   string $note    Nota opcional (motivo).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function record( int $user_id, string $area, string $field, $old, $new, string $note = '' ): void {
        global $wpdb;

        if ( $user_id <= 0 || false === self::is_enabled() ) {
            return;
        }

        $note = trim( implode( ' · ', array_unique( array_filter( [ self::$note, $note ] ) ) ) );

        $wpdb->insert(
            MembersSchema::table( MembersSchema::LOG ),
            [
                'user_id' => $user_id,
                'area' => $area,
                'field' => substr( $field, 0, 64 ),
                'old_value' => self::to_text( $old ),
                'new_value' => self::to_text( $new ),
                'note' => substr( $note, 0, 255 ),
                'author_id' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );

        // Servicios y estados afectan a los recibos (D40): Receipts comprueba si alguno queda desfasado.
        if ( in_array( $area, [ self::AREA_SERVICES, self::AREA_STATUS ], true ) ) {
            do_action( 'sulayr_member_history_changed', $user_id );
        }
    }

    /**
     * Entradas de un socio, de la más reciente a la más antigua.
     *
     * @param   int $user_id Socio.
     * @param   int $limit   Máximo de entradas.
     * @return 	array<int, object>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get_for_user( int $user_id, int $limit = 200 ): array {
        global $wpdb;

        if ( false === self::is_enabled() ) {
            return [];
        }

        $table = MembersSchema::table( MembersSchema::LOG );

        return $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id DESC LIMIT %d", $user_id, $limit )
        );
    }

    /**
     * Nombre legible de un campo del registro.
     *
     * @param   string $field Campo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function field_label( string $field ): string {
        $labels = [
            'first_name' => __( 'Nombre', SULAYR_TEXTDOMAIN ),
            'last_name' => __( 'Apellidos', SULAYR_TEXTDOMAIN ),
            'user_email' => __( 'Email', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_PHONE => __( 'Teléfono', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_BIRTH => __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_JOIN_DATE => __( 'Fecha de alta', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_DNI => __( 'DNI', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_PHOTO => __( 'Foto', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_MEMBERSHIP_PLAN => __( 'Tarifa (antigua)', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_TRAINING_TYPE => __( 'Modalidad (antigua)', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_SWIMMING => __( 'Natación (antigua)', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_IS_DIRECTIVE => __( 'Directiva (antigua)', SULAYR_TEXTDOMAIN ),
            CustomFields::USER_PROFILE_ATHLETE_STATUS => __( 'Estado', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_NUMBER => __( 'Nº de socio', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_TYPE => __( 'Tipo de socio', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_LEAVE_DATE => __( 'Fecha de baja', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_TSHIRT_SIZE => __( 'Talla de camiseta', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_BOARD_PAYMENT => __( 'Retribución junta', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_GUARDIAN . '_name' => __( 'Tutor: nombre', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_GUARDIAN . '_dni' => __( 'Tutor: DNI', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_GUARDIAN . '_phone' => __( 'Tutor: teléfono', SULAYR_TEXTDOMAIN ),
            CustomFields::MEMBER_GUARDIAN . '_email' => __( 'Tutor: email', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $field ] ?? $field;
    }

    /**
     * added_user_meta
     *
     * @param   int    $meta_id   ID de la meta.
     * @param   int    $user_id   Usuario.
     * @param   string $meta_key  Clave.
     * @param   mixed  $value     Valor.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_meta_added( $meta_id, $user_id, $meta_key, $value ): void {
        if ( false === isset( self::TRACKED_META[ $meta_key ] ) || '' === self::to_text( $value ) ) {
            return;
        }

        self::record( (int) $user_id, self::TRACKED_META[ $meta_key ], $meta_key, '', $value );
    }

    /**
     * update_user_meta (antes de guardar). WordPress no lo dispara si el valor no cambia.
     *
     * @param   int    $meta_id   ID de la meta.
     * @param   int    $user_id   Usuario.
     * @param   string $meta_key  Clave.
     * @param   mixed  $value     Valor nuevo.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_meta_update( $meta_id, $user_id, $meta_key, $value ): void {
        if ( false === isset( self::TRACKED_META[ $meta_key ] ) ) {
            return;
        }

        $old = get_metadata_raw( 'user', (int) $user_id, $meta_key, true );
        if ( self::to_text( $old ) === self::to_text( $value ) ) {
            return;
        }

        self::record( (int) $user_id, self::TRACKED_META[ $meta_key ], $meta_key, $old, $value );
    }

    /**
     * delete_user_meta (antes de borrar).
     *
     * @param   array  $meta_ids  IDs.
     * @param   int    $user_id   Usuario.
     * @param   string $meta_key  Clave.
     * @param   mixed  $value     Valor pedido.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_meta_delete( $meta_ids, $user_id, $meta_key, $value ): void {
        if ( false === isset( self::TRACKED_META[ $meta_key ] ) ) {
            return;
        }

        $old = get_metadata_raw( 'user', (int) $user_id, $meta_key, true );
        if ( '' === self::to_text( $old ) ) {
            return;
        }

        self::record( (int) $user_id, self::TRACKED_META[ $meta_key ], $meta_key, $old, '' );
    }

    /**
     * profile_update: solo el email, que no es una meta.
     *
     * @param   int      $user_id       Usuario.
     * @param   \WP_User $old_user_data Datos anteriores.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_profile_update( $user_id, $old_user_data ): void {
        $user = get_userdata( (int) $user_id );
        if ( false === $user || false === ( $old_user_data instanceof \WP_User ) ) {
            return;
        }

        if ( $old_user_data->user_email !== $user->user_email ) {
            self::record( (int) $user_id, self::AREA_DATA, 'user_email', $old_user_data->user_email, $user->user_email );
        }
    }

    /**
     * set_user_role
     *
     * @param   int      $user_id   Usuario.
     * @param   string   $role      Rol nuevo.
     * @param   string[] $old_roles Roles anteriores.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_set_role( $user_id, $role, $old_roles ): void {
        $old_roles = (array) $old_roles;
        if ( [ $role ] === array_values( $old_roles ) ) {
            return;
        }

        self::record( (int) $user_id, self::AREA_ROLES, 'roles', implode( ', ', $old_roles ), $role );
    }

    /**
     * add_user_role
     *
     * @param   int    $user_id Usuario.
     * @param   string $role    Rol añadido.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_add_role( $user_id, $role ): void {
        self::record( (int) $user_id, self::AREA_ROLES, 'roles', '', $role );
    }

    /**
     * remove_user_role
     *
     * @param   int    $user_id Usuario.
     * @param   string $role    Rol quitado.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_remove_role( $user_id, $role ): void {
        self::record( (int) $user_id, self::AREA_ROLES, 'roles', $role, '' );
    }

    /**
     * True si el blog actual tiene las tablas de socios.
     *
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function is_enabled(): bool {
        $blog_id = get_current_blog_id();
        if ( false === isset( self::$enabled_blogs[ $blog_id ] ) ) {
            self::$enabled_blogs[ $blog_id ] = 0 < (int) get_option( MembersSchema::VERSION_OPTION );
        }

        return self::$enabled_blogs[ $blog_id ];
    }

    /**
     * Valor a texto para guardar y comparar.
     *
     * @param   mixed $value Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function to_text( $value ): string {
        if ( is_array( $value ) || is_object( $value ) ) {
            return (string) wp_json_encode( $value );
        }

        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
