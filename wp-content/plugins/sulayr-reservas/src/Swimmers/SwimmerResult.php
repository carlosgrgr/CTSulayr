<?php

namespace SulayrReservas\Swimmers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resultado de una operación sobre un nadador.
 */
class SwimmerResult {

	const CREATED      = 'created';
	const REUSED       = 'reused';
	const DEACTIVATED  = 'deactivated';
	const REACTIVATED  = 'reactivated';
	const REMOVED      = 'removed';
	const DUPLICATE    = 'duplicate';
	const INVALID      = 'invalid';
	const UNAUTHORIZED = 'unauthorized';
	const MISSING      = 'missing';
	const FAILED       = 'failed';
	const UPDATED      = 'updated';

	/** @var string */
	private $status;

	/** @var string */
	private $message;

	/** @var int */
	private $user_id;

	/**
	 * @param string $status  Uno de los estados de la clase.
	 * @param string $message Texto para quien invocó la operación.
	 * @param int    $user_id Nadador afectado. Cero si no hay.
	 */
	public function __construct( $status, $message, $user_id = 0 ) {
		$this->status  = $status;
		$this->message = $message;
		$this->user_id = (int) $user_id;
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

	/**
	 * @return int
	 */
	public function userId() {
		return $this->user_id;
	}
}
