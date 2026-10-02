<?php

namespace SulayrReservas\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resultado de sincronizar a un deportista.
 */
class SyncResult {

	const OK     = 'ok';
	const FAILED = 'failed';

	const CREATED     = 'created';
	const UPDATED     = 'updated';
	const ACTIVATED   = 'activated';
	const DEACTIVATED = 'deactivated';
	const UNCHANGED   = 'unchanged';

	/** @var string */
	private $result;

	/** @var string */
	private $action;

	/** @var string */
	private $message;

	/**
	 * @param string $result  ok o failed.
	 * @param string $action  Acción registrada.
	 * @param string $message Texto para el administrador.
	 */
	public function __construct( $result, $action, $message ) {
		$this->result  = $result;
		$this->action  = $action;
		$this->message = $message;
	}

	/**
	 * @return string
	 */
	public function result() {
		return $this->result;
	}

	/**
	 * @return string
	 */
	public function action() {
		return $this->action;
	}

	/**
	 * @return string
	 */
	public function message() {
		return $this->message;
	}
}
