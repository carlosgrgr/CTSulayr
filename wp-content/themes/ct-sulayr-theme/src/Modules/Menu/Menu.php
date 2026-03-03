<?php
namespace CTSulayrTheme\Modules\Menu;

/**
 * Menu
 *
 * @package	ct_sulayr_theme
 */
class Menu extends \Walker_Nav_Menu
{
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
    }

    /**
     * start_lvl()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     * @param string   $output
     * @param int      $depth
     * @param stdClass $args
     */
    public function start_lvl( &$output, $depth = 0, $args = [] ): void
    {
        $indent = str_repeat( "\t", $depth );
        $output .= "\n$indent<ul class='submenu dropdown-menu'><div class='dropdown-container'>\n";
    }

    /**
     * end_lvl()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     * @param string   $output
     * @param int      $depth
     * @param stdClass $args
     */
    public function end_lvl( &$output, $depth = 0, $args = [] )
    {
        $indent = str_repeat( "\t", $depth );
        $output .= "$indent</div></ul>\n";
    }
    
    /**
     * start_el()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     * @param string   $output
     * @param WP_Post  $item
     * @param int      $depth
     * @param stdClass $args
     * @param int      $id
     */
    public function start_el( &$output, $item, $depth = 0, $args = [], $id = 0 ): void
    {
        $indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';
    
        $classes = empty( $item->classes ) ? [] : (array) $item->classes;
        $classes[] = $depth <= 0 ? 'nav-item' : 'dropdown-item';
        $classes[] = ( $args->walker->has_children ) ? 'dropdown' : '';
        $classes[] = ( in_array( 'current-menu-item', $classes ) ) ? 'active' : '';
        $classes[] = 'menu-item-' . $item->ID;
    
        $class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args ) );
        $class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';
        
        $id = apply_filters( 'nav_menu_item_id', 'menu-item-'. $item->ID, $item, $args );
        $id = $id ? ' id="' . esc_attr( $id ) . '"' : '';
    
        $output .= $indent . '<li' . $id . $class_names .'>';
    
        $atts = apply_filters( 'nav_menu_link_attributes', [], $item, $args );
    
        $attributes = '';
        foreach ( $atts as $attr => $value ) {
            if ( ! empty( $value ) ) {
                $value = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
                $attributes .= ' ' . $attr . '="' . $value . '"';
            }
        }
    
        $item_output = $args->before;
        $item_output .= '<a class="' . ( $depth <= 0 ? 'nav-link' : '' ) . '"' . ( $depth <= 0 && $args->walker->has_children ? 'data-bs-toggle="dropdown"' : '' );
        $item_output .= $attributes . '>';
        $item_output .= $args->link_before . apply_filters( 'the_title', $item->title, $item->ID ) . $args->link_after;
        $item_output .= ( $args->walker->has_children ) ? ' <img src="' . CT_SULAYR_THEME_ASSETS_DIR . '/images/Vector.svg" alt="dropdown icon" class="dropdown-icon"></a>' : '</a>';
        $item_output .= $args->after;
    
        $output .= apply_filters( 'walker_nav_menu_start_el', $item_output, $item, $depth, $args );
    }
    
    /**
     * end_el()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     * @param string   $output
     * @param WP_Post  $item
     * @param int      $depth
     * @param stdClass $args
     */
    public function end_el( &$output, $item, $depth = 0, $args = [] ) {
        $output .= "</li>\n";
    }
}
