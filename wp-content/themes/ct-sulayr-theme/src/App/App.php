<?php
namespace CTSulayrTheme\App;

use CTSulayrTheme\Services\SecurityService;
use CTSulayrTheme\Modules\Blocks\Blocks;
use CTSulayrTheme\Services\HelperService;
use CTSulayrTheme\Services\CommentsService;
use CTSulayrTheme\Infrastructure\CustomBlocks;
use CTSulayrTheme\Infrastructure\CustomFields;
use CTSulayrTheme\Modules\Menu\Menu;
use CTSulayrTheme\Modules\Settings\GeneralSettings;
use CTSulayrTheme\Modules\Fields\GeneralSettingsFields;
use CTSulayrTheme\Modules\Fields\HeroFields;
use CTSulayrTheme\Modules\BlockCategories\BlockCategories;
use CTSulayrTheme\Modules\Breadcrumbs\Breadcrumbs;

class App
{
    private array $styles;
    private array $scripts;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct()
    {
        $this->styles = [
            'common' => [
                'ct_sulayr_theme-bootstrap' => [
                    'path' => 'assets/css/bootstrap.min.css',
                    'deps' => [],
                    'media' => 'all',
                ],
                'ct_sulayr_theme-bootstrap-icons' => [
                    'path' => 'assets/css/bootstrap-icons.min.css',
                    'deps' => [ 'ct_sulayr_theme-bootstrap' ],
                    'media' => 'all',
                ],
                'ct_sulayr_theme' => [
                    'path' => 'assets/css/styles.min.css',
                    'deps' => [ 'ct_sulayr_theme-bootstrap-icons' ],
                    'media' => 'all',
                ],
            ],
            'admin' => [
                'ct_sulayr_theme_admin' => [
                    'path' => 'assets/css/styles_admin.css',
                    'deps' => [],
                    'media' => 'all',
                ],
            ]
        ];

        $this->scripts = [
            'common' => [
                'ct_sulayr_theme' => [
                    'path' => 'assets/js/functions.js',
                    'deps' => [ 'jquery' ],
                    'in_footer' => true,
                    'localize' => [
                        'object_name' => 'wpArgs',
                        'args' => [
                            'ajaxurl' => admin_url( 'admin-ajax.php' ),
                        ],
                    ],
                ],
                'ct_sulayr_theme-bootstrap-bundle' => [
                    'path' => 'assets/js/bootstrap.bundle.min.js',
                    'deps' => [ 'ct_sulayr_theme' ],
                    'in_footer' => true,
                ],
                'ct_sulayr_theme_ui_core' => [
                    'path' => 'assets/js/core.min.js',
                    'deps' => [ 'jquery' ],
                    'in_footer' => true,
                ],
                'ct_sulayr_theme_ui_script' => [
                    'path' => 'assets/js/script.js',
                    'deps' => [ 'ct_sulayr_theme_ui_core' ],
                    'in_footer' => true,
                ],
            ],
            'front' => [],
            'admin' => [
                'scripts-admin' => [
                    'path' => 'assets/js/scripts_admin.js',
                    'deps' => [],
                    'in_footer' => true,
                ],
            ]
        ];

        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function init(): void
    {
        add_action( 'after_setup_theme', [ $this, 'load_theme_textdomain' ] );
        add_action( 'after_setup_theme', [ $this, 'load_theme_supports' ] );
        add_action( 'after_setup_theme', [ $this, 'load_theme_menus' ] );
        add_action( 'after_setup_theme', [ $this, 'load_classes' ] );
        
        add_action( 'wp_enqueue_scripts', [ $this, 'load_scripts' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'register_styles' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'load_styles' ] );
        
        add_filter( 'allowed_block_types_all', [ $this, 'disable_wp_blocks' ], 10, 2 );
        add_action( 'acf/init', [ $this, 'load_settings' ] );
        add_filter( 'get_custom_logo', [ $this, 'edit_custom_logo_html' ] );
        add_filter( 'nav_menu_link_attributes', [ $this, 'nav_menu_attrs' ], 10, 3 );
        add_filter( 'wp_footer', [ $this, 'footer_hook' ] );
    }

    /**
     * load_textdomain()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_theme_textdomain(): void
    {
        load_theme_textdomain( 'ct_sulayr_theme', CT_SULAYR_THEME_LANG_DIR );
    }

    /**
     * load_theme_supports()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_theme_supports(): void
    {
        add_theme_support( 'post-thumbnails' );
        add_theme_support( 'title-tag' );

        add_theme_support( 'html5', [ 'search-form',  'comment-form', 'comment-list', 'gallery', 'caption' ] );
        add_theme_support( 'post-formats', [ 'aside', 'image', 'video', 'quote', 'link', 'gallery', 'status', 'audio', 'chat' ] );

        add_theme_support( 'custom-logo', [
            'width' => '64',
            'height' => '64',
            'flex-height' => true,
            'flex-width' => true,
            'header-text' => [ 'site-title', 'site-description' ],
         ] );
    }

    /**
     * load_theme_menus()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_theme_menus(): void
    {
        register_nav_menus( [
            'header-menu' => __( 'Header Menu', 'ct_sulayr_theme' ),
            'footer_column_1' => __( 'Footer Column 1', 'ct_sulayr_theme'),
            'footer_column_2' => __( 'Footer Column 2', 'ct_sulayr_theme'),
        ] );
    }
    
    /**
     * load_scripts()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_scripts(): void
    {
        if ( ! is_admin() ) {
            // Force jQuery to header for maximum compatibility (Safety Strategy)
            wp_deregister_script( 'jquery' );
            wp_register_script( 'jquery', includes_url( '/js/jquery/jquery.min.js' ), false, NULL, false );
            wp_enqueue_script( 'jquery' );
        }

        foreach( $this->scripts[ 'common' ] as $handle => $script ) {
            $src = get_theme_file_uri( $script['path'] );
            wp_enqueue_script( $handle, $src, $script['deps'], CT_SULAYR_THEME_VERSION, $script['in_footer'] );

            if ( false !== isset( $script['localize'] ) && false === empty( $script['localize'] ) ) {
                wp_localize_script( $handle, $script['localize']['object_name'], $script['localize']['args'] );
            }
        }
        foreach( $this->scripts[ 'front' ] as $handle => $script ) {
            $src = get_theme_file_uri( $script['path'] );
            wp_enqueue_script( $handle, $src, $script['deps'], CT_SULAYR_THEME_VERSION, $script['in_footer'] );

            if ( false !== isset( $script['localize'] ) && false === empty( $script['localize'] ) ) {
                wp_localize_script( $handle, $script['localize']['object_name'], $script['localize']['args'] );
            }
        }
    }

    /**
     * load_styles()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_styles(): void
    {
        foreach( $this->styles[ 'common' ] as $handle => $style ) {
            $href = get_theme_file_uri( $style['path'] );
            wp_enqueue_style( $handle, $href, $style['deps'], CT_SULAYR_THEME_VERSION, $style['media'] );
        }
        foreach( $this->styles[ 'front' ] as $handle => $style ) {
            $href = get_theme_file_uri( $style['path'] );
            wp_enqueue_style( $handle, $href, $style['deps'], CT_SULAYR_THEME_VERSION, $style['media'] );
        }
    }

    /**
     * register_styles()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function register_styles(): void
    {
        foreach( $this->styles[ 'common' ] as $handle => $style ) {
            $href = get_theme_file_uri( $style['path'] );
            wp_register_style( $handle, $href, $style['deps'], CT_SULAYR_THEME_VERSION, $style['media'] );
        }
        foreach( $this->styles[ 'admin' ] as $handle => $style ) {
            $href = get_theme_file_uri( $style['path'] );
            wp_register_style( $handle, $href, $style['deps'], CT_SULAYR_THEME_VERSION, $style['media'] );
        }

        foreach( $this->scripts[ 'common' ] as $handle => $script ) {
            $src = get_theme_file_uri( $script['path'] );
            wp_register_script( $handle, $src, $script['deps'], CT_SULAYR_THEME_VERSION, false );

            if ( false !== isset( $script['localize'] ) && false === empty( $script['localize'] ) ) {
                wp_localize_script( $handle, $script['localize']['object_name'], $script['localize']['args'] );
            }
        }
        foreach( $this->scripts[ 'admin' ] as $handle => $script ) {
            $src = get_theme_file_uri( $script['path'] );
            wp_register_script( $handle, $src, $script['deps'], CT_SULAYR_THEME_VERSION, false );

            if ( false !== isset( $script['localize'] ) && false === empty( $script['localize'] ) ) {
                wp_localize_script( $handle, $script['localize']['object_name'], $script['localize']['args'] );
            }
        }
    }

    /**
     * load_classes()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_classes(): void
    {
        $helper_service = new HelperService();
        $menu             = new Menu();
        $breadcrumbs      = new Breadcrumbs();

        $blocks = [
            CustomBlocks::HERO_BLOCK_NAME => CustomBlocks::HERO_BLOCK_DIR,
        ];
        foreach ( $blocks as $block_name => $block_dir ) {
            new Blocks( $block_dir );
        }

        new GeneralSettingsFields();
        new HeroFields();
    }

    /**
     * disable_wp_blocks()
     *
     * @param   $allowed_block_types
     * @param   $editor_context
     * @return 	array block types allowed
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function disable_wp_blocks( $allowed_block_types, $editor_context ): array
    {
        $allowed = [
            CustomBlocks::HERO_BLOCK_SLUG,
            'core/paragraph',
            'core/heading',
            'core/image',
            'core/list',
            'core/buttons',
            'core/group',
            'core/columns',
        ];
        return $allowed;
    }
    
    /**
     * load_settings()
     *
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function load_settings(): void
    {
        new GeneralSettings();
    }

    /**
     * edit_custom_logo_html()
     *
     * @return 	string
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function edit_custom_logo_html(): string
    {
        $custom_logo_id = get_theme_mod( 'custom_logo' );
        $aria_label = sprintf( '%s Logo', get_bloginfo( 'name' ) );

        $html = sprintf( '<a href="%1$s" class="navbar-brand" aria-label="%3$s">%2$s</a>',
            esc_url( home_url( '/' ) ),
            wp_get_attachment_image( $custom_logo_id, 'full', false, [] ),
            $aria_label
        );

        return $html;  
    }

    /**
     * nav_menu_attrs()
     *
     * @param   array     $atts
     * @param   \WP_Post  $item
     * @param   \stdClass $args
     * @return 	array
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function nav_menu_attrs( array $atts, \WP_Post $item, \stdClass $args ): array
    {
        $atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
        $atts['target'] = ! empty( $item->target )     ? $item->target     : '';
        $atts['rel']    = ! empty( $item->xfn )        ? $item->xfn        : '';
        $atts['href']   = ! empty( $item->url )        ? $item->url        : '';
        $atts['aria-label'] = $item->title;

        return $atts; 
    }

}
