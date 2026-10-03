<?php

namespace SulayrReservas\Bookings;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Swimmers\WeeklyLimit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea y cancela reservas en el sitio de reservas.
 *
 * El actor es el usuario actual. Las comprobaciones de permiso se hacen en el blog 2.
 */
class BookingService {

	/** @var BlogContext */
	private $blog_context;

	/** @var BookingRepository */
	private $repository;

	/**
	 * @param BlogContext            $blog_context Cambio de sitio con restauración.
	 * @param BookingRepository|null $repository   Persistencia de reservas.
	 */
	public function __construct( BlogContext $blog_context, ?BookingRepository $repository = null ) {
		$this->blog_context = $blog_context;
		$this->repository   = $repository ? $repository : new BookingRepository();
	}

	/**
	 * Reserva una sesión para un nadador activo.
	 *
	 * Si la sesión ya ha comenzado, solo un gestor puede registrarla y queda marcada como manual.
	 *
	 * @param int                     $session_id       Sesión.
	 * @param int                     $swimmer_user_id  Nadador. Si coincide con el actor, es una reserva propia.
	 * @param \DateTimeImmutable|null $now              Fecha de referencia. Si es null, ahora en la zona del sitio.
	 * @return BookingResult
	 */
	public function book( $session_id, $swimmer_user_id, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $session_id, $swimmer_user_id, $now ) {
				return $this->bookOnBookings( (int) $session_id, (int) $swimmer_user_id, $now );
			}
		);
	}

	/**
	 * Elimina una reserva y libera su plaza.
	 *
	 * @param int                     $booking_id Reserva.
	 * @param \DateTimeImmutable|null $now        Fecha de referencia.
	 * @return BookingResult
	 */
	public function cancel( $booking_id, ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $booking_id, $now ) {
				return $this->cancelOnBookings( (int) $booking_id, $now );
			}
		);
	}

	/**
	 * @param int                     $session_id      Sesión.
	 * @param int                     $swimmer_user_id Nadador.
	 * @param \DateTimeImmutable|null $now             Fecha de referencia.
	 * @return BookingResult
	 */
	private function bookOnBookings( $session_id, $swimmer_user_id, ?\DateTimeImmutable $now ) {
		$actor_id = get_current_user_id();
		if ( $actor_id < 1 || $session_id < 1 || $swimmer_user_id < 1 ) {
			return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$for_other = $swimmer_user_id !== $actor_id;
		if ( $for_other && ! current_user_can( Capabilities::BOOK_FOR_OTHERS ) ) {
			return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( ! $for_other && ! current_user_can( Capabilities::BOOK_SELF ) && ! current_user_can( Capabilities::BOOK_FOR_OTHERS ) ) {
			return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		try {
			$this->begin();
			$swimmer = $this->repository->lockSwimmer( $swimmer_user_id );
			if ( ! $swimmer || 1 !== (int) $swimmer->active ) {
				$this->rollback();
				return $this->result( BookingResult::SWIMMER_INACTIVE, 'El usuario no está activo en reservas.' );
			}

			$session = $this->repository->lockSession( $session_id );
			if ( ! $session ) {
				$this->rollback();
				return $this->result( BookingResult::SESSION_MISSING, 'La sesión no existe.' );
			}

			if ( $this->repository->findUserBookingId( $session_id, $swimmer_user_id ) > 0 ) {
				$this->rollback();
				return $this->result( BookingResult::DUPLICATE, 'Ya existe una reserva para esta sesión.' );
			}

			$now     = $this->clock( $now );
			$start   = $this->sessionStart( $session );
			$started = $now >= $start;
			$manual  = false;

			if ( $started ) {
				if ( ! current_user_can( Capabilities::BOOK_FOR_OTHERS ) ) {
					$this->rollback();
					return $this->result( BookingResult::SESSION_STARTED, 'La sesión ya ha comenzado.' );
				}
				$manual = true;
			} elseif ( 1 !== (int) $session->enabled ) {
				$this->rollback();
				return $this->result( BookingResult::SESSION_DISABLED, 'La sesión no admite reservas.' );
			}

			if ( $this->repository->countSessionBookings( $session_id ) >= (int) $session->capacity ) {
				$this->rollback();
				return $this->result( BookingResult::FULL, 'No quedan plazas.' );
			}

			$week  = $this->weekBounds( $session->session_date );
			$limit = WeeklyLimit::normalize( isset( $swimmer->weekly_limit ) ? $swimmer->weekly_limit : WeeklyLimit::DEFAULT_LIMIT );
			if ( $this->repository->countUserBookingsBetween( $swimmer_user_id, $week['from'], $week['to'] ) >= $limit ) {
				$this->rollback();
				return $this->result( BookingResult::WEEKLY_LIMIT, 'Se ha alcanzado el máximo de reservas de la semana.' );
			}

			$booking_id = $this->repository->insertBooking(
				array(
					'session_id' => $session_id,
					'user_id'    => $swimmer_user_id,
					'first_name' => (string) $swimmer->first_name,
					'last_name'  => (string) $swimmer->last_name,
					'phone'      => (string) $swimmer->phone,
					'email'      => (string) $swimmer->email,
					'created_at' => $now->format( 'Y-m-d H:i:s' ),
					'created_by' => $actor_id,
					'is_manual'  => $manual ? 1 : 0,
					'manager_id' => $manual ? $actor_id : null,
				)
			);

			if ( $booking_id < 1 ) {
				$this->rollback();
				return $this->result( BookingResult::DUPLICATE, 'Ya existe una reserva para esta sesión.' );
			}

			$this->commit();
			return $this->result( BookingResult::CREATED, 'Reserva creada.', $booking_id );
		} catch ( \Throwable $exception ) {
			$this->rollback();
			return $this->result( BookingResult::FAILED, 'No se ha podido completar la operación.' );
		}
	}

	/**
	 * @param int                     $booking_id Reserva.
	 * @param \DateTimeImmutable|null $now        Fecha de referencia.
	 * @return BookingResult
	 */
	private function cancelOnBookings( $booking_id, ?\DateTimeImmutable $now ) {
		$actor_id = get_current_user_id();
		if ( $actor_id < 1 || $booking_id < 1 ) {
			return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}
		if ( ! current_user_can( Capabilities::CANCEL_OWN ) && ! current_user_can( Capabilities::CANCEL_ANY ) ) {
			return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		try {
			$preview = $this->repository->findBooking( $booking_id );
			if ( ! $preview ) {
				return $this->result( BookingResult::BOOKING_MISSING, 'La reserva no existe.' );
			}

			$can_any = current_user_can( Capabilities::CANCEL_ANY );
			$owner   = (int) $preview->user_id;
			if ( ! $can_any && $owner !== $actor_id ) {
				return $this->result( BookingResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
			}

			$this->begin();

			if ( $owner > 0 ) {
				$this->repository->lockSwimmer( $owner );
			}

			$session = $this->repository->lockSession( (int) $preview->session_id );
			$booking = $this->repository->lockBooking( $booking_id );
			if ( ! $booking || ! $session ) {
				$this->rollback();
				return $this->result( BookingResult::BOOKING_MISSING, 'La reserva no existe.' );
			}

			if ( ! $can_any ) {
				$now       = $this->clock( $now );
				$deadline  = $this->sessionStart( $session )->modify( '-60 minutes' );
				if ( $now > $deadline ) {
					$this->rollback();
					return $this->result( BookingResult::TOO_LATE, 'Ya no se puede cancelar esta reserva.' );
				}
			}

			$this->repository->deleteBooking( $booking_id );
			$this->commit();
			return $this->result( BookingResult::CANCELLED, 'Reserva cancelada.', $booking_id );
		} catch ( \Throwable $exception ) {
			$this->rollback();
			return $this->result( BookingResult::FAILED, 'No se ha podido completar la operación.' );
		}
	}

	/**
	 * @param string $status     Estado.
	 * @param string $message    Mensaje.
	 * @param int    $booking_id Reserva.
	 * @return BookingResult
	 */
	private function result( $status, $message, $booking_id = 0 ) {
		return new BookingResult( $status, $message, $booking_id );
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

	/**
	 * @param object $session Fila de sesión.
	 * @return \DateTimeImmutable
	 */
	private function sessionStart( $session ) {
		$start = \DateTimeImmutable::createFromFormat(
			'Y-m-d H:i:s',
			$session->session_date . ' ' . $session->start_time,
			wp_timezone()
		);

		if ( ! $start instanceof \DateTimeImmutable ) {
			throw new \RuntimeException( 'La sesión no tiene una fecha válida.' );
		}

		return $start;
	}

	/**
	 * Lunes y domingo de la semana de la sesión, en la zona del sitio.
	 *
	 * @param string $session_date Fecha `Y-m-d`.
	 * @return array{from: string, to: string}
	 */
	private function weekBounds( $session_date ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $session_date, wp_timezone() );
		if ( ! $date instanceof \DateTimeImmutable ) {
			throw new \RuntimeException( 'La sesión no tiene una fecha válida.' );
		}

		$monday = $date->modify( '-' . ( (int) $date->format( 'N' ) - 1 ) . ' days' );

		return array(
			'from' => $monday->format( 'Y-m-d' ),
			'to'   => $monday->modify( '+6 days' )->format( 'Y-m-d' ),
		);
	}
}
