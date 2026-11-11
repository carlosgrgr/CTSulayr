<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campos de socio en la edición de usuario de wp-admin (spec §4.1).
 *
 * Es un grupo aparte del del deportista: el formulario de Mi cuenta solo
 * pinta el grupo del deportista, así que estos campos nunca salen en el front.
 */
class MemberFields {

    public const GROUP = 'sulayr_member_fields';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'acf/init', [ $this, 'register' ] );
        add_filter( 'acf/load_field/key=' . self::key( CustomFields::MEMBER_TYPE ), [ $this, 'load_member_types' ] );
        add_filter( 'acf/validate_value/key=' . self::key( CustomFields::MEMBER_NUMBER ), [ $this, 'validate_unique_number' ], 10, 4 );
        add_filter( 'acf/validate_value/key=' . self::key( CustomFields::MEMBER_LEAVE_DATE ), [ $this, 'validate_leave_date' ], 10, 2 );
    }

    /**
     * Clave ACF.
     *
     * @param   string $name Nombre del campo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function key( string $name ): string {
        return 'field_' . md5( $name );
    }

    /**
     * Registra el grupo.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        if ( false === function_exists( 'acf_add_local_field_group' ) ) {
            return;
        }

        acf_add_local_field_group( [
            'key' => 'group_' . md5( self::GROUP ),
            'title' => __( 'Ficha del socio', SULAYR_TEXTDOMAIN ),
            'fields' => [
                [
                    'key' => self::key( CustomFields::MEMBER_NUMBER ),
                    'label' => __( 'Nº de socio', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_NUMBER,
                    'type' => 'number',
                    'min' => 1,
                    'step' => 1,
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_TYPE ),
                    'label' => __( 'Tipo de socio', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_TYPE,
                    'type' => 'select',
                    'choices' => [],
                    'allow_null' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_LEAVE_DATE ),
                    'label' => __( 'Fecha de baja', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_LEAVE_DATE,
                    'type' => 'text',
                    'placeholder' => 'aaaa-mm-dd',
                    'instructions' => __( 'Último día como socio (aaaa-mm-dd), o «desconocida».', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_TSHIRT_SIZE ),
                    'label' => __( 'Talla de camiseta', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_TSHIRT_SIZE,
                    'type' => 'select',
                    'choices' => [
                        'XS' => 'XS',
                        'S' => 'S',
                        'M' => 'M',
                        'L' => 'L',
                        'XL' => 'XL',
                        'XXL' => 'XXL',
                    ],
                    'allow_null' => 1,
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_BOARD_PAYMENT ),
                    'label' => __( 'Retribución mensual de la junta (€)', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_BOARD_PAYMENT,
                    'type' => 'number',
                    'min' => 0,
                    'step' => 0.01,
                    'instructions' => __( 'Se apunta en su monedero el día 1 de cada mes.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_GUARDIAN ),
                    'label' => __( 'Tutor (menores)', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_GUARDIAN,
                    'type' => 'group',
                    'layout' => 'table',
                    'sub_fields' => [
                        [
                            'key' => self::key( CustomFields::MEMBER_GUARDIAN . '_name' ),
                            'label' => __( 'Nombre', SULAYR_TEXTDOMAIN ),
                            'name' => 'name',
                            'type' => 'text',
                        ],
                        [
                            'key' => self::key( CustomFields::MEMBER_GUARDIAN . '_dni' ),
                            'label' => __( 'DNI', SULAYR_TEXTDOMAIN ),
                            'name' => 'dni',
                            'type' => 'text',
                        ],
                        [
                            'key' => self::key( CustomFields::MEMBER_GUARDIAN . '_phone' ),
                            'label' => __( 'Teléfono', SULAYR_TEXTDOMAIN ),
                            'name' => 'phone',
                            'type' => 'text',
                        ],
                        [
                            'key' => self::key( CustomFields::MEMBER_GUARDIAN . '_email' ),
                            'label' => __( 'Email', SULAYR_TEXTDOMAIN ),
                            'name' => 'email',
                            'type' => 'email',
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'user_form',
                        'operator' => '==',
                        'value' => 'edit',
                    ],
                    [
                        'param' => 'user_role',
                        'operator' => '==',
                        'value' => 'athlete',
                    ],
                ],
            ],
        ] );
    }

    /**
     * Opciones del tipo de socio.
     *
     * @param   array $field Campo.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_member_types( array $field ): array {
        $field['choices'] = MemberSettings::member_types();
        return $field;
    }

    /**
     * El número de socio no se puede repetir.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value Valor.
     * @param   array       $field Campo.
     * @param   string      $input Nombre del input.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_unique_number( $valid, $value, $field, $input ) {
        if ( true !== $valid || '' === (string) $value ) {
            return $valid;
        }

        $current = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF ya verificó el formulario.
        $other = self::find_by_number( (int) $value );

        if ( $other > 0 && $other !== $current ) {
            return __( 'Ese número de socio ya lo tiene otro socio.', SULAYR_TEXTDOMAIN );
        }

        return $valid;
    }

    /**
     * Fecha de baja: vacía, «desconocida» o aaaa-mm-dd.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value Valor.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_leave_date( $valid, $value ) {
        $value = trim( (string) $value );
        if ( true !== $valid || '' === $value || CustomFields::MEMBER_LEAVE_DATE_UNKNOWN === $value ) {
            return $valid;
        }

        $date = \DateTime::createFromFormat( '!Y-m-d', $value );
        if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
            return __( 'Usa el formato aaaa-mm-dd o escribe «desconocida».', SULAYR_TEXTDOMAIN );
        }

        return $valid;
    }

    /**
     * Usuario con un número de socio, o 0.
     *
     * @param   int $number Número.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function find_by_number( int $number ): int {
        $users = get_users( [
            'meta_key' => CustomFields::MEMBER_NUMBER,
            'meta_value' => (string) $number,
            'number' => 1,
            'fields' => 'ID',
            'blog_id' => get_current_blog_id(),
        ] );

        return empty( $users ) ? 0 : (int) $users[0];
    }

    /**
     * Siguiente número libre: el mayor + 1 (D14).
     *
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function next_number(): int {
        global $wpdb;

        $max = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM {$wpdb->usermeta} WHERE meta_key = %s",
            CustomFields::MEMBER_NUMBER
        ) );

        return $max + 1;
    }
}
