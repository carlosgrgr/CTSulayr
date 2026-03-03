<?php
namespace CTSulayrTheme\Services;

/**
 * SecurityService
 *
 * @package	ct_sulayr_theme
 */
class SecurityService
{

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct()
    {        
        $this->init();
    }
    
    /**
     * init
     *
     * @return  void
     * @access 	private
     * @package	ct_sulayr_theme
     */
    private function init(): void
    {
        add_filter( 'the_generator', [ $this, 'remove_wp_version' ] );
    }

    /**
     * remove_wp_version
     *
     * @return  string WP version
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function remove_wp_version(): string
    {
        return '';
    }
}
