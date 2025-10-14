<?php
namespace ClubTriatlonSulayr\Modules\Fields\UserFields;

use ClubTriatlonSulayr\Modules\Fields\Fields;
use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserFields extends Fields
{
    private string $group_name = 'user_profile_fields';
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
                    'key' => 'sulayr_user_phone',
                    'label' => 'Teléfono',
                    'name' => 'sulayr_user_phone',
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'sulayr_user_birth_date',
                    'label' => 'Fecha de nacimiento',
                    'name' => 'sulayr_user_birth_date',
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                ],
                [
                    'key' => 'sulayr_user_dni',
                    'label' => 'DNI',
                    'name' => 'sulayr_user_dni',
                    'type' => 'text',
                ],
                [
                    'key' => 'sulayr_user_membership_plan',
                    'label' => 'Tarifa elegida',
                    'name' => 'sulayr_user_membership_plan',
                    'type' => 'select',
                    'choices' => [
                        'basic'   => 'Básica',
                        'premium' => 'Entrenamiento',
                    ],
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'sulayr_user_swimming',
                    'label' => 'Suplemento de natación',
                    'name' => 'sulayr_user_swimming',
                    'type' => 'true_false',
                    'ui' => 1,
                ],
                [
                    'key' => 'sulayr_user_photo',
                    'label' => 'Foto',
                    'name' => 'sulayr_user_photo',
                    'type' => 'image',
                    'return_format' => 'id',
                    'preview_size' => 'thumbnail',
                    'library' => 'all',
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
