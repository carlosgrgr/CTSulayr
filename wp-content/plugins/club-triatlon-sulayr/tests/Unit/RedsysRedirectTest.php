<?php
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysNotification;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRequest;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysSignature;
use PHPUnit\Framework\TestCase;

/**
 * Pago con redirección y notificación de Redsys (SPEC-COBROS §5.1).
 *
 * Clave pública de pruebas de Redsys (comercio genérico 999008881).
 */
class RedsysRedirectTest extends TestCase {

    private const KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    private function config(): RedsysConfig {
        return new RedsysConfig( '999008881', '1', self::KEY );
    }

    /** Campos de una notificación firmada como la firmaría Redsys (base64 «URL»). */
    private function notification( array $params, string $key = self::KEY ): array {
        $encoded = strtr( RedsysSignature::encode_params( $params ), '+/', '-_' );
        $signature = ( new RedsysSignature( $key ) )->sign( (string) $params['Ds_Order'], $encoded );

        return [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => strtr( $signature, '+/', '-_' ),
        ];
    }

    private function authorized(): array {
        return [
            'Ds_Date' => '17%2F11%2F2026',
            'Ds_Hour' => '10%3A15',
            'Ds_Amount' => '4500',
            'Ds_Currency' => '978',
            'Ds_Order' => '2611Q0000007',
            'Ds_MerchantCode' => '999008881',
            'Ds_Terminal' => '1',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => '987654',
            'Ds_TransactionType' => '0',
            'Ds_Merchant_Identifier' => 'abc123ref',
            'Ds_Merchant_Cof_Txnid' => '2611171015000',
            'Ds_ExpiryDate' => '2812',
            'Ds_Card_Number' => '454881******0004',
        ];
    }

    public function test_parametros_del_pago_guardando_la_tarjeta(): void {
        $params = RedsysRequest::redirect_params( $this->config(), '2611Q0000007', 4500, 'Recibo 2026-11-0001', 'https://club/wp-json/sulayr/v1/redsys', 'https://club/ok', 'https://club/ko' );

        $this->assertSame( '4500', $params['DS_MERCHANT_AMOUNT'] );
        $this->assertSame( '2611Q0000007', $params['DS_MERCHANT_ORDER'] );
        $this->assertSame( '999008881', $params['DS_MERCHANT_MERCHANTCODE'] );
        $this->assertSame( '978', $params['DS_MERCHANT_CURRENCY'] );
        $this->assertSame( '0', $params['DS_MERCHANT_TRANSACTIONTYPE'] );
        $this->assertSame( '1', $params['DS_MERCHANT_TERMINAL'] );
        $this->assertSame( 'https://club/wp-json/sulayr/v1/redsys', $params['DS_MERCHANT_MERCHANTURL'] );
        $this->assertSame( 'REQUIRED', $params['DS_MERCHANT_IDENTIFIER'] );
        $this->assertSame( 'S', $params['DS_MERCHANT_COF_INI'] );
        $this->assertSame( 'R', $params['DS_MERCHANT_COF_TYPE'] );
    }

    public function test_sin_guardar_la_tarjeta_no_pide_referencia(): void {
        $params = RedsysRequest::redirect_params( $this->config(), '2611Q0000007', 4500, 'x', 'n', 'o', 'k', false );

        $this->assertArrayNotHasKey( 'DS_MERCHANT_IDENTIFIER', $params );
        $this->assertArrayNotHasKey( 'DS_MERCHANT_COF_INI', $params );
    }

    public function test_campos_firmados_del_formulario(): void {
        $fields = RedsysRequest::redirect_fields( $this->config(), '2611Q0000007', 4500, 'x', 'n', 'o', 'k' );

        $this->assertSame( 'HMAC_SHA256_V1', $fields['Ds_SignatureVersion'] );
        $this->assertSame( '2611Q0000007', RedsysSignature::decode_params( $fields['Ds_MerchantParameters'] )['DS_MERCHANT_ORDER'] );
        $this->assertTrue( ( new RedsysSignature( self::KEY ) )->verify( $fields['Ds_MerchantParameters'], $fields['Ds_Signature'] ) );
    }

    public function test_importe_cero_no_se_admite(): void {
        $this->expectException( \InvalidArgumentException::class );
        RedsysRequest::redirect_params( $this->config(), '2611Q0000007', 0, 'x', 'n', 'o', 'k' );
    }

    public function test_notificacion_autorizada(): void {
        $n = RedsysNotification::from_request( $this->config(), $this->notification( $this->authorized() ) );

        $this->assertNotNull( $n );
        $this->assertTrue( $n->is_authorized() );
        $this->assertSame( '2611Q0000007', $n->order() );
        $this->assertSame( 4500, $n->cents() );
        $this->assertSame( 'abc123ref', $n->identifier() );
        $this->assertSame( '2611171015000', $n->cof_txnid() );
        $this->assertSame( '2812', $n->expiry() );
        $this->assertSame( '454881******0004', $n->masked_card() );
        $this->assertSame( '17/11/2026', $n->get( 'Ds_Date' ), 'Los valores codificados para URL se decodifican.' );
        $this->assertSame( '987654', $n->get( 'DS_AUTHORISATIONCODE' ), 'Sin distinguir mayúsculas.' );
    }

    public function test_notificacion_denegada(): void {
        $params = $this->authorized();
        $params['Ds_Response'] = '0190';
        $n = RedsysNotification::from_request( $this->config(), $this->notification( $params ) );

        $this->assertNotNull( $n );
        $this->assertFalse( $n->is_authorized() );
    }

    public function test_notificaciones_no_validas(): void {
        $config = $this->config();
        $good = $this->notification( $this->authorized() );

        $this->assertNull( RedsysNotification::from_request( $config, [] ) );
        $this->assertNull( RedsysNotification::from_request( $config, [ 'Ds_SignatureVersion' => 'OTRA' ] + $good ) );
        $this->assertNull( RedsysNotification::from_request( $config, [ 'Ds_Signature' => 'falsa' ] + $good ) );
        $this->assertNull( RedsysNotification::from_request( $config, $this->notification( $this->authorized(), base64_encode( str_repeat( 'z', 24 ) ) ) ), 'Firmada con otra clave.' );

        // Firma correcta con la clave buena, pero de otro comercio.
        $other = $this->authorized();
        $other['Ds_MerchantCode'] = '111111111';
        $this->assertNull( RedsysNotification::from_request( $config, $this->notification( $other ) ) );

        // Sin credenciales no se acepta nada.
        $this->assertNull( RedsysNotification::from_request( new RedsysConfig( '', '', '' ), $good ) );
    }

    public function test_importe_ausente(): void {
        $params = $this->authorized();
        unset( $params['Ds_Amount'] );
        $n = RedsysNotification::from_request( $this->config(), $this->notification( $params ) );

        $this->assertSame( -1, $n->cents() );
    }
}
