<?php
/**
 * Comprobación de la base del pago online con WordPress (SPEC-COBROS, parte B). Solo en local.
 *
 *   wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-pagos.php
 *
 * Crea un socio y un recibo de prueba, comprueba esquema, medio de cobro,
 * tarjetas, operaciones, pago con medio «tpv», consentimiento y ajustes, y lo
 * borra todo al terminar. No llama a Redsys.
 */

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Payments\ChargeMethod;
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
$admin = get_users( [ 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ] );
wp_set_current_user( (int) ( $admin[0] ?? 1 ) );

try {
    // Esquema.
    $check( 'Versión del esquema de socios = 5', 5 === (int) get_option( MembersSchema::VERSION_OPTION ) );
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
    $wpdb->insert( Receipts::table(), [
        'number' => 'PRUEBA-' . $user_id,
        'user_id' => $user_id,
        'period' => current_time( 'Y-m' ),
        'total' => 45,
        'status' => Receipts::STATUS_PENDING,
        'generated_by' => get_current_user_id(),
        'generated_at' => current_time( 'mysql' ),
    ] );
    $receipt_id = (int) $wpdb->insert_id;

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
} catch ( Throwable $e ) {
    $check( 'Excepción: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine(), false );
} finally {
    if ( $receipt_id > 0 ) {
        $wpdb->delete( Receipts::payments_table(), [ 'receipt_id' => $receipt_id ], [ '%d' ] );
        $wpdb->delete( Receipts::lines_table(), [ 'receipt_id' => $receipt_id ], [ '%d' ] );
        $wpdb->delete( Receipts::table(), [ 'id' => $receipt_id ], [ '%d' ] );
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
