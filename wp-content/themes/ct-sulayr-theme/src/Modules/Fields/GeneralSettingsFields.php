<?php
namespace CTSulayrTheme\Modules\Fields;

use CTSulayrTheme\Infrastructure\CustomFields;
use CTSulayrTheme\Modules\Fields\Fields;
use CTSulayrTheme\Modules\Settings\GeneralSettings;

/**
 * GeneralSettingsFields
 *
 * @package	ct_sulayr_theme
 */
class GeneralSettingsFields extends Fields
{
    private string $group_name = 'general-settings';

    /**
     * __construct()
     *
     * @param string $menu
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct()
    {
        $this->fields = [
            'key' => 'group_' . md5( $this->group_name ),
            'title' => __( 'General Settings', CT_SULAYR_THEME_TEXTDOMAIN ),
            'fields' => [
                // Start: Banner Tab
                    [
                        'key' => 'field_' . md5( CustomFields::GENERAL_SETTINGS_BANNER_HEAD ),
                        'label' => __( 'Banner', CT_SULAYR_THEME_TEXTDOMAIN ),
                        'name' => '',
                        'aria-label' => '',
                        'type' => 'tab',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '',
                            'class' => '',
                            'id' => '',
                        ],
                        'placement' => 'top',
                        'endpoint' => 1,
                    ],
                    [
                        'key' => 'field_' . md5( CustomFields::GENERAL_SETTINGS_BANNER_DISPLAY ),
                        'label' => __( 'Display Banner', CT_SULAYR_THEME_TEXTDOMAIN ),
                        'name' => CustomFields::GENERAL_SETTINGS_BANNER_DISPLAY,
                        'aria-label' => '',
                        'type' => 'button_group',
                        'instructions' => '',
                        'required' => 0,
                        'conditional_logic' => 0,
                        'wrapper' => [
                            'width' => '50',
                            'class' => '',
                            'id' => '',
                        ],
                        'choices' => [
                            'yes' => __( 'Yes', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'no' => __( 'No', CT_SULAYR_THEME_TEXTDOMAIN ),
                        ],
                        'default_value' => 'no',
                        'return_format' => 'value',
                        'allow_null' => 0,
                        'layout' => 'horizontal',
                    ],
                // End: Banner Tab              
            ],
            'location' => [
                [
                    [
                        'param' => 'options_page',
                        'operator' => '==',
                        'value' => GeneralSettings::MENU_SLUG,
                    ],
                ],
            ],
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
            'active' => true,
            'description' => '',
            'show_in_rest' => 0,
        ];

        parent::__construct();
    }
}



