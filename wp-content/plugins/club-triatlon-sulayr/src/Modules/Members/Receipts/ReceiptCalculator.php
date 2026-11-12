<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;
use ClubTriatlonSulayr\Modules\Members\Join\JoinRequests;
use ClubTriatlonSulayr\Modules\Members\Lifecycle\MemberChanges;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberTypes;
use ClubTriatlonSulayr\Modules\Members\Subsidies;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Líneas automáticas del recibo de un socio y mes (spec 8.2).
 *
 * Reglas (D24, D48, D50 y decisiones del 11 de noviembre de 2026):
 * - Hay recibo si el socio está activo algún día del mes.
 * - Tipo de socio: el del día 1 para todo el mes (MemberTypes::for_month).
 * - Precio: el vigente el día 1 del mes para ese tipo, o el general (4.3).
 * - Un servicio vigente el día 1 se cobra entero, aunque se quite a mitad de
 *   mes. Uno que empieza después del día 1 se prorratea por los días que
 *   quedan (incluido el de inicio) si es prorrateable; si no, entero. Si el
 *   mismo servicio estaba antes en el mes con otra opción, ya está cobrado.
 * - Precio del tipo más bajo que el general: línea al precio general y
 *   «Bonificación <tipo>» con la diferencia.
 * - Congelación inmediata (D30): los servicios «se anula al congelar» del mes
 *   quedan anulados (no suman).
 * - Matrícula aprobada si la fecha de alta es de este mes.
 * - Subvenciones vigentes en el mes, como líneas negativas.
 *
 * Las líneas manuales, de penalización y de corrección no salen de aquí: las
 * conserva el recibo.
 */
class ReceiptCalculator {

    public const TYPE_BASE = 'cuota';
    public const TYPE_SERVICE = 'servicio';
    public const TYPE_PRORATED = 'prorrateo';
    public const TYPE_BONUS = 'bonificacion';
    public const TYPE_ENROLLMENT = 'matricula';
    public const TYPE_SUBSIDY = 'subvencion';
    public const TYPE_PENALTY = 'penalizacion';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_CORRECTION = 'correccion';

    /** Tipos que calcula esta clase; el resto los añade un gestor o el sistema. */
    public const AUTO_TYPES = [ self::TYPE_BASE, self::TYPE_SERVICE, self::TYPE_PRORATED, self::TYPE_BONUS, self::TYPE_ENROLLMENT, self::TYPE_SUBSIDY ];

    /**
     * Líneas del recibo, o null si ese mes no le corresponde recibo.
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	array<int, array{type: string, concept: string, amount: float, service_id: int, cancelled: int}>|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function lines( int $user_id, string $period ): ?array {
        $first = $period . '-01';
        $month = new \DateTimeImmutable( $first );
        $last = $month->format( 'Y-m-t' );
        $days_in_month = (int) $month->format( 't' );

        if ( false === self::active_in( $user_id, $first, $last ) ) {
            return null;
        }

        $type = MemberTypes::for_month( $user_id, $period );
        if ( '' === $type ) {
            $type = ServiceCatalog::GENERAL;
        }
        $type_labels = MemberSettings::member_types();
        $cancelled_services = self::cancelled_on_freeze( $user_id, $period );

        // Servicios del mes, agrupados por servicio.
        $by_service = [];
        foreach ( self::services_in( $user_id, $first, $last ) as $row ) {
            $by_service[ (int) $row->service_id ][] = $row;
        }

        $lines = [];
        $service_net = [];
        foreach ( $by_service as $service_id => $rows ) {
            $service = ServiceCatalog::get( $service_id );
            if ( null === $service ) {
                continue;
            }
            $price = ServiceCatalog::price_for( $service, $type, $first );
            if ( null === $price ) {
                continue;
            }

            $start = $last;
            $latest = $rows[0];
            foreach ( $rows as $row ) {
                $row_start = (string) $row->start_date < $first ? $first : (string) $row->start_date;
                if ( $row_start < $start ) {
                    $start = $row_start;
                }
                if ( (string) $row->start_date >= (string) $latest->start_date ) {
                    $latest = $row;
                }
            }

            $name = MemberServices::label( $service_id, (string) $latest->option_key );
            $factor = 1.0;
            $concept = MemberSettings::fill( 'receipt_concept_service', [ 'servicio' => $name ] );
            $line_type = $service->is_base ? self::TYPE_BASE : self::TYPE_SERVICE;
            if ( $start > $first && $service->prorated ) {
                $days = (int) ( new \DateTimeImmutable( $start ) )->diff( new \DateTimeImmutable( $last ) )->days + 1;
                $factor = $days / $days_in_month;
                $concept = MemberSettings::fill( 'receipt_concept_prorated', [ 'servicio' => $name, 'dias' => $days, 'dias_mes' => $days_in_month ] );
                $line_type = self::TYPE_PRORATED;
            }

            $cancelled = isset( $cancelled_services[ $service_id ] ) ? 1 : 0;
            $amount = round( $price['amount'] * $factor, 2 );
            $general = null !== $price['general'] ? round( (float) $price['general'] * $factor, 2 ) : null;

            if ( ServiceCatalog::GENERAL !== $price['member_type'] && null !== $general && $amount < $general ) {
                $lines[] = self::line( $line_type, $concept, $general, $service_id, $cancelled );
                $lines[] = self::line( self::TYPE_BONUS, MemberSettings::fill( 'receipt_concept_bonus', [ 'tipo' => $type_labels[ $type ] ?? $type, 'servicio' => $name ] ), round( $amount - $general, 2 ), $service_id, $cancelled );
            } else {
                $lines[] = self::line( $line_type, $concept, $amount, $service_id, $cancelled );
            }

            if ( 0 === $cancelled ) {
                $service_net[ $service_id ] = $amount;
            }
        }

        // Matrícula del alta o reingreso de este mes (D5).
        foreach ( self::enrollment_fees( $user_id, $first, $last ) as $fee ) {
            $lines[] = self::line( self::TYPE_ENROLLMENT, MemberSettings::fill( 'receipt_concept_enrollment' ), $fee, 0, 0 );
        }

        // Subvenciones (D35).
        foreach ( Subsidies::in_range( $user_id, $first, $last ) as $subsidy ) {
            $ids = Subsidies::service_ids( $subsidy );
            $net = 0.0;
            foreach ( $service_net as $service_id => $amount ) {
                if ( empty( $ids ) || in_array( $service_id, $ids, true ) ) {
                    $net += $amount;
                }
            }
            if ( $net <= 0 ) {
                continue;
            }
            $discount = Subsidies::MODE_PERCENT === $subsidy->mode ? round( $net * (float) $subsidy->value / 100, 2 ) : min( (float) $subsidy->value, round( $net, 2 ) );
            if ( $discount > 0 ) {
                $lines[] = self::line( self::TYPE_SUBSIDY, MemberSettings::fill( 'receipt_concept_subsidy', [ 'motivo' => (string) $subsidy->reason ] ), -$discount, 0, 0 );
            }
        }

        return $lines;
    }

    /**
     * Firma de unas líneas automáticas para comparar (sin el texto, que es configurable).
     *
     * @param   array $lines Líneas (arrays u objetos).
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function signature( array $lines ): array {
        $out = [];
        foreach ( $lines as $line ) {
            $line = (array) $line;
            if ( false === in_array( (string) $line['type'], self::AUTO_TYPES, true ) ) {
                continue;
            }
            $out[] = sprintf( '%s|%d|%.2f|%d', $line['type'], (int) $line['service_id'], (float) $line['amount'], (int) $line['cancelled'] );
        }
        sort( $out );

        return $out;
    }

    /**
     * Suma de líneas no anuladas.
     *
     * @param   array $lines   Líneas.
     * @param   bool  $only_auto Solo las automáticas.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sum( array $lines, bool $only_auto = false ): float {
        $total = 0.0;
        foreach ( $lines as $line ) {
            $line = (array) $line;
            if ( ! empty( $line['cancelled'] ) || ( $only_auto && false === in_array( (string) $line['type'], self::AUTO_TYPES, true ) ) ) {
                continue;
            }
            $total += (float) $line['amount'];
        }

        return round( $total, 2 );
    }

    /**
     * Socios activos algún día entre dos fechas.
     *
     * @param   string $first Y-m-d.
     * @param   string $last  Y-m-d.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function active_members( string $first, string $last ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table} WHERE status = %s AND ( start_date IS NULL OR start_date <= %s ) AND ( end_date IS NULL OR end_date >= %s )",
            CustomFields::STATUS_ACTIVE,
            $last,
            $first
        ) ) );
    }

    /**
     * Nombre de un tipo de línea.
     *
     * @param   string $type Tipo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function type_label( string $type ): string {
        $labels = [
            self::TYPE_BASE => __( 'Cuota', SULAYR_TEXTDOMAIN ),
            self::TYPE_SERVICE => __( 'Servicio', SULAYR_TEXTDOMAIN ),
            self::TYPE_PRORATED => __( 'Prorrateo', SULAYR_TEXTDOMAIN ),
            self::TYPE_BONUS => __( 'Bonificación', SULAYR_TEXTDOMAIN ),
            self::TYPE_ENROLLMENT => __( 'Matrícula', SULAYR_TEXTDOMAIN ),
            self::TYPE_SUBSIDY => __( 'Subvención', SULAYR_TEXTDOMAIN ),
            self::TYPE_PENALTY => __( 'Penalización', SULAYR_TEXTDOMAIN ),
            self::TYPE_MANUAL => __( 'Manual', SULAYR_TEXTDOMAIN ),
            self::TYPE_CORRECTION => __( 'Corrección', SULAYR_TEXTDOMAIN ),
        ];

        return $labels[ $type ] ?? $type;
    }

    /**
     * True si el socio está activo algún día del intervalo.
     *
     * @param   int    $user_id Socio.
     * @param   string $first   Y-m-d.
     * @param   string $last    Y-m-d.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function active_in( int $user_id, string $first, string $last ): bool {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::STATUS_PERIODS );

        return 0 < (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND status = %s AND ( start_date IS NULL OR start_date <= %s ) AND ( end_date IS NULL OR end_date >= %s )",
            $user_id,
            CustomFields::STATUS_ACTIVE,
            $last,
            $first
        ) );
    }

    /**
     * Filas de servicio vigentes algún día del intervalo.
     *
     * @param   int    $user_id Socio.
     * @param   string $first   Y-m-d.
     * @param   string $last    Y-m-d.
     * @return 	object[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function services_in( int $user_id, string $first, string $last ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::SERVICES );

        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND start_date <= %s AND ( end_date IS NULL OR end_date >= %s ) ORDER BY start_date ASC, id ASC",
            $user_id,
            $last,
            $first
        ) );
    }

    /**
     * Servicios anulados por una congelación inmediata de este mes (D30, C-e).
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	array<int, true>
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function cancelled_on_freeze( int $user_id, string $period ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::REQUESTS );
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT data FROM {$table} WHERE user_id = %d AND type = %s AND status = %s",
            $user_id,
            MemberChanges::TYPE_FREEZE,
            MemberChanges::STATUS_APPROVED
        ) );

        foreach ( $rows as $data ) {
            $fields = json_decode( (string) $data, true );
            if ( is_array( $fields ) && $period === (string) ( $fields['cancel_month'] ?? '' ) ) {
                $out = [];
                foreach ( ServiceCatalog::all() as $service ) {
                    if ( $service->cancel_on_freeze ) {
                        $out[ $service->id ] = true;
                    }
                }
                return $out;
            }
        }

        return [];
    }

    /**
     * Matrículas aprobadas de altas con fecha en el intervalo (D5, 8.2).
     *
     * @param   int    $user_id Socio.
     * @param   string $first   Y-m-d.
     * @param   string $last    Y-m-d.
     * @return 	float[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function enrollment_fees( int $user_id, string $first, string $last ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::REQUESTS );
        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT data FROM {$table} WHERE user_id = %d AND type = %s AND status = %s AND effective_date BETWEEN %s AND %s ORDER BY id ASC",
            $user_id,
            JoinRequests::TYPE_JOIN,
            JoinRequests::STATUS_APPROVED,
            $first,
            $last
        ) );

        $fees = [];
        foreach ( $rows as $data ) {
            $fields = json_decode( (string) $data, true );
            $fee = is_array( $fields ) ? round( (float) ( $fields['approval']['fee'] ?? 0 ), 2 ) : 0.0;
            if ( $fee > 0 ) {
                $fees[] = $fee;
            }
        }

        return $fees;
    }

    /**
     * Línea.
     *
     * @return 	array{type: string, concept: string, amount: float, service_id: int, cancelled: int}
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function line( string $type, string $concept, float $amount, int $service_id, int $cancelled ): array {
        return [
            'type' => $type,
            'concept' => $concept,
            'amount' => round( $amount, 2 ),
            'service_id' => $service_id,
            'cancelled' => $cancelled,
        ];
    }
}
