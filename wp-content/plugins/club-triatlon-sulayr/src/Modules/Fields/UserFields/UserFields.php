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
                    'key' => 'sulayr_user_photo',
                    'label' => __( 'Profile photo', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_PHOTO,
                    'type' => 'file',
                    'return_format' => 'id',
                    'uploader' => 'basic',
                    'mime_types' => 'jpg,jpeg,png',
                    'required' => 0,
                    'instructions' => __( 'Upload your photo (jpg, png, jpeg)', SULAYR_TEXTDOMAIN ),
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_PHONE ),
                    'label' => __( 'Phone number', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_PHONE,
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_BIRTH ),
                    'label' => __( 'Birthday', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_BIRTH,
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
                    'label' => __( 'Your Selected Rate', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_MEMBERSHIP_PLAN,
                    'type' => 'select',
                    'choices' => [
                        'basic'   => __( 'Basic', SULAYR_TEXTDOMAIN ),
                        'premium' => __( 'Training', SULAYR_TEXTDOMAIN ),
                    ],
                    'ui' => 1,
                    'return_format' => 'value',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::USER_PROFILE_SWIMMING ),
                    'label' => __( 'Swimming Supplement', SULAYR_TEXTDOMAIN ),
                    'name' => CustomFields::USER_PROFILE_SWIMMING,
                    'type' => 'select',
                    'choices' => [
                        '1' => __( 'Yes', SULAYR_TEXTDOMAIN ),
                        '0' => __( 'No', SULAYR_TEXTDOMAIN ),
                    ],
                    'default_value' => '0',
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
