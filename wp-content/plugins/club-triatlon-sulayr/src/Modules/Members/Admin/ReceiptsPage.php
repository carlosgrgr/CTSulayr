<?php
namespace ClubTriatlonSulayr\Modules\Members\Admin;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberRoles;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptCalculator;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptExport;
use ClubTriatlonSulayr\Modules\Members\Receipts\ReceiptPdf;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Modules\Members\Receipts\WalletLedger;
use ClubTriatlonSulayr\Modules\Members\Subsidies;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Socios → Recibos» (§8) y el bloque de recibos de la ficha del socio
 * (recibos, correcciones pendientes, subvenciones y retribución de la junta).
 *
 * Todo con admin-post.php, nonce y sulayr_manage_members.
 */
class ReceiptsPage {

    public const SLUG = 'sulayr-socios-recibos';

    private const ACTION = 'sulayr_receipts';
    private const ACTION_PDF = 'sulayr_receipt_pdf';
    private const ACTION_EXPORT = 'sulayr_receipts_export';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register' ], 10 );
        add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
        add_action( 'admin_post_' . self::ACTION_PDF, [ $this, 'handle_pdf' ] );
        add_action( 'admin_post_' . self::ACTION_EXPORT, [ $this, 'handle_export' ] );
    }

    /**
     * Submenú.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function register(): void {
        add_submenu_page( MembersMenu::SLUG, __( 'Recibos', SULAYR_TEXTDOMAIN ), __( 'Recibos', SULAYR_TEXTDOMAIN ), MemberRoles::MANAGE_MEMBERS, self::SLUG, [ $this, 'render' ] );
    }

    /**
     * URL del listado de un mes o del detalle de un recibo.
     *
     * @param   string $period AAAA-MM ('' = mes en curso).
     * @param   int    $id     Recibo (0 = listado).
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function url( string $period = '', int $id = 0 ): string {
        $args = [ 'page' => self::SLUG ];
        if ( '' !== $period ) {
            $args['mes'] = $period;
        }
        if ( $id > 0 ) {
            $args['recibo'] = $id;
        }

        return add_query_arg( $args, admin_url( 'admin.php' ) );
    }

    /**
     * URL del PDF en wp-admin.
     *
     * @param   int $id Recibo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pdf_url( int $id ): string {
        return wp_nonce_url( add_query_arg( [ 'action' => self::ACTION_PDF, 'id' => $id ], admin_url( 'admin-post.php' ) ), self::ACTION_PDF . '_' . $id );
    }

    /**
     * «Ver recibo» en wp-admin (D51).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_pdf(): void {
        $id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
        check_admin_referer( self::ACTION_PDF . '_' . $id );
        $this->require_cap();

        $receipt = Receipts::get( $id );
        if ( null === $receipt ) {
            wp_die( esc_html__( 'Recibo no encontrado.', SULAYR_TEXTDOMAIN ), 404 );
        }
        ReceiptPdf::stream( $receipt );
    }

    /**
     * «Exportar a Excel» (8.7).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle_export(): void {
        check_admin_referer( self::ACTION_EXPORT );
        $this->require_cap();

        $period = $this->period( (string) wp_unslash( $_GET['mes'] ?? '' ) );
        ReceiptExport::stream( $period );
    }

    /**
     * Acciones de los formularios.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function handle(): void {
        check_admin_referer( self::ACTION );
        $this->require_cap();

        $do = sanitize_key( wp_unslash( $_POST['do'] ?? '' ) );
        $id = isset( $_POST['receipt_id'] ) ? absint( $_POST['receipt_id'] ) : 0;
        $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
        $period = $this->period( (string) wp_unslash( $_POST['mes'] ?? '' ) );
        $reason = sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) );
        $back = '' !== (string) ( $_POST['back'] ?? '' ) ? esc_url_raw( wp_unslash( $_POST['back'] ) ) : self::url( $period, $id );
        $done = '';

        switch ( $do ) {
            case 'generar':
                $result = Receipts::generate_month( $period );
                $done = is_wp_error( $result ) ? '' : sprintf( _n( 'Generado %1$d recibo de %2$s.', 'Generados %1$d recibos de %2$s.', (int) $result, SULAYR_TEXTDOMAIN ), (int) $result, Receipts::month_label( $period ) );
                break;
            case 'penalizar':
                $result = Receipts::apply_penalties( $period );
                $done = is_wp_error( $result ) ? '' : sprintf( _n( 'Penalización aplicada a %d recibo.', 'Penalización aplicada a %d recibos.', (int) $result, SULAYR_TEXTDOMAIN ), (int) $result );
                break;
            case 'regenerar':
                $result = Receipts::regenerate( $id );
                $done = __( 'Recibo regenerado.', SULAYR_TEXTDOMAIN );
                break;
            case 'corregir':
                $result = Receipts::create_correction( $id );
                $done = __( 'Corrección creada.', SULAYR_TEXTDOMAIN );
                break;
            case 'linea':
                $result = Receipts::add_manual_line( $id, sanitize_text_field( wp_unslash( $_POST['concept'] ?? '' ) ), $this->amount( 'amount' ) );
                $done = __( 'Línea añadida.', SULAYR_TEXTDOMAIN );
                break;
            case 'borrar_linea':
                $result = Receipts::delete_line( absint( $_POST['line_id'] ?? 0 ) );
                $done = __( 'Línea borrada.', SULAYR_TEXTDOMAIN );
                break;
            case 'anular':
                $result = Receipts::annul( $id, $reason );
                $done = __( 'Recibo anulado.', SULAYR_TEXTDOMAIN );
                break;
            case 'pago':
                $result = Receipts::add_payment( $id, $this->amount( 'amount' ), sanitize_text_field( wp_unslash( $_POST['paid_on'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['method'] ?? '' ) ), sanitize_text_field( wp_unslash( $_POST['note'] ?? '' ) ) );
                $done = __( 'Pago apuntado.', SULAYR_TEXTDOMAIN );
                break;
            case 'borrar_pago':
                $result = Receipts::delete_payment( absint( $_POST['payment_id'] ?? 0 ), $reason );
                $done = __( 'Pago borrado.', SULAYR_TEXTDOMAIN );
                break;
            case 'correccion_monedero':
                $result = $this->own_line( absint( $_POST['line_id'] ?? 0 ), $user_id ) ? Receipts::correction_to_wallet( absint( $_POST['line_id'] ?? 0 ) ) : $this->not_found();
                $done = __( 'Corrección pasada al monedero.', SULAYR_TEXTDOMAIN );
                break;
            case 'correccion_descartar':
                $result = $this->own_line( absint( $_POST['line_id'] ?? 0 ), $user_id ) ? Receipts::discard_correction( absint( $_POST['line_id'] ?? 0 ), $reason ) : $this->not_found();
                $done = __( 'Corrección descartada.', SULAYR_TEXTDOMAIN );
                break;
            case 'subvencion':
                $result = $this->create_subsidy( $user_id );
                $done = __( 'Subvención guardada.', SULAYR_TEXTDOMAIN );
                break;
            case 'subvencion_fin':
                $subsidy = Subsidies::get( absint( $_POST['subsidy_id'] ?? 0 ) );
                $end = $this->month_end( (string) wp_unslash( $_POST['end_month'] ?? '' ) );
                $result = null === $subsidy || (int) $subsidy->user_id !== $user_id ? $this->not_found() : ( '' === $end ? new \WP_Error( 'sulayr_date', __( 'Elige el último mes.', SULAYR_TEXTDOMAIN ) ) : Subsidies::end( (int) $subsidy->id, $end ) );
                $done = __( 'Subvención terminada.', SULAYR_TEXTDOMAIN );
                break;
            case 'retribucion':
                $result = $this->save_board_payment( $user_id );
                $done = __( 'Retribución guardada.', SULAYR_TEXTDOMAIN );
                break;
            default:
                $result = new \WP_Error( 'sulayr_action', __( 'Acción desconocida.', SULAYR_TEXTDOMAIN ) );
        }

        if ( is_wp_error( $result ) ) {
            RequestsPage::set_notice( 'error', $result->get_error_message() );
        } else {
            RequestsPage::set_notice( 'success', $done );
        }
        wp_safe_redirect( $back );
        exit;
    }

    /**
     * Página: listado de un mes o detalle de un recibo.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render(): void {
        $this->require_cap();

        echo '<div class="wrap sulayr-members sulayr-receipts sulayr-brand">';
        RequestsPage::print_notice();
        $id = isset( $_GET['recibo'] ) ? absint( $_GET['recibo'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lectura.
        if ( $id > 0 ) {
            $this->render_detail( $id );
        } else {
            $this->render_month( $this->period( (string) wp_unslash( $_GET['mes'] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lectura.
        }
        echo '</div>';
    }

    /**
     * Listado de un mes con generar, penalizar y exportar.
     *
     * @param   string $period AAAA-MM.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_month( string $period ): void {
        $allowed = Receipts::allowed_months();
        echo '<p class="sulayr-eyebrow">' . esc_html__( 'Socios · Recibos', SULAYR_TEXTDOMAIN ) . '</p><h1>' . esc_html( sprintf( __( 'Recibos de %s', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ) ) . '</h1>';

        // Selector de mes.
        $months = array_unique( array_merge( $allowed, [ $period ] ) );
        rsort( $months );
        printf( '<form method="get" class="sulayr-inline"><input type="hidden" name="page" value="%s"><select name="mes">', esc_attr( self::SLUG ) );
        foreach ( $months as $month ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $month ), selected( $month, $period, false ), esc_html( ucfirst( Receipts::month_label( $month ) ) ) );
        }
        printf( '</select> <button class="button">%s</button></form>', esc_html__( 'Ver', SULAYR_TEXTDOMAIN ) );

        if ( empty( $allowed ) ) {
            printf( '<div class="notice notice-warning inline"><p>%s</p></div>', esc_html__( 'Configura «Primer mes con recibos» en Socios → Ajustes → Recibos para empezar a generar recibos.', SULAYR_TEXTDOMAIN ) );
        }

        $generation = Receipts::generation( $period );
        if ( null !== $generation ) {
            $by = (int) $generation['by'] > 0 ? get_userdata( (int) $generation['by'] ) : false;
            printf( '<p class="description">%s</p>', esc_html( sprintf( __( 'Generado el %1$s por %2$s.', SULAYR_TEXTDOMAIN ), date_i18n( 'd/m/Y H:i', strtotime( (string) $generation['at'] ) ), false !== $by ? $by->display_name : __( 'la tarea diaria', SULAYR_TEXTDOMAIN ) ) ) );
        }

        $receipts = Receipts::for_period( $period );
        $first = $period . '-01';
        $missing = count( array_diff( ReceiptCalculator::active_members( $first, ( new \DateTimeImmutable( $first ) )->format( 'Y-m-t' ) ), array_map( function ( $r ) {
            return (int) $r->user_id;
        }, $receipts ) ) );

        echo '<p>';
        if ( in_array( $period, $allowed, true ) && ( null === $generation || $missing > 0 ) ) {
            $label = null === $generation ? sprintf( __( 'Generar recibos de %s', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ) : sprintf( _n( 'Generar el recibo que falta (%d)', 'Generar los recibos que faltan (%d)', $missing, SULAYR_TEXTDOMAIN ), $missing );
            $this->button( 'generar', $label, [ 'mes' => $period ], 'button-primary sulayr-cta', __( '¿Generar los recibos de este mes?', SULAYR_TEXTDOMAIN ) );
            echo ' ';
        }
        if ( ! empty( $receipts ) ) {
            if ( current_time( 'Y-m-d' ) >= Receipts::limit_date( $period ) ) {
                $this->button( 'penalizar', sprintf( __( 'Aplicar penalización a impagados (%s)', SULAYR_TEXTDOMAIN ), Receipts::money( (float) MemberSettings::get( 'penalty_amount' ) ) ), [ 'mes' => $period ], '', __( '¿Añadir la penalización a los recibos pendientes de este mes?', SULAYR_TEXTDOMAIN ) );
                echo ' ';
            }
            printf( '<a class="button" href="%s">%s</a>', esc_url( wp_nonce_url( add_query_arg( [ 'action' => self::ACTION_EXPORT, 'mes' => $period ], admin_url( 'admin-post.php' ) ), self::ACTION_EXPORT ) ), esc_html__( 'Exportar a Excel (CSV)', SULAYR_TEXTDOMAIN ) );
        }
        echo '</p>';

        if ( empty( $receipts ) ) {
            echo '<p>' . esc_html__( 'No hay recibos de este mes.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $sum = [ 'total' => 0.0, 'paid' => 0.0, 'pending' => 0.0 ];
        echo '<table class="wp-list-table widefat fixed striped sulayr-t-month"><thead><tr>';
        foreach ( [ __( 'Recibo', SULAYR_TEXTDOMAIN ), __( 'Nº', SULAYR_TEXTDOMAIN ), __( 'Socio', SULAYR_TEXTDOMAIN ), __( 'Total', SULAYR_TEXTDOMAIN ), __( 'Pagado', SULAYR_TEXTDOMAIN ), __( 'Pendiente', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ), '' ] as $heading ) {
            printf( '<th scope="col">%s</th>', esc_html( $heading ) );
        }
        echo '</tr></thead><tbody>';
        foreach ( $receipts as $receipt ) {
            $user_id = (int) $receipt->user_id;
            $paid = Receipts::paid( (int) $receipt->id );
            $pending = Receipts::outstanding( $receipt );
            if ( Receipts::STATUS_ANNULLED !== $receipt->status ) {
                $sum['total'] += (float) $receipt->total;
                $sum['paid'] += $paid;
                $sum['pending'] += $pending;
            }
            printf(
                '<tr><td><a href="%1$s"><strong>%2$s</strong></a></td><td>%3$s</td><td><a href="%4$s">%5$s</a></td><td>%6$s</td><td>%7$s</td><td>%8$s</td><td>%9$s</td><td><a href="%10$s" target="_blank">%11$s</a></td></tr>',
                esc_url( self::url( $period, (int) $receipt->id ) ),
                esc_html( (string) $receipt->number ),
                esc_html( (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true ) ),
                esc_url( MembersMenu::member_url( $user_id ) ),
                esc_html( $this->name( $user_id ) ),
                esc_html( Receipts::money( (float) $receipt->total ) ),
                esc_html( Receipts::money( $paid ) ),
                esc_html( Receipts::money( $pending ) ),
                $this->status_html( $receipt ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
                esc_url( self::pdf_url( (int) $receipt->id ) ),
                esc_html__( 'Ver recibo', SULAYR_TEXTDOMAIN )
            );
        }
        printf(
            '</tbody><tfoot><tr><th colspan="3">%s</th><th>%s</th><th>%s</th><th>%s</th><th colspan="2"></th></tr></tfoot></table>',
            esc_html__( 'Totales (sin anulados)', SULAYR_TEXTDOMAIN ),
            esc_html( Receipts::money( $sum['total'] ) ),
            esc_html( Receipts::money( $sum['paid'] ) ),
            esc_html( Receipts::money( $sum['pending'] ) )
        );
    }

    /**
     * Detalle de un recibo.
     *
     * @param   int $id Recibo.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function render_detail( int $id ): void {
        $receipt = Receipts::get( $id );
        if ( null === $receipt ) {
            echo '<p>' . esc_html__( 'Recibo no encontrado.', SULAYR_TEXTDOMAIN ) . '</p>';
            return;
        }

        $user_id = (int) $receipt->user_id;
        $period = (string) $receipt->period;
        $editable = Receipts::is_editable( $receipt );

        printf( '<p><a href="%s">← %s</a></p>', esc_url( self::url( $period ) ), esc_html( sprintf( __( 'Recibos de %s', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ) ) );
        printf( '<p class="sulayr-eyebrow">%s</p><h1>%s</h1>', esc_html( sprintf( __( 'Recibo %s', SULAYR_TEXTDOMAIN ), $receipt->number ) ), esc_html( $this->name( $user_id ) ) );
        printf(
            '<p>%s · %s · <a href="%s">%s</a> · <a class="button" href="%s" target="_blank">%s</a></p>',
            esc_html( ucfirst( Receipts::month_label( $period ) ) ),
            $this->status_html( $receipt ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
            esc_url( MembersMenu::member_url( $user_id ) ),
            esc_html__( 'Ficha del socio', SULAYR_TEXTDOMAIN ),
            esc_url( self::pdf_url( $id ) ),
            esc_html__( 'Ver recibo (PDF)', SULAYR_TEXTDOMAIN )
        );
        if ( Receipts::STATUS_ANNULLED === $receipt->status && '' !== (string) $receipt->note ) {
            printf( '<p>%s</p>', esc_html( sprintf( __( 'Motivo de la anulación: %s', SULAYR_TEXTDOMAIN ), $receipt->note ) ) );
        }

        // Desfase (8.3).
        if ( (int) $receipt->outdated > 0 ) {
            $diff = Receipts::outdated_diff( $receipt );
            echo '<div class="notice notice-warning inline"><p>';
            echo esc_html( sprintf( __( 'Recibo desfasado: algo cambió después de generarlo (servicios, fechas, tipo, subvenciones o precios). Diferencia: %s.', SULAYR_TEXTDOMAIN ), Receipts::money( $diff ) ) ) . ' ';
            if ( $editable ) {
                $this->button( 'regenerar', __( 'Regenerar recibo', SULAYR_TEXTDOMAIN ), [ 'receipt_id' => $id, 'mes' => $period ], 'button-primary sulayr-cta' );
            } elseif ( Receipts::STATUS_PAID === $receipt->status && 0.0 !== $diff ) {
                $this->button( 'corregir', __( 'Crear corrección en el recibo siguiente', SULAYR_TEXTDOMAIN ), [ 'receipt_id' => $id, 'mes' => $period ], 'button-primary sulayr-cta' );
            }
            echo '</p></div>';
        }

        // Líneas.
        echo '<h2>' . esc_html__( 'Líneas', SULAYR_TEXTDOMAIN ) . '</h2><table class="widefat striped sulayr-t-lines"><thead><tr><th>' . esc_html__( 'Concepto', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Tipo', SULAYR_TEXTDOMAIN ) . '</th><th style="text-align:right">' . esc_html__( 'Importe', SULAYR_TEXTDOMAIN ) . '</th><th></th></tr></thead><tbody>';
        foreach ( Receipts::lines( $id ) as $line ) {
            $cancelled = (int) $line->cancelled > 0;
            echo '<tr' . ( $cancelled ? ' class="sulayr-line-cancelled"' : '' ) . '>';
            printf( '<td>%s%s</td><td>%s</td><td style="text-align:right">%s</td><td>', esc_html( (string) $line->concept ), $cancelled ? ' <em>(' . esc_html__( 'anulada por congelación', SULAYR_TEXTDOMAIN ) . ')</em>' : '', esc_html( ReceiptCalculator::type_label( (string) $line->type ) ), esc_html( Receipts::money( (float) $line->amount ) ) );
            if ( $editable && in_array( (string) $line->type, [ ReceiptCalculator::TYPE_MANUAL, ReceiptCalculator::TYPE_PENALTY ], true ) ) {
                $this->button( 'borrar_linea', __( 'Borrar', SULAYR_TEXTDOMAIN ), [ 'receipt_id' => $id, 'line_id' => (int) $line->id, 'mes' => $period ], 'button-small', __( '¿Borrar esta línea?', SULAYR_TEXTDOMAIN ) );
            }
            echo '</td></tr>';
        }
        printf( '</tbody><tfoot><tr><th colspan="2">%s</th><th style="text-align:right">%s</th><th></th></tr></tfoot></table>', esc_html__( 'Total', SULAYR_TEXTDOMAIN ), esc_html( Receipts::money( (float) $receipt->total ) ) );

        if ( $editable ) {
            $this->open_form( 'linea', [ 'receipt_id' => $id, 'mes' => $period ] );
            printf(
                '<p><strong>%1$s</strong> <input type="text" name="concept" class="regular-text" placeholder="%2$s" required> <input type="number" name="amount" step="0.01" placeholder="%3$s" required> <button type="submit" class="button">%4$s</button></p></form>',
                esc_html__( 'Línea manual:', SULAYR_TEXTDOMAIN ),
                esc_attr__( 'Concepto', SULAYR_TEXTDOMAIN ),
                esc_attr__( 'Importe (± €)', SULAYR_TEXTDOMAIN ),
                esc_html__( 'Añadir', SULAYR_TEXTDOMAIN )
            );
            if ( 0 === (int) $receipt->outdated ) {
                echo '<p>';
                $this->button( 'regenerar', __( 'Volver a calcular', SULAYR_TEXTDOMAIN ), [ 'receipt_id' => $id, 'mes' => $period ], '' );
                echo '</p>';
            }
        } else {
            echo '<p class="description">' . esc_html__( 'Recibo pagado o anulado: no se puede cambiar (D43).', SULAYR_TEXTDOMAIN ) . '</p>';
        }

        // Pagos.
        echo '<h2>' . esc_html__( 'Pagos', SULAYR_TEXTDOMAIN ) . '</h2>';
        $payments = Receipts::payments( $id );
        if ( empty( $payments ) ) {
            echo '<p>' . esc_html__( 'Sin pagos.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped sulayr-t-payments"><thead><tr><th>' . esc_html__( 'Fecha', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Medio', SULAYR_TEXTDOMAIN ) . '</th><th style="text-align:right">' . esc_html__( 'Importe', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Apuntado por', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Comentario', SULAYR_TEXTDOMAIN ) . '</th><th></th></tr></thead><tbody>';
            foreach ( $payments as $payment ) {
                $by = get_userdata( (int) $payment->created_by );
                printf(
                    '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td>%s</td><td>%s</td><td>',
                    esc_html( MemberDates::show( (string) $payment->paid_on ) ),
                    esc_html( Receipts::method_label( (string) $payment->method ) ),
                    esc_html( Receipts::money( (float) $payment->amount ) ),
                    esc_html( ( false !== $by ? $by->display_name : '—' ) . ' · ' . date_i18n( 'd/m/Y H:i', strtotime( (string) $payment->created_at ) ) ),
                    esc_html( (string) $payment->note )
                );
                $this->open_form( 'borrar_pago', [ 'receipt_id' => $id, 'payment_id' => (int) $payment->id, 'mes' => $period ], 'sulayr-inline' );
                printf(
                    '<input type="text" name="reason" placeholder="%s" required> <button type="submit" class="button button-small" onclick="return confirm(\'%s\');">%s</button></form>',
                    esc_attr__( 'Motivo', SULAYR_TEXTDOMAIN ),
                    esc_js( (int) $payment->wallet_post_id > 0 ? __( '¿Borrar el pago? Se devolverá el importe al monedero.', SULAYR_TEXTDOMAIN ) : __( '¿Borrar el pago?', SULAYR_TEXTDOMAIN ) ),
                    esc_html__( 'Borrar', SULAYR_TEXTDOMAIN )
                );
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }

        $outstanding = Receipts::outstanding( $receipt );
        if ( $outstanding > 0 ) {
            $this->open_form( 'pago', [ 'receipt_id' => $id, 'mes' => $period ] );
            echo '<p><strong>' . esc_html__( 'Apuntar pago:', SULAYR_TEXTDOMAIN ) . '</strong> ';
            printf( '<input type="number" name="amount" step="0.01" min="0.01" max="%1$s" value="%1$s" required> € ', esc_attr( number_format( $outstanding, 2, '.', '' ) ) );
            printf( '<input type="date" name="paid_on" value="%s" required> ', esc_attr( current_time( 'Y-m-d' ) ) );
            echo '<select name="method" required>';
            foreach ( Receipts::payment_methods() as $value => $label ) {
                $text = Receipts::METHOD_WALLET === $value ? sprintf( __( '%1$s (saldo %2$s)', SULAYR_TEXTDOMAIN ), $label, Receipts::money( WalletLedger::balance( $user_id ) ) ) : $label;
                printf( '<option value="%s">%s</option>', esc_attr( $value ), esc_html( $text ) );
            }
            printf( '</select> <input type="text" name="note" placeholder="%s"> <button type="submit" class="button button-primary sulayr-cta">%s</button></p></form>', esc_attr__( 'Comentario (opcional)', SULAYR_TEXTDOMAIN ), esc_html__( 'Apuntar', SULAYR_TEXTDOMAIN ) );
        }

        // Anular.
        if ( Receipts::STATUS_ANNULLED !== $receipt->status && empty( $payments ) ) {
            echo '<h2>' . esc_html__( 'Anular recibo', SULAYR_TEXTDOMAIN ) . '</h2>';
            $this->open_form( 'anular', [ 'receipt_id' => $id, 'mes' => $period ] );
            printf(
                '<p><input type="text" name="reason" class="regular-text" placeholder="%s" required> <button type="submit" class="button" onclick="return confirm(\'%s\');">%s</button></p><p class="description">%s</p></form>',
                esc_attr__( 'Motivo', SULAYR_TEXTDOMAIN ),
                esc_js( __( '¿Anular este recibo? No se puede deshacer.', SULAYR_TEXTDOMAIN ) ),
                esc_html__( 'Anular', SULAYR_TEXTDOMAIN ),
                esc_html__( 'El número no se reutiliza y no se generará otro recibo de este mes para este socio.', SULAYR_TEXTDOMAIN )
            );
        }
    }

    /**
     * Bloque de la ficha del socio: recibos, correcciones pendientes,
     * subvenciones y retribución de la junta.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function render_member_block( int $user_id ): void {
        $back = MembersMenu::member_url( $user_id );

        echo '<div class="sulayr-brand sulayr-brand-block">';
        echo '<h2 id="recibos">' . esc_html__( 'Recibos', SULAYR_TEXTDOMAIN ) . '</h2>';
        $receipts = Receipts::for_user( $user_id );
        if ( empty( $receipts ) ) {
            echo '<p>' . esc_html__( 'Sin recibos.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped sulayr-t-member"><thead><tr><th>' . esc_html__( 'Mes', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Recibo', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Total', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Pendiente', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Estado', SULAYR_TEXTDOMAIN ) . '</th></tr></thead><tbody>';
            foreach ( $receipts as $receipt ) {
                printf(
                    '<tr><td>%s</td><td><a href="%s">%s</a></td><td>%s</td><td>%s</td><td>%s</td></tr>',
                    esc_html( ucfirst( Receipts::month_label( (string) $receipt->period ) ) ),
                    esc_url( self::url( (string) $receipt->period, (int) $receipt->id ) ),
                    esc_html( (string) $receipt->number ),
                    esc_html( Receipts::money( (float) $receipt->total ) ),
                    esc_html( Receipts::money( Receipts::outstanding( $receipt ) ) ),
                    $this->status_html( $receipt ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escapado dentro.
                );
            }
            echo '</tbody></table>';
        }

        // Correcciones pendientes (D43, decisión 8).
        $pending = Receipts::pending_corrections( $user_id );
        if ( ! empty( $pending ) ) {
            echo '<h3>' . esc_html__( 'Correcciones pendientes del próximo recibo', SULAYR_TEXTDOMAIN ) . '</h3><table class="widefat striped"><tbody>';
            foreach ( $pending as $line ) {
                printf( '<tr><td>%s</td><td>%s</td><td>', esc_html( (string) $line->concept ), esc_html( Receipts::money( (float) $line->amount ) ) );
                if ( (float) $line->amount < 0 ) {
                    $this->button( 'correccion_monedero', __( 'Pasar al monedero', SULAYR_TEXTDOMAIN ), [ 'user_id' => $user_id, 'line_id' => (int) $line->id, 'back' => $back ], 'button-small', __( '¿Apuntar esta cantidad a favor del socio en su monedero?', SULAYR_TEXTDOMAIN ) );
                }
                $this->open_form( 'correccion_descartar', [ 'user_id' => $user_id, 'line_id' => (int) $line->id, 'back' => $back ], 'sulayr-inline' );
                printf( ' <input type="text" name="reason" placeholder="%s" required> <button type="submit" class="button button-small">%s</button></form></td></tr>', esc_attr__( 'Motivo', SULAYR_TEXTDOMAIN ), esc_html__( 'Descartar', SULAYR_TEXTDOMAIN ) );
            }
            echo '</tbody></table>';
        }

        // Subvenciones (D35).
        echo '<h2 id="subvenciones">' . esc_html__( 'Subvenciones', SULAYR_TEXTDOMAIN ) . '</h2>';
        $subsidies = Subsidies::for_user( $user_id );
        if ( empty( $subsidies ) ) {
            echo '<p>' . esc_html__( 'Sin subvenciones.', SULAYR_TEXTDOMAIN ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Subvención', SULAYR_TEXTDOMAIN ) . '</th><th>' . esc_html__( 'Motivo', SULAYR_TEXTDOMAIN ) . '</th><th></th></tr></thead><tbody>';
            foreach ( $subsidies as $subsidy ) {
                printf( '<tr><td>%s</td><td>%s</td><td>', esc_html( Subsidies::describe( $subsidy ) ), esc_html( (string) $subsidy->reason ) );
                if ( null === $subsidy->end_date || (string) $subsidy->end_date >= current_time( 'Y-m-d' ) ) {
                    $this->open_form( 'subvencion_fin', [ 'user_id' => $user_id, 'subsidy_id' => (int) $subsidy->id, 'back' => $back . '#subvenciones' ], 'sulayr-inline' );
                    printf( '%s <input type="month" name="end_month" value="%s" required> <button type="submit" class="button button-small">%s</button></form>', esc_html__( 'Último mes:', SULAYR_TEXTDOMAIN ), esc_attr( current_time( 'Y-m' ) ), esc_html__( 'Terminar', SULAYR_TEXTDOMAIN ) );
                }
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }
        $this->open_form( 'subvencion', [ 'user_id' => $user_id, 'back' => $back . '#subvenciones' ] );
        echo '<table class="form-table" role="presentation"><tbody>';
        printf(
            '<tr><th>%1$s</th><td><select name="mode"><option value="%2$s">%3$s</option><option value="%4$s">%5$s</option></select> <input type="number" name="value" step="0.01" min="0.01" required></td></tr>',
            esc_html__( 'Nueva subvención', SULAYR_TEXTDOMAIN ),
            esc_attr( Subsidies::MODE_PERCENT ),
            esc_html__( 'Porcentaje (%)', SULAYR_TEXTDOMAIN ),
            esc_attr( Subsidies::MODE_AMOUNT ),
            esc_html__( 'Importe al mes (€)', SULAYR_TEXTDOMAIN )
        );
        echo '<tr><th>' . esc_html__( 'Servicios', SULAYR_TEXTDOMAIN ) . '</th><td>';
        foreach ( ServiceCatalog::all() as $service ) {
            printf( '<label><input type="checkbox" name="services[]" value="%d"> %s</label><br>', (int) $service->id, esc_html( $service->title ) );
        }
        echo '<p class="description">' . esc_html__( 'Sin marcar ninguno, se aplica a todos.', SULAYR_TEXTDOMAIN ) . '</p></td></tr>';
        printf(
            '<tr><th>%1$s</th><td><input type="month" name="start_month" value="%2$s" required> – <input type="month" name="end_month"> <span class="description">%3$s</span></td></tr>',
            esc_html__( 'Meses', SULAYR_TEXTDOMAIN ),
            esc_attr( current_time( 'Y-m' ) ),
            esc_html__( 'El último puede quedar vacío.', SULAYR_TEXTDOMAIN )
        );
        printf( '<tr><th>%s</th><td><input type="text" name="reason" class="regular-text" required></td></tr>', esc_html__( 'Motivo', SULAYR_TEXTDOMAIN ) );
        printf( '</tbody></table><p><button type="submit" class="button">%s</button></p></form>', esc_html__( 'Guardar subvención', SULAYR_TEXTDOMAIN ) );

        // Retribución de la junta (D34).
        echo '<h2 id="retribucion">' . esc_html__( 'Retribución mensual de la junta', SULAYR_TEXTDOMAIN ) . '</h2>';
        $this->open_form( 'retribucion', [ 'user_id' => $user_id, 'back' => $back . '#retribucion' ] );
        printf(
            '<p><input type="number" name="board_payment" step="0.01" min="0" value="%s"> € <button type="submit" class="button">%s</button></p><p class="description">%s</p></form>',
            esc_attr( (string) get_user_meta( $user_id, CustomFields::MEMBER_BOARD_PAYMENT, true ) ),
            esc_html__( 'Guardar', SULAYR_TEXTDOMAIN ),
            esc_html__( 'Se apunta sola en su monedero el día 1 de cada mes si está activo ese día. 0 o vacío: no se apunta nada.', SULAYR_TEXTDOMAIN )
        );
        echo '</div>';
    }

    /**
     * Estado con aviso de desfase.
     *
     * @param   object $receipt Recibo.
     * @return 	string HTML.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function status_html( object $receipt ): string {
        $status = Receipts::display_status( $receipt );
        $html = sprintf( '<span class="sulayr-receipt-status sulayr-receipt-%s">%s</span>', esc_attr( $status ), esc_html( Receipts::status_label( $status ) ) );
        if ( (int) $receipt->outdated > 0 ) {
            $html .= ' <span class="sulayr-receipt-outdated">' . esc_html__( 'desfasado', SULAYR_TEXTDOMAIN ) . '</span>';
        }

        return $html;
    }

    /**
     * Crea una subvención desde el formulario de la ficha.
     *
     * @param   int $user_id Socio.
     * @return 	int|\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function create_subsidy( int $user_id ) {
        if ( false === $this->is_member( $user_id ) ) {
            return $this->not_found();
        }

        $start = $this->month_start( (string) wp_unslash( $_POST['start_month'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce en handle().
        $end_input = (string) wp_unslash( $_POST['end_month'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $end = '' !== $end_input ? $this->month_end( $end_input ) : '';
        $services = array_map( 'absint', (array) wp_unslash( $_POST['services'] ?? [] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

        return Subsidies::create(
            $user_id,
            sanitize_key( wp_unslash( $_POST['mode'] ?? '' ) ), // phpcs:ignore WordPress.Security.NonceVerification.Missing
            $this->amount( 'value' ),
            $services,
            $start,
            $end,
            sanitize_text_field( wp_unslash( $_POST['reason'] ?? '' ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
        );
    }

    /**
     * Guarda la retribución de la junta (queda en el registro por la meta).
     *
     * @param   int $user_id Socio.
     * @return 	true|\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function save_board_payment( int $user_id ) {
        if ( false === $this->is_member( $user_id ) ) {
            return $this->not_found();
        }

        $amount = $this->amount( 'board_payment' );
        if ( $amount < 0 ) {
            return new \WP_Error( 'sulayr_amount', __( 'La retribución no puede ser negativa.', SULAYR_TEXTDOMAIN ) );
        }
        if ( 0.0 === $amount ) {
            delete_user_meta( $user_id, CustomFields::MEMBER_BOARD_PAYMENT );
        } else {
            update_user_meta( $user_id, CustomFields::MEMBER_BOARD_PAYMENT, (string) $amount );
        }

        return true;
    }

    /**
     * Botón suelto en su propio formulario.
     *
     * @param   string $do      Acción.
     * @param   string $label   Texto.
     * @param   array  $hidden  Campos ocultos.
     * @param   string $class   Clase del botón.
     * @param   string $confirm Pregunta de confirmación.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function button( string $do, string $label, array $hidden, string $class = '', string $confirm = '' ): void {
        $this->open_form( $do, $hidden, 'sulayr-inline' );
        printf(
            '<button type="submit" class="button %s"%s>%s</button></form>',
            esc_attr( $class ),
            '' !== $confirm ? ' onclick="return confirm(\'' . esc_js( $confirm ) . '\');"' : '',
            esc_html( $label )
        );
    }

    /**
     * Abre un formulario de acción.
     *
     * @param   string $do     Acción.
     * @param   array  $hidden Campos ocultos.
     * @param   string $class  Clase.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function open_form( string $do, array $hidden, string $class = '' ): void {
        printf( '<form method="post" action="%s" class="%s">', esc_url( admin_url( 'admin-post.php' ) ), esc_attr( $class ) );
        wp_nonce_field( self::ACTION );
        printf( '<input type="hidden" name="action" value="%s"><input type="hidden" name="do" value="%s">', esc_attr( self::ACTION ), esc_attr( $do ) );
        foreach ( $hidden as $name => $value ) {
            printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( (string) $value ) );
        }
    }

    /**
     * Mes pedido (AAAA-MM) o el mes en curso.
     *
     * @param   string $value Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function period( string $value ): string {
        return 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $value ) ? $value : current_time( 'Y-m' );
    }

    /**
     * Día 1 de un mes AAAA-MM, o ''.
     *
     * @param   string $month Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function month_start( string $month ): string {
        return 1 === preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $month ) ? $month . '-01' : '';
    }

    /**
     * Último día de un mes AAAA-MM, o ''.
     *
     * @param   string $month Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function month_end( string $month ): string {
        $start = $this->month_start( $month );

        return '' !== $start ? ( new \DateTimeImmutable( $start ) )->format( 'Y-m-t' ) : '';
    }

    /**
     * Importe de un campo, admitiendo coma decimal.
     *
     * @param   string $name Campo.
     * @return 	float
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function amount( string $name ): float {
        $value = str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST[ $name ] ?? '' ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce en handle().

        return is_numeric( $value ) ? round( (float) $value, 2 ) : 0.0;
    }

    /**
     * True si la corrección pendiente es de ese socio.
     *
     * @param   int $line_id Línea.
     * @param   int $user_id Socio.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function own_line( int $line_id, int $user_id ): bool {
        foreach ( Receipts::pending_corrections( $user_id ) as $line ) {
            if ( (int) $line->id === $line_id ) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si es un deportista del club.
     *
     * @param   int $user_id Usuario.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function is_member( int $user_id ): bool {
        $user = get_userdata( $user_id );

        return false !== $user && in_array( 'athlete', (array) $user->roles, true );
    }

    /**
     * Error «no encontrado».
     *
     * @return 	\WP_Error
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function not_found(): \WP_Error {
        return new \WP_Error( 'sulayr_not_found', __( 'No encontrado.', SULAYR_TEXTDOMAIN ) );
    }

    /**
     * Nombre completo.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function name( int $user_id ): string {
        $user = get_userdata( $user_id );

        return HelperService::get_user_full_name( $user_id ) ?: ( false !== $user ? $user->display_name : '#' . $user_id );
    }

    /**
     * Corta si no es gestor.
     *
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private function require_cap(): void {
        if ( false === current_user_can( MemberRoles::MANAGE_MEMBERS ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ), 403 );
        }
    }
}
