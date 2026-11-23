<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Join\MemberMailer;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Emails al socio que cobra con tarjeta (SPEC-COBROS C23): aviso previo,
 * cobro realizado, cobro fallido y tarjeta a punto de caducar. Textos en
 * Socios → Ajustes → Emails. Si es menor, también al tutor (D6).
 */
class ChargeNotices {

    /**
     * Aviso previo al aprobar el cobro: importe, desglose y día de cargo.
     *
     * @param   object $receipt   Recibo.
     * @param   string $charge_on Fecha del cargo (Y-m-d).
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function notice( object $receipt, string $charge_on ): bool {
        $items = [];
        foreach ( Receipts::lines( (int) $receipt->id ) as $line ) {
            if ( empty( $line->cancelled ) ) {
                $items[] = '<li>' . esc_html( (string) $line->concept ) . ': ' . esc_html( Receipts::money( (float) $line->amount ) ) . '</li>';
            }
        }
        $paid = Receipts::paid( (int) $receipt->id );
        if ( $paid > 0 ) {
            $items[] = '<li>' . esc_html( sprintf( __( 'Ya pagado: %s', SULAYR_TEXTDOMAIN ), Receipts::money( -$paid ) ) ) . '</li>';
        }

        return self::send( 'tpv_notice', $receipt, Receipts::outstanding( $receipt ), [
            'dia_cargo' => date_i18n( 'j \d\e F', strtotime( $charge_on ) ),
            'desglose_html' => empty( $items ) ? '' : '<ul>' . implode( '', $items ) . '</ul>',
        ] );
    }

    /**
     * Cobro realizado.
     *
     * @param   object $receipt Recibo.
     * @param   float  $amount  Importe cobrado.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function charged( object $receipt, float $amount ): bool {
        return self::send( 'tpv_charged', $receipt, $amount );
    }

    /**
     * Cobro fallido, con el próximo intento o sin él.
     *
     * @param   object $receipt Recibo.
     * @param   float  $amount  Importe que no se pudo cobrar.
     * @param   string $next_on Fecha del próximo intento (Y-m-d), o '' si no habrá más.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function failed( object $receipt, float $amount, string $next_on ): bool {
        return self::send( 'tpv_failed', $receipt, $amount, [
            'siguiente_intento' => '' !== $next_on
                ? sprintf( __( 'Lo volveremos a intentar el %s.', SULAYR_TEXTDOMAIN ), date_i18n( 'j \d\e F', strtotime( $next_on ) ) )
                : __( 'No haremos más intentos automáticos este mes.', SULAYR_TEXTDOMAIN ),
        ] );
    }

    /**
     * La tarjeta guardada caduca pronto.
     *
     * @param   object $card Tarjeta.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function expiring( object $card ): bool {
        $user_id = (int) $card->user_id;
        $last = PaymentCards::last_valid_day( $card );

        return self::mail( 'tpv_expiring', $user_id, [
            'tarjeta' => (string) $card->masked,
            'fecha_caducidad' => '' !== $last ? date_i18n( 'j \d\e F \d\e Y', strtotime( $last ) ) : '',
        ] );
    }

    /**
     * Email de un recibo con sus marcadores comunes.
     *
     * @param   string $key     Email (sin «email_»).
     * @param   object $receipt Recibo.
     * @param   float  $amount  Importe.
     * @param   array  $extra   Más marcadores.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function send( string $key, object $receipt, float $amount, array $extra = [] ): bool {
        $user_id = (int) $receipt->user_id;
        $card = PaymentCards::active( $user_id );

        return self::mail( $key, $user_id, $extra + [
            'mes' => Receipts::month_label( (string) $receipt->period ),
            'numero' => (string) $receipt->number,
            'importe' => Receipts::money( $amount ),
            'tarjeta' => null !== $card ? (string) $card->masked : '',
        ] );
    }

    /**
     * Envía al socio y, si lo tiene, al tutor.
     *
     * @param   string $key          Email.
     * @param   int    $user_id      Socio.
     * @param   array  $placeholders Marcadores.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function mail( string $key, int $user_id, array $placeholders ): bool {
        $user = get_userdata( $user_id );
        if ( false === $user ) {
            return false;
        }

        return MemberMailer::send( $key, array_filter( [ $user->user_email, (string) get_user_meta( $user_id, CustomFields::MEMBER_GUARDIAN . '_email', true ) ] ), $placeholders + [
            'nombre' => (string) get_user_meta( $user_id, 'first_name', true ),
            'enlace_mis_recibos_html' => MemberMailer::button( ReceiptsAccount::url(), __( 'Ver mis recibos', SULAYR_TEXTDOMAIN ) ),
        ], true );
    }
}
