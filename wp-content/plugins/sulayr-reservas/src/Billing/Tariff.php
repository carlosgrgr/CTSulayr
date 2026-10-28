<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Valores económicos de la facturación. Los importes van en céntimos.
 */
class Tariff {

	const BASE_FEE       = 'base_fee';
	const INCLUDED_USERS = 'included_users';
	const EXTRA_PRICE    = 'extra_price';
	const MAX_FEE        = 'max_fee';
	const ATHLETE_AMOUNT = 'athlete_amount';

	/** @var int */
	private $base_fee;

	/** @var int */
	private $included_users;

	/** @var int */
	private $extra_price;

	/** @var int */
	private $max_fee;

	/** @var int|null */
	private $athlete_amount;

	/**
	 * @param int      $base_fee       Cuota base en céntimos.
	 * @param int      $included_users Usuarios incluidos en la cuota base.
	 * @param int      $extra_price    Precio por usuario adicional en céntimos.
	 * @param int      $max_fee        Importe máximo mensual en céntimos.
	 * @param int|null $athlete_amount Importe por deportista en céntimos, o null si no está configurado.
	 */
	public function __construct( $base_fee, $included_users, $extra_price, $max_fee, $athlete_amount ) {
		$this->base_fee       = (int) $base_fee;
		$this->included_users = (int) $included_users;
		$this->extra_price    = (int) $extra_price;
		$this->max_fee        = (int) $max_fee;
		$this->athlete_amount = null === $athlete_amount ? null : (int) $athlete_amount;
	}

	/**
	 * Tarifa de la sección 4.1 de la spec. El importe por deportista empieza vacío.
	 *
	 * @return self
	 */
	public static function initial() {
		return new self( 900, 25, 50, 4900, null );
	}

	/**
	 * @param array<string, mixed> $values Valores guardados.
	 * @return self
	 */
	public static function fromArray( array $values ) {
		$initial = self::initial()->toArray();
		$values  = array_merge( $initial, $values );

		return new self(
			$values[ self::BASE_FEE ],
			$values[ self::INCLUDED_USERS ],
			$values[ self::EXTRA_PRICE ],
			$values[ self::MAX_FEE ],
			null === $values[ self::ATHLETE_AMOUNT ] || '' === $values[ self::ATHLETE_AMOUNT ] ? null : $values[ self::ATHLETE_AMOUNT ]
		);
	}

	/**
	 * @return array<string, int|null>
	 */
	public function toArray() {
		return array(
			self::BASE_FEE       => $this->base_fee,
			self::INCLUDED_USERS => $this->included_users,
			self::EXTRA_PRICE    => $this->extra_price,
			self::MAX_FEE        => $this->max_fee,
			self::ATHLETE_AMOUNT => $this->athlete_amount,
		);
	}

	/**
	 * Nombres visibles de cada valor.
	 *
	 * @return array<string, string>
	 */
	public static function labels() {
		return array(
			self::BASE_FEE       => 'Cuota base mensual',
			self::INCLUDED_USERS => 'Usuarios incluidos en la cuota base',
			self::EXTRA_PRICE    => 'Precio por usuario adicional',
			self::MAX_FEE        => 'Importe máximo mensual',
			self::ATHLETE_AMOUNT => 'Importe por deportista',
		);
	}

	/**
	 * @param string $field Clave del valor.
	 * @return bool
	 */
	public static function isMoney( $field ) {
		return self::INCLUDED_USERS !== $field;
	}

	/**
	 * @return int
	 */
	public function baseFee() {
		return $this->base_fee;
	}

	/**
	 * @return int
	 */
	public function includedUsers() {
		return $this->included_users;
	}

	/**
	 * @return int
	 */
	public function extraPrice() {
		return $this->extra_price;
	}

	/**
	 * @return int
	 */
	public function maxFee() {
		return $this->max_fee;
	}

	/**
	 * @return int|null
	 */
	public function athleteAmount() {
		return $this->athlete_amount;
	}
}
