<?php
namespace CTSulayrTheme\Modules\Blocks;

/**
 * Blocks
 *
 * @package	ct_sulayr_theme
 */
class Blocks
{  
    private string $block_dir;

    /**
     * __construct()
     *
     * @param string $block_dir
     * @return 	void
     * @access 	public
     * @package	ct_sulayr_theme
     */
    public function __construct( string $block_dir )
    {
        $this->block_dir = $block_dir;

        $this->init();
    }

    /**
     * init()
     *
     *
     * @return 	void
     * @access 	public
     * @version	1.0
     * @since  	1.0
     * @package	ct_sulayr_theme
     */
    public function init(): void
    {
        add_action( 'init', [ $this, 'add_custom_blocks' ] );
    }

    /**
     * add_custom_block()
     *
     * This method is responsible for registering the new block for Gutenberg through the register_block_type() function. Since ACF v6
     *
     * @return 	void
     * @access 	public
     * @version	1.0
     * @since  	1.0
     * @package	ct_sulayr_theme
     */
    public function add_custom_blocks(): void
    {
        if ( false === function_exists( 'register_block_type' ) ) {
            return;
        }
        register_block_type( $this->block_dir . '/block.json' );
    }
}
