<?php
namespace CTSulayrTheme\Modules\Settings;

use CTSulayrTheme\Modules\Settings\SettingsPage;

/**
 * CT Sulayr Settings – única página de opciones del tema (menú admin).
 *
 * @package	ct_sulayr_theme
 */
class GeneralSettings extends SettingsPage
{
    const MENU_SLUG = 'ct_sulayr_theme-settings';
    protected string $position = '78.001';
    protected string $capability = 'edit_others_posts';

    public function __construct()
    {
        $this->page_title = __( 'CT Sulayr Theme Settings', CT_SULAYR_THEME_TEXTDOMAIN );
        $this->menu_title = __( 'CT Sulayr Theme Settings', CT_SULAYR_THEME_TEXTDOMAIN );

        parent::__construct();
    }
}

