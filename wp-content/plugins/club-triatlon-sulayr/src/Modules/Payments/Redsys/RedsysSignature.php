<?php
namespace ClubTriatlonSulayr\Modules\Payments\Redsys;

/**
 * Firma HMAC SHA-256 de Redsys (SPEC-COBROS §5.3), versión HMAC_SHA256_V1.
 *
 * 1. Los parámetros van en JSON y en base64 (Ds_MerchantParameters).
 * 2. La clave de la operación es el número de pedido cifrado con 3DES (CBC,
 *    IV de ceros, relleno con ceros) usando la clave del comercio.
 * 3. La firma es el HMAC SHA-256 de Ds_MerchantParameters con esa clave, en
 *    base64.
 *
 * Las notificaciones y las respuestas de Redsys llegan en base64 «URL»
 * (- y _ en lugar de + y /). La comprobación acepta las dos formas.
 */
class RedsysSignature {

    public const VERSION = 'HMAC_SHA256_V1';

    /** @var string Clave del comercio ya decodificada. */
    private $key;

    /**
     * __construct()
     *
     * @param   string $secret_key Clave SHA-256 del comercio, en base64.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct( string $secret_key ) {
        $key = base64_decode( $secret_key, true );
        if ( false === $key || 24 !== strlen( $key ) ) {
            throw new \InvalidArgumentException( 'La clave de Redsys no es válida: tiene que ser base64 de 24 bytes.' );
        }

        $this->key = $key;
    }

    /**
     * Ds_MerchantParameters: los parámetros en JSON y base64.
     *
     * @param   array<string, scalar> $params Parámetros DS_MERCHANT_*.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function encode_params( array $params ): string {
        return base64_encode( (string) json_encode( $params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
    }

    /**
     * Decodifica Ds_MerchantParameters (base64 normal o «URL»).
     *
     * @param   string $encoded Parámetros codificados.
     * @return 	array<string, mixed>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function decode_params( string $encoded ): array {
        $json = base64_decode( strtr( $encoded, '-_', '+/' ), true );
        $data = false !== $json ? json_decode( $json, true ) : null;

        return is_array( $data ) ? $data : [];
    }

    /**
     * Firma unos parámetros ya codificados para un número de pedido.
     *
     * @param   string $order          Número de pedido (Ds_Order).
     * @param   string $encoded_params Ds_MerchantParameters.
     * @return 	string Firma en base64.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function sign( string $order, string $encoded_params ): string {
        return base64_encode( hash_hmac( 'sha256', $encoded_params, $this->order_key( $order ), true ) );
    }

    /**
     * Comprueba la firma de una notificación o respuesta de Redsys.
     *
     * El número de pedido se lee de los propios parámetros (Ds_Order).
     *
     * @param   string $encoded_params Ds_MerchantParameters recibido.
     * @param   string $signature      Ds_Signature recibido.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function verify( string $encoded_params, string $signature ): bool {
        $order = self::order_from( self::decode_params( $encoded_params ) );
        if ( '' === $order || '' === $signature ) {
            return false;
        }

        $expected = $this->sign( $order, $encoded_params );

        return hash_equals( self::normalize( $expected ), self::normalize( $signature ) );
    }

    /**
     * Ds_Order de unos parámetros decodificados, venga con el nombre que venga.
     *
     * @param   array<string, mixed> $params Parámetros.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function order_from( array $params ): string {
        foreach ( [ 'Ds_Order', 'DS_ORDER', 'Ds_Merchant_Order', 'DS_MERCHANT_ORDER' ] as $name ) {
            if ( isset( $params[ $name ] ) && is_scalar( $params[ $name ] ) ) {
                return (string) $params[ $name ];
            }
        }

        return '';
    }

    /**
     * Clave de la operación: el pedido cifrado con 3DES y la clave del comercio.
     *
     * @param   string $order Número de pedido.
     * @return 	string Binario.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function order_key( string $order ): string {
        $length = (int) ( ceil( strlen( $order ) / 8 ) * 8 );
        $padded = str_pad( $order, max( 8, $length ), "\0" );

        $encrypted = openssl_encrypt( $padded, 'des-ede3-cbc', $this->key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, str_repeat( "\0", 8 ) );
        if ( false === $encrypted ) {
            throw new \RuntimeException( 'No se pudo cifrar el número de pedido (3DES).' );
        }

        return $encrypted;
    }

    /**
     * Base64 sin variantes, para comparar firmas.
     *
     * @param   string $signature Firma.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function normalize( string $signature ): string {
        return rtrim( strtr( $signature, '-_', '+/' ), '=' );
    }
}

