<?php
namespace ClubTriatlonSulayr\Modules\SettingsPages;

/**
 * SettingsPage
 */
abstract class SettingsPage {
    protected string $page_title = '';
    protected string $menu_title = '';
    protected string $capability = 'manage_options';
    protected string $position = '2.9';
    protected string $icon_url = 'dashicons-paperclip';
    protected bool $redirect = true;
    protected string $update_button = '';
    protected string $updated_message = '';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function init(): void {
        $this->set_default_labels();
        add_action( 'acf/init', [ $this, 'create_settings_page' ] );
    }

    /**
     * set_default_labels()
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function set_default_labels(): void {
        if ( false !== empty( $this->page_title ) ) {
            $this->page_title = __( 'Settings', SULAYR_TEXTDOMAIN );
        }

        if ( false !== empty( $this->menu_title ) ) {
            $this->menu_title = __( 'Settings', SULAYR_TEXTDOMAIN );
        }

        if ( false !== empty( $this->update_button ) ) {
            $this->update_button = __( 'Save', SULAYR_TEXTDOMAIN );
        }

        if ( false !== empty( $this->updated_message ) ) {
            $this->updated_message = __( 'Settings have been saved successfully', SULAYR_TEXTDOMAIN );
        }
    }

    /**
     * create_settings_page()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function create_settings_page(): void {
        acf_add_options_page(
            [
                'page_title' => $this->page_title,
                'menu_title' => $this->menu_title,
                'menu_slug' => $this::MENU_SLUG,
                'capability' => $this->capability,
                'position' => $this->position,
                'icon_url' => $this->icon_url,
                'redirect' => $this->redirect,
                'update_button' => $this->update_button,
                'updated_message' => $this->updated_message,
            ]
        );
    }
}
