<?php

namespace SulayrReservas\Sessions;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea las sesiones de un mes en el sitio de reservas.
 *
 * No actualiza sesiones ya existentes: conserva capacidad, estado y reservas.
 */
class SessionGenerator {

	/** @var BlogContext */
	private $blog_context;

	/** @var Schedule */
	private $schedule;

	/** @var GenerationWindow */
	private $window;

	/**
	 * @param BlogContext           $blog_context Cambio de sitio con restauración.
	 * @param Schedule|null         $schedule     Horario fijo.
	 * @param GenerationWindow|null $window       Ventana de generación.
	 */
	public function __construct( BlogContext $blog_context, ?Schedule $schedule = null, ?GenerationWindow $window = null ) {
		$this->blog_context = $blog_context;
		$this->schedule     = $schedule ? $schedule : new Schedule();
		$this->window       = $window ? $window : new GenerationWindow();
	}

	/**
	 * Mes que correspondería al botón en esta fecha.
	 *
	 * @param \DateTimeImmutable|null $now Fecha de referencia. Si es null, ahora en la zona del sitio.
	 * @return string|null
	 */
	public function offeredMonth( ?\DateTimeImmutable $now = null ) {
		return $this->blog_context->onBookings(
			function () use ( $now ) {
				$now     = $this->clock( $now );
				$current = $now->format( 'Y-m' );

				return $this->window->offeredMonth( $now, $this->monthExists( $current ) );
			}
		);
	}

	/**
	 * Genera el mes si la ventana y el permiso lo permiten.
	 *
	 * Una repetición no modifica filas existentes. Si la ejecución anterior se interrumpió,
	 * inserta solo las sesiones que falten.
	 *
	 * @param string                  $year_month Mes `YYYY-MM`.
	 * @param int                     $actor_id   Usuario que genera. Debe poder `sulayr_manage_sessions` en reservas.
	 * @param \DateTimeImmutable|null $now        Fecha de referencia.
	 * @return GenerationResult
	 */
	public function generate( $year_month, $actor_id, ?\DateTimeImmutable $now = null ) {
		$year_month = (string) $year_month;

		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', $year_month ) ) {
			return new GenerationResult( GenerationResult::REJECTED, $year_month, 0, 'El mes no tiene el formato YYYY-MM.' );
		}

		return $this->blog_context->onBookings(
			function () use ( $year_month, $actor_id, $now ) {
				return $this->generateOnBookings( $year_month, (int) $actor_id, $now );
			}
		);
	}

	/**
	 * @param string                  $year_month Mes ya validado.
	 * @param int                     $actor_id   Usuario que genera.
	 * @param \DateTimeImmutable|null $now        Fecha de referencia.
	 * @return GenerationResult
	 */
	private function generateOnBookings( $year_month, $actor_id, ?\DateTimeImmutable $now = null ) {
		if ( ! $this->actorCanGenerate( $actor_id ) ) {
			return new GenerationResult( GenerationResult::REJECTED, $year_month, 0, 'No tienes permiso para generar sesiones.' );
		}

		$now    = $this->clock( $now );
		$exists = $this->monthExists( $year_month );
		$has_sessions = $this->monthHasSessions( $year_month );

		if ( ! $exists && ! $has_sessions && ! $this->monthIsOffered( $year_month, $now ) ) {
			return new GenerationResult( GenerationResult::REJECTED, $year_month, 0, 'Ese mes no está dentro de la ventana de generación.' );
		}

		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		try {
			if ( ! $exists ) {
				$this->insertMonth( $year_month, $actor_id, $now );
			}

			$created = $this->insertMissingSessions( $year_month, $now );
			$this->beforeCommit( $year_month );
			$wpdb->query( 'COMMIT' );
		} catch ( \Exception $exception ) {
			$wpdb->query( 'ROLLBACK' );

			return new GenerationResult( GenerationResult::FAILED, $year_month, 0, $exception->getMessage() );
		}

		if ( ! $exists && ! $has_sessions ) {
			return new GenerationResult( GenerationResult::CREATED, $year_month, $created, 'Sesiones generadas.' );
		}

		if ( 0 === $created && $exists ) {
			return new GenerationResult( GenerationResult::UNCHANGED, $year_month, 0, 'El mes ya estaba generado.' );
		}

		return new GenerationResult( GenerationResult::REPAIRED, $year_month, $created, 'Se completaron las sesiones que faltaban.' );
	}

	/**
	 * Punto para comprobar una interrupción. No hace nada en producción.
	 *
	 * @param string $year_month Mes en curso.
	 * @return void
	 */
	protected function beforeCommit( $year_month ) {
	}

	/**
	 * @param int $actor_id Usuario que genera.
	 * @return bool
	 */
	private function actorCanGenerate( $actor_id ) {
		if ( $actor_id < 1 ) {
			return false;
		}

		$user = get_userdata( $actor_id );

		return $user instanceof \WP_User && $user->has_cap( Capabilities::MANAGE_SESSIONS );
	}

	/**
	 * @param \DateTimeImmutable|null $now Fecha recibida.
	 * @return \DateTimeImmutable
	 */
	private function clock( ?\DateTimeImmutable $now = null ) {
		$zone = wp_timezone();

		if ( null === $now ) {
			return new \DateTimeImmutable( 'now', $zone );
		}

		return $now->setTimezone( $zone );
	}

	/**
	 * @param string             $year_month Mes pedido.
	 * @param \DateTimeImmutable $now        Fecha de referencia.
	 * @return bool
	 */
	private function monthIsOffered( $year_month, \DateTimeImmutable $now ) {
		$current = $now->format( 'Y-m' );
		$offered = $this->window->offeredMonth( $now, $this->monthExists( $current ) );

		return $offered === $year_month;
	}

	/**
	 * @param string $year_month Mes `YYYY-MM`.
	 * @return bool
	 */
	private function monthExists( $year_month ) {
		global $wpdb;

		$found = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT generated_month FROM ' . $this->monthsTable() . ' WHERE generated_month = %s',
				$year_month
			)
		);

		return $found === $year_month;
	}

	/**
	 * @param string $year_month Mes `YYYY-MM`.
	 * @return bool
	 */
	private function monthHasSessions( $year_month ) {
		global $wpdb;

		$last_day = \DateTimeImmutable::createFromFormat( '!Y-m-d', $year_month . '-01', wp_timezone() )
			->modify( 'last day of this month' )
			->format( 'Y-m-d' );
		$count    = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->sessionsTable() . ' WHERE session_date BETWEEN %s AND %s',
				$year_month . '-01',
				$last_day
			)
		);

		return $count > 0;
	}

	/**
	 * @param string             $year_month Mes `YYYY-MM`.
	 * @param int                $actor_id   Usuario que genera.
	 * @param \DateTimeImmutable $now        Fecha de la operación.
	 * @return void
	 */
	private function insertMonth( $year_month, $actor_id, \DateTimeImmutable $now ) {
		global $wpdb;

		$suppressed = $wpdb->suppress_errors( true );
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->monthsTable() . ' (generated_month, generated_at, generated_by) VALUES (%s, %s, %d)',
				$year_month,
				$now->format( 'Y-m-d H:i:s' ),
				$actor_id
			)
		);
		$wpdb->suppress_errors( $suppressed );

		if ( false === $inserted && ! $this->isDuplicateError() ) {
			throw new \RuntimeException( 'No se pudo registrar el mes generado.' );
		}

		$this->clearLastError();
	}

	/**
	 * @param string             $year_month Mes `YYYY-MM`.
	 * @param \DateTimeImmutable $now        Fecha de la operación.
	 * @return int Sesiones nuevas.
	 */
	private function insertMissingSessions( $year_month, \DateTimeImmutable $now ) {
		$created = 0;
		$stamp   = $now->format( 'Y-m-d H:i:s' );

		foreach ( $this->schedule->slotsForMonth( $year_month, wp_timezone() ) as $slot ) {
			if ( $this->insertSession( $slot, $stamp ) ) {
				++$created;
			}
		}

		return $created;
	}

	/**
	 * @param array<string, string> $slot  Fecha y horas.
	 * @param string                $stamp Momento de creación.
	 * @return bool True si la fila es nueva.
	 */
	private function insertSession( array $slot, $stamp ) {
		global $wpdb;

		$suppressed = $wpdb->suppress_errors( true );
		$inserted   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO ' . $this->sessionsTable() . ' (session_date, start_time, end_time, capacity, enabled, created_at, updated_at) VALUES (%s, %s, %s, %d, %d, %s, %s)',
				$slot['session_date'],
				$slot['start_time'],
				$slot['end_time'],
				Schedule::CAPACITY,
				1,
				$stamp,
				$stamp
			)
		);
		$wpdb->suppress_errors( $suppressed );

		if ( false === $inserted ) {
			if ( $this->isDuplicateError() ) {
				$this->clearLastError();
				return false;
			}

			throw new \RuntimeException( 'No se pudo crear una sesión.' );
		}

		return true;
	}

	/**
	 * @return bool
	 */
	private function isDuplicateError() {
		global $wpdb;

		return '' !== $wpdb->last_error && false !== stripos( $wpdb->last_error, 'Duplicate' );
	}

	/**
	 * @return void
	 */
	private function clearLastError() {
		global $wpdb;

		$wpdb->last_error = '';
	}

	/**
	 * @return string
	 */
	private function sessionsTable() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_sessions';
	}

	/**
	 * @return string
	 */
	private function monthsTable() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_months';
	}
}
