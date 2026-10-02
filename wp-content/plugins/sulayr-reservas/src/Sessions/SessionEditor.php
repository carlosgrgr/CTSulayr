<?php

namespace SulayrReservas\Sessions;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cambia la capacidad o el estado de una sesión ya creada.
 *
 * No borra reservas. Quien reserva sigue siendo BookingService.
 */
class SessionEditor {

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param BlogContext $blog_context Cambio de sitio con restauración.
	 */
	public function __construct( BlogContext $blog_context ) {
		$this->blog_context = $blog_context;
	}

	/**
	 * @param int $session_id Sesión.
	 * @param int $capacity   Plazas. Cero o más.
	 * @return SessionUpdateResult
	 */
	public function setCapacity( $session_id, $capacity ) {
		$session_id = (int) $session_id;
		$capacity   = (int) $capacity;

		if ( $capacity < 0 || $capacity > 65535 ) {
			return new SessionUpdateResult( SessionUpdateResult::INVALID, 'La capacidad no es válida.' );
		}

		return $this->update( $session_id, array( 'capacity' => $capacity ), 'Capacidad actualizada.' );
	}

	/**
	 * @param int  $session_id Sesión.
	 * @param bool $enabled    True para admitir reservas ordinarias.
	 * @return SessionUpdateResult
	 */
	public function setEnabled( $session_id, $enabled ) {
		return $this->update(
			(int) $session_id,
			array( 'enabled' => $enabled ? 1 : 0 ),
			$enabled ? 'Sesión habilitada.' : 'Sesión deshabilitada.'
		);
	}

	/**
	 * @param int                   $session_id Sesión.
	 * @param array<string, int>    $fields     Capacidad o estado.
	 * @param string                $message    Texto si la operación termina bien.
	 * @return SessionUpdateResult
	 */
	private function update( $session_id, array $fields, $message ) {
		return $this->blog_context->onBookings(
			function () use ( $session_id, $fields, $message ) {
				if ( get_current_user_id() < 1 || ! current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
					return new SessionUpdateResult( SessionUpdateResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
				}
				if ( $session_id < 1 ) {
					return new SessionUpdateResult( SessionUpdateResult::MISSING, 'La sesión no existe.' );
				}

				global $wpdb;

				$wpdb->query( 'START TRANSACTION' );
				$row = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $this->table() . ' WHERE id = %d FOR UPDATE', $session_id ) );
				if ( ! $row ) {
					$wpdb->query( 'ROLLBACK' );
					return new SessionUpdateResult( SessionUpdateResult::MISSING, 'La sesión no existe.' );
				}

				$fields['updated_at'] = ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d H:i:s' );
				$updated              = $wpdb->update( $this->table(), $fields, array( 'id' => $session_id ) );
				if ( false === $updated ) {
					$wpdb->query( 'ROLLBACK' );
					return new SessionUpdateResult( SessionUpdateResult::FAILED, 'No se ha podido completar la operación.' );
				}

				$wpdb->query( 'COMMIT' );

				return new SessionUpdateResult( SessionUpdateResult::UPDATED, $message );
			}
		);
	}

	/**
	 * @return string
	 */
	private function table() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_sessions';
	}
}
