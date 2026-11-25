<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Payments\ChargeMethod;
use ClubTriatlonSulayr\Modules\Payments\PaymentCards;

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

    /** Tallas de camiseta de la ficha y del formulario de alta. */
    public const TSHIRT_SIZES = [ 'XS', 'S', 'M', 'L', 'XL', 'XXL' ];

    /** Mensaje de solo lectura con la tarjeta guardada (SPEC-COBROS §4.1). */
    private const CARD_FIELD = 'sulayr_member_card_status';

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
        add_filter( 'acf/load_field/key=' . self::key( ChargeMethod::META ), [ $this, 'load_charge_methods' ] );
        add_filter( 'acf/load_field/key=' . self::key( self::CARD_FIELD ), [ $this, 'load_card_status' ] );
    }

    /**
     * Valores del sexo como [ valor => nombre ].
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sexes(): array {
        return [
            CustomFields::SEX_MALE => __( 'Masculino', SULAYR_TEXTDOMAIN ),
            CustomFields::SEX_FEMALE => __( 'Femenino', SULAYR_TEXTDOMAIN ),
        ];
    }

    /**
     * Nombre del sexo de un socio, o '—'.
     *
     * @param   string $sex Valor guardado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sex_label( string $sex ): string {
        return self::sexes()[ $sex ] ?? '—';
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
                    'key' => self::key( CustomFields::MEMBER_SEX ),
                    'label' => __( 'Sexo', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_SEX,
                    'type' => 'select',
                    'choices' => self::sexes(),
                    'allow_null' => 1,
                    'return_format' => 'value',
                    'instructions' => __( 'Para las clasificaciones del ranking interno.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( ChargeMethod::META ),
                    'label' => __( 'Medio de cobro', SULAYR_TEXTDOMAIN ),
                    'name' => ChargeMethod::META,
                    'type' => 'select',
                    'choices' => [],
                    'return_format' => 'value',
                    'instructions' => __( 'Solo los de «Tarjeta (TPV)» entran en el cobro automático con la tarjeta guardada.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( self::CARD_FIELD ),
                    'label' => __( 'Tarjeta guardada', SULAYR_TEXTDOMAIN ),
                    'name' => '',
                    'type' => 'message',
                    'message' => '',
                ],
                [
                    'key' => self::key( CustomFields::MEMBER_TSHIRT_SIZE ),
                    'label' => __( 'Talla de camiseta', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::MEMBER_TSHIRT_SIZE,
                    'type' => 'select',
                    'choices' => array_combine( self::TSHIRT_SIZES, self::TSHIRT_SIZES ),
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
     * Opciones del medio de cobro: vacío sigue el ajuste (C8).
     *
     * @param   array $field Campo.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_charge_methods( array $field ): array {
        $field['choices'] = [
            '' => sprintf( __( 'Según el ajuste (ahora: %s)', SULAYR_TEXTDOMAIN ), ChargeMethod::label( ChargeMethod::default_method() ) ),
        ] + ChargeMethod::choices();

        return $field;
    }

    /**
     * Texto de la tarjeta guardada del socio que se está editando.
     *
     * @param   array $field Campo.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_card_status( array $field ): array {
        $user_id = absint( $_GET['user_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Solo lectura.
        if ( 0 === $user_id && defined( 'IS_PROFILE_PAGE' ) && IS_PROFILE_PAGE ) {
            $user_id = get_current_user_id();
        }

        $card = $user_id > 0 ? PaymentCards::active( $user_id ) : null;
        $field['message'] = null === $card
            ? esc_html__( 'Sin tarjeta guardada.', SULAYR_TEXTDOMAIN )
            : esc_html( sprintf( __( '%1$s, guardada el %2$s.', SULAYR_TEXTDOMAIN ), PaymentCards::describe( $card ), MemberDates::show( substr( (string) $card->created_at, 0, 10 ) ) ) );

        return $field;
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
