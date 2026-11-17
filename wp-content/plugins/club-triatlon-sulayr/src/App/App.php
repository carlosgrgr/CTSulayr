<?php
namespace ClubTriatlonSulayr\App;

use ClubTriatlonSulayr\Services\UserService;
use ClubTriatlonSulayr\Services\HelperService;
use ClubTriatlonSulayr\Modules\Roles\AthleteRole;
use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Templates\TemplatesManager;
use ClubTriatlonSulayr\Modules\Fields\UserFields\UserFields;
use ClubTriatlonSulayr\Modules\Fields\WalletFields\WalletFields;
use ClubTriatlonSulayr\Modules\SettingsPages\SulayrSettingsPage;
use ClubTriatlonSulayr\Modules\Users\UserAdminList;
use ClubTriatlonSulayr\Modules\Emails\EmailConfig;
use ClubTriatlonSulayr\Modules\Users\AthleteAdminProfile;
use ClubTriatlonSulayr\Modules\WooCommerce\WooCommerceIntegration;
use ClubTriatlonSulayr\Modules\WooCommerce\PrintNameField;
use ClubTriatlonSulayr\Modules\WooCommerce\OrdersCsvExport;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberFields;
use ClubTriatlonSulayr\Modules\Members\MemberTypes;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Admin\MembersMenu;
use ClubTriatlonSulayr\Modules\Members\Admin\ManagerAccess;
use ClubTriatlonSulayr\Modules\Members\Admin\RequestsPage;
use ClubTriatlonSulayr\Modules\Members\Join\PublicJoinForm;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\MembersDailyTask;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\LegacyFieldsHidden;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\FeesAccount;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;
use ClubTriatlonSulayr\Modules\Members\Join\ImageConsentAccount;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServicePostType;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceFields;
use ClubTriatlonSulayr\Modules\Wallet\WalletAdminList;
use ClubTriatlonSulayr\Modules\Wallet\WalletBalancePage;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;
use ClubTriatlonSulayr\Modules\Documents\DocumentCapabilities;
use ClubTriatlonSulayr\Modules\Documents\DocumentStorage;
use ClubTriatlonSulayr\Modules\Fields\DocumentFields\DocumentFields;
use ClubTriatlonSulayr\Modules\Documents\DocumentsAccountEndpoint;
use ClubTriatlonSulayr\Services\DocumentService;
use ClubTriatlonSulayr\Modules\Taxonomies\DocumentCategoryTaxonomy;
use ClubTriatlonSulayr\Modules\Documents\DocumentAdminList;
use ClubTriatlonSulayr\Modules\Ranking\Database\RankingSchema;
use ClubTriatlonSulayr\Modules\Ranking\RankingSettings;
use ClubTriatlonSulayr\Modules\Ranking\RankingService;
use ClubTriatlonSulayr\Modules\Ranking\Admin\RankingMenu;
use ClubTriatlonSulayr\Modules\Ranking\Front\RankingAccount;
use ClubTriatlonSulayr\Modules\Ranking\Front\RankingShortcode;

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
        $helpers = new HelperService();
        $user_fields = new UserFields();
        new WalletPostType( [] );
        $wallet_fields = new WalletFields();
        new DocumentPostType();
        new DocumentCategoryTaxonomy();
        new DocumentCapabilities();
        new DocumentFields();
        new DocumentStorage();
        $userService = new UserService();
        $userTemplate = new TemplatesManager();
        $athlete_role = new AthleteRole();
        $sulayr_settings = new SulayrSettingsPage( $userService );
        $user_admin_list = new UserAdminList( $helpers );
        if ( is_admin() ) {
            new WalletAdminList();
            new WalletBalancePage();
            new DocumentAdminList( new DocumentService() );
        }
        $email_config = new EmailConfig();
        $athlete_admin_profile = new AthleteAdminProfile();

        $this->load_members();
        $this->load_ranking();

        if ( class_exists( 'WooCommerce' ) ) {
            new DocumentsAccountEndpoint( new DocumentService() );
            new WooCommerceIntegration();
            new PrintNameField();
            if ( is_admin() ) {
                new OrdersCsvExport();
            }
        }
    }

    /**
     * Socios: alta y ciclo de vida (docs/club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md).
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function load_members(): void {
        new MembersSchema();
        new MemberLog();
        new MemberRoles();
        new MemberSettings();
        new MemberFields();
        new ServicePostType();
        new ServiceFields();
        new LegacyFieldsHidden();
        new ManagerAccess();
        new MembersDailyTask();
        new FeesAccount();
        new ReceiptsAccount();
        new MemberTypes();
        new Receipts();
        // Pago online (SPEC-COBROS): la URL de notificación de Redsys va por la API REST.
        new \ClubTriatlonSulayr\Modules\Payments\CardPayments();

        if ( is_admin() ) {
            new MembersMenu();
            new RequestsPage();
        } else {
            new PublicJoinForm();
            new ImageConsentAccount();
            new \ClubTriatlonSulayr\Modules\Payments\CardAccount();
        }
    }

    /**
     * Ranking interno (docs/club/SPEC-RANKING-INTERNO.md).
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function load_ranking(): void {
        new RankingSchema();
        new RankingSettings();
        new RankingService();

        if ( is_admin() ) {
            new RankingMenu();
        } else {
            new RankingShortcode();
        }

        if ( class_exists( 'WooCommerce' ) ) {
            new RankingAccount();
        }
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
        wp_enqueue_style( 'bootstrap-css', SULAYR_PUBLIC_ASSETS . '/bootstrap/css/bootstrap.min.css', [], '5.3.8' );
        wp_enqueue_style( 'club-triatlon-sulayr-front', SULAYR_PUBLIC_ASSETS . '/css/styles.css', [ 'bootstrap-css', 'woocommerce-general' ], self::asset_version( 'public/css/styles.css' ) );
        
        wp_enqueue_script( 'bootstrap-js', SULAYR_PUBLIC_ASSETS . '/bootstrap/js/bootstrap.bundle.min.js', [], '5.3.8', true );
        wp_enqueue_script( 'club-triatlon-sulayr-front', SULAYR_PUBLIC_ASSETS . '/js/scripts.js', [], self::asset_version( 'public/js/scripts.js' ), true );
    }

    /**
     * Versión de un archivo estático: la del plugin más la fecha del archivo.
     * El servidor cachea CSS y JS siete días; así, cada vez que se sube un archivo
     * cambia su URL y los navegadores dejan de usar la copia antigua.
     *
     * @param   string $relative Ruta dentro del plugin, p. ej. public/css/styles.css.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function asset_version( string $relative ): string {
        $file = SULAYR_DIR . $relative;

        return is_readable( $file ) ? SULAYR_VERSION . '.' . filemtime( $file ) : SULAYR_VERSION;
    }

    /**
     * load_admin_assets()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function load_admin_assets(): void {
        wp_enqueue_script( 'club-triatlon-sulayr-admin', SULAYR_ADMIN_ASSETS . '/js/scripts.js', [], self::asset_version( 'admin/js/scripts.js' ), true );
        wp_enqueue_style( 'club-triatlon-sulayr-admin', SULAYR_ADMIN_ASSETS . '/css/styles.css', [], self::asset_version( 'admin/css/styles.css' ) );
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
