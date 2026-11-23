<?php
/**
 * Comprobación de la base del pago online con WordPress (SPEC-COBROS, parte B). Solo en local.
 *
 *   wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-pagos.php
 *
 * Crea un socio y recibos de prueba, comprueba esquema, medio de cobro,
 * tarjetas, operaciones, pago con medio «tpv», consentimiento y ajustes
 * (paso B1), y el pago desde Mis recibos con notificaciones simuladas y
 * firmadas con la clave pública de pruebas de Redsys (paso B2). Lo borra todo
 * al terminar. No llama a Redsys ni envía emails.
 */

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;
use ClubTriatlonSulayr\Modules\Payments\CardCharges;
use ClubTriatlonSulayr\Modules\Payments\CardPayments;
use ClubTriatlonSulayr\Modules\Payments\ChargesPanel;
use ClubTriatlonSulayr\Modules\Payments\ChargeMethod;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysConfig;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysSignature;
use ClubTriatlonSulayr\Modules\Payments\PaymentCards;
use ClubTriatlonSulayr\Modules\Payments\Redsys\RedsysOrder;
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
$user_id = 0;
$receipt_id = 0;
$receipt_ids = [];
$mails = [];
$catch_mail = function ( $return, $atts ) use ( &$mails ) {
    $mails[] = $atts;
    return true;
};
add_filter( 'pre_wp_mail', $catch_mail, 10, 2 );
$new_receipt = function ( int $user, float $total ) use ( $wpdb, &$receipt_ids ) {
    $wpdb->insert( Receipts::table(), [
        'number' => 'PRUEBA-' . $user . '-' . count( $receipt_ids ),
        'user_id' => $user,
        // Un recibo por socio y mes: cada recibo de prueba va en un mes de 2001.
        'period' => sprintf( '2001-%02d', count( $receipt_ids ) + 1 ),
        'total' => $total,
        'status' => Receipts::STATUS_PENDING,
        'generated_by' => get_current_user_id(),
        'generated_at' => current_time( 'mysql' ),
    ] );
    $receipt_ids[] = (int) $wpdb->insert_id;
    return (int) $wpdb->insert_id;
};
$test_key = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7'; // Clave pública de pruebas de Redsys.
$notify = function ( array $params, string $key = '' ) use ( $test_key ) {
    $encoded = strtr( RedsysSignature::encode_params( $params ), '+/', '-_' );
    return [
        'Ds_SignatureVersion' => 'HMAC_SHA256_V1',
        'Ds_MerchantParameters' => $encoded,
        'Ds_Signature' => strtr( ( new RedsysSignature( '' !== $key ? $key : $test_key ) )->sign( (string) $params['Ds_Order'], $encoded ), '+/', '-_' ),
    ];
};
$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) ( $admin[0] ?? 1 ) );

try {
    // Esquema.
    $check( 'Versión del esquema de socios = 6', 6 === (int) get_option( MembersSchema::VERSION_OPTION ) );
    foreach ( [ MembersSchema::PAYMENT_CARDS, MembersSchema::TPV_TRANSACTIONS ] as $t ) {
        $check( "Tabla {$t}", MembersSchema::table( $t ) === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', MembersSchema::table( $t ) ) ) );
    }

    $user_id = wp_insert_user( [
        'user_login' => 'prueba_pagos_' . wp_generate_password( 6, false ),
        'user_email' => 'prueba-pagos-' . wp_generate_password( 6, false ) . '@example.invalid',
        'user_pass' => wp_generate_password(),
        'role' => 'athlete',
        'first_name' => 'Prueba',
        'last_name' => 'Pagos',
    ] );
    if ( is_wp_error( $user_id ) ) {
        throw new RuntimeException( $user_id->get_error_message() );
    }

    // Medio de cobro (C6 a C9).
    $check( 'Medio de cobro por defecto = ajuste (otro)', ChargeMethod::OTHER === ChargeMethod::default_method() && ChargeMethod::OTHER === ChargeMethod::get( $user_id ) && '' === ChargeMethod::stored( $user_id ) );
    update_user_meta( $user_id, ChargeMethod::META, ChargeMethod::TPV );
    $check( 'Medio de cobro «tpv» en la ficha', ChargeMethod::is_tpv( $user_id ) );
    $logged = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MembersSchema::table( MembersSchema::LOG ) . ' WHERE user_id = %d AND field = %s', $user_id, ChargeMethod::META ) );
    $check( 'El cambio de medio de cobro queda en el registro (C9)', 1 === $logged );
    update_user_meta( $user_id, ChargeMethod::META, 'raro' );
    $check( 'Un valor desconocido sigue el ajuste', ChargeMethod::OTHER === ChargeMethod::get( $user_id ) );
    update_user_meta( $user_id, ChargeMethod::META, ChargeMethod::TPV );

    // Recibo de prueba de 45 €.
    $receipt_id = $new_receipt( $user_id, 45 );

    // Operaciones (§4.2, §5.3).
    $tx = TpvTransactions::create( $user_id, TpvTransactions::TYPE_MANUAL, 45.0, $receipt_id );
    $check( 'Operación creada «enviada» con Ds_Order AAMMQ + id', is_object( $tx ) && TpvTransactions::STATUS_SENT === $tx->status && RedsysOrder::number( (int) $tx->id, (string) $tx->created_at ) === $tx->ds_order );
    $check( 'Se encuentra por su Ds_Order', is_object( $tx ) && (int) $tx->id === (int) TpvTransactions::find_by_order( (string) $tx->ds_order )->id );
    $check( 'Recibo con operación en curso', TpvTransactions::in_flight( $receipt_id ) );
    $second = TpvTransactions::create( $user_id, TpvTransactions::TYPE_MANUAL, 45.0, $receipt_id );
    $check( 'No se crea otra operación sobre un recibo con una «enviada» (§5.2)', is_wp_error( $second ) );

    // Pago con medio tpv (§4.4).
    $manual = Receipts::add_payment( $receipt_id, 45.0, current_time( 'Y-m-d' ), Receipts::METHOD_TPV );
    $check( 'Un gestor no puede apuntar a mano un pago «tpv»', is_wp_error( $manual ) );
    $check( '«Tarjeta (TPV)» no sale en los medios del formulario manual', false === isset( Receipts::payment_methods()[ Receipts::METHOD_TPV ] ) );
    $payment_id = Receipts::add_tpv_payment( $receipt_id, 45.0, (string) $tx->ds_order, 0 );
    $check( 'Pago «tpv» apuntado y recibo pagado', is_int( $payment_id ) && $payment_id > 0 && Receipts::STATUS_PAID === Receipts::get( $receipt_id )->status );
    $payment = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . Receipts::payments_table() . ' WHERE id = %d', (int) $payment_id ) );
    $check( 'El pago guarda el Ds_Order en la nota y autor 0', null !== $payment && $tx->ds_order === $payment->note && 0 === (int) $payment->created_by && Receipts::METHOD_TPV === $payment->method );
    $check( 'Nombre del medio: «Tarjeta (TPV)»', 'Tarjeta (TPV)' === Receipts::method_label( Receipts::METHOD_TPV ) );

    $response = [ 'Ds_Order' => $tx->ds_order, 'Ds_Response' => '0000', 'Ds_AuthorisationCode' => '123456', 'Ds_Merchant_Identifier' => 'REF-SECRETA', 'Ds_Merchant_Cof_Txnid' => 'TXN-1', 'Ds_Signature' => 'x' ];
    $check( 'Cerrar la operación la primera vez', TpvTransactions::close( (int) $tx->id, TpvTransactions::STATUS_AUTHORIZED, $response, (int) $payment_id ) );
    $check( 'Cerrarla otra vez no hace nada (notificación repetida)', false === TpvTransactions::close( (int) $tx->id, TpvTransactions::STATUS_DENIED, $response ) );
    $closed = TpvTransactions::get( (int) $tx->id );
    $check( 'Queda autorizada con código y pago', TpvTransactions::STATUS_AUTHORIZED === $closed->status && '0000' === $closed->ds_response && '123456' === $closed->auth_code && (int) $payment_id === (int) $closed->payment_id );
    $check( 'La respuesta guardada no lleva la referencia, el Cof_Txnid ni la firma', false === strpos( (string) $closed->response, 'REF-SECRETA' ) && false === strpos( (string) $closed->response, 'TXN-1' ) && false === strpos( (string) $closed->response, 'Ds_Signature' ) );
    $check( 'El recibo ya no tiene operación en curso', false === TpvTransactions::in_flight( $receipt_id ) );

    // Tarjetas (C13, C14).
    $check( 'Sin tarjeta activa al principio', null === PaymentCards::active( $user_id ) );
    $first = PaymentCards::save( $user_id, 'REF-1', 'TXN-1', '454881******0004', '2812', (int) $tx->id );
    $card = PaymentCards::active( $user_id );
    $check( 'Tarjeta guardada y enmascarada con 4 cifras', is_int( $first ) && null !== $card && '**** 0004' === $card->masked && '2812' === $card->expiry );
    $check( 'Texto de la tarjeta', '**** 0004 (cad. 12/28)' === PaymentCards::describe( $card ) );
    $second_card = PaymentCards::save( $user_id, 'REF-2', 'TXN-2', '************1234', '2701', (int) $tx->id );
    $check( 'Guardar otra sustituye a la anterior (C13)', PaymentCards::STATUS_REPLACED === PaymentCards::get( (int) $first )->status && (int) $second_card === (int) PaymentCards::active( $user_id )->id );
    $check( 'Caducidad: vale hasta el final de su mes', false === PaymentCards::is_expired( PaymentCards::active( $user_id ), '2027-01-31' ) && PaymentCards::is_expired( PaymentCards::active( $user_id ), '2027-02-01' ) );
    $check( 'Sin referencia no se guarda', is_wp_error( PaymentCards::save( $user_id, '', '', '', '', 0 ) ) );

    $removed_hook = 0;
    $listener = function () use ( &$removed_hook ) {
        $removed_hook++;
    };
    add_action( 'sulayr_payment_card_removed', $listener );
    $check( 'Quitar la tarjeta', PaymentCards::remove( $user_id ) && null === PaymentCards::active( $user_id ) && 1 === $removed_hook );
    $check( 'Quitarla otra vez no hace nada', false === PaymentCards::remove( $user_id ) && 1 === $removed_hook );
    remove_action( 'sulayr_payment_card_removed', $listener );

    PaymentCards::save( $user_id, 'REF-3', 'TXN-3', '1111', '2905', 0 );
    PaymentCards::erase_for_leave( $user_id );
    $left = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . PaymentCards::table() . " WHERE user_id = %d AND ( identifier <> '' OR cof_txnid <> '' )", $user_id ) );
    $check( 'Baja: ninguna fila conserva referencia ni Cof_Txnid (C14)', 0 === $left && null === PaymentCards::active( $user_id ) );
    $card_log = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . MembersSchema::table( MembersSchema::LOG ) . ' WHERE user_id = %d AND area = %s', $user_id, MemberLog::AREA_CARD ) );
    $check( 'Cada cambio de tarjeta queda en el registro (C30)', 5 === $card_log );

    // Consentimiento de cargos recurrentes (C12).
    $consent_id = MemberConsents::record( MemberConsents::RECURRING_CHARGES, true, MemberConsents::BY_MEMBER, $user_id );
    $latest = MemberConsents::latest( $user_id, MemberConsents::RECURRING_CHARGES );
    $check( 'Autorización de cargos guardada con versión', $consent_id > 0 && null !== $latest && '1' === (string) $latest->version && 1 === (int) $latest->accepted );
    $check( 'La autorización no sale en el formulario de alta', false === isset( MemberConsents::types()[ MemberConsents::RECURRING_CHARGES ] ) );
    $check( 'Nombre de la autorización', 'Autorización de cargos recurrentes' === MemberConsents::label( MemberConsents::RECURRING_CHARGES ) );
    $check( 'Texto de la autorización con {dia_cobro}', false !== strpos( MemberConsents::text( MemberConsents::RECURRING_CHARGES ), '{dia_cobro}' ) );

    // Ajustes (§4.3).
    $check( 'Ajustes por defecto: día 5, reintentos 3 y 6, aviso 30', 5 === (int) MemberSettings::get( 'tpv_charge_day' ) && 3 === (int) MemberSettings::get( 'tpv_retry_first' ) && 6 === (int) MemberSettings::get( 'tpv_retry_second' ) && 30 === (int) MemberSettings::get( 'tpv_expiry_notice_days' ) );

    // ---------------------------------------------------------------
    // Paso B2: pago desde Mis recibos (§5.1, §7).
    // ---------------------------------------------------------------
    CardPayments::use_config( new RedsysConfig( '', '', '' ) );
    $check( 'B2: sin credenciales el pago con tarjeta no está disponible', false === CardPayments::is_enabled() && is_wp_error( CardPayments::start( $user_id, $new_receipt( $user_id, 10 ), true ) ) );
    CardPayments::use_config( new RedsysConfig( '999008881', '1', $test_key ) );
    $check( 'B2: con credenciales sí', CardPayments::is_enabled() );
    $check( 'B2: URL de notificación /wp-json/sulayr/v1/redsys', false !== strpos( CardPayments::notify_url(), 'sulayr/v1/redsys' ) );
    $routes = rest_get_server()->get_routes();
    $check( 'B2: ruta REST registrada', isset( $routes['/sulayr/v1/redsys'] ) );

    $r1 = $new_receipt( $user_id, 39.10 );
    $check( 'B2: sin la autorización de cargos no se va al banco (C12)', is_wp_error( CardPayments::start( $user_id, $r1, false ) ) && false === TpvTransactions::in_flight( $r1 ) );
    $other_user = (int) $admin[0];
    $check( 'B2: no se puede pagar un recibo ajeno', is_wp_error( CardPayments::start( $other_user, $r1, true ) ) );
    $consents_before = count( MemberConsents::rows( $user_id ) );
    $start = CardPayments::start( $user_id, $r1, true );
    $check( 'B2: empieza el pago con el pendiente en céntimos y pidiendo la tarjeta', is_array( $start ) && false !== strpos( $start['url'], 'sis-t.redsys.es' ) && '3910' === RedsysSignature::decode_params( $start['fields']['Ds_MerchantParameters'] )['DS_MERCHANT_AMOUNT'] && 'REQUIRED' === RedsysSignature::decode_params( $start['fields']['Ds_MerchantParameters'] )['DS_MERCHANT_IDENTIFIER'] );
    $check( 'B2: la autorización queda guardada con versión (C12)', count( MemberConsents::rows( $user_id ) ) === $consents_before + 1 && 1 === (int) MemberConsents::latest( $user_id, MemberConsents::RECURRING_CHARGES )->accepted );
    $check( 'B2: un segundo intento mientras está en curso se rechaza', is_wp_error( CardPayments::start( $user_id, $r1, true ) ) );
    $tx1 = $start['transaction'];

    $base = [ 'Ds_Amount' => '3910', 'Ds_Currency' => '978', 'Ds_Order' => $tx1->ds_order, 'Ds_MerchantCode' => '999008881', 'Ds_Terminal' => '1', 'Ds_Response' => '0000', 'Ds_AuthorisationCode' => '111111', 'Ds_Merchant_Identifier' => 'REF-B2', 'Ds_Merchant_Cof_Txnid' => 'TXN-B2', 'Ds_ExpiryDate' => '3001', 'Ds_Card_Number' => '454881******0004' ];
    $check( 'B2: firma falsa → no válida y no se apunta nada (criterio 5)', CardPayments::RESULT_INVALID === CardPayments::handle_notification( $notify( $base, base64_encode( str_repeat( 'z', 24 ) ) ) ) && 0.0 === Receipts::paid( $r1 ) );
    $check( 'B2: volver a la web sin notificación no apunta nada (criterio 6)', TpvTransactions::STATUS_SENT === TpvTransactions::get( (int) $tx1->id )->status && 0.0 === Receipts::paid( $r1 ) );
    $check( 'B2: notificación autorizada', CardPayments::RESULT_AUTHORIZED === CardPayments::handle_notification( $notify( $base ) ) );
    $paid = Receipts::get( $r1 );
    $card = PaymentCards::active( $user_id );
    $check( 'B2: recibo pagado con «Tarjeta (TPV)» y autor el socio (criterio 4)', Receipts::STATUS_PAID === $paid->status && Receipts::METHOD_TPV === Receipts::payments( $r1 )[0]->method && $user_id === (int) Receipts::payments( $r1 )[0]->created_by );
    $check( 'B2: tarjeta guardada en «Mi tarjeta» (criterio 4)', null !== $card && 'REF-B2' === $card->identifier && 'TXN-B2' === $card->cof_txnid && '**** 0004' === $card->masked && '3001' === $card->expiry );
    $check( 'B2: operación cerrada con su pago', (int) Receipts::payments( $r1 )[0]->id === (int) TpvTransactions::get( (int) $tx1->id )->payment_id );
    $check( 'B2: notificación repetida → no crea otro pago (criterio 5)', CardPayments::RESULT_DUPLICATE === CardPayments::handle_notification( $notify( $base ) ) && 1 === count( Receipts::payments( $r1 ) ) );
    $check( 'B2: Ds_Order desconocido', CardPayments::RESULT_UNKNOWN === CardPayments::handle_notification( $notify( [ 'Ds_Order' => '2611Q9999999' ] + $base ) ) );

    // Denegada.
    $r2 = $new_receipt( $user_id, 20 );
    $tx2 = CardPayments::start( $user_id, $r2, true )['transaction'];
    $check( 'B2: denegada → sin pago y la tarjeta anterior sigue', CardPayments::RESULT_DENIED === CardPayments::handle_notification( $notify( [ 'Ds_Order' => $tx2->ds_order, 'Ds_Amount' => '2000', 'Ds_Response' => '0190', 'Ds_Merchant_Identifier' => 'OTRA' ] + $base ) ) && 0.0 === Receipts::paid( $r2 ) && 'REF-B2' === PaymentCards::active( $user_id )->identifier );
    $check( 'B2: tras una denegada se puede volver a intentar', false === TpvTransactions::in_flight( $r2 ) && is_array( CardPayments::start( $user_id, $r2, true ) ) );

    // Importe distinto.
    $r3 = $new_receipt( $user_id, 15 );
    $tx3 = CardPayments::start( $user_id, $r3, true )['transaction'];
    $mails = [];
    $check( 'B2: autorizada por otro importe → error, sin pago y aviso al club', CardPayments::RESULT_MISMATCH === CardPayments::handle_notification( $notify( [ 'Ds_Order' => $tx3->ds_order, 'Ds_Amount' => '1' ] + $base ) ) && 0.0 === Receipts::paid( $r3 ) && TpvTransactions::STATUS_ERROR === TpvTransactions::get( (int) $tx3->id )->status && 1 === count( $mails ) );

    // El gestor apuntó el pago a mano mientras el socio pagaba.
    $r4 = $new_receipt( $user_id, 12 );
    $tx4 = CardPayments::start( $user_id, $r4, true )['transaction'];
    $manual_method = (string) array_key_first( Receipts::payment_methods() );
    $check( 'B2: el gestor apunta el pago a mano', true === Receipts::add_payment( $r4, 12, current_time( 'Y-m-d' ), $manual_method ) );
    $mails = [];
    $check( 'B2: cobrado sin pendiente → autorizada, sin segundo pago y aviso al club', CardPayments::RESULT_AUTHORIZED === CardPayments::handle_notification( $notify( [ 'Ds_Order' => $tx4->ds_order, 'Ds_Amount' => '1200' ] + $base ) ) && 1 === count( Receipts::payments( $r4 ) ) && 1 === count( $mails ) );

    // Operación abandonada: deja de bloquear a los 30 minutos.
    $r5 = $new_receipt( $user_id, 8 );
    $tx5 = CardPayments::start( $user_id, $r5, true )['transaction'];
    $wpdb->update( TpvTransactions::table(), [ 'created_at' => gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 31 * MINUTE_IN_SECONDS ) ], [ 'id' => (int) $tx5->id ] ); // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
    $check( 'B2: un pago abandonado deja de bloquear a los 30 minutos', false === TpvTransactions::in_flight( $r5 ) );
    $check( 'B2: su notificación tardía se procesa igual', CardPayments::RESULT_AUTHORIZED === CardPayments::handle_notification( $notify( [ 'Ds_Order' => $tx5->ds_order, 'Ds_Amount' => '800' ] + $base ) ) && Receipts::STATUS_PAID === Receipts::get( $r5 )->status );

    // REST.
    $r6 = $new_receipt( $user_id, 5 );
    $tx6 = CardPayments::start( $user_id, $r6, true )['transaction'];
    $request = new WP_REST_Request( 'POST', '/sulayr/v1/redsys' );
    $request->set_body_params( [ 'Ds_Signature' => 'falsa' ] + $notify( [ 'Ds_Order' => $tx6->ds_order, 'Ds_Amount' => '500' ] + $base ) );
    $check( 'B2: REST con firma falsa → 400', 400 === rest_do_request( $request )->get_status() );
    $request->set_body_params( $notify( [ 'Ds_Order' => $tx6->ds_order, 'Ds_Amount' => '500' ] + $base ) );
    $response = rest_do_request( $request );
    $check( 'B2: REST sin sesión → 200 y pago apuntado', 200 === $response->get_status() && Receipts::STATUS_PAID === Receipts::get( $r6 )->status );

    // Baja: paga sin guardar tarjeta ni pedir autorización (C14).
    update_user_meta( $user_id, \ClubTriatlonSulayr\Infrastructure\CustomFields::USER_PROFILE_ATHLETE_STATUS, \ClubTriatlonSulayr\Infrastructure\CustomFields::STATUS_LEFT );
    PaymentCards::erase_for_leave( $user_id );
    $r7 = $new_receipt( $user_id, 7 );
    $start7 = CardPayments::start( $user_id, $r7, false );
    $check( 'B2: un socio de baja paga sin autorización y sin pedir la tarjeta', is_array( $start7 ) && false === isset( RedsysSignature::decode_params( $start7['fields']['Ds_MerchantParameters'] )['DS_MERCHANT_IDENTIFIER'] ) );
    CardPayments::handle_notification( $notify( [ 'Ds_Order' => $start7['transaction']->ds_order, 'Ds_Amount' => '700', 'Ds_Merchant_Identifier' => 'NO-GUARDAR' ] + $base ) );
    $check( 'B2: y no se le guarda la tarjeta aunque Redsys mande referencia', Receipts::STATUS_PAID === Receipts::get( $r7 )->status && null === PaymentCards::active( $user_id ) );
    delete_user_meta( $user_id, \ClubTriatlonSulayr\Infrastructure\CustomFields::USER_PROFILE_ATHLETE_STATUS );

    // Mis recibos.
    PaymentCards::save( $user_id, 'REF-VISTA', 'TXN', '1234', '3001', 0 );
    $r8 = $new_receipt( $user_id, 30 );
    wp_set_current_user( $user_id );
    ob_start();
    ( new ReceiptsAccount() )->render();
    $html = ob_get_clean();
    $check( 'B2: Mis recibos enseña «Mi tarjeta» y «Quitar tarjeta»', false !== strpos( $html, 'Mi tarjeta' ) && false !== strpos( $html, '**** 1234' ) && false !== strpos( $html, 'Quitar tarjeta' ) );
    $check( 'B2: «Pagar 30,00 € con tarjeta» con la autorización y la información', false !== strpos( $html, 'Pagar 30,00' ) && false !== strpos( $html, 'name="authorized"' ) && false !== strpos( $html, 'Protección de datos' ) && false !== strpos( $html, 'día 5' ) );
    $payable = count( array_filter( Receipts::for_user( $user_id ), function ( $receipt ) {
        return Receipts::outstanding( $receipt ) > 0 && false === TpvTransactions::in_flight( (int) $receipt->id );
    } ) );
    $check( 'B2: botón de pago solo en los pendientes sin pago en curso', $payable > 0 && $payable === substr_count( $html, 'sulayr-card-pay-button' ) && false !== strpos( $html, 'Hay un pago con tarjeta de este recibo en curso' ) );
    $check( 'B2: sin el ID del tutor no sale el selector de quién autoriza', false === strpos( $html, 'name="given_by"' ) );
    $_GET = [ 'pago' => 'ok', 'op' => (string) $tx1->id ];
    ob_start();
    ( new ReceiptsAccount() )->render();
    $html = ob_get_clean();
    $check( 'B2: vuelta del banco con la operación autorizada → «Pago … hecho»', false !== strpos( $html, 'Pago de 39,10' ) );
    $_GET = [ 'pago' => 'ok', 'op' => (string) $tx1->id ];
    wp_set_current_user( $other_user );
    ob_start();
    do_action( 'sulayr_receipts_account_top', $other_user );
    $html = ob_get_clean();
    $check( 'B2: otro usuario no ve el estado de una operación ajena', false === strpos( $html, 'Pago de 39,10' ) );
    $_GET = [];
    wp_set_current_user( $user_id );
    CardPayments::use_config( new RedsysConfig( '', '', '' ) );
    ob_start();
    ( new ReceiptsAccount() )->render();
    $html = ob_get_clean();
    $check( 'B2: sin credenciales no hay botón de pago, pero sí la tarjeta guardada', false === strpos( $html, 'sulayr-card-pay-button' ) && false !== strpos( $html, '**** 1234' ) );
    wp_set_current_user( $other_user );

    // Quitar tarjeta con medio «tpv» → aviso al club (C13).
    $mails = [];
    PaymentCards::remove( $user_id );
    $check( 'B2: quitar la tarjeta con medio «Tarjeta (TPV)» avisa al club (C13)', 1 === count( $mails ) && false !== strpos( (string) $mails[0]['subject'], 'tarjeta' ) );
    update_user_meta( $user_id, ChargeMethod::META, ChargeMethod::OTHER );
    PaymentCards::save( $user_id, 'REF-X', 'TXN', '1234', '3001', 0 );
    $mails = [];
    PaymentCards::remove( $user_id );
    $check( 'B2: con «Otro medio» no se avisa', 0 === count( $mails ) );

    // ---------------------------------------------------------------
    // Paso B3: cobro con la tarjeta guardada, sin el socio (§5.2).
    // ---------------------------------------------------------------
    CardPayments::use_config( new RedsysConfig( '999008881', '1', $test_key ) );
    $sent = [];
    $reply = null;
    CardCharges::use_transport( function ( $url, $body ) use ( &$sent, &$reply, $test_key ) {
        $data = json_decode( $body, true );
        $params = RedsysSignature::decode_params( (string) $data['Ds_MerchantParameters'] );
        $sent[] = [ 'url' => $url, 'params' => $params, 'signed' => ( new RedsysSignature( $test_key ) )->verify( (string) $data['Ds_MerchantParameters'], (string) $data['Ds_Signature'] ) ];
        return $reply( $params );
    } );
    // Respuesta firmada de Redsys con un Ds_Response.
    $answer = function ( string $code, array $extra = [] ) use ( $notify ) {
        return function ( array $params ) use ( $notify, $code, $extra ) {
            return [ 'code' => 200, 'body' => (string) wp_json_encode( $notify( $extra + [
                'Ds_Amount' => $params['DS_MERCHANT_AMOUNT'],
                'Ds_Currency' => '978',
                'Ds_Order' => $params['DS_MERCHANT_ORDER'],
                'Ds_MerchantCode' => '999008881',
                'Ds_Terminal' => '1',
                'Ds_Response' => $code,
                'Ds_AuthorisationCode' => '0000' === $code ? '654321' : '',
                'Ds_CardNumber' => '454881******0003',
            ] ) ) ];
        };
    };
    $tx_count = function () use ( $wpdb, $user_id ) {
        return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . TpvTransactions::table() . ' WHERE user_id = %d', $user_id ) );
    };

    $m1 = $new_receipt( $user_id, 33.30 );
    $before = $tx_count();
    $check( 'B3: sin tarjeta guardada no se cobra ni se crea operación', is_wp_error( CardCharges::charge( $m1 ) ) && $before === $tx_count() && 0 === count( $sent ) );
    PaymentCards::save( $user_id, 'REF-CADUCADA', 'TXN', '0003', '2001', 0 );
    $check( 'B3: con la tarjeta caducada no se cobra', 'sulayr_tpv_card_expired' === CardCharges::charge( $m1 )->get_error_code() && 0 === count( $sent ) );
    PaymentCards::save( $user_id, 'REF-MIT', 'TXN-MIT', '454881******0003', '3012', 0 );
    $card_id = (int) PaymentCards::active( $user_id )->id;
    $check( 'B3: tipo de cobro no válido', is_wp_error( CardCharges::charge( $m1, TpvTransactions::TYPE_MANUAL ) ) );

    // Autorizada: cobra lo pendiente (total menos lo ya pagado, C17). Con
    // un pago parcial el total se recalcula de las líneas: el recibo lleva una.
    $wpdb->insert( Receipts::lines_table(), [ 'receipt_id' => $m1, 'user_id' => $user_id, 'type' => 'manual', 'concept' => 'Prueba B3', 'amount' => 33.30, 'created_at' => current_time( 'mysql' ) ] );
    Receipts::add_payment( $m1, 10, current_time( 'Y-m-d' ), $manual_method );
    $reply = $answer( '0000', [ 'Ds_Merchant_Identifier' => 'OTRA-REF' ] );
    $consents_before = count( MemberConsents::rows( $user_id ) );
    $res = CardCharges::charge( $m1 );
    $p = $sent[0]['params'] ?? [];
    $check( 'B3: petición REST a trataPeticionREST de pruebas, firmada', 1 === count( $sent ) && false !== strpos( $sent[0]['url'], 'sis-t.redsys.es:25443/sis/rest/trataPeticionREST' ) && true === $sent[0]['signed'] );
    $check( 'B3: lleva la referencia, el Cof_Txnid, MIT, DIRECTPAYMENT y lo pendiente', '2330' === ( $p['DS_MERCHANT_AMOUNT'] ?? '' ) && 'REF-MIT' === ( $p['DS_MERCHANT_IDENTIFIER'] ?? '' ) && 'TXN-MIT' === ( $p['DS_MERCHANT_COF_TXNID'] ?? '' ) && 'MIT' === ( $p['DS_MERCHANT_EXCEP_SCA'] ?? '' ) && 'true' === ( $p['DS_MERCHANT_DIRECTPAYMENT'] ?? '' ) && 'N' === ( $p['DS_MERCHANT_COF_INI'] ?? '' ) && 'R' === ( $p['DS_MERCHANT_COF_TYPE'] ?? '' ) );
    $check( 'B3: autorizada → recibo pagado', is_array( $res ) && CardPayments::RESULT_AUTHORIZED === $res['result'] && Receipts::STATUS_PAID === Receipts::get( $m1 )->status );
    $tpv_payment = Receipts::payments( $m1 )[1] ?? null;
    $check( 'B3: pago «Tarjeta (TPV)» con autor 0 y el Ds_Order en la nota (§4.4)', null !== $tpv_payment && Receipts::METHOD_TPV === $tpv_payment->method && 0 === (int) $tpv_payment->created_by && $res['transaction']->ds_order === $tpv_payment->note && 23.30 === round( (float) $tpv_payment->amount, 2 ) );
    $check( 'B3: operación «recurrente», intento 1, autorizada y unida al pago', TpvTransactions::TYPE_RECURRING === $res['transaction']->type && 1 === (int) $res['transaction']->attempt && TpvTransactions::STATUS_AUTHORIZED === $res['transaction']->status && '654321' === $res['transaction']->auth_code && (int) $tpv_payment->id === (int) $res['transaction']->payment_id );
    $check( 'B3: no cambia la tarjeta guardada ni pide autorización', $card_id === (int) PaymentCards::active( $user_id )->id && 'REF-MIT' === PaymentCards::active( $user_id )->identifier && count( MemberConsents::rows( $user_id ) ) === $consents_before );
    $check( 'B3: la respuesta guardada no lleva la tarjeta enmascarada', false === strpos( (string) $res['transaction']->response, '454881' ) );
    $sent = [];
    $check( 'B3: un recibo pagado no se vuelve a cobrar', is_wp_error( CardCharges::charge( $m1 ) ) && 0 === count( $sent ) );

    // Denegada.
    $m2 = $new_receipt( $user_id, 18 );
    $reply = $answer( '0190' );
    $res = CardCharges::charge( $m2 );
    $check( 'B3: denegada → sin pago, operación denegada y sin bloquear el recibo', is_array( $res ) && CardPayments::RESULT_DENIED === $res['result'] && TpvTransactions::STATUS_DENIED === $res['transaction']->status && '0190' === $res['transaction']->ds_response && 0.0 === Receipts::paid( $m2 ) && false === TpvTransactions::in_flight( $m2 ) );
    $reply = $answer( '0000' );
    $res = CardCharges::charge( $m2, TpvTransactions::TYPE_RETRY, 2 );
    $check( 'B3: el reintento (intento 2) cobra', is_array( $res ) && CardPayments::RESULT_AUTHORIZED === $res['result'] && TpvTransactions::TYPE_RETRY === $res['transaction']->type && 2 === (int) $res['transaction']->attempt && Receipts::STATUS_PAID === Receipts::get( $m2 )->status );

    // Error SIS: Redsys no hizo el cargo.
    $m3 = $new_receipt( $user_id, 9 );
    $reply = function () {
        return [ 'code' => 200, 'body' => '{"errorCode":"SIS0617","errorCodeDescription":"Error de prueba"}' ];
    };
    $res = CardCharges::charge( $m3 );
    $check( 'B3: error SIS → rechazada, operación en «error» con el código y sin bloquear', is_array( $res ) && CardCharges::RESULT_REJECTED === $res['result'] && TpvTransactions::STATUS_ERROR === $res['transaction']->status && false !== strpos( (string) $res['transaction']->response, 'SIS0617' ) && 'SIS0617' === $res['error'] && false === TpvTransactions::in_flight( $m3 ) );

    // Importe distinto.
    $reply = $answer( '0000', [ 'Ds_Amount' => '1' ] );
    $mails = [];
    $res = CardCharges::charge( $m3 );
    $check( 'B3: autorizada por otro importe → sin pago y aviso al club', is_array( $res ) && CardPayments::RESULT_MISMATCH === $res['result'] && 0.0 === Receipts::paid( $m3 ) && 1 === count( $mails ) );

    // Sin respuesta: no se sabe si se cobró.
    $m4 = $new_receipt( $user_id, 21 );
    $reply = function () {
        return new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
    };
    $mails = [];
    $res = CardCharges::charge( $m4 );
    $check( 'B3: sin respuesta → la operación sigue «enviada» y se avisa al club (§5.2, paso 3)', is_array( $res ) && CardCharges::RESULT_PENDING === $res['result'] && TpvTransactions::STATUS_SENT === $res['transaction']->status && false !== strpos( (string) $res['transaction']->response, 'timed out' ) && 1 === count( $mails ) );
    $check( 'B3: y aparece entre los cobros por resolver', in_array( (int) $res['transaction']->id, array_map( 'intval', wp_list_pluck( TpvTransactions::unresolved_charges(), 'id' ) ), true ) );
    $sent = [];
    $check( 'B3: el recibo queda bloqueado: no se lanza otro cobro', TpvTransactions::in_flight( $m4 ) && is_wp_error( CardCharges::charge( $m4 ) ) && 0 === count( $sent ) );
    $reply = $answer( '0000', [ 'Ds_Order' => '2611Q9999999' ] );
    $m5 = $new_receipt( $user_id, 6.5 );
    $res = CardCharges::charge( $m5 );
    $check( 'B3: respuesta firmada de otro pedido → sin respuesta clara y sin pago', is_array( $res ) && CardCharges::RESULT_PENDING === $res['result'] && 0.0 === Receipts::paid( $m5 ) );
    $reply = function () {
        return [ 'code' => 200, 'body' => '{"errorCode":"SIS0051"}' ];
    };
    $m6 = $new_receipt( $user_id, 4 );
    $res = CardCharges::charge( $m6 );
    $check( 'B3: «pedido repetido» (SIS0051) no se da por no cobrado', is_array( $res ) && CardCharges::RESULT_PENDING === $res['result'] && TpvTransactions::in_flight( $m6 ) );

    // Cierre a mano de un cobro sin respuesta, tras mirarlo en el portal de Redsys.
    $tx6 = TpvTransactions::latest_charge( $m6 );
    $tx4 = TpvTransactions::latest_charge( $m4 );
    ob_start();
    ( new ChargesPanel() )->render_receipt( Receipts::get( $m6 ) );
    $html = (string) ob_get_clean();
    $check( 'Cierre a mano: el detalle del recibo ofrece «Cerrar operación»', false !== strpos( $html, 'Cerrar operación' ) && false !== strpos( $html, (string) $tx6->ds_order ) );
    $check( 'Cierre a mano: sin nota no se cierra', is_wp_error( CardCharges::resolve( (int) $tx6->id, true, ' ' ) ) && TpvTransactions::in_flight( $m6 ) );
    $check( 'Cierre a mano: un pago con el socio delante no se cierra así', is_wp_error( CardCharges::resolve( (int) $tx1->id, true, 'x' ) ) );
    $mails = [];
    $check( 'Cierre a mano «no cobrado» → operación en «error» y recibo desbloqueado', true === CardCharges::resolve( (int) $tx4->id, false, 'No aparece en el portal' ) && TpvTransactions::STATUS_ERROR === TpvTransactions::get( (int) $tx4->id )->status && false === TpvTransactions::in_flight( $m4 ) && 0.0 === Receipts::paid( $m4 ) && 0 === count( $mails ) );
    $check( 'Cierre a mano «cobrado» → pago «Tarjeta (TPV)» del gestor y email al socio', true === CardCharges::resolve( (int) $tx6->id, true, 'Autorizada, código 999999' ) && Receipts::STATUS_PAID === Receipts::get( $m6 )->status && Receipts::METHOD_TPV === Receipts::payments( $m6 )[0]->method && get_current_user_id() === (int) Receipts::payments( $m6 )[0]->created_by && 1 === count( $mails ) && (int) Receipts::payments( $m6 )[0]->id === (int) TpvTransactions::get( (int) $tx6->id )->payment_id );
    $check( 'Cierre a mano: no se cierra dos veces', is_wp_error( CardCharges::resolve( (int) $tx6->id, true, 'otra vez' ) ) && 1 === count( Receipts::payments( $m6 ) ) );
    ob_start();
    ( new ChargesPanel() )->render_receipt( Receipts::get( $m6 ) );
    $html = (string) ob_get_clean();
    $check( 'Cierre a mano: queda a la vista quién la cerró y qué vio', false !== strpos( $html, 'cerrada a mano' ) && false !== strpos( $html, 'código 999999' ) && false === strpos( $html, 'Cerrar operación' ) );

    // Dos ejecuciones a la vez: el bloqueo del recibo lo tiene otra conexión.
    $m7 = $new_receipt( $user_id, 11 );
    $other_db = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
    $other_db->query( "SELECT GET_LOCK('sulayr_tpv_receipt_{$m7}', 0)" );
    $sent = [];
    $res = CardCharges::charge( $m7 );
    $check( 'B3: con el recibo bloqueado por otra ejecución no se cobra', is_wp_error( $res ) && 'sulayr_tpv_in_flight' === $res->get_error_code() && 0 === count( $sent ) );
    $other_db->query( "SELECT RELEASE_LOCK('sulayr_tpv_receipt_{$m7}')" );
    $other_db->close();
    $reply = $answer( '0000' );
    $res = CardCharges::charge( $m7 );
    $check( 'B3: liberado el bloqueo, sí', is_array( $res ) && CardPayments::RESULT_AUTHORIZED === $res['result'] );

    CardPayments::use_config( new RedsysConfig( '', '', '' ) );
    $check( 'B3: sin credenciales no se cobra', is_wp_error( CardCharges::charge( $new_receipt( $user_id, 3 ) ) ) );
} catch ( Throwable $e ) {
    $check( 'Excepción: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(), false );
} finally {
    CardPayments::use_config( null );
    CardCharges::use_transport( null );
    remove_filter( 'pre_wp_mail', $catch_mail, 10 );
    $_GET = [];
    foreach ( $receipt_ids as $id ) {
        $wpdb->delete( Receipts::payments_table(), [ 'receipt_id' => $id ], [ '%d' ] );
        $wpdb->delete( Receipts::lines_table(), [ 'receipt_id' => $id ], [ '%d' ] );
        $wpdb->delete( Receipts::table(), [ 'id' => $id ], [ '%d' ] );
    }
    if ( $user_id > 0 ) {
        $wpdb->delete( TpvTransactions::table(), [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( PaymentCards::table(), [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( MembersSchema::table( MembersSchema::CONSENTS ), [ 'user_id' => $user_id ], [ '%d' ] );
        $wpdb->delete( MembersSchema::table( MembersSchema::LOG ), [ 'user_id' => $user_id ], [ '%d' ] );
        require_once ABSPATH . 'wp-admin/includes/user.php';
        require_once ABSPATH . 'wp-admin/includes/ms.php';
        is_multisite() ? wpmu_delete_user( $user_id ) : wp_delete_user( $user_id );
        $wpdb->delete( MembersSchema::table( MembersSchema::LOG ), [ 'user_id' => $user_id ], [ '%d' ] );
    }
}

echo "\n" . ( 0 === $fails ? 'Todo correcto.' : "{$fails} comprobaciones fallidas." ) . "\n";
