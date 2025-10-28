<?php
namespace ClubTriatlonSulayr\Modules\Users;

use ClubTriatlonSulayr\Services\UserService;

/**
 * Endpoint
 */
class Unsubscription
{
    private UserService $user_service;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( UserService $user_service )
    {
        $this->user_service = $user_service;

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
        add_action( 'sulayr_before_athlete_unsubscription', [ $this, 'send_unsubscription_notification' ] );
    }

    /**
     * send_unsubscription_notification()
     * 
     * @param   \WP_User  $user
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function send_unsubscription_notification( \WP_User $user ): void
    {
        $admin_email = get_option( 'admin_email' );
        $subject = 'Baja de usuario en el club';
        $body = "El usuario {$user->display_name} ({$user->user_email}) se ha dado de baja.\nUser ID: {$user->ID}";
        wp_mail( $admin_email, $subject, $body );
    }
}