<?php
namespace ClubTriatlonSulayr\Modules\RestFields;

/**
 * RestField
 */
abstract class RestField {
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
        add_action( 'rest_api_init', [ $this, 'add_new_rest_field' ] );
    }

    /**
     * add_new_rest_field()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_new_rest_field(): void {
        $args = [
            'get_callback' => [ $this, 'get_rest_field_value' ],
        ];
    
        register_rest_field( $this::OBJECT_TYPES, 'custom-rest-field', $args );
    }

    /**
     * get_rest_field_value()
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function get_rest_field_value(): string {
        return 'Some value';
    }
}
