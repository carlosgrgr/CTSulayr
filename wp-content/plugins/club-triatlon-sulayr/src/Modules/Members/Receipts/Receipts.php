<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServicePostType;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\Join\MemberMailer;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\FeesAccount;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberLog;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Recibos mensuales (spec §8, D39 a D52).
 *
 * Un recibo por socio y mes, con número R-AAAA-NNNN (año del mes del recibo,
 * correlativo, nunca reutilizado). Las líneas automáticas salen de
 * ReceiptCalculator; las manuales, penalizaciones y correcciones se
 * conservan al regenerar.
 *
 * Estados guardados: pendiente, parcial, pagado, no_aplica, anulado. Un
 * pendiente o parcial desde el día de penalización se muestra como
 * «impagado». Un recibo pagado o anulado no se edita (D43).
 *
 * Desfase (D40, 8.3): cada cambio de historial de un socio vuelve a calcular
 * sus recibos al final de la petición y marca los que no coinciden. No se
 * cambia nada solo, salvo tras un cambio inmediato del gestor (D27), que
 * regenera el recibo del mes en curso.
 */
class Receipts {

    public const STATUS_PENDING = 'pendiente';
    public const STATUS_PARTIAL = 'parcial';
    public const STATUS_PAID = 'pagado';
    public const STATUS_NOT_APPLICABLE = 'no_aplica';
    public const STATUS_ANNULLED = 'anulado';
    /** Solo para mostrar: pendiente o parcial pasado el día límite. */
    public const STATUS_UNPAID = 'impagado';

    public const METHOD_WALLET = 'monedero';

    /** Meses generados: [ 'AAAA-MM' => [ 'at' => fecha, 'by' => usuario, 'auto' => bool ] ]. */
    public const MONTHS_OPTION = 'sulayr_receipts_months';

    /** @var array<int, true> Socios cuyo historial cambió en esta petición. */
    private static array $dirty = [];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'sulayr_member_history_changed', [ self::class, 'mark_dirty' ] );
        add_action( 'shutdown', [ self::class, 'check_dirty' ] );
        add_action( 'acf/save_post', [ $this, 'on_service_saved' ], 30 );
    }

    /**
     * Anota un socio para comprobar sus recibos al final de la petición.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mark_dirty( $user_id ): void {
        self::$dirty[ (int) $user_id ] = true;
    }

    /**
     * Comprueba el desfase de los socios anotados.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function check_dirty(): void {
        $users = array_keys( self::$dirty );
        self::$dirty = [];
        foreach ( $users as $user_id ) {
            self::check_outdated( (int) $user_id );
        }
    }

    /**
     * Un precio del catálogo cambió: comprueba los recibos de todos (D40).
     *
     * @param   mixed $post_id Post.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_service_saved( $post_id ): void {
        if ( false === is_numeric( $post_id ) || ServicePostType::POST_TYPE_NAME !== get_post_type( (int) $post_id ) ) {
            return;
        }

        \ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog::flush();
        foreach ( self::user_ids_with_receipts() as $user_id ) {
            self::check_outdated( $user_id );
        }
    }

    /* ------------------------------------------------------------------
     * Lectura
     * ------------------------------------------------------------------ */

    /**
     * Recibo por ID.
     *
     * @param   int $id Recibo.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
    }

    /**
     * Recibo de un socio y mes, o null.
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function find( int $user_id, string $period ): ?object {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d AND period = %s", $user_id, $period ) );
    }

    /**
     * Recibos de un socio, del más reciente al más antiguo.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY period DESC, id DESC", $user_id ) );
    }

    /**
     * Recibos de un mes, por número.
     *
     * @param   string $period AAAA-MM.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_period( string $period ): array {
        global $wpdb;

        $table = self::table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE period = %s ORDER BY number ASC", $period ) );
    }

    /**
     * Líneas de un recibo.
     *
     * @param   int $receipt_id Recibo.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function lines( int $receipt_id ): array {
        global $wpdb;

        $table = self::lines_table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE receipt_id = %d ORDER BY id ASC", $receipt_id ) );
    }

    /**
     * Pagos de un recibo.
     *
     * @param   int $receipt_id Recibo.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function payments( int $receipt_id ): array {
        global $wpdb;

        $table = self::payments_table();

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE receipt_id = %d ORDER BY paid_on ASC, id ASC", $receipt_id ) );
    }

    /**
     * Suma pagada.
     *
     * @param   int $receipt_id Recibo.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function paid( int $receipt_id ): float {
        global $wpdb;

        $table = self::payments_table();

        return round( (float) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(amount) FROM {$table} WHERE receipt_id = %d", $receipt_id ) ), 2 );
    }

    /**
     * Lo que queda por pagar (nunca negativo).
     *
     * @param   object $receipt Recibo.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function outstanding( object $receipt ): float {
        if ( self::STATUS_ANNULLED === $receipt->status ) {
            return 0.0;
        }

        return max( 0.0, round( (float) $receipt->total - self::paid( (int) $receipt->id ), 2 ) );
    }

    /**
     * True si se puede editar: no está pagado del todo ni anulado (D43).
     *
     * @param   object $receipt Recibo.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_editable( object $receipt ): bool {
        return false === in_array( (string) $receipt->status, [ self::STATUS_PAID, self::STATUS_ANNULLED ], true );
    }

    /**
     * True si el recibo del socio en ese mes tiene algún pago (D27, C5).
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function has_payments( int $user_id, string $period ): bool {
        $receipt = self::find( $user_id, $period );

        return null !== $receipt && self::paid( (int) $receipt->id ) > 0;
    }

    /**
     * Estado para mostrar: «impagado» si sigue pendiente desde el día límite
     * (el día de penalización del mes del recibo).
     *
     * @param   object $receipt Recibo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function display_status( object $receipt ): string {
        $status = (string) $receipt->status;
        if ( in_array( $status, [ self::STATUS_PENDING, self::STATUS_PARTIAL ], true ) && current_time( 'Y-m-d' ) >= self::limit_date( (string) $receipt->period ) ) {
            return self::STATUS_UNPAID;
        }

        return $status;
    }

    /**
     * Día límite de pago (y desde el que se puede penalizar) de un mes.
     *
     * @param   string $period AAAA-MM.
     * @return 	string Y-m-d.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function limit_date( string $period ): string {
        $first = new \DateTimeImmutable( $period . '-01' );
        $day = min( max( 1, (int) MemberSettings::get( 'penalty_day' ) ), (int) $first->format( 't' ) );

        return $first->format( 'Y-m-' ) . sprintf( '%02d', $day );
    }

    /**
     * Nombre de un estado.
     *
     * @param   string $status Estado.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function status_label( string $status ): string {
        $labels = [
            self::STATUS_PENDING => __( 'Pendiente', SULAYR_TEXTDOMAIN ),
            self::STATUS_PARTIAL => __( 'Pagado parcialmente', SULAYR_TEXTDOMAIN ),
            self::STATUS_PAID => __( 'Pagado', SULAYR_TEXTDOMAIN ),
            self::STATUS_NOT_APPLICABLE => __( 'No aplica', SULAYR_TEXTDOMAIN ),
            self::STATUS_ANNULLED => __( 'Anulado', SULAYR_TEXTDOMAIN ),
            self::STATUS_UNPAID => __( 'Impagado', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $status ] ?? $status;
    }

    /**
     * Nombre de un mes: «noviembre de 2026».
     *
     * @param   string $period AAAA-MM.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function month_label( string $period ): string {
        $time = strtotime( $period . '-01' );

        return false !== $time ? date_i18n( 'F \d\e Y', $time ) : $period;
    }

    /**
     * Medios de pago: los de Socios → Ajustes más el monedero.
     *
     * @return 	array<string, string> valor guardado => nombre.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function payment_methods(): array {
        $methods = [];
        foreach ( MemberSettings::labels( 'payment_methods' ) as $label ) {
            $methods[ $label ] = $label;
        }
        $methods[ self::METHOD_WALLET ] = __( 'Monedero', SULAYR_TEXTDOMAIN );

        return $methods;
    }

    /**
     * Nombre de un medio de pago guardado.
     *
     * @param   string $method Valor.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function method_label( string $method ): string {
        return self::METHOD_WALLET === $method ? __( 'Monedero', SULAYR_TEXTDOMAIN ) : $method;
    }

    /* ------------------------------------------------------------------
     * Meses
     * ------------------------------------------------------------------ */

    /**
     * Meses que se pueden generar a mano: desde el primer mes con recibos
     * hasta el mes en curso (decisión 5 del 11 de noviembre de 2026).
     *
     * @return 	string[] AAAA-MM, del más reciente al más antiguo.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function allowed_months(): array {
        $first = MemberSettings::first_receipts_month();
        $current = current_time( 'Y-m' );
        if ( '' === $first || $first > $current ) {
            return [];
        }

        $months = [];
        $month = new \DateTimeImmutable( $current . '-01' );
        while ( $month->format( 'Y-m' ) >= $first ) {
            $months[] = $month->format( 'Y-m' );
            $month = $month->modify( '-1 month' );
        }

        return $months;
    }

    /**
     * True si ese mes ya se generó.
     *
     * @param   string $period AAAA-MM.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_generated( string $period ): bool {
        $months = get_option( self::MONTHS_OPTION, [] );

        return is_array( $months ) && isset( $months[ $period ] );
    }

    /**
     * Datos de la generación de un mes, o null.
     *
     * @param   string $period AAAA-MM.
     * @return 	array|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function generation( string $period ): ?array {
        $months = get_option( self::MONTHS_OPTION, [] );

        return is_array( $months ) && isset( $months[ $period ] ) ? (array) $months[ $period ] : null;
    }

    /* ------------------------------------------------------------------
     * Generación
     * ------------------------------------------------------------------ */

    /**
     * Genera los recibos que falten de un mes (8.1). Los socios que ya tienen
     * recibo de ese mes no reciben otro.
     *
     * @param   string $period AAAA-MM.
     * @param   bool   $auto   Lo lanza la tarea diaria.
     * @return 	int|\WP_Error Recibos creados.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function generate_month( string $period, bool $auto = false ) {
        if ( false === in_array( $period, self::allowed_months(), true ) ) {
            return new \WP_Error( 'sulayr_receipts_month', __( 'Ese mes no se puede generar. Revisa «Primer mes con recibos» en Socios → Ajustes.', SULAYR_TEXTDOMAIN ) );
        }

        $first = $period . '-01';
        $last = ( new \DateTimeImmutable( $first ) )->format( 'Y-m-t' );
        $created = 0;
        foreach ( ReceiptCalculator::active_members( $first, $last ) as $user_id ) {
            if ( null !== self::generate_for_user( $user_id, $period ) ) {
                $created++;
            }
        }

        $months = get_option( self::MONTHS_OPTION, [] );
        $months = is_array( $months ) ? $months : [];
        if ( false === isset( $months[ $period ] ) ) {
            $months[ $period ] = [ 'at' => current_time( 'mysql' ), 'by' => get_current_user_id(), 'auto' => $auto ];
            update_option( self::MONTHS_OPTION, $months, false );
        }

        return $created;
    }

    /**
     * Genera el recibo de un socio y mes si le corresponde y no lo tiene.
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	int|null ID del recibo creado.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function generate_for_user( int $user_id, string $period ): ?int {
        global $wpdb;

        if ( null !== self::find( $user_id, $period ) ) {
            return null;
        }
        $lines = ReceiptCalculator::lines( $user_id, $period );
        if ( null === $lines ) {
            return null;
        }

        $number = self::insert_receipt( $user_id, $period );
        if ( null === $number ) {
            return null;
        }
        $receipt = self::find( $user_id, $period );
        $id = (int) $receipt->id;

        foreach ( $lines as $line ) {
            self::insert_line( $id, $user_id, $line );
        }

        // Correcciones pendientes de recibos anteriores (D43).
        $wpdb->query( $wpdb->prepare(
            'UPDATE ' . self::lines_table() . ' SET receipt_id = %d WHERE receipt_id = 0 AND user_id = %d AND type = %s',
            $id,
            $user_id,
            ReceiptCalculator::TYPE_CORRECTION
        ) );

        self::recompute( $id );
        $receipt = self::get( $id );
        MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, $number, '', sprintf( '%s · %s', self::month_label( $period ), self::money( (float) $receipt->total ) ) );

        self::maybe_email( $receipt );

        return $id;
    }

    /**
     * Tras aprobar un alta (5.3): si el mes de alta ya está generado, genera
     * el recibo del nuevo socio.
     *
     * @param   int    $user_id Socio.
     * @param   string $start   Fecha de alta Y-m-d.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function after_join( int $user_id, string $start ): void {
        $period = substr( $start, 0, 7 );
        if ( self::is_generated( $period ) ) {
            self::generate_for_user( $user_id, $period );
        }
    }

    /**
     * Tras un cambio inmediato del gestor (D27, D30): regenera el recibo del
     * mes en curso si se puede editar; si el mes está generado y el socio no
     * tenía recibo, lo crea.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function refresh_current( int $user_id ): void {
        $period = current_time( 'Y-m' );
        $receipt = self::find( $user_id, $period );
        if ( null === $receipt ) {
            if ( self::is_generated( $period ) ) {
                self::generate_for_user( $user_id, $period );
            }
            return;
        }

        if ( self::is_editable( $receipt ) ) {
            MemberLog::with_note( __( 'Cambio inmediato', SULAYR_TEXTDOMAIN ), function () use ( $receipt ) {
                self::regenerate( (int) $receipt->id );
            } );
        }
    }

    /**
     * Vuelve a calcular las líneas automáticas de un recibo que se puede
     * editar. Conserva número, líneas manuales, penalizaciones y correcciones.
     *
     * @param   int $id Recibo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function regenerate( int $id ) {
        global $wpdb;

        $receipt = self::get( $id );
        if ( null === $receipt || false === self::is_editable( $receipt ) ) {
            return new \WP_Error( 'sulayr_receipt_locked', __( 'Este recibo está pagado o anulado y no se puede cambiar. Usa una corrección en el recibo siguiente.', SULAYR_TEXTDOMAIN ) );
        }

        $lines = ReceiptCalculator::lines( (int) $receipt->user_id, (string) $receipt->period );
        $lines = null !== $lines ? $lines : [];
        $kept = array_filter( self::lines( $id ), function ( $line ) {
            return false === in_array( (string) $line->type, ReceiptCalculator::AUTO_TYPES, true );
        } );
        $new_total = round( ReceiptCalculator::sum( $lines ) + ReceiptCalculator::sum( $kept ), 2 );
        if ( $new_total < self::paid( $id ) ) {
            return new \WP_Error( 'sulayr_receipt_overpaid', __( 'Con las líneas nuevas el total quedaría por debajo de lo ya pagado. Borra antes el pago que sobra.', SULAYR_TEXTDOMAIN ) );
        }

        $old_total = (float) $receipt->total;
        $placeholders = implode( ',', array_fill( 0, count( ReceiptCalculator::AUTO_TYPES ), '%s' ) );
        $wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::lines_table() . " WHERE receipt_id = %d AND type IN ({$placeholders})", array_merge( [ $id ], ReceiptCalculator::AUTO_TYPES ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Marcadores generados arriba.
        foreach ( $lines as $line ) {
            self::insert_line( $id, (int) $receipt->user_id, $line );
        }
        $wpdb->update( self::table(), [ 'outdated' => 0 ], [ 'id' => $id ] );
        self::recompute( $id );

        $receipt = self::get( $id );
        MemberLog::record( (int) $receipt->user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, self::money( $old_total ), self::money( (float) $receipt->total ), __( 'Regenerado', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /* ------------------------------------------------------------------
     * Desfase
     * ------------------------------------------------------------------ */

    /**
     * Diferencia entre lo calculado hoy y lo que tiene el recibo en sus
     * líneas automáticas, descontadas las correcciones ya creadas desde él.
     *
     * @param   object $receipt Recibo.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function outdated_diff( object $receipt ): float {
        global $wpdb;

        $lines = ReceiptCalculator::lines( (int) $receipt->user_id, (string) $receipt->period );
        $computed = null !== $lines ? ReceiptCalculator::sum( $lines ) : 0.0;
        $stored = ReceiptCalculator::sum( self::lines( (int) $receipt->id ), true );
        $corrected = (float) $wpdb->get_var( $wpdb->prepare( 'SELECT SUM(amount) FROM ' . self::lines_table() . ' WHERE source_receipt_id = %d', (int) $receipt->id ) );

        return round( $computed - $stored - $corrected, 2 );
    }

    /**
     * Marca o desmarca como desfasados los recibos de un socio (D40, 8.3).
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function check_outdated( int $user_id ): void {
        global $wpdb;

        if ( 4 > (int) get_option( MembersSchema::VERSION_OPTION ) ) {
            return;
        }

        foreach ( self::for_user( $user_id ) as $receipt ) {
            if ( self::STATUS_ANNULLED === $receipt->status ) {
                continue;
            }

            if ( self::is_editable( $receipt ) ) {
                $lines = ReceiptCalculator::lines( $user_id, (string) $receipt->period );
                $outdated = ReceiptCalculator::signature( null !== $lines ? $lines : [] ) !== ReceiptCalculator::signature( self::lines( (int) $receipt->id ) );
            } else {
                $outdated = 0.0 !== self::outdated_diff( $receipt );
            }

            if ( (int) $receipt->outdated !== (int) $outdated ) {
                $wpdb->update( self::table(), [ 'outdated' => (int) $outdated ], [ 'id' => (int) $receipt->id ] );
                if ( $outdated ) {
                    MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, '', __( 'Desfasado', SULAYR_TEXTDOMAIN ) );
                }
            }
        }
    }

    /**
     * Recibo pagado y desfasado: crea la línea de corrección (D43) en el
     * siguiente recibo que se pueda editar o, si no hay, la deja pendiente
     * para el próximo que se genere.
     *
     * @param   int $id Recibo pagado.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function create_correction( int $id ) {
        global $wpdb;

        $receipt = self::get( $id );
        if ( null === $receipt || self::STATUS_PAID !== $receipt->status ) {
            return new \WP_Error( 'sulayr_correction', __( 'Solo se corrige así un recibo pagado. Uno sin pagar se regenera.', SULAYR_TEXTDOMAIN ) );
        }
        $diff = self::outdated_diff( $receipt );
        if ( 0.0 === $diff ) {
            return new \WP_Error( 'sulayr_correction', __( 'El recibo no tiene diferencias.', SULAYR_TEXTDOMAIN ) );
        }

        $user_id = (int) $receipt->user_id;
        $target = $wpdb->get_row( $wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE user_id = %d AND period > %s AND status NOT IN (%s, %s) ORDER BY period ASC LIMIT 1',
            $user_id,
            (string) $receipt->period,
            self::STATUS_PAID,
            self::STATUS_ANNULLED
        ) );

        self::insert_line( null !== $target ? (int) $target->id : 0, $user_id, [
            'type' => ReceiptCalculator::TYPE_CORRECTION,
            'concept' => MemberSettings::fill( 'receipt_concept_correction', [ 'numero' => (string) $receipt->number, 'mes' => self::month_label( (string) $receipt->period ) ] ),
            'amount' => $diff,
            'service_id' => 0,
            'cancelled' => 0,
        ], $id );

        $wpdb->update( self::table(), [ 'outdated' => 0 ], [ 'id' => $id ] );
        if ( null !== $target ) {
            self::recompute( (int) $target->id );
        }
        MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, '', sprintf( __( 'Corrección de %s', SULAYR_TEXTDOMAIN ), self::money( $diff ) ), null !== $target ? sprintf( __( 'en %s', SULAYR_TEXTDOMAIN ), $target->number ) : __( 'pendiente del próximo recibo', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * Correcciones que esperan al próximo recibo del socio.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function pending_corrections( int $user_id ): array {
        global $wpdb;

        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::lines_table() . ' WHERE receipt_id = 0 AND user_id = %d ORDER BY id ASC', $user_id ) );
    }

    /**
     * Corrección pendiente a favor del socio: la pasa a su monedero (decisión 8).
     *
     * @param   int $line_id Línea pendiente.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function correction_to_wallet( int $line_id ) {
        global $wpdb;

        $line = self::pending_line( $line_id );
        if ( null === $line || (float) $line->amount >= 0 ) {
            return new \WP_Error( 'sulayr_correction', __( 'Solo una corrección pendiente a favor del socio se puede pasar al monedero.', SULAYR_TEXTDOMAIN ) );
        }

        $source = self::get( (int) $line->source_receipt_id );
        $number = null !== $source ? (string) $source->number : '';
        $result = WalletLedger::add( (int) $line->user_id, abs( (float) $line->amount ), MemberSettings::fill( 'wallet_concept_correction', [ 'numero' => $number ] ), current_time( 'Y-m-d' ), [ WalletLedger::META_RECEIPT => (int) $line->source_receipt_id ] );
        if ( is_wp_error( $result ) ) {
            return $result;
        }

        $wpdb->delete( self::lines_table(), [ 'id' => $line_id ] );
        MemberLog::record( (int) $line->user_id, MemberLog::AREA_RECEIPTS, $number, (string) $line->concept . ' ' . self::money( (float) $line->amount ), __( 'Pasada al monedero', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * Descarta una corrección pendiente.
     *
     * @param   int    $line_id Línea.
     * @param   string $reason  Motivo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function discard_correction( int $line_id, string $reason ) {
        global $wpdb;

        $line = self::pending_line( $line_id );
        if ( null === $line ) {
            return new \WP_Error( 'sulayr_correction', __( 'Corrección no encontrada.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === trim( $reason ) ) {
            return new \WP_Error( 'sulayr_reason', __( 'Escribe el motivo.', SULAYR_TEXTDOMAIN ) );
        }

        $wpdb->delete( self::lines_table(), [ 'id' => $line_id ] );
        MemberLog::record( (int) $line->user_id, MemberLog::AREA_RECEIPTS, __( 'Corrección pendiente', SULAYR_TEXTDOMAIN ), (string) $line->concept . ' ' . self::money( (float) $line->amount ), __( 'Descartada', SULAYR_TEXTDOMAIN ), $reason );

        return true;
    }

    /* ------------------------------------------------------------------
     * Edición
     * ------------------------------------------------------------------ */

    /**
     * Línea manual (D45).
     *
     * @param   int    $id      Recibo.
     * @param   string $concept Concepto.
     * @param   float  $amount  Importe (positivo o negativo).
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_manual_line( int $id, string $concept, float $amount ) {
        $receipt = self::get( $id );
        $concept = trim( $concept );
        if ( null === $receipt || false === self::is_editable( $receipt ) ) {
            return new \WP_Error( 'sulayr_receipt_locked', __( 'Este recibo está pagado o anulado y no se puede cambiar.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === $concept || 0.0 === round( $amount, 2 ) ) {
            return new \WP_Error( 'sulayr_line', __( 'Escribe el concepto y un importe distinto de 0.', SULAYR_TEXTDOMAIN ) );
        }
        if ( round( (float) $receipt->total + $amount, 2 ) < self::paid( $id ) ) {
            return new \WP_Error( 'sulayr_receipt_overpaid', __( 'El total quedaría por debajo de lo ya pagado.', SULAYR_TEXTDOMAIN ) );
        }

        self::insert_line( $id, (int) $receipt->user_id, [ 'type' => ReceiptCalculator::TYPE_MANUAL, 'concept' => $concept, 'amount' => $amount, 'service_id' => 0, 'cancelled' => 0 ] );
        self::recompute( $id );
        MemberLog::record( (int) $receipt->user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, '', sprintf( '%s %s', $concept, self::money( $amount ) ), __( 'Línea manual', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * Borra una línea manual o de penalización de un recibo editable.
     *
     * @param   int $line_id Línea.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function delete_line( int $line_id ) {
        global $wpdb;

        $line = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::lines_table() . ' WHERE id = %d', $line_id ) );
        $receipt = null !== $line ? self::get( (int) $line->receipt_id ) : null;
        if ( null === $receipt || false === self::is_editable( $receipt ) || false === in_array( (string) $line->type, [ ReceiptCalculator::TYPE_MANUAL, ReceiptCalculator::TYPE_PENALTY ], true ) ) {
            return new \WP_Error( 'sulayr_line', __( 'Esa línea no se puede borrar.', SULAYR_TEXTDOMAIN ) );
        }
        if ( round( (float) $receipt->total - (float) $line->amount, 2 ) < self::paid( (int) $receipt->id ) ) {
            return new \WP_Error( 'sulayr_receipt_overpaid', __( 'El total quedaría por debajo de lo ya pagado.', SULAYR_TEXTDOMAIN ) );
        }

        $wpdb->delete( self::lines_table(), [ 'id' => $line_id ] );
        self::recompute( (int) $receipt->id );
        MemberLog::record( (int) $receipt->user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, sprintf( '%s %s', $line->concept, self::money( (float) $line->amount ) ), __( 'Línea borrada', SULAYR_TEXTDOMAIN ) );

        return true;
    }

    /**
     * Anula un recibo sin pagos (D42). Su número no se reutiliza.
     *
     * @param   int    $id     Recibo.
     * @param   string $reason Motivo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function annul( int $id, string $reason ) {
        global $wpdb;

        $reason = trim( $reason );
        $receipt = self::get( $id );
        if ( null === $receipt || self::STATUS_ANNULLED === $receipt->status || self::paid( $id ) > 0 ) {
            return new \WP_Error( 'sulayr_receipt_annul', __( 'Solo se puede anular un recibo sin pagos.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === $reason ) {
            return new \WP_Error( 'sulayr_reason', __( 'Escribe el motivo.', SULAYR_TEXTDOMAIN ) );
        }

        // Las correcciones que había recibido vuelven a quedar pendientes.
        $wpdb->query( $wpdb->prepare( 'UPDATE ' . self::lines_table() . ' SET receipt_id = 0 WHERE receipt_id = %d AND type = %s', $id, ReceiptCalculator::TYPE_CORRECTION ) );
        $wpdb->update( self::table(), [ 'status' => self::STATUS_ANNULLED, 'note' => substr( $reason, 0, 255 ), 'outdated' => 0 ], [ 'id' => $id ] );
        MemberLog::record( (int) $receipt->user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, self::status_label( (string) $receipt->status ), self::status_label( self::STATUS_ANNULLED ), $reason );

        return true;
    }

    /**
     * Penalización a los recibos pendientes y parciales de un mes que no la
     * tengan (D46, 8.5). Solo desde el día configurado.
     *
     * @param   string $period AAAA-MM.
     * @return 	int|\WP_Error Recibos penalizados.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function apply_penalties( string $period ) {
        global $wpdb;

        if ( current_time( 'Y-m-d' ) < self::limit_date( $period ) ) {
            return new \WP_Error( 'sulayr_penalty_day', sprintf( __( 'La penalización se puede aplicar desde el %s.', SULAYR_TEXTDOMAIN ), MemberDates::show( self::limit_date( $period ) ) ) );
        }
        $amount = round( (float) MemberSettings::get( 'penalty_amount' ), 2 );
        if ( $amount <= 0 ) {
            return new \WP_Error( 'sulayr_penalty_amount', __( 'El importe de la penalización es 0.', SULAYR_TEXTDOMAIN ) );
        }

        $count = 0;
        foreach ( self::for_period( $period ) as $receipt ) {
            if ( false === in_array( (string) $receipt->status, [ self::STATUS_PENDING, self::STATUS_PARTIAL ], true ) ) {
                continue;
            }
            $has = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::lines_table() . ' WHERE receipt_id = %d AND type = %s', (int) $receipt->id, ReceiptCalculator::TYPE_PENALTY ) );
            if ( $has > 0 ) {
                continue;
            }
            self::insert_line( (int) $receipt->id, (int) $receipt->user_id, [ 'type' => ReceiptCalculator::TYPE_PENALTY, 'concept' => MemberSettings::fill( 'receipt_concept_penalty' ), 'amount' => $amount, 'service_id' => 0, 'cancelled' => 0 ] );
            self::recompute( (int) $receipt->id );
            MemberLog::record( (int) $receipt->user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, '', sprintf( __( 'Penalización %s', SULAYR_TEXTDOMAIN ), self::money( $amount ) ) );
            $count++;
        }

        return $count;
    }

    /* ------------------------------------------------------------------
     * Pagos
     * ------------------------------------------------------------------ */

    /**
     * Apunta un pago (D41). Con el monedero, crea el cargo si hay saldo (D47).
     *
     * @param   int    $id     Recibo.
     * @param   float  $amount Importe.
     * @param   string $date   Fecha Y-m-d.
     * @param   string $method Medio (clave de payment_methods()).
     * @param   string $note   Comentario.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add_payment( int $id, float $amount, string $date, string $method, string $note = '' ) {
        global $wpdb;

        $amount = round( $amount, 2 );
        $receipt = self::get( $id );
        if ( null === $receipt || self::STATUS_ANNULLED === $receipt->status ) {
            return new \WP_Error( 'sulayr_payment', __( 'Recibo no encontrado o anulado.', SULAYR_TEXTDOMAIN ) );
        }
        if ( $amount <= 0 || $amount > self::outstanding( $receipt ) ) {
            return new \WP_Error( 'sulayr_payment_amount', sprintf( __( 'El importe tiene que ser mayor que 0 y no más de lo pendiente (%s).', SULAYR_TEXTDOMAIN ), self::money( self::outstanding( $receipt ) ) ) );
        }
        if ( false === isset( self::payment_methods()[ $method ] ) ) {
            return new \WP_Error( 'sulayr_payment_method', __( 'Elige el medio de pago.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === MemberDates::acf_to_iso( $date ) ) {
            return new \WP_Error( 'sulayr_payment_date', __( 'Fecha de pago no válida.', SULAYR_TEXTDOMAIN ) );
        }

        $user_id = (int) $receipt->user_id;
        $wallet_post = 0;
        if ( self::METHOD_WALLET === $method ) {
            $balance = WalletLedger::balance( $user_id );
            if ( $balance < $amount ) {
                return new \WP_Error( 'sulayr_payment_wallet', sprintf( __( 'Saldo insuficiente en el monedero (%s).', SULAYR_TEXTDOMAIN ), self::money( $balance ) ) );
            }
            $wallet_post = WalletLedger::add( $user_id, -$amount, MemberSettings::fill( 'wallet_concept_receipt', [ 'numero' => (string) $receipt->number, 'mes' => self::month_label( (string) $receipt->period ) ] ), $date, [ WalletLedger::META_RECEIPT => $id ] );
            if ( is_wp_error( $wallet_post ) ) {
                return $wallet_post;
            }
        }

        $wpdb->insert( self::payments_table(), [
            'receipt_id' => $id,
            'amount' => $amount,
            'paid_on' => $date,
            'method' => $method,
            'wallet_post_id' => (int) $wallet_post,
            'note' => substr( trim( $note ), 0, 255 ),
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );
        self::recompute( $id );
        MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, '', sprintf( __( 'Pago de %1$s (%2$s, %3$s)', SULAYR_TEXTDOMAIN ), self::money( $amount ), self::method_label( $method ), MemberDates::show( $date ) ), $note );

        return true;
    }

    /**
     * Borra un pago apuntado por error (decisión 10). Si era con monedero, el
     * cargo no se borra: se apunta la devolución.
     *
     * @param   int    $payment_id Pago.
     * @param   string $reason     Motivo.
     * @return 	true|\WP_Error
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function delete_payment( int $payment_id, string $reason ) {
        global $wpdb;

        $payment = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::payments_table() . ' WHERE id = %d', $payment_id ) );
        $receipt = null !== $payment ? self::get( (int) $payment->receipt_id ) : null;
        if ( null === $receipt ) {
            return new \WP_Error( 'sulayr_payment', __( 'Pago no encontrado.', SULAYR_TEXTDOMAIN ) );
        }
        if ( '' === trim( $reason ) ) {
            return new \WP_Error( 'sulayr_reason', __( 'Escribe el motivo.', SULAYR_TEXTDOMAIN ) );
        }

        $user_id = (int) $receipt->user_id;
        if ( (int) $payment->wallet_post_id > 0 ) {
            $refund = WalletLedger::add( $user_id, (float) $payment->amount, MemberSettings::fill( 'wallet_concept_refund', [ 'numero' => (string) $receipt->number, 'mes' => self::month_label( (string) $receipt->period ) ] ), current_time( 'Y-m-d' ), [ WalletLedger::META_RECEIPT => (int) $receipt->id ] );
            if ( is_wp_error( $refund ) ) {
                return $refund;
            }
        }

        $wpdb->delete( self::payments_table(), [ 'id' => $payment_id ] );
        self::recompute( (int) $receipt->id );
        MemberLog::record( $user_id, MemberLog::AREA_RECEIPTS, (string) $receipt->number, sprintf( __( 'Pago de %1$s (%2$s, %3$s)', SULAYR_TEXTDOMAIN ), self::money( (float) $payment->amount ), self::method_label( (string) $payment->method ), MemberDates::show( (string) $payment->paid_on ) ), __( 'Pago borrado', SULAYR_TEXTDOMAIN ), $reason );

        return true;
    }

    /* ------------------------------------------------------------------
     * Utilidades
     * ------------------------------------------------------------------ */

    /**
     * Importe para mostrar: «57,10 €».
     *
     * @param   float $amount Importe.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function money( float $amount ): string {
        return number_format( $amount, 2, ',', '.' ) . ' €';
    }

    /**
     * Total y estado según líneas y pagos (D42, D50).
     *
     * @param   int $id Recibo.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function recompute( int $id ): void {
        global $wpdb;

        $receipt = self::get( $id );
        if ( null === $receipt ) {
            return;
        }

        $total = ReceiptCalculator::sum( self::lines( $id ) );
        $paid = self::paid( $id );
        if ( self::STATUS_ANNULLED === $receipt->status ) {
            $status = self::STATUS_ANNULLED;
        } elseif ( $paid > 0 && $paid >= $total ) {
            $status = self::STATUS_PAID;
        } elseif ( $paid > 0 ) {
            $status = self::STATUS_PARTIAL;
        } elseif ( $total <= 0 ) {
            $status = self::STATUS_NOT_APPLICABLE;
        } else {
            $status = self::STATUS_PENDING;
        }

        $wpdb->update( self::table(), [ 'total' => $total, 'status' => $status ], [ 'id' => $id ] );
    }

    /**
     * Inserta el recibo con el siguiente número del año del mes (D44).
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	string|null Número, o null si ya existía el recibo.
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function insert_receipt( int $user_id, string $period ): ?string {
        global $wpdb;

        $table = self::table();
        $prefix = 'R-' . substr( $period, 0, 4 ) . '-';
        $wpdb->query( "SELECT GET_LOCK('sulayr_receipt_number', 10)" );
        try {
            $max = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MAX( CAST( SUBSTRING( number, 8 ) AS UNSIGNED ) ) FROM {$table} WHERE number LIKE %s", $wpdb->esc_like( $prefix ) . '%' ) );
            $number = $prefix . sprintf( '%04d', $max + 1 );
            $ok = $wpdb->insert( $table, [
                'number' => $number,
                'user_id' => $user_id,
                'period' => $period,
                'total' => 0,
                'status' => self::STATUS_PENDING,
                'outdated' => 0,
                'generated_by' => get_current_user_id(),
                'generated_at' => current_time( 'mysql' ),
            ] );
        } finally {
            $wpdb->query( "SELECT RELEASE_LOCK('sulayr_receipt_number')" );
        }

        return false !== $ok ? $number : null;
    }

    /**
     * Inserta una línea.
     *
     * @param   int   $receipt_id Recibo (0 = corrección pendiente).
     * @param   int   $user_id    Socio.
     * @param   array $line       Línea.
     * @param   int   $source     Recibo que corrige.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function insert_line( int $receipt_id, int $user_id, array $line, int $source = 0 ): void {
        global $wpdb;

        $wpdb->insert( self::lines_table(), [
            'receipt_id' => $receipt_id,
            'user_id' => $user_id,
            'source_receipt_id' => $source,
            'type' => (string) $line['type'],
            'concept' => substr( (string) $line['concept'], 0, 255 ),
            'amount' => round( (float) $line['amount'], 2 ),
            'service_id' => (int) $line['service_id'],
            'cancelled' => (int) $line['cancelled'],
            'created_by' => get_current_user_id(),
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Corrección pendiente por ID.
     *
     * @param   int $line_id Línea.
     * @return 	object|null
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function pending_line( int $line_id ): ?object {
        global $wpdb;

        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::lines_table() . ' WHERE id = %d AND receipt_id = 0', $line_id ) );
    }

    /**
     * Aviso por email del recibo (D52), solo si está activado en Ajustes.
     *
     * @param   object $receipt Recibo.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function maybe_email( object $receipt ): void {
        if ( empty( MemberSettings::get( 'receipt_email_enabled' ) ) || self::STATUS_PENDING !== $receipt->status ) {
            return;
        }

        $user_id = (int) $receipt->user_id;
        $user = get_userdata( $user_id );
        if ( false === $user ) {
            return;
        }

        MemberMailer::send( 'receipt', array_filter( [ $user->user_email, (string) get_user_meta( $user_id, CustomFields::MEMBER_GUARDIAN . '_email', true ) ] ), [
            'nombre' => (string) get_user_meta( $user_id, 'first_name', true ),
            'nombre_completo' => HelperService::get_user_full_name( $user_id ) ?: $user->display_name,
            'mes' => self::month_label( (string) $receipt->period ),
            'numero' => (string) $receipt->number,
            'total' => self::money( (float) $receipt->total ),
            'enlace_mis_cuotas_html' => MemberMailer::button( FeesAccount::url(), __( 'Ver mis cuotas', SULAYR_TEXTDOMAIN ) ),
        ], true );
    }

    /**
     * Socios con algún recibo.
     *
     * @return 	int[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function user_ids_with_receipts(): array {
        global $wpdb;

        if ( 4 > (int) get_option( MembersSchema::VERSION_OPTION ) ) {
            return [];
        }

        return array_map( 'intval', $wpdb->get_col( 'SELECT DISTINCT user_id FROM ' . self::table() ) );
    }

    /** Tabla de recibos. */
    public static function table(): string {
        return MembersSchema::table( MembersSchema::RECEIPTS );
    }

    /** Tabla de líneas. */
    public static function lines_table(): string {
        return MembersSchema::table( MembersSchema::RECEIPT_LINES );
    }

    /** Tabla de pagos. */
    public static function payments_table(): string {
        return MembersSchema::table( MembersSchema::RECEIPT_PAYMENTS );
    }
}
