<?php

namespace SulayrReservas\Bookings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lectura y escritura de reservas en el blog actual.
 *
 * Quien llama debe haber cambiado ya al sitio de reservas y, si la operación
 * modifica datos, tener una transacción abierta.
 */
class BookingRepository {

	/**
	 * Bloquea la ficha del nadador para serializar su límite semanal.
	 *
	 * @param int $user_id Usuario de la reserva.
	 * @return object|null
	 */
	public function lockSwimmer( $user_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT user_id, active, first_name, last_name, phone, email FROM ' . $this->swimmers() . ' WHERE user_id = %d FOR UPDATE',
				$user_id
			)
		);
	}

	/**
	 * Bloquea la sesión para serializar su aforo.
	 *
	 * @param int $session_id Sesión.
	 * @return object|null
	 */
	public function lockSession( $session_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT id, session_date, start_time, end_time, capacity, enabled FROM ' . $this->sessions() . ' WHERE id = %d FOR UPDATE',
				$session_id
			)
		);
	}

	/**
	 * @param int $booking_id Reserva.
	 * @return object|null
	 */
	public function findBooking( $booking_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT id, session_id, user_id FROM ' . $this->bookings() . ' WHERE id = %d',
				$booking_id
			)
		);
	}

	/**
	 * @param int $booking_id Reserva.
	 * @return object|null
	 */
	public function lockBooking( $booking_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT id, session_id, user_id FROM ' . $this->bookings() . ' WHERE id = %d FOR UPDATE',
				$booking_id
			)
		);
	}

	/**
	 * @param int $session_id Sesión.
	 * @param int $user_id    Usuario.
	 * @return int
	 */
	public function findUserBookingId( $session_id, $user_id ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM ' . $this->bookings() . ' WHERE session_id = %d AND user_id = %d',
				$session_id,
				$user_id
			)
		);
		$this->assertNoError();

		return (int) $value;
	}

	/**
	 * @param int $session_id Sesión.
	 * @return int
	 */
	public function countSessionBookings( $session_id ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->bookings() . ' WHERE session_id = %d',
				$session_id
			)
		);
		$this->assertNoError();

		return (int) $value;
	}

	/**
	 * Reservas del usuario cuya sesión cae entre dos fechas, inclusive.
	 *
	 * @param int    $user_id Usuario.
	 * @param string $from    Fecha `Y-m-d`.
	 * @param string $to      Fecha `Y-m-d`.
	 * @return int
	 */
	public function countUserBookingsBetween( $user_id, $from, $to ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->bookings() . ' b INNER JOIN ' . $this->sessions() . ' s ON s.id = b.session_id WHERE b.user_id = %d AND s.session_date BETWEEN %s AND %s',
				$user_id,
				$from,
				$to
			)
		);
		$this->assertNoError();

		return (int) $value;
	}

	/**
	 * @param array<string, int|string|null> $row Campos de la reserva.
	 * @return int Identificador insertado.
	 */
	public function insertBooking( array $row ) {
		global $wpdb;

		$manager_sql  = null === $row['manager_id'] ? 'NULL' : '%d';
		$values       = array(
			$row['session_id'],
			$row['user_id'],
			$row['first_name'],
			$row['last_name'],
			$row['phone'],
			$row['email'],
			$row['created_at'],
			$row['created_by'],
			$row['is_manual'],
		);
		if ( null !== $row['manager_id'] ) {
			$values[] = $row['manager_id'];
		}

		$suppressed = $wpdb->suppress_errors( true );
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->bookings() . ' (session_id, user_id, first_name, last_name, phone, email, created_at, created_by, is_manual, manager_id) VALUES (%d, %d, %s, %s, %s, %s, %s, %d, %d, ' . $manager_sql . ')',
				$values
			)
		);
		$wpdb->suppress_errors( $suppressed );

		if ( false === $inserted ) {
			if ( $this->isDuplicate() ) {
				$wpdb->last_error = '';
				return 0;
			}

			throw new \RuntimeException( 'No se pudo crear la reserva.' );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param int $booking_id Reserva.
	 * @return void
	 */
	public function deleteBooking( $booking_id ) {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $this->bookings() . ' WHERE id = %d',
				$booking_id
			)
		);
		$this->assertNoError();

		if ( false === $deleted ) {
			throw new \RuntimeException( 'No se pudo cancelar la reserva.' );
		}
	}

	/**
	 * @param string $sql Consulta ya preparada.
	 * @return object|null
	 */
	private function row( $sql ) {
		global $wpdb;

		$row = $wpdb->get_row( $sql );
		$this->assertNoError();

		return $row;
	}

	/**
	 * @return void
	 */
	private function assertNoError() {
		global $wpdb;

		if ( '' !== $wpdb->last_error ) {
			throw new \RuntimeException( 'Error de base de datos.' );
		}
	}

	/**
	 * @return bool
	 */
	private function isDuplicate() {
		global $wpdb;

		return '' !== $wpdb->last_error && false !== stripos( $wpdb->last_error, 'Duplicate' );
	}

	/**
	 * @return string
	 */
	private function sessions() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_sessions';
	}

	/**
	 * @return string
	 */
	private function bookings() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_bookings';
	}

	/**
	 * @return string
	 */
	private function swimmers() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_swimmers';
	}
}
