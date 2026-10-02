<?php

namespace SulayrReservas\Sessions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class GenerationResult {

	const CREATED   = 'created';
	const UNCHANGED = 'unchanged';
	const REPAIRED  = 'repaired';
	const REJECTED  = 'rejected';
	const FAILED    = 'failed';

	/** @var string */
	private $status;

	/** @var string */
	private $month;

	/** @var int */
	private $created;

	/** @var string */
	private $message;

	/**
	 * @param string $status  Uno de los estados de la clase.
	 * @param string $month   Mes `YYYY-MM`.
	 * @param int    $created Sesiones insertadas en esta ejecución.
	 * @param string $message Texto para quien invocó la operación.
	 */
	public function __construct( $status, $month, $created, $message ) {
		$this->status  = $status;
		$this->month   = $month;
		$this->created = (int) $created;
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
	public function month() {
		return $this->month;
	}

	/**
	 * @return int
	 */
	public function created() {
		return $this->created;
	}

	/**
	 * @return string
	 */
	public function message() {
		return $this->message;
	}
}
