<?php

namespace ClubTriatlonSulayr\Modules\Fields\WalletFields;

use ClubTriatlonSulayr\Modules\Fields\Fields;
use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Campos ACF del CPT Monedero (sulayr_wallet).
 * Incluye guardado automático de "Creado por".
 */
class WalletFields extends Fields {

    private string $group_name = CustomFields::WALLET_FIELDS_GROUP;
    protected array $fields = [];

    public function __construct() {
        $this->fields = [
            'key' => 'group_' . md5( $this->group_name ),
            'title' => __( 'Datos del movimiento', SULAYR_TEXTDOMAIN ),
            'fields' => [
                [
                    'key' => 'field_' . md5( CustomFields::WALLET_ATHLETE ),
                    'label' => __( 'Deportista', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::WALLET_ATHLETE,
                    'type' => 'user',
                    'role' => [ 'athlete' ],
                    'return_format' => 'id',
                    'required' => 1,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::WALLET_AMOUNT ),
                    'label' => __( 'Importe', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::WALLET_AMOUNT,
                    'type' => 'number',
                    'required' => 1,
                    'step' => '0.01',
                    'placeholder' => 'Positivo = ingreso, negativo = cargo',
                    'instructions' => __( 'Use números positivos para ingresos y negativos para cargos.', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => 'field_' . md5( CustomFields::WALLET_CONCEPT ),
                    'label' => __( 'Concepto', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::WALLET_CONCEPT,
                    'type' => 'textarea',
                    'required' => 1,
                    'rows' => 3,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::WALLET_DATE ),
                    'label' => __( 'Fecha de movimiento', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::WALLET_DATE,
                    'type' => 'date_picker',
                    'display_format' => 'd/m/Y',
                    'return_format' => 'Y-m-d',
                    'required' => 1,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::WALLET_CREATED_BY ),
                    'label' => __( 'Creado por', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::WALLET_CREATED_BY,
                    'type' => 'user',
                    'return_format' => 'id',
                    'required' => 0,
                    'disabled' => 1,
                    'instructions' => __( 'Se guarda automáticamente al crear el movimiento.', SULAYR_TEXTDOMAIN ),
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'sulayr_wallet',
                    ],
                ],
            ],
        ];

        parent::__construct();
    }

    public function init(): void {
        parent::init();
        add_action( 'acf/save_post', [ $this, 'set_created_by_on_save' ], 20 );
    }

    /**
     * Rellena "Creado por" con el usuario actual al guardar (solo en wp-admin, post tipo sulayr_wallet).
     */
    public function set_created_by_on_save( $post_id ): void {
        if ( ! is_admin() || ! function_exists( 'get_field' ) ) {
            return;
        }

        $post = get_post( $post_id );
        if ( ! $post || $post->post_type !== 'sulayr_wallet' ) {
            return;
        }

        $current = get_field( CustomFields::WALLET_CREATED_BY, $post_id );
        if ( ! empty( $current ) ) {
            return;
        }

        $user_id = get_current_user_id();
        if ( $user_id ) {
            update_field( CustomFields::WALLET_CREATED_BY, $user_id, $post_id );
        }
    }
}
