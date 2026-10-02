<?php

namespace SulayrReservas\Bookings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resultado de crear o cancelar una reserva.
 */
class BookingResult {

	const CREATED          = 'created';
	const CANCELLED        = 'cancelled';
	const SESSION_MISSING  = 'session_missing';
	const SESSION_DISABLED = 'session_disabled';
	const SESSION_STARTED  = 'session_started';
	const FULL             = 'full';
	const DUPLICATE        = 'duplicate';
	const WEEKLY_LIMIT     = 'weekly_limit';
	const UNAUTHORIZED     = 'unauthorized';
	const SWIMMER_INACTIVE = 'swimmer_inactive';
	const BOOKING_MISSING  = 'booking_missing';
	const TOO_LATE         = 'too_late';
	const FAILED           = 'failed';

	/** @var string */
	private $status;

	/** @var string */
	private $message;

	/** @var int */
	private $booking_id;

	/**
	 * @param string $status     Uno de los estados de la clase.
	 * @param string $message    Texto para quien invocó la operación.
	 * @param int    $booking_id Reserva creada o cancelada. Cero si no hay.
	 */
	public function __construct( $status, $message, $booking_id = 0 ) {
		$this->status     = $status;
		$this->message    = $message;
		$this->booking_id = (int) $booking_id;
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
	public function bookingId() {
		return $this->booking_id;
	}
}
