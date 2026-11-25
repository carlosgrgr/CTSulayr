<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Modules\Members\Admin\ReceiptsPage;
use ClubTriatlonSulayr\Modules\Members\Admin\RequestsPage;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pago con tarjeta en Socios → Recibos (SPEC-COBROS §6.1, C16, C22, C28).
 *
 * - En el listado del mes en curso: resumen del cobro, «Aprobar cobro de
 *   <mes>» y, mientras no haya empezado, «Anular la aprobación».
 * - En el detalle de un recibo: sus operaciones con tarjeta y «Cobrar con la
 *   tarjeta guardada».
 *
 * Todo con admin-post.php, nonce y sulayr_manage_members.
 */
class ChargesPanel {

    private const ACTION = 'sulayr_tpv_charges';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'sulayr_receipts_month_panel', [ $this, 'render_month' ] );
        add_action( 'sulayr_receipt_detail_payments', [ $this, 'render_receipt' ] );
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
    }

    /**
     * Acciones: aprobar, anular la aprobación y cobrar un recibo.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        check_admin_referer( self::ACTION );
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para hacer esto.', SULAYR_TEXTDOMAIN ), 403 );
        }

        $do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
        $period = sanitize_text_field( wp_unslash( $_POST['mes'] ?? '' ) );
        $receipt_id = absint( $_POST['receipt_id'] ?? 0 );
        $back = ReceiptsPage::url( $period, $receipt_id );
        $done = '';

        switch ( $do ) {
            case 'aprobar':
                $result = MonthlyCharges::approve( $period, get_current_user_id() );
                $done = MonthlyCharges::charge_date( $period ) <= MonthlyCharges::today()
                    ? sprintf( __( 'Cobro de %s aprobado. Los avisos y el cobro se hacen ahora, en segundo plano: en unos minutos verás el resultado aquí.', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) )
                    : sprintf( __( 'Cobro de %1$s aprobado. Los socios reciben ahora el aviso y se les cobra el %2$s.', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ), date_i18n( 'j \d\e F', strtotime( MonthlyCharges::charge_date( $period ) ) ) );
                break;
            case 'anular':
                $result = MonthlyCharges::unapprove( $period );
                $done = __( 'Aprobación anulada. No se cobrará nada hasta que se vuelva a aprobar.', SULAYR_TEXTDOMAIN );
                break;
            case 'cobrar':
                $result = MonthlyCharges::charge_one( $receipt_id );
                $done = is_array( $result ) ? self::result_text( $result ) : '';
                break;
            case 'resolver':
                $outcome = sanitize_key( wp_unslash( $_POST['outcome'] ?? '' ) );
                $result = in_array( $outcome, [ 'cobrado', 'no_cobrado' ], true )
                    ? CardCharges::resolve( absint( $_POST['transaction_id'] ?? 0 ), 'cobrado' === $outcome, sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) )
                    : new \WP_Error( 'sulayr_tpv_resolve', __( 'Elige si Redsys lo cobró o no.', SULAYR_TEXTDOMAIN ) );
                $done = 'cobrado' === $outcome
                    ? __( 'Operación cerrada como cobrada: pago apuntado en el recibo y email al socio.', SULAYR_TEXTDOMAIN )
                    : __( 'Operación cerrada como no cobrada: el recibo ya se puede volver a cobrar.', SULAYR_TEXTDOMAIN );
                break;
            default:
                $result = new \WP_Error( 'sulayr_action', __( 'Acción desconocida.', SULAYR_TEXTDOMAIN ) );
        }

        if ( is_wp_error( $result ) ) {
            RequestsPage::set_notice( 'error', $result->get_error_message() );
        } else {
            $ok = ! is_array( $result ) || CardPayments::RESULT_AUTHORIZED === $result['result'];
            RequestsPage::set_notice( $ok ? 'success' : 'warning', $done );
        }
        wp_safe_redirect( $back );
        exit;
    }

    /**
     * Panel del cobro con tarjeta del mes en curso (§6.1).
     *
     * @param   string $period AAAA-MM.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_month( $period ): void {
        $period = (string) $period;
        if ( false === CardPayments::is_enabled() || substr( MonthlyCharges::today(), 0, 7 ) !== $period ) {
            return;
        }

        $state = MonthlyCharges::state( $period );
        $charge_date = date_i18n( 'j \d\e F', strtotime( MonthlyCharges::charge_date( $period ) ) );
        echo '<div class="sulayr-tpv-panel card" style="max-width:none">';
        printf( '<h2>%s</h2>', esc_html( sprintf( __( 'Cobro con tarjeta de %s', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ) ) );

        $unresolved = TpvTransactions::unresolved_charges();
        if ( ! empty( $unresolved ) ) {
            printf( '<div class="notice notice-error inline"><p>%s %s</p></div>', esc_html( sprintf( _n( '%d cobro con tarjeta sin respuesta clara de Redsys:', '%d cobros con tarjeta sin respuesta clara de Redsys:', count( $unresolved ), SULAYR_TEXTDOMAIN ), count( $unresolved ) ) ), $this->receipt_links( $unresolved ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
        }

        if ( ! empty( $state['charged_on'] ) ) {
            $this->render_progress( $state );
            echo '</div>';
            return;
        }

        $summary = MonthlyCharges::summary( $period );
        printf(
            '<p>%s</p>',
            esc_html( empty( $summary['receipts'] )
                ? __( 'Ningún recibo entra en el cobro con tarjeta: no hay recibos pendientes de socios con medio «Tarjeta (TPV)» y tarjeta guardada.', SULAYR_TEXTDOMAIN )
                : sprintf( _n( 'Se cobrará %1$d recibo por %2$s el %3$s.', 'Se cobrarán %1$d recibos por %2$s el %3$s.', count( $summary['receipts'] ), SULAYR_TEXTDOMAIN ), count( $summary['receipts'] ), Receipts::money( $summary['amount'] ), $charge_date ) )
        );
        if ( ! empty( $summary['outdated'] ) ) {
            printf( '<div class="notice notice-warning inline"><p>%s %s</p></div>', esc_html__( 'Recibos desfasados (revísalos y regenera los que haga falta antes de aprobar):', SULAYR_TEXTDOMAIN ), $this->receipt_links( $summary['outdated'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
        }
        if ( ! empty( $summary['without_card'] ) ) {
            printf( '<div class="notice notice-warning inline"><p>%s %s</p></div>', esc_html__( 'Con medio «Tarjeta (TPV)» pero sin tarjeta guardada o con la tarjeta caducada (no entran: pagarán desde Mis recibos o a mano):', SULAYR_TEXTDOMAIN ), $this->receipt_links( $summary['without_card'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
        }
        if ( ! empty( $summary['in_flight'] ) ) {
            printf( '<div class="notice notice-info inline"><p>%s %s</p></div>', esc_html__( 'Con un pago con tarjeta en curso (no entran):', SULAYR_TEXTDOMAIN ), $this->receipt_links( $summary['in_flight'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
        }

        if ( empty( $state['approved_at'] ) ) {
            if ( ! empty( $state['unapproved_notice_at'] ) ) {
                echo '<p class="description">' . esc_html__( 'El día de cobro llegó sin aprobar: si lo apruebas ahora, se cobra al momento.', SULAYR_TEXTDOMAIN ) . '</p>';
            }
            if ( ! empty( $summary['receipts'] ) ) {
                $this->button( 'aprobar', sprintf( __( 'Aprobar cobro de %s', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ), [ 'mes' => $period ], 'button-primary sulayr-cta', sprintf( __( '¿Aprobar el cobro? Cada socio recibirá ahora el aviso con su importe y se le cargará en la tarjeta el %s.', SULAYR_TEXTDOMAIN ), $charge_date ) );
            }
        } else {
            $by = get_userdata( (int) $state['approved_by'] );
            printf(
                '<p><strong>%s</strong></p>',
                esc_html( sprintf( __( 'Aprobado por %1$s el %2$s. Se cobra el %3$s.', SULAYR_TEXTDOMAIN ), false !== $by ? $by->display_name : '—', date_i18n( 'd/m/Y H:i', strtotime( (string) $state['approved_at'] ) ), $charge_date ) )
            );
            if ( false === MonthlyCharges::has_started( $period ) ) {
                $this->button( 'anular', __( 'Anular la aprobación', SULAYR_TEXTDOMAIN ), [ 'mes' => $period ], '', __( '¿Anular la aprobación? No se cobrará nada hasta que se vuelva a aprobar. Los socios ya avisados no reciben otro email.', SULAYR_TEXTDOMAIN ) );
            }
        }
        echo '</div>';
    }

    /**
     * Operaciones con tarjeta de un recibo y «Cobrar con la tarjeta guardada» (C22).
     *
     * @param   object $receipt Recibo.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_receipt( $receipt ): void {
        if ( false === is_object( $receipt ) ) {
            return;
        }

        $receipt_id = (int) $receipt->id;
        $operations = array_values( array_filter( TpvTransactions::for_user( (int) $receipt->user_id ), function ( $tx ) use ( $receipt_id ) {
            return (int) $tx->receipt_id === $receipt_id;
        } ) );

        if ( ! empty( $operations ) ) {
            echo '<h2>' . esc_html__( 'Operaciones con tarjeta', SULAYR_TEXTDOMAIN ) . '</h2>';
            echo '<table class="widefat striped sulayr-t-tpv"><thead><tr>';
            foreach ( [ __( 'Fecha', SULAYR_TEXTDOMAIN ), __( 'Pedido', SULAYR_TEXTDOMAIN ), __( 'Tipo', SULAYR_TEXTDOMAIN ), __( 'Importe', SULAYR_TEXTDOMAIN ), __( 'Resultado', SULAYR_TEXTDOMAIN ) ] as $heading ) {
                printf( '<th>%s</th>', esc_html( $heading ) );
            }
            echo '</tr></thead><tbody>';
            foreach ( $operations as $tx ) {
                printf(
                    '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html( date_i18n( 'd/m/Y H:i', strtotime( (string) $tx->created_at ) ) ),
                    esc_html( (string) $tx->ds_order ),
                    esc_html( self::type_label( $tx ) ),
                    esc_html( Receipts::money( (float) $tx->amount ) ),
                    esc_html( self::status_label( $tx ) )
                );
            }
            echo '</tbody></table>';

            foreach ( $operations as $tx ) {
                if ( TpvTransactions::STATUS_SENT === $tx->status && false === CardPayments::is_with_member( (string) $tx->type ) ) {
                    $this->render_resolve_form( $tx, (string) $receipt->period );
                }
            }
        }

        if ( false === CardPayments::is_enabled() || false === Receipts::is_editable( $receipt ) || Receipts::outstanding( $receipt ) <= 0 ) {
            return;
        }
        $card = PaymentCards::active( (int) $receipt->user_id );
        if ( null === $card || '' === trim( (string) $card->identifier ) || PaymentCards::is_expired( $card, MonthlyCharges::today() ) ) {
            echo '<p class="description">' . esc_html__( 'El socio no tiene una tarjeta guardada válida: no se puede cobrar con tarjeta desde aquí.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }
        if ( TpvTransactions::in_flight( $receipt_id ) ) {
            echo '<p class="description">' . esc_html__( 'Hay un cobro con tarjeta en curso o sin respuesta: no se puede lanzar otro.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        echo '<p>';
        $this->button(
            'cobrar',
            sprintf( __( 'Cobrar %1$s con la tarjeta guardada (%2$s)', SULAYR_TEXTDOMAIN ), Receipts::money( Receipts::outstanding( $receipt ) ), PaymentCards::describe( $card ) ),
            [ 'mes' => (string) $receipt->period, 'receipt_id' => $receipt_id ],
            'button-primary sulayr-cta',
            sprintf( __( '¿Cobrar ahora %s en la tarjeta guardada del socio? Recibirá un email con el resultado.', SULAYR_TEXTDOMAIN ), Receipts::money( Receipts::outstanding( $receipt ) ) )
        );
        echo '</p>';
    }

    /**
     * Cierre a mano de un cobro sin respuesta, tras mirarlo en el portal de Redsys.
     *
     * @param   object $tx     Operación «enviada» de un cobro sin el socio.
     * @param   string $period Mes del recibo.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_resolve_form( object $tx, string $period ): void {
        echo '<div class="notice notice-error inline sulayr-tpv-resolve"><p>';
        echo esc_html( sprintf( __( 'El cobro %1$s (%2$s) no tuvo respuesta clara de Redsys. Búscalo en el portal de Redsys por el número de pedido y ciérralo con lo que veas:', SULAYR_TEXTDOMAIN ), $tx->ds_order, Receipts::money( (float) $tx->amount ) ) );
        echo '</p>';
        printf( '<form method="post" action="%s"><p>', esc_url( admin_url( 'admin-post.php' ) ) );
        wp_nonce_field( self::ACTION );
        printf(
            '<input type="hidden" name="action" value="%1$s"><input type="hidden" name="do" value="resolver"><input type="hidden" name="mes" value="%2$s"><input type="hidden" name="receipt_id" value="%3$d"><input type="hidden" name="transaction_id" value="%4$d">',
            esc_attr( self::ACTION ),
            esc_attr( $period ),
            (int) $tx->receipt_id,
            (int) $tx->id
        );
        printf(
            '<select name="outcome" required><option value="">%1$s</option><option value="cobrado">%2$s</option><option value="no_cobrado">%3$s</option></select> <input type="text" name="note" class="regular-text" placeholder="%4$s" required> <button type="submit" class="button" onclick="return confirm(%5$s);">%6$s</button></p></form></div>',
            esc_html__( '¿Qué dice el portal?', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Cobrado (autorizada)', SULAYR_TEXTDOMAIN ),
            esc_html__( 'No cobrado (no aparece, denegada o anulada)', SULAYR_TEXTDOMAIN ),
            esc_attr__( 'Lo que has visto (p. ej. «Autorizada, código 123456»)', SULAYR_TEXTDOMAIN ),
            esc_attr( (string) wp_json_encode( __( '¿Cerrar esta operación? Si la marcas como cobrada se apunta el pago en el recibo y el socio recibe el email.', SULAYR_TEXTDOMAIN ) ) ),
            esc_html__( 'Cerrar operación', SULAYR_TEXTDOMAIN )
        );
    }

    /**
     * Progreso de un mes ya cobrado: cobro y reintentos.
     *
     * @param   array<string, mixed> $state Estado del mes.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_progress( array $state ): void {
        $rows = [ 'batch' => sprintf( __( 'Cobro del %s', SULAYR_TEXTDOMAIN ), date_i18n( 'j \d\e F', strtotime( (string) $state['charged_on'] ) ) ) ];
        foreach ( [ 1, 2 ] as $n ) {
            $rows[ 'retry' . $n ] = sprintf( __( 'Reintento %d', SULAYR_TEXTDOMAIN ), $n );
        }

        echo '<table class="widefat striped" style="max-width:640px"><thead><tr>';
        foreach ( [ '', __( 'Cobrados', SULAYR_TEXTDOMAIN ), __( 'Importe', SULAYR_TEXTDOMAIN ), __( 'Fallidos', SULAYR_TEXTDOMAIN ), __( 'Sin respuesta', SULAYR_TEXTDOMAIN ) ] as $heading ) {
            printf( '<th>%s</th>', esc_html( $heading ) );
        }
        echo '</tr></thead><tbody>';
        foreach ( $rows as $key => $label ) {
            if ( empty( $state[ $key ] ) || false === is_array( $state[ $key ] ) ) {
                continue;
            }
            $batch = $state[ $key ];
            printf( '<tr><th>%s</th><td>%d</td><td>%s</td><td>%d</td><td>%d</td></tr>', esc_html( $label ), (int) $batch['charged'], esc_html( Receipts::money( (float) $batch['amount'] ) ), (int) $batch['failed'], (int) $batch['unknown'] );
        }
        echo '</tbody></table>';
        if ( empty( $state['retry2_at'] ) ) {
            echo '<p class="description">' . esc_html__( 'Los fallidos se reintentan solos en los días configurados. También puedes cobrar un recibo concreto desde su detalle.', SULAYR_TEXTDOMAIN ) . '</p>';
        }
    }

    /**
     * Enlaces a recibos con el nombre del socio.
     *
     * @param   array<int, object> $rows Recibos u operaciones (con receipt_id).
     * @return 	string HTML escapado.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function receipt_links( array $rows ): string {
        $links = [];
        foreach ( $rows as $row ) {
            $receipt = isset( $row->receipt_id ) ? Receipts::get( (int) $row->receipt_id ) : $row;
            if ( null === $receipt ) {
                continue;
            }
            $user_id = (int) $receipt->user_id;
            $links[] = sprintf( '<a href="%s">%s</a>', esc_url( ReceiptsPage::url( (string) $receipt->period, (int) $receipt->id ) ), esc_html( ( HelperService::get_user_full_name( $user_id ) ?: '#' . $user_id ) . ' (' . $receipt->number . ')' ) );
        }

        return implode( ', ', $links );
    }

    /**
     * Texto del resultado de un cobro.
     *
     * @param   array{result: string, transaction: object, error: string} $result Resultado.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function result_text( array $result ): string {
        $amount = Receipts::money( (float) $result['transaction']->amount );
        switch ( $result['result'] ) {
            case CardPayments::RESULT_AUTHORIZED:
                return sprintf( __( 'Cobrado %s con la tarjeta guardada. El socio recibe el email.', SULAYR_TEXTDOMAIN ), $amount );
            case CardPayments::RESULT_DENIED:
                return sprintf( __( 'El banco ha denegado el cargo de %1$s (código %2$s). El socio recibe el email para pagarlo o cambiar la tarjeta.', SULAYR_TEXTDOMAIN ), $amount, (string) $result['transaction']->ds_response );
            case CardCharges::RESULT_REJECTED:
                return sprintf( __( 'Redsys ha rechazado la operación (%s). No se ha cobrado nada.', SULAYR_TEXTDOMAIN ), $result['error'] );
            case CardPayments::RESULT_MISMATCH:
                return __( 'Redsys ha autorizado otro importe: no se ha apuntado nada. El club tiene un email con los detalles.', SULAYR_TEXTDOMAIN );
            default:
                return __( 'Sin respuesta clara de Redsys: no se sabe si se ha cobrado. Revisa la operación en el portal de Redsys. El recibo queda bloqueado para cobros con tarjeta.', SULAYR_TEXTDOMAIN );
        }
    }

    /**
     * Tipo de una operación, con el intento.
     *
     * @param   object $tx Operación.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function type_label( object $tx ): string {
        $labels = [
            TpvTransactions::TYPE_MANUAL => __( 'Pago del socio', SULAYR_TEXTDOMAIN ),
            TpvTransactions::TYPE_RECURRING => __( 'Cobro con la tarjeta guardada', SULAYR_TEXTDOMAIN ),
            TpvTransactions::TYPE_RETRY => sprintf( __( 'Reintento (intento %d)', SULAYR_TEXTDOMAIN ), (int) $tx->attempt ),
            TpvTransactions::TYPE_VALIDATION => __( 'Validación', SULAYR_TEXTDOMAIN ),
            TpvTransactions::TYPE_REGISTER => __( 'Registro', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ (string) $tx->type ] ?? (string) $tx->type;
    }

    /**
     * Estado de una operación.
     *
     * @param   object $tx Operación.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function status_label( object $tx ): string {
        $response = json_decode( (string) $tx->response, true );
        if ( is_array( $response ) && isset( $response['cierre_manual'] ) ) {
            $by = get_userdata( (int) ( $response['por'] ?? 0 ) );
            return sprintf(
                'cobrado' === $response['cierre_manual'] ? __( 'Cobrada, cerrada a mano por %1$s: %2$s', SULAYR_TEXTDOMAIN ) : __( 'No cobrada, cerrada a mano por %1$s: %2$s', SULAYR_TEXTDOMAIN ),
                false !== $by ? $by->display_name : '—',
                (string) ( $response['nota'] ?? '' )
            );
        }

        switch ( (string) $tx->status ) {
            case TpvTransactions::STATUS_AUTHORIZED:
                return sprintf( __( 'Autorizada (%s)', SULAYR_TEXTDOMAIN ), (string) $tx->auth_code );
            case TpvTransactions::STATUS_DENIED:
                return sprintf( __( 'Denegada (%s)', SULAYR_TEXTDOMAIN ), (string) $tx->ds_response );
            case TpvTransactions::STATUS_ERROR:
                $response = json_decode( (string) $tx->response, true );
                return sprintf( __( 'Error (%s)', SULAYR_TEXTDOMAIN ), is_array( $response ) && isset( $response['errorCode'] ) ? (string) $response['errorCode'] : (string) $tx->ds_response );
            default:
                return CardPayments::is_with_member( (string) $tx->type ) ? __( 'Enviada (el socio no ha terminado o no llegó el aviso)', SULAYR_TEXTDOMAIN ) : __( 'Sin respuesta: revisar en el portal de Redsys', SULAYR_TEXTDOMAIN );
        }
    }

    /**
     * Botón de acción con confirmación.
     *
     * @param   string $do      Acción.
     * @param   string $label   Texto.
     * @param   array  $hidden  Campos ocultos.
     * @param   string $class   Clases.
     * @param   string $confirm Pregunta.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function button( string $do, string $label, array $hidden, string $class, string $confirm ): void {
        printf( '<form method="post" action="%s" class="sulayr-inline">', esc_url( admin_url( 'admin-post.php' ) ) );
        wp_nonce_field( self::ACTION );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="do" value="%s">', esc_attr( self::ACTION ), esc_attr( $do ) );
        foreach ( $hidden as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
        }
        printf( '<button type="submit" class="button %s" onclick="return confirm(%s);">%s</button></form>', esc_attr( $class ), esc_attr( (string) wp_json_encode( $confirm ) ), esc_html( $label ) );
    }
}
