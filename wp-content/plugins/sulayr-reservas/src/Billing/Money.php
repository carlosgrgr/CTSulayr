<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Conversión entre céntimos y texto en euros.
 */
class Money {

	/**
	 * @param int $cents Céntimos.
	 * @return string Por ejemplo «11,50 €».
	 */
	public static function format( $cents ) {
		return self::plain( $cents ) . ' €';
	}

	/**
	 * @param int $cents Céntimos.
	 * @return string Por ejemplo «11,50», sin símbolo. Sirve para los campos del formulario.
	 */
	public static function plain( $cents ) {
		$cents = (int) $cents;
		$sign  = $cents < 0 ? '-' : '';

		return $sign . number_format( abs( $cents ) / 100, 2, ',', '.' );
	}

	/**
	 * Lee un importe escrito por una persona: número ≥ 0 con dos decimales como máximo.
	 * Acepta coma o punto como separador decimal.
	 *
	 * @param string $text Texto del formulario.
	 * @return int|null Céntimos, o null si no es válido.
	 */
	public static function parse( $text ) {
		$text = trim( (string) $text );
		if ( ! preg_match( '/^(\d{1,7})(?:[.,](\d{1,2}))?$/', $text, $parts ) ) {
			return null;
		}

		$decimals = isset( $parts[2] ) ? str_pad( $parts[2], 2, '0' ) : '00';

		return (int) $parts[1] * 100 + (int) $decimals;
	}
}
