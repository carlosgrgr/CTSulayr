<?php

require_once __DIR__ . '/lib/autoload.php';

use Redsys\Parameters;
use Redsys\Rest;
use Redsys\TransactionType;

class WC_Redsys_Order {

    private static $_estado;

	public static function confirmation($gateway, $idOrder, $amount, $dataCapture = null, $transactionType = 1){
        if ($transactionType == TransactionType::$preauthorisation)
		    return self::transaction($gateway, $idOrder, TransactionType::$preauthorisationConfirmation, $amount, $dataCapture);
        else if ($transactionType == TransactionType::$authentication)
            return self::transaction($gateway, $idOrder, TransactionType::$authenticationConfirmation, $amount, $dataCapture);
    }

	public static function refund($gateway, $idOrder, $amount){
        $orderDetails = self::getOrderDetails($idOrder);
        if($orderDetails['transaction_type'] == TransactionType::$preauthorisation){
            $orderConfirmationDetails = self::getOrderConfirmationDetails($idOrder);

            $currency_decimals = $gateway->decimales_moneda;
            $amount = number_format( (float) ($amount), intval($currency_decimals), '.', '' );
            $amount = str_replace('.','',$amount);
            $amount = floatval($amount);
            
            if(!$orderConfirmationDetails){
                return array(
                    'result' => 0,
                    'error' => 'No se ha encontrado detalles de la orden ' . $idOrder . ' sobre la que se quiere realizar la devolución.'
                );
            
            } else if (!self::refundAmountValidation($idOrder, $orderConfirmationDetails, $amount)) {
                return array(
                    'result' => 0,
                    'error' => 'El importe es incorrecto.'
                );
            }

            $amountRefunded = false;
            foreach($orderConfirmationDetails as $orderConfirmationDetail){
                $rts = $orderConfirmationDetail['rts'];
                $amountToRefund = min($amount, $orderConfirmationDetail['confirmation_amount'] - $orderConfirmationDetail['refund_amount']);
                if($amountToRefund){
                    $response = self::transaction($gateway, $idOrder, TransactionType::$refund, $amountToRefund/pow(10, $currency_decimals), null, $rts);

                    if($response['result']){
                        global $wpdb;
                        $wpdb->update(
                            self::getOrderConfirmationTableName(),
                            array(
                                'refund_amount' => $orderConfirmationDetail['refund_amount'] + $amountToRefund
                            ),
                            array( 
                                'id_order' => $idOrder,
                                'rts' => $rts
                            )
                        );
                        $amount -= $amountToRefund;
                        $amountRefunded = true;
                        if(!$amount){
                            return $response;
                        }
                    }else{
                        if($amountRefunded){
                            $response['error'] = "Se ha realizado una devolución parcial correctamente pero ha fallado otra.<br>" . $response['error'];
                        }
                        return $response;
                    }
                }else{
                    $response = array(
                        'result' => 0,
                        'error' => 'El importe es incorrecto'
                    );
                }
            }
            return $response;
        }else{
            return self::transaction($gateway, $idOrder, TransactionType::$refund, $amount);
        }
    }

	public static function cancellation($gateway, $idOrder, $amount, $dataCapture = null){
        return self::transaction($gateway, $idOrder, TransactionType::$void, $amount, $dataCapture);
    }

	private static function transaction($gateway, $idOrder, $transactionType, $amount = null, $dataCapture = null, $rts = null){
        $orderDetails = self::getOrderDetails($idOrder);

        $idLog = RedsysHelper::generateIdLog($gateway->activar_log, $gateway->logString, $idOrder . $gateway->merchant->fuc, $gateway->tamano_log);

        if(!$orderDetails){
            return array(
                'result' => 0,
                'error' => 'No se encuentra la orden registrada'
            );
        }

        if(!$amount){
            $amount = $orderDetails['grand_total'];
        }

		$currency_decimals = $gateway->decimales_moneda;
        $amount = number_format( (float) ($amount), intval($currency_decimals), '.', '' );
        $amount = str_replace('.','',$amount);
        $amount = floatval($amount);

        if($transactionType == TransactionType::$void){
            //Anulacion de Pago (45) o Anulacion de Preautorizacion (9)
            $transactionType = $orderDetails['transaction_type'] == TransactionType::$authorisation? TransactionType::$cancellation : TransactionType::$void;
        }

        if($transactionType == TransactionType::$preauthorisationConfirmation){
            //Si es una confirmacion parcial, utilizamos el tipo de operacion 48
            if($amount < $orderDetails['grand_total']){
                $transactionType = TransactionType::$partialConfirmation;
            }
        }
        
        RedsysHelper::escribirLog("DEBUG", $idLog, "Se va a realizar una " . self::getTransactionTypeString($transactionType) . " para el pedido " . $orderDetails['redsys_order']);

        if(!self::amountValidation($idOrder, $transactionType, $amount)){
            RedsysHelper::escribirLog("DEBUG", $idLog, "No supero las validaciones");
            return array(
                'result' => 0,
                'error' => 'El importe es incorrecto'
            );
        }

        try{
            $merchantModule = RedsysHelper::getMerchantModule(REDSYSPUR_MODULE_VERSION);

            $params = new Parameters();
            $params->amount = $amount;
            $params->currency = $gateway->moneda;
            $params->order = $orderDetails['redsys_order'];
            $params->module = $merchantModule;

            if(!is_null($rts) and $rts > 0){
                RedsysHelper::escribirLog("DEBUG", $idLog, "La operación se va a realizar usando el RTS " . $rts);
                $params->rts = $rts;
            }

            $result = Rest::generic($gateway->merchant, $params, $transactionType);

            //Cuando el SIS devuelve error sin erroCode, lo generamos
            if(!in_array(intval($result->data->codResponse), [0, 400, 900])){
                $result->errorCode = $result->data->codResponse;
            }
        }catch(\Exception $e){
            return array(
                'result' => 0,
                'error' => $e->getMessage()
            );
        }

        if($result != null && empty($result->errorCode) && $result->data != null) {
            RedsysHelper::escribirLog("DEBUG", $idLog, "La " . self::getTransactionTypeString($transactionType) . " se ha procesado correctamente");

            $rts = null;
            if ($transactionType == TransactionType::$partialConfirmation)
                $rts = $result->data->rts;
            else if ($transactionType == TransactionType::$preauthorisationConfirmation || $transactionType == TransactionType::$authenticationConfirmation)
                $rts = -1;

            self::updateOrderAmount($idOrder, $transactionType, $amount, $rts, $idLog);

            $response = array(
                'result' => 1,
            );
        }else{
            RedsysHelper::escribirLog("DEBUG", $idLog, "Ha habido un problema al procesar la " . self::getTransactionTypeString($transactionType) . ", contacte con su entidad o revise el Portal de Administracion del TPV Virtual");

            $response = array(
                'result' => 0,
                'error' => $result->description
            );
        }

		return $response;
    }

    private static function getTransactionTypeString($transactionType){
        switch($transactionType){
            case TransactionType::$preauthorisationConfirmation:
                return "CONFIRMACIÓN";
            case TransactionType::$authenticationConfirmation:
                return "CONFIRMACIÓN DE AUTENTICACIÓN";
            case TransactionType::$refund:
                return "DEVOLUCIÓN";
            case TransactionType::$partialConfirmation:
                return "CONFIRMACIÓN PARCIAL";
            case TransactionType::$cancellation:
            case TransactionType::$void:
                return "ANULACIÓN";
        }
        return "TRANSACCIÓN " . $transactionType;
    }

    private static function updateOrderAmount($idOrder, $transactionType, $amount, $rts = null, $idLog = null){
		global $wpdb;

        $orderDetails = self::getOrderDetails($idOrder);
            
        $data = array();
        switch($transactionType){
            case TransactionType::$preauthorisationConfirmation:
            case TransactionType::$authenticationConfirmation:
            case TransactionType::$partialConfirmation:
                $data = array(
                    'confirmation_amount' => $orderDetails['confirmation_amount'] + $amount
                );
                break;
            case TransactionType::$refund:
                $data = array(
                    'refund_amount' => $orderDetails['refund_amount'] + $amount
                );
                break;
            case TransactionType::$cancellation:
            case TransactionType::$void:
                $data = array(
                    'cancellation_amount' => $orderDetails['cancellation_amount'] + $amount
                );
                break;
        }

        $wpdb->update(
            self::getOrderTableName(),
            $data,
            array( 
                'id_order' => $idOrder
            )
        );

        if(!is_null($rts)){
            $wpdb->insert(
                self::getOrderConfirmationTableName(),
                array(
                    'id_order' => $idOrder,
                    'rts' => $rts,
                    'confirmation_amount' => $amount,
                    'refund_amount' => 0
                )
            );
        }

        self::updateOrderStatus($idOrder, $transactionType, $idLog);
    }

    private static function updateOrderStatus($idOrder, $transactionType, $idLog = null){
        $orderDetails = self::getOrderDetails($idOrder);
        $order = new WC_Order($idOrder);
        $estado = null;

        switch($transactionType) {
            case TransactionType::$preauthorisationConfirmation:
            case TransactionType::$authenticationConfirmation:
                $order->payment_complete();
                break;
        }

        if($orderDetails['cancellation_amount'] == $orderDetails['grand_total']){
            $estado = "cancelled";
        }

        if($estado){
            RedsysHelper::escribirLog("DEBUG", $idLog, "Se actualiza el estado de la orden " . $idOrder . " a " . $estado, null, __METHOD__);
            $order->update_status($estado,__( '[REDSYS] La ' . self::getTransactionTypeString($transactionType) . ' se ha procesado correctamente', REDSYSPUR_DOMAIN ));
        
        } else {

            if ($transactionType > 1 and $transactionType != 7) $order->add_order_note(__( '[REDSYS] La ' . self::getTransactionTypeString($transactionType) . ' se ha procesado correctamente', REDSYSPUR_DOMAIN ));
        }

        $resultPorConfirmar = number_format( ($orderDetails['grand_total'] - $orderDetails['confirmation_amount'] - $orderDetails['cancellation_amount']) / 100, 2, "," , "." );
        $resultConfirmado = number_format( ($orderDetails['confirmation_amount'] - $orderDetails['refund_amount']) / 100, 2, "," , "." );
        $resultAnulado = number_format( $orderDetails['cancellation_amount'] / 100, 2, "," , "." );
        $resultDevuelto = number_format( $orderDetails['refund_amount'] / 100, 2, "," , "." );

        if($orderDetails['transaction_type'] == TransactionType::$authorisation)
            return;

        $order->add_order_note( __('Estado de la orden.
        <br>
            POR CONFIRMAR: ' . $resultPorConfirmar . '€
            CONFIRMADO: ' . $resultConfirmado . '€
            ANULADO: ' . $resultAnulado . '€
            DEVUELTO: ' . $resultDevuelto .'€'
            , REDSYSPUR_DOMAIN));

    }

    private static function amountValidation($idOrder, $transactionType, $amount){
		global $wpdb;

        $orderDetails = self::getOrderDetails($idOrder);

        switch($transactionType){
            case TransactionType::$preauthorisationConfirmation:
            case TransactionType::$partialConfirmation:
            case TransactionType::$cancellation:
            case TransactionType::$void:
                $amountNotConfirmed = $orderDetails['grand_total'] - $orderDetails['confirmation_amount'] - $orderDetails['cancellation_amount'];
                if($amount > $amountNotConfirmed){
                    return false;
                }
                break;
            case TransactionType::$refund:
                if($amount > $orderDetails['confirmation_amount'] - $orderDetails['refund_amount']){
                    return false;
                }
                break;
        }

        return true;
    }

    private static function refundAmountValidation($idOrder, $orderConfirmationDetails, $amount){
        $amountToRefund = 0;

        foreach($orderConfirmationDetails as $orderConfirmationDetail){
            $amountToRefund += $orderConfirmationDetail['confirmation_amount'] - $orderConfirmationDetail['refund_amount'];
        }

        return $amountToRefund >= $amount;
    }

    private static function removeEmptyFields($array){
        foreach ($array as $key => & $value) {
            if (is_array($value)) {
                $value = self::removeEmptyFields($value);
            }else{
                if ( $value == '') {
                    unset($array[$key]);
                }
            }
        }
        unset($value);
    
        return $array;
    }

    public static function saveOrderDetails($idOrder, $redsysOrder, $transactionType, $amount, $idLog = null){
		global $wpdb;

        if($idOrder!=null && self::checkOrderTable()){
            $oldRedsysOrder=self::getRedsysOrder($idOrder);
            
            if($oldRedsysOrder==null){
				$wpdb->insert(
					self::getOrderTableName(),
					array(
                        'id_order' => $idOrder,
                        'redsys_order' => substr($redsysOrder, 0, 20),
                        'transaction_type' => $transactionType,
                        'grand_total' => $amount,
                        'confirmation_amount' => $transactionType == TransactionType::$authorisation ? $amount : 0,
                        'result_processed' => 1,
					)
				);
            }else{
				$wpdb->update(
					self::getOrderTableName(),
					array(
						'redsys_order' => substr($redsysOrder, 0, 20),
                        'transaction_type' => $transactionType,
                        'grand_total' => $amount,
                        'confirmation_amount' => $transactionType == TransactionType::$authorisation ? $amount : 0,
                        'result_processed' => 1,
					),
					array( 
						'id_order' => $idOrder
                    )
				);
            }
            self::updateOrderStatus($idOrder, $transactionType, $idLog);
        }
    }

    public static function getOrderId($redsysOrder){
		if(self::checkOrderTable()){
			global $wpdb;

			$order=$wpdb->get_results( "SELECT * FROM ".self::getOrderTableName()." WHERE redsys_order='".$redsysOrder."';", ARRAY_A  );
			if(sizeof($order)>0)
				return $order[0]["id_order"];
		}
		return null;
    }

    public static function getRedsysOrder($idOrder){
		if(self::checkOrderTable()){
			global $wpdb;
			
			$order=$wpdb->get_results( "SELECT * FROM ".self::getOrderTableName()." WHERE id_order=".$idOrder.";", ARRAY_A  );
			if(sizeof($order)>0)
				return $order[0]["redsys_order"];
		}
		return null;
    }

    public static function getOrderDetails($idOrder){
		if(self::checkOrderTable()){
			global $wpdb;
			
			$order=$wpdb->get_results( "SELECT * FROM ".self::getOrderTableName()." WHERE id_order=".$idOrder.";", ARRAY_A  );
			if(sizeof($order)>0)
				return $order[0];
		}
		return null;
    }

    public static function getOrderConfirmationDetails($idOrder){
		if(self::checkOrderConfirmationTable()){
			global $wpdb;
			
			$order=$wpdb->get_results( "SELECT * FROM ".self::getOrderConfirmationTableName()." WHERE id_order=".$idOrder.";", ARRAY_A  );
			if(sizeof($order)>0)
				return $order;
		}
		return null;
    }

    public static function getNotificationProcessed($idOrder){
		if(self::checkOrderTable()){
			global $wpdb;
			
			$order=$wpdb->get_results( "SELECT * FROM ".self::getOrderTableName()." WHERE id_order=".$idOrder.";", ARRAY_A  );
			if(sizeof($order)>0)
				return ($order[0]["result_processed"] == 1) ? true : false;
		}
		return false; //Distinto al resto: si no tengo info de la orden, casi seguro es primera notificacion (orden no guardada), ergo se devuelve NO PROCESADA.
    }

	public static function checkOrderTable(){
		global $wpdb;

        $sqlQueryCheckTable = "
            SELECT 1
            FROM information_schema.columns 
            WHERE table_name = '".self::getOrderTableName()."' AND column_name = 'result_processed';
        ";
		
        $exists = sizeof($wpdb->get_results( $sqlQueryCheckTable ))>0;

        if(!$exists){
            self::createOrderTable();
            $exists = sizeof($wpdb->get_results( $sqlQueryCheckTable ))>0;
        }

        return $exists && self::checkOrderConfirmationTable();
	}

	public static function checkOrderConfirmationTable(){
		global $wpdb;

        $sqlQueryCheckTable = "
            SELECT 1
            FROM information_schema.columns 
            WHERE table_name = '".self::getOrderConfirmationTableName()."' AND column_name = 'confirmation_amount';
        ";
		
        $exists = sizeof($wpdb->get_results( $sqlQueryCheckTable ))>0;

        if(!$exists){
            self::createOrderConfirmationTable();
            $exists = sizeof($wpdb->get_results( $sqlQueryCheckTable ))>0;
        }

        return $exists;
    }

	public static function createOrderTable(){
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `".self::getOrderTableName()."` (
            `id_order` INT NOT NULL PRIMARY KEY,
            `redsys_order` VARCHAR(20) NOT NULL,
            `transaction_type` INT NOT NULL,
            `grand_total` float NOT NULL DEFAULT 0,
            `confirmation_amount` float NOT NULL DEFAULT 0,
            `refund_amount` float NOT NULL DEFAULT 0,
            `cancellation_amount` float NOT NULL DEFAULT 0,
            `result_processed` BOOL NOT NULL DEFAULT 0,
            INDEX (`id_order`)
        ) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$res = dbDelta( $sql );
	}

	public static function createOrderConfirmationTable(){
		global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE `".self::getOrderConfirmationTableName()."` (
            `id_order` INT NOT NULL,
            `rts` VARCHAR(40) NOT NULL,
            `confirmation_amount` float NOT NULL DEFAULT 0,
            `refund_amount` float NOT NULL DEFAULT 0,
            PRIMARY KEY (`id_order`, `rts`),
            INDEX (`id_order`, `rts`)
        ) $charset_collate;";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		$res = dbDelta( $sql );
    }

	public static function dropOrderTable(){
		global $wpdb;
		
		$sql = "'DROP TABLE `".self::getOrderTableName()."`'";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

    public static function dropOrderConfirmationTable(){
        global $wpdb;

		$sql = "'DROP TABLE `".self::getOrderConfirmationTableName()."`'";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
    }

    public static function getOrderTableName(){
		global $wpdb;
		return $wpdb->prefix."redsys_order";
    }

    public static function getOrderConfirmationTableName(){
		global $wpdb;
		return $wpdb->prefix."redsys_order_confirmation";
    }

}