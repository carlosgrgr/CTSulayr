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
                    'key' => 'field_' . md5( CustomFields::HERO_SLIDER ),
                    'label' => __( 'Slider', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'name' => CustomFields::HERO_SLIDER,
                    'aria-label' => '',
                    'type' => 'repeater',
                    'instructions' => '',
                    'required' => 0,
                    'conditional_logic' => 0,
                    'wrapper' => [
                        'width' => '',
                        'class' => '',
                        'id' => '',
                    ],
                    'layout' => 'block',
                    'pagination' => 0,
                    'min' => 1,
                    'max' => 0,
                    'collapsed' => '',
                    'button_label' => __( 'Add slide', CT_SULAYR_THEME_TEXTDOMAIN ),
                    'rows_per_page' => 20,
                    'sub_fields' => [
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_SLIDER_IMAGE ),
                            'label' => __( 'Image', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_SLIDER_IMAGE,
                            'aria-label' => '',
                            'type' => 'image',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
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
                            'parent_repeater' => 'field_' . md5( CustomFields::HERO_SLIDER ),
                        ],
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_SLIDER_TITLE ),
                            'label' => __( 'Title', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_SLIDER_TITLE,
                            'aria-label' => '',
                            'type' => 'text',
                            'instructions' => '',
                            'required' => 1,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '70',
                                'class' => '',
                                'id' => '',
                            ],
                            'default_value' => '',
                            'maxlength' => '',
                            'allow_in_bindings' => 1,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                            'parent_repeater' => 'field_' . md5( CustomFields::HERO_SLIDER ),
                        ],
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_SLIDER_DESCRIPTION ),
                            'label' => __( 'Description', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_SLIDER_DESCRIPTION,
                            'aria-label' => '',
                            'type' => 'text',
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
                            'allow_in_bindings' => 1,
                            'placeholder' => '',
                            'prepend' => '',
                            'append' => '',
                            'parent_repeater' => 'field_' . md5( CustomFields::HERO_SLIDER ),
                        ],
                        [
                            'key' => 'field_' . md5( CustomFields::HERO_SLIDER_BUTTON ),
                            'label' => __( 'Button', CT_SULAYR_THEME_TEXTDOMAIN ),
                            'name' => CustomFields::HERO_SLIDER_BUTTON,
                            'aria-label' => '',
                            'type' => 'link',
                            'instructions' => '',
                            'required' => 0,
                            'conditional_logic' => 0,
                            'wrapper' => [
                                'width' => '',
                                'class' => '',
                                'id' => '',
                            ],
                            'return_format' => 'array',
                            'allow_in_bindings' => 0,
                            'parent_repeater' => 'field_' . md5( CustomFields::HERO_SLIDER ),
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
