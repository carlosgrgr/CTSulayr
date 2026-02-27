<?php

namespace ClubTriatlonSulayr\Modules\Fields\SubscriptionChangesFields;

use ClubTriatlonSulayr\Modules\Fields\Fields;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\SubscriptionChangesPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * ACF fields for subscription changes CPT (sulayr_subscription_changes).
 */
class SubscriptionChangesFields extends Fields {

    protected array $fields = [];

    public function __construct() {
        $this->fields = [
            'key'    => 'group_' . md5( CustomFields::SUBSCRIPTION_CHANGES_FIELDS_GROUP ),
            'title'  => __( 'Datos del cambio de tarifa', SULAYR_TEXTDOMAIN ),
            'fields' => [
                [
                    'key'           => 'field_' . md5( CustomFields::SUB_CHANGE_USER ),
                    'label'         => __( 'Usuario sobre el que se hace el cambio', SULAYR_TEXTDOMAIN ),
                    'name'         => CustomFields::SUB_CHANGE_USER,
                    'type'         => 'user',
                    'role'         => [ 'athlete' ],
                    'return_format' => 'id',
                    'required'      => 1,
                ],
                [
                    'key'           => 'field_' . md5( CustomFields::SUB_CHANGE_MADE_BY ),
                    'label'         => __( 'Persona que hace el cambio', SULAYR_TEXTDOMAIN ),
                    'name'         => CustomFields::SUB_CHANGE_MADE_BY,
                    'type'         => 'user',
                    'return_format' => 'id',
                    'required'      => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_PLAN_BEFORE ),
                    'label'    => __( 'Tarifa antes del cambio', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_PLAN_BEFORE,
                    'type'     => 'text',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_PLAN_AFTER ),
                    'label'    => __( 'Tarifa después del cambio', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_PLAN_AFTER,
                    'type'     => 'text',
                    'required' => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_SWIMMING_BEFORE ),
                    'label'    => __( 'Suplemento natación antes', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_SWIMMING_BEFORE,
                    'type'     => 'true_false',
                    'ui'       => 1,
                    'required' => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_SWIMMING_AFTER ),
                    'label'    => __( 'Suplemento natación después', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_SWIMMING_AFTER,
                    'type'     => 'true_false',
                    'ui'       => 1,
                    'required' => 0,
                ],
                [
                    'key'           => 'field_' . md5( CustomFields::SUB_CHANGE_DATE ),
                    'label'         => __( 'Fecha del cambio', SULAYR_TEXTDOMAIN ),
                    'name'         => CustomFields::SUB_CHANGE_DATE,
                    'type'         => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'required'      => 1,
                ],
                [
                    'key'           => 'field_' . md5( CustomFields::SUB_CHANGE_EFFECTIVE_DATE ),
                    'label'         => __( 'Fecha efectiva del cambio (próximo ciclo)', SULAYR_TEXTDOMAIN ),
                    'name'         => CustomFields::SUB_CHANGE_EFFECTIVE_DATE,
                    'type'         => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'required'      => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_REASON ),
                    'label'    => __( 'Motivo del cambio', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_REASON,
                    'type'     => 'textarea',
                    'rows'     => 3,
                    'required' => 0,
                ],
                [
                    'key'      => 'field_' . md5( CustomFields::SUB_CHANGE_STRIPE_SUBSCRIPTION_ID ),
                    'label'    => __( 'ID suscripción Stripe', SULAYR_TEXTDOMAIN ),
                    'name'     => CustomFields::SUB_CHANGE_STRIPE_SUBSCRIPTION_ID,
                    'type'     => 'text',
                    'required' => 0,
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => SubscriptionChangesPostType::POST_TYPE_NAME,
                    ],
                ],
            ],
        ];
        parent::__construct();
    }
}
