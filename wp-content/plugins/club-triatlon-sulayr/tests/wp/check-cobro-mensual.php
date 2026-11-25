<?php
/**
 * Comprobación del cobro mensual con tarjeta (SPEC-COBROS, parte C). Solo en local.
 *
 *   wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-cobro-mensual.php
 *
 * Simula marzo de 2001 día a día (MonthlyCharges::use_today) con socios y
 * recibos de prueba: aprobación, aviso al club sin aprobar, avisos previos,
 * cobro, reintentos, aviso tras el último, baja (C14), caducidad y cobro de
 * un recibo concreto (C22). Las respuestas de Redsys se simulan firmadas con
 * la clave pública de pruebas. No llama a Redsys ni envía emails. Lo borra
 * todo al terminar.
 */

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Payments\CardCharges;
use ClubTriatlonSulayr\Modules\Payments\CardPayments;
use ClubTriatlonSulayr\Modules\Payments\ChargeMethod;
use ClubTriatlonSulayr\Modules\Payments\ChargesPanel;
use ClubTriatlonSulayr\Modules\Payments\MonthlyCharges;
use ClubTriatlonSulayr\Modules\Payments\PaymentCards;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysSignature;
use ClubTriatlonSulayr\Modules\Payments\TpvTransactions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$fails = 0;
$check = function ( string $label, bool $ok ) use ( &$fails ) {
    echo ( $ok ? 'OK    ' : 'FALLO ' ) . $label . "\n";
    if ( ! $ok ) {
        $fails++;
    }
};

global $wpdb;
$period = '2001-03';
$users = [];
$receipt_ids = [];
$mails = [];
$catch_mail = function ( $return, $atts ) use ( &$mails ) {
    $mails[] = $atts;
    return true;
};
add_filter( 'pre_wp_mail', $catch_mail, 10, 2 );
$subjects = function ( string $needle ) use ( &$mails ) {
    return count( array_filter( $mails, function ( $mail ) use ( $needle ) {
        return false !== mb_stripos( (string) $mail['subject'], $needle );
    } ) );
};
$to = function ( int $user_id ) use ( &$mails ) {
    $email = get_userdata( $user_id )->user_email;
    return array_values( array_filter( $mails, function ( $mail ) use ( $email ) {
        return in_array( $email, (array) $mail['to'], true );
    } ) );
};

$test_key = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // Clave pública de pruebas de Redsys.
// Respuesta por referencia: REF-OK cobra, REF-KO nunca, REF-KO2 falla la primera vez y luego cobra.
$calls = [];
$transport = function ( $url, $body ) use ( &$calls, $test_key ) {
    $data = json_decode( $body, true );
    $params = RedsysSignature::decode_params( (string) $data['Ds_MerchantParameters'] );
    $ref = (string) $params['DS_MERCHANT_IDENTIFIER'];
    $calls[ $ref ] = ( $calls[ $ref ] ?? 0 ) + 1;
    $code = 'REF-OK' === $ref || ( 'REF-KO2' === $ref && $calls[ $ref ] > 1 ) ? '0000' : '0190';
    $answer = [
        'Ds_Amount' => $params['DS_MERCHANT_AMOUNT'],
        'Ds_Currency' => '978',
        'Ds_Order' => $params['DS_MERCHANT_ORDER'],
        'Ds_MerchantCode' => '999008881',
        'Ds_Terminal' => '1',
        'Ds_Response' => $code,
        'Ds_AuthorisationCode' => '0000' === $code ? '112233' : '',
    ];
    $encoded = strtr( RedsysSignature::encode_params( $answer ), '+/', '-_' );
    return [ 'code' => 200, 'body' => (string) wp_json_encode( [
        'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
        'Ds_MerchantParameters' => $encoded,
        'Ds_Signature' => strtr( ( new RedsysSignature( $test_key ) )->sign( (string) $answer['Ds_Order'], $encoded ), '+/', '-_' ),
    ] ) ];
};

$member = function ( string $name, string $method, string $ref = '', string $expiry = '3012' ) use ( &$users ) {
    $user_id = wp_insert_user( [
        'user_login' => 'prueba_cobro_' . strtolower( $name ) . '_' . wp_generate_password( 4, false ),
        'user_email' => 'prueba-cobro-' . strtolower( $name ) . '-' . wp_generate_password( 4, false ) . '@example.invalid',
        'user_pass' => wp_generate_password(),
        'role' => 'athlete',
        'first_name' => $name,
        'last_name' => 'Prueba',
    ] );
    if ( is_wp_error( $user_id ) ) {
        throw new RuntimeException( $user_id->get_error_message() );
    }
    $users[] = $user_id;
    update_user_meta( $user_id, ChargeMethod::META, $method );
    if ( '' !== $ref ) {
        PaymentCards::save( $user_id, $ref, 'TXN-' . $ref, '454881******0003', $expiry, 0 );
    }
    return $user_id;
};
$receipt = function ( int $user_id, float $total, string $month = '2001-03' ) use ( $wpdb, &$receipt_ids ) {
    $wpdb->insert( Receipts::table(), [
        'number' => 'PRUEBA-C-' . $user_id . '-' . $month,
        'user_id' => $user_id,
        'period' => $month,
        'total' => $total,
        'status' => Receipts::STATUS_PENDING,
        'generated_by' => get_current_user_id(),
        'generated_at' => current_time( 'mysql' ),
    ] );
    $id = (int) $wpdb->insert_id;
    $receipt_ids[] = $id;
    $wpdb->insert( Receipts::lines_table(), [ 'receipt_id' => $id, 'user_id' => $user_id, 'type' => 'manual', 'concept' => 'Cuota de prueba', 'amount' => $total, 'created_at' => current_time( 'mysql' ) ] );
    return $id;
};
$charges = function ( int $receipt_id ) use ( $wpdb ) {
    return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . TpvTransactions::table() . ' WHERE receipt_id = %d ORDER BY id', $receipt_id ) );
};
$day = function ( string $date ) {
    MonthlyCharges::use_today( $date );
    return MonthlyCharges::run();
};

$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) ( $admin[0] ?? 1 ) );
$saved_option = get_option( MonthlyCharges::OPTION, [] );

try {
    CardPayments::use_config( new RedsysConfig( '999008881', '1', $test_key ) );
    CardCharges::use_transport( $transport );

    $a = $member( 'Ana', ChargeMethod::TPV, 'REF-OK' );
    $b = $member( 'Bea', ChargeMethod::TPV, 'REF-KO' );
    $c = $member( 'Carlos', ChargeMethod::TPV, 'REF-KO2' );
    $d = $member( 'Dani', ChargeMethod::OTHER, 'REF-OK' );
    $e = $member( 'Eva', ChargeMethod::TPV );
    $f = $member( 'Fede', ChargeMethod::TPV, 'REF-OK', '0103' ); // Caduca el 31/03/2001.
    $g = $member( 'Gema', ChargeMethod::TPV, 'REF-OK' );
    $ra = $receipt( $a, 40 );
    $rb = $receipt( $b, 25.5 );
    $rc = $receipt( $c, 30 );
    $rd = $receipt( $d, 40 );
    $re = $receipt( $e, 40 );
    $rf = $receipt( $f, 12 );
    $rg = $receipt( $g, 40 );
    Receipts::add_payment( $rg, 40, '2001-03-02', (string) array_key_first( Receipts::payment_methods() ) );

    // Día 4: sin aprobar y antes del día de cobro.
    $mails = [];
    $r = $day( '2001-03-04' );
    $check( 'Antes del día de cobro y sin aprobar: nada', 0 === $r['charged'] + $r['failed'] && 0 === $subjects( 'sin aprobar' ) && empty( $charges( $ra ) ) );
    $check( 'Tarjeta que caduca en 30 días: aviso al socio (C23)', 1 === $r['expiring'] && 1 === count( $to( $f ) ) && 1 === $subjects( 'caduca' ) );
    $mails = [];
    $r = $day( '2001-03-04' );
    $check( 'El aviso de caducidad sale una sola vez por tarjeta', 0 === $r['expiring'] && 0 === count( $mails ) );

    // Día 5 sin aprobar (criterio 9).
    $r = $day( '2001-03-05' );
    $check( 'Criterio 9: día de cobro sin aprobar → no se cobra y el club recibe el aviso', 0 === $r['charged'] + $r['failed'] && empty( $charges( $ra ) ) && 1 === $subjects( 'sin aprobar' ) );
    $mails = [];
    $day( '2001-03-05' );
    $check( 'Criterio 9: el aviso al club sale una sola vez', 0 === count( $mails ) );

    // Resumen y aprobación (§6.1).
    $summary = MonthlyCharges::summary( $period );
    $ids = array_map( function ( $receipt ) {
        return (int) $receipt->id;
    }, $summary['receipts'] );
    sort( $ids );
    $expected = [ $ra, $rb, $rc, $rf ];
    sort( $expected );
    $check( 'Resumen: entran A, B, C y F (C17), por 107,50 €', $expected === $ids && 107.5 === $summary['amount'] );
    $check( 'Resumen: E con medio «Tarjeta (TPV)» y sin tarjeta, aparte', 1 === count( $summary['without_card'] ) && $re === (int) $summary['without_card'][0]->id );
    $panel = new ChargesPanel();
    $render = function ( $what, $arg ) use ( $panel ) {
        ob_start();
        $panel->$what( $arg );
        return (string) ob_get_clean();
    };
    $html = $render( 'render_month', $period );
    $check( 'Panel: resumen, socios sin tarjeta y botón «Aprobar cobro»', false !== strpos( $html, 'Se cobrarán 4 recibos por 107,50' ) && false !== strpos( $html, 'Eva Prueba' ) && false !== strpos( $html, 'Aprobar cobro de' ) && false !== strpos( $html, 'llegó sin aprobar' ) );
    $check( 'Panel: solo en el mes en curso', '' === $render( 'render_month', '2001-02' ) );
    $check( 'Aprobar un mes que no es el en curso: no', is_wp_error( MonthlyCharges::approve( '2001-02', get_current_user_id() ) ) );
    $check( 'Aprobar el mes', true === MonthlyCharges::approve( $period, get_current_user_id() ) && MonthlyCharges::is_approved( $period ) );
    $check( 'Aprobar dos veces: no', is_wp_error( MonthlyCharges::approve( $period, get_current_user_id() ) ) );
    $check( 'Al aprobar se programa la tarea en segundo plano', false !== wp_next_scheduled( MonthlyCharges::HOOK_NOW ) );
    $check( 'Anular la aprobación antes de empezar', true === MonthlyCharges::unapprove( $period ) && false === MonthlyCharges::is_approved( $period ) );
    MonthlyCharges::approve( $period, get_current_user_id() );
    wp_clear_scheduled_hook( MonthlyCharges::HOOK_NOW );
    $html = $render( 'render_month', $period );
    $check( 'Panel: aprobado, con «Anular la aprobación»', false !== strpos( $html, 'Aprobado por' ) && false !== strpos( $html, 'Anular la aprobación' ) && false === strpos( $html, 'Aprobar cobro de' ) );

    // Día 5 aprobado (tarde): avisos previos y cobro al momento (C16, criterio 10).
    $mails = [];
    $r = $day( '2001-03-05' );
    $check( 'Aviso previo a los 4 socios del cobro (C23)', 4 === $r['notices'] && 4 === $subjects( 'Cargo de tu recibo' ) );
    $notice = array_values( array_filter( $to( $b ), function ( $mail ) {
        return false !== strpos( (string) $mail['subject'], 'Cargo de tu recibo' );
    } ) );
    $check( 'El aviso previo lleva importe, desglose y día', ! empty( $notice ) && false !== strpos( (string) $notice[0]['message'], '25,50' ) && false !== strpos( (string) $notice[0]['message'], 'Cuota de prueba' ) && false !== strpos( (string) $notice[0]['message'], '5 de marzo' ) );
    $check( 'Criterio 10: cobra A y F, fallan B y C', 2 === $r['charged'] && 2 === $r['failed'] && Receipts::STATUS_PAID === Receipts::get( $ra )->status && Receipts::STATUS_PAID === Receipts::get( $rf )->status && 0.0 === Receipts::paid( $rb ) );
    $check( 'Criterio 10: D («Otro medio») sin cargo ni email; E sin tarjeta y G pagado, sin cargo', empty( $charges( $rd ) ) && empty( $to( $d ) ) && empty( $charges( $re ) ) && empty( $to( $e ) ) && empty( $charges( $rg ) ) );
    $failed_b = array_values( array_filter( $to( $b ), function ( $mail ) {
        return false !== strpos( (string) $mail['subject'], 'No hemos podido cobrar' );
    } ) );
    $check( 'Criterio 13: email de cobro realizado a A y F, y de fallido a B y C con el reintento del día 8', 2 === $subjects( 'pagado con tu tarjeta' ) && 2 === $subjects( 'No hemos podido cobrar' ) && 1 === count( $failed_b ) && false !== strpos( (string) $failed_b[0]['message'], '8 de marzo' ) );
    $state = MonthlyCharges::state( $period );
    $check( 'Estado del mes: cobrado el día 5 con su resumen', '2001-03-05' === ( $state['charged_on'] ?? '' ) && 2 === (int) ( $state['batch']['charged'] ?? 0 ) && 52.0 === (float) ( $state['batch']['amount'] ?? 0 ) );
    $check( 'Ya empezado: no se puede anular la aprobación', is_wp_error( MonthlyCharges::unapprove( $period ) ) );
    $html = $render( 'render_month', $period );
    $check( 'Panel: progreso del cobro, sin botones de aprobar', false !== strpos( $html, 'Cobro del 5 de marzo' ) && false === strpos( $html, 'Anular la aprobación' ) );
    $html = $render( 'render_receipt', Receipts::get( $rb ) );
    $check( 'Detalle del recibo: operación denegada y «Cobrar con la tarjeta guardada» (C22)', false !== strpos( $html, 'Denegada (0190)' ) && false !== strpos( $html, 'Cobrar 25,50' ) );
    $check( 'Detalle de un recibo pagado: sin botón de cobro', false === strpos( $render( 'render_receipt', Receipts::get( $ra ) ), 'Cobrar ' ) );

    // Criterio 11: otra vez el mismo día.
    $before = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TpvTransactions::table() );
    $mails = [];
    $r = $day( '2001-03-05' );
    $check( 'Criterio 11: ejecutar la tarea dos veces el mismo día no cobra dos veces', $before === (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . TpvTransactions::table() ) && 0 === count( $mails ) );

    // Día 6: baja efectiva con un recibo pendiente de un mes aprobado (C14, criterio 14).
    $h = $member( 'Hugo', ChargeMethod::TPV, 'REF-OK' );
    $rh = $receipt( $h, 18 );
    update_user_meta( $h, CustomFields::USER_PROFILE_ATHLETE_STATUS, CustomFields::STATUS_LEFT );
    $r = $day( '2001-03-06' );
    $refs = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PaymentCards::table() . " WHERE user_id = %d AND identifier <> ''", $h ) );
    $check( 'Criterio 14: la baja cobra antes lo pendiente del mes aprobado y vacía la referencia', 1 === $r['leaves'] && Receipts::STATUS_PAID === Receipts::get( $rh )->status && 0 === $refs && null === PaymentCards::active( $h ) );
    $check( 'Día 6: no toca reintentar', 0 === $r['retried'] && 1 === count( $charges( $rb ) ) );

    // Día 8: primer reintento (criterio 12).
    $mails = [];
    $r = $day( '2001-03-08' );
    $last_b = TpvTransactions::latest_charge( $rb );
    $check( 'Criterio 12: el día 8 se reintentan B y C', 2 === $r['retried'] && 2 === count( $charges( $rb ) ) && 2 === count( $charges( $rc ) ) && TpvTransactions::TYPE_RETRY === $last_b->type && 2 === (int) $last_b->attempt );
    $check( 'C cobra en el reintento; B vuelve a fallar con el aviso del día 11', Receipts::STATUS_PAID === Receipts::get( $rc )->status && 1 === count( $to( $b ) ) && false !== strpos( (string) $to( $b )[0]['message'], '11 de marzo' ) && 1 === count( $to( $c ) ) );
    $mails = [];
    $r = $day( '2001-03-08' );
    $check( 'Día 8 otra vez: nada', 0 === $r['retried'] && 0 === count( $mails ) );
    $r = $day( '2001-03-10' );
    $check( 'Día 10: nada', 0 === $r['retried'] );

    // Día 11: segundo reintento y aviso al club.
    $mails = [];
    $r = $day( '2001-03-11' );
    $check( 'Criterio 12: el día 11 se reintenta B (intento 3)', 1 === $r['retried'] && 3 === count( $charges( $rb ) ) && 3 === (int) TpvTransactions::latest_charge( $rb )->attempt );
    $check( 'Criterio 12: tras el último, B sabe que no hay más intentos y el club recibe el aviso con B', false !== strpos( (string) $to( $b )[0]['message'], 'No haremos más intentos' ) && 1 === $subjects( 'sin cobrar tras los reintentos' ) && false !== strpos( implode( '', array_column( $mails, 'message' ) ), 'PRUEBA-C-' . $b ) );
    $mails = [];
    $r = $day( '2001-03-12' );
    $check( 'Día 12: nada más', 0 === $r['retried'] && 0 === count( $mails ) && 3 === count( $charges( $rb ) ) );

    // C22: cobrar un recibo concreto, con otra tarjeta.
    PaymentCards::save( $b, 'REF-OK', 'TXN', '454881******0004', '3012', 0 );
    $mails = [];
    $one = MonthlyCharges::charge_one( $rb );
    $check( 'C22: cobrar un recibo concreto sigue la numeración (intento 4) y avisa al socio', is_array( $one ) && CardPayments::RESULT_AUTHORIZED === $one['result'] && 4 === (int) $one['transaction']->attempt && Receipts::STATUS_PAID === Receipts::get( $rb )->status && 1 === count( $to( $b ) ) );

    // Caducidad: el mes siguiente la tarjeta de F está caducada.
    $r = $day( '2001-04-01' );
    $check( 'Tarjeta vencida → «caducada» y fuera del cobro', $r['expired'] >= 1 && null === PaymentCards::active( $f ) );

    // Sin credenciales: no cobra nada.
    CardPayments::use_config( new RedsysConfig( '', '', '' ) );
    $check( 'Sin credenciales no se aprueba', is_wp_error( MonthlyCharges::approve( '2001-04', get_current_user_id() ) ) );
} catch ( Throwable $e ) {
    $check( 'Excepción: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(), false );
} finally {
    MonthlyCharges::use_today( null );
    CardPayments::use_config( null );
    CardCharges::use_transport( null );
    wp_clear_scheduled_hook( MonthlyCharges::HOOK_NOW );
    update_option( MonthlyCharges::OPTION, $saved_option, false );
    remove_filter( 'pre_wp_mail', $catch_mail, 10 );
    foreach ( $receipt_ids as $id ) {
        $wpdb->delete( Receipts::payments_table(), [ 'receipt_id' => $id ], [ '%d' ] );
        $wpdb->delete( Receipts::lines_table(), [ 'receipt_id' => $id ], [ '%d' ] );
        $wpdb->delete( Receipts::table(), [ 'id' => $id ], [ '%d' ] );
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    require_once ABSPATH . 'wp-admin/includes/ms.php';
    foreach ( $users as $user_id ) {
        $wpdb->delete( TpvTransactions::table(), [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( PaymentCards::table(), [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( MembersSchema::table( MembersSchema::LOG ), [ 'user_id' => $user_id ], [ '%d' ] );
        is_multisite() ? wpmu_delete_user( $user_id ) : wp_delete_user( $user_id );
        $wpdb->delete( MembersSchema::table( MembersSchema::LOG ), [ 'user_id' => $user_id ], [ '%d' ] );
    }
}

echo "\n" . ( 0 === $fails ? 'Todo correcto.' : "{$fails} comprobaciones fallidas." ) . "\n";
