<?php

namespace SulayrReservas\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resultado de cambiar la capacidad o el estado de una sesión.
 */
class SessionUpdateResult {

	const UPDATED      = 'updated';
	const MISSING      = 'missing';
	const INVALID      = 'invalid';
	const UNAUTHORIZED = 'unauthorized';
	const FAILED       = 'failed';

	/** @var string */
	private $status;

	/** @var string */
	private $message;

	/**
	 * @param string $status  Uno de los estados de la clase.
	 * @param string $message Texto para quien invocó la operación.
	 */
	public function __construct( $status, $message ) {
		$this->status  = $status;
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function status() {
		return $this->status;
	}

	/**
	 * @return string
	 */
	public function message() {
		return $this->message;
	}
}
