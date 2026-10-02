<?php

namespace SulayrReservas\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mes que el botón puede ofrecer en una fecha.
 *
 * El día 26 abre el mes siguiente solo si el mes en curso ya está generado.
 * Si no lo está, sigue ofrecido hasta el día 26 inclusive y el 27 pasa al siguiente.
 */
class GenerationWindow {

	/**
	 * @param \DateTimeImmutable $now                     Fecha en la zona del sitio.
	 * @param bool               $current_month_generated Si el mes de `$now` ya tiene registro.
	 * @return string|null Mes `YYYY-MM`, o null si no hay nada que ofrecer.
	 */
	public function offeredMonth( \DateTimeImmutable $now, $current_month_generated ) {
		$day     = (int) $now->format( 'j' );
		$current = $now->format( 'Y-m' );
		$next    = $now->modify( 'first day of next month' )->format( 'Y-m' );

		if ( ! $current_month_generated ) {
			if ( $day <= 26 ) {
				return $current;
			}

			return $next;
		}

		if ( $day >= 26 ) {
			return $next;
		}

		return null;
	}
}
