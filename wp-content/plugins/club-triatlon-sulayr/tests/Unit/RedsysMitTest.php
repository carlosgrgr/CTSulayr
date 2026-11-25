<?php
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRequest;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysRestResult;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysSignature;
use PHPUnit\Framework\TestCase;

/**
 * Cobro sin el socio con la tarjeta guardada, por la API REST (SPEC-COBROS §5.2).
 *
 * Clave pública de pruebas de Redsys (comercio genérico 999008881).
 */
class RedsysMitTest extends TestCase {

    private const KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    /**
     * Respuesta real del entorno de pruebas de Redsys (23 de noviembre de 2026)
     * a una petición REST del comercio genérico: denegada con 0195 (el emisor
     * exige autenticación). Firmada por Redsys, en base64 «URL».
     */
    private const REAL_ORDER = '2611T5452676';
    private const REAL_BODY = '{"Ds_SignatureVersion":"HMAC_SHA256_V1","Ds_MerchantParameters":"eyJEc19BbW91bnQiOiIxMjMiLCJEc19DdXJyZW5jeSI6Ijk3OCIsIkRzX09yZGVyIjoiMjYxMVQ1NDUyNjc2IiwiRHNfTWVyY2hhbnRDb2RlIjoiOTk5MDA4ODgxIiwiRHNfVGVybWluYWwiOiIxIiwiRHNfUmVzcG9uc2UiOiIwMTk1IiwiRHNfQXV0aG9yaXNhdGlvbkNvZGUiOiIiLCJEc19UcmFuc2FjdGlvblR5cGUiOiIwIiwiRHNfU2VjdXJlUGF5bWVudCI6IjAiLCJEc19MYW5ndWFnZSI6IjEiLCJEc19DYXJkTnVtYmVyIjoiNDU0ODgxKioqKioqMDAwMyIsIkRzX01lcmNoYW50RGF0YSI6IiIsIkRzX0NhcmRfQ291bnRyeSI6IjcyNCIsIkRzX0NhcmRfVHlwb2xvZ3kiOiJDT05TVU1PIiwiRHNfUHJvY2Vzc2VkUGF5TWV0aG9kIjoiMyIsIkRzX0NvbnRyb2xfMTc5NTQ1MjY3NjQ1MyI6IjE3OTU0NTI2NzY0NTMiLCJEc19FQ0kiOiIwNyIsIkRzX1Jlc3BvbnNlX0Rlc2NyaXB0aW9uIjoiRU1JU09SIEVYSUdFIEFVVEVOVElDQUNJw5NOIn0=","Ds_Signature":"KZJHsuMD82lcr1bIHw0z7iuvj-k2ktZmk-5XgSGo1V4="}';

    private function config(): RedsysConfig {
        return new RedsysConfig( '999008881', '1', self::KEY );
    }

    /** Cuerpo firmado como lo firmaría Redsys. */
    private function signed_body( array $params, string $key = self::KEY ): string {
        $encoded = strtr( RedsysSignature::encode_params( $params ), '+/', '-_' );

        return (string) json_encode( [
            'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
            'Ds_MerchantParameters' => $encoded,
            'Ds_Signature' => strtr( ( new RedsysSignature( $key ) )->sign( (string) $params['Ds_Order'], $encoded ), '+/', '-_' ),
        ] );
    }

    private function authorized(): array {
        return [
            'Ds_Amount' => '4500',
            'Ds_Currency' => '978',
            'Ds_Order' => '2612Q0000042',
            'Ds_MerchantCode' => '999008881',
            'Ds_Terminal' => '1',
            'Ds_Response' => '0000',
            'Ds_AuthorisationCode' => '123456',
            'Ds_TransactionType' => '0',
            'Ds_CardNumber' => '454881******0003',
        ];
    }

    public function test_parametros_del_cobro_mit(): void {
        $params = RedsysRequest::mit_params( $this->config(), '2612Q0000042', 4500, ' ref-guardada ', 'TXN-INICIAL', 'Recibo 2026-12-0001' );

        $this->assertSame( '4500', $params['DS_MERCHANT_AMOUNT'] );
        $this->assertSame( '2612Q0000042', $params['DS_MERCHANT_ORDER'] );
        $this->assertSame( '999008881', $params['DS_MERCHANT_MERCHANTCODE'] );
        $this->assertSame( '978', $params['DS_MERCHANT_CURRENCY'] );
        $this->assertSame( '0', $params['DS_MERCHANT_TRANSACTIONTYPE'] );
        $this->assertSame( '1', $params['DS_MERCHANT_TERMINAL'] );
        $this->assertSame( 'ref-guardada', $params['DS_MERCHANT_IDENTIFIER'] );
        $this->assertSame( 'true', $params['DS_MERCHANT_DIRECTPAYMENT'] );
        $this->assertSame( 'MIT', $params['DS_MERCHANT_EXCEP_SCA'] );
        $this->assertSame( 'N', $params['DS_MERCHANT_COF_INI'] );
        $this->assertSame( 'R', $params['DS_MERCHANT_COF_TYPE'] );
        $this->assertSame( 'TXN-INICIAL', $params['DS_MERCHANT_COF_TXNID'] );
        $this->assertArrayNotHasKey( 'DS_MERCHANT_MERCHANTURL', $params, 'La respuesta llega en la misma llamada.' );
    }

    public function test_sin_cof_txnid_no_se_manda(): void {
        $params = RedsysRequest::mit_params( $this->config(), '2612Q0000042', 4500, 'ref', '', 'x' );

        $this->assertArrayNotHasKey( 'DS_MERCHANT_COF_TXNID', $params );
    }

    public function test_sin_referencia_o_sin_importe_no_se_cobra(): void {
        foreach ( [ [ 4500, '' ], [ 0, 'ref' ] ] as $case ) {
            try {
                RedsysRequest::mit_params( $this->config(), '2612Q0000042', $case[0], $case[1], '', 'x' );
                $this->fail( 'Tenía que fallar.' );
            } catch ( \InvalidArgumentException $e ) {
                $this->assertNotSame( '', $e->getMessage() );
            }
        }
    }

    public function test_cuerpo_json_firmado(): void {
        $body = json_decode( RedsysRequest::mit_body( $this->config(), '2612Q0000042', 4500, 'ref', 'TXN', 'x' ), true );

        $this->assertSame( 'HMAC_SHA256_V1', $body['Ds_SignatureVersion'] );
        $this->assertSame( 'ref', RedsysSignature::decode_params( $body['Ds_MerchantParameters'] )['DS_MERCHANT_IDENTIFIER'] );
        $this->assertTrue( ( new RedsysSignature( self::KEY ) )->verify( $body['Ds_MerchantParameters'], $body['Ds_Signature'] ) );
    }

    public function test_respuesta_real_de_redsys_se_verifica(): void {
        $result = RedsysRestResult::parse( $this->config(), self::REAL_ORDER, 200, self::REAL_BODY );

        $this->assertSame( RedsysRestResult::OUTCOME_DENIED, $result->outcome() );
        $this->assertTrue( $result->is_signed() );
        $this->assertSame( '0195', $result->notification()->response() );
        $this->assertSame( '454881******0003', $result->notification()->masked_card(), 'La API REST lo llama Ds_CardNumber.' );
    }

    public function test_autorizada(): void {
        $result = RedsysRestResult::parse( $this->config(), '2612Q0000042', 200, $this->signed_body( $this->authorized() ) );

        $this->assertSame( RedsysRestResult::OUTCOME_AUTHORIZED, $result->outcome() );
        $this->assertSame( 4500, $result->notification()->cents() );
        $this->assertSame( '123456', $result->params()['Ds_AuthorisationCode'] );
    }

    public function test_error_sis_es_rechazada_sin_cargo(): void {
        $result = RedsysRestResult::parse( $this->config(), '2612Q0000042', 200, '{"errorCode":"SIS0617","errorCodeDescription":"Error del parámetro DS_MERCHANT_EXCEP_SCA"}' );

        $this->assertSame( RedsysRestResult::OUTCOME_REJECTED, $result->outcome() );
        $this->assertFalse( $result->is_signed() );
        $this->assertSame( 'SIS0617', $result->error() );
        $this->assertSame( [ 'errorCode' => 'SIS0617', 'errorCodeDescription' => 'Error del parámetro DS_MERCHANT_EXCEP_SCA' ], $result->params() );
    }

    public function test_pedido_repetido_no_es_seguro(): void {
        $result = RedsysRestResult::parse( $this->config(), '2612Q0000042', 200, '{"errorCode":"SIS0051"}' );

        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, $result->outcome(), 'Puede que la primera petición sí cobrara.' );
    }

    public function test_respuestas_desconocidas(): void {
        $config = $this->config();
        $good = $this->signed_body( $this->authorized() );

        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, RedsysRestResult::parse( $config, '2612Q0000042', 0, '' )->outcome(), 'Sin respuesta.' );
        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, RedsysRestResult::parse( $config, '2612Q0000042', 502, $good )->outcome(), 'HTTP distinto de 200.' );
        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, RedsysRestResult::parse( $config, '2612Q0000042', 200, '<html>' )->outcome(), 'JSON roto.' );
        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, RedsysRestResult::parse( $config, '2612Q0000042', 200, $this->signed_body( $this->authorized(), base64_encode( str_repeat( 'z', 24 ) ) ) )->outcome(), 'Firma con otra clave.' );
        $this->assertSame( RedsysRestResult::OUTCOME_UNKNOWN, RedsysRestResult::parse( $config, '2612Q0000099', 200, $good )->outcome(), 'Respuesta de otro pedido.' );
        $this->assertSame( 'pedido', RedsysRestResult::parse( $config, '2612Q0000099', 200, $good )->error() );
        $this->assertSame( 'http_502', RedsysRestResult::parse( $config, '2612Q0000042', 502, $good )->error() );
    }
}
