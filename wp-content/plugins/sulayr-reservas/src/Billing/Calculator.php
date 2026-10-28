<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cuentas de la facturación (spec, secciones 4.2 y 6.1). Sin acceso a base de datos.
 *
 * Todo va en céntimos enteros, así que no hay redondeos.
 */
class Calculator {

	/**
	 * Coste mensual del sistema de reservas.
	 *
	 * @param Tariff $tariff       Tarifa vigente al generar.
	 * @param int    $users        Usuarios facturables.
	 * @param bool   $has_sessions El mes tiene al menos una sesión habilitada.
	 * @return int Céntimos.
	 */
	public static function cost( Tariff $tariff, $users, $has_sessions ) {
		if ( ! $has_sessions ) {
			return $tariff->baseFee();
		}

		$extra = max( 0, (int) $users - $tariff->includedUsers() );

		return min( $tariff->baseFee() + $extra * $tariff->extraPrice(), $tariff->maxFee() );
	}

	/**
	 * Pago del Sulayr a CN Nazarí por sus deportistas.
	 *
	 * @param Tariff $tariff   Tarifa vigente al generar.
	 * @param int    $athletes Deportistas Sulayr.
	 * @return int|null Céntimos, o null si no hay importe por deportista.
	 */
	public static function athletesPayment( Tariff $tariff, $athletes ) {
		if ( null === $tariff->athleteAmount() ) {
			return null;
		}

		return (int) $athletes * $tariff->athleteAmount();
	}

	/**
	 * Importe neto que el Sulayr transfiere. Negativo si CN Nazarí debe al Sulayr.
	 *
	 * @param int|null $payment Pago por deportistas.
	 * @param int      $cost    Coste del sistema.
	 * @return int|null Céntimos, o null si la liquidación está pendiente.
	 */
	public static function net( $payment, $cost ) {
		if ( null === $payment ) {
			return null;
		}

		return (int) $payment - (int) $cost;
	}
}
