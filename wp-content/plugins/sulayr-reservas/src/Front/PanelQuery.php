<?php

namespace SulayrReservas\Front;

use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Lecturas del panel. No reserva, no cancela y no cambia fichas.
 */
class PanelQuery {

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param BlogContext $blog_context Cambio de sitio con restauración.
	 */
	public function __construct( BlogContext $blog_context ) {
		$this->blog_context = $blog_context;
	}

	/**
	 * @param int $user_id Usuario.
	 * @return bool
	 */
	public function isActiveSwimmer( $user_id ) {
		$row = $this->swimmer( $user_id );

		return $row && 1 === (int) $row->active;
	}

	/**
	 * @param int $user_id Usuario.
	 * @return object|null
	 */
	public function swimmer( $user_id ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id ) {
				global $wpdb;

				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT user_id, active, linked_to_club, first_name, last_name, email, phone, dni FROM ' . $this->swimmersTable() . ' WHERE user_id = %d',
						(int) $user_id
					)
				);
			}
		);
	}

	/**
	 * @param string $year_month Mes `YYYY-MM`.
	 * @param int    $viewer_id  Usuario que mira el calendario.
	 * @return object[]
	 */
	public function monthSessions( $year_month, $viewer_id ) {
		return $this->blog_context->onBookings(
			function () use ( $year_month, $viewer_id ) {
				$bounds = $this->bounds( $year_month );
				if ( null === $bounds ) {
					return array();
				}

				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT s.id, s.session_date, s.start_time, s.end_time, s.capacity, s.enabled, (SELECT COUNT(*) FROM ' . $this->bookings() . ' b WHERE b.session_id = s.id) AS occupied, (SELECT b2.id FROM ' . $this->bookings() . ' b2 WHERE b2.session_id = s.id AND b2.user_id = %d LIMIT 1) AS own_booking_id FROM ' . $this->sessions() . ' s WHERE s.session_date BETWEEN %s AND %s ORDER BY s.session_date ASC, s.start_time ASC',
						(int) $viewer_id,
						$bounds['from'],
						$bounds['to']
					)
				);

				return is_array( $rows ) ? $rows : array();
			}
		);
	}

	/**
	 * @param int $session_id Sesión.
	 * @param int $viewer_id  Usuario que mira.
	 * @return object|null
	 */
	public function session( $session_id, $viewer_id ) {
		return $this->blog_context->onBookings(
			function () use ( $session_id, $viewer_id ) {
				global $wpdb;

				return $wpdb->get_row(
					$wpdb->prepare(
						'SELECT s.id, s.session_date, s.start_time, s.end_time, s.capacity, s.enabled, (SELECT COUNT(*) FROM ' . $this->bookings() . ' b WHERE b.session_id = s.id) AS occupied, (SELECT b2.id FROM ' . $this->bookings() . ' b2 WHERE b2.session_id = s.id AND b2.user_id = %d LIMIT 1) AS own_booking_id FROM ' . $this->sessions() . ' s WHERE s.id = %d',
						(int) $viewer_id,
						(int) $session_id
					)
				);
			}
		);
	}

	/**
	 * Asistentes de una sesión. Solo quien puede ver todas las reservas.
	 *
	 * @param int $session_id Sesión.
	 * @return object[]
	 */
	public function attendees( $session_id ) {
		return $this->blog_context->onBookings(
			function () use ( $session_id ) {
				if ( ! current_user_can( Capabilities::VIEW_ALL_BOOKINGS ) ) {
					return array();
				}

				global $wpdb;

				$rows = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT id, user_id, first_name, last_name, phone, email, is_manual FROM ' . $this->bookings() . ' WHERE session_id = %d ORDER BY last_name ASC, first_name ASC, id ASC',
						(int) $session_id
					)
				);

				return is_array( $rows ) ? $rows : array();
			}
		);
	}

	/**
	 * @param int                $user_id Usuario.
	 * @param \DateTimeImmutable $now     Momento de referencia.
	 * @return array{active: object[], past: object[]}
	 */
	public function bookingsFor( $user_id, \DateTimeImmutable $now ) {
		$rows = $this->blog_context->onBookings(
			function () use ( $user_id ) {
				if ( ! $this->canSeeUser( (int) $user_id ) ) {
					return array();
				}

				global $wpdb;

				$found = $wpdb->get_results(
					$wpdb->prepare(
						'SELECT b.id, b.user_id, b.first_name, b.last_name, b.phone, b.email, b.is_manual, s.id AS session_id, s.session_date, s.start_time, s.end_time FROM ' . $this->bookings() . ' b INNER JOIN ' . $this->sessions() . ' s ON s.id = b.session_id WHERE b.user_id = %d ORDER BY s.session_date DESC, s.start_time DESC',
						(int) $user_id
					)
				);

				return is_array( $found ) ? $found : array();
			}
		);

		$active = array();
		$past   = array();
		foreach ( $rows as $row ) {
			$start = $this->start( $row );
			if ( $start instanceof \DateTimeImmutable && $now < $start ) {
				$active[] = $row;
			} else {
				$past[] = $row;
			}
		}

		return array(
			'active' => $active,
			'past'   => $past,
		);
	}

	/**
	 * @return object[]
	 */
	public function swimmers() {
		return $this->blog_context->onBookings(
			function () {
				if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
					return array();
				}

				global $wpdb;

				$rows = $wpdb->get_results( 'SELECT user_id, active, linked_to_club, first_name, last_name, email, phone FROM ' . $this->swimmersTable() . ' ORDER BY last_name ASC, first_name ASC, user_id ASC' );

				return is_array( $rows ) ? $rows : array();
			}
		);
	}

	/**
	 * Meses anteriores al actual que ya tienen sesiones.
	 *
	 * @param string $current Mes `YYYY-MM`.
	 * @return string[]
	 */
	public function pastMonths( $current ) {
		return $this->blog_context->onBookings(
			function () use ( $current ) {
				if ( ! current_user_can( Capabilities::VIEW_ALL_BOOKINGS ) ) {
					return array();
				}

				global $wpdb;

				$rows = $wpdb->get_col( 'SELECT DISTINCT DATE_FORMAT(session_date, \'%Y-%m\') FROM ' . $this->sessions() . ' ORDER BY session_date DESC' );
				$list = array();
				foreach ( (array) $rows as $month ) {
					if ( is_string( $month ) && $month < $current ) {
						$list[] = $month;
					}
				}

				return $list;
			}
		);
	}

	/**
	 * @param string $year_month Mes.
	 * @return bool
	 */
	public function monthHasSessions( $year_month ) {
		$bounds = $this->bounds( $year_month );
		if ( null === $bounds ) {
			return false;
		}

		return $this->blog_context->onBookings(
			function () use ( $bounds ) {
				global $wpdb;

				$count = (int) $wpdb->get_var(
					$wpdb->prepare(
						'SELECT COUNT(*) FROM ' . $this->sessions() . ' WHERE session_date BETWEEN %s AND %s',
						$bounds['from'],
						$bounds['to']
					)
				);

				return $count > 0;
			}
		);
	}

	/**
	 * @param int $user_id Usuario consultado.
	 * @return bool
	 */
	private function canSeeUser( $user_id ) {
		return get_current_user_id() === $user_id || current_user_can( Capabilities::VIEW_ALL_BOOKINGS );
	}

	/**
	 * @param string $year_month Mes `YYYY-MM`.
	 * @return array{from: string, to: string}|null
	 */
	private function bounds( $year_month ) {
		if ( ! preg_match( '/^\d{4}-(0[1-9]|1[0-2])$/', (string) $year_month ) ) {
			return null;
		}

		$start = \DateTimeImmutable::createFromFormat( '!Y-m-d', $year_month . '-01', wp_timezone() );
		if ( ! $start instanceof \DateTimeImmutable ) {
			return null;
		}

		return array(
			'from' => $start->format( 'Y-m-d' ),
			'to'   => $start->modify( 'last day of this month' )->format( 'Y-m-d' ),
		);
	}

	/**
	 * @param object $row Fila con fecha y hora.
	 * @return \DateTimeImmutable|null
	 */
	private function start( $row ) {
		$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $row->session_date . ' ' . $row->start_time, wp_timezone() );

		return $start instanceof \DateTimeImmutable ? $start : null;
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
	private function swimmersTable() {
		global $wpdb;

		return $wpdb->prefix . 'sulayr_swim_swimmers';
	}
}
