<?php
namespace ClubTriatlonSulayr\App;

use ClubTriatlonSulayr\Services\UserService;
use ClubTriatlonSulayr\Services\HelpersService;
use ClubTriatlonSulayr\Modules\Roles\AthleteRole;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Templates\TemplatesManager;
use ClubTriatlonSulayr\Modules\Fields\UserFields\UserFields;
use ClubTriatlonSulayr\Modules\Users\Unsubscription;

/**
 * App
 */
class App {
    private WpDependencies $wp_dependencies;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        $this->load_dependencies();

        if ( false === $this->wp_dependencies->check_dependencies() ) {
            add_action( 'admin_notices', [ $this, 'render_dependencies_not_found_notice' ] );
            return;
        }
        
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
        add_action( 'plugins_loaded', [ $this, 'load_classes' ] );
        add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'load_public_assets' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'load_admin_assets' ] );
    }

    /**
     * load_dependencies()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_dependencies(): void {
        $this->wp_dependencies = new WpDependencies();
    }

    /**
     * load_classes()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_classes(): void {
        $helpers = new HelpersService();
        $user_fields = new UserFields();
        $userService = new UserService();
        $userTemplate = new TemplatesManager();
        $athlete_role = new AthleteRole();
        $user_unsubscription = new Unsubscription( $userService );
    }

    /**
     * load_textdomain()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_textdomain(): void {
        load_plugin_textdomain( SULAYR_TEXTDOMAIN, false, SULAYR_LANG_DIR );
    }

    /**
     * load_public_assets()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_public_assets(): void {
        wp_enqueue_script( 'club-triatlon-sulayr-front', SULAYR_PUBLIC_ASSETS . '/js/scripts.js', [], SULAYR_VERSION, true );
        wp_enqueue_style( 'club-triatlon-sulayr-front', SULAYR_PUBLIC_ASSETS . '/css/styles.css', [], SULAYR_VERSION );
    }

    /**
     * load_admin_assets()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_admin_assets(): void {
        wp_enqueue_script( 'club-triatlon-sulayr-admin', SULAYR_ADMIN_ASSETS . '/js/scripts.js', [], SULAYR_VERSION, true );
        wp_enqueue_style( 'club-triatlon-sulayr-admin', SULAYR_ADMIN_ASSETS . '/css/styles.css', [], SULAYR_VERSION );
    }

    /**
     * render_dependencies_not_found_notice()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_dependencies_not_found_notice(): void {
        $dependencies = WpDependencies::DEPENDENCIES;

        ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo sprintf( __( 'In order to activate the <b>%s</b> plugin, you have to meet the next requirements:', SULAYR_TEXTDOMAIN ), SULAYR_NAME ); ?></p>
            <ul>
                <li><?php echo sprintf( __( 'PHP version: %s', SULAYR_TEXTDOMAIN ), WpDependencies::MIN_PHP_VERSION ); ?></li>
                <li><?php echo sprintf( __( 'WordPress version: %s', SULAYR_TEXTDOMAIN ), WpDependencies::MIN_WP_VERSION ); ?></li>
                <?php foreach( $dependencies as $name => $plugin ): ?>
                    <li><?php echo sprintf( __( 'Activate plugin: %s', SULAYR_TEXTDOMAIN ), $name ); ?></li>
                <?php endforeach ?>
            </ul>
        </div>
        <?php
    }
}
