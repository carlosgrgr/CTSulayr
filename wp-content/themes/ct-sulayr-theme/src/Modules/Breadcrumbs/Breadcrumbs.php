<?php
namespace CTSulayrTheme\Modules\Breadcrumbs;

use CTSulayrTheme\Infrastructure\CustomActions;
use CTSulayrTheme\Infrastructure\CustomFields;

/**
 * Breadcrumb
 *
 * @package	ct_sulayr_theme
 */
class Breadcrumbs
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
     * init()
     *
     * @return 	void
     * @access 	private
     * @package	ct_sulayr_theme
     */
    private function init(): void
    {
        add_filter( 'wpseo_breadcrumb_links', [ $this, 'yoast_seo_breadcrumb_append_link' ] );
        add_filter( CustomActions::CT_SULAYR_THEME_PAGE_AFTER_HEADER, [ $this, 'print_breadcrumbs' ] );
        add_filter( CustomActions::CT_SULAYR_THEME_POST_AFTER_HEADER, [ $this, 'print_breadcrumbs' ] );
        add_filter( CustomActions::CT_SULAYR_THEME_TAX_FAQ_CATEGORY_BEFORE_TITLE, [ $this, 'print_breadcrumbs' ] );
        add_filter( CustomActions::CT_SULAYR_THEME_SEARCH_BEFORE_TITLE, [ $this, 'print_breadcrumbs' ] );
        add_filter( CustomActions::CT_SULAYR_THEME_SINGLE_FAQ_BEFORE_TITLE, [ $this, 'print_breadcrumbs' ] );
    }

    /**
     * yoast_seo_breadcrumb_append_link()
     *
     * @param   array   $links
     * @return 	array
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function yoast_seo_breadcrumb_append_link( array $links ): array
    {    
        if ( true === is_tax( [ 'faq_category' ] ) || is_singular( 'faq' ) || is_search() ) {
            $help_center_page = get_field( CustomFields::SEARCH_SETTINGS_HELP_CENTER_PAGE, 'option' );
            $breadcrumb[] = array(
                'url' => isset( $help_center_page ) ? get_permalink( $help_center_page ) : '/help-center/',
                'text' => isset( $help_center_page ) ? get_the_title( $help_center_page ) : 'Help center'
            );

            array_splice( $links, 1, -2, $breadcrumb );
            
            if ( is_search() ) {
                end($links);
                $key = key($links);
                $links[$key]['text'] = __( 'Search results', CT_SULAYR_THEME_TEXTDOMAIN );
            }
        }

        return $links;
    }

    /**
     * print_breadcrumbs()
     * 
     * Print breadcrumbs in content.
     * 
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme 
     */
    public function print_breadcrumbs() : void
    {
        $hide_breadcrumbs = true === is_home() ||
                            true === is_front_page() ||
                            true === wp_is_mobile() || 
                            true === is_search() ||
                            false === function_exists( 'yoast_breadcrumb' );

        if ( true === $hide_breadcrumbs ) {
            return;
        }

        $allowed_post_types = [ 'post', 'faq' ];
        $post_type = get_post_type();

        if ( false === in_array( $post_type, $allowed_post_types ) && false === has_post_parent() && false === is_search() ) {
            return;
        }

        $post_parent = wp_get_post_parent_id() ?: 0;
        $home_page = (int)get_option( 'page_on_front' );

        if ( $post_parent === $home_page ) {
            return;
        }
        
        get_template_part( 'templates/parts/content', 'breadcrumbs' );
    }
}
