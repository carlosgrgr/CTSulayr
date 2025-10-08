<?php
namespace ClubTriatlonSulayr\Modules\Endpoints;

/**
 * Endpoint
 */
abstract class Endpoint {
    protected string $namespace = 'v1/club-triatlon-sulayr';
    protected string $method = 'GET';

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
        add_action( 'rest_api_init', [ $this, 'create_rest_route' ] );
    }

    /**
     * create_rest_route()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function create_rest_route(): void {
        register_rest_route( $this->namespace, $this::ROUTE, [
            'methods' => $this->method,
            'callback' => [ $this, 'controller' ],
            'permission_callback' => [ $this, 'permission_callback' ],
        ] );
    }

    /**
     * controller()
     *
     * @return 	\WP_REST_Response
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function controller(): \WP_REST_Response {
        return new \WP_REST_Response( [
            'code' => 'custom_response',
            'message' => __( 'Message of custom response', SULAYR_TEXTDOMAIN ),
            'data' => [],
        ], 200 );
    }

    /**
     * permission_callback()
     *
     * @return 	boolean
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function permission_callback(): bool {
        return true;
    }
}
