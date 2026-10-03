<?php
namespace ClubTriatlonSulayr\Modules\Fields;

/**
 * Fields
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
     * @package	newsroom
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function init(): void
    {
        $this->create_group_fields();
    }

    /**
     * create_group_fields()
     *
     * @return 	void
     * @access 	public
     * @package	newsroom
     */
    public function create_group_fields(): void
    {
        acf_add_local_field_group( $this->fields );
    }
}
