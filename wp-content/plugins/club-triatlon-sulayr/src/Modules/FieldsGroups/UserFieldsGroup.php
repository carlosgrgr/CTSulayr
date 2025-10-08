<?php
namespace ClubTriatlonSulayr\Modules\FieldsGroups;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class UserFieldsGroup extends FieldsGroup {

    const GROUP_NAME = 'user_profile_fields';

    protected string $title = 'Perfil de Usuario';
    protected int $show_in_rest = 1;

    /**
     * Definir campos
     */
    protected function get_fields(): array {
        return [
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
        ];
    }

    /**
     * Ubicación del grupo
     */
    protected function get_location(): array {
        return [
            [
                [
                    'param' => 'user_form',
                    'operator' => '==',
                    'value' => 'edit',
                ],
            ],
        ];
    }
}
