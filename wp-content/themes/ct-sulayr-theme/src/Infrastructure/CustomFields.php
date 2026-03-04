<?php

namespace CTSulayrTheme\Infrastructure;

/**
 * Custom Fields names
 * Solo constantes usadas por Hero, Settings (solo Banner) y Breadcrumbs.
 *
 * @package	ct_sulayr_theme
 */
class CustomFields
{
    // General Settings - Banner (CT Sulayr Theme Settings)
    const GENERAL_SETTINGS_BANNER_HEAD    = 'general_settings_banner_head';
    const GENERAL_SETTINGS_BANNER_DISPLAY = 'general_settings_banner_display_banner';

    // Search (usado por Breadcrumbs)
    const SEARCH_SETTINGS_HELP_CENTER_PAGE = 'search_settings_help_center_page';

    // Hero block
    const HERO_DISPLAY             = 'hero_display_block';
    const HERO_SLIDER              = 'hero_slider';
    const HERO_SLIDER_IMAGE        = 'hero_slider_image';
    const HERO_SLIDER_TITLE        = 'hero_slider_title';
    const HERO_SLIDER_DESCRIPTION  = 'hero_slider_description';
    const HERO_SLIDER_BUTTON       = 'hero_slider_button';
}
