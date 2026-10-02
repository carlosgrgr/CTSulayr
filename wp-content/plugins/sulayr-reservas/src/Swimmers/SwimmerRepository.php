<?php

namespace SulayrReservas\Swimmers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ficha local y reservas del nadador en el blog actual.
 *
 * Quien llama debe estar ya en el sitio de reservas.
 */
class SwimmerRepository {

	/**
	 * @param int $user_id Usuario.
	 * @return object|null
	 */
	public function find( $user_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT user_id, active, linked_to_club, first_name, last_name, email, phone, dni FROM ' . $this->swimmers() . ' WHERE user_id = %d',
				$user_id
			)
		);
	}

	/**
	 * @param int $user_id Usuario.
	 * @return object|null
	 */
	public function lock( $user_id ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT user_id, active FROM ' . $this->swimmers() . ' WHERE user_id = %d FOR UPDATE',
				$user_id
			)
		);
	}

	/**
	 * @param string $email Correo ya saneado.
	 * @return int
	 */
	public function findUserIdByEmail( $email ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM ' . $this->swimmers() . ' WHERE email = %s LIMIT 1',
				$email
			)
		);
		$this->assertNoError();

		return (int) $value;
	}

	/**
	 * @param array<string, int|string> $row Campos de la ficha.
	 * @return bool False si el usuario ya tenía ficha.
	 */
	public function insert( array $row ) {
		global $wpdb;

		$suppressed = $wpdb->suppress_errors( true );
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->swimmers() . ' (user_id, active, linked_to_club, first_name, last_name, email, phone, dni, created_at, updated_at) VALUES (%d, %d, %d, %s, %s, %s, %s, %s, %s, %s)',
				$row['user_id'],
				1,
				0,
				$row['first_name'],
				$row['last_name'],
				$row['email'],
				$row['phone'],
				$row['dni'],
				$row['stamp'],
				$row['stamp']
			)
		);
		$wpdb->suppress_errors( $suppressed );

		if ( false === $inserted ) {
			if ( $this->isDuplicate() ) {
				$wpdb->last_error = '';
				return false;
			}

			throw new \RuntimeException( 'No se pudo crear la ficha.' );
		}

		return true;
	}

	/**
	 * @param int    $user_id Usuario.
	 * @param int    $active  1 o 0.
	 * @param string $stamp   Momento de la operación.
	 * @return void
	 */
	public function setActive( $user_id, $active, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET active = %d, updated_at = %s WHERE user_id = %d',
				$active,
				$stamp,
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo actualizar el estado.' );
		}
	}

	/**
	 * Crea la ficha vinculada al club. No envía correo ni toca el perfil global.
	 *
	 * @param array<string, int|string> $row Campos ya limitados a las columnas.
	 * @return bool False si otra ejecución creó la ficha al mismo tiempo.
	 */
	public function insertSynced( array $row ) {
		global $wpdb;

		$suppressed = $wpdb->suppress_errors( true );
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->swimmers() . ' (user_id, active, linked_to_club, first_name, last_name, email, phone, dni, created_at, updated_at) VALUES (%d, 1, 1, %s, %s, %s, %s, %s, %s, %s)',
				$row['user_id'],
				$row['first_name'],
				$row['last_name'],
				$row['email'],
				$row['phone'],
				$row['dni'],
				$row['stamp'],
				$row['stamp']
			)
		);
		$wpdb->suppress_errors( $suppressed );

		if ( false === $inserted ) {
			if ( $this->isDuplicate() ) {
				$wpdb->last_error = '';

				return false;
			}

			throw new \RuntimeException( 'No se pudo crear la ficha.' );
		}

		return true;
	}

	/**
	 * Sustituye la copia local por los datos actuales del club.
	 *
	 * @param int                       $user_id Usuario.
	 * @param array<string, int|string> $row     Datos ya limitados a las columnas.
	 * @return void
	 */
	public function updateSynced( $user_id, array $row ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET active = %d, linked_to_club = 1, first_name = %s, last_name = %s, email = %s, phone = %s, dni = %s, updated_at = %s WHERE user_id = %d',
				1,
				$row['first_name'],
				$row['last_name'],
				$row['email'],
				$row['phone'],
				$row['dni'],
				$row['stamp'],
				(int) $user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo actualizar la ficha.' );
		}
	}

	/**
	 * @param int $user_id Usuario.
	 * @return void
	 */
	public function delete( $user_id ) {
		global $wpdb;

		$deleted = $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $this->swimmers() . ' WHERE user_id = %d',
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $deleted ) {
			throw new \RuntimeException( 'No se pudo eliminar la ficha.' );
		}
	}

	/**
	 * Reservas del usuario con la fecha y hora de su sesión.
	 *
	 * @param int $user_id Usuario.
	 * @return object[]
	 */
	public function bookings( $user_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.id, s.session_date, s.start_time FROM ' . $this->bookingsTable() . ' b INNER JOIN ' . $this->sessions() . ' s ON s.id = b.session_id WHERE b.user_id = %d',
				$user_id
			)
		);
		$this->assertNoError();

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param int[] $ids Identificadores de reserva.
	 * @return void
	 */
	public function deleteBookings( array $ids ) {
		global $wpdb;

		$ids = array_values( array_filter( array_map( 'intval', $ids ) ) );
		if ( array() === $ids ) {
			return;
		}

		$deleted = $wpdb->query( 'DELETE FROM ' . $this->bookingsTable() . ' WHERE id IN (' . implode( ',', $ids ) . ')' );
		$this->assertNoError();

		if ( false === $deleted ) {
			throw new \RuntimeException( 'No se pudieron eliminar las reservas futuras.' );
		}
	}

	/**
	 * Desvincula el historial del usuario y conserva el snapshot.
	 *
	 * @param int $user_id Usuario.
	 * @return void
	 */
	public function detachBookingUsers( $user_id ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->bookingsTable() . ' SET user_id = NULL WHERE user_id = %d',
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo conservar el historial.' );
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
	private function swimmers() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_swimmers';
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
	private function bookingsTable() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_bookings';
	}
}
