<?php
namespace ClubTriatlonSulayr\Modules\Fields\UserFields;

use ClubTriatlonSulayr\Modules\Fields\Fields;
use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserFields extends Fields
{
    private string $group_name = CustomFields::USER_PROFILE_FIELDS_GROUP;
    protected array $fields = [];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function __construct()
    {
        $this->fields = [
            'key' => 'group_' . md5( $this->group_name ),
            'title' => __( 'Athlete data', SULAYR_TEXTDOMAIN ),
            'fields' => [
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_PHOTO ),
                    'label' => __( 'Foto de perfil', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_PHOTO,
                    'type' => 'file',
                    'return_format' => 'id',
                    'uploader' => 'basic',
                    'mime_types' => 'jpg,jpeg,png',
                    'required' => 0,
                    'instructions' => __( 'Sube tu foto (jpg, png, jpeg)', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_PHONE ),
                    'label' => __( 'Número de teléfono', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_PHONE,
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_BIRTH ),
                    'label' => __( 'Fecha de nacimiento', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_BIRTH,
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_JOIN_DATE ),
                    'label' => __( 'Fecha de alta en el club', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_JOIN_DATE,
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_DNI ),
                    'label' => __( 'DNI', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_DNI,
                    'type' => 'text',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN ),
                    'label' => __( 'Tu tarifa', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_MEMBERSHIP_PLAN,
                    'type' => 'select',
                    'choices' => [
                        'basic'   => __( 'Básica', SULAYR_TEXTDOMAIN ),
                        'premium' => __( 'Entrenamiento', SULAYR_TEXTDOMAIN ),
                    ],
                    'allow_null' => 1,
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_TRAINING_TYPE ),
                    'label' => __( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_TRAINING_TYPE,
                    'type' => 'select',
                    'choices' => [
                        'sprint'  => __( 'Sprint', SULAYR_TEXTDOMAIN ),
                        'olimpic' => __( 'Olímpico', SULAYR_TEXTDOMAIN ),
                    ],
                    'allow_null' => 1,
                    'ui' => 1,
                    'return_format' => 'value',
                    'required' => 0,
                    'conditional_logic' => [
                        [
                            [
                                'field' => 'field_' . md5( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN ),
                                'operator' => '==',
                                'value' => 'premium',
                            ],
                        ],
                    ],
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_SWIMMING ),
                    'label' => __( 'Suplemento natación', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_SWIMMING,
                    'type' => 'select',
                    'choices' => [
                        '1' => __( 'Sí', SULAYR_TEXTDOMAIN ),
                        '0' => __( 'No', SULAYR_TEXTDOMAIN ),
                    ],
                    'default_value' => '0',
                    'allow_null' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_IS_DIRECTIVE ),
                    'label' => __( 'Miembro de la directiva', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_IS_DIRECTIVE,
                    'type' => 'true_false',
                    'message' => __( 'Este deportista es miembro de la directiva (solo paga suscripción natación directiva 29,50 €)', SULAYR_TEXTDOMAIN ),
                    'default_value' => 0,
                    'ui' => 1,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_ATHLETE_STATUS ),
                    'label' => __( 'Estado del deportista', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_ATHLETE_STATUS,
                    'type' => 'select',
                    'choices' => [
                        'active' => __( 'Activo', SULAYR_TEXTDOMAIN ),
                        'standby' => __( 'Stand By', SULAYR_TEXTDOMAIN ),
                        'baja' => __( 'Baja', SULAYR_TEXTDOMAIN ),
                        'moroso' => __( 'Moroso', SULAYR_TEXTDOMAIN ),
                    ],
                    'default_value' => 'active',
                    'allow_null' => 0,
                    'ui' => 1,
                    'return_format' => 'value',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'user_form',
                        'operator' => '==',
                        'value' => 'edit',
                    ],
                ],
            ]
        ];

        parent::__construct();
    }
}
