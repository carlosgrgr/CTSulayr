<?php

namespace SulayrReservas\Swimmers;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Roles\RoleRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Alta, activación, desactivación y baja de nadadores en el sitio de reservas.
 *
 * El actor es el usuario actual. No escribe metadatos del perfil del club.
 */
class SwimmerService {

	/** @var BlogContext */
	private $blog_context;

	/** @var SwimmerRepository */
	private $repository;

	/**
	 * @param BlogContext            $blog_context Cambio de sitio con restauración.
	 * @param SwimmerRepository|null $repository   Ficha y reservas del blog actual.
	 */
	public function __construct( BlogContext $blog_context, ?SwimmerRepository $repository = null ) {
		$this->blog_context = $blog_context;
		$this->repository   = $repository ? $repository : new SwimmerRepository();
	}

	/**
	 * Crea un nadador manual o reutiliza la cuenta de WordPress de ese correo.
	 *
	 * Una cuenta nueva recibe el correo nativo para elegir contraseña. Una cuenta ya
	 * existente no cambia de contraseña ni de metadatos globales.
	 *
	 * @param array<string, string>   $data Nombre, apellidos, correo, teléfono y DNI.
	 * @param \DateTimeImmutable|null $now  Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function create( array $data, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $data, $now ) {
				return $this->createOnBookings( $data, $now );
			}
		);
	}

	/**
	 * Crea un nadador desde una fila de CSV ya validada.
	 *
	 * No reutiliza ni modifica una cuenta existente. El aviso de contraseña solo sale si el alta se completa.
	 *
	 * @param array<string, int|string> $data Datos de la fila.
	 * @param \DateTimeImmutable|null   $now  Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function createImported( array $data, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $data, $now ) {
				return $this->importOnBookings( $data, $now );
			}
		);
	}

	/**
	 * Actualiza nombre, apellidos y teléfono de una ficha manual.
	 *
	 * @param int                     $user_id Nadador.
	 * @param array<string, string>   $data    Campos del formulario.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function updatePersonal( $user_id, array $data, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $data, $now ) {
				return $this->updatePersonalOnBookings( (int) $user_id, $data, $now );
			}
		);
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param mixed                   $limit   Valor recibido.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function updateWeeklyLimit( $user_id, $limit, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $limit, $now ) {
				return $this->updateLimitOnBookings( (int) $user_id, $limit, $now );
			}
		);
	}

	/**
	 * Cambia la contraseña del usuario actual. Un gestor no puede cambiar la de otro.
	 *
	 * @param string $current Contraseña actual.
	 * @param string $new     Contraseña nueva.
	 * @param string $confirm Repetición.
	 * @return SwimmerResult
	 */
	public function changePassword( $current, $new, $confirm ) {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( $user_id < 1 || ! $user instanceof \WP_User ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( ! wp_check_password( (string) $current, $user->user_pass, $user_id ) ) {
			return $this->result( SwimmerResult::INVALID, 'La contraseña actual no es correcta.' );
		}
		if ( (string) $new !== (string) $confirm ) {
			return $this->result( SwimmerResult::INVALID, 'La confirmación no coincide con la nueva contraseña.' );
		}
		if ( strlen( (string) $new ) < 8 ) {
			return $this->result( SwimmerResult::INVALID, 'La nueva contraseña debe tener al menos 8 caracteres.' );
		}

		wp_set_password( (string) $new, $user_id );
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		return $this->result( SwimmerResult::UPDATED, 'Contraseña actualizada.', $user_id );
	}

	/**
	 * Desactiva al nadador y elimina sus reservas futuras.
	 *
	 * @param int                     $user_id Nadador.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function deactivate( $user_id, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $now ) {
				return $this->changeActive( (int) $user_id, 0, $now );
			}
		);
	}

	/**
	 * Reactiva al nadador. No recupera las reservas futuras eliminadas.
	 *
	 * @param int $user_id Nadador.
	 * @return SwimmerResult
	 */
	public function reactivate( $user_id ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id ) {
				return $this->changeActive( (int) $user_id, 1, null );
			}
		);
	}

	/**
	 * Pasa una ficha del club a CN Nazarí y la deja activa.
	 *
	 * Solo un administrador del sitio de reservas. La baja del club ya no la
	 * desactiva. Si más adelante vuelve a cumplir las condiciones del club,
	 * la sincronización puede volver a vincularla.
	 *
	 * @param int                     $user_id Nadador.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function releaseFromClub( $user_id, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $now ) {
				return $this->releaseOnBookings( (int) $user_id, $now );
			}
		);
	}

	/**
	 * Retira al nadador del sitio de reservas.
	 *
	 * Borra las reservas futuras, deja el historial pasado sin `user_id` y sin DNI,
	 * y no elimina la cuenta global ni la pertenencia al club.
	 *
	 * @param int                     $user_id Nadador.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	public function remove( $user_id, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $now ) {
				return $this->removeOnBookings( (int) $user_id, $now );
			}
		);
	}

	/**
	 * @param array<string, string>   $data Datos del formulario.
	 * @param \DateTimeImmutable|null $now  Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function createOnBookings( array $data, ?\DateTimeImmutable $now ) {
		if ( ! $this->canManage() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$fields = $this->fields( $data );
		if ( null === $fields ) {
			return $this->result( SwimmerResult::INVALID, 'Revisa los datos del nadador.' );
		}

		$limit = $this->limitFrom( $data );
		if ( null === $limit ) {
			return $this->result( SwimmerResult::INVALID, 'El límite semanal debe estar entre 1 y 5.' );
		}
		$fields['weekly_limit'] = $limit;
		$fields['active']       = 1;

		if ( $this->repository->findUserIdByEmail( $fields['email'] ) > 0 ) {
			return $this->result( SwimmerResult::DUPLICATE, 'Ya hay un nadador con ese correo.' );
		}

		$existing = get_user_by( 'email', $fields['email'] );
		if ( $existing instanceof \WP_User ) {
			return $this->attachExisting( $existing->ID, $fields, $now );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $this->uniqueLogin( $fields['email'] ),
				'user_pass'  => wp_generate_password( 24, true, true ),
				'user_email' => $fields['email'],
				'role'       => RoleRegistrar::ROLE_NADADOR,
			)
		);

		if ( is_wp_error( $user_id ) ) {
			if ( 'existing_user_email' === $user_id->get_error_code() ) {
				$race = get_user_by( 'email', $fields['email'] );
				if ( $race instanceof \WP_User ) {
					return $this->attachExisting( $race->ID, $fields, $now );
				}
			}

			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		$stored = $this->storeProfile( (int) $user_id, $fields, $now );
		if ( SwimmerResult::CREATED !== $stored->status() ) {
			return $stored;
		}

		wp_send_new_user_notifications( (int) $user_id, 'user' );

		return $stored;
	}

	/**
	 * @param int                     $user_id Usuario de WordPress.
	 * @param array<string, string>   $fields  Datos ya validados.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function attachExisting( $user_id, array $fields, ?\DateTimeImmutable $now ) {
		if ( $this->repository->find( $user_id ) ) {
			return $this->result( SwimmerResult::DUPLICATE, 'Ya hay un nadador con ese correo.', $user_id );
		}

		$blog_id = get_current_blog_id();
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			$added = add_user_to_blog( $blog_id, $user_id, RoleRegistrar::ROLE_NADADOR );
			if ( is_wp_error( $added ) ) {
				return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
			}
		} else {
			$user = get_userdata( $user_id );
			if ( $user instanceof \WP_User && ! in_array( RoleRegistrar::ROLE_NADADOR, (array) $user->roles, true ) ) {
				$user->add_role( RoleRegistrar::ROLE_NADADOR );
			}
		}

		$stored = $this->storeProfile( $user_id, $fields, $now );
		if ( SwimmerResult::CREATED === $stored->status() ) {
			return $this->result( SwimmerResult::REUSED, 'Se ha reutilizado la cuenta existente.', $user_id );
		}

		return $stored;
	}

	/**
	 * @param int                     $user_id Usuario.
	 * @param array<string, string>   $fields  Datos ya validados.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function storeProfile( $user_id, array $fields, ?\DateTimeImmutable $now ) {
		$fields['user_id'] = $user_id;
		$fields['stamp']   = $this->clock( $now )->format( 'Y-m-d H:i:s' );

		try {
			if ( ! $this->repository->insert( $fields ) ) {
				return $this->result( SwimmerResult::DUPLICATE, 'Ya hay un nadador con ese correo.', $user_id );
			}
		} catch ( \Throwable $exception ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		return $this->result( SwimmerResult::CREATED, 'Nadador creado.', $user_id );
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param int                     $active  1 o 0.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function changeActive( $user_id, $active, ?\DateTimeImmutable $now ) {
		if ( ! $this->canManage() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( $user_id < 1 ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		try {
			$this->begin();
			if ( ! $this->repository->lock( $user_id ) ) {
				$this->rollback();
				return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
			}

			$now = $this->clock( $now );
			$this->repository->setActive( $user_id, $active, $now->format( 'Y-m-d H:i:s' ) );
			if ( 0 === $active ) {
				$this->repository->deleteBookings( $this->futureBookingIds( $user_id, $now ) );
			}
			$this->commit();
		} catch ( \Throwable $exception ) {
			$this->rollback();
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		if ( 1 === $active ) {
			return $this->result( SwimmerResult::REACTIVATED, 'Nadador reactivado.', $user_id );
		}

		return $this->result( SwimmerResult::DEACTIVATED, 'Nadador desactivado.', $user_id );
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function releaseOnBookings( $user_id, ?\DateTimeImmutable $now ) {
		if ( ! $this->canAdminister() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( $user_id < 1 ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		$row = $this->repository->find( $user_id );
		if ( ! $row ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}
		if ( 1 !== (int) $row->linked_to_club ) {
			return $this->result( SwimmerResult::INVALID, 'Este nadador ya es de CN Nazarí.', $user_id );
		}

		$role = $this->ensureNadador( $user_id );
		if ( null !== $role ) {
			return $role;
		}

		try {
			$this->begin();
			if ( ! $this->repository->lock( $user_id ) ) {
				$this->rollback();
				return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
			}

			$released = $this->repository->releaseFromClub( $user_id, $this->clock( $now )->format( 'Y-m-d H:i:s' ) );
			$this->commit();
		} catch ( \Throwable $exception ) {
			$this->rollback();
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		if ( ! $released ) {
			return $this->result( SwimmerResult::INVALID, 'Este nadador ya es de CN Nazarí.', $user_id );
		}

		return $this->result( SwimmerResult::RELEASED, 'El nadador ha pasado a CN Nazarí y queda activo en reservas.', $user_id );
	}

	/**
	 * Garantiza el rol de nadador en el sitio de reservas. No quita otros roles.
	 *
	 * @param int $user_id Usuario.
	 * @return SwimmerResult|null Null si la cuenta puede entrar, o ya no existe.
	 */
	private function ensureNadador( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return null;
		}

		$blog_id = get_current_blog_id();
		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			$added = add_user_to_blog( $blog_id, $user_id, RoleRegistrar::ROLE_NADADOR );
			if ( is_wp_error( $added ) ) {
				return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
			}

			return null;
		}

		if ( ! in_array( RoleRegistrar::ROLE_NADADOR, (array) $user->roles, true ) ) {
			$user->add_role( RoleRegistrar::ROLE_NADADOR );
		}

		return null;
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function removeOnBookings( $user_id, ?\DateTimeImmutable $now ) {
		if ( ! $this->canManage() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( $user_id < 1 ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		$current = $this->repository->find( $user_id );
		$photo   = $current ? (int) $current->photo_id : 0;
		$existed = (bool) $current;
		$member  = is_user_member_of_blog( $user_id, get_current_blog_id() );
		if ( ! $existed && ! $member ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		if ( $existed ) {
			try {
				$this->begin();
				if ( $this->repository->lock( $user_id ) ) {
					$now = $this->clock( $now );
					$this->repository->deleteBookings( $this->futureBookingIds( $user_id, $now ) );
					$this->repository->detachBookingUsers( $user_id );
					$this->repository->delete( $user_id );
				}
				$this->commit();
			} catch ( \Throwable $exception ) {
				$this->rollback();
				return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
			}
		}

		$this->detachFromBookings( $user_id );
		if ( $photo > 0 ) {
			wp_delete_attachment( $photo, true );
		}

		return $this->result( SwimmerResult::REMOVED, 'Nadador retirado del sitio de reservas.', $user_id );
	}

	/**
	 * Quita `nadador` del sitio. Si ese es su único rol útil, lo retira del blog.
	 *
	 * No llama a `wp_delete_user()`. Un gestor, un administrador o un superadministrador
	 * conservan el resto de sus roles en este sitio.
	 *
	 * @param int $user_id Usuario.
	 * @return void
	 */
	private function detachFromBookings( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$extra = array_diff( (array) $user->roles, array( RoleRegistrar::ROLE_NADADOR, 'subscriber' ) );
		if ( is_super_admin( $user_id ) || array() !== $extra ) {
			$user->remove_role( RoleRegistrar::ROLE_NADADOR );
			return;
		}

		global $wpdb;

		$suppressed = $wpdb->suppress_errors( true );
		remove_user_from_blog( $user_id, get_current_blog_id() );
		$wpdb->suppress_errors( $suppressed );
		$wpdb->last_error = '';
	}

	/**
	 * @param int                $user_id Usuario.
	 * @param \DateTimeImmutable $now     Momento de la operación.
	 * @return int[]
	 */
	private function futureBookingIds( $user_id, \DateTimeImmutable $now ) {
		$ids = array();

		foreach ( $this->repository->bookings( $user_id ) as $booking ) {
			if ( $now < $this->sessionStart( $booking ) ) {
				$ids[] = (int) $booking->id;
			}
		}

		return $ids;
	}

	/**
	 * @param object $booking Reserva con fecha y hora de la sesión.
	 * @return \DateTimeImmutable
	 */
	private function sessionStart( $booking ) {
		$start = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$booking->session_date . ' ' . $booking->start_time,
			wp_timezone()
		);

		if ( ! $start instanceof \DateTimeImmutable ) {
			throw new \RuntimeException( 'La reserva no tiene una sesión válida.' );
		}

		return $start;
	}

	/**
	 * @param array<string, int|string> $data Datos de una fila válida.
	 * @param \DateTimeImmutable|null   $now  Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function importOnBookings( array $data, ?\DateTimeImmutable $now ) {
		if ( ! $this->canManage() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$first = $this->text( isset( $data['first_name'] ) ? $data['first_name'] : '', 100 );
		$last  = $this->text( isset( $data['last_name'] ) ? $data['last_name'] : '', 100 );
		$phone = $this->optionalText( isset( $data['phone'] ) ? $data['phone'] : '', 50 );
		$email = sanitize_email( isset( $data['email'] ) ? (string) $data['email'] : '' );
		$limit = $this->limitFrom( $data );
		$active = isset( $data['active'] ) && ( 0 === (int) $data['active'] || '0' === (string) $data['active'] ) ? 0 : 1;

		if ( '' === $first || '' === $last || null === $phone || null === $limit || ! is_email( $email ) || strlen( $email ) > 100 ) {
			return $this->result( SwimmerResult::INVALID, 'Revisa los datos del nadador.' );
		}
		if ( $this->repository->findUserIdByEmail( $email ) > 0 || get_user_by( 'email', $email ) instanceof \WP_User ) {
			return $this->result( SwimmerResult::DUPLICATE, 'El correo ya existe. No se ha modificado la cuenta.' );
		}

		$user_id = wp_insert_user(
			array(
				'user_login' => $this->uniqueLogin( $email ),
				'user_pass'  => wp_generate_password( 24, true, true ),
				'user_email' => $email,
				'role'       => RoleRegistrar::ROLE_NADADOR,
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		$stored = $this->storeProfile(
			(int) $user_id,
			array(
				'first_name'   => $first,
				'last_name'    => $last,
				'email'        => $email,
				'phone'        => $phone,
				'dni'          => '',
				'weekly_limit' => $limit,
				'active'       => $active,
			),
			$now
		);
		if ( SwimmerResult::CREATED !== $stored->status() ) {
			return $stored;
		}

		wp_send_new_user_notifications( (int) $user_id, 'user' );

		return $stored;
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param array<string, string>   $data    Campos.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function updatePersonalOnBookings( $user_id, array $data, ?\DateTimeImmutable $now ) {
		$actor = get_current_user_id();
		if ( $actor < 1 || ( $actor !== $user_id && ! $this->canManage() ) ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$row = $this->repository->find( $user_id );
		if ( ! $row ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}
		if ( 1 === (int) $row->linked_to_club ) {
			return $this->result( SwimmerResult::INVALID, 'El club gestiona el nombre, los apellidos y el teléfono de este nadador.' );
		}

		$first = $this->text( isset( $data['first_name'] ) ? $data['first_name'] : '', 100 );
		$last  = $this->text( isset( $data['last_name'] ) ? $data['last_name'] : '', 100 );
		$phone = $this->optionalText( isset( $data['phone'] ) ? $data['phone'] : '', 50 );
		if ( '' === $first || '' === $last || null === $phone ) {
			return $this->result( SwimmerResult::INVALID, 'Revisa el nombre, los apellidos y el teléfono.' );
		}

		try {
			$this->repository->updatePersonal( $user_id, $first, $last, $phone, $this->clock( $now )->format( 'Y-m-d H:i:s' ) );
		} catch ( \Throwable $exception ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		return $this->result( SwimmerResult::UPDATED, 'Datos actualizados.', $user_id );
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param mixed                   $limit   Valor recibido.
	 * @param \DateTimeImmutable|null $now     Fecha de referencia.
	 * @return SwimmerResult
	 */
	private function updateLimitOnBookings( $user_id, $limit, ?\DateTimeImmutable $now ) {
		if ( ! $this->canManage() ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( ! $this->repository->find( $user_id ) ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}
		if ( ! WeeklyLimit::isValid( $limit ) ) {
			return $this->result( SwimmerResult::INVALID, 'El límite semanal debe estar entre 1 y 5.' );
		}

		try {
			$this->repository->setWeeklyLimit( $user_id, (int) $limit, $this->clock( $now )->format( 'Y-m-d H:i:s' ) );
		} catch ( \Throwable $exception ) {
			return $this->result( SwimmerResult::FAILED, 'No se ha podido completar la operación.' );
		}

		return $this->result( SwimmerResult::UPDATED, 'Límite semanal actualizado.', $user_id );
	}

	/**
	 * @param array<string, mixed> $data Datos recibidos.
	 * @return int|null Null si el valor existe y no está entre 1 y 5.
	 */
	private function limitFrom( array $data ) {
		if ( ! array_key_exists( 'weekly_limit', $data ) || '' === trim( (string) $data['weekly_limit'] ) ) {
			return WeeklyLimit::DEFAULT_LIMIT;
		}
		if ( ! WeeklyLimit::isValid( $data['weekly_limit'] ) ) {
			return null;
		}

		return (int) $data['weekly_limit'];
	}

	/**
	 * @param mixed $value Texto recibido.
	 * @param int   $limit Longitud máxima.
	 * @return string|null Null si supera la longitud. Cadena vacía si no hay texto.
	 */
	private function optionalText( $value, $limit ) {
		$text = sanitize_text_field( (string) $value );
		if ( mb_strlen( $text ) > $limit ) {
			return null;
		}

		return $text;
	}

	/**
	 * @param array<string, mixed> $data Datos recibidos.
	 * @return array<string, string>|null Null si falta un dato obligatorio o alguno supera su longitud.
	 */
	private function fields( array $data ) {
		$fields = array(
			'first_name' => $this->text( isset( $data['first_name'] ) ? $data['first_name'] : '', 100 ),
			'last_name'  => $this->text( isset( $data['last_name'] ) ? $data['last_name'] : '', 100 ),
			'phone'      => $this->optionalText( isset( $data['phone'] ) ? $data['phone'] : '', 50 ),
			'dni'        => $this->optionalText( isset( $data['dni'] ) ? $data['dni'] : '', 20 ),
		);
		$email  = sanitize_email( isset( $data['email'] ) ? (string) $data['email'] : '' );

		if ( ! is_email( $email ) || strlen( $email ) > 100 ) {
			return null;
		}

		// Nombre y apellidos son obligatorios. Teléfono y DNI son opcionales, pero no pueden superar su longitud.
		if ( '' === $fields['first_name'] || '' === $fields['last_name'] || null === $fields['phone'] || null === $fields['dni'] ) {
			return null;
		}

		$fields['email'] = $email;

		return $fields;
	}

	/**
	 * @param mixed $value  Texto recibido.
	 * @param int   $limit  Longitud máxima de la columna.
	 * @return string Vacío si no es válido.
	 */
	private function text( $value, $limit ) {
		$text = sanitize_text_field( (string) $value );
		if ( '' === $text || mb_strlen( $text ) > $limit ) {
			return '';
		}

		return $text;
	}

	/**
	 * @param string $email Correo.
	 * @return string
	 */
	private function uniqueLogin( $email ) {
		$local = strstr( $email, '@', true );
		$base  = sanitize_user( is_string( $local ) ? $local : '', true );
		if ( '' === $base ) {
			$base = 'nadador';
		}

		$base      = substr( $base, 0, 50 );
		$candidate = $base;
		$suffix    = 2;

		while ( username_exists( $candidate ) ) {
			$tail      = '-' . $suffix;
			$candidate = substr( $base, 0, 60 - strlen( $tail ) ) . $tail;
			++$suffix;
			if ( $suffix > 1000 ) {
				$candidate = 'nadador-' . wp_generate_password( 8, false, false );
				break;
			}
		}

		return $candidate;
	}

	/**
	 * @return bool
	 */
	private function canManage() {
		return get_current_user_id() > 0 && current_user_can( Capabilities::MANAGE_SWIMMERS );
	}

	/**
	 * El paso a CN Nazarí queda reservado al administrador del sitio de reservas.
	 *
	 * @return bool
	 */
	private function canAdminister() {
		return get_current_user_id() > 0 && current_user_can( 'manage_options' );
	}

	/**
	 * @param string $status  Estado.
	 * @param string $message Mensaje.
	 * @param int    $user_id Nadador.
	 * @return SwimmerResult
	 */
	private function result( $status, $message, $user_id = 0 ) {
		return new SwimmerResult( $status, $message, $user_id );
	}

	/**
	 * @return void
	 */
	private function begin() {
		global $wpdb;

		$started = $wpdb->query( 'START TRANSACTION' );
		if ( false === $started ) {
			throw new \RuntimeException( 'No se pudo abrir la transacción.' );
		}
	}

	/**
	 * @return void
	 */
	private function commit() {
		global $wpdb;

		$this->beforeCommit();
		$done = $wpdb->query( 'COMMIT' );
		if ( false === $done ) {
			throw new \RuntimeException( 'No se pudo confirmar la operación.' );
		}
	}

	/**
	 * @return void
	 */
	private function rollback() {
		global $wpdb;

		$wpdb->query( 'ROLLBACK' );
		$wpdb->last_error = '';
	}

	/**
	 * Punto de prueba para forzar un fallo antes de confirmar.
	 *
	 * @return void
	 */
	protected function beforeCommit() {
	}

	/**
	 * @param \DateTimeImmutable|null $now Fecha recibida.
	 * @return \DateTimeImmutable
	 */
	private function clock( ?\DateTimeImmutable $now ) {
		$zone = wp_timezone();

		if ( null === $now ) {
			return new \DateTimeImmutable( 'now', $zone );
		}

		return $now->setTimezone( $zone );
	}
}
