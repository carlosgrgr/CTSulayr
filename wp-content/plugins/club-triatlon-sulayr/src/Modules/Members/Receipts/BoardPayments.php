<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Retribución mensual de la junta (D34, D7 de la parte D).
 *
 * Cada mes, a partir del día 1, se apunta en el monedero de cada socio con
 * una retribución mayor que 0 que esté activo el día 1 (congelados y bajas
 * no). El movimiento lleva fecha del día 1 aunque la tarea corra más tarde y
 * la marca del mes, así que se apunta una sola vez. No empieza antes del
 * «Primer mes con recibos» de Socios → Ajustes.
 */
class BoardPayments {

    /**
     * Apunta las retribuciones del mes en curso que falten.
     *
     * @return 	int Movimientos creados.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function run(): int {
        $period = current_time( 'Y-m' );
        $first_month = MemberSettings::first_receipts_month();
        if ( '' === $first_month || $period < $first_month ) {
            return 0;
        }

        $first = $period . '-01';
        $users = get_users( [
            'role' => 'athlete',
            'meta_key' => CustomFields::MEMBER_BOARD_PAYMENT,
            'meta_compare' => 'EXISTS',
            'fields' => 'ID',
            'number' => -1,
        ] );

        $created = 0;
        foreach ( $users as $user_id ) {
            $user_id = (int) $user_id;
            $amount = round( (float) get_user_meta( $user_id, CustomFields::MEMBER_BOARD_PAYMENT, true ), 2 );
            if ( $amount <= 0 ) {
                continue;
            }
            $status = StatusPeriods::on( $user_id, $first );
            if ( null === $status || CustomFields::STATUS_ACTIVE !== $status->status ) {
                continue;
            }
            if ( WalletLedger::exists( $user_id, WalletLedger::META_BOARD_PERIOD, $period ) ) {
                continue;
            }

            $result = WalletLedger::add( $user_id, $amount, MemberSettings::fill( 'wallet_concept_board', [ 'mes' => Receipts::month_label( $period ) ] ), $first, [ WalletLedger::META_BOARD_PERIOD => $period ] );
            if ( false === is_wp_error( $result ) ) {
                MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, __( 'Retribución junta', SULAYR_TEXTDOMAIN ), '', sprintf( '%s · %s', Receipts::month_label( $period ), Receipts::money( $amount ) ) );
                $created++;
            }
        }

        return $created;
    }
}
