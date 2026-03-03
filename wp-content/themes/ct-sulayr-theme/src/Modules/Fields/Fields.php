<?php
namespace CTSulayrTheme\Modules\Fields;

/**
 * Fields
 *
 * @package	ct_sulayr_theme
 */
abstract class Fields
{
    protected array $fields = [];

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
        $this->create_group_fields();
    }

    /**
     * create_group_fields()
     *
     *
     * @return 	void
     * @access 	public
     * @version	1.0
     * @since  	1.0
     * @package	ct_sulayr_theme
     */
    public function create_group_fields(): void
    {
        acf_add_local_field_group( $this->fields );
    }
}
