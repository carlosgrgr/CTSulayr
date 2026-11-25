<?php
namespace ClubTriatlonSulayr\Modules\Payments;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\Receipts\Receipts;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Cobro mensual con tarjeta (SPEC-COBROS §6, C15 a C19, C14).
 *
 * El estado de cada mes vive en la opción sulayr_tpv_charges (§4.3), por
 * «AAAA-MM»: quién aprobó y cuándo, día de cobro, avisos enviados, cuándo se
 * cobró, reintentos hechos y el resumen del lote.
 *
 * run() la llama la tarea diaria de socios y, al aprobar, un evento único de
 * WP-Cron (si se aprueba pasado el día de cobro, se cobra al momento, C16).
 * Cada paso deja su marca en el estado, así ejecutarla dos veces el mismo
 * día no cobra dos veces (criterio 11). Un bloqueo de MySQL evita dos lotes
 * a la vez y CardCharges bloquea cada recibo.
 */
class MonthlyCharges {

    public const OPTION = 'sulayr_tpv_charges';
    public const HOOK_NOW = 'sulayr_tpv_charge_now';
    private const LOCK = 'sulayr_tpv_batch';

    /** @var string|null Fecha fija (Y-m-d) para las comprobaciones locales. */
    private static $today = null;

    /**
     * Fija la fecha de hoy (solo para las comprobaciones locales).
     *
     * @param   string|null $today Y-m-d, o null para la fecha real.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function use_today( ?string $today ): void {
        self::$today = $today;
    }

    /**
     * Hoy en la hora del club (Y-m-d).
     *
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function today(): string {
        return self::$today ?? current_time( 'Y-m-d' );
    }

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( self::HOOK_NOW, [ self::class, 'run' ] );
    }

    /**
     * Estado de un mes.
     *
     * @param   string $period AAAA-MM.
     * @return 	array<string, mixed>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function state( string $period ): array {
        $all = get_option( self::OPTION, [] );

        return is_array( $all ) && isset( $all[ $period ] ) && is_array( $all[ $period ] ) ? $all[ $period ] : [];
    }

    /**
     * El cobro del mes está aprobado.
     *
     * @param   string $period AAAA-MM.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_approved( string $period ): bool {
        return ! empty( self::state( $period )['approved_at'] );
    }

    /**
     * Ya empezó el cobro del mes (no se puede anular la aprobación).
     *
     * @param   string $period AAAA-MM.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_started( string $period ): bool {
        return ! empty( self::state( $period )['started_at'] );
    }

    /**
     * Fecha de cobro del mes: el día guardado al aprobar o el del ajuste (C15).
     *
     * @param   string $period AAAA-MM.
     * @return 	string Y-m-d.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function charge_date( string $period ): string {
        $state = self::state( $period );
        $day = (int) ( $state['charge_day'] ?? MemberSettings::get( 'tpv_charge_day' ) );

        return $period . '-' . sprintf( '%02d', min( 28, max( 1, $day ) ) );
    }

    /**
     * Aprueba el cobro del mes en curso (C16).
     *
     * @param   string $period  AAAA-MM.
     * @param   int    $user_id Gestor.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function approve( string $period, int $user_id ) {
        if ( substr( self::today(), 0, 7 ) !== $period ) {
            return new \WP_Error( 'sulayr_tpv_period', __( 'Solo se puede aprobar el cobro del mes en curso.', SULAYR_TEXTDOMAIN ) );
        }
        if ( false === CardPayments::is_enabled() ) {
            return new \WP_Error( 'sulayr_tpv_disabled', __( 'El pago con tarjeta no está configurado.', SULAYR_TEXTDOMAIN ) );
        }
        if ( self::is_approved( $period ) ) {
            return new \WP_Error( 'sulayr_tpv_approved', __( 'El cobro de este mes ya está aprobado.', SULAYR_TEXTDOMAIN ) );
        }

        $state = self::state( $period );
        unset( $state['notices_at'] );
        $state['approved_by'] = $user_id;
        $state['approved_at'] = current_time( 'mysql' );
        $state['charge_day'] = min( 28, max( 1, (int) MemberSettings::get( 'tpv_charge_day' ) ) );
        self::save( $period, $state );

        // Avisos y, si ya pasó el día de cobro, el cobro: en segundo plano.
        if ( false === wp_next_scheduled( self::HOOK_NOW ) ) {
            wp_schedule_single_event( time(), self::HOOK_NOW );
        }

        return true;
    }

    /**
     * Anula la aprobación mientras no haya empezado el cobro (§6.1).
     *
     * @param   string $period AAAA-MM.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function unapprove( string $period ) {
        if ( false === self::is_approved( $period ) ) {
            return new \WP_Error( 'sulayr_tpv_not_approved', __( 'El cobro de este mes no está aprobado.', SULAYR_TEXTDOMAIN ) );
        }
        if ( self::has_started( $period ) ) {
            return new \WP_Error( 'sulayr_tpv_started', __( 'El cobro de este mes ya ha empezado: no se puede anular la aprobación.', SULAYR_TEXTDOMAIN ) );
        }

        $state = self::state( $period );
        unset( $state['approved_by'], $state['approved_at'], $state['charge_day'], $state['notices_at'] );
        self::save( $period, $state );

        return true;
    }

    /**
     * Recibos que entran en el cobro (C17): del mes, pendientes o parciales,
     * de socios con medio «Tarjeta (TPV)» y tarjeta activa sin caducar, y sin
     * una operación en curso.
     *
     * @param   string $period AAAA-MM.
     * @return 	array<int, object>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function eligible( string $period ): array {
        $today = self::today();

        return array_values( array_filter( Receipts::for_period( $period ), function ( $receipt ) use ( $today ) {
            $user_id = (int) $receipt->user_id;
            if ( false === Receipts::is_editable( $receipt ) || Receipts::outstanding( $receipt ) <= 0 || false === ChargeMethod::is_tpv( $user_id ) ) {
                return false;
            }

            return self::has_usable_card( $user_id, $today ) && false === TpvTransactions::in_flight( (int) $receipt->id );
        } ) );
    }

    /**
     * Resumen para la pantalla «Aprobar cobro» (§6.1).
     *
     * @param   string $period AAAA-MM.
     * @return 	array{receipts: array<int, object>, amount: float, without_card: array<int, object>, outdated: array<int, object>, in_flight: array<int, object>}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function summary( string $period ): array {
        $today = self::today();
        $summary = [ 'receipts' => [], 'amount' => 0.0, 'without_card' => [], 'outdated' => [], 'in_flight' => [] ];

        foreach ( Receipts::for_period( $period ) as $receipt ) {
            $user_id = (int) $receipt->user_id;
            if ( false === Receipts::is_editable( $receipt ) || Receipts::outstanding( $receipt ) <= 0 || false === ChargeMethod::is_tpv( $user_id ) ) {
                continue;
            }
            if ( false === self::has_usable_card( $user_id, $today ) ) {
                $summary['without_card'][] = $receipt;
                continue;
            }
            if ( TpvTransactions::in_flight( (int) $receipt->id ) ) {
                $summary['in_flight'][] = $receipt;
                continue;
            }
            $summary['receipts'][] = $receipt;
            $summary['amount'] += Receipts::outstanding( $receipt );
            if ( abs( Receipts::outdated_diff( $receipt ) ) >= 0.01 ) {
                $summary['outdated'][] = $receipt;
            }
        }
        $summary['amount'] = round( $summary['amount'], 2 );

        return $summary;
    }

    /**
     * Cobra un recibo con la tarjeta guardada y avisa al socio (C22, C23).
     *
     * @param   int    $receipt_id Recibo.
     * @param   string $type       TpvTransactions::TYPE_RECURRING o TYPE_RETRY.
     * @param   int    $attempt    Intento.
     * @param   string $next_on    Próximo intento automático (Y-m-d), o '' si no habrá.
     * @return 	array{result: string, transaction: object, error: string}|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function charge_receipt( int $receipt_id, string $type = TpvTransactions::TYPE_RECURRING, int $attempt = 1, string $next_on = '' ) {
        $result = CardCharges::charge( $receipt_id, $type, $attempt );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $receipt = Receipts::get( $receipt_id );
        $amount = (float) $result['transaction']->amount;
        if ( null !== $receipt ) {
            if ( CardPayments::RESULT_AUTHORIZED === $result['result'] ) {
                ChargeNotices::charged( $receipt, $amount );
            } elseif ( in_array( $result['result'], [ CardPayments::RESULT_DENIED, CardCharges::RESULT_REJECTED ], true ) ) {
                ChargeNotices::failed( $receipt, $amount, $next_on );
            }
        }

        return $result;
    }

    /**
     * Cobro de un recibo concreto desde wp-admin (C22). Sigue la numeración
     * de intentos si el recibo ya tuvo cobros.
     *
     * @param   int $receipt_id Recibo.
     * @return 	array{result: string, transaction: object, error: string}|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function charge_one( int $receipt_id ) {
        $last = TpvTransactions::latest_charge( $receipt_id );

        return null === $last
            ? self::charge_receipt( $receipt_id )
            : self::charge_receipt( $receipt_id, TpvTransactions::TYPE_RETRY, (int) $last->attempt + 1 );
    }

    /**
     * Tarea del cobro (§6.2). La llaman la tarea diaria y el evento al aprobar.
     *
     * @return 	array<string, int>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function run(): array {
        global $wpdb;

        $result = [ 'notices' => 0, 'charged' => 0, 'failed' => 0, 'unknown' => 0, 'retried' => 0, 'leaves' => 0, 'expired' => 0, 'expiring' => 0 ];
        if ( 1 !== (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', self::LOCK ) ) ) {
            return $result;
        }

        try {
            if ( function_exists( 'set_time_limit' ) ) {
                @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Puede estar desactivada.
            }
            $today = self::today();
            if ( CardPayments::is_enabled() ) {
                self::run_month( substr( self::today(), 0, 7 ), $today, $result );
            }
            self::run_leaves( $result );
            self::run_expiry( $today, $result );
        } finally {
            $wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', self::LOCK ) );
        }

        return $result;
    }

    /**
     * Avisos, cobro y reintentos del mes.
     *
     * @param   string             $period AAAA-MM.
     * @param   string             $today  Y-m-d.
     * @param   array<string, int> $result Contadores.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function run_month( string $period, string $today, array &$result ): void {
        $state = self::state( $period );
        $charge_date = self::charge_date( $period );

        // Sin aprobar el día de cobro: no se cobra y se avisa al club una vez (C16).
        if ( empty( $state['approved_at'] ) ) {
            if ( $today >= $charge_date && empty( $state['unapproved_notice_at'] ) && ! empty( self::eligible( $period ) ) ) {
                CardPayments::notify_club(
                    sprintf( __( 'Cobro con tarjeta de %s sin aprobar', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ),
                    sprintf( __( 'Hoy era el día de cobro con tarjeta de %1$s y nadie lo ha aprobado, así que no se ha cobrado nada. Revisa los recibos y pulsa «Aprobar cobro» en Socios → Recibos: se cobrará en ese momento.', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) )
                );
                $state['unapproved_notice_at'] = current_time( 'mysql' );
                self::save( $period, $state );
            }
            return;
        }

        // Aviso previo a cada socio (C23).
        if ( empty( $state['notices_at'] ) ) {
            foreach ( self::eligible( $period ) as $receipt ) {
                if ( ChargeNotices::notice( $receipt, max( $charge_date, $today ) ) ) {
                    $result['notices']++;
                }
            }
            $state['notices_at'] = current_time( 'mysql' );
            self::save( $period, $state );
        }

        // Día de cobro (o después, si se aprobó tarde).
        if ( empty( $state['charged_on'] ) ) {
            if ( $today < $charge_date ) {
                return;
            }
            $state['started_at'] = current_time( 'mysql' );
            self::save( $period, $state );

            $batch = [ 'charged' => 0, 'failed' => 0, 'unknown' => 0, 'amount' => 0.0 ];
            $next = self::add_days( $today, (int) MemberSettings::get( 'tpv_retry_first' ) );
            foreach ( self::eligible( $period ) as $receipt ) {
                self::count( self::charge_receipt( (int) $receipt->id, TpvTransactions::TYPE_RECURRING, 1, $next ), $batch );
            }
            $state['charged_on'] = $today;
            $state['charged_at'] = current_time( 'mysql' );
            $state['batch'] = $batch;
            self::save( $period, $state );
            foreach ( [ 'charged', 'failed', 'unknown' ] as $key ) {
                $result[ $key ] += $batch[ $key ];
            }
            return;
        }

        // Reintentos (C19): uno por ejecución, a los días configurados desde el cobro.
        $retries = [ 1 => (int) MemberSettings::get( 'tpv_retry_first' ), 2 => (int) MemberSettings::get( 'tpv_retry_second' ) ];
        foreach ( $retries as $n => $days ) {
            $key = 'retry' . $n . '_at';
            if ( ! empty( $state[ $key ] ) ) {
                continue;
            }
            if ( $today < self::add_days( (string) $state['charged_on'], $days ) ) {
                return;
            }

            $next = 1 === $n ? self::add_days( (string) $state['charged_on'], $retries[2] ) : '';
            $batch = [ 'charged' => 0, 'failed' => 0, 'unknown' => 0, 'amount' => 0.0 ];
            foreach ( self::retryable( $period, $n + 1 ) as $receipt ) {
                $result['retried']++;
                self::count( self::charge_receipt( (int) $receipt->id, TpvTransactions::TYPE_RETRY, $n + 1, $next ), $batch );
            }
            $state[ $key ] = current_time( 'mysql' );
            $state[ 'retry' . $n ] = $batch;
            self::save( $period, $state );
            foreach ( [ 'charged', 'failed', 'unknown' ] as $count ) {
                $result[ $count ] += $batch[ $count ];
            }

            if ( 2 === $n ) {
                self::notify_unpaid( $period );
            }
            return;
        }
    }

    /**
     * Recibos con el último cobro fallido (denegado o rechazado) antes de
     * este intento y que siguen entrando en el cobro.
     *
     * @param   string $period  AAAA-MM.
     * @param   int    $attempt Intento que se va a hacer.
     * @return 	array<int, object>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function retryable( string $period, int $attempt ): array {
        return array_values( array_filter( self::eligible( $period ), function ( $receipt ) use ( $attempt ) {
            $last = TpvTransactions::latest_charge( (int) $receipt->id );

            return null !== $last && in_array( $last->status, [ TpvTransactions::STATUS_DENIED, TpvTransactions::STATUS_ERROR ], true ) && (int) $last->attempt < $attempt;
        } ) );
    }

    /**
     * Tras el último reintento: aviso al club con los que siguen sin cobrar (C19).
     *
     * @param   string $period AAAA-MM.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function notify_unpaid( string $period ): void {
        $rows = [];
        foreach ( self::retryable( $period, PHP_INT_MAX ) as $receipt ) {
            $user_id = (int) $receipt->user_id;
            $rows[] = sprintf( '%1$s · %2$s · %3$s', $receipt->number, HelperService::get_user_full_name( $user_id ) ?: '#' . $user_id, Receipts::money( Receipts::outstanding( $receipt ) ) );
        }
        if ( empty( $rows ) ) {
            return;
        }

        CardPayments::notify_club(
            sprintf( __( 'Recibos de %s sin cobrar tras los reintentos', SULAYR_TEXTDOMAIN ), Receipts::month_label( $period ) ),
            sprintf( __( "Después del último reintento, estos recibos siguen sin cobrar con tarjeta. Los socios ya tienen el aviso para pagarlos desde Mis recibos. Siguen el flujo normal de impagados (día 20).\n\n%s", SULAYR_TEXTDOMAIN ), implode( "\n", $rows ) )
        );
    }

    /**
     * Bajas efectivas (C14): se intenta cobrar lo pendiente de los meses con el
     * cobro aprobado y se borra la referencia de la tarjeta.
     *
     * @param   array<string, int> $result Contadores.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function run_leaves( array &$result ): void {
        $today = self::today();
        foreach ( PaymentCards::users_with_reference() as $user_id ) {
            if ( CustomFields::STATUS_LEFT !== (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ) {
                continue;
            }

            if ( CardPayments::is_enabled() && ChargeMethod::is_tpv( $user_id ) && self::has_usable_card( $user_id, $today ) ) {
                foreach ( Receipts::for_user( $user_id ) as $receipt ) {
                    if ( Receipts::is_editable( $receipt ) && Receipts::outstanding( $receipt ) > 0 && self::is_approved( (string) $receipt->period ) && false === TpvTransactions::in_flight( (int) $receipt->id ) ) {
                        $batch = [ 'charged' => 0, 'failed' => 0, 'unknown' => 0, 'amount' => 0.0 ];
                        self::count( self::charge_one( (int) $receipt->id ), $batch );
                        foreach ( [ 'charged', 'failed', 'unknown' ] as $key ) {
                            $result[ $key ] += $batch[ $key ];
                        }
                    }
                }
            }

            PaymentCards::erase_for_leave( $user_id );
            $result['leaves']++;
        }
    }

    /**
     * Caducidad (§6.2, C23): marca las vencidas y avisa una vez de las que
     * caducan pronto a los socios que cobran con tarjeta.
     *
     * @param   string             $today  Y-m-d.
     * @param   array<string, int> $result Contadores.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function run_expiry( string $today, array &$result ): void {
        $days = max( 1, (int) MemberSettings::get( 'tpv_expiry_notice_days' ) );
        $limit = self::add_days( $today, $days );

        foreach ( PaymentCards::all_active() as $card ) {
            if ( PaymentCards::is_expired( $card, $today ) ) {
                PaymentCards::expire( $card );
                $result['expired']++;
                continue;
            }

            $last = PaymentCards::last_valid_day( $card );
            $user_id = (int) $card->user_id;
            if ( '' === $last || $last > $limit || ! empty( $card->expiry_notice_at ) || false === ChargeMethod::is_tpv( $user_id ) ) {
                continue;
            }
            if ( CustomFields::STATUS_LEFT === (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ) {
                continue;
            }
            if ( PaymentCards::mark_expiry_notice( (int) $card->id ) && ChargeNotices::expiring( $card ) ) {
                $result['expiring']++;
            }
        }
    }

    /**
     * El socio tiene una tarjeta activa, con referencia y sin caducar.
     *
     * @param   int    $user_id Socio.
     * @param   string $today   Y-m-d.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function has_usable_card( int $user_id, string $today ): bool {
        $card = PaymentCards::active( $user_id );

        return null !== $card && '' !== trim( (string) $card->identifier ) && false === PaymentCards::is_expired( $card, $today );
    }

    /**
     * Suma el resultado de un cobro a los contadores de un lote.
     *
     * @param   array|\WP_Error      $charge Resultado de charge_receipt().
     * @param   array<string, mixed> $batch  Contadores.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function count( $charge, array &$batch ): void {
        if ( is_wp_error( $charge ) ) {
            return;
        }
        if ( CardPayments::RESULT_AUTHORIZED === $charge['result'] ) {
            $batch['charged']++;
            $batch['amount'] = round( (float) $batch['amount'] + (float) $charge['transaction']->amount, 2 );
        } elseif ( in_array( $charge['result'], [ CardPayments::RESULT_DENIED, CardCharges::RESULT_REJECTED ], true ) ) {
            $batch['failed']++;
        } else {
            $batch['unknown']++;
        }
    }

    /**
     * Fecha más unos días.
     *
     * @param   string $date Y-m-d.
     * @param   int    $days Días.
     * @return 	string Y-m-d.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function add_days( string $date, int $days ): string {
        return ( new \DateTimeImmutable( $date ) )->modify( '+' . max( 0, $days ) . ' days' )->format( 'Y-m-d' );
    }

    /**
     * Guarda el estado de un mes.
     *
     * @param   string               $period AAAA-MM.
     * @param   array<string, mixed> $state  Estado.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function save( string $period, array $state ): void {
        $all = get_option( self::OPTION, [] );
        if ( false === is_array( $all ) ) {
            $all = [];
        }
        $all[ $period ] = $state;
        update_option( self::OPTION, $all, false );
    }
}
