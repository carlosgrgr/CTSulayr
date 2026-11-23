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

require_once('redsys-helper.php');
require_once('wc-redsys-ref.php');
require_once('wc-redsys-order.php');

require_once __DIR__ . '/lib/autoload.php';

use Redsys\Endpoints;
use Redsys\Merchant;
use Redsys\Parameters;
use Redsys\RequestData;
use Redsys\Resources;
use Redsys\Signature;
use Redsys\TransactionType;
use Redsys\Utils;

class WC_Redsys extends WC_Payment_Gateway {

    public $notify_url;
    public $payment_fields_url;
    public $session_vars_url;
    public $delete_ref_url;
    public $redirect_url;
    public $back_from_qr_url;
    public $buttonLabel;
    public $nombre;
    public $tipopago;
    public $genPedido;
    public $pedidoExtendido;
    public $idioma;
    public $activar_log;
    public $tamano_log;
    public $estado;
    public $estado_preautorizacion;
    public $estado_autenticacion;
    public $activar_3ds;
    public $cancelar_en_error;
    public $withref;
    public $tabla_ordenes;
    public $decimales_moneda;
    public $modal;
    public $qr;
    public $notificacion_get;
    public $urlOK;
    public $urlKO;
    public $moneda;
    public $moduleComent;
    public $merchant;
    public $logString;
    public $custom_order_statuses;

    public function __construct() {
        $this->id                 = 'redsys';  
        //$this->icon               = REDSYSPUR_URL . '/pages/assets/images/Redsys.png';
        $this->method_title       = __( 'Redirección · Pasarela Unificada de Redsys para WooCommerce', REDSYSPUR_DOMAIN );
        $this->method_description = __( 'Permita a sus clientes pagar con tarjeta redirigiéndoles a los servicios de Redsys.', REDSYSPUR_DOMAIN );
        $this->notify_url         = add_query_arg( 'wc-api', 'WC_redsys', home_url( '/' ) );
        $this->payment_fields_url = add_query_arg( 'wc-api', 'WC_redsys_payment_fields', home_url( '/' ) );
        $this->session_vars_url = add_query_arg( 'wc-api', 'WC_redsys_session_vars', home_url( '/' ) );
        $this->delete_ref_url = add_query_arg( 'wc-api', 'WC_redsys_delete_ref', home_url( '/' ) );
        $this->redirect_url       = add_query_arg( 'wc-api', 'WC_redsys_redirect', home_url( '/' ) );
        $this->back_from_qr_url = add_query_arg( 'wc-api', 'WC_redsys_back_from_qr', home_url( '/' ) );

        $this->has_fields         = false;

        // Load the settings
        $this->init_settings();
        $this->init_form_fields();

        $this->supports           = array( 'refunds' );

        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );
        $this->buttonLabel        = "Continuar en la pasarela de pago";

        // Get settings
        $this->nombre             = $this->get_option( 'name' );
        $this->tipopago           = $this->get_option( 'tipopago' );
        $this->genPedido          = $this->get_option( 'genPedido' );
        $this->pedidoExtendido    = $this->get_option( 'pedidoExtendido' );
        $this->idioma             = $this->get_option( 'idioma' );
        $this->activar_log	      = $this->get_option( 'activar_log' );
        $this->tamano_log	      = $this->get_option( 'tamano_log' );
        $this->estado             = $this->get_option( 'estado' );
        $this->estado_preautorizacion = $this->get_option( 'estado_preautorizacion' );
        $this->estado_autenticacion = $this->get_option( 'estado_autenticacion' );
        $this->activar_3ds        = $this->get_option( 'activar_3ds' );
        $this->cancelar_en_error   = $this->get_option( 'cancelar_en_error' );
        $this->withref            = $this->get_option( 'withref' );
        $this->tabla_ordenes      = $this->get_option( 'tabla_ordenes' );
        $this->decimales_moneda   = $this->get_option( 'decimales_moneda' );
        $this->modal              = 0 /*$this->get_option( 'modal' )*/; //ELIMINACION DEL FORMULARIO MODAL
        $this->qr                 = $this->get_option( 'qr' );
        $this->notificacion_get	  = $this->get_option( 'notificacion_get' );
		$this->urlOK              = $this->get_option( 'urlOK' );
		$this->urlKO              = $this->get_option( 'urlKO' );

        //moneda a usar
        $this->moneda = RedsysHelper::getCurrencyCode(get_option('woocommerce_currency'));
		if(!empty($this->get_option('moneda_manual'))){
            $this->moneda = $this->get_option('moneda_manual');
        }

        $this->moduleComent = "Pasarela Unificada de Redsys para WooCommerce";

        $fuc = $this->get_option('fuc');
        $terminal = $this->get_option('terminal');
        $claveFirma = $this->get_option('claveFirma');
        $entorno = RedsysHelper::getLiteralEntorno($this->get_option('entorno'));

        $this->merchant = Merchant::initWithParams($fuc, $terminal, $claveFirma, $entorno);

        //idLog
        $this->logString          = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Actions
        add_action( 'woocommerce_receipt_redsys', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        //Payment listener/API hook
        add_action( 'woocommerce_api_wc_redsys', array( $this, 'check_rds_response' ) );
        add_action( 'woocommerce_api_wc_redsys_payment_fields', array( $this, 'payment_fields_api' ) );
        add_action( 'woocommerce_api_wc_redsys_session_vars', array( $this, 'session_vars' ) );
        add_action( 'woocommerce_api_wc_redsys_delete_ref', array( $this, 'delete_ref' ) );
        add_action( 'woocommerce_api_wc_redsys_redirect', array( $this, 'redirect' ) );
        add_action( 'woocommerce_api_wc_redsys_back_from_qr', array( $this, 'back_from_qr' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
        // Confirmation payment
        add_action( 'woocommerce_order_action_confirmation_payment_action', array( $this, 'confirmation_payment_action' ) );
        add_action( 'woocommerce_order_action_cancellation_payment_action', array( $this, 'cancellation_payment_action' ) );
        add_action( 'post_action_confirmation_payment', array( $this, 'confirmation_payment' ) );
        add_action( 'post_action_cancellation_payment', array( $this, 'cancellation_payment' ) );
        add_filter( 'init', array( $this, 'register_custom_order_statuses' ) );
        add_filter( 'wc_order_statuses', array( $this, 'add_custom_order_statuses' ) );
    }

    function register_custom_order_statuses(){
        if($this->custom_order_statuses){
            foreach($this->custom_order_statuses as $key => $value){
                register_post_status($key, array(
                    'label' => $value,
                    'public' => true,
                    'show_in_admin_status_list' => true,
                    'show_in_admin_all_list' => true,
                    'exclude_from_search' => false,
                ));
            }
        }
    }

    function add_custom_order_statuses( $order_statuses ) {
        if($this->custom_order_statuses){
            foreach($this->custom_order_statuses as $key => $value){
                $order_statuses[$key] = $value;
            }
        }
        return $order_statuses;
    }

    function init_form_fields() {
        global $woocommerce;

        $this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Mostrar método de pago', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si mostrar u ocultar el método de pago por Redirección', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'description' => __( 'Desactivarlo no afecta a la operativa contratada en tu TPV ni a las configuraciones guardadas', REDSYSPUR_DOMAIN ),
                    'default'     => 'no',
                    'desc_tip'    => true,
                ),
                'title' => array(
                    'title'       => __( 'Título del método de Pago', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Título del método de Pago que el cliente verá en la página de compra.', REDSYSPUR_DOMAIN ),
                    'default'     => __( 'Pagar con Tarjeta', REDSYSPUR_DOMAIN ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Descripción del método de Pago', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Descripción del método de Pago que el cliente verá en la página de compra.', REDSYSPUR_DOMAIN ),
                    'default'     => __( 'Será redirigido de forma segura para pagar con tarjeta usando los servicios de Redsys.', REDSYSPUR_DOMAIN ),
                    'desc_tip'    => true,
                ),
                'qr' => array(
                    'title'       => __( 'Habilitar pago QR', REDSYSPUR_DOMAIN ),
                    'label'       => 'Configura si quieres habilitar una opción adicional para permitir pagar escaneando un QR',
                    'type'        => 'checkbox',
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Este método de pago <b>no</b> se puede probar en el entorno de Test debido a limitaciones técnicas, pero puedes probarlo en entorno Real y luego ejecutar una devolución de la operación.<br>El pago por QR es útil para las personas que tengan guardadas sus tarjetas en sus dispositivos móviles, que no se encuentren en un dispositivo de confianza (como un ordenador público), o que quieran pagar usando Apple Pay o Google Pay desde su móvil.', REDSYSPUR_DOMAIN ),
                    'default'     => 'no',
                    'disabled'    => false,
                    'desc_tip'    => false,
                ),
                // 'modal' => array(
                //     'title'       => __( 'Habilitar ventana de pago modal', REDSYSPUR_DOMAIN ),
                //     'label'       => __( 'Configura si quieres mostrar tu TPV de redirección en un formulario modal', REDSYSPUR_DOMAIN ),
                //     'type'        => 'checkbox',
                //     'description' => __( '<span style="color:#fa7878">Esta funcionalidad se ha descontinuado y ya no recibirá más actualizaciones, por lo que su activación <b>no modificará</b> el comportamiento del TPV.</span>', REDSYSPUR_DOMAIN ),
                //     'default'     => 'no',
                //     'disabled'    => true,
                //     'desc_tip'    => false,
                // ),
                'entorno' => array(
                    'title'       => __( 'Entorno de Operación', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Entorno donde procesar el pago. <br>Recuerde no activar el modo "Sandbox" en su entorno de producción, de lo contrario podrían producirse ventas no deseadas. Dispone de más información sobre cómo realizar pruebas <a href=https://pagosonline.redsys.es/entornosPruebas.html target="_blank" rel="noopener noreferrer">aquí</a>.', REDSYSPUR_DOMAIN ),
                    'default'     => 0,
                    'options'     => array(
                        'TEST' => __( 'Sandbox', REDSYSPUR_DOMAIN ),
                        'PROD' => __( 'Producción', REDSYSPUR_DOMAIN )
                    )
                ),
                'name' => array(
                    'title'       => __( 'Nombre del Comercio', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Nombre de su comercio que se establecerá a la hora de enviar las operaciones.', REDSYSPUR_DOMAIN ),
                    'default'     => __( get_bloginfo('name'), REDSYSPUR_DOMAIN ),
                    'desc_tip'    => true,
                ),
                'fuc' => array(
                    'title'       => __( 'Número FUC', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'El número de comercio, también denominado FUC, es un número que identifica a su comercio y debe habérselo provisto su Entidad Bancaria.', REDSYSPUR_DOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'terminal' => array(
                    'title'       => __( 'Terminal', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'El número de terminal es el número que identifica el terminal dentro de su comercio y debe habérselo provisto su Entidad Bancaria.', REDSYSPUR_DOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'claveFirma' => array(
                    'title'       => __( 'Clave de encriptación', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Esta clave permite firmar todas las operaciones enviadas por el módulo y ha debido ser provista de ella por su Entidad Bancaria. Recuerde guardarla en un lugar seguro. <br> Para realizar pruebas en el entorno Sandbox, puede usar <code>sq7HjrUOBfKmC576ILgskD5srU870gJ7</code> o la provista por su Entidad Bancaria.', REDSYSPUR_DOMAIN ),
                    // 'default'     => __( 'sq7HjrUOBfKmC576ILgskD5srU870gJ7', REDSYSPUR_DOMAIN ),
                ),
                'tipopago' => array(
                    'title'       => __( 'Tipo de transacción', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( '<b>Autorización:</b> Es la operación estándar para que tus clientes realicen un pago.<br><b>Preautorización:</b> Esta operación retiene el cargo en la tarjeta del cliente, pero debe ser confirmada por ti en el Portal de Administración del TPV Virtual para que tenga efecto contable.<br><b>Autenticación:</b> Confirma los datos de la tarjeta del cliente pero no retiene el dinero en su cuenta. Para que tenga valor contable, debes confirmar la operación en el Portal de Administración del TPV Virtual, al igual que con la preautorización.', REDSYSPUR_DOMAIN ),
                    'default'     => '0',
                    'options'     => array(
                            '0' => __( 'Autorización (predeterminado)', REDSYSPUR_DOMAIN ),
                            '1' => __( 'Preautorización', REDSYSPUR_DOMAIN ),
                            '7' => __( 'Autenticación', REDSYSPUR_DOMAIN )
                    ),
                ),
                'estado' => array(
                    'title'       => __( 'Estado del pedido al verificarse el pago para las autorizaciones', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Aquí puede configurar el estado en el que se mostrará el pedido en el apartado "Pedidos" de su backoffice una vez el módulo reciba la notificación de que el pago ha sido correcto.', REDSYSPUR_DOMAIN ),
                    'default'     => null,
                    'options'     => array(),
                    'desc_tip'    => true,
                ),
                'estado_preautorizacion' => array(
                    'title'       => __( 'Estado del pedido al verificarse el proceso de preautorización', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Aquí puede configurar el estado en el que se mostrará el pedido en el apartado "Pedidos" de su backoffice al realizar una preautorizacion.', REDSYSPUR_DOMAIN ),
                    'default'     => 'on-hold',
                    'options'     => array(),
                    'desc_tip'    => true,
                ),
                'estado_autenticacion' => array(
                    'title'       => __( 'Estado del pedido al verificarse el proceso de autenticación', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Aquí puede configurar el estado en el que se mostrará el pedido en el apartado "Pedidos" de su backoffice al realizar una autenticación.', REDSYSPUR_DOMAIN ),
                    'default'     => 'on-hold',
                    'options'     => array(),
                    'desc_tip'    => true,
                ),
    			'withref' => array(
                    'title'       => __( 'Habilitar pago por referencia', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si permitir al cliente guardar su tarjeta para futuras compras', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Esta configuración podría requerir activación por parte de su Entidad Bancaria.<br>El pago por referencia permite al cliente guardar su tarjeta para pagar de manera más sencilla en futuras compras.', REDSYSPUR_DOMAIN ),
                    'default'     => 'no'
                ),
    			'activar_3ds' => array(
                    'title'       => __( 'Pago seguro usando 3D Secure', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si activar o desactivar el pago seguro enviando información adicional de 3D Secure', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'description' => __( 'Esta opción permite enviar información adicional del cliente que está realizando la compra, proporcionando más seguirdad a la hora de autenticar la operación. Se recomienda el envío de esta información en los datos de la operación.', REDSYSPUR_DOMAIN ),
                    'default'     => 'yes',
                    'desc_tip'    => true
                ),
                'genPedido' => array(
                    'title' => __( 'Método de generación del número de pedido', REDSYSPUR_DOMAIN ),
                    'type' => 'select',
                    'description' => __( 'Esta opción no modifica la forma en la que se identifica la orden en su Backoffice, sino el número de pedido (adaptado para que siempre ocupe doce dígitos) que se envía a Redsys para identificar la operación.<br>Recuerde que en los detalles de cada orden puede ver el número de pedido que identifica la operación en el Portal de Administración del TPV Virtual.', REDSYSPUR_DOMAIN ),
                    'default' => '0',
                    'options' => array(
                            '0' => __( 'Híbrido (recomendado)', REDSYSPUR_DOMAIN ),
                            '1' => __( 'Sólo ID del carrito', REDSYSPUR_DOMAIN ),
                            '2' => __( 'Aleatorio', REDSYSPUR_DOMAIN )
                    ),
                ),
                'pedidoExtendido' => array(
                    'title'       => __( 'El terminal permite número de pedido extendido', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si se pueden enviar a tu TPV números de pedido que superen las 12 posiciones', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'description' => __( 'Marque esta opción si su terminal está configurado para admitir números de pedidos extendidos. Esto es útil para tiendas cuyos número de pedidos podrían exceder las doce posiciones que tiene como máximo un número de pedido estándar.<br>Recuerde que debe solicitar a su entidad bancaria que activen esta configuración en su terminal antes de marcar esta opción.', REDSYSPUR_DOMAIN ),
                    'default'     => 'no',
                ),
    			'idioma' => array(
                    'title'       => __( 'Permitir al TPV usar el idioma configurado en el navegador del cliente', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si mostrar el formulario de pago de tu TPV en el idioma del navegador del cliente', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'description' => __( 'Con esta opción activada, la pasarela se mostrará en el idioma de visualización que el cliente haya configurado en los ajustes de su navegador.', REDSYSPUR_DOMAIN ),
                    'default'     => 'no',
                    'desc_tip'    => false
                ),
                'tabla_ordenes' => array(
                    'title'       => __( 'Tabla de Wordpress donde se guardan las órdenes de Woocommerce', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Configura aquí la tabla donde se guardan las órdenes de Woocommerce. Por defecto, se usa la tabla de entradas de Wordpress, pero en versiones más nuevas de Woocommerce, es posible que tengas configurado que se haga en una tabla propia de Woocommerce.<br>Puedes consultar tu configuración en Ajustes de Woocommerce > Avanzado > Almacenes de datos personalizado.', REDSYSPUR_DOMAIN ),
                    'default'     => '1',
                    'options'     => array(
                            '0' => __( 'Tabla de Post/Entradas wp_posts', REDSYSPUR_DOMAIN ),
                            '1' => __( 'Tabla de órdenes de WooCommerce wc_orders (por defecto)', REDSYSPUR_DOMAIN )
                    ),
                ),
                'moneda_manual' => array(
                    'title'       => __( 'Moneda personalizada para operaciones', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'placeholder' => __( 'Introduzca el código ISO de la moneda.', REDSYSPUR_DOMAIN),
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Esta configuración sobreescribirá la detección automática de moneda, su terminal deberá estar configurado para usar la moneda que aquí establezca si es distinta al Euro.<br>Deje en blanco para usar la detección automática. Use esta configuración sí y sólo sí su comercio está recibiendo errores SIS0015 o SIS0027.', REDSYSPUR_DOMAIN ),
                    'default'     => '',
                ),
                'decimales_moneda' => array(
                    'title'       => __( 'Número de decimales de la moneda utilizada', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Esta configuración sobreescribe la detección automática de decimales de la moneda.<br>Al instalar el módulo se obtiene la configuración de Woocommerce, salvo que se hayan configurado cuatro o más decimales, que entonces se fija por defecto en 2 decimales (para el Euro, por ejemplo); pero si está usando monedas con distinto número de decimales o tiene problemas con el importe enviado al TPV Virtual, pruebe a cambiar esta opción.', REDSYSPUR_DOMAIN ),
                    'default' => (intval(get_option('woocommerce_price_num_decimals')) > 3) ? 2 : intval(get_option('woocommerce_price_num_decimals')),
                    'options' => array(
                            0 => __( '0 (JPY, KRW, VND, ...)', REDSYSPUR_DOMAIN ),
                            1 => __( '1 (JOD, TND, LYD, ...)', REDSYSPUR_DOMAIN ),
                            2 => __( '2 (EUR, USD, GBP, ...)', REDSYSPUR_DOMAIN ),
                            3 => __( '3 (KWD, OMR, BHD, ...)', REDSYSPUR_DOMAIN )
                    ),
                ),
                'activar_log' => array(
                    'title'       => __( 'Guardar registros de comportamiento', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Si activa esta opción, se guardarán registros (logs) de los procesos que realice el módulo. <br> A la hora de notificar cualquier incidencia, los logs completos son de gran utilidad para poder detectar el problema.', REDSYSPUR_DOMAIN ),
                    'default'     => '1',
                    'options'     => array(
                            '0' => __( 'No', REDSYSPUR_DOMAIN ),
                            '1' => __( 'Sí, sólo informativos', REDSYSPUR_DOMAIN ),
                            '2' => __( 'Sí, todos los registros', REDSYSPUR_DOMAIN )
                    ),
                    'desc_tip'    => true,
                ),       
                'tamano_log' => array(
                    'title'       => __( 'Tamaño maximo del fichero de registros de comportamiento (en MB)', REDSYSPUR_DOMAIN ),
                    'type'        => 'number',
                    'description' => __( 'Cada vez que se alcance el límite estipulado, se creará un fichero nuevo, lo que agiliza la carga y manejo de los registros', REDSYSPUR_DOMAIN ),
                    'placeholder' => __( 'Déjalo vacío si no quieres limitar el tamaño del fichero.', REDSYSPUR_DOMAIN),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'cancelar_en_error' => array(
                    'title'       => __( 'Cancelar el pedido en caso de error en el pago', REDSYSPUR_DOMAIN),
                    'type'        => 'checkbox',
                    'label'       => 'Configura si, en caso de error, se debe cancelar la orden y generar una nueva',
                    'description' => __( 'Si el usuario cancela el pago u ocurre un error durante el mismo, elige si quieres cancelar el pedido y que se genere uno nuevo manteniendo el carrito, o que se muestre la pantalla de selección de método de pago directamente para que el cliente pueda reintentar la operación.<br>Ten en cuenta que, normalmente, el propio TPV dará la opción de reintentar, y este flujo se usará sobretodo en caso de que el cliente cancele el pago; por lo que mostrar directamente la pantalla de selección de método de pago sin oportunidad para modificar el carrito, podría resultarle confuso al cliente.', REDSYSPUR_DOMAIN ),
                    'default'     => 'yes',
                    'desc_tip'    => false,
                    'disabled'    => false,
                ),
                'notificacion_get' => array(
                    'title'       => __( 'Validar los pedidos usando los parámetros incluidos en el retorno de navegación del cliente', REDSYSPUR_DOMAIN ),
                    'type'        => 'checkbox',
                    'label'       => 'Configura si permitir usar estos parámetros para mitigar errores en las notificaciones HTTP.',
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Ten en cuenta que para que esta opción funcione, no debes tener ninguna URL personalizada configurada en urlOK ni en urlKO, ya que el módulo debe redireccionar al cliente a un endpoint específico.<br>Para un correcto funcionamiento, debes activar la opción "Redirigir al Checkout en caso de error..." y configurar en el Portal de Administración del TPV Virtual la opción "Enviar parámetros en las URLs" a "Sí".', REDSYSPUR_DOMAIN ),
                    'default'     => 'no',
                    'desc_tip'    => false,
                ),
                'urlOK' => array(
                    'title'       => __( 'URL para operaciones correctas', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Este campo, denominado URL_OK, establece a qué página se redirigirá al cliente al volver de Redsys una vez la operación haya finalizado y esta sea correcta. Si este campo se rellena, se ignorará la configuración del parámetro establecida en el Portal de Administración del TPV Virtual.', REDSYSPUR_DOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'urlKO' => array(
                    'title'       => __( 'URL para operaciones erróneas', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'description' => __( 'Este campo, denominado URL_KO, establece a qué página se redirigirá al cliente al volver de Redsys una vez la operación haya finalizado y esta haya tenido algún error. Si este campo se rellena, se ignorará la configuración del parámetro establecida en el Portal de Administración del TPV Virtual.', REDSYSPUR_DOMAIN ),
                    'default'     => '',
                    'desc_tip'    => true,
                ));

                $this->form_fields['estado']['options'][null] = __( 'Por defecto', REDSYSPUR_DOMAIN);
                $this->form_fields['estado_preautorizacion']['options'][null] = __( 'Por defecto', REDSYSPUR_DOMAIN);
                $this->form_fields['estado_autenticacion']['options'][null] = __( 'Por defecto', REDSYSPUR_DOMAIN);
				
				$tmp_estados=wc_get_order_statuses();
				foreach($tmp_estados as $est_id=>$est_na){
					$this->form_fields['estado']['options'][substr($est_id,3)]=$est_na;
                    $this->form_fields['estado_preautorizacion']['options'][substr($est_id,3)]=$est_na;
                    $this->form_fields['estado_autenticacion']['options'][substr($est_id,3)]=$est_na;
				}
    }

    function process_payment( $order_id ) {
        $withQR = (bool) WC()->session->get( 'with_qr', false );
        if( false ) { //ELIMINACION DEL FORMULARIO MODAL
            $order = new WC_Order($order_id);
            $urlOK = $this->urlOK;
            $redirectUrl = $urlOK ? $urlOK : $order->get_checkout_order_received_url();
        }else if($withQR){
            return array(
                'result' 	=> 'success'
            );
        }else{
            global $woocommerce;
            $order = new WC_Order($order_id);
    
            $orderIdLog = $order_id . $this->merchant->fuc;
            $idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);
    
            $isLogged = is_user_logged_in();
            $userId = $order->get_customer_id();
    
            RedsysHelper::escribirLog("DEBUG", $idLog, "**************************");
            RedsysHelper::escribirLog("INFO ", $idLog, "****** NUEVO PEDIDO ******");
            RedsysHelper::escribirLog("DEBUG", $idLog, "**************************");
    
            RedsysHelper::escribirLog("INFO ", $idLog, "Pago con Tarjeta redirección", null, __METHOD__);
            RedsysHelper::escribirLog("INFO ", $idLog, "ID del usuario cargado: " . $userId, null, __METHOD__);
    
            if ($isLogged == true)
                RedsysHelper::escribirLog("INFO ", $idLog, "El usuario que hace el pedido está logueado en la página", null, __METHOD__);
            else
                RedsysHelper::escribirLog("INFO ", $idLog, "El usuario que hace el pedido no está logueado en la página", null, __METHOD__);
    
            $redirectUrl = $this->redirect_url . "&order_id=" . $order_id;
    
            RedsysHelper::escribirLog("DEBUG", $idLog, "Redireccionando a " . $redirectUrl . " para continuar...", null, __METHOD__);
        }

        // Return redirect page
        return array(
            'result' 	=> 'success',
            'redirect'	=> $redirectUrl
        );
    }

    function get_redsys_object($order){
        $order_id = $order->get_id();

        $orderIdLog = $order_id . $this->merchant->fuc;
        $idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Generando formulario para el pedido " . $order_id, null, __METHOD__);
        
        $merchantModule = RedsysHelper::getMerchantModule(REDSYSPUR_MODULE_VERSION);

        RedsysHelper::escribirLog("DEBUG", $idLog, "Versión del módulo: " . $merchantModule, null, __METHOD__);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de Wordpress: " . $GLOBALS['wp_version'], null, __METHOD__);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de WooCommerce: " . WC_VERSION, null, __METHOD__);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de PHP: " . phpversion(), null, __METHOD__);


        //Recuperamos los datos de config.
        $logActivo=$this->activar_log;
        $nombre=$this->nombre;
        $codigo=$this->merchant->fuc;
        $terminal=$this->merchant->terminal;
        $moneda=$this->moneda;
        $claveFirma=$this->merchant->kc;	
        $tipopago=intval($this->tipopago);
        $idioma=$this->idioma;
        $entorno=$this->merchant->env;

        //Callback
        $urltienda = $this -> notify_url;

        if ($this->notificacion_get == 'yes') {

            $urlok = $this->notify_url;
            $urlko = $this->notify_url;

        } else {

            $returnURL_OK = $order->get_checkout_order_received_url();
            $returnURL_OK = (strpos($returnURL_OK, 'http') !== 0) ? home_url($returnURL_OK) : $returnURL_OK;

            $returnURL_KO = ($this->cancelar_en_error == 'yes') ? $order->get_cancel_order_url() : $order->get_checkout_payment_url();
            $returnURL_KO = (strpos($returnURL_KO, 'http') !== 0) ? home_url($returnURL_KO) : $returnURL_KO;

            $urlok = $this->urlOK ? $this->urlOK : $returnURL_OK;
		    $urlko = $this->urlKO ? $this->urlKO : $returnURL_KO;
        }

        RedsysHelper::escribirLog("DEBUG", $idLog, "URL para operaciones correctas  : " . $urlok, null, __METHOD__);
        RedsysHelper::escribirLog("DEBUG", $idLog, "URL para operaciones incorrectas: " . $urlko, null, __METHOD__);

        //Calculo del precio total del pedido
        $currency_decimals = $this->decimales_moneda;

        $transaction_amount = number_format( (float) ($order->get_total()), intval($currency_decimals), '.', '' );
        $transaction_amount = str_replace('.','',$transaction_amount);
        $transaction_amount = floatval($transaction_amount);

        // Descripción de los productos
        $productos="";
        $products = WC()->cart->cart_contents;
        foreach ($products as $product) {
            $productos .= $product['quantity'].'x'.$product['data']->get_title().'/';
        }

        $numpedido = RedsysHelper::generaNumeroPedido($order_id, $this->genPedido, $this->pedidoExtendido == 'yes'); 
        RedsysHelper::escribirLog("INFO ", $idLog, "Numero de pedido enviado a Redsys ─ [Ds_Merchant_Order]: " . $numpedido, null, __METHOD__);

        $idiomaFinal = RedsysHelper::getLanguage($this->idioma == 'yes');

        $merchantTitular = RedsysHelper::createMerchantTitular($order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_billing_email());
        $merchantData = RedsysHelper::createMerchantData($this->moduleComent, $order_id);

        // Generamos la firma	
        $params = new Parameters();
        $params->amount = $transaction_amount;
        $params->order = $numpedido;
        $params->fuc = $codigo;
        $params->currency = $moneda;
        $params->transactionType = $tipopago;
        $params->terminal = $terminal;
        $params->merchantUrl = $urltienda;
        $params->urlOk = $urlok;
        $params->urlKo = $urlko;
        $params->consumerLanguage = $idiomaFinal;
        $params->productDescription = $productos;
        $params->cardholderName = $merchantTitular;
        $params->merchantName = $nombre;
        $params->payMethods = "";
        $params->module = $merchantModule;
        $params->merchantData = Utils::base64_url_encode_safe($merchantData);

        ///// 3DSecure
        if ($this->activar_3ds == 'yes')
            include_once 'redsys_3ds.php';

        return $params;
    }

    function generate_redsys_form( $order_id ) {
        $order = new WC_Order($order_id);
        $params = $this->get_redsys_object($order);

        $orderIdLog = $order_id . $this->merchant->fuc;
        $idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);
        
        //Datos de configuración
        $redsys_args = RequestData::generate($this->merchant, $params, RedsysHelper::$SIGNATURE_VERSION);

        // Se generan los parámetros de la petición
        $redsys_options_args['Ds_MerchantParameters_New'] = $redsys_args['Ds_MerchantParameters'];
        $redsys_options_args['Ds_Signature_New'] = $redsys_args['Ds_Signature'];

        $redsys_options_args['Ds_MerchantParameters_SaveRef'] = "";
        $redsys_options_args['Ds_Signature_SaveRef'] = "";
        $redsys_options_args['Ds_MerchantParameters_WithRef'] = "";
        $redsys_options_args['Ds_Signature_WithRef'] = "";

        $hasReference = false;
        $allowReference = $this->withref == 'yes' && is_user_logged_in();
		$maskedCard = '';
        if($allowReference){
            $params->tokenizedCard = "REQUIRED";
            $redsys_args_saveref = RequestData::generate($this->merchant, $params, RedsysHelper::$SIGNATURE_VERSION);

            $redsys_options_args['Ds_MerchantParameters_SaveRef'] = $redsys_args_saveref['Ds_MerchantParameters'];
            $redsys_options_args['Ds_Signature_SaveRef'] = $redsys_args_saveref['Ds_Signature'];

            $idCustomer = $order->get_customer_id();
            $ref = WC_Redsys_Ref::getCustomerRef($idCustomer, $idLog);

            $hasReference = ($ref != null);
            if($hasReference){
                $params->tokenizedCard = $ref['reference'];
                $redsys_args_withref = RequestData::generate($this->merchant, $params, RedsysHelper::$SIGNATURE_VERSION);

                $redsys_options_args['Ds_MerchantParameters_WithRef'] = $redsys_args_withref['Ds_MerchantParameters'];
                $redsys_options_args['Ds_Signature_WithRef'] = $redsys_args_withref['Ds_Signature'];
				$maskedCard = $ref['cardNumber'];
            }
        }

        if (!$hasReference) { //LOGGING DE PARÁMETROS
            if ($allowReference) { //Solicitud de referencia.
                RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la solicitud: " . $redsys_options_args['Ds_MerchantParameters_SaveRef'], null, __METHOD__ );
                RedsysHelper::escribirLog("DEBUG", $idLog, "Firma calculada y enviada : " . $redsys_options_args['Ds_Signature_SaveRef'], null, __METHOD__ );
            } else { // Pago normal con tarjeta.
                RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la solicitud: " . $redsys_options_args['Ds_MerchantParameters_New'], null, __METHOD__ );
                RedsysHelper::escribirLog("DEBUG", $idLog, "Firma calculada y enviada : " . $redsys_options_args['Ds_Signature_New'], null, __METHOD__ );
            }
        } else { //Pago con referencia.
            RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la solicitud: " . $redsys_options_args['Ds_MerchantParameters_WithRef'], null, __METHOD__ );
            RedsysHelper::escribirLog("DEBUG", $idLog, "Firma calculada y enviada : " . $redsys_options_args['Ds_Signature_WithRef'], null, __METHOD__ );
        }

        //Se establece el entorno del SIS
        $env = Endpoints::$redirect[$this->merchant->env];

        //Formulario que envía los datos del pedido y la redirección al formulario de acceso al TPV
        $withRef = (bool) WC()->session->get( 'redirect_option_with_ref', false );
        $saveRef = (bool) WC()->session->get( 'redirect_option_save_ref', false );

        RedsysHelper::escribirLog("DEBUG", $idLog, "Configuración del pago por referencia (USAR / GUARDAR): [" . var_export($withRef, true) ." / " . var_export($saveRef, true) ."]", null, __METHOD__ );

        if($withRef){
            $redsys_args['Ds_MerchantParameters'] = $redsys_options_args['Ds_MerchantParameters_WithRef'];
            $redsys_args['Ds_Signature'] = $redsys_options_args['Ds_Signature_WithRef'];
        }else{
            if($saveRef){
                $redsys_args['Ds_MerchantParameters'] = $redsys_options_args['Ds_MerchantParameters_SaveRef'];
                $redsys_args['Ds_Signature'] = $redsys_options_args['Ds_Signature_SaveRef'];
            }else{
                $redsys_args['Ds_MerchantParameters'] = $redsys_options_args['Ds_MerchantParameters_New'];
                $redsys_args['Ds_Signature'] = $redsys_options_args['Ds_Signature_New'];
            }
        }

        $template_vars = [
            'env' => $env,
            'Ds_SignatureVersion' => $redsys_args['Ds_SignatureVersion'],
            'Ds_MerchantParameters' => $redsys_args['Ds_MerchantParameters'],
            'Ds_Signature' => $redsys_args['Ds_Signature'],
            'js' => REDSYSPUR_URL . "/pages/assets/js/redirect.js",
        ];

        include(REDSYSPUR_PATH.'/pages/templates/redirect.php');
    }

    function session_vars(){
        if(array_key_exists('withRef', $_POST)){
            WC()->session->set( 'redirect_option_with_ref', $_POST['withRef'] == 'true' );
        }
        if(array_key_exists('saveRef', $_POST)){
            WC()->session->set( 'redirect_option_save_ref', $_POST['saveRef'] == 'true' );
        }
        if(array_key_exists('withQR', $_POST)){
            WC()->session->set( 'with_qr', $_POST['withQR'] == 'true' );
        }
    }

    function redirect(){
        header("Content-Type: text/html");
        
        if ( isset( $_GET['order_id'] ) ) {
            $order_id = $_GET['order_id'];
            $this->generate_redsys_form($order_id);
        }
        exit;
    }

    function delete_ref(){
        $idCustomer = get_current_user_id();
        WC_Redsys_Ref::deleteRef($idCustomer);

        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    }

    function back_from_qr(){
        if(array_key_exists('orderId', $_POST) && array_key_exists('status', $_POST)){
            $order = new WC_Order($_POST['orderId']);

            if ($this->notificacion_get == 'yes') {

                $urlok = $this->notify_url;
                $urlko = $this->notify_url;

            } else {

                $returnURL_OK = $order->get_checkout_order_received_url();
                $returnURL_KO = ($this->cancelar_en_error == 'yes') ? $order->get_cancel_order_url() : $order->get_checkout_payment_url();

                $urlok = $this->urlOK ? $this->urlOK : $returnURL_OK;
                $urlko = $this->urlKO ? $this->urlKO : $returnURL_KO;
            }

            if($_POST['status'] == "OK"){
                $redirectUrl = $urlok;
            }else{
                $redirectUrl = $urlko;
            }

            echo(json_encode(array(
                "redirectUrl" => $redirectUrl,
            )));
        }
        exit();
    }

    function get_modal_parameters($order){
        $params = $this->get_redsys_object($order);

        $modalParams = RequestData::generate($this->merchant, $params, RedsysHelper::$SIGNATURE_VERSION); 

        $modalParams['url_modal'] = RedsysHelper::getModalJs($this->merchant->env);
        $modalParams['environment_modal'] = strtolower($this->merchant->env);

        $urlOK = $this->urlOK;
        $urlKO = $this->urlKO;
        
        $modalParams['url_ok'] = $urlOK ? $urlOK : $order->get_checkout_order_received_url();
        $modalParams['url_ko'] = $urlKO ? $urlKO : $order->get_cancel_order_url();

        return $modalParams;
    }

    function get_qr_parameters($order){
        $params = $this->get_redsys_object($order);

        $qrParams = RequestData::generate($this->merchant, $params, RedsysHelper::$SIGNATURE_VERSION);

        $env = Endpoints::$redirect[$this->merchant->env];

        //TODO: Eliminar la siguiente linea cuando este subido a TEST
        $env = str_replace("sis-t.redsys.es", "sis-i.redsys.es", $env);

        $env = str_replace("realizarPago", "realizarPagoQR", $env);
        $env = str_replace("/utf-8", "", $env); //TODO: Es necesario sacar el utf-8?

        $qrParams['env'] = $env;

        return $qrParams;
    }

    function get_next_order_id(){
        global $wpdb;
        global $woocommerce;

        $order = wc_get_order($woocommerce->session->order_awaiting_payment);
        if($woocommerce->session->order_awaiting_payment && $order->get_status() == 'pending'){
            return $woocommerce->session->order_awaiting_payment;
        }else{
            $statuses = array_keys(wc_get_order_statuses());
            $statuses = implode( "','", $statuses );
        
		// Getting last Order ID (max value)

            switch ($this->tabla_ordenes) {

                case 0:
                    $results = $wpdb->get_col( "
                        SELECT MAX(ID)+1 FROM {$wpdb->prefix}posts
                        WHERE post_type LIKE 'shop_order'
                        AND post_status IN ('$statuses')
                    " );
                    break;
                    
                case 1:
                    $results = $wpdb->get_col( "
                        SELECT MAX(id)+1 FROM {$wpdb->prefix}wc_orders
                    " );
                    break;

                default:
                    return;

            }

            return reset($results);
        }
    }

	function payment_fields_api(){
		$this->payment_fields();
		exit;
	}

    function payment_fields(){
        $html = $this->get_description();

        if( false ) { //ELIMINACION DEL FORMULARIO MODAL
            $post_data = [];
            if(isset($_POST['post_data'])){
                parse_str($_POST['post_data'], $post_data);
            }

            if(!$post_data || !filter_var($post_data['billing_email'], FILTER_VALIDATE_EMAIL) || !$post_data['billing_email'] || !$post_data['billing_first_name'] || !$post_data['billing_last_name']){
                echo("Por favor, rellene los datos de facturación");
                return;
            }

            //Create fake order
            $order = new WC_Order();

            $order_id = (is_array($post_data) && array_key_exists('order_id', $post_data)) ? $post_data['order_id'] : $this->get_next_order_id();
            $order->set_id($order_id);
            $order->set_total(WC()->cart->total);

            $order->set_billing_email($post_data['billing_email']);
            $order->set_billing_first_name($post_data['billing_first_name']);
            $order->set_billing_last_name($post_data['billing_last_name']);

            $params = $this->get_modal_parameters($order);

            $template_vars = [
                'url_modal' => $params['url_modal'],
                'environment_modal' => $params['environment_modal'],
                'url_ok' => $params['url_ok'],
                'url_ko' => $params['url_ko'],
                'Ds_SignatureVersion' => $params['Ds_SignatureVersion'],
                'Ds_MerchantParameters' => $params['Ds_MerchantParameters'],
                'Ds_Signature' => $params['Ds_Signature'],
                'js' => REDSYSPUR_URL . "/pages/assets/js/paymentmodal.js",
            ];

            include(REDSYSPUR_PATH.'/pages/templates/paymentmodal.php');
        }else if($this->qr == 'yes' && $this->has_checkout_blocks()){
            $post_data = [];
            if(isset($_POST['post_data'])){
                parse_str($_POST['post_data'], $post_data);
            }

            if(!$post_data || !filter_var($post_data['billing_email'], FILTER_VALIDATE_EMAIL) || !$post_data['billing_email'] || !$post_data['billing_first_name'] || !$post_data['billing_last_name']){
                echo("Por favor, rellene los datos de facturación");
                return;
            }

            //Create fake order
            $order = new WC_Order();

            $order_id = (is_array($post_data) && array_key_exists('order_id', $post_data)) ? $post_data['order_id'] : $this->get_next_order_id();
            $order->set_id($order_id);
            $order->set_total(WC()->cart->total);

            $order->set_billing_email($post_data['billing_email']);
            $order->set_billing_first_name($post_data['billing_first_name']);
            $order->set_billing_last_name($post_data['billing_last_name']);

            $params = $this->get_qr_parameters($order);

            $template_vars = [
                'env' => $params['env'],
                'Ds_SignatureVersion' => $params['Ds_SignatureVersion'],
                'Ds_MerchantParameters' => $params['Ds_MerchantParameters'],
                'Ds_Signature' => $params['Ds_Signature'],
                'orderId' => $order_id,
                'backFromQRUrl' => $this->back_from_qr_url,
                'sessionVarsUrl' => $this->session_vars_url,
                'js' => REDSYSPUR_URL . "/pages/assets/js/paymentqr.js",
            ];

            include(REDSYSPUR_PATH.'/pages/templates/paymentqr.php');
        }else if($this->withref == 'yes' && is_user_logged_in() && $this->has_checkout_blocks()){
            $post_data = [];
            if(isset($_POST['post_data'])){
                parse_str($_POST['post_data'], $post_data);
            }

            if(!$post_data || !filter_var($post_data['billing_email'], FILTER_VALIDATE_EMAIL) || !$post_data['billing_email'] || !$post_data['billing_first_name'] || !$post_data['billing_last_name']){
                echo("Por favor, rellene los datos de facturación");
                return;
            }

            //Create fake order
            $order = new WC_Order();

            $order_id = (is_array($post_data) && array_key_exists('order_id', $post_data)) ? $post_data['order_id'] : $this->get_next_order_id();
            $order->set_id($order_id);
            $order->set_total(WC()->cart->total);

            $order->set_billing_email($post_data['billing_email']);
            $order->set_billing_first_name($post_data['billing_first_name']);
            $order->set_billing_last_name($post_data['billing_last_name']);

            $orderIdLog = $order_id . $this->merchant->fuc;
            $idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

            $hasReference = false;
            $allowReference = $this->withref == 'yes' && is_user_logged_in();
            $maskedCard = '';
            $brand_front = 'Tarjeta';
            if($allowReference){    
                $idCustomer = get_current_user_id();
                $ref = WC_Redsys_Ref::getCustomerRef($idCustomer, $idLog);
    
                $hasReference = ($ref != null);
                if($hasReference){
                    $maskedCard = $ref['cardNumber'];

                    switch($ref['brand']) {
                        case 1:
                            $brand_front = 'VISA';
                            break;
                        case 2:
                            $brand_front = 'Mastercard';
                            break;
                    }        
                }
            }

            $template_vars = [
                'maskedCard' => $maskedCard,
                'brand' => $brand_front,
                'sessionVarsUrl' => $this->session_vars_url,
                'deleteRefUrl' => $this->delete_ref_url,
                'js' => REDSYSPUR_URL . "/pages/assets/js/redirect_options.js",
            ];

            include(REDSYSPUR_PATH.'/pages/templates/redirect_options.php');
        }else{
            echo($html);
        }
    }

    function check_rds_response() {

        try {

            /** Identificamos que la petición ha llegado hasta el validador. */
            http_response_code(100);
            /** Se crea el objeto principal de la clase. */
            $redsys = new WC_Redsys;

            /** Se recogen los datos de entrada. **/
            $dsSignatureVersion   = $_POST["Ds_SignatureVersion"] ?? $_GET["Ds_SignatureVersion"] ?? false;
            $dsMerchantParameters = $_POST["Ds_MerchantParameters"] ?? $_GET["Ds_MerchantParameters"] ?? false;
            $dsSignature          = $_POST["Ds_Signature"] ?? $_GET["Ds_Signature"] ?? false;

            /** Se comprueba si la URL ha entrado con parámetros. */
            if (!$dsMerchantParameters or !$dsSignature) {

                http_response_code(400);

                if ($_SERVER['REQUEST_METHOD'] === 'POST')
                    die ('La URL de notificación o del retorno de navegación no contiene parámetros válidos, por lo que no se puede redireccionar de nuevo a la tienda. Revisa tu historial de pedidos accediendo a la tienda de nuevo y en caso de duda contacta con el comercio.');
                else
                    wp_redirect(home_url());
            }
            
            /** Se decodifican los datos enviados **/
            $tmp = array(
                'Ds_SignatureVersion' => $dsSignatureVersion,
                'Ds_MerchantParameters' => $dsMerchantParameters,
                'Ds_Signature' => $dsSignature,
            );
            $params = Parameters::digest($this->merchant, $tmp);

            /** Se inicializan los objetos necesarios para crear los registros de log. **/
            $merchantData = Utils::base64_url_decode_safe($params->merchantData);
            $merchantData = json_decode( $merchantData ); 

            $idCart = $merchantData->idCart;
            $pedido = $params->order; 

            $orderIdLog = $idCart . $redsys->merchant->fuc;
            $idLog = RedsysHelper::generateIdLog($redsys->activar_log, $redsys->logString, $orderIdLog);

            /** Se identifica la operacion en el registro. */
            if (!empty($_POST))
                RedsysHelper::escribirLog("INFO ", $idLog, "***** VALIDACIÓN DE LA NOTIFICACIÓN  ──  PEDIDO " . $params->order . " *****");
            else
                RedsysHelper::escribirLog("INFO ", $idLog, "***** RETORNO DE NAVEGACIÓN  ──  PEDIDO " . $params->order . " *****");

            /** Obtenemos la clave local. **/
            $claveComercio = $redsys->merchant->kc;

            RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la notificación : " . $dsMerchantParameters);
            RedsysHelper::escribirLog("DEBUG", $idLog, "Firma recibida del TPV Virtual: " . $dsSignature);

            /** Comprobacion de la firma y rechazo del procesamiento si no coinciden. */
            if (!WC_Redsys::validarFirma($params, $dsMerchantParameters, $dsSignature, $claveComercio, $dsSignatureVersion, $idLog)) {
                
                http_response_code(403);
                RedsysHelper::escribirLog("ERROR", $idLog, "Las firmas no coinciden, la notificación se rechazará con error HTTP 403.");
                die ('La petición no puede ser atendida porque las firmas no coinciden.');
            }

            /** Se obtiene cuál es el estado configurado como "estado final" en la configuración del módulo. */
            $estadoFinal = $redsys->estado;

            // if(!wc_get_order($idCart)) {

            //     $idCart++;
            //     RedsysHelper::escribirLog("INFO ", $idLog, "Se ha actualizado el idCart a $idCart al haber fallado la obtención de orden con el número enviado a Redsys.", null, __METHOD__);
            // }
            
            /** Se crea el objeto order para poder procesar la notificación. */
            $order = new WC_Order($idCart);
                        
            /** Control de navegación en caso de que el cliente sea redirigido al validation. */
            /** Se accede sólo si el POST está vacío pero sí que tenemos merchantParameters. */
            if (empty($_POST) and $dsMerchantParameters) {
                                
                RedsysHelper::escribirLog("INFO ", $idLog, "Cliente redirigido al validador a través del retorno de navegación.");

                /** URL adonde redirigiremos al cliente. */
                if ((int)$params->codResponse <= 100){
                    $urlRedirect = $order->get_checkout_order_received_url();
                    $urlRedirect = (strpos($urlRedirect, 'http') !== 0) ? home_url($urlRedirect) : $urlRedirect;
                }else{
                    $urlRedirect = ($redsys->cancelar_en_error == 'yes') ? $order->get_cancel_order_url() : $order->get_checkout_payment_url();
                    $urlRedirect = (strpos($urlRedirect, 'http') !== 0) ? home_url($urlRedirect) : $urlRedirect;
                }

                /** Se evalúa si se necesita procesar la notificación usando parámetros GET comprobando si getOrderByCartId nos devuelve un pedido. Si lo hiciera, el pedido existe y no hay que validar. */
                if (!WC_Redsys_Order::getNotificationProcessed($order->id) // Se evalua que el pedido no este pagado o en un estado final.
                    and ($redsys->notificacion_get == 'yes') // Se evalua que este activada la opcion que permite validar el pedido con los parametros GET.
                    and (($redsys->cancelar_en_error == 'yes') or $params->codResponse <= 100) // No se entra si se desactivo la opción de cancelar en caso de error, pero si en caso de que la respuesta sea que se ha pagado el pedido.
                ) {

                    RedsysHelper::escribirLog("INFO ", $idLog, "Se van utilizar los datos recibidos vía GET para validar el pedido " . $pedido . " porque notificacion_get es " . $redsys->notificacion_get);

                    /** Si la validación sale mal, fijamos el checkout como la URL a la que redirigir. */
                    if (!WC_Redsys::confirmarPedido($params, $redsys, $order, $merchantData, $pedido, $estadoFinal, $idLog))
                        $urlRedirect = $order->get_cancel_order_url();    

                    $order->add_order_note( __('[REDSYS] El pedido se ha registrado usando los parámetros incluidos en el retorno de navegación.', REDSYSPUR_DOMAIN) . $metodoOrder);
                }
                
                RedsysHelper::escribirLog("DEBUG", $idLog, "Redireccionando cliente a: " . $urlRedirect);

                http_response_code(308);
                wp_redirect($urlRedirect);

                exit();
            }

            /** Evaluamos si el pedido ya está creado, y si es así, registramos que no lo tenemos que tocar. */
            if (WC_Redsys_Order::getNotificationProcessed($order->id)) {
				
				http_response_code(422);
				RedsysHelper::escribirLog("ERROR", $idLog, "Se ha recibido una notificación pero la orden ya está creada.");
				
				die("Se ha recibido una notificación pero la orden ya está creada.");
            
			} else {

				/** Ejectuamos la lógica de confirmación del pedido. */
                if (($redsys->cancelar_en_error == 'yes') or $params->codResponse <= 100)
                    WC_Redsys::confirmarPedido($params, $redsys, $order, $merchantData, $pedido, $estadoFinal, $idLog);
                else {
                    http_response_code(200);
                    RedsysHelper::escribirLog("INFO ", $idLog, "No se genera el pedido debido a la configuración del módulo. Respuesta del SIS: " . $params->codResponse, null, __METHOD__);
                }
                
                exit();
			}

        } catch (\Exception $e) {
            
            http_response_code(500);
            RedsysHelper::escribirLog("ERROR", "0000000000000000000000000ERROR", "Excepción en la validación: ".$e->getMessage());

            die("Excepcion en la validacion.");
        }
    }

    public static function confirmarPedido($params, $redsys, $order, $merchantData, $pedido, $estadoFinal, $idLog = null) {

        /** Se extraen todos los datos de la notificación. **/
        $total            = (int)$params->amount;  
        $idCart           = $merchantData->idCart;
        $codigo           = (int)$params->fuc;
        $terminal         = (int)$params->terminal;
        $moneda           = (int)$params->currency;
        $respuesta        = $params->codResponse;
        $authCode         = $params->authorisationCode;
        $tipoTransaccion  = (int)$params->transactionType;
        $metodo           = (int)$params->processedPayMethod;

        $metodoOrder = "N/A";

        if ($respuesta < 101)
            $metodoOrder = "Autorizada " . $authCode;    
        else if ($respuesta >= 101)
            $metodoOrder = "Denegada " . $respuesta;

        /** Se escriben en el registro los datos recibidos. */
        RedsysHelper::escribirLog("DEBUG", $idLog, "ID del Carrito: " . $idCart);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Codigo Comercio FUC: " . $codigo);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Terminal: " . $terminal);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Moneda: " . $moneda);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Codigo de respuesta del SIS: " . $respuesta);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Método de Pago: " . $metodo);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Información adicional del módulo: " . $merchantData->moduleComent);

        switch($tipoTransaccion) {

            case 0:
                $estadoFinal = $redsys->estado;
                break;

            case 1:
                $estadoFinal = $redsys->estado_preautorizacion;
                break;

            case 7:
                $estadoFinal = $redsys->estado_autenticacion;
                break;
        }

        /** Análisis de respuesta del SIS. */
        $lang = empty($params->consumerLanguage) ? 1 : $params->consumerLanguage;
        $errorBackofficeSIS = Resources::getDescriptionResposeCode($params->codResponse, $lang);
        
        $authCode = str_replace("+", "", $authCode);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Código de Autorización: " . $authCode);

        /** Advertimos si es una preautorización que el pedido aún no tiene valor contable. */
        if ($tipoTransaccion == 1)
            $order->add_order_note( __('[REDSYS] Esta orden se ha validado usando una PREAUTORIZACIÓN. Recuerda que para que tenga efecto contable, deberás ejecutar la confirmación desde la página de la orden o desde el Portal de Adminstración del TPV Virtual.', REDSYSPUR_DOMAIN));

        /** Se valida el pedido cuando la operación es genuina y válida. */    
        if(!empty($respuesta) && (int)$respuesta < 101){

            /** Se marca como completada para WooCommerce y se reduce el stock. */
            if ($tipoTransaccion == 0) $order->payment_complete();

            /** Añadimos los mensajes de información a la orden. */
            $order->add_order_note( __('[REDSYS] ', REDSYSPUR_DOMAIN) . $metodoOrder);
            $order->add_order_note( __('[REDSYS] Respuesta del SIS: ', REDSYSPUR_DOMAIN) . $errorBackofficeSIS);
            $order->add_order_note( __('[REDSYS] El pedido es válido y se ha registrado correctamente. Número de pedido enviado a Redsys: ', REDSYSPUR_DOMAIN ) . $pedido);
            if($estadoFinal && $estadoFinal != $order->get_status()) {
                $order->update_status($estadoFinal);
            }

            WC()->cart->empty_cart();
            
            /** Guardamos la reeferencia si en la notificación está incluida. */
            $merchantIdentifier = $params->tokenizedCard;
            
            if($redsys->withref == 'yes' and $merchantIdentifier!=null) {
                
                $idCustomer = $order->get_customer_id();
                $cardNumber=$params->cardNumber;
                $brand=$params->cardBrand;
                $cardType=$params->cardType;
                $expiryDate=$params->cardExpiryDate;
                
                WC_Redsys_Ref::saveReference($idCustomer, $merchantIdentifier, $cardNumber, $brand, $cardType, $expiryDate, $idLog, $idCart);
                RedsysHelper::escribirLog("INFO ", $idLog, "Se ha guardado una referencia para la tarjeta " . str_pad(substr($cardNumber, -4, 4), 5, "*", STR_PAD_LEFT), null, __METHOD__);
            }
            
            /** Se guarda el ID para posteriores operaciones sobre la orden. */
            WC_Redsys_Order::saveOrderDetails($order->id, $pedido, $tipoTransaccion, $total, $idLog);

            /** E imprimimos el resultado en el registro. */
            RedsysHelper::escribirLog("INFO ", $idLog, "El pedido con ID de carrito " . $idCart . " (" . $pedido . "), y número de orden $order->id es válido y se ha registrado correctamente.", null, __METHOD__);
            
            echo "Pedido validado con éxito ── " . $errorBackofficeSIS;
            http_response_code(200);

            return(1);
        
        } else {

            /** Si se ha producido un error, mostramos que ha habido un error. */

            $order = new WC_Order($idCart);
            $order->add_order_note( __('[REDSYS] ', REDSYSPUR_DOMAIN) . $metodoOrder);
            $order->add_order_note( __('[REDSYS] Respuesta del SIS: ', REDSYSPUR_DOMAIN) . $errorBackofficeSIS);
            $order->update_status('cancelled',__( '[REDSYS] El pedido ha finalizado con errores. Número de pedido enviado a Redsys: ', REDSYSPUR_DOMAIN ) . $pedido);
            WC()->cart->empty_cart();

            RedsysHelper::escribirLog("ERROR", $idLog, "El pedido con ID de carrito " . $idCart . " (" . $pedido . "), y número de orden $order->id ha finalizado con errores.");
            RedsysHelper::escribirLog("ERROR", $idLog, $errorBackofficeSIS);
           
            echo "El pedido ha finalizado con errores ── " . $errorBackofficeSIS;
            http_response_code(200);

            return(0);
        }
    }

    public static function validarFirma($params, $dsMerchantParameters, $dsSignature, $claveComercio, $dsSignatureVersion, $idLog = false) {
        $dsSignatureLOCAL = Signature::createMerchantSignature($claveComercio, $dsMerchantParameters, $params->order, $dsSignatureVersion);
        
        RedsysHelper::escribirLog("DEBUG", $idLog, "Firma calculada notificación  : " . $dsSignatureLOCAL);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Firma calculada usando la clave de encriptación [" . $dsSignatureVersion . "] " . substr($claveComercio, 0, 3) . "*");

        if ($dsSignature === $dsSignatureLOCAL)
            return true;
        else
            return false;
    }

    function receipt_page( $order_id ) {
        if( false ) { // ELIMINACION DEL FORMULARIO MODAL
            $order = new WC_Order($order_id);
            $urlOK = $this->urlOK;
            header("Location: " . ($urlOK ? $urlOK : $order->get_checkout_order_received_url()));
            
            exit();
        }
    }

	public function process_refund($order_id, $amount = 0, $reason = '', $idLog = null){
		$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $order_id, $this->tamano_log);

        $result = WC_Redsys_Order::refund($this, $order_id, $amount);

        if (isset($result['error']) && $result['error'])
            RedsysHelper::escribirLog("ERROR", $idLog, $result['error']);

		return $result['result'];
    }

    function add_order_actions($actions){
        global $theorder;

        $orderDetails = WC_Redsys_Order::getOrderDetails($theorder->get_id());

        if($orderDetails && ($orderDetails['transaction_type'] == TransactionType::$preauthorisation)){
            if($theorder->get_payment_method() == $this->id){
                $actions['confirmation_payment_action'] = __( 'Confirmar pago', REDSYSPUR_DOMAIN );
                $actions['cancellation_payment_action'] = __( 'Anular pago', REDSYSPUR_DOMAIN );
            }
        }

        if($orderDetails && ($orderDetails['transaction_type'] == TransactionType::$authentication)){
            if($theorder->get_payment_method() == $this->id){
                $actions['confirmation_payment_action'] = __( 'Confirmar pago', REDSYSPUR_DOMAIN );
            }
        }

        return $actions;
    }

    function confirmation_payment_action($order){
        $url = admin_url( 'post.php?&action=confirmation_payment&post=' . $order->id );

        header('Location: '.$url);
        die;
    }

    function cancellation_payment_action($order){
        $url = admin_url( 'post.php?&action=cancellation_payment&post=' . $order->id );

        header('Location: '.$url);
        die;
    }

    function confirmation_payment($post_id){
        $order = new WC_Order($post_id);
        $importe_restante = 0;
        $details = WC_Redsys_Order::getOrderDetails($order->id);
        $urlBack = admin_url( 'post.php?post=' . $order->id . '&action=edit' );

        if (!empty( $_POST ) ){
            $url = admin_url( 'post.php?&action=confirmation_payment&post=' . $order->id );
            $response_message = array();

            $amount = floatval($_POST['amount']);
            $response = WC_Redsys_Order::confirmation($this, $order->get_id(), $amount, null, $details['transaction_type']);
            
            $url = add_query_arg($response, $url);
            header('Location: '.$url);
        }else{
            if(isset($_GET['result'])){
                if($_GET['result']){
                    $message = '<div class="notice notice-success"><p>La transacción se ha ejecutado correctamente</p></div>';
                }else{
                    $message = '<div class="notice notice-error"><p><strong>ERROR</strong> - ' . $_GET['error'] . '</p></div>';
                }

                $botonConfirmar = '';
                $botonVolver = '<a href="'.$urlBack.'" class="button">Volver a los detalles de la orden</a>';

            } else {
                
                // $message = '<div class="notice notice-info"><p>La orden <b>#' . $order->id . '</b> se encuentra en el siguiente estado.<br>
                // <br>Importe <b>por confirmar</b>: '. ( $details['grand_total'] - $details['confirmation_amount'] - $details['cancellation_amount']) / 100 .' €.
                //     <br>Importe <b>confirmado</b>: '. ($details['confirmation_amount'] - $details['refund_amount']) / 100 .' €.
                //     <br>Importe <b>anulado</b>: '. $details['cancellation_amount'] / 100 .' €.
                //     <br>Importe <b>devuelto</b>: '. $details['refund_amount'] / 100 .' €.
                // </p></div>';

                $message = '<div class="notice notice-warning"><p>Confirme todos los datos antes de continuar con la operación.</p></div>';

                $importe_restante = ($details['grand_total'] - $details['confirmation_amount'] - $details['cancellation_amount']) / 100;
                $importe_formateado = strip_tags( wc_price( $importe_restante ) );

                $botonConfirmar = '<input type="submit" class="button button-primary" value="Confirmar '.$importe_formateado.'">';
                $botonVolver = '<a href="'.$urlBack.'" class="button">Volver a los detalles de la orden</a>';

                if (!$importe_restante) {

                    $message = '<div class="notice notice-error"><p><b>No queda importe restante por confirmar.</b> Si crees que esto es un error, revisa el Portal de Administración del TPV Virtual o contacte con su entidad bancaria.</p></div>';
                    $botonConfirmar = '';
                }
            }

            $template_vars = [
                'orderId' => $order->id,
                'redsys_order' => $details['redsys_order'],
                'importe_restante' => $importe_restante,
                'importe_formateado' => $importe_formateado,
                'urlBack' => $urlBack,
                'message' => $message,
                'botonConfirmar' => $botonConfirmar,
                'botonVolver' => $botonVolver,
            ];

            require_once( ABSPATH . 'wp-admin/admin-header.php' );
            include(REDSYSPUR_PATH.'/pages/templates/confirmationpayment.php');
            include( ABSPATH . 'wp-admin/admin-footer.php' );
        }
        exit;
    }

    function cancellation_payment($post_id){
        $order = new WC_Order($post_id);
        $importe_restante = 0;
        $details = WC_Redsys_Order::getOrderDetails($order->id);
        $urlBack = admin_url( 'post.php?post=' . $order->id . '&action=edit' );

        if (!empty( $_POST ) ){
            $url = admin_url( 'post.php?&action=cancellation_payment&post=' . $order->id );
            $response_message = array();

            $amount = floatval($_POST['amount']);
            $response = WC_Redsys_Order::cancellation($this, $order->get_id(), $amount);
            
            $url = add_query_arg($response, $url);
            header('Location: '.$url);
        }else{
            if(isset($_GET['result'])){
                if($_GET['result']){
                    $message = '<div class="notice notice-success"><p>La transacción se ha ejecutado correctamente</p></div>';
                }else{
                    $message = '<div class="notice notice-error"><p><strong>ERROR</strong> - ' . $_GET['error'] . '</p></div>';
                }

                $botonConfirmar = '';
                $botonVolver = '<a href="'.$urlBack.'" class="button">Volver a los detalles de la orden</a>';

            } else {
                
                // $message = '<div class="notice notice-info"><p>La orden <b>#' . $order->id . '</b> se encuentra en el siguiente estado.<br>
                // <br>Importe <b>por confirmar</b>: '. ( $details['grand_total'] - $details['confirmation_amount'] - $details['cancellation_amount']) / 100 .' €.
                //     <br>Importe <b>confirmado</b>: '. ($details['confirmation_amount'] - $details['refund_amount']) / 100 .' €.
                //     <br>Importe <b>anulado</b>: '. $details['cancellation_amount'] / 100 .' €.
                //     <br>Importe <b>devuelto</b>: '. $details['refund_amount'] / 100 .' €.
                // </p></div>';

                $message = '<div class="notice notice-warning"><p>Confirme todos los datos antes de continuar con la operación.</p></div>';

                $importe_restante = ($details['grand_total'] - $details['confirmation_amount'] - $details['cancellation_amount']) / 100;
                $importe_formateado = strip_tags( wc_price( $importe_restante ) );

                $botonConfirmar = '<input type="submit" class="button button-primary" value="Anular '.$importe_formateado.'">';
                $botonVolver = '<a href="'.$urlBack.'" class="button">Volver a los detalles de la orden</a>';

                if (!$importe_restante) {

                    $message = '<div class="notice notice-error"><p><b>No queda importe restante para anular.</b> Si crees que esto es un error, revisa el Portal de Administración del TPV Virtual o contacte con su entidad bancaria.</p></div>';
                    $botonConfirmar = '';
                }
            }

            $template_vars = [
                'orderId' => $order->id,
                'redsys_order' => $details['redsys_order'],
                'importe_restante' => $importe_restante,
                'importe_formateado' => $importe_formateado,
                'urlBack' => $urlBack,
                'message' => $message,
                'botonConfirmar' => $botonConfirmar,
                'botonVolver' => $botonVolver,
            ];

            require_once( ABSPATH . 'wp-admin/admin-header.php' );
            include(REDSYSPUR_PATH.'/pages/templates/cancellationpayment.php');
            include( ABSPATH . 'wp-admin/admin-footer.php' );
        }
        exit;
    }

    public function has_checkout_blocks(){
        return WC_Blocks_Utils::has_block_in_page( wc_get_page_id('checkout'), 'woocommerce/checkout' );
    }

    public function admin_menu() {
		add_menu_page('Diagnóstico Redsys', 'Diagnóstico Redsys', 'manage_options', 'redsys_diagnostico', array( $this, 'diagnostico' ), 'dashicons-analytics', 65 );
    }

    public function diagnostico(){

        $ch = curl_init(RedsysHelper::$DIAGNOSTICO_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $connectionRedsys = $httpStatusCode == 200 ? "OK" : "KO";
        $connectionRedsysMark = $httpStatusCode == 200 ? "yes" : "no";
        $connectionRedsysColor = $httpStatusCode == 200 ? "green" : "red";
    
        $template_vars = [
            'connectionRedsys' => $connectionRedsys,
            'connectionRedsysMark' => $connectionRedsysMark,
            'connectionRedsysColor' => $connectionRedsysColor,
            'phpVersion' => phpversion(),
            'serverVersion' => $_SERVER["SERVER_SOFTWARE"],
            'redsysVersion' => REDSYSPUR_MODULE_VERSION,
            'wordpressVersion' => $GLOBALS['wp_version'],
            'woocommerceVersion' => WC_VERSION,
        ];
    
        include(REDSYSPUR_PATH.'/pages/templates/diagnostico.php');
        exit;
    }
}