<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Textos de un resumen que comparten la página de ajustes y el panel. Devuelve texto sin escapar.
 */
class SummaryFormatter {

	const MISSING_ATHLETE_AMOUNT = 'Importe por deportista sin configurar';

	const STALE_PENDING = 'Este resumen se generó cuando aún no había importe por deportista. El importe ya está configurado:';

	/**
	 * @param string $month `YYYY-MM`.
	 * @return string Por ejemplo «octubre de 2026».
	 */
	public static function month( $month ) {
		$names = array( 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre' );
		if ( ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $month, $parts ) || (int) $parts[2] < 1 || (int) $parts[2] > 12 ) {
			return (string) $month;
		}

		return $names[ (int) $parts[2] - 1 ] . ' de ' . $parts[1];
	}

	/**
	 * El resumen tiene la liquidación pendiente, pero ahora sí hay importe por deportista.
	 *
	 * @param object $summary Fila del resumen.
	 * @param Tariff $current Tarifa actual.
	 * @return bool
	 */
	public static function isStalePending( $summary, Tariff $current ) {
		return null === $summary->net_cents && null !== $current->athleteAmount();
	}

	/**
	 * Usuarios por encima de los incluidos. 0 en un mes sin sesiones, que paga solo la cuota base.
	 *
	 * @param object $summary Fila del resumen.
	 * @return int
	 */
	public static function extraUsers( $summary ) {
		if ( 1 !== (int) $summary->has_sessions ) {
			return 0;
		}

		return max( 0, (int) $summary->billable_users - (int) $summary->included_users );
	}

	/**
	 * @param object $summary Fila del resumen.
	 * @return string
	 */
	public static function cost( $summary ) {
		return Money::format( (int) $summary->cost_cents );
	}

	/**
	 * @param object $summary Fila del resumen.
	 * @return string
	 */
	public static function payment( $summary ) {
		if ( null === $summary->athletes_payment_cents ) {
			return 'Pendiente';
		}

		return Money::format( (int) $summary->athletes_payment_cents );
	}

	/**
	 * Importe neto, el aviso de neto negativo, o «Pendiente» (6.1).
	 *
	 * @param object $summary Fila del resumen.
	 * @return string
	 */
	public static function net( $summary ) {
		if ( null === $summary->net_cents ) {
			return 'Pendiente';
		}

		$net = (int) $summary->net_cents;
		if ( $net < 0 ) {
			return 'CN Nazarí debe al Sulayr ' . Money::format( -$net );
		}

		return Money::format( $net );
	}

	/**
	 * @param object $summary Fila del resumen.
	 * @return string
	 */
	public static function sessions( $summary ) {
		return 1 === (int) $summary->has_sessions ? 'Sí' : 'No';
	}

	/**
	 * @param object $summary Fila del resumen.
	 * @return string Por ejemplo «20/10/2026 09:15 por sistema».
	 */
	public static function generated( $summary ) {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $summary->generated_at, wp_timezone() );
		$when = $date instanceof \DateTimeImmutable ? $date->format( 'd/m/Y H:i' ) : (string) $summary->generated_at;
		$who  = '' !== (string) $summary->generated_by_name ? (string) $summary->generated_by_name : 'sistema';

		return $when . ' por ' . $who;
	}

	/**
	 * Tarifa aplicada en un resumen, en una línea.
	 *
	 * @param Tariff $tariff Tarifa.
	 * @return string
	 */
	public static function tariff( Tariff $tariff ) {
		$text = 'Cuota base ' . Money::format( $tariff->baseFee() )
			. ' con ' . $tariff->includedUsers() . ' usuarios incluidos, '
			. Money::format( $tariff->extraPrice() ) . ' por usuario adicional, máximo '
			. Money::format( $tariff->maxFee() ) . '. Importe por deportista: ';

		$text .= null === $tariff->athleteAmount() ? 'sin configurar cuando se generó.' : Money::format( $tariff->athleteAmount() ) . '.';

		return $text;
	}

	/**
	 * Cuenta del coste y de la liquidación con los números del resumen.
	 *
	 * @param object $summary Fila del resumen.
	 * @return string[] Líneas.
	 */
	public static function formula( $summary ) {
		$tariff = SummaryService::tariffOf( $summary );
		$users  = (int) $summary->billable_users;
		$lines  = array();

		if ( 1 !== (int) $summary->has_sessions ) {
			$lines[] = 'Mes sin sesiones habilitadas: coste = cuota base = ' . Money::format( $tariff->baseFee() ) . '.';
		} else {
			$extra   = max( 0, $users - $tariff->includedUsers() );
			$lines[] = 'Usuarios adicionales = máx(0, ' . $users . ' − ' . $tariff->includedUsers() . ') = ' . $extra . '.';
			$lines[] = 'Coste = mín(' . Money::format( $tariff->baseFee() ) . ' + ' . $extra . ' × ' . Money::format( $tariff->extraPrice() )
				. ', ' . Money::format( $tariff->maxFee() ) . ') = ' . Money::format( (int) $summary->cost_cents ) . '.';
		}

		if ( null === $tariff->athleteAmount() ) {
			$lines[] = 'Liquidación pendiente: cuando se generó este resumen no había importe por deportista.';
		} else {
			$lines[] = 'Pago por deportistas = ' . (int) $summary->club_athletes . ' × ' . Money::format( $tariff->athleteAmount() )
				. ' = ' . self::payment( $summary ) . '.';
			$lines[] = 'Neto = ' . self::payment( $summary ) . ' − ' . self::cost( $summary ) . ' = ' . Money::format( (int) $summary->net_cents ) . '.';
		}

		return $lines;
	}

	/**
	 * Una fila del registro de cambios en texto.
	 *
	 * @param object $change Fila de `sulayr_billing_changes`.
	 * @return array{when: string, who: string, field: string, old: string, new: string}
	 */
	public static function change( $change ) {
		$labels = Tariff::labels();
		$field  = (string) $change->field;
		$date   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $change->changed_at, wp_timezone() );
		$is_init = 0 === (int) $change->user_id && '' === (string) $change->old_value;

		return array(
			'when'  => $date instanceof \DateTimeImmutable ? $date->format( 'd/m/Y H:i' ) : (string) $change->changed_at,
			'who'   => '' !== (string) $change->user_name ? (string) $change->user_name : 'sistema',
			'field' => isset( $labels[ $field ] ) ? $labels[ $field ] : $field,
			'old'   => $is_init ? 'valor inicial' : BillingSettings::displayStored( $field, (string) $change->old_value ),
			'new'   => BillingSettings::displayStored( $field, (string) $change->new_value ),
		);
	}
}
