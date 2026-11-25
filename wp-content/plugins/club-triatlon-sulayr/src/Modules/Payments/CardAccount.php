<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\Lifecycle\FeesAccount;
use ClubTriatlonSulayr\Modules\Members\MemberConsents;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptsAccount;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pago con tarjeta en Mi cuenta → Mis recibos (SPEC-COBROS §7).
 *
 * - «Pagar con tarjeta» en cada recibo pendiente o parcial (C21), con la
 *   autorización de cargos recurrentes (C12) y la información de protección
 *   de datos al lado.
 * - Bloque «Mi tarjeta»: la tarjeta guardada y «Quitar tarjeta» (C13).
 * - Avisos al volver del banco. La vuelta no apunta nada (§5.1, paso 4).
 *
 * Sin credenciales de Redsys en wp-config.php no se enseña nada de pago,
 * salvo la tarjeta si el socio ya tenía una.
 *
 * «Guardar tarjeta» sin pagar nada (validación sin importe, C11) espera a que
 * el técnico de Redsys confirme que se puede. Mientras, la tarjeta se guarda
 * o se cambia pagando un recibo.
 */
class CardAccount {

    private const PAY_ACTION = 'sulayr_card_pay';
    private const REMOVE_ACTION = 'sulayr_card_remove';
    private const NOTICE_TRANSIENT = 'sulayr_card_notice_';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'template_redirect', [ $this, 'handle' ], 6 );
        add_action( 'sulayr_receipts_account_top', [ $this, 'render_top' ] );
        add_action( 'sulayr_receipt_account_actions', [ $this, 'render_pay' ], 10, 2 );
    }

    /**
     * Formularios de pagar y de quitar la tarjeta.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || false === is_user_logged_in() ) {
            return;
        }

        if ( ! empty( $_POST[ self::PAY_ACTION ] ) ) {
            $receipt_id = absint( $_POST['receipt_id'] ?? 0 );
            check_admin_referer( self::PAY_ACTION . '_' . $receipt_id );
            if ( false === FeesAccount::is_athlete() ) {
                return;
            }

            $given_by = MemberConsents::BY_GUARDIAN === ( $_POST['given_by'] ?? '' ) ? MemberConsents::BY_GUARDIAN : MemberConsents::BY_MEMBER;
            $result = CardPayments::start( get_current_user_id(), $receipt_id, ! empty( $_POST['authorized'] ), $given_by );
            if ( is_wp_error( $result ) ) {
                $this->back( [ 'error', $result->get_error_message() ] );
            }

            $this->redirect_to_bank( $result['url'], $result['fields'] );
        }

        if ( ! empty( $_POST[ self::REMOVE_ACTION ] ) ) {
            check_admin_referer( self::REMOVE_ACTION );
            if ( false === FeesAccount::is_athlete() ) {
                return;
            }

            $user_id = get_current_user_id();
            if ( PaymentCards::remove( $user_id ) ) {
                MemberConsents::record( MemberConsents::RECURRING_CHARGES, false, MemberConsents::BY_MEMBER, $user_id );
                $this->back( [ 'success', __( 'Has quitado tu tarjeta.', SULAYR_TEXTDOMAIN ) ] );
            }
            $this->back( [ 'error', __( 'No tenías ninguna tarjeta guardada.', SULAYR_TEXTDOMAIN ) ] );
        }
    }

    /**
     * Avisos y bloque «Mi tarjeta», encima de los recibos.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_top( $user_id ): void {
        $user_id = (int) $user_id;
        $this->print_notices( $user_id );

        $card = PaymentCards::active( $user_id );
        if ( false === CardPayments::is_enabled() && null === $card ) {
            return;
        }

        echo '<div class="sulayr-info-block sulayr-card-block"><h3 class="sulayr-info-block-title">' . esc_html__( 'Mi tarjeta', SULAYR_TEXTDOMAIN ) . '</h3>';

        if ( null !== $card ) {
            printf(
                '<p class="sulayr-card-current">%s</p>',
                esc_html( sprintf( __( '%1$s, guardada el %2$s.', SULAYR_TEXTDOMAIN ), PaymentCards::describe( $card ), MemberDates::show( substr( (string) $card->created_at, 0, 10 ) ) ) )
            );
            if ( PaymentCards::is_expired( $card, current_time( 'Y-m-d' ) ) ) {
                echo '<div class="sulayr-alert sulayr-alert-warning" role="status">' . esc_html__( 'Tu tarjeta ha caducado. Paga tu próximo recibo con una tarjeta nueva para guardarla.', SULAYR_TEXTDOMAIN ) . '</div>';
            }
        } elseif ( false === CardPayments::is_left( $user_id ) ) {
            echo '<p>' . esc_html__( 'No tienes ninguna tarjeta guardada. Se guarda cuando pagas un recibo con tarjeta.', SULAYR_TEXTDOMAIN ) . '</p>';
        }

        if ( ChargeMethod::is_tpv( $user_id ) && false === CardPayments::is_left( $user_id ) ) {
            printf( '<p class="sulayr-fees-note">%s</p>', esc_html( sprintf( __( 'Tus cuotas se cobran con tu tarjeta guardada a partir del día %d de cada mes, después de avisarte por email.', SULAYR_TEXTDOMAIN ), (int) MemberSettings::get( 'tpv_charge_day' ) ) ) );
            if ( null === $card ) {
                echo '<div class="sulayr-alert sulayr-alert-warning" role="status">' . esc_html__( 'Sin tarjeta guardada no podemos cobrarte las cuotas. Paga tu recibo pendiente con tarjeta para guardarla.', SULAYR_TEXTDOMAIN ) . '</div>';
            }
        }

        if ( null !== $card ) {
            echo '<p class="sulayr-fees-note">' . esc_html( $this->change_card_text( $user_id ) ) . '</p>';
            printf(
                '<form method="post" onsubmit="return confirm(%s);">',
                esc_attr( wp_json_encode( __( '¿Quitar tu tarjeta? Dejarás de pagar las cuotas automáticamente.', SULAYR_TEXTDOMAIN ) ) )
            );
            wp_nonce_field( self::REMOVE_ACTION );
            printf( '<input type="hidden" name="%s" value="1"><p><button type="submit" class="button">%s</button></p></form>', esc_attr( self::REMOVE_ACTION ), esc_html__( 'Quitar tarjeta', SULAYR_TEXTDOMAIN ) );
        }

        echo '</div>';
    }

    /**
     * Cómo usar otra tarjeta (C13): solo se guarda una nueva al pagar con ella
     * un recibo pendiente, así que el texto depende de si hay uno que se pueda
     * pagar ahora y de si las cuotas se cobran solas con la tarjeta guardada.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function change_card_text( int $user_id ): string {
        if ( CardPayments::is_enabled() ) {
            foreach ( Receipts::for_user( $user_id ) as $receipt ) {
                if ( Receipts::outstanding( $receipt ) > 0 && Receipts::is_editable( $receipt ) && false === TpvTransactions::in_flight( (int) $receipt->id ) ) {
                    return __( '¿Quieres usar otra tarjeta? Paga tu recibo pendiente con el botón «Pagar con tarjeta» de más abajo y escribe en el banco los datos de la tarjeta nueva. Se guardará en lugar de esta.', SULAYR_TEXTDOMAIN );
                }
            }
        }

        if ( ChargeMethod::is_tpv( $user_id ) && false === CardPayments::is_left( $user_id ) ) {
            return sprintf(
                __( '¿Quieres usar otra tarjeta? Ahora no tienes recibos pendientes. Cuando te llegue el email de aviso del próximo recibo, entra aquí antes del día %d y págalo con la tarjeta nueva: se guardará en lugar de esta. Si no lo haces, el recibo se cobrará en esta tarjeta.', SULAYR_TEXTDOMAIN ),
                (int) MemberSettings::get( 'tpv_charge_day' )
            );
        }

        return __( '¿Quieres usar otra tarjeta? Cuando tengas un recibo pendiente, págalo aquí con la tarjeta nueva: se guardará en lugar de esta.', SULAYR_TEXTDOMAIN );
    }

    /**
     * «Pagar con tarjeta» dentro de un recibo pendiente o parcial.
     *
     * @param   object $receipt     Recibo.
     * @param   float  $outstanding Pendiente.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_pay( $receipt, $outstanding ): void {
        if ( false === CardPayments::is_enabled() || false === is_object( $receipt ) || (float) $outstanding <= 0 || false === Receipts::is_editable( $receipt ) ) {
            return;
        }

        $receipt_id = (int) $receipt->id;
        if ( TpvTransactions::in_flight( $receipt_id ) ) {
            echo '<p class="sulayr-fees-note">' . esc_html__( 'Hay un pago con tarjeta de este recibo en curso. Si acabas de pagar, en unos minutos aparecerá aquí.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $user_id = (int) $receipt->user_id;
        $save_card = false === CardPayments::is_left( $user_id );

        echo '<form method="post" class="sulayr-card-pay">';
        wp_nonce_field( self::PAY_ACTION . '_' . $receipt_id );
        printf( '<input type="hidden" name="%s" value="1"><input type="hidden" name="receipt_id" value="%d">', esc_attr( self::PAY_ACTION ), $receipt_id );

        if ( $save_card ) {
            $field_id = 'sulayr-card-authorized-' . $receipt_id;
            printf(
                '<div class="form-check"><input class="form-check-input" type="checkbox" id="%1$s" name="authorized" value="1" required> <label class="form-check-label" for="%1$s">%2$s</label></div>',
                esc_attr( $field_id ),
                esc_html( CardPayments::authorization_text() )
            );
            $guardian = CardPayments::guardian_name( $user_id );
            if ( '' !== $guardian ) {
                printf(
                    '<p><label for="sulayr-card-by-%1$d">%2$s</label> <select class="form-select" id="sulayr-card-by-%1$d" name="given_by"><option value="%3$s">%4$s</option><option value="%5$s">%6$s</option></select></p>',
                    $receipt_id,
                    esc_html__( 'Lo autoriza:', SULAYR_TEXTDOMAIN ),
                    esc_attr( MemberConsents::BY_MEMBER ),
                    esc_html__( 'El socio o la socia', SULAYR_TEXTDOMAIN ),
                    esc_attr( MemberConsents::BY_GUARDIAN ),
                    esc_html( sprintf( __( 'El tutor (%s)', SULAYR_TEXTDOMAIN ), $guardian ) )
                );
            }
            printf( '<div class="sulayr-card-privacy">%s</div>', wp_kses_post( wpautop( (string) MemberSettings::get( 'tpv_privacy_notice' ) ) ) );
        }

        printf(
            '<p><button type="submit" class="button sulayr-card-pay-button">%s</button></p></form>',
            esc_html( sprintf( __( 'Pagar %s con tarjeta', SULAYR_TEXTDOMAIN ), Receipts::money( (float) $outstanding ) ) )
        );
    }

    /**
     * Avisos: error al empezar el pago, quitar tarjeta y vuelta del banco.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function print_notices( int $user_id ): void {
        $notice = get_transient( self::NOTICE_TRANSIENT . $user_id );
        if ( is_array( $notice ) && 2 === count( $notice ) ) {
            delete_transient( self::NOTICE_TRANSIENT . $user_id );
            $this->alert( (string) $notice[0], (string) $notice[1] );
        }

        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- Solo se lee el estado de una operación propia.
        $back = sanitize_key( $_GET['pago'] ?? '' );
        $transaction = ! empty( $_GET['op'] ) ? TpvTransactions::get( absint( $_GET['op'] ) ) : null;
        // phpcs:enable
        if ( '' === $back || null === $transaction || (int) $transaction->user_id !== $user_id ) {
            return;
        }

        if ( TpvTransactions::STATUS_AUTHORIZED === $transaction->status ) {
            $this->alert( 'success', sprintf( __( 'Pago de %s hecho. ¡Gracias!', SULAYR_TEXTDOMAIN ), Receipts::money( (float) $transaction->amount ) ) );
        } elseif ( TpvTransactions::STATUS_SENT === $transaction->status && 'ok' === $back ) {
            $this->alert( 'warning', __( 'Estamos esperando la confirmación del banco. En unos minutos el pago aparecerá en tu recibo.', SULAYR_TEXTDOMAIN ) );
        } else {
            $this->alert( 'error', __( 'El pago no se ha completado y no se ha cobrado nada. Puedes intentarlo de nuevo.', SULAYR_TEXTDOMAIN ) );
        }
    }

    /**
     * Un aviso.
     *
     * @param   string $type success, warning o error.
     * @param   string $text Texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function alert( string $type, string $text ): void {
        $class = in_array( $type, [ 'success', 'warning', 'error' ], true ) ? $type : 'warning';
        printf( '<div class="sulayr-alert sulayr-alert-%1$s" role="%2$s">%3$s</div>', esc_attr( $class ), 'error' === $class ? 'alert' : 'status', esc_html( $text ) );
    }

    /**
     * Página intermedia que envía el formulario firmado a Redsys.
     *
     * @param   string                $url    URL de pago de Redsys.
     * @param   array<string, string> $fields Campos firmados.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function redirect_to_bank( string $url, array $fields ): void {
        nocache_headers();
        header( 'Content-Type: text/html; charset=UTF-8' );

        echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">';
        echo '<title>' . esc_html__( 'Pago con tarjeta', SULAYR_TEXTDOMAIN ) . '</title>';
        echo '<style>body{font-family:Arial,sans-serif;background:#f6f7f7;color:#1d2327;display:flex;min-height:100vh;align-items:center;justify-content:center;margin:0;padding:16px;text-align:center}button{font-size:16px;padding:10px 20px;cursor:pointer}</style></head>';
        echo '<body><form id="sulayr-redsys" method="post" action="' . esc_url( $url ) . '">';
        foreach ( $fields as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
        }
        echo '<p>' . esc_html__( 'Te llevamos a la página segura del banco para pagar con tarjeta…', SULAYR_TEXTDOMAIN ) . '</p>';
        echo '<noscript><button type="submit">' . esc_html__( 'Continuar al pago', SULAYR_TEXTDOMAIN ) . '</button></noscript>';
        echo '</form><script>document.getElementById("sulayr-redsys").submit();</script></body></html>';
        exit;
    }

    /**
     * Vuelve a Mis recibos con un aviso.
     *
     * @param   array{0: string, 1: string} $notice Tipo y texto.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function back( array $notice ): void {
        set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );
        wp_safe_redirect( ReceiptsAccount::url() );
        exit;
    }
}
