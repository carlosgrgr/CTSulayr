<?php

namespace SulayrReservas\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Horario fijo del SPEC. Las horas son de pared en la zona del sitio.
 */
class Schedule {

	const CAPACITY = 24;

	/**
	 * Franjas por número de día ISO: 1 lunes … 6 sábado. Viernes y domingo no están.
	 *
	 * @var array<int, array<int, array<int, string>>>
	 */
	private static $hours = array(
		1 => array(
			array( '15:00:00', '16:30:00' ),
			array( '21:00:00', '22:30:00' ),
		),
		2 => array(
			array( '15:00:00', '16:30:00' ),
			array( '21:00:00', '22:30:00' ),
		),
		3 => array(
			array( '15:00:00', '16:30:00' ),
			array( '21:00:00', '22:30:00' ),
		),
		4 => array(
			array( '15:00:00', '16:30:00' ),
			array( '21:00:00', '22:30:00' ),
		),
		6 => array(
			array( '13:00:00', '14:30:00' ),
		),
	);

	/**
	 * @param string            $year_month Mes `YYYY-MM`.
	 * @param \DateTimeZone     $timezone   Zona con la que se cuentan los días.
	 * @return array<int, array<string, string>>
	 */
	public function slotsForMonth( $year_month, \DateTimeZone $timezone ) {
		$cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', $year_month . '-01', $timezone );
		$last   = $cursor->modify( 'last day of this month' );
		$slots  = array();

		for ( $day = $cursor; $day <= $last; $day = $day->modify( '+1 day' ) ) {
			$weekday = (int) $day->format( 'N' );

			if ( ! isset( self::$hours[ $weekday ] ) ) {
				continue;
			}

			foreach ( self::$hours[ $weekday ] as $range ) {
				$slots[] = array(
					'session_date' => $day->format( 'Y-m-d' ),
					'start_time'   => $range[0],
					'end_time'     => $range[1],
				);
			}
		}

		return $slots;
	}
}
