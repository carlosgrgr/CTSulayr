<?php
namespace ClubTriatlonSulayr\Modules\SettingsPages;

use ClubTriatlonSulayr\Services\UserService;
use ClubTriatlonSulayr\Services\CsvImporterService;
use ClubTriatlonSulayr\Modules\PostTypes\DocumentPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * SulayrSettingsPage
 * Página de opciones del plugin en el panel de administración
 */
class SulayrSettingsPage {
    const MENU_SLUG = 'sulayr-settings';
    const PAGE_SLUG = 'sulayr-settings';
    const MENU_ICON = 'dashicons-admin-settings';
    const MENU_POSITION = 58;
    
    private UserService $user_service;
    private CsvImporterService $csv_importer;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( UserService $user_service ) {
        $this->user_service = $user_service;
        $this->csv_importer = new CsvImporterService( $user_service );
        $this->init();
    }

    /**
     * init()
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function init(): void {
        add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
        add_filter( 'custom_menu_order', '__return_true' );
        add_filter( 'menu_order', [ $this, 'reorder_admin_menu' ], 99 );
        add_action( 'admin_init', [ $this, 'handle_list_requests' ] );
        add_action( 'admin_init', [ $this, 'register_stripe_settings' ] );
        add_action( 'admin_init', [ $this, 'handle_stripe_settings_save' ], 20 );
        add_action( 'admin_init', [ $this, 'handle_subscription_checkout_link' ], 20 );
        // Procesar importación antes que otras acciones para evitar conflictos
        add_action( 'admin_init', [ $this, 'handle_csv_import' ], 5 );
    }

    /**
     * Register Stripe API keys and webhook secret options.
     */
    public function register_stripe_settings(): void {
        register_setting( 'sulayr_stripe', 'sulayr_stripe_publishable_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'sulayr_stripe', 'sulayr_stripe_secret_key', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
        register_setting( 'sulayr_stripe', 'sulayr_stripe_webhook_secret', [
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ] );
    }

    /**
     * Handle saving Stripe settings form.
     */
    public function handle_stripe_settings_save(): void {
        if ( ! isset( $_POST['sulayr_stripe_save'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sulayr_stripe_settings' ) ) {
            wp_die( __( 'Acción no autorizada.', SULAYR_TEXTDOMAIN ) );
        }
        // Settings are saved via options.php or we save manually
        if ( isset( $_POST['sulayr_stripe_publishable_key'] ) ) {
            update_option( 'sulayr_stripe_publishable_key', sanitize_text_field( wp_unslash( $_POST['sulayr_stripe_publishable_key'] ) ) );
        }
        if ( isset( $_POST['sulayr_stripe_secret_key'] ) ) {
            update_option( 'sulayr_stripe_secret_key', sanitize_text_field( wp_unslash( $_POST['sulayr_stripe_secret_key'] ) ) );
        }
        if ( isset( $_POST['sulayr_stripe_webhook_secret'] ) ) {
            update_option( 'sulayr_stripe_webhook_secret', sanitize_text_field( wp_unslash( $_POST['sulayr_stripe_webhook_secret'] ) ) );
        }
        wp_safe_redirect( $this->get_page_url( [
            'action'  => 'stripe',
            'updated' => '1',
        ] ) );
        exit;
    }

    /**
     * add_settings_page()
     * Añade la página de opciones como ítem de menú principal
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_settings_page(): void {
        add_menu_page(
            __( 'Sulayr Info', SULAYR_TEXTDOMAIN ),
            __( 'Sulayr Info', SULAYR_TEXTDOMAIN ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_settings_page' ],
            self::MENU_ICON,
            self::MENU_POSITION
        );
    }

    /**
     * reorder_admin_menu()
     * Coloca "Sulayr Info" y "Documentos Sulayr" justo debajo de Escritorio,
     * antes de Entradas.
     *
     * @param array $menu_order Slugs del menú principal en orden.
     * @return array
     * @access public
     * @package club-triatlon-sulayr
     */
    public function reorder_admin_menu( $menu_order ): array {
        if ( ! is_array( $menu_order ) ) {
            return [];
        }

        $first = [ self::MENU_SLUG, 'edit.php?post_type=' . DocumentPostType::POST_TYPE_NAME ];
        $rest  = array_values( array_diff( $menu_order, $first ) );
        $first = array_values( array_intersect( $first, $menu_order ) );
        $index = array_search( 'index.php', $rest, true );
        $at    = false === $index ? 0 : $index + 1;

        array_splice( $rest, $at, 0, $first );

        return $rest;
    }

    /**
     * Genera la URL base de la página de administración del plugin.
     *
     * @param array $args Parámetros de query adicionales.
     * @return string
     */
    private function get_page_url( array $args = [] ): string {
        return add_query_arg(
            array_merge( [ 'page' => self::MENU_SLUG ], $args ),
            admin_url( 'admin.php' )
        );
    }

    /**
     * handle_list_requests()
     * Maneja las peticiones para mostrar listados
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_list_requests(): void {
        $page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

        if ( $page !== self::MENU_SLUG ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Verificar nonce para seguridad
        if ( isset( $_GET['action'] ) && isset( $_GET['_wpnonce'] ) ) {
            if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'sulayr_list_action' ) ) {
                wp_die( __( 'Acción no autorizada.', SULAYR_TEXTDOMAIN ) );
            }
        }
    }

    /**
     * render_settings_page()
     * Renderiza la página de opciones
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para acceder a esta página.', SULAYR_TEXTDOMAIN ) );
        }

        $action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : '';

        if ( empty( $action ) ) {
            $this->render_main_view();
        } else {
            $this->render_list_view( $action );
        }
    }

    /**
     * Renderiza la vista principal con botones de selección
     *
     * @return void
     * @access private
     */
    private function render_main_view(): void {
        $this->load_template( 'admin/main-view', [
            'list_by_plan_url'    => $this->get_list_url( 'by_plan' ),
            'list_swimmers_url'  => $this->get_list_url( 'swimmers' ),
            'list_by_status_url' => $this->get_list_url( 'by_status' ),
            'import_url'          => $this->get_list_url( 'import' ),
            'stripe_settings_url' => $this->get_list_url( 'stripe' ),
            'subscriptions_url'   => $this->get_list_url( 'suscripciones' ),
        ] );
    }

    /**
     * Renderiza la vista de listado según la acción
     *
     * @param string $action Acción a realizar
     * @return void
     * @access private
     */
    private function render_list_view( string $action ): void {
        ob_start();
        
        switch ( $action ) {
            case 'by_plan':
                $this->render_athletes_by_plan();
                break;
            case 'swimmers':
                $this->render_swimmers_list();
                break;
            case 'by_status':
                $this->render_athletes_by_status();
                break;
            case 'import':
                $this->render_import_view();
                break;
            case 'stripe':
                $this->render_stripe_settings();
                break;
            case 'suscripciones':
                $this->render_subscriptions_management();
                break;
            default:
                $this->render_error_message( __( 'Acción no válida.', SULAYR_TEXTDOMAIN ) );
        }
        
        $content = ob_get_clean();
        
        $this->load_template( 'admin/list-view', [
            'back_url' => $this->get_page_url(),
            'content' => $content,
        ] );
    }

    /**
     * Renderiza un mensaje de error
     *
     * @param string $message Mensaje de error
     * @return void
     * @access private
     */
    private function render_error_message( string $message ): void {
        $this->load_template( 'admin/error-message', [
            'message' => $message,
        ] );
    }

    /**
     * render_athletes_by_plan()
     * Renderiza el listado de deportistas ordenados por tarifa en formato lista numerada
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_athletes_by_plan(): void {
        $athletes = $this->user_service->get_athletes_by_plan();
        
        if ( empty( $athletes ) ) {
            $this->render_empty_athletes_message();
            return;
        }

        $grouped = $this->group_athletes_by_plan( $athletes );
        $current_month = $this->get_current_month_name();
        $total_count = count( $athletes );
        
        $this->load_template( 'admin/athletes-by-plan', [
            'grouped' => $grouped,
            'current_month' => $current_month,
            'total_count' => $total_count,
        ] );
    }

    /**
     * Agrupa los atletas por plan y tipo de entrenamiento
     *
     * @param array $athletes Lista de atletas
     * @return array Atletas agrupados por categoría
     * @access private
     */
    private function group_athletes_by_plan( array $athletes ): array {
        $grouped = [
            'basic' => [],
            'training_sprint' => [],
            'training_olimpic' => [],
            'other' => [],
        ];
        
        foreach ( $athletes as $athlete ) {
            $plan_value = $athlete['plan_value'] ?? '';
            $training_type = $athlete['training_type_value'] ?? '';
            
            $group_key = $this->determine_athlete_group( $plan_value, $training_type );
            $grouped[ $group_key ][] = $athlete;
        }
        
        // Ordenar alfabéticamente cada grupo por nombre
        foreach ( $grouped as $key => $group ) {
            usort( $group, function( $a, $b ) {
                return strcmp( $a['name'], $b['name'] );
            } );
            $grouped[ $key ] = $group;
        }
        
        return $grouped;
    }

    /**
     * Determina el grupo al que pertenece un atleta según su plan y tipo de entrenamiento
     *
     * @param string $plan_value Valor del plan
     * @param string $training_type Tipo de entrenamiento
     * @return string Clave del grupo
     * @access private
     */
    private function determine_athlete_group( string $plan_value, string $training_type ): string {
        $plan_lower = strtolower( $plan_value );
        $training_lower = strtolower( $training_type );
        
        // Si es premium/entrenamiento, agrupar por tipo
        if ( $plan_value === 'premium' || stripos( $plan_value, 'premium' ) !== false || stripos( $plan_value, 'entrenamiento' ) !== false ) {
            if ( $training_type === 'sprint' || stripos( $training_type, 'sprint' ) !== false ) {
                return 'training_sprint';
            }
            if ( $training_type === 'olimpic' || stripos( $training_type, 'olimpic' ) !== false || stripos( $training_type, 'olímpico' ) !== false ) {
                return 'training_olimpic';
            }
            // Si tiene plan premium pero no tipo, poner en otros
            return 'other';
        }
        
        if ( $plan_value === 'basic' || stripos( $plan_value, 'basic' ) !== false || stripos( $plan_value, 'básica' ) !== false ) {
            return 'basic';
        }
        
        return 'other';
    }

    /**
     * Renderiza un grupo de atletas
     *
     * @param string $group_key Clave del grupo
     * @param array  $athletes  Lista de atletas del grupo
     * @param string $title     Título del grupo
     * @param bool   $bold      Si el título debe estar en negrita
     * @return void
     * @access private
     */
    private function render_athletes_group( string $group_key, array $athletes, string $title, bool $bold = false ): void {
        if ( empty( $athletes ) ) {
            return;
        }

        $this->load_template( 'admin/athletes-group', [
            'group_key' => $group_key,
            'athletes' => $athletes,
            'title' => $title,
            'bold' => $bold,
        ] );
    }

    /**
     * Obtiene el nombre del mes actual en español
     *
     * @return string Nombre del mes en mayúsculas
     * @access private
     */
    private function get_current_month_name(): string {
        $months = [
            1 => __( 'ENERO', SULAYR_TEXTDOMAIN ),
            2 => __( 'FEBRERO', SULAYR_TEXTDOMAIN ),
            3 => __( 'MARZO', SULAYR_TEXTDOMAIN ),
            4 => __( 'ABRIL', SULAYR_TEXTDOMAIN ),
            5 => __( 'MAYO', SULAYR_TEXTDOMAIN ),
            6 => __( 'JUNIO', SULAYR_TEXTDOMAIN ),
            7 => __( 'JULIO', SULAYR_TEXTDOMAIN ),
            8 => __( 'AGOSTO', SULAYR_TEXTDOMAIN ),
            9 => __( 'SEPTIEMBRE', SULAYR_TEXTDOMAIN ),
            10 => __( 'OCTUBRE', SULAYR_TEXTDOMAIN ),
            11 => __( 'NOVIEMBRE', SULAYR_TEXTDOMAIN ),
            12 => __( 'DICIEMBRE', SULAYR_TEXTDOMAIN ),
        ];
        
        $current_month_num = (int) date( 'n' );
        return $months[ $current_month_num ] ?? '';
    }

    /**
     * Renderiza mensaje cuando no hay atletas
     *
     * @return void
     * @access private
     */
    private function render_empty_athletes_message(): void {
        $this->load_template( 'admin/empty-athletes' );
    }

    /**
     * render_athletes_by_status()
     * Renderiza el listado de deportistas agrupados por estado
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_athletes_by_status(): void {
        $grouped = $this->user_service->get_athletes_by_status();
        
        // Calcular total de deportistas
        $total_count = 0;
        foreach ( $grouped as $group ) {
            $total_count += count( $group );
        }
        
        if ( $total_count === 0 ) {
            $this->render_empty_athletes_message();
            return;
        }
        
        $this->load_template( 'admin/athletes-by-status', [
            'grouped' => $grouped,
            'total_count' => $total_count,
        ] );
    }

    /**
     * render_swimmers_list()
     * Renderiza el listado de nadadores (con suplemento de natación)
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_swimmers_list(): void {
        $swimmers = $this->user_service->get_swimmers();
        
        $this->load_template( 'admin/swimmers-list', [
            'swimmers' => $swimmers,
        ] );
    }

    /**
     * Renderiza la tabla de nadadores
     *
     * @param array $swimmers Lista de nadadores
     * @return void
     * @access private
     */
    private function render_swimmers_table( array $swimmers ): void {
        $this->load_template( 'admin/swimmers-table', [
            'swimmers' => $swimmers,
        ] );
    }

    /**
     * Renderiza una fila de la tabla de nadadores
     *
     * @param array $swimmer Datos del nadador
     * @return void
     * @access private
     */
    private function render_swimmer_row( array $swimmer ): void {
        $this->load_template( 'admin/swimmer-row', [
            'swimmer' => $swimmer,
        ] );
    }

    /**
     * get_list_url()
     * Genera la URL para mostrar un listado específico
     *
     * @param string $action Acción a realizar
     * @return string URL completa
     * @access private
     * @package club-triatlon-sulayr
     */
    private function get_list_url( string $action ): string {
        return wp_nonce_url(
            $this->get_page_url( [ 'action' => $action ] ),
            'sulayr_list_action',
            '_wpnonce'
        );
    }

    /**
     * Maneja la importación de CSV
     *
     * @return void
     * @access public
     */
    public function handle_csv_import(): void {
        if ( ! isset( $_POST['sulayr_import_csv'] ) ) {
            return;
        }

        // Forzar el uso del sistema de archivos directo ANTES de cualquier otra operación
        // Esto evita que WordPress intente usar FTP
        if ( ! defined( 'FS_METHOD' ) ) {
            define( 'FS_METHOD', 'direct' );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'No tienes permisos para realizar esta acción.', SULAYR_TEXTDOMAIN ) );
        }

        // Verificar nonce
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'sulayr_import_csv' ) ) {
            wp_die( __( 'Acción no autorizada.', SULAYR_TEXTDOMAIN ) );
        }

        // Verificar que se haya subido un archivo
        if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
            add_settings_error(
                'sulayr_import',
                'upload_error',
                __( 'Error al subir el archivo. Por favor, intenta de nuevo.', SULAYR_TEXTDOMAIN )
            );
            return;
        }

        $file = $_FILES['csv_file'];
        
        // Validar tipo de archivo sin usar wp_check_filetype (evita activar sistema de archivos)
        $file_name = $file['name'];
        $file_ext = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
        
        if ( $file_ext !== 'csv' ) {
            add_settings_error(
                'sulayr_import',
                'invalid_file',
                __( 'El archivo debe ser un CSV (.csv).', SULAYR_TEXTDOMAIN )
            );
            return;
        }

        // Usar el archivo temporal directamente sin moverlo
        // Esto evita problemas con FTP y permisos de archivos
        $temp_file = $file['tmp_name'];
        
        if ( ! file_exists( $temp_file ) || ! is_readable( $temp_file ) ) {
            add_settings_error(
                'sulayr_import',
                'file_error',
                __( 'Error al acceder al archivo temporal.', SULAYR_TEXTDOMAIN )
            );
            return;
        }

        // Importar el CSV usando el archivo temporal directamente
        $result = $this->csv_importer->import_from_csv( $temp_file );

        // Guardar resultados en transients para mostrarlos en la página
        set_transient( 'sulayr_import_result', $result, 60 );

        // Redirigir para evitar reenvío del formulario
        wp_safe_redirect( $this->get_page_url( [
            'action'   => 'import',
            'imported' => '1',
        ] ) );
        exit;
    }

    /**
     * Renderiza la configuración de Stripe (API keys y webhook secret).
     *
     * @return void
     * @access private
     */
    private function render_stripe_settings(): void {
        $publishable = get_option( 'sulayr_stripe_publishable_key', '' );
        $secret      = get_option( 'sulayr_stripe_secret_key', '' );
        $webhook     = get_option( 'sulayr_stripe_webhook_secret', '' );
        $this->load_template( 'admin/stripe-settings', [
            'stripe_publishable_key' => $publishable,
            'stripe_secret_key'      => $secret,
            'stripe_webhook_secret'  => $webhook,
        ] );
    }

    /**
     * Handle admin request to generate checkout link for an athlete.
     */
    public function handle_subscription_checkout_link(): void {
        if ( ! isset( $_POST['sulayr_generar_enlace_suscripcion'] ) || ! current_user_can( 'manage_options' ) ) {
            return;
        }
        if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'sulayr_generar_enlace_' . (int) $_POST['user_id'] ) ) {
            return;
        }
        $user_id = (int) $_POST['user_id'];
        if ( ! $user_id ) {
            return;
        }
        $subscription_service = new \ClubTriatlonSulayr\Modules\Subscriptions\SubscriptionService();
        if ( ! $subscription_service->is_available() || $subscription_service->hasActiveSubscription( $user_id ) ) {
            wp_safe_redirect( $this->get_page_url( [ 'action' => 'suscripciones' ] ) );
            exit;
        }
        $success_url = wc_get_endpoint_url( 'perfil-deportista', '', wc_get_page_permalink( 'myaccount' ) );
        $cancel_url  = $success_url;
        $checkout_url = $subscription_service->createCheckoutSessionForUser( $user_id, $success_url, $cancel_url );
        if ( $checkout_url ) {
            set_transient( 'sulayr_checkout_url_' . $user_id, $checkout_url, 300 );
        }
        wp_safe_redirect( $this->get_page_url( [
            'action'   => 'suscripciones',
            'generado' => $user_id,
        ] ) );
        exit;
    }

    /**
     * Renderiza la gestión de suscripciones (admin): listar deportistas y generar enlace de pago.
     *
     * @return void
     * @access private
     */
    private function render_subscriptions_management(): void {
        $subscription_service = new \ClubTriatlonSulayr\Modules\Subscriptions\SubscriptionService();
        $athletes = $this->user_service->get_athletes_by_plan();
        $list = [];
        foreach ( $athletes as $a ) {
            $user_id = (int) ( $a['ID'] ?? 0 );
            if ( ! $user_id ) {
                continue;
            }
            $list[] = [
                'user_id'      => $user_id,
                'name'         => $a['name'] ?? '',
                'email'        => $a['email'] ?? '',
                'plan'         => $a['plan_value'] ?? '',
                'is_directive' => ! empty( $a['is_directive'] ),
                'has_sub'      => $subscription_service->hasActiveSubscription( $user_id ),
            ];
        }
        $generado = isset( $_GET['generado'] ) ? (int) $_GET['generado'] : 0;
        $checkout_url = $generado ? get_transient( 'sulayr_checkout_url_' . $generado ) : '';
        if ( $checkout_url ) {
            delete_transient( 'sulayr_checkout_url_' . $generado );
        }
        $this->load_template( 'admin/subscriptions-management', [
            'subscription_service' => $subscription_service,
            'athletes'             => $list,
            'generado_user_id'     => $generado,
            'checkout_url'         => $checkout_url,
        ] );
    }

    /**
     * Renderiza la vista de importación
     *
     * @return void
     * @access private
     */
    private function render_import_view(): void {
        $import_result = null;
        if ( isset( $_GET['imported'] ) && $_GET['imported'] === '1' ) {
            $import_result = get_transient( 'sulayr_import_result' );
            if ( $import_result ) {
                delete_transient( 'sulayr_import_result' );
            }
        }

        $this->load_template( 'admin/import-view', [
            'import_result' => $import_result,
        ] );
    }

    /**
     * Carga una plantilla desde la carpeta templates
     *
     * @param string $template_name Nombre de la plantilla (sin extensión .php)
     * @param array  $vars          Variables a pasar a la plantilla
     * @return void
     * @access private
     */
    private function load_template( string $template_name, array $vars = [] ): void {
        \ClubTriatlonSulayr\Services\TemplateService::load( $template_name, $vars );
    }
}

