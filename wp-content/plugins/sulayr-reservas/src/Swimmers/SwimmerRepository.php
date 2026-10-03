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
				'SELECT user_id, active, linked_to_club, first_name, last_name, email, phone, dni, weekly_limit, photo_id, pending_email, email_token_hash, email_token_expires, created_at FROM ' . $this->swimmers() . ' WHERE user_id = %d',
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
		$active = isset( $row['active'] ) ? (int) $row['active'] : 1;
		$limit  = isset( $row['weekly_limit'] ) ? (int) $row['weekly_limit'] : WeeklyLimit::DEFAULT_LIMIT;
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->swimmers() . ' (user_id, active, linked_to_club, first_name, last_name, email, phone, dni, weekly_limit, created_at, updated_at) VALUES (%d, %d, %d, %s, %s, %s, %s, %s, %d, %s, %s)',
				$row['user_id'],
				1 === $active ? 1 : 0,
				0,
				$row['first_name'],
				$row['last_name'],
				$row['email'],
				$row['phone'],
				$row['dni'],
				$limit,
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
	 * Nombre, apellidos y teléfono de una ficha no vinculada al club.
	 *
	 * @param int    $user_id    Usuario.
	 * @param string $first_name Nombre.
	 * @param string $last_name  Apellidos.
	 * @param string $phone      Teléfono.
	 * @param string $stamp      Momento de la operación.
	 * @return void
	 */
	public function updatePersonal( $user_id, $first_name, $last_name, $phone, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET first_name = %s, last_name = %s, phone = %s, updated_at = %s WHERE user_id = %d AND linked_to_club = 0',
				$first_name,
				$last_name,
				$phone,
				$stamp,
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo actualizar la ficha.' );
		}
	}

	/**
	 * @param int    $user_id Usuario.
	 * @param int    $limit   Límite entre 1 y 5.
	 * @param string $stamp   Momento de la operación.
	 * @return void
	 */
	public function setWeeklyLimit( $user_id, $limit, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET weekly_limit = %d, updated_at = %s WHERE user_id = %d',
				$limit,
				$stamp,
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo actualizar el límite.' );
		}
	}

	/**
	 * @param int    $user_id  Usuario.
	 * @param int    $photo_id Adjunto del blog de reservas. Cero si no hay.
	 * @param string $stamp    Momento de la operación.
	 * @return void
	 */
	public function setPhoto( $user_id, $photo_id, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET photo_id = %d, updated_at = %s WHERE user_id = %d',
				$photo_id,
				$stamp,
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo actualizar la fotografía.' );
		}
	}

	/**
	 * @param int    $user_id Usuario.
	 * @param string $email   Correo nuevo pendiente.
	 * @param string $hash    Hash del token.
	 * @param string $expires Caducidad en la zona del sitio.
	 * @param string $stamp   Momento de la operación.
	 * @return void
	 */
	public function setPendingEmail( $user_id, $email, $hash, $expires, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET pending_email = %s, email_token_hash = %s, email_token_expires = %s, updated_at = %s WHERE user_id = %d',
				$email,
				$hash,
				$expires,
				$stamp,
				$user_id
			)
		);
		$this->assertNoError();

		if ( false === $updated ) {
			throw new \RuntimeException( 'No se pudo guardar la solicitud de correo.' );
		}
	}

	/**
	 * @param string $hash Hash del token recibido.
	 * @return object|null
	 */
	public function findByEmailToken( $hash ) {
		global $wpdb;

		return $this->row(
			$wpdb->prepare(
				'SELECT user_id, email, pending_email, email_token_hash, email_token_expires FROM ' . $this->swimmers() . ' WHERE email_token_hash = %s LIMIT 1',
				$hash
			)
		);
	}

	/**
	 * @param int    $user_id Usuario.
	 * @param string $email   Correo ya confirmado.
	 * @param string $hash    Hash que debe seguir vigente.
	 * @param string $stamp   Momento de la operación.
	 * @return bool False si el token ya no está.
	 */
	public function applyConfirmedEmail( $user_id, $email, $hash, $stamp ) {
		global $wpdb;

		$updated = $wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . $this->swimmers() . ' SET email = %s, pending_email = %s, email_token_hash = %s, email_token_expires = %s, updated_at = %s WHERE user_id = %d AND email_token_hash = %s',
				$email,
				'',
				'',
				'1970-01-01 00:00:00',
				$stamp,
				$user_id,
				$hash
			)
		);
		$this->assertNoError();

		return (int) $updated > 0;
	}

	/**
	 * @param string $email   Correo.
	 * @param int    $user_id Usuario que sí puede conservarlo.
	 * @return int
	 */
	public function findOtherUserIdByEmail( $email, $user_id ) {
		global $wpdb;

		$value = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT user_id FROM ' . $this->swimmers() . ' WHERE email = %s AND user_id <> %d LIMIT 1',
				$email,
				$user_id
			)
		);
		$this->assertNoError();

		return (int) $value;
	}

	/**
	 * Reservas que siguen en la tabla, con los datos que la exportación puede mostrar.
	 *
	 * @param int $user_id Usuario.
	 * @return object[]
	 */
	public function bookingsForExport( $user_id ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT b.user_id, b.created_at, b.created_by, s.session_date, s.start_time FROM ' . $this->bookingsTable() . ' b INNER JOIN ' . $this->sessions() . ' s ON s.id = b.session_id WHERE b.user_id = %d ORDER BY s.session_date ASC, s.start_time ASC, b.id ASC',
				$user_id
			)
		);
		$this->assertNoError();

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param string $status Estado `todos`, `activos` o `inactivos`.
	 * @param string $search Texto de nombre o correo. Vacío si no hay búsqueda.
	 * @return object[]
	 */
	public function search( $status, $search ) {
		global $wpdb;

		$where  = array( '1=1' );
		$values = array();
		if ( 'activos' === $status ) {
			$where[] = 'active = 1';
		} elseif ( 'inactivos' === $status ) {
			$where[] = 'active = 0';
		}

		if ( '' !== $search ) {
			$like     = '%' . $wpdb->esc_like( $search ) . '%';
			$where[]  = '(first_name LIKE %s OR last_name LIKE %s OR CONCAT(first_name, \' \', last_name) LIKE %s OR email LIKE %s)';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$sql = 'SELECT user_id, active, linked_to_club, first_name, last_name, email, phone, weekly_limit, photo_id, created_at FROM ' . $this->swimmers() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY last_name ASC, first_name ASC, user_id ASC';
		if ( array() !== $values ) {
			$sql = $wpdb->prepare( $sql, $values );
		}

		$rows = $wpdb->get_results( $sql );
		$this->assertNoError();

		return is_array( $rows ) ? $rows : array();
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
