<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\MemberTypes;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * «Exportar a Excel» de un mes (8.7): CSV con punto y coma, coma decimal y
 * BOM UTF-8, que Excel en español abre directamente.
 *
 * Columnas: nº socio, nombre, tipo, una por concepto (cada servicio, y cada
 * tipo de línea para el resto), total, pagado, pendiente, estado, medios de
 * pago y comentarios generados con lo que pasó ese mes (altas, cambios,
 * congelaciones, bajas).
 */
class ReceiptExport {

    /**
     * Envía el CSV y termina.
     *
     * @param   string $period AAAA-MM.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function stream( string $period ): void {
        $rows = self::rows( $period );

        nocache_headers();
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="recibos-' . $period . '.csv"' );

        $out = fopen( 'php://output', 'w' );
        fwrite( $out, "\xEF\xBB\xBF" );
        foreach ( $rows as $row ) {
            fputcsv( $out, $row, ';' );
        }
        fclose( $out );
        exit;
    }

    /**
     * Filas del CSV, con la cabecera primero.
     *
     * @param   string $period AAAA-MM.
     * @return 	array<int, string[]>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function rows( string $period ): array {
        $types = MemberSettings::member_types();
        $receipts = Receipts::for_period( $period );

        // Columnas de concepto, en el orden en que aparecen.
        $columns = [];
        $data = [];
        foreach ( $receipts as $receipt ) {
            $amounts = [];
            foreach ( Receipts::lines( (int) $receipt->id ) as $line ) {
                if ( (int) $line->cancelled > 0 ) {
                    continue;
                }
                $key = self::column( $line );
                $columns[ $key ] = true;
                $amounts[ $key ] = ( $amounts[ $key ] ?? 0 ) + (float) $line->amount;
            }
            $data[ (int) $receipt->id ] = $amounts;
        }
        $columns = array_keys( $columns );

        $out = [ array_merge(
            [ __( 'Nº socio', SULAYR_TEXTDOMAIN ), __( 'Nombre', SULAYR_TEXTDOMAIN ), __( 'Tipo', SULAYR_TEXTDOMAIN ), __( 'Recibo', SULAYR_TEXTDOMAIN ) ],
            $columns,
            [ __( 'Total', SULAYR_TEXTDOMAIN ), __( 'Pagado', SULAYR_TEXTDOMAIN ), __( 'Pendiente', SULAYR_TEXTDOMAIN ), __( 'Estado', SULAYR_TEXTDOMAIN ), __( 'Medio de pago', SULAYR_TEXTDOMAIN ), __( 'Comentarios', SULAYR_TEXTDOMAIN ) ]
        ) ];

        foreach ( $receipts as $receipt ) {
            $user_id = (int) $receipt->user_id;
            $user = get_userdata( $user_id );
            $type = MemberTypes::for_month( $user_id, $period );
            $methods = array_unique( array_map( function ( $payment ) {
                return Receipts::method_label( (string) $payment->method );
            }, Receipts::payments( (int) $receipt->id ) ) );

            $row = [
                (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true ),
                HelperService::get_user_full_name( $user_id ) ?: ( false !== $user ? $user->display_name : '' ),
                $types[ $type ] ?? $type,
                (string) $receipt->number,
            ];
            foreach ( $columns as $column ) {
                $row[] = isset( $data[ (int) $receipt->id ][ $column ] ) ? self::number( $data[ (int) $receipt->id ][ $column ] ) : '';
            }
            $paid = Receipts::paid( (int) $receipt->id );
            $row[] = self::number( (float) $receipt->total );
            $row[] = self::number( $paid );
            $row[] = self::number( Receipts::outstanding( $receipt ) );
            $row[] = Receipts::status_label( Receipts::display_status( $receipt ) );
            $row[] = implode( ', ', $methods );
            $row[] = implode( '. ', self::comments( $user_id, $period ) );
            $out[] = $row;
        }

        return $out;
    }

    /**
     * Lo que pasó al socio en el mes, sacado del historial.
     *
     * @param   int    $user_id Socio.
     * @param   string $period  AAAA-MM.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function comments( int $user_id, string $period ): array {
        $first = $period . '-01';
        $last = ( new \DateTimeImmutable( $first ) )->format( 'Y-m-t' );
        $in = function ( $date ) use ( $first, $last ): bool {
            return null !== $date && (string) $date >= $first && (string) $date <= $last;
        };
        $out = [];

        foreach ( StatusPeriods::for_user( $user_id ) as $period_row ) {
            if ( $in( $period_row->start_date ) ) {
                $out[] = sprintf( __( '%1$s desde el %2$s', SULAYR_TEXTDOMAIN ), StatusPeriods::label( (string) $period_row->status ), MemberDates::show( (string) $period_row->start_date ) ) . ( '' !== (string) $period_row->reason ? ' (' . $period_row->reason . ')' : '' );
            }
        }
        foreach ( MemberServices::for_user( $user_id ) as $row ) {
            $label = MemberServices::label( (int) $row->service_id, (string) $row->option_key );
            if ( $in( $row->start_date ) && MemberServices::ORIGIN_JOIN !== $row->origin ) {
                $out[] = sprintf( __( 'Añade %1$s desde el %2$s', SULAYR_TEXTDOMAIN ), $label, MemberDates::show( (string) $row->start_date ) );
            }
            if ( $in( $row->end_date ) ) {
                $out[] = sprintf( __( 'Quita %1$s el %2$s', SULAYR_TEXTDOMAIN ), $label, MemberDates::show( MemberDates::add_days( (string) $row->end_date, 1 ) ) );
            }
        }
        $types = MemberSettings::member_types();
        foreach ( MemberTypes::for_user( $user_id ) as $row ) {
            if ( $in( $row->start_date ) ) {
                $out[] = sprintf( __( 'Tipo %1$s desde el %2$s', SULAYR_TEXTDOMAIN ), $types[ $row->member_type ] ?? $row->member_type, MemberDates::show( (string) $row->start_date ) );
            }
        }

        return $out;
    }

    /**
     * Columna de una línea: el nombre del servicio, o el tipo de línea.
     *
     * @param   object $line Línea.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function column( object $line ): string {
        if ( in_array( (string) $line->type, [ ReceiptCalculator::TYPE_BASE, ReceiptCalculator::TYPE_SERVICE, ReceiptCalculator::TYPE_PRORATED ], true ) ) {
            $service = ServiceCatalog::get( (int) $line->service_id );
            return null !== $service ? $service->title : (string) $line->concept;
        }

        return ReceiptCalculator::type_label( (string) $line->type );
    }

    /**
     * Número con coma decimal.
     *
     * @param   float $value Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function number( float $value ): string {
        return number_format( $value, 2, ',', '' );
    }
}
