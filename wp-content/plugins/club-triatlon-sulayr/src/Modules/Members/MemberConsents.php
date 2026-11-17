<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Consentimientos (D10): cada aceptación o revocación es una fila nueva con
 * la versión del texto, la fecha y quién la dio (socio o tutor).
 *
 * Mientras la solicitud no está aprobada, la fila lleva request_id y
 * user_id 0. Al aprobar, se le pone el usuario.
 */
class MemberConsents {

    public const PRIVACY = 'privacy';
    public const RULES = 'rules';
    public const IMAGE = 'image';
    /**
     * Autorización de cargos recurrentes al guardar la tarjeta (SPEC-COBROS C12).
     * No está en types(): no sale en el formulario de alta.
     */
    public const RECURRING_CHARGES = 'cargos_recurrentes';

    public const BY_MEMBER = 'socio';
    public const BY_GUARDIAN = 'tutor';

    /**
     * Tipos con su nombre y si son obligatorios.
     *
     * @return 	array<string, array{label: string, required: bool}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function types(): array {
        return [
            self::PRIVACY => [ 'label' => __( 'Política de privacidad', SULAYR_TEXTDOMAIN ), 'required' => true ],
            self::RULES => [ 'label' => __( 'Normas y condiciones del club', SULAYR_TEXTDOMAIN ), 'required' => true ],
            self::IMAGE => [ 'label' => __( 'Uso de imagen', SULAYR_TEXTDOMAIN ), 'required' => false ],
        ];
    }

    /**
     * Texto vigente de un consentimiento (de Socios → Ajustes).
     *
     * @param   string $type Tipo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function text( string $type ): string {
        return (string) MemberSettings::get( "consent_{$type}_text" );
    }

    /**
     * Versión vigente de un consentimiento.
     *
     * @param   string $type Tipo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function version( string $type ): string {
        return (string) MemberSettings::get( "consent_{$type}_version" );
    }

    /**
     * Guarda una aceptación o revocación con la versión vigente.
     *
     * @param   string $type       Tipo.
     * @param   bool   $accepted   Aceptado (true) o no/revocado (false).
     * @param   string $given_by   BY_MEMBER o BY_GUARDIAN.
     * @param   int    $user_id    Socio (0 si aún es una solicitud).
     * @param   int    $request_id Solicitud (0 si no viene de una).
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function record( string $type, bool $accepted, string $given_by, int $user_id = 0, int $request_id = 0 ): int {
        global $wpdb;

        $version = self::version( $type );
        $wpdb->insert(
            MembersSchema::table( MembersSchema::CONSENTS ),
            [
                'user_id' => $user_id,
                'request_id' => $request_id,
                'type' => $type,
                'version' => substr( $version, 0, 32 ),
                'accepted' => $accepted ? 1 : 0,
                'given_by' => self::BY_GUARDIAN === $given_by ? self::BY_GUARDIAN : self::BY_MEMBER,
                'created_by' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%d', '%s', '%s', '%d', '%s', '%d', '%s' ]
        );

        if ( $user_id > 0 ) {
            MemberLog::record( $user_id, MemberLog::AREA_CONSENTS, self::label( $type ), '', self::describe( $accepted, $version, $given_by ) );
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Pasa al socio los consentimientos de su solicitud y los registra.
     *
     * @param   int $request_id Solicitud.
     * @param   int $user_id    Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function attach_request( int $request_id, int $user_id ): void {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::CONSENTS );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE request_id = %d AND user_id = 0 ORDER BY id ASC", $request_id ) );
        foreach ( $rows as $row ) {
            $wpdb->update( $table, [ 'user_id' => $user_id ], [ 'id' => (int) $row->id ], [ '%d' ], [ '%d' ] );
            MemberLog::record(
                $user_id,
                MemberLog::AREA_CONSENTS,
                self::label( (string) $row->type ),
                '',
                self::describe( (bool) $row->accepted, (string) $row->version, (string) $row->given_by ),
                sprintf( __( 'Dado en la solicitud #%1$d el %2$s', SULAYR_TEXTDOMAIN ), $request_id, (string) $row->created_at )
            );
        }
    }

    /**
     * Filas de un socio o de una solicitud, de la más antigua a la más reciente.
     *
     * @param   int $user_id    Socio (0 = buscar por solicitud).
     * @param   int $request_id Solicitud.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function rows( int $user_id, int $request_id = 0 ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::CONSENTS );
        if ( $user_id > 0 ) {
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY id ASC", $user_id ) );
        }

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE request_id = %d ORDER BY id ASC", $request_id ) );
    }

    /**
     * Última fila de un tipo para un socio, o null.
     *
     * @param   int    $user_id Socio.
     * @param   string $type    Tipo.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function latest( int $user_id, string $type ): ?object {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::CONSENTS );

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND type = %s ORDER BY id DESC LIMIT 1", $user_id, $type ) );
    }

    /**
     * Nombre de un tipo.
     *
     * @param   string $type Tipo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function label( string $type ): string {
        if ( self::RECURRING_CHARGES === $type ) {
            return __( 'Autorización de cargos recurrentes', SULAYR_TEXTDOMAIN );
        }

        return self::types()[ $type ]['label'] ?? $type;
    }

    /**
     * Texto corto para el registro, p. ej. «Aceptado (v. 1, tutor)».
     *
     * @param   bool   $accepted Aceptado.
     * @param   string $version  Versión.
     * @param   string $given_by Quién.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function describe( bool $accepted, string $version, string $given_by ): string {
        return sprintf(
            /* translators: 1: Aceptado o No aceptado, 2: versión, 3: socio o tutor. */
            __( '%1$s (v. %2$s, %3$s)', SULAYR_TEXTDOMAIN ),
            $accepted ? __( 'Aceptado', SULAYR_TEXTDOMAIN ) : __( 'No aceptado', SULAYR_TEXTDOMAIN ),
            $version,
            self::BY_GUARDIAN === $given_by ? __( 'tutor', SULAYR_TEXTDOMAIN ) : __( 'socio', SULAYR_TEXTDOMAIN )
        );
    }
}
