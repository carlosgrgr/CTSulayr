<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Pago con el socio delante, por redirección (SPEC-COBROS §5.1, paso 1).
 *
 * Arma los tres campos del formulario que se envía a Redsys. Pide guardar la
 * tarjeta para cobros recurrentes iniciados por el comercio:
 * DS_MERCHANT_IDENTIFIER=REQUIRED, DS_MERCHANT_COF_INI=S y
 * DS_MERCHANT_COF_TYPE=R.
 *
 * Los nombres son los habituales de Redsys: hay que confirmarlos con el
 * manual que dé el técnico.
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
        $params = self::redirect_params( $config, $order, $cents, $description, $notify_url, $ok_url, $ko_url, $save_card );
        $encoded = RedsysSignature::encode_params( $params );

        return [
            'Ds_SignatureVersion' => RedsysSignature::VERSION,
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => ( new RedsysSignature( $config->secret_key() ) )->sign( $order, $encoded ),
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
