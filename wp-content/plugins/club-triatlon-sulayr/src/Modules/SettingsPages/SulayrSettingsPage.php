<?php
namespace ClubTriatlonSulayr\Modules\SettingsPages;

use ClubTriatlonSulayr\Services\UserService;

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
    
    private UserService $user_service;

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( UserService $user_service ) {
        $this->user_service = $user_service;
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
        add_action( 'admin_init', [ $this, 'handle_list_requests' ] );
    }

    /**
     * add_settings_page()
     * Añade la página de opciones al menú de administración
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function add_settings_page(): void {
        add_submenu_page(
            'options-general.php',
            __( 'Sulayr Settings', SULAYR_TEXTDOMAIN ),
            __( 'Sulayr Settings', SULAYR_TEXTDOMAIN ),
            'manage_options',
            self::MENU_SLUG,
            [ $this, 'render_settings_page' ]
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
        if ( ! isset( $_GET['page'] ) || $_GET['page'] !== self::MENU_SLUG ) {
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

        $action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php if ( empty( $action ) ) : ?>
                <!-- Vista principal con botones -->
                <div class="sulayr-settings-main">
                    <div class="card">
                        <div class="card-body">
                            <h2 class="card-title"><?php _e( 'Listados de deportistas', SULAYR_TEXTDOMAIN ); ?></h2>
                            <p class="card-text"><?php _e( 'Selecciona el tipo de listado que deseas ver:', SULAYR_TEXTDOMAIN ); ?></p>
                            
                            <div class="sulayr-settings-buttons">
                                <a href="<?php echo esc_url( $this->get_list_url( 'by_plan' ) ); ?>" 
                                   class="button button-primary button-large">
                                    <span class="dashicons dashicons-groups"></span>
                                    <?php _e( 'Listar deportistas por tarifa', SULAYR_TEXTDOMAIN ); ?>
                                </a>
                                
                                <a href="<?php echo esc_url( $this->get_list_url( 'swimmers' ) ); ?>" 
                                   class="button button-primary button-large">
                                    <span class="dashicons dashicons-swimming"></span>
                                    <?php _e( 'Listar nadadores', SULAYR_TEXTDOMAIN ); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else : ?>
                <!-- Vista de listado -->
                <div class="sulayr-settings-list">
                    <p>
                        <a href="<?php echo esc_url( admin_url( 'options-general.php?page=' . self::MENU_SLUG ) ); ?>" 
                           class="button">
                            <span class="dashicons dashicons-arrow-left-alt"></span>
                            <?php _e( 'Volver', SULAYR_TEXTDOMAIN ); ?>
                        </a>
                    </p>
                    
                    <?php
                    switch ( $action ) {
                        case 'by_plan':
                            $this->render_athletes_by_plan();
                            break;
                        case 'swimmers':
                            $this->render_swimmers_list();
                            break;
                        default:
                            echo '<div class="notice notice-error"><p>' . esc_html__( 'Acción no válida.', SULAYR_TEXTDOMAIN ) . '</p></div>';
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
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
        
        // Obtener el mes actual en español
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
        $current_month = $months[ (int) date( 'n' ) ];
        
        ?>
        <div class="card sulayr-athletes-list-card">
            <div class="card-body">
                <?php if ( empty( $athletes ) ) : ?>
                    <p><?php _e( 'No se encontraron deportistas.', SULAYR_TEXTDOMAIN ); ?></p>
                <?php else : ?>
                    <?php
                    // Agrupar por tarifa y tipo de entrenamiento
                    $grouped = [
                        'basic' => [],
                        'training_sprint' => [],
                        'training_olimpic' => [],
                        'other' => [],
                    ];
                    
                    foreach ( $athletes as $athlete ) {
                        $plan_value = $athlete['plan_value'] ?? '';
                        $training_type = $athlete['training_type_value'] ?? '';
                        
                        // Si es premium/entrenamiento, agrupar por tipo
                        if ( $plan_value === 'premium' || stripos( $plan_value, 'premium' ) !== false ) {
                            if ( $training_type === 'sprint' || stripos( $training_type, 'sprint' ) !== false ) {
                                $grouped['training_sprint'][] = $athlete;
                            } elseif ( $training_type === 'olimpic' || stripos( $training_type, 'olimpic' ) !== false || stripos( $training_type, 'olímpico' ) !== false ) {
                                $grouped['training_olimpic'][] = $athlete;
                            } else {
                                // Si tiene plan premium pero no tipo, poner en otros
                                $grouped['other'][] = $athlete;
                            }
                        } elseif ( $plan_value === 'basic' || stripos( $plan_value, 'basic' ) !== false ) {
                            $grouped['basic'][] = $athlete;
                        } else {
                            $grouped['other'][] = $athlete;
                        }
                    }
                    
                    // Ordenar alfabéticamente cada grupo por nombre
                    foreach ( $grouped as $key => $group ) {
                        usort( $group, function( $a, $b ) {
                            return strcmp( $a['name'], $b['name'] );
                        } );
                        $grouped[ $key ] = $group;
                    }
                    ?>
                    
                    <h2 class="sulayr-athletes-title">
                        *<?php echo esc_html( sprintf( __( 'DEPORTISTAS %s', SULAYR_TEXTDOMAIN ), $current_month ) ); ?>* (<?php echo count( $athletes ); ?>)
                    </h2>
                    
                    <?php if ( ! empty( $grouped['basic'] ) ) : ?>
                        <h4 class="sulayr-athletes-group-title">
                            *<?php echo esc_html( __( 'Básica', SULAYR_TEXTDOMAIN ) ); ?>* <?php echo '(' . count( $grouped['basic'] ) . ')'; ?>
                        </h4>
                        <div class="sulayr-athletes-list">
                            <?php foreach ( $grouped['basic'] as $index => $athlete ) : ?>
                                <span class="sulayr-athletes-list-item"><?php echo $index + 1 . '. ' . esc_html( $athlete['name'] ) . ' ' . esc_html( $athlete['email'] ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        </br>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $grouped['training_sprint'] ) ) : ?>
                        <h4 class="sulayr-athletes-group-title">
                            *<?php echo esc_html( __( 'Entrenamientos sprint', SULAYR_TEXTDOMAIN ) ); ?>* <?php echo '(' . count( $grouped['training_sprint'] ) . ')'; ?>
                        </h4>
                        <div class="sulayr-athletes-list">
                            <?php foreach ( $grouped['training_sprint'] as $index => $athlete ) : ?>
                                <span class="sulayr-athletes-list-item"><?php echo $index + 1 . '. ' . esc_html( $athlete['name'] ) . ' ' . esc_html( $athlete['email'] ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        </br>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $grouped['training_olimpic'] ) ) : ?>
                        <h4 class="sulayr-athletes-group-title">
                            *<?php echo esc_html( __( 'Entrenamientos olímpico', SULAYR_TEXTDOMAIN ) ); ?>* <?php echo '(' . count( $grouped['training_olimpic'] ) . ')'; ?>
                        </h4>
                        <div class="sulayr-athletes-list">
                            <?php foreach ( $grouped['training_olimpic'] as $index => $athlete ) : ?>
                                <span class="sulayr-athletes-list-item"><?php echo $index + 1 . '. ' . esc_html( $athlete['name'] ) . ' ' . esc_html( $athlete['email'] ); ?></span>
                            <?php endforeach; ?>
                        </div>
                        </br>
                    <?php endif; ?>
                    
                    <?php if ( ! empty( $grouped['other'] ) ) : ?>
                        <h4 class="sulayr-athletes-group-title sulayr-athletes-group-title-other">
                            <?php echo esc_html( __( 'Otros', SULAYR_TEXTDOMAIN ) ); ?> <?php echo '(' . count( $grouped['other'] ) . ')'; ?>
                        </h4>
                        <div class="sulayr-athletes-list">
                            <?php foreach ( $grouped['other'] as $index => $athlete ) : ?>
                                <span class="sulayr-athletes-list-item"><?php echo $index + 1 . '. ' . esc_html( $athlete['name'] ) . ' ' . esc_html( $athlete['email'] ); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php
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
        
        ?>
        <div class="card sulayr-swimmers-card">
            <div class="card-header">
                <h2><?php _e( 'Listado de nadadores', SULAYR_TEXTDOMAIN ); ?></h2>
                <p><?php _e( 'Deportistas con suplemento de natación activo', SULAYR_TEXTDOMAIN ); ?></p>
            </div>
            <div class="card-body">
                <?php if ( empty( $swimmers ) ) : ?>
                    <p><?php _e( 'No se encontraron nadadores con suplemento activo.', SULAYR_TEXTDOMAIN ); ?></p>
                <?php else : ?>
                    <p class="sulayr-swimmers-count">
                        <strong><?php _e( 'Total de nadadores:', SULAYR_TEXTDOMAIN ); ?></strong> 
                        <?php echo count( $swimmers ); ?>
                    </p>
                    
                    <table class="wp-list-table widefat fixed striped sulayr-list-table">
                        <thead>
                            <tr>
                                <th><?php _e( 'ID', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'Nombre', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'Email', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'DNI', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></th>
                                <th><?php _e( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $swimmers as $swimmer ) : ?>
                                <tr>
                                    <td><?php echo esc_html( $swimmer['ID'] ); ?></td>
                                    <td>
                                        <strong><?php echo esc_html( $swimmer['name'] ); ?></strong>
                                    </td>
                                    <td><?php echo esc_html( $swimmer['email'] ); ?></td>
                                    <td><?php echo esc_html( $swimmer['phone'] ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $swimmer['dni'] ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $swimmer['plan'] ?: '-' ); ?></td>
                                    <td><?php echo esc_html( $swimmer['training_type'] ?: '-' ); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
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
            admin_url( 'options-general.php?page=' . self::MENU_SLUG . '&action=' . $action ),
            'sulayr_list_action',
            '_wpnonce'
        );
    }
}

