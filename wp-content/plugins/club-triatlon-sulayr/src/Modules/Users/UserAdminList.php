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
        $columns[CustomFields::USER_PROFILE_MEMBERSHIP_PLAN] = __( 'Tarifa', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_TRAINING_TYPE]   = __( 'Tipo entrenamiento', SULAYR_TEXTDOMAIN );
        $columns[CustomFields::USER_PROFILE_SWIMMING]        = __( 'Natación', SULAYR_TEXTDOMAIN );
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
        return $value;
    }


}