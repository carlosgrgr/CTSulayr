<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Notificación de Redsys a la URL de la web (SPEC-COBROS §5.1, pasos 2 y 3).
 *
 * from_request() comprueba la versión de firma, la firma y el comercio, y
 * devuelve null si algo no cuadra. Solo con una notificación válida se puede
 * apuntar un pago.
 */
class RedsysNotification {

    /** @var array<string, string> Parámetros decodificados. */
    private $params;

    /**
     * __construct()
     *
     * @param   array<string, string> $params Parámetros ya verificados.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function __construct( array $params ) {
        $this->params = $params;
    }

    /**
     * Lee y verifica una notificación.
     *
     * @param   RedsysConfig         $config Credenciales.
     * @param   array<string, mixed> $post   Campos recibidos (Ds_SignatureVersion, Ds_MerchantParameters, Ds_Signature).
     * @return 	self|null null si la firma, la versión o el comercio no son válidos.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function from_request( RedsysConfig $config, array $post ): ?self {
        $version = isset( $post['Ds_SignatureVersion'] ) ? (string) $post['Ds_SignatureVersion'] : '';
        $encoded = isset( $post['Ds_MerchantParameters'] ) ? (string) $post['Ds_MerchantParameters'] : '';
        $signature = isset( $post['Ds_Signature'] ) ? (string) $post['Ds_Signature'] : '';

        if ( RedsysSignature::VERSION !== $version || '' === $encoded || false === $config->is_configured() ) {
            return null;
        }
        if ( false === ( new RedsysSignature( $config->secret_key() ) )->verify( $encoded, $signature ) ) {
            return null;
        }

        $params = [];
        foreach ( RedsysSignature::decode_params( $encoded ) as $key => $value ) {
            if ( is_scalar( $value ) ) {
                // Redsys manda algunos valores codificados para URL (fechas, horas).
                $params[ (string) $key ] = urldecode( (string) $value );
            }
        }

        $notification = new self( $params );
        $merchant = $notification->get( 'Ds_MerchantCode' );
        if ( '' !== $merchant && ltrim( $merchant, '0' ) !== ltrim( $config->merchant_code(), '0' ) ) {
            return null;
        }

        return $notification;
    }

    /**
     * Valor de un parámetro, sin distinguir mayúsculas.
     *
     * @param   string $name Nombre (p. ej. Ds_Response).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function get( string $name ): string {
        if ( isset( $this->params[ $name ] ) ) {
            return trim( $this->params[ $name ] );
        }
        foreach ( $this->params as $key => $value ) {
            if ( 0 === strcasecmp( $key, $name ) ) {
                return trim( $value );
            }
        }

        return '';
    }

    /** @return string Ds_Order. */
    public function order(): string {
        return $this->get( 'Ds_Order' );
    }

    /** @return string Ds_Response. */
    public function response(): string {
        return $this->get( 'Ds_Response' );
    }

    /** @return bool Autorizada (Ds_Response 0000 a 0099). */
    public function is_authorized(): bool {
        return RedsysOrder::is_authorized( $this->response() );
    }

    /** @return int Importe en céntimos (-1 si no viene). */
    public function cents(): int {
        $amount = $this->get( 'Ds_Amount' );

        return 1 === preg_match( '/^\d+$/', $amount ) ? (int) $amount : -1;
    }

    /** @return string Referencia de la tarjeta (Ds_Merchant_Identifier). */
    public function identifier(): string {
        return $this->get( 'Ds_Merchant_Identifier' );
    }

    /** @return string Identificador de la operación inicial (Ds_Merchant_Cof_Txnid). */
    public function cof_txnid(): string {
        return $this->get( 'Ds_Merchant_Cof_Txnid' );
    }

    /** @return string Caducidad AAMM (Ds_ExpiryDate). */
    public function expiry(): string {
        return $this->get( 'Ds_ExpiryDate' );
    }

    /** @return string Tarjeta enmascarada (Ds_Card_Number), si Redsys la manda. */
    public function masked_card(): string {
        return $this->get( 'Ds_Card_Number' );
    }

    /**
     * Todos los parámetros, para guardarlos (TpvTransactions los limpia).
     *
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function params(): array {
        return $this->params;
    }
}
