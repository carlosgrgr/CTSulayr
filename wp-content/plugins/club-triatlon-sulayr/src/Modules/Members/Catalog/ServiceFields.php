<?php
namespace ClubTriatlonSulayr\Modules\Members\Catalog;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campos ACF de un servicio (spec §4.3) y sus validaciones.
 */
class ServiceFields {

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'acf/init', [ $this, 'register' ] );
        add_filter( 'acf/load_field/key=' . self::key( CustomFields::SERVICE_PRICE_MEMBER_TYPE ), [ $this, 'load_member_types' ] );
        add_filter( 'acf/validate_value/key=' . self::key( CustomFields::SERVICE_IS_BASE ), [ $this, 'validate_single_base' ], 10, 2 );
        add_filter( 'acf/validate_value/key=' . self::key( CustomFields::SERVICE_PRICES ), [ $this, 'validate_prices' ], 10, 2 );
        add_action( 'save_post_' . ServicePostType::POST_TYPE_NAME, [ ServiceCatalog::class, 'flush' ] );
    }

    /**
     * Clave ACF de un campo de servicio.
     *
     * @param   string $name Nombre.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function key( string $name ): string {
        return 'field_' . md5( 'sulayr_service_' . $name );
    }

    /**
     * Registra el grupo de campos.
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
            'key' => 'group_' . md5( CustomFields::SERVICE_FIELDS_GROUP ),
            'title' => __( 'Datos del servicio', SULAYR_TEXTDOMAIN ),
            'fields' => [
                [
                    'key' => self::key( CustomFields::SERVICE_IS_BASE ),
                    'label' => __( 'Es la cuota base', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_IS_BASE,
                    'type' => 'true_false',
                    'ui' => 1,
                    'instructions' => __( 'Todos los socios la tienen. Solo puede haber una cuota base publicada.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_PRICES ),
                    'label' => __( 'Precios', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_PRICES,
                    'type' => 'repeater',
                    'layout' => 'table',
                    'min' => 1,
                    'button_label' => __( 'Añadir precio', SULAYR_TEXTDOMAIN ),
                    'instructions' => __( 'Para subir un precio, añade una fila nueva con la fecha desde la que vale; no cambies las antiguas. En cada mes vale la fila más reciente con fecha anterior o igual al día 1: la del tipo de socio si hay alguna, si no la general. Necesita al menos un precio general.', SULAYR_TEXTDOMAIN ),
                    'sub_fields' => [
                        [
                            'key' => self::key( CustomFields::SERVICE_PRICE_MEMBER_TYPE ),
                            'label' => __( 'Tipo de socio', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_PRICE_MEMBER_TYPE,
                            'type' => 'select',
                            'choices' => [],
                            'allow_null' => 0,
                            'return_format' => 'value',
                        ],
                        [
                            'key' => self::key( CustomFields::SERVICE_PRICE_AMOUNT ),
                            'label' => __( 'Importe mensual (€)', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_PRICE_AMOUNT,
                            'type' => 'number',
                            'step' => 0.01,
                            'min' => 0,
                            'required' => 1,
                        ],
                        [
                            'key' => self::key( CustomFields::SERVICE_PRICE_VALID_FROM ),
                            'label' => __( 'Desde', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_PRICE_VALID_FROM,
                            'type' => 'date_picker',
                            'display_format' => 'd/m/Y',
                            'return_format' => 'Y-m-d',
                            'required' => 1,
                        ],
                    ],
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_PRORATED ),
                    'label' => __( 'Se prorratea', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_PRORATED,
                    'type' => 'true_false',
                    'ui' => 1,
                    'default_value' => 1,
                    'instructions' => __( 'Si no, en el mes de alta se cobra completo (p. ej. natación).', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_CANCEL_ON_FREEZE ),
                    'label' => __( 'Se anula al congelar en el mes en curso', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_CANCEL_ON_FREEZE,
                    'type' => 'true_false',
                    'ui' => 1,
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_PUBLIC ),
                    'label' => __( 'Aparece en el formulario público', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_PUBLIC,
                    'type' => 'true_false',
                    'ui' => 1,
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_OPTIONS ),
                    'label' => __( 'Opciones', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_OPTIONS,
                    'type' => 'repeater',
                    'layout' => 'table',
                    'button_label' => __( 'Añadir opción', SULAYR_TEXTDOMAIN ),
                    'instructions' => __( 'Si tiene opciones, el socio tiene que elegir una (p. ej. sprint / olímpico). No cambies la clave de una opción ya usada.', SULAYR_TEXTDOMAIN ),
                    'sub_fields' => [
                        [
                            'key' => self::key( 'option_' . CustomFields::SERVICE_OPTION_KEY ),
                            'label' => __( 'Clave', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_OPTION_KEY,
                            'type' => 'text',
                            'required' => 1,
                        ],
                        [
                            'key' => self::key( 'option_' . CustomFields::SERVICE_OPTION_LABEL ),
                            'label' => __( 'Nombre', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_OPTION_LABEL,
                            'type' => 'text',
                            'required' => 1,
                        ],
                    ],
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_INCOMPATIBLE ),
                    'label' => __( 'Incompatible con', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_INCOMPATIBLE,
                    'type' => 'post_object',
                    'post_type' => [ ServicePostType::POST_TYPE_NAME ],
                    'multiple' => 1,
                    'return_format' => 'id',
                    'ui' => 1,
                    'allow_null' => 1,
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_GRANTS_SWIMMING ),
                    'label' => __( 'Da acceso a reservas de natación', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_GRANTS_SWIMMING,
                    'type' => 'true_false',
                    'ui' => 1,
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_COACH_LIST ),
                    'label' => __( 'Aparece en el listado del entrenador', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_COACH_LIST,
                    'type' => 'true_false',
                    'ui' => 1,
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_WELCOME_TEXT ),
                    'label' => __( 'Texto para el email de bienvenida', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_WELCOME_TEXT,
                    'type' => 'wysiwyg',
                    'tabs' => 'all',
                    'toolbar' => 'basic',
                    'media_upload' => 0,
                    'instructions' => __( 'Se añade al email de bienvenida de quien tenga este servicio.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => self::key( CustomFields::SERVICE_STEPS ),
                    'label' => __( 'Pasos de alta que añade', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::SERVICE_STEPS,
                    'type' => 'repeater',
                    'layout' => 'table',
                    'button_label' => __( 'Añadir paso', SULAYR_TEXTDOMAIN ),
                    'sub_fields' => [
                        [
                            'key' => self::key( 'step_' . CustomFields::SERVICE_STEP_LABEL ),
                            'label' => __( 'Paso', SULAYR_TEXTDOMAIN ),
                            'name' => CustomFields::SERVICE_STEP_LABEL,
                            'type' => 'text',
                            'required' => 1,
                        ],
                    ],
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => ServicePostType::POST_TYPE_NAME,
                    ],
                ],
            ],
        ] );
    }

    /**
     * Opciones del desplegable de tipo de socio.
     *
     * @param   array $field Campo.
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_member_types( array $field ): array {
        $field['choices'] = ServiceCatalog::member_type_labels();
        return $field;
    }

    /**
     * Solo una cuota base publicada.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value Valor.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_single_base( $valid, $value ) {
        if ( true !== $valid || empty( $value ) ) {
            return $valid;
        }

        $current = isset( $_POST['post_ID'] ) ? (int) $_POST['post_ID'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- ACF ya verificó el formulario.
        $others = get_posts( [
            'post_type' => ServicePostType::POST_TYPE_NAME,
            'post_status' => 'publish',
            'numberposts' => 1,
            'fields' => 'ids',
            'exclude' => $current ? [ $current ] : [],
            'meta_key' => CustomFields::SERVICE_IS_BASE,
            'meta_value' => '1',
        ] );

        if ( ! empty( $others ) ) {
            return sprintf(
                /* translators: %s: nombre del servicio */
                __( 'Ya hay una cuota base publicada: «%s».', SULAYR_TEXTDOMAIN ),
                get_the_title( (int) $others[0] )
            );
        }

        return $valid;
    }

    /**
     * Exige al menos un precio general.
     *
     * @param   bool|string $valid Resultado previo.
     * @param   mixed       $value Filas.
     * @return 	bool|string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function validate_prices( $valid, $value ) {
        if ( true !== $valid || ! is_array( $value ) ) {
            return $valid;
        }

        $type_key = self::key( CustomFields::SERVICE_PRICE_MEMBER_TYPE );
        foreach ( $value as $row ) {
            if ( is_array( $row ) && ServiceCatalog::GENERAL === (string) ( $row[ $type_key ] ?? '' ) ) {
                return $valid;
            }
        }

        return __( 'Añade al menos un precio para el tipo «General».', SULAYR_TEXTDOMAIN );
    }
}
