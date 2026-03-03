<?php
namespace CTSulayrTheme\Modules\Fields;

use CTSulayrTheme\Modules\Fields\Fields;
use CTSulayrTheme\Infrastructure\CustomBlocks;
use CTSulayrTheme\Infrastructure\CustomFields;

/**
 * HeroFields
 *
 * @package	ct_sulayr_theme
 */
class HeroFields extends Fields
{
    private string $group_name = 'hero';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct()
    {
        $this->fields = [
            'key' => 'group_' . md5( $this->group_name ),
            'title' => CustomBlocks::HERO_BLOCK_NAME,
            'fields' => [
                [
                    'key' => 'field_' . md5( CustomFields::HERO_DISPLAY ),
                    'label' => __( 'Display Block', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_DISPLAY,
                    'aria-label' => '',
                    'type' => 'button_group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'choices' => [
                        'yes' => __('Yes', CT_SULAYR_THEME_TEXTDOMAIN),
                        'no' => __('No', CT_SULAYR_THEME_TEXTDOMAIN),
                    ],
                    'default_value' => 1,
                    'return_format' => 'value',
                    'allow_null' => 0,
                    'layout' => 'horizontal',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::HERO_TITLE ),
                    'label' => __('Title line 1', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_TITLE,
                    'aria-label' => '',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '30',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'maxlength' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::HERO_FEATURED_TEXT ),
                    'label' => __('Title line 2', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_FEATURED_TEXT,
                    'aria-label' => '',
                    'type' => 'text',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '40',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'maxlength' => '',
                    'placeholder' => '',
                    'prepend' => '',
                    'append' => '',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::HERO_HEADING ),
                    'label' => __('Heading', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_HEADING,
                    'aria-label' => '',
                    'type' => 'button_group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '30',
                        'class' => '',
                        'id' => '',
                    ],
                    'choices' => [
                        'h1' => 'H1',
                        'h2' => 'H2',
                        'h3' => 'H3',
                        'h4' => 'H4',
                        'h5' => 'H5',
                        'h6' => 'H6',
                    ],
                    'default_value' => 'h1',
                    'return_format' => 'value',
                    'allow_null' => 0,
                    'layout' => 'horizontal',
                ],

                [
                    'key' => 'field_' . md5( CustomFields::HERO_DESCRIPTION ),
                    'label' => __('Description', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_DESCRIPTION,
                    'aria-label' => '',
                    'type' => 'textarea',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'default_value' => '',
                    'maxlength' => '',
                    'rows' => '',
                    'placeholder' => '',
                    'new_lines' => '',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::HERO_IMAGE ),
                    'label' => __('Image', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_IMAGE,
                    'aria-label' => '',
                    'type' => 'image',
                    'instructions' => '',
                    'required' => 1,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ],
                    'return_format' => 'array',
                    'library' => 'all',
                    'min_width' => '',
                    'min_height' => '',
                    'min_size' => '',
                    'max_width' => '',
                    'max_height' => '',
                    'max_size' => '',
                    'mime_types' => '',
                    'preview_size' => 'medium',
                ],
                [
                    'key' => 'field_' . md5( CustomFields::HERO_BUTTONS_GROUP ),
                    'label' => '',
                    'name' => CustomFields::HERO_BUTTONS_GROUP,
                    'aria-label' => '',
                    'type' => 'group',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '50',
                        'class' => '',
                        'id' => '',
                    ],
                    'layout' => 'block',
                    'sub_fields' => [
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_BUTTONS_OPTIONS ),
                            'label' => __('Display Buttons', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_BUTTONS_OPTIONS,
                            'aria-label' => '',
                            'type' => 'button_group',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'choices' => [
                                'account' => __( 'Account', CT_SULAYR_THEME_TEXTDOMAIN ),
                                'stores'  => __( 'Stores', CT_SULAYR_THEME_TEXTDOMAIN ),
                                'none'    => __( 'None', CT_SULAYR_THEME_TEXTDOMAIN ),
                                'custom'  => __( 'Custom', CT_SULAYR_THEME_TEXTDOMAIN ),
                            ],
                            'default_value' => 'account',
                            'return_format' => 'value',
                            'allow_null' => 0,
                            'layout' => 'horizontal',
                        ],
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_BUTTONS_MESSAGE ),
                            'label' =>  __( 'Edit buttons options', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => '',
                            'aria-label' => '',
                            'type' => 'message',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_' . md5(CustomFields::HERO_BUTTONS_OPTIONS),
                                        'operator' => '!=',
                                        'value' => 'custom',
                                    ],
                                ],
                            ],
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'message' => __( 'To edit the buttons text and image visit <a href="' . get_site_url() . '/wp-admin/admin.php?page=ct_sulayr_theme-settings">CT Sulayr Theme Settings</a>.', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'new_lines' => 'wpautop',
                            'esc_html' => 0,
                        ],
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_BUTTONS_CUSTOM_LINK),
                            'label' => __( 'Custom Link', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_BUTTONS_CUSTOM_LINK,
                            'aria-label' => '',
                            'type' => 'link',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => [
                                [
                                    [
                                        'field' => 'field_' . md5(CustomFields::HERO_BUTTONS_OPTIONS),
                                        'operator' => '==',
                                        'value' => 'custom',
                                    ],
                                ],
                            ],
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => '',
                            'maxlength' => '',
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                        ],
                    ],
                ],                 
            ],
            'location' => [
                [
                    [
                        'param' => 'block',
                        'operator' => '==',
                        'value' => CustomBlocks::HERO_BLOCK_SLUG,
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
