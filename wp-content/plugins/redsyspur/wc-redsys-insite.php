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
require_once('wc-redsys.php');
require_once('wc-redsys-ref.php');
require_once('wc-redsys-order.php');

require_once __DIR__ . '/lib/autoload.php';

use Redsys\Merchant;
use Redsys\Parameters;
use Redsys\Rest;
use Redsys\TransactionType;
use Redsys\Utils;

class WC_Redsys_Insite extends WC_Payment_Gateway {

	public $process_url;
	public $secure_redir_url;
	public $secure_redir_v2_url;
	public $secure_back_url;
	public $secure_back_v2_url;
	public $payment_fields_url;
	public $threedsmethod_url;
	public $threedsmethod_notify_url;
	public $back_from_threedsmethod_url;
	public $delete_ref_url;
	public $version;
	public $buttonLabel;
	public $nombre;
	public $tipopago;
	public $activar_log;
	public $tamano_log;
	public $estado;
	public $estado_preautorizacion;
	public $estado_autenticacion;
	public $genPedido;
	public $with3ds;
	public $idioma_tpv;
	public $withref;
	public $tabla_ordenes;
	public $iniste_partes;
	public $decimales_moneda;
	public $button_text;
	public $button_style;
	public $body_style;
	public $form_style;
	public $form_text_style;
	public $sustituir_idioma;
	public $urlOK;
	public $urlKO;
	public $moneda;
	public $merchant;
	public $logString;
	public $custom_order_statuses;

    public function __construct() {
        $this->id                 = 'redsys_insite';
        //$this->icon               = REDSYSPUR_URL . '/pages/assets/images/Redsys.png';
        $this->method_title       = __( 'inSite · Pasarela Unificada de Redsys para WooCommerce', REDSYSPUR_DOMAIN );
        $this->method_description = __( 'Permita a sus clientes pagar con tarjeta sin salir de su web usando los servicios de Redsys.', REDSYSPUR_DOMAIN );
        
		$this->process_url         = add_query_arg( 'wc-api', 'WC_redsys_process', home_url( '/' ) );
        $this->secure_redir_url    = add_query_arg( 'wc-api', 'WC_redsys_secure_redir', home_url( '/' ) );
		$this->secure_redir_v2_url = add_query_arg( 'wc-api', 'WC_redsys_secure_redir_v2', home_url( '/' ) );
        $this->secure_back_url     = add_query_arg( 'wc-api', 'WC_redsys_secure_back', home_url( '/' ) );
		$this->secure_back_v2_url  = add_query_arg( 'wc-api', 'WC_redsys_secure_back_v2', home_url( '/' ) );
		$this->threedsmethod_url   = add_query_arg( 'wc-api', 'WC_redsys_threedsmethod', home_url( '/' ) );
		$this->threedsmethod_notify_url   = add_query_arg( 'wc-api', 'WC_redsys_threedsmethod_notify', home_url( '/' ) );
		$this->back_from_threedsmethod_url  = add_query_arg( 'wc-api', 'WC_redsys_back_from_threedsmethod', home_url( '/' ) );
		$this->payment_fields_url  = add_query_arg( 'wc-api', 'WC_redsys_insite_payment_fields', home_url( '/' ) );
		$this->delete_ref_url 	   = add_query_arg( 'wc-api', 'WC_redsys_insite_delete_ref', home_url( '/' ) );

        $this->has_fields         = false;
        $this->version			  = REDSYSPUR_MODULE_VERSION;
        
        // Load the settings
        $this->init_settings();
        $this->init_form_fields();

		$this->supports           = array( 'refunds' );

        $this->title              = $this->get_option( 'title' );
        $this->description        = $this->get_option( 'description' );

        // Get settings
        $this->nombre             = $this->get_option( 'name' );
        $this->tipopago           = $this->get_option( 'tipopago' );
        $this->activar_log	  	  = $this->get_option( 'activar_log' );
		$this->tamano_log	      = $this->get_option( 'tamano_log' );
        $this->estado             = $this->get_option( 'estado' );
        $this->estado_preautorizacion = $this->get_option( 'estado_preautorizacion' );
        $this->estado_autenticacion = $this->get_option( 'estado_autenticacion' );
		$this->genPedido	      = $this->get_option( 'genPedido' );
//         $this->withdcc            = $this->get_option( 'withdcc' );
        $this->with3ds            = $this->get_option( 'with3ds' );
        $this->idioma_tpv         = $this->get_option( 'idioma_tpv' );
        $this->withref            = $this->get_option( 'withref' );
        $this->tabla_ordenes      = $this->get_option( 'tabla_ordenes' );

		$this->iniste_partes   = $this->get_option( 'iniste_partes' );
		$this->decimales_moneda   = $this->get_option( 'decimales_moneda' );
        $this->button_text        = $this->get_option( 'button_text' );
        $this->button_style       = $this->get_option( 'button_style' );
        $this->body_style         = $this->get_option( 'body_style' );
        $this->form_style         = $this->get_option( 'form_style' );
        $this->form_text_style    = $this->get_option( 'form_text_style' );
        $this->sustituir_idioma   = $this->get_option( 'sustituir_idioma' );
		$this->urlOK              = $this->get_option( 'urlOK' );
		$this->urlKO              = $this->get_option( 'urlKO' );

		//moneda a usar
        $this->moneda = RedsysHelper::getCurrencyCode(get_option('woocommerce_currency'));
		if(!empty($this->get_option('moneda_manual'))){
            $this->moneda = $this->get_option('moneda_manual');
        }

        $fuc = $this->get_option('fuc');
        $terminal = $this->get_option('terminal');
        $claveFirma = $this->get_option('claveFirma');
        $entorno = RedsysHelper::getLiteralEntorno($this->get_option('entorno'));
		
        $this->merchant = Merchant::initWithParams($fuc, $terminal, $claveFirma, $entorno);

		//idLog
        $this->logString          = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

        // Actions
		add_action( 'wp_head', array( $this, 'add_redsys_insite' ) );
        add_action( 'woocommerce_receipt_redsys_insite', array( $this, 'receipt_page' ) );
        add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        //Payment listener/API hook
        add_action( 'woocommerce_api_wc_redsys_process', array( $this, 'process_order' ) );
        add_action( 'woocommerce_api_wc_redsys_secure_redir', array( $this, 'redirect_to_tdsecure_v1' ) );
		add_action( 'woocommerce_api_wc_redsys_secure_redir_v2', array( $this, 'redirect_to_tdsecure_v2' ) );
        add_action( 'woocommerce_api_wc_redsys_secure_back', array( $this, 'back_from_tdsecure' ) );
		add_action( 'woocommerce_api_wc_redsys_secure_back_v2', array( $this, 'back_from_tdsecure_v2' ) );
		add_action( 'woocommerce_api_wc_redsys_threedsmethod', array( $this, 'threedsmethod' ) );
		add_action( 'woocommerce_api_wc_redsys_threedsmethod_notify', array( $this, 'threedsmethod_notify' ) );
		add_action( 'woocommerce_api_wc_redsys_back_from_threedsmethod', array( $this, 'back_from_threedsmethod' ) );
		add_action( 'woocommerce_api_wc_redsys_insite_payment_fields', array( $this, 'payment_fields_api' ) );
		add_action( 'woocommerce_api_wc_redsys_insite_delete_ref', array( $this, 'delete_ref' ) );
		add_filter( 'woocommerce_checkout_fields', array( $this, 'checkout_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'checkout_update_order_meta' ) );
		add_action( 'woocommerce_order_actions', array( $this, 'add_order_actions' ) );
        // Confirmation payment
        add_action( 'woocommerce_order_action_confirmation_insite_payment_action', array( $this, 'confirmation_insite_payment_action' ) );
        add_action( 'woocommerce_order_action_cancellation_insite_payment_action', array( $this, 'cancellation_insite_payment_action' ) );
        add_action( 'post_action_confirmation_insite_payment', array( $this, 'confirmation_payment' ) );
        add_action( 'post_action_cancellation_insite_payment', array( $this, 'cancellation_payment' ) );
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

	function checkout_fields( $fields ) {
		foreach($fields['billing'] as &$field){
			$field['class'][] = 'update_totals_on_change';
		}
		foreach($fields['shipping'] as &$field){
			$field['class'][] = 'update_totals_on_change';
		}			
		return $fields;
	}

	function checkout_update_order_meta($order_id){
		if ( ! empty( $_POST['redsysOrder'] ) ) {
			update_post_meta($order_id, 'redsysOrder', $_POST['redsysOrder']);
		}
	}

	function add_redsys_insite(){

		if (is_checkout() && $this->enabled) {

			echo '<!-- Script de procesamiento de pago inSite de la Pasarela Unificada de Redsys. -->';
			echo('<script src="'.RedsysHelper::getInsiteJs($this->merchant->env).'"></script>');
		}

	}

	public static function createEndpointParams($endpoint, $merchant, $params, $transactionType, $idCart, $protocolVersion = null, $idLog = null) {

		$endpoint .= "&order=".$params->order;
		$endpoint .= "&currency=".$params->currency;
		$endpoint .= "&amount=".$params->amount;
		$endpoint .= "&merchant=".$merchant->fuc;
		$endpoint .= "&terminal=".$merchant->terminal;
		$endpoint .= "&transactionType=".$transactionType;
		$endpoint .= "&idCart=".$idCart;
	   
		if (!empty($protocolVersion))
			$endpoint .= "&protocolVersion=".$protocolVersion;
		
		if (!empty($idLog))
			$endpoint .= "&idLog=".$idLog;
	   
		return $endpoint;
	}
    
    function process_order() {

		$insiteData = array(
			'idCart' => $_POST['idCart'],
			'orderId' => $_POST['orderId'],
			'orderTotal' => $_POST['orderTotal'],
			'billingFirstName' => $_POST['billingFirstName'],
			'billingLastName' => $_POST['billingLastName'],
			'email' => $_POST['email'],
			'valores3DS' => $_POST['valores3DS'],
			'operId' => $_POST['operId'],
			'useReference' => $_POST['useReference'] === "true",
			'saveReference' => $_POST['saveReference'] === "true",
		);
		
		WC()->session->set('REDSYS_insite_data', json_encode($insiteData));

		die();
    }
    
    function redirect_to_tdsecure_v1() {

		$template_vars = [
			'action' => WC()->session->get("REDSYS_urlacs"),
			'PaReq' => WC()->session->get("REDSYS_pareq"),
			'TermUrl' => WC()->session->get("REDSYS_termURL"),
			'MD' => WC()->session->get("REDSYS_md"),
			'js' => REDSYSPUR_URL . "/pages/assets/js/tdsecure_v1.js",
		];

    	WC()->session->set('REDSYS_pareq', null);
    	WC()->session->set('REDSYS_urlacs', null);
    	WC()->session->set('REDSYS_md', null);
		WC()->session->set('REDSYS_termURL', null);

    	include(REDSYSPUR_PATH.'/pages/templates/tdsecure_v1.php');
		exit;
    }

	function redirect_to_tdsecure_v2() {

		$template_vars = [
			'action' => WC()->session->get("REDSYS_urlacs"),
			'creq' => WC()->session->get("REDSYS_creq"),
			'js' => REDSYSPUR_URL . "/pages/assets/js/tdsecure_v2.js",
		];

    	WC()->session->set('REDSYS_creq', null);
    	WC()->session->set('REDSYS_urlacs', null);
		WC()->session->set('REDSYS_idCart', null);

    	include(REDSYSPUR_PATH.'/pages/templates/tdsecure_v2.php');
		exit;
    }
    
    function back_from_tdsecure() {

		$origIdCart = $_GET["idCart"];

		$orderIdLog = $_GET['order'] . $this->merchant->fuc;
    	$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		$order = new WC_Order($origIdCart);

		$params = new Parameters();
		$params->order = $_GET['order'];
		$params->amount = $_GET['amount'];
		$params->currency = $_GET['currency'];
		$params->emv3ds = array(
			"protocolVersion" => "1.0.2",
			"PARes" => $_POST ["PaRes"],
			"MD" => $_POST ["MD"],
			"threeDSInfo" => "ChallengeResponse"
		);

		RedsysHelper::escribirLog("INFO ", $idLog, "Envío del resultado de la autenticación en V1 - 2o trataPeticion", null, __METHOD__);
		$datosTerminal = clone $this->merchant;
		$datosTerminal->kc = isset($datosTerminal->kc) ? substr($datosTerminal->kc, 0, 3) . '*' : null;
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros del terminal  : " . json_encode($datosTerminal), null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la petición: " . json_encode($params), null, __METHOD__);	

		$result = Rest::generic($this->merchant, $params, $_GET['transactionType']);
		
		$paymentResult = [
			'idCart' => $origIdCart,
			'order' => $_GET['order'],
			'amount' => $_GET['amount'],
			'transactionType' => $result->data->transactionType,
			'result' => empty($result->errorCode),
			'respuestaSIS' => $result->data->codResponse,
			'merchantIdentifier' => $result->data->tokenizedCard,
			'cardNumber' => $result->data->cardNumber,
			'cardBrand' => $result->data->cardBrand,
			'cardType' => $result->data->cardType,
			'expiryDate' => $result->data->expiryDate,
		];

		set_transient('REDSYS_payment_result_' . $origIdCart, json_encode($paymentResult), 600);
			
		RedsysHelper::escribirLog("DEBUG", $idLog, "Fijado transient con paymentResult para la orden " . $origIdCart, null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Valor de paymentResult: " . json_encode($paymentResult), null, __METHOD__);
		
		$template_vars = [
			'redirect_url' => $order->get_checkout_payment_url(true),
			'js' => REDSYSPUR_URL . "/pages/assets/js/processing.js",
		];

		include(REDSYSPUR_PATH.'/pages/templates/processing.php');
		exit;
	}

	function back_from_tdsecure_v2() {

		$origIdCart=$_GET["idCart"];

		$orderIdLog = $_GET['order'] . $this->merchant->fuc;
    	$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		$order = new WC_Order($origIdCart);

		$params = new Parameters();
		$params->order = $_GET['order'];
		$params->amount = $_GET['amount'];
		$params->currency = $_GET['currency'];
		$params->emv3ds = array(
			"protocolVersion" => $_GET['protocolVersion'],
			"cres" => $_POST ["cres"],
			"threeDSInfo" => "ChallengeResponse"
		);

		RedsysHelper::escribirLog("INFO ", $idLog, "Envío del resultado de la autenticación en V2 - 2o trataPeticion", null, __METHOD__);
		$datosTerminal = clone $this->merchant;
		$datosTerminal->kc = isset($datosTerminal->kc) ? substr($datosTerminal->kc, 0, 3) . '*' : null;
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros del terminal  : " . json_encode($datosTerminal), null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la petición: " . json_encode($params), null, __METHOD__);
			
		$result = Rest::generic($this->merchant, $params, $_GET['transactionType']);

		$controlCode = RedsysHelper::generateIdLog($this->activar_log, $this->logString . $result->data->cardNumber, $orderIdLog, $this->tamano_log);

		$paymentResult = [
			'idCart' => $origIdCart,
			'order' => $_GET['order'],
			'amount' => $_GET['amount'],
			'transactionType' => $result->data->transactionType,
			'result' => empty($result->errorCode),
			'respuestaSIS' => $result->data->codResponse,
			'merchantIdentifier' => $result->data->tokenizedCard,
			'cardNumber' => $result->data->cardNumber,
			'cardBrand' => $result->data->cardBrand,
			'cardType' => $result->data->cardType,
			'expiryDate' => $result->data->expiryDate,
			'controlCode' => $controlCode,
		];

		set_transient('REDSYS_payment_result_' . $origIdCart, json_encode($paymentResult), 600);

		RedsysHelper::escribirLog("DEBUG", $idLog, "Fijado transient con paymentResult para la orden " . $origIdCart, null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Valor de paymentResult: " . json_encode($paymentResult), null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Valor de result: " . json_encode($result), null, __METHOD__);

		$template_vars = [
			'redirect_url' => $order->get_checkout_payment_url(true),
			'js' => REDSYSPUR_URL . "/pages/assets/js/processing.js",
		];

		include(REDSYSPUR_PATH.'/pages/templates/processing.php');
		exit;
	}

    function process_payment( $order_id ) {

		$order = new WC_Order($order_id);

		$insiteData = WC()->session->get('REDSYS_insite_data');
		$insiteData = json_decode($insiteData, true);

		$orderIdLog = $insiteData['idCart'] . $this->merchant->fuc;
    	$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		//Ya tenemos el order_id real
		$insiteData['orderId'] = $order_id;
		WC()->session->set('REDSYS_insite_data', json_encode($insiteData));

		if(!$insiteData){
			return array(
				'result' 	=> 'failure',
			);
		}

		$idCart = $insiteData['idCart'];
		$orderId = $insiteData['orderId'];
		$orderTotal = $insiteData['orderTotal'];
		$billingFirstName = $insiteData['billingFirstName'];
		$billingLastName = $insiteData['billingLastName'];
		$email = $insiteData['email'];
		$valores3DS = $insiteData['valores3DS'];
		$operId = $insiteData['operId'];
		$useReference = $insiteData['useReference'];
		$saveReference = $insiteData['saveReference'];

		$merchantModule = RedsysHelper::getMerchantModule(REDSYSPUR_MODULE_VERSION);

		RedsysHelper::escribirLog("DEBUG", $idLog, "Versión del módulo: " . $merchantModule, null, __METHOD__);
        RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de Wordpress: " . $GLOBALS['wp_version'], null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de WooCommerce: " . WC_VERSION, null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de PHP: " . phpversion(), null, __METHOD__);

        //Calculo del precio total del pedido
        $currency_decimals = $this->decimales_moneda;

        $transaction_amount = number_format( (float) ($orderTotal), intval($currency_decimals), '.', '' );
        $transaction_amount = str_replace('.','',$transaction_amount);
        $transaction_amount = floatval($transaction_amount);
		
		//Peticion de datos de tarjeta. (IniciaPeticion)
		$params = new Parameters();
		$params->amount = $transaction_amount;
		$params->currency = $this->moneda;
		$params->order = $idCart;
		$params->module = $merchantModule;
		if($useReference){
			$userId = get_current_user_id();
			$ref = WC_Redsys_Ref::getCustomerRef($userId, $idLog);

			$params->tokenizedCard = $ref['reference'];
		}else{
			$params->idOper = $operId;
		}

		RedsysHelper::escribirLog("INFO ", $idLog, "Solicitud de información de la tarjeta - iniciaPeticion", null, __METHOD__);
		$datosTerminal = clone $this->merchant;
		$datosTerminal->kc = isset($datosTerminal->kc) ? substr($datosTerminal->kc, 0, 3) . '*' : null;
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros del terminal  : " . json_encode($datosTerminal), null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la petición: " . json_encode($params), null, __METHOD__);

		$initialResult = Rest::genericInit($this->merchant, $params, $this->tipopago);

		RedsysHelper::escribirLog("DEBUG", $idLog, "initialResult: " . json_encode($initialResult), null, __METHOD__);

		WC()->session->set('REDSYS_insite_initial_result', $initialResult);

		if(!empty($initialResult->errorCode)){
			return array(
				'result' 	=> 'failure',
			);
		}

		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->threedsmethod_url
		);
    }

	function threedsmethod(){

		$initialResult = WC()->session->get('REDSYS_insite_initial_result');

		$threeDSServerTransID = $initialResult->data->emv3ds["threeDSServerTransID"];
		$threeDSMethodURL = $initialResult->data->emv3ds["threeDSMethodURL"] ?? null;

		if(!$threeDSMethodURL){
			header("Location: ". $this->back_from_threedsmethod_url);
			exit;
		}

		$threeDSMethodData = [
			'threeDSServerTransID' => $threeDSServerTransID,
			'threeDSMethodNotificationURL' => $this->threedsmethod_notify_url
		];

		$threeDSMethodData = json_encode($threeDSMethodData, JSON_UNESCAPED_SLASHES);
		$threeDSMethodData = Utils::base64_url_encode_safe($threeDSMethodData);
		
		$template_vars = [
			'threeDSMethodURL' => $threeDSMethodURL,
			'threeDSMethodData' => $threeDSMethodData,
			'redirect_url' => $this->back_from_threedsmethod_url,
			'js' => REDSYSPUR_URL . "/pages/assets/js/threedsmethod.js",
		];

    	include(REDSYSPUR_PATH.'/pages/templates/threedsmethod.php');
		exit;
	}

	function threedsmethod_notify(){
		
	}

	function back_from_threedsmethod(){
		$initialResult = WC()->session->get('REDSYS_insite_initial_result');

		$insiteData = WC()->session->get('REDSYS_insite_data');
		$insiteData = json_decode($insiteData, true);

		if(!$insiteData){
			return;
		}

		$idCart = $insiteData['idCart'];
		$orderId = $insiteData['orderId'];
		$orderTotal = $insiteData['orderTotal'];
		$billingFirstName = $insiteData['billingFirstName'];
		$billingLastName = $insiteData['billingLastName'];
		$email = $insiteData['email'];
		$valores3DS = $insiteData['valores3DS'];
		$operId = $insiteData['operId'];
		$useReference = $insiteData['useReference'];
		$saveReference = $insiteData['saveReference'];

		$orderIdLog = $idCart . $this->merchant->fuc;
    	$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		$order = new WC_Order($orderId);
		$order_id = $orderId;

        //Calculo del precio total del pedido
        $currency_decimals = $this->decimales_moneda;

        $transaction_amount = number_format( (float) ($orderTotal), intval($currency_decimals), '.', '' );
        $transaction_amount = str_replace('.','',$transaction_amount);
        $transaction_amount = floatval($transaction_amount);

		$merchantModule = RedsysHelper::getMerchantModule(REDSYSPUR_MODULE_VERSION);

		$merchantTitular = RedsysHelper::createMerchantTitular($billingFirstName, $billingLastName, $email);

        $productos="";
        $products = WC()->cart->cart_contents;
        foreach ($products as $product) {
            $productos .= $product['quantity'].'x'.$product['data']->get_title().'/';
        }

		//Creación de objeto para la p1etición.
		$params = new Parameters();
		$params->amount = $transaction_amount;
		$params->currency = $this->moneda;
		$params->order = $idCart;

		if($useReference){
			$userId = get_current_user_id();
			$ref = WC_Redsys_Ref::getCustomerRef($userId, $idLog);
			
			$params->tokenizedCard = $ref['reference'];
		}else{
			$params->idOper = $operId;
		}

		$params->cardholderName = $merchantTitular;
		$params->productDescription = $productos;
		$params->module = $merchantModule;
		$ip = $_SERVER['REMOTE_ADDR'] == "::1" ? "127.0.0.1" : $_SERVER['REMOTE_ADDR'];
		$params->clientIp = $ip;
		$ThreeDSParams = $valores3DS;
		$ThreeDSInfo = $initialResult->data->emv3ds["protocolVersion"];

		if ($this->with3ds == 'yes') {
			RedsysHelper::escribirLog("DEBUG", $idLog, "Se va a usar 3DSecure.", null, __METHOD__);

			$version = explode( '.', $ThreeDSInfo);			
			if ($version[0] == "1") {

				RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $ThreeDSInfo, null, __METHOD__);
				$params->emv3ds = array(
					"threeDSInfo" => "AuthenticationData",
					"protocolVersion" => "1.0.2",
					"browserAcceptHeader" => "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json",
					"browserUserAgent" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36",
				);
	
			} else {

				RedsysHelper::escribirLog("DEBUG", $idLog, "Versión de 3DSecure: " . $ThreeDSInfo, null, __METHOD__);
				$decoded3DS = json_decode(str_replace("\\","",$ThreeDSParams));

				$browserAcceptHeader = "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8,application/json";
				$browserUserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/71.0.3578.98 Safari/537.36";
				$browserJavaEnable = $decoded3DS->browserJavaEnabled;
				$browserJavaScriptEnabled = $decoded3DS->browserJavascriptEnabled;
				$browserLanguage = $decoded3DS->browserLanguage;
				$browserColorDepth = $decoded3DS->browserColorDepth;
				$browserScreenHeight = $decoded3DS->browserScreenHeight;
				$browserScreenWidth = $decoded3DS->browserScreenWidth;
				$browserTZ = $decoded3DS->browserTZ;
				$threeDSCompInd = ($_GET["threeDSCompInd"] ?? '') === "Y" ? "Y" : "N";
				$threeDSServerTransID = $initialResult->data->emv3ds["threeDSServerTransID"];
				$notificationURL = WC_Redsys_Insite::createEndpointParams($this->secure_back_v2_url, $this->merchant, $params, $this->tipopago, $order_id, $ThreeDSInfo, $idLog);
				
				$params->emv3ds = array(
					"threeDSInfo" => "AuthenticationData",
					"protocolVersion" => $ThreeDSInfo,
					"browserAcceptHeader" => $browserAcceptHeader,
					"browserUserAgent" => $browserUserAgent,
					"browserJavaEnabled" => $browserJavaEnable,
					"browserJavascriptEnabled" => $browserJavaScriptEnabled,
					"browserLanguage" => $browserLanguage,
					"browserColorDepth" => $browserColorDepth,
					"browserScreenHeight" => $browserScreenHeight,
					"browserScreenWidth" => $browserScreenWidth,
					"browserTZ" => $browserTZ,
					"threeDSServerTransID" => $threeDSServerTransID,
					"notificationURL" => $notificationURL,
					"threeDSCompInd" => $threeDSCompInd,
				);
	
			}

		} else {
			RedsysHelper::escribirLog("DEBUG", $idLog, "Se va a enviar la operación de manera no segura a través del método tradicional.", null, __METHOD__);
			$params->directPayment = true;
		}
		
		if(($this->withref == 'yes') && !$useReference && $saveReference && is_user_logged_in()) {
			RedsysHelper::escribirLog("INFO ", $idLog, "Se ha recibido una petición para guardar la referencia del cliente.", null, __METHOD__);
			$params->tokenizedCard = "REQUIRED";
		}

		RedsysHelper::escribirLog("INFO ", $idLog, "Envío de la petición al TPV Virtual tras evaluación del riesgo - 1er trataPeticion", null, __METHOD__);
		$datosTerminal = clone $this->merchant;
		$datosTerminal->kc = isset($datosTerminal->kc) ? substr($datosTerminal->kc, 0, 3) . '*' : null;
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros del terminal  : " . json_encode($datosTerminal), null, __METHOD__);
		RedsysHelper::escribirLog("DEBUG", $idLog, "Parámetros de la petición: " . json_encode($params), null, __METHOD__);

		$result = Rest::generic($this->merchant, $params, $this->tipopago);

		if (!empty($result->data->emv3ds)) {
			$ThreeDSInfo = $result->data->emv3ds["protocolVersion"];

			if ($ThreeDSInfo == "1.0.2") {

				$termURL = WC_Redsys_Insite::createEndpointParams($this->secure_back_url, $result->data, $result->data, $result->data->transactionType, $order_id, null, $idLog);

				WC()->session->set('REDSYS_pareq', $result->data->emv3ds["PAReq"]);
				WC()->session->set('REDSYS_urlacs', $result->data->emv3ds["acsURL"]);
				WC()->session->set('REDSYS_md', $result->data->emv3ds["MD"]);
				WC()->session->set('REDSYS_termURL', $termURL);

				RedsysHelper::escribirLog("DEBUG", $idLog, "URL con parámetros: " . $termURL, null, __METHOD__);

				$redirectUrl = $this->secure_redir_url;
			} else {

				WC()->session->set('REDSYS_creq', $result->data->emv3ds["creq"]);
				WC()->session->set('REDSYS_urlacs', $result->data->emv3ds["acsURL"]);

				$redirectUrl = $this->secure_redir_v2_url;
			}
		}else{
			if (!empty($result->errorCode)) {
				$paymentResult = [
					'idCart' => $orderId,
					'order' => $idCart,
					'result' => empty($result->errorCode),
					'respuestaSIS' => $result->data->codResponse,
				];
			}else{
				$paymentResult = [
					'idCart' => $orderId,
					'order' => $idCart,
					'amount' => $transaction_amount,
					'transactionType' => $result->data->transactionType,
					'result' => empty($result->errorCode),
					'respuestaSIS' => $result->data->codResponse,
					'merchantIdentifier' => $result->data->tokenizedCard,
					'cardNumber' => $result->data->cardNumber,
					'cardBrand' => $result->data->cardBrand,
					'cardType' => $result->data->cardType,
					'expiryDate' => $result->data->expiryDate,
				];
			}

			$redirectUrl = $order->get_checkout_payment_url(true);
	
			set_transient('REDSYS_payment_result_' . $order_id, json_encode($paymentResult), 600);
			
			RedsysHelper::escribirLog("DEBUG", $idLog, "Fijado transient con paymentResult para orderId " . $order_id, null, __METHOD__);
			RedsysHelper::escribirLog("DEBUG", $idLog, "Valor de paymentResult: " . json_encode($paymentResult), null, __METHOD__);
		}

		$template_vars = [
			'redirect_url' => $redirectUrl,
			'js' => REDSYSPUR_URL . "/pages/assets/js/processing.js",
		];

		include(REDSYSPUR_PATH.'/pages/templates/processing.php');
		exit;
	}

    function get_next_order_id(){
        global $wpdb;
        global $woocommerce;

		$statuses = array_keys(wc_get_order_statuses());
		$statuses = implode( "','", $statuses );
	
		// Getting last Order ID (max value)

		// $results = $wpdb->get_col( "
		// 	SELECT MAX(id)+1 FROM {$wpdb->prefix}wc_orders
		// " );

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

	function payment_fields_api(){
		$this->payment_fields();
		exit;
	}

    function payment_fields(){
		$post_data = [];
		if(isset($_POST['post_data'])){
			parse_str($_POST['post_data'], $post_data);
			$post_data = array_filter($post_data, function($v){return !is_null($v);});
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

		$orderId = $order->get_id();
		$orderTotal = $order->get_total();
		$billingFirstName = $order->get_billing_first_name();
		$billingLastName = $order->get_billing_last_name();
		$email = $order->get_billing_email();

    	$allowReference=$this->withref=="yes" && is_user_logged_in();
    	$body_style=$this->body_style;
    	$form_style=$this->form_style;
    	$form_text_style=$this->form_text_style;
    	$btnStyle=$this->button_style;
    	$btnText=$this->button_text;

    	//Objeto tipo pedido
		$numpedido = RedsysHelper::generaNumeroPedido($orderId, 2);

		$htmlPath = REDSYSPUR_PATH.'/pages/templates/paymentform.php';
    	$staticPath = REDSYSPUR_URL.'/pages/assets';

		$isLogged = is_user_logged_in();
		$userId = get_current_user_id();

		$orderIdLog = $numpedido . $this->merchant->fuc;
		$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		RedsysHelper::escribirLog("DEBUG", $idLog, "**************************");
		RedsysHelper::escribirLog("INFO ", $idLog, "****** NUEVO PEDIDO ******");
		RedsysHelper::escribirLog("DEBUG", $idLog, "**************************");

		RedsysHelper::escribirLog("INFO ", $idLog, "Pago con Tarjeta inSite", null, __METHOD__);
		RedsysHelper::escribirLog("INFO ", $idLog, "ID del usuario cargado: " . $userId, null, __METHOD__);
		RedsysHelper::escribirLog("INFO ", $idLog, "Número de pedido generado: " . $numpedido, null, __METHOD__);

		if ($isLogged == true)
			RedsysHelper::escribirLog("INFO ", $idLog, "El usuario que hace el pedido está logueado en la página", null, __METHOD__);
		else
			RedsysHelper::escribirLog("INFO ", $idLog, "El usuario que hace el pedido no está logueado en la página", null, __METHOD__);	
		
		$brandImg="";
		$refTitle="";
		$ref = null;

		if($allowReference && $isLogged){
			$ref = WC_Redsys_Ref::getCustomerRef($userId, $idLog);

			if($ref!=null && $ref['brand']!=null)
				$brandImg='<img src="'.REDSYSPUR_URL.'/pages/assets/images/brands/'.$ref['brand'].'.jpg" style="display: inline;"/>';
			
			if($ref!=null){
				$refTitle="Usar tarjeta ";
				if($ref['cardType']=="C")
					$refTitle="Usar tarjeta de crédito ";
				else
					$refTitle="Usar tarjeta de débito ";
				
				if($ref['cardNumber']!=null)
					$refTitle.=$ref['cardNumber'];
			}
		}

		$idioma_tpv = RedsysHelper::getLanguage($this->idioma_tpv == 'yes');

		if ( $this->sustituir_idioma == 'yes') {

			$htmlPath   = preg_replace('/(\.[a-z]{2,3})\/[a-z]{2}\//', '$1/', $htmlPath);
			$staticPath = preg_replace('/(\.[a-z]{2,3})\/[a-z]{2}\//', '$1/', $staticPath);
			$brandImg   = preg_replace('/(\.[a-z]{2,3})\/[a-z]{2}\//', '$1/', $brandImg);

			RedsysHelper::escribirLog("INFO ", $idLog, "Opción para eliminar el idioma de las URL de los assets activada", null, __METHOD__);
			RedsysHelper::escribirLog("DEBUG", $idLog, "Nueva URL   htmlPath: $htmlPath", null, __METHOD__);
			RedsysHelper::escribirLog("DEBUG", $idLog, "Nueva URL staticPath: $staticPath", null, __METHOD__);
			RedsysHelper::escribirLog("DEBUG", $idLog, "Nueva URL   brandImg: $brandImg", null, __METHOD__);
		}

		$errorCodeLiterales = [
			"msg1" => __("Ha de rellenar los datos de la tarjeta", "redsyspur"),
			"msg2" => __("La tarjeta es obligaoria.", "redsyspur"),
			"msg3" => __("La tarjeta ha de ser numérica.", "redsyspur"),
			"msg4" => __("La tarjeta no puede ser negativa.", "redsyspur"),
			"msg5" => __("El mes de caducidad de la tarjeta es obligatorio.", "redsyspur"),
			"msg6" => __("El mes de caducidad de la tarjeta ha de ser numérico.", "redsyspur"),
			"msg7" => __("El mes de caducidad de la tarjeta es incorrecto.", "redsyspur"),
			"msg8" => __("El año de caducidad de la tarjeta es incorrecto.", "redsyspur"),
			"msg9" => __("El año de caducidad de la tarjeta ha de ser numérico.", "redsyspur"),
			"msg10" => __("El año de caducidad de la tarjeta no puede ser negativo.", "redsyspur"),
			"msg11" => __("El código de seguridad de la tarjeta no tiene la longitud correcta.", "redsyspur"),
			"msg12" => __("El código de seguridad de la tarjeta ha de ser numérico.", "redsyspur"),
			"msg13" => __("El código de seguridad de la tarjeta no puede ser negativo.", "redsyspur"),
			"msg14" => __("El código de seguridad no es necesario para su tarjeta.", "redsyspur"),
			"msg15" => __("La longitud de la tarjeta no es correcta.", "redsyspur"),
			"msg16" => __("Debe introducir un número de tarjeta válido, sin espacios ni guiones.", "redsyspur"),
			"msg17" => __("La validación por parte del comercio es incorrecta.", "redsyspur"),
		];

		$template_vars = [
			'merchantCode' => $this->merchant->fuc,
			'merchantTerminal' => $this->merchant->terminal,
			'staticPath' => $staticPath,
			'orderTotal' => $orderTotal,
			'billingFirstName' => $billingFirstName,
			'billingLastName' => $billingLastName,
			'email' => $email,
			'idCart' => $numpedido,
			'allowReference' => $allowReference ? "true" : "false",
			'hasReference' => $ref ? "true" : "false",
			'referenceTitle' => $refTitle,
			'cardBrandLogo' => $brandImg,
			'deleteRefUrl' => $this->delete_ref_url,
			'procUrl' => $this->process_url,
			'body_style' => $body_style,
			'form_style' => $form_style,
			'form_text_style' => $form_text_style,
			'btnStyle' => $btnStyle,
			'btnText' => $btnText,
			'idioma_tpv' => $idioma_tpv,
			'insiteUnificado' => $this->iniste_partes == 'no' ? "true" : "false",
			'js' => REDSYSPUR_URL . "/pages/assets/js/paymentform.js",
			'errorText' => __('Error al cargar el formulario de pago', REDSYSPUR_DOMAIN),
			'errorCodeLiterales' => json_encode($errorCodeLiterales),
		];

		include($htmlPath);
    }

    function delete_ref(){
        $idCustomer = get_current_user_id();
        WC_Redsys_Ref::deleteRef($idCustomer);

        header("Location: " . $_SERVER["HTTP_REFERER"]);
        exit;
    }
    
    function receipt_page( $order_id ) {

		$paymentResult = get_transient('REDSYS_payment_result_' . $order_id);
		$paymentResult = json_decode($paymentResult, true);
		
		$orderIdLog = $paymentResult['order'] . $this->merchant->fuc;
    	$idLog = RedsysHelper::generateIdLog($this->activar_log, $this->logString, $orderIdLog, $this->tamano_log);

		RedsysHelper::escribirLog("INFO ", $idLog, "Procesando respuesta REST para el pedido " . $order_id, null, __METHOD__);

		if(!wc_get_order($order_id)) {
			$order_id ++;
			RedsysHelper::escribirLog("INFO ", $idLog, "Se ha actualizado el idCart a $order_id al haber fallado la obtención de orden con el número enviado a Redsys.", null, __METHOD__);
		}

        $order = new WC_Order($order_id);
		
		$urlOK = $this->urlOK;
		$urlKO = $this->urlKO;

		if($paymentResult){
			$estadoFinal = $this->estado;
			switch($paymentResult['transactionType']){
				case 0:
					$estadoFinal = $this->estado;
					break;
				case 1:
					$estadoFinal = $this->estado_preautorizacion;
					break;
				case 7:
					$estadoFinal = $this->estado_autenticacion;
					break;
			}
	
			if (!empty($paymentResult['respuestaSIS']) && (int)$paymentResult['respuestaSIS'] < 100) {
				/** Se marca como completada para WooCommerce y se reduce el stock. */
				if ($paymentResult['transactionType'] == 0) $order->payment_complete();

				$order->add_order_note( __('[REDSYS] Respuesta del SIS: ', REDSYSPUR_DOMAIN) . $paymentResult['respuestaSIS']);
				$order->add_order_note( __('[REDSYS] El pedido es válido y se ha registrado correctamente. Número de pedido enviado a Redsys: ', REDSYSPUR_DOMAIN ) . $paymentResult['order']);
				if($estadoFinal && $estadoFinal != $order->get_status()) {
					$order->update_status($estadoFinal);
				}

//				RedsysHelper::escribirLog("INFO ", $idLog, "Pedido que debería ser el ORDER ID: " . (new WC_Redsys)->get_next_order_id() - 1, null, __METHOD__);
//				WC_Redsys_Refund::saveOrderId((new WC_Redsys)->get_next_order_id() - 1, $paymentResult['order'], $paymentResult['amount']);
				WC_Redsys_Order::saveOrderDetails($order->id, $paymentResult['order'], $paymentResult['transactionType'], $paymentResult['amount'], $idLog);
				RedsysHelper::escribirLog("INFO ", $idLog, "Pedido " . $paymentResult['idCart'] . " registrado con éxito.", null, __METHOD__);
				
				$reference=$paymentResult['merchantIdentifier'];
				if($reference!=null){
					$idCustomer = get_current_user_id();
					$cardNumber=$paymentResult['cardNumber'];
					$cardBrand=$paymentResult['cardBrand'];
					$cardType=$paymentResult['cardType'];
					$expiryDate=$paymentResult['expiryDate'];
					
					WC_Redsys_Ref::saveReference($idCustomer, $reference, $cardNumber, $cardBrand, $cardType, $expiryDate, $idLog);
				}
	
				$redirectUrl = $urlOK ? $urlOK : $order->get_checkout_order_received_url();
			} else {
				$order->add_order_note( __('[REDSYS] Respuesta del SIS: ', REDSYSPUR_DOMAIN) . $paymentResult['respuestaSIS']);
				$order->update_status('cancelled',__( '[REDSYS] El pedido ha finalizado con errores. Número de pedido enviado a Redsys: ', REDSYSPUR_DOMAIN ) . $paymentResult['order']);

				RedsysHelper::escribirLog("ERROR", $idLog, "Pedido " . $order->id . " finalizado con errores.", null, __METHOD__);
	
				wc_add_notice('Se ha producido un error al intentar procesar la operación. Pruebe con otra tarjeta o método de pago, o póngase en contacto con nosotros mencionando el número de pedido ' . $order->id . '.', 'error');
				$redirectUrl = $urlKO ? $urlKO : $order->get_cancel_order_url();
			}

			RedsysHelper::escribirLog("INFO ", $idLog, $paymentResult['respuestaSIS'], null, __METHOD__);
		}else{
			$order->update_status('cancelled',__( '[REDSYS] El pedido ha finalizado con errores. Número de pedido enviado a Redsys: ', REDSYSPUR_DOMAIN ) . $paymentResult['order']);
			RedsysHelper::escribirLog("ERROR", $idLog, "Pedido " . $order->id . " finalizado con errores, marcado como Cancelado.", null, __METHOD__);

			wc_add_notice('Se ha producido un error al intentar procesar la operación. Pruebe con otra tarjeta o método de pago, o póngase en contacto con nosotros mencionando el número de pedido ' . $order->id . '.', 'error');
			$redirectUrl = $urlKO ? $urlKO : $order->get_cancel_order_url();
		}

		RedsysHelper::escribirLog("DEBUG", $idLog, "Redirigiendo a " . $redirectUrl, null, __METHOD__);

		header("Location: " . $redirectUrl);
		
		exit();
    }

    function init_form_fields() {
    	global $woocommerce;
    
    	$this->form_fields = array(
                'enabled' => array(
                    'title'       => __( 'Mostrar método de pago', REDSYSPUR_DOMAIN ),
                    'label'       => __( 'Configura si mostrar u ocultar el método de pago inSite', REDSYSPUR_DOMAIN ),
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
                        'default'     => __( 'Pague con tarjeta usando los servicios de Redsys.', REDSYSPUR_DOMAIN ),
                        'desc_tip'    => true,
                ),
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
				'genPedido' => array(
                    'title'       => __( 'Método de generación del número de pedido', REDSYSPUR_DOMAIN ),
                    'type'        => 'select',
                    'description' => __( 'Esta opción no modifica la forma en la que se identifica la orden en su Backoffice, sino el número de pedido (adaptado para que siempre ocupe doce dígitos) que se envía a Redsys para identificar la operación.<br>En algunos casos, si falla la obtención del identificador del carrito, se forzará que se utilice un número aleatorio.<br>Recuerde que en los detalles de cada orden puede ver el número de pedido que identifica la operación en el Portal de Administración del TPV Virtual.', REDSYSPUR_DOMAIN ),
                    'default'     => '2',
                    'options'     => array(
                        '0' => __( 'Híbrido (recomendado)', REDSYSPUR_DOMAIN ),
                        '1' => __( 'Sólo ID del carrito', REDSYSPUR_DOMAIN ),
                        '2' => __( 'Aleatorio', REDSYSPUR_DOMAIN )
                    ),
					'disabled'    => true
                ),
    			'with3ds' => array(
    					'title'       => __( 'Pago seguro usando 3D Secure', REDSYSPUR_DOMAIN ),
						'label'       => __( 'Configura si activar o desactivar el pago seguro enviando información adicional de 3D Secure', REDSYSPUR_DOMAIN ),
    					'type'        => 'checkbox',
						'description' => __( 'Esta opción permite enviar información adicional del cliente que está realizando la compra, proporcionando más seguirdad a la hora de autenticar la operación. Se recomienda el envío de esta información en los datos de la operación.', REDSYSPUR_DOMAIN ),
    					'default'     => 'yes',
    					'desc_tip'    => true
    			),
    			'idioma_tpv' => array(
						'title'       => __( 'Permitir al TPV usar el idioma configurado en el navegador del cliente', REDSYSPUR_DOMAIN ),
						'label'       => __( 'Configura si mostrar el formulario de pago de tu TPV en el idioma del navegador del cliente', REDSYSPUR_DOMAIN ),
						'type'        => 'checkbox',
						'description' => __( 'Con esta opción activada, la pasarela se mostrará en el idioma de visualización que el cliente haya configurado en los ajustes de su navegador.', REDSYSPUR_DOMAIN ),
						'default'     => 'no',
    					'desc_tip'    => false
    			),
    			'withref' => array(
    					'title'       => __( 'Habilitar pago por referencia', REDSYSPUR_DOMAIN ),
						'label'       => __( 'Configura si permitir al cliente guardar su tarjeta para futuras compras', REDSYSPUR_DOMAIN ),
    					'type'        => 'checkbox',
    					'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Esta configuración podría requerir activación por parte de su Entidad Bancaria.<br>El pago por referencia permite al cliente guardar su tarjeta para pagar de manera más sencilla en futuras compras.', REDSYSPUR_DOMAIN ),
    					'default'     => 'no'
    			),
    			'iniste_partes' => array(
						'title'       => __( 'Integración por partes del formulario inSite', REDSYSPUR_DOMAIN ),
						'label'       => __( 'Configura si quieres utilizar el formulario inSite integrado por partes', REDSYSPUR_DOMAIN ),
						'type'        => 'checkbox',
						'description' => __( 'El formulario inSite integrado por partes se integra mejor en la interfaz de la tienda al abandonar el estilo por defecto de inSite de Redsys.', REDSYSPUR_DOMAIN ),
						'default'     => 'no',
						'disabled'    => true
				),
    			'button_text' => array(
    					'title'       => __( 'Texto del botón de pago', REDSYSPUR_DOMAIN ),
    					'type'        => 'text',
    					'description' => __( 'Texto del botón de pago.', REDSYSPUR_DOMAIN ),
    					'default'     => 'REALIZAR PAGO',
    					'desc_tip'    => true
    			),
    			'button_style' => array(
    					'title'       => __( 'Estilo del botón de pago', REDSYSPUR_DOMAIN ),
    					'type'        => 'text',
    					'description' => __( 'Estilo del botón de pago.', REDSYSPUR_DOMAIN ),
    					'default'     => 'background-color:orange;color:black;',
    					'desc_tip'    => true
    			),
    			'body_style' => array(
    					'title'       => __( 'Estilo del iframe', REDSYSPUR_DOMAIN ),
    					'type'        => 'text',
    					'description' => __( 'Estilo del iframe.', REDSYSPUR_DOMAIN ),
    					'default'     => 'color:black;',
    					'desc_tip'    => true
    			),
    			'form_style' => array(
    					'title'       => __( 'Estilo del formulario', REDSYSPUR_DOMAIN ),
    					'type'        => 'text',
    					'description' => __( 'Estilo del formulario.', REDSYSPUR_DOMAIN ),
    					'default'     => 'color:grey;',
    					'desc_tip'    => true
    			),
    			'form_text_style' => array(
    					'title'       => __( 'Estilo del texto de formulario', REDSYSPUR_DOMAIN ),
    					'type'        => 'text',
    					'description' => __( 'Estilo del texto de formulario.', REDSYSPUR_DOMAIN ),
    					'default'     => ';',
    					'desc_tip'    => true
				),
				'sustituir_idioma' => array(
					'title'       => __( 'Eliminar el idioma en la ruta de las URLs', REDSYSPUR_DOMAIN ),
					'label'       => __( 'Configura si eliminar las referencias al idioma de la web de las rutas de las URLs', REDSYSPUR_DOMAIN ),
					'type'        => 'checkbox',
					'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Activa esta opción sólo si sabes lo que estás haciendo o se te indica desde soporte técnico.<br>Esta opción elimina los idiomas (por ejemplo /en/) de la ruta de las URL. Esto es útil si tu plataforma no es capaz de encontrar las rutas de plantillas o archivos requeridos para el funcionamiento del módulo.', REDSYSPUR_DOMAIN ),
					'default'     => 'no'
				),
				'moneda_manual' => array(
                    'title'       => __( 'Moneda personalizada para operaciones', REDSYSPUR_DOMAIN ),
                    'type'        => 'text',
                    'placeholder' => __( 'Introduzca el código ISO de la moneda.', REDSYSPUR_DOMAIN),
                    'description' => __( '<span style="color:#fa7878; font-weight:bold;">( ! )</span> Esta configuración sobreescribirá la detección automática de moneda, su terminal deberá estar configurado para usar la moneda que aquí establezca si es distinta al Euro.<br>Déjalo en blanco para usar la detección automática. Use esta configuración sí y sólo sí su comercio está recibiendo errores SIS0015 o SIS0027.', REDSYSPUR_DOMAIN ),
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
                )
    	);

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
                $actions['confirmation_insite_payment_action'] = __( 'Confirmar pago', REDSYSPUR_DOMAIN );
                $actions['cancellation_insite_payment_action'] = __( 'Anular pago', REDSYSPUR_DOMAIN );
            }
        }

        if($orderDetails && ($orderDetails['transaction_type'] == TransactionType::$authentication)){
            if($theorder->get_payment_method() == $this->id){
                $actions['confirmation_insite_payment_action'] = __( 'Confirmar pago', REDSYSPUR_DOMAIN );
            }
        }

        return $actions;
    }

    function confirmation_insite_payment_action($order){
        $url = admin_url( 'post.php?&action=confirmation_insite_payment&post=' . $order->id );

        header('Location: '.$url);
        die;
    }

    function cancellation_insite_payment_action($order){
        $url = admin_url( 'post.php?&action=cancellation_insite_payment&post=' . $order->id );

        header('Location: '.$url);
        die;
    }

    function confirmation_payment($post_id){
        $order = new WC_Order($post_id);
        $importe_restante = 0;
        $details = WC_Redsys_Order::getOrderDetails($order->id);
        $urlBack = admin_url( 'post.php?post=' . $order->id . '&action=edit' );

        if (!empty( $_POST ) ){
            $url = admin_url( 'post.php?&action=confirmation_insite_payment&post=' . $order->id );
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
}