<?php
namespace ClubTriatlonSulayr\Modules\Roles;

/**
 * Role
 */
abstract class Role {

    const ROLE_NAME = '';

    protected array $capabilities = [];

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
        add_action( 'init', [ $this, 'add_new_role' ] );
    }
    
    /**
     * add_new_role()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_new_role(): void {
        add_role(
            $this::ROLE_NAME,
            __( $this->display_name ?? 'Custom role', SULAYR_TEXTDOMAIN ),
            $this->capabilities
        );
    }
}
