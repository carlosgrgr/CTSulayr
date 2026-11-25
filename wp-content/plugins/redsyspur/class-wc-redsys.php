<?php

/**
 * NOTA SOBRE LA LICENCIA DE USO DEL SOFTWARE
 *
 * El uso de este software está sujeto a las Condiciones de uso de software que
 * se incluyen en el paquete en el documento "Aviso Legal.pdf". También puede
 * obtener una copia en la siguiente url:
 * http://www.redsys.es/wps/portal/redsys/publica/areadeserviciosweb/descargaDeDocumentacionYEjecutables
 *
 * Redsys es titular de todos los derechos de propiedad intelectual e industrial
 * del software.
 *
 * Quedan expresamente prohibidas la reproducción, la distribución y la
 * comunicación pública, incluida su modalidad de puesta a disposición con fines
 * distintos a los descritos en las Condiciones de uso.
 *
 * Redsys se reserva la posibilidad de ejercer las acciones legales que le
 * correspondan para hacer valer sus derechos frente a cualquier infracción de
 * los derechos de propiedad intelectual y/o industrial.
 *
 * Redsys Servicios de Procesamiento, S.L., CIF B85955367
 */

/**
 * Plugin Name: Pasarela Unificada de Redsys para WooCommerce
 * Plugin URI: https://pagosonline.redsys.es/
 * Description: Acepta pagos con tarjeta o con Bizum utilizando los servicios de Redsys.
 * Version: 2.1.5
 * Author: Redsys Servicios de Procesamiento S.L.
 * Author URI: http://www.redsys.es/
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'REDSYSPUR_URL' ) ) {
	define( 'REDSYSPUR_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'REDSYSPUR_PATH' ) ) {
	define( 'REDSYSPUR_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'REDSYSPUR_DOMAIN' ) ) {
	define( 'REDSYSPUR_DOMAIN', 'redsyspur' );
}

add_action( 'plugins_loaded', 'init_redsys' );
add_action( 'activate_plugin', 'activate_redsyspur' , 10, 2);
add_action( 'admin_init', 'redsyspur_deactivate_plugins' );

$plugin_data = get_file_data(__FILE__, array('Version' => 'Version'), false);
$plugin_version = $plugin_data['Version'];

if ( ! defined( 'REDSYSPUR_MODULE_VERSION' ) )
    define( 'REDSYSPUR_MODULE_VERSION', $plugin_version );

if ( ! defined( 'REDSYSPUR_PLUGIN_FILE' ) )
    define( 'REDSYSPUR_PLUGIN_FILE', __FILE__ );

if ( ! defined( 'REDSYSPUR_PLUGIN_BASENAME' ) )
    define( 'REDSYSPUR_PLUGIN_BASENAME', plugin_basename( REDSYSPUR_PLUGIN_FILE ) );

function init_redsys() {
    load_plugin_textdomain(REDSYSPUR_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');

    if ( !class_exists( 'WC_Payment_Gateway' ) ) 
        return;
    
    add_filter( 'woocommerce_payment_gateways', 'anadir_pago_woocommerce_redsys' );
    add_filter( 'plugin_action_links_' . REDSYSPUR_PLUGIN_BASENAME, 'plugin_action_links', 10, 1 );

    add_action( 'woocommerce_blocks_loaded', 'anadir_pago_woocommerce_redsys_block' );
    add_action( 'wp_enqueue_scripts', 'redsys_enqueue_assets' );
}

function anadir_pago_woocommerce_redsys($methods) {
    include_once ('wc-redsys.php');
    include_once ('wc-redsys-bizum.php');
    include_once ('wc-redsys-insite.php');
   
    $methods[] = new WC_Redsys();
    $methods[] = new WC_Redsys_Insite();
    $methods[] = new WC_Redsys_Bizum();

    return $methods;
}

function anadir_pago_woocommerce_redsys_block() {
    global $payment_methods;

	if ( class_exists( 'Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType' ) ) {
		require_once 'wc-redsys-block.php';
		add_action(
			'woocommerce_blocks_payment_method_type_registration',
			function( Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry ) {
				$payment_method_registry->register( new WC_Gateway_Redsys_Block('redsys') );
                $payment_method_registry->register( new WC_Gateway_Redsys_Block('redsys_bizum') );
                $payment_method_registry->register( new WC_Gateway_Redsys_Block('redsys_insite') );
			}
		);
	}
}

function redsys_enqueue_assets() {

    wp_enqueue_script('jquery');

    wp_enqueue_script('jquery-ui-core');
    wp_enqueue_script('jquery-ui-dialog'); 

    /** jQuery UI CSS */
    wp_enqueue_style(
        'jquery-ui-css',
        plugin_dir_url(__FILE__) . './pages/assets/css/jquery-ui.min.css',
        array(),
        '1.14.1'
    );
}

function activate_redsyspur($plugin, $network_wide) {
    if (!defined('WC_VERSION')) {
        wp_die('El plugin WooCommerce no esta activo');
    }
    deleteCustomerZero();
}

function deleteCustomerZero(){
    global $wpdb;
    $tableName=$wpdb->prefix."redsys_reference";

    $tablas=$wpdb->get_results( "SHOW TABLES LIKE '".$tableName."'" );
    if(sizeof($tablas)>0){
        $sql = "DELETE FROM `".$tableName."` WHERE id_customer = 0";
        $wpdb->get_results( $sql );
    }
}

function plugin_action_links( $links ) {
    $action_links = array(
        'settings' => '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '" aria-label="' . esc_attr__( 'Configurar métodos de Pago', REDSYSPUR_DOMAIN ) . '">' . esc_html__( 'Ajustes', REDSYSPUR_DOMAIN ) . '</a>',
    );

    return array_merge( $action_links, $links );
}

function redsyspur_deactivate_plugins() {
	$plugins = array(
        'woo-redsys-gateway-light/woocommerce-redsys.php',
        'woocommerce-gateway-redsys/woocommerce-gateway-redsys.php',
    );

    $anyActivated = false;
    foreach($plugins as $plugin){
        $anyActivated = $anyActivated || is_plugin_active($plugin);
    }

    if($anyActivated){
        $options = array(
            'type' => 'warning',
            'dismissible' => true
        );
        wp_admin_notice( __( 'Se ha desactivado Woocommerce Redsys Gateway para evitar problemas de compatibilidad.', REDSYSPUR_DOMAIN ), $options );
    }

	deactivate_plugins( $plugins, true );
}