<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Peticiones a Redsys (SPEC-COBROS §5.1 y §5.2).
 *
 * Pago con el socio delante, por redirección: arma los tres campos del
 * formulario y pide guardar la tarjeta para cobros recurrentes iniciados por
 * el comercio (DS_MERCHANT_IDENTIFIER=REQUIRED, COF_INI=S y COF_TYPE=R).
 *
 * Cobro sin el socio (MIT): el cuerpo JSON de la API REST
 * (trataPeticionREST) con la referencia guardada.
 *
 * Los parámetros siguen la documentación pública de Redsys (tokenización
 * MIT) y las respuestas del entorno de pruebas con el comercio genérico.
 */
class RedsysRequest {

    /** Autorización (pago normal). */
    public const TRANSACTION_AUTHORIZATION = '0';

    /** Español. */
    private const LANGUAGE_ES = '001';

    /**
     * Campos del formulario de pago.
     *
     * @param   RedsysConfig $config      Credenciales.
     * @param   string       $order       Ds_Order.
     * @param   int          $cents       Importe en céntimos.
     * @param   string       $description Descripción para el socio (máx. 125).
     * @param   string       $notify_url  URL de notificación (HTTPS en PROD).
     * @param   string       $ok_url      Vuelta si el pago va bien.
     * @param   string       $ko_url      Vuelta si falla o se cancela.
     * @param   bool         $save_card   Pedir la referencia para cobros recurrentes.
     * @return 	array{Ds_SignatureVersion: string, Ds_MerchantParameters: string, Ds_Signature: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function redirect_fields( RedsysConfig $config, string $order, int $cents, string $description, string $notify_url, string $ok_url, string $ko_url, bool $save_card = true ): array {
        return self::signed_fields( $config, self::redirect_params( $config, $order, $cents, $description, $notify_url, $ok_url, $ko_url, $save_card ) );
    }

    /**
     * Cuerpo JSON de la petición REST de un cobro sin el socio (§5.2, paso 1).
     *
     * @param   RedsysConfig $config      Credenciales.
     * @param   string       $order       Ds_Order.
     * @param   int          $cents       Importe en céntimos.
     * @param   string       $identifier  Referencia de la tarjeta (Ds_Merchant_Identifier).
     * @param   string       $cof_txnid   Ds_Merchant_Cof_Txnid de la operación inicial ('' si no se tiene).
     * @param   string       $description Descripción (máx. 125).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mit_body( RedsysConfig $config, string $order, int $cents, string $identifier, string $cof_txnid, string $description ): string {
        return (string) json_encode( self::signed_fields( $config, self::mit_params( $config, $order, $cents, $identifier, $cof_txnid, $description ) ), JSON_UNESCAPED_SLASHES );
    }

    /**
     * Parámetros DS_MERCHANT_* de un cobro iniciado por el comercio (MIT) con
     * la tarjeta guardada: referencia, DIRECTPAYMENT=true, EXCEP_SCA=MIT,
     * COF_INI=N, COF_TYPE=R y el Cof_Txnid de la operación inicial (§5.2).
     *
     * @param   RedsysConfig $config      Credenciales.
     * @param   string       $order       Ds_Order.
     * @param   int          $cents       Importe en céntimos.
     * @param   string       $identifier  Referencia de la tarjeta.
     * @param   string       $cof_txnid   Cof_Txnid de la operación inicial.
     * @param   string       $description Descripción.
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mit_params( RedsysConfig $config, string $order, int $cents, string $identifier, string $cof_txnid, string $description ): array {
        if ( $cents <= 0 ) {
            throw new \InvalidArgumentException( 'El importe de un cobro con la tarjeta guardada tiene que ser mayor que 0.' );
        }
        $identifier = trim( $identifier );
        if ( '' === $identifier ) {
            throw new \InvalidArgumentException( 'Falta la referencia de la tarjeta.' );
        }

        $params = [
            'DS_MERCHANT_AMOUNT' => (string) $cents,
            'DS_MERCHANT_ORDER' => $order,
            'DS_MERCHANT_MERCHANTCODE' => $config->merchant_code(),
            'DS_MERCHANT_CURRENCY' => RedsysConfig::CURRENCY_EUR,
            'DS_MERCHANT_TRANSACTIONTYPE' => self::TRANSACTION_AUTHORIZATION,
            'DS_MERCHANT_TERMINAL' => $config->terminal(),
            'DS_MERCHANT_IDENTIFIER' => $identifier,
            'DS_MERCHANT_DIRECTPAYMENT' => 'true',
            'DS_MERCHANT_EXCEP_SCA' => 'MIT',
            'DS_MERCHANT_COF_INI' => 'N',
            'DS_MERCHANT_COF_TYPE' => 'R',
            'DS_MERCHANT_PRODUCTDESCRIPTION' => function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 125 ) : substr( $description, 0, 125 ),
        ];
        if ( '' !== trim( $cof_txnid ) ) {
            $params['DS_MERCHANT_COF_TXNID'] = trim( $cof_txnid );
        }

        return $params;
    }

    /**
     * Versión de firma, parámetros codificados y firma.
     *
     * @param   RedsysConfig          $config Credenciales.
     * @param   array<string, string> $params Parámetros DS_MERCHANT_* (con DS_MERCHANT_ORDER).
     * @return 	array{Ds_SignatureVersion: string, Ds_MerchantParameters: string, Ds_Signature: string}
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function signed_fields( RedsysConfig $config, array $params ): array {
        $encoded = RedsysSignature::encode_params( $params );

        return [
            'Ds_SignatureVersion' => RedsysSignature::VERSION,
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => ( new RedsysSignature( $config->secret_key() ) )->sign( $params['DS_MERCHANT_ORDER'], $encoded ),
        ];
    }

    /**
     * Parámetros DS_MERCHANT_* del pago con redirección.
     *
     * @param   RedsysConfig $config      Credenciales.
     * @param   string       $order       Ds_Order.
     * @param   int          $cents       Importe en céntimos.
     * @param   string       $description Descripción.
     * @param   string       $notify_url  URL de notificación.
     * @param   string       $ok_url      Vuelta correcta.
     * @param   string       $ko_url      Vuelta con error.
     * @param   bool         $save_card   Pedir la referencia.
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function redirect_params( RedsysConfig $config, string $order, int $cents, string $description, string $notify_url, string $ok_url, string $ko_url, bool $save_card = true ): array {
        if ( $cents <= 0 ) {
            throw new \InvalidArgumentException( 'El importe de un pago con redirección tiene que ser mayor que 0.' );
        }

        $params = [
            'DS_MERCHANT_AMOUNT' => (string) $cents,
            'DS_MERCHANT_ORDER' => $order,
            'DS_MERCHANT_MERCHANTCODE' => $config->merchant_code(),
            'DS_MERCHANT_CURRENCY' => RedsysConfig::CURRENCY_EUR,
            'DS_MERCHANT_TRANSACTIONTYPE' => self::TRANSACTION_AUTHORIZATION,
            'DS_MERCHANT_TERMINAL' => $config->terminal(),
            'DS_MERCHANT_MERCHANTURL' => $notify_url,
            'DS_MERCHANT_URLOK' => $ok_url,
            'DS_MERCHANT_URLKO' => $ko_url,
            'DS_MERCHANT_PRODUCTDESCRIPTION' => function_exists( 'mb_substr' ) ? mb_substr( $description, 0, 125 ) : substr( $description, 0, 125 ),
            'DS_MERCHANT_CONSUMERLANGUAGE' => self::LANGUAGE_ES,
        ];

        if ( $save_card ) {
            $params['DS_MERCHANT_IDENTIFIER'] = 'REQUIRED';
            $params['DS_MERCHANT_COF_INI'] = 'S';
            $params['DS_MERCHANT_COF_TYPE'] = 'R';
        }

        return $params;
    }
}
