<?php

namespace SulayrReservas\Swimmers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Límite semanal de un nadador. Único sitio donde se definen el rango y el valor por defecto.
 */
class WeeklyLimit {

	const MIN            = 1;
	const MAX            = 5;
	const DEFAULT_LIMIT  = 3;

	/**
	 * @param mixed $value Valor ya almacenado o recibido.
	 * @return bool
	 */
	public static function isValid( $value ) {
		if ( is_bool( $value ) || is_array( $value ) || is_object( $value ) || null === $value ) {
			return false;
		}

		return 1 === preg_match( '/^[1-5]$/', (string) $value );
	}

	/**
	 * @param mixed $value Valor de la ficha. Si no es válido, el defecto.
	 * @return int
	 */
	public static function normalize( $value ) {
		return self::isValid( $value ) ? (int) $value : self::DEFAULT_LIMIT;
	}
}
