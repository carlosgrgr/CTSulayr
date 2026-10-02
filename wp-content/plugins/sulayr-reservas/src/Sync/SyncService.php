<?php

namespace SulayrReservas\Sync;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\RoleRegistrar;
use SulayrReservas\Swimmers\SwimmerRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Copia el estado de un deportista del club a la ficha local de reservas.
 *
 * Lee el club y escribe solo la tabla de nadadores. No cambia la contraseña
 * ni las metas del perfil del club.
 */
class SyncService {

	/** @var BlogContext */
	private $blog_context;

	/** @var SyncLog */
	private $log;

	/** @var bool */
	private static $running = false;

	/**
	 * @param BlogContext    $blog_context Cambio de sitio con restauración.
	 * @param SyncLog|null   $log          Registro técnico.
	 */
	public function __construct( BlogContext $blog_context, ?SyncLog $log = null ) {
		$this->blog_context = $blog_context;
		$this->log          = $log ? $log : new SyncLog( $blog_context );
	}

	/**
	 * @param int    $user_id         Usuario global.
	 * @param string $origin          automatic o manual.
	 * @param bool   $force_inactive  True cuando acaba de salir del club o de perder `athlete`.
	 * @return SyncResult
	 */
	public function sync( $user_id, $origin, $force_inactive = false ) {
		if ( self::$running ) {
			return new SyncResult( SyncResult::OK, SyncResult::UNCHANGED, 'La sincronización ya estaba en curso.' );
		}

		self::$running = true;

		try {
			$result = $this->blog_context->onClub(
				function () use ( $user_id, $origin, $force_inactive ) {
					return $this->syncOnClub( (int) $user_id, (string) $origin, (bool) $force_inactive );
				}
			);
		} catch ( \Throwable $exception ) {
			$result = new SyncResult( SyncResult::FAILED, SyncResult::FAILED, 'No se ha podido completar la sincronización.' );
			$this->record( (int) $user_id, '', $origin, SyncResult::FAILED, SyncResult::FAILED, $result->message() );
		} finally {
			self::$running = false;
		}

		return $result;
	}

	/**
	 * @param int    $user_id         Usuario.
	 * @param string $origin          Origen.
	 * @param bool   $force_inactive  No usar las condiciones actuales del club.
	 * @return SyncResult
	 */
	private function syncOnClub( $user_id, $origin, $force_inactive ) {
		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			$result = new SyncResult( SyncResult::FAILED, SyncResult::FAILED, 'El usuario no existe.' );
			$this->record( $user_id, '', $origin, SyncResult::FAILED, SyncResult::FAILED, $result->message() );

			return $result;
		}

		$profile = $this->profile( $user );
		if ( $force_inactive ) {
			$profile['eligible'] = false;
		}
		$name    = trim( $profile['first_name'] . ' ' . $profile['last_name'] );
		if ( '' === $name ) {
			$name = $user->user_login;
		}

		try {
			$applied = $this->blog_context->onBookings(
				function () use ( $profile ) {
					return $this->apply( $profile );
				}
			);
		} catch ( \Throwable $exception ) {
			$result = new SyncResult( SyncResult::FAILED, SyncResult::FAILED, 'No se ha podido completar la sincronización.' );
			$this->record( $user_id, $name, $origin, SyncResult::FAILED, SyncResult::FAILED, $result->message() );

			return $result;
		}

		$this->record( $user_id, $name, $origin, SyncResult::OK, $applied->action(), '' );

		return $applied;
	}

	/**
	 * @param int    $user_id Usuario.
	 * @param string $name    Nombre.
	 * @param string $origin  Origen.
	 * @param string $result  Resultado.
	 * @param string $action  Acción.
	 * @param string $error   Mensaje.
	 * @return void
	 */
	private function record( $user_id, $name, $origin, $result, $action, $error ) {
		try {
			$this->log->write( $user_id, $name, $origin, $result, $action, $error );
		} catch ( \Throwable $exception ) {
			return;
		}
	}

	/**
	 * @param \WP_User $user Usuario leído en el club.
	 * @return array<string, int|string|bool>
	 */
	private function profile( \WP_User $user ) {
		$swimming = (string) get_user_meta( $user->ID, 'sulayr_user_swimming', true );
		$status   = (string) get_user_meta( $user->ID, 'sulayr_user_athlete_status', true );
		$athlete  = in_array( 'athlete', (array) $user->roles, true );

		return array(
			'user_id'    => (int) $user->ID,
			'eligible'   => $athlete && '1' === $swimming && 'active' === $status,
			'first_name' => $this->text( get_user_meta( $user->ID, 'first_name', true ), 100 ),
			'last_name'  => $this->text( get_user_meta( $user->ID, 'last_name', true ), 100 ),
			'email'      => $this->email( $user->user_email ),
			'phone'      => $this->text( get_user_meta( $user->ID, 'sulayr_user_phone', true ), 50 ),
			'dni'        => $this->text( get_user_meta( $user->ID, 'sulayr_user_dni', true ), 20 ),
		);
	}

	/**
	 * @param array<string, int|string|bool> $profile Perfil leído en el club.
	 * @return SyncResult
	 */
	private function apply( array $profile ) {
		$repository = new SwimmerRepository();
		$current    = $repository->find( (int) $profile['user_id'] );
		$stamp      = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
		$row        = array(
			'user_id'    => (int) $profile['user_id'],
			'first_name' => (string) $profile['first_name'],
			'last_name'  => (string) $profile['last_name'],
			'email'      => (string) $profile['email'],
			'phone'      => (string) $profile['phone'],
			'dni'        => (string) $profile['dni'],
			'stamp'      => $stamp,
		);

		if ( $profile['eligible'] ) {
			$this->ensureMember( (int) $profile['user_id'] );

			if ( ! $current ) {
				if ( $repository->insertSynced( $row ) ) {
					return new SyncResult( SyncResult::OK, SyncResult::CREATED, 'El deportista se ha incorporado a reservas.' );
				}

				$current = $repository->find( (int) $profile['user_id'] );
				if ( ! $current ) {
					throw new \RuntimeException( 'No se pudo crear la ficha.' );
				}
			}

			$was_inactive = 1 !== (int) $current->active;
			if ( ! $was_inactive && $this->sameData( $current, $row ) && 1 === (int) $current->linked_to_club ) {
				return new SyncResult( SyncResult::OK, SyncResult::UNCHANGED, 'No había cambios que aplicar.' );
			}

			$repository->updateSynced( (int) $profile['user_id'], $row );

			if ( $was_inactive ) {
				return new SyncResult( SyncResult::OK, SyncResult::ACTIVATED, 'El deportista ha vuelto a quedar activo en reservas.' );
			}

			return new SyncResult( SyncResult::OK, SyncResult::UPDATED, 'Los datos del deportista se han actualizado en reservas.' );
		}

		if ( ! $current || 1 !== (int) $current->active ) {
			return new SyncResult( SyncResult::OK, SyncResult::UNCHANGED, 'No había cambios que aplicar.' );
		}

		$this->deactivate( $repository, (int) $profile['user_id'], $stamp );

		return new SyncResult( SyncResult::OK, SyncResult::DEACTIVATED, 'El deportista ha quedado desactivado en reservas.' );
	}

	/**
	 * @param int $user_id Usuario.
	 * @return void
	 */
	private function ensureMember( $user_id ) {
		$blog_id = get_current_blog_id();

		if ( ! is_user_member_of_blog( $user_id, $blog_id ) ) {
			add_user_to_blog( $blog_id, $user_id, RoleRegistrar::ROLE_NADADOR );

			return;
		}

		$user = get_userdata( $user_id );
		if ( $user instanceof \WP_User && ! in_array( RoleRegistrar::ROLE_NADADOR, (array) $user->roles, true ) ) {
			$user->add_role( RoleRegistrar::ROLE_NADADOR );
		}
	}

	/**
	 * @param SwimmerRepository  $repository Ficha del blog de reservas.
	 * @param int                $user_id    Usuario.
	 * @param string             $stamp      Momento.
	 * @return void
	 */
	private function deactivate( SwimmerRepository $repository, $user_id, $stamp ) {
		$now = new \DateTimeImmutable( 'now', wp_timezone() );
		$ids = array();

		foreach ( $repository->bookings( $user_id ) as $booking ) {
			$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $booking->session_date . ' ' . $booking->start_time, wp_timezone() );
			if ( $start instanceof \DateTimeImmutable && $now < $start ) {
				$ids[] = (int) $booking->id;
			}
		}

		$repository->deleteBookings( $ids );
		$repository->setActive( $user_id, 0, $stamp );
	}

	/**
	 * @param object                    $current Ficha actual.
	 * @param array<string, int|string> $row     Datos nuevos.
	 * @return bool
	 */
	private function sameData( $current, array $row ) {
		return (string) $current->first_name === (string) $row['first_name']
			&& (string) $current->last_name === (string) $row['last_name']
			&& (string) $current->email === (string) $row['email']
			&& (string) $current->phone === (string) $row['phone']
			&& (string) $current->dni === (string) $row['dni'];
	}

	/**
	 * @param mixed $value Valor de meta.
	 * @param int   $limit Longitud de columna.
	 * @return string
	 */
	private function text( $value, $limit ) {
		$text = sanitize_text_field( (string) $value );
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( $text, 0, $limit );
		}

		return substr( $text, 0, $limit );
	}

	/**
	 * @param string $email Correo de la cuenta.
	 * @return string
	 */
	private function email( $email ) {
		$email = sanitize_email( (string) $email );

		return strlen( $email ) > 100 ? substr( $email, 0, 100 ) : $email;
	}
}
