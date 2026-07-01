<?php
namespace ClubTriatlonSulayr\Modules\Users;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Services\HelperService;

/**
 * UserAdminList
 */
class UserAdminList
{
    private HelperService $helper_service;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( HelperService $helper_service )
    {
        $this->helper_service = $helper_service;

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
        add_filter( 'manage_users_columns', [ $this, 'add_columns_to_user_list' ] );
        add_filter( 'manage_users_custom_column', [ $this, 'add_data_to_column_user_list' ], 10, 3 );
        add_filter( 'user_search_columns', [ $this, 'add_search_columns_to_user_list' ], 10, 1 );
        add_filter( 'manage_users_sortable_columns', [ $this, 'make_column_sortable' ] );
        add_action( 'pre_get_users', [ $this, 'handle_sorting' ] );
        add_action( 'admin_head-users.php', [ $this, 'add_column_css' ] );
    }

    /**
     * add_search_columns_to_user_list()
     *
     * @param   array  $search_columns
     * @return 	array  $search_columns  
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_search_columns_to_user_list( array $search_columns ): array
    {
        if ( ! in_array( 'display_name', $search_columns, true ) ) {
            $search_columns[] = 'display_name';
        }
        
        if ( ! in_array( 'user_login', $search_columns, true ) ) {
            $search_columns[] = 'user_login';
        }
    
        return $search_columns;
    }

    /**
     * add_columns_to_user_list()
     * 
     * @param   array  $columns
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_columns_to_user_list( $columns ): array
    {
        $columns['user_id'] = 'ID';
        $columns[CustomFields::USER_PROFILE_ATHLETE_STATUS] = __( 'Estado del deportista', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_MEMBERSHIP_PLAN] = __( 'Tarifa', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_TRAINING_TYPE]   = __( 'Tipo entrenamiento', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_SWIMMING]        = __( 'Natación', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_IS_DIRECTIVE]    = __( 'Directiva', SULAYR_TEXTDOMAIN );
        return $columns;
    }

    /**
     * add_data_to_column_user_list()
     * 
     * @param   string  $value
     * @param   string  $column_name
     * @param   int     $user_id
     * @return 	array
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_data_to_column_user_list( $value, $column_name, $user_id ): string
    {
        if ( $column_name === 'user_id' ) {
            return esc_html( (string) $user_id );
        }
        if ( $column_name === CustomFields::USER_PROFILE_ATHLETE_STATUS ) {
            $status = HelperService::get_user_field( CustomFields::USER_PROFILE_ATHLETE_STATUS, $user_id );
            if ( empty( $status ) && $status !== '0' && $status !== 0 ) {
                $status = 'active';
            }
            return HelperService::format_athlete_status( $status );
        }
        if ( $column_name === CustomFields::USER_PROFILE_MEMBERSHIP_PLAN ) {
            $plan = HelperService::get_user_field( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, $user_id );
            return HelperService::format_membership_plan( $plan );
        }
        if ( $column_name === CustomFields::USER_PROFILE_TRAINING_TYPE ) {
            $training_type = HelperService::get_user_field( CustomFields::USER_PROFILE_TRAINING_TYPE, $user_id );
            return HelperService::format_training_type( $training_type );
        }
        if ( $column_name === CustomFields::USER_PROFILE_SWIMMING ) {
            $swimming = HelperService::get_user_field( CustomFields::USER_PROFILE_SWIMMING, $user_id );
            return HelperService::format_swimming_supplement_to_label( $swimming );
        }
        if ( $column_name === CustomFields::USER_PROFILE_IS_DIRECTIVE ) {
            $is_directive = (bool) HelperService::get_user_field( CustomFields::USER_PROFILE_IS_DIRECTIVE, $user_id );
            return $is_directive ? __( 'Sí', SULAYR_TEXTDOMAIN ) : '—';
        }
        return $value;
    }

    /**
     * Make ID column sortable
     */
    public function make_column_sortable( $columns ) {
        $columns['user_id'] = 'ID';
        return $columns;
    }

    /**
     * Fix query to order by User ID
     */
    public function handle_sorting( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( $query->get( 'orderby' ) === 'ID' ) {
            $query->set( 'orderby', 'ID' );
        }
    }

    /**
     * CSS inline for ID column width
     */
    public function add_column_css() {
        ?>
        <style>
            .wp-list-table .column-user_id {
                width: 5%;
                text-align: center;
                font-weight: 600;
            }
        </style>
        <?php
    }

}