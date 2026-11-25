<?php
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysOrder;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysSignature;
use PHPUnit\Framework\TestCase;

/**
 * Cliente de Redsys sin conexión (SPEC-COBROS §5.3).
 *
 * La clave es la de pruebas que Redsys publica para el comercio genérico
 * 999008881: no es una credencial del club. La firma esperada se calculó
 * aparte, con la línea de comandos de openssl (3DES + HMAC SHA-256), para no
 * comprobar el código contra sí mismo.
 */
class RedsysTest extends TestCase {

    private const KEY = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';

    private const PARAMS_JSON = '{"DS_MERCHANT_AMOUNT":"4500","DS_MERCHANT_ORDER":"2611Q0000001","DS_MERCHANT_MERCHANTCODE":"999008881","DS_MERCHANT_CURRENCY":"978","DS_MERCHANT_TRANSACTIONTYPE":"0","DS_MERCHANT_TERMINAL":"1"}';

    private const SIGNATURE = 'LfTC13Ua3P8ahFnkHAkVh9suzOs4/BsZbk3l47uR6nc=';

    private function params(): array {
        return json_decode( self::PARAMS_JSON, true );
    }

    public function test_codifica_los_parametros_en_json_y_base64(): void {
        $this->assertSame( base64_encode( self::PARAMS_JSON ), RedsysSignature::encode_params( $this->params() ) );
    }

    public function test_firma_igual_que_openssl(): void {
        $signer = new RedsysSignature( self::KEY );
        $encoded = RedsysSignature::encode_params( $this->params() );

        $this->assertSame( self::SIGNATURE, $signer->sign( '2611Q0000001', $encoded ) );
    }

    public function test_verifica_la_firma_en_base64_normal_y_url(): void {
        $signer = new RedsysSignature( self::KEY );
        $encoded = RedsysSignature::encode_params( $this->params() );

        $this->assertTrue( $signer->verify( $encoded, self::SIGNATURE ) );
        $this->assertTrue( $signer->verify( strtr( $encoded, '+/', '-_' ), strtr( self::SIGNATURE, '+/', '-_' ) ) );
    }

    public function test_rechaza_firma_falsa_parametros_cambiados_u_otra_clave(): void {
        $signer = new RedsysSignature( self::KEY );
        $encoded = RedsysSignature::encode_params( $this->params() );

        $this->assertFalse( $signer->verify( $encoded, '' ) );
        $this->assertFalse( $signer->verify( $encoded, 'AAAA' . substr( self::SIGNATURE, 4 ) ) );

        $tampered = $this->params();
        $tampered['DS_MERCHANT_AMOUNT'] = '1';
        $this->assertFalse( $signer->verify( RedsysSignature::encode_params( $tampered ), self::SIGNATURE ) );

        $other = new RedsysSignature( base64_encode( str_repeat( 'x', 24 ) ) );
        $this->assertFalse( $other->verify( $encoded, self::SIGNATURE ) );
    }

    public function test_sin_numero_de_pedido_no_verifica(): void {
        $signer = new RedsysSignature( self::KEY );
        $params = $this->params();
        unset( $params['DS_MERCHANT_ORDER'] );
        $encoded = RedsysSignature::encode_params( $params );

        $this->assertFalse( $signer->verify( $encoded, $signer->sign( '', $encoded ) ) );
    }

    public function test_lee_el_pedido_de_una_notificacion(): void {
        $notification = base64_encode( '{"Ds_Order":"2611Q0000042","Ds_Response":"0000"}' );

        $this->assertSame( '2611Q0000042', RedsysSignature::order_from( RedsysSignature::decode_params( strtr( $notification, '+/', '-_' ) ) ) );
        $this->assertSame( [], RedsysSignature::decode_params( 'no es base64 !!' ) );
    }

    public function test_clave_no_valida(): void {
        $this->expectException( \InvalidArgumentException::class );
        new RedsysSignature( 'corta' );
    }

    public function test_numero_de_pedido(): void {
        $this->assertSame( '2611Q0000001', RedsysOrder::number( 1, '2026-11-17' ) );
        $this->assertSame( '2701Q1234567', RedsysOrder::number( 1234567, '2027-01-05 10:00:00' ) );
        $this->assertSame( 12, strlen( RedsysOrder::number( 9999999, '2026-12-31' ) ) );
        $this->assertSame( 42, RedsysOrder::transaction_id( '2611Q0000042' ) );
        $this->assertSame( 0, RedsysOrder::transaction_id( '000000012345' ) );
        $this->assertSame( 0, RedsysOrder::transaction_id( '2611Q42' ) );
    }

    public function test_numero_de_pedido_fuera_de_rango(): void {
        $this->expectException( \InvalidArgumentException::class );
        RedsysOrder::number( 10000000, '2026-11-17' );
    }

    public function test_importes_en_centimos(): void {
        $this->assertSame( 4500, RedsysOrder::cents( 45.0 ) );
        $this->assertSame( 3910, RedsysOrder::cents( 39.1 ) );
        $this->assertSame( 1, RedsysOrder::cents( 0.01 ) );
        $this->assertSame( 0, RedsysOrder::cents( 0.0 ) );
        $this->assertSame( 39.1, RedsysOrder::euros( '3910' ) );
    }

    public function test_respuestas_autorizadas(): void {
        $this->assertTrue( RedsysOrder::is_authorized( '0000' ) );
        $this->assertTrue( RedsysOrder::is_authorized( '0099' ) );
        $this->assertTrue( RedsysOrder::is_authorized( 0 ) );
        $this->assertFalse( RedsysOrder::is_authorized( '0100' ) );
        $this->assertFalse( RedsysOrder::is_authorized( '0190' ) );
        $this->assertFalse( RedsysOrder::is_authorized( '9915' ) );
        $this->assertFalse( RedsysOrder::is_authorized( '' ) );
        $this->assertFalse( RedsysOrder::is_authorized( null ) );
        $this->assertFalse( RedsysOrder::is_authorized( 'SIS0042' ) );
    }

    public function test_configuracion(): void {
        $test = new RedsysConfig( '999008881', '1', self::KEY );
        $this->assertTrue( $test->is_configured() );
        $this->assertFalse( $test->is_live() );
        $this->assertStringContainsString( 'sis-t.redsys.es', $test->redirect_url() );
        $this->assertStringContainsString( 'sis-t.redsys.es', $test->rest_url() );

        $typo = new RedsysConfig( '999008881', '1', self::KEY, 'LIVE' );
        $this->assertFalse( $typo->is_live(), 'Solo «live» exacto usa el entorno real.' );

        $live = new RedsysConfig( '999008881', '1', self::KEY, RedsysConfig::ENV_LIVE );
        $this->assertTrue( $live->is_live() );
        $this->assertSame( 'https://sis.redsys.es/sis/realizarPago', $live->redirect_url() );

        $this->assertFalse( ( new RedsysConfig( '', '1', self::KEY ) )->is_configured() );
        $this->assertFalse( RedsysConfig::from_constants()->is_configured() );
    }
}
