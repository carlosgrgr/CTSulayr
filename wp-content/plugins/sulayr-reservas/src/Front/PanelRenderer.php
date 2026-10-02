<?php

namespace SulayrReservas\Front;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Sessions\SessionGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * HTML del panel. Las reglas de reserva las aplican los servicios al enviar el formulario.
 */
class PanelRenderer {

	/** @var SiteConfig */
	private $config;

	/** @var PanelQuery */
	private $query;

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param SiteConfig  $config       Sitios.
	 * @param BlogContext $blog_context Cambio de sitio.
	 */
	public function __construct( SiteConfig $config, BlogContext $blog_context ) {
		$this->config       = $config;
		$this->blog_context = $blog_context;
		$this->query        = new PanelQuery( $blog_context );
	}

	/**
	 * @param bool $manager True si el usuario gestiona reservas.
	 * @return string
	 */
	public function render( $manager ) {
		$now  = new \DateTimeImmutable( 'now', wp_timezone() );
		$view = $this->view( $manager );

		ob_start();
		echo '<div class="sulayr-panel">';
		$this->notice();
		$this->nav( $manager );
		if ( 'sesion' === $view ) {
			$this->session( $manager, $now );
		} elseif ( 'reservas' === $view ) {
			$this->bookings( get_current_user_id(), $now, false );
		} elseif ( 'perfil' === $view ) {
			$this->ownProfile();
		} elseif ( 'nadadores' === $view && $manager ) {
			$this->swimmers();
		} elseif ( 'nadador' === $view && $manager ) {
			$this->swimmerDetail( $now );
		} elseif ( 'alta' === $view && $manager ) {
			$this->createForm();
		} elseif ( 'historial' === $view && $manager ) {
			$this->history( $now );
		} else {
			$this->calendar( $manager, $now );
		}
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * @param bool $manager Gestor o administrador.
	 * @return string
	 */
	private function view( $manager ) {
		$view = isset( $_GET['sulayr_vista'] ) ? sanitize_key( wp_unslash( $_GET['sulayr_vista'] ) ) : 'calendario';
		$allowed = array( 'calendario', 'sesion', 'reservas', 'perfil' );
		if ( $manager ) {
			$allowed = array_merge( $allowed, array( 'nadadores', 'nadador', 'alta', 'historial' ) );
		}

		return in_array( $view, $allowed, true ) ? $view : 'calendario';
	}

	/**
	 * @return void
	 */
	private function notice() {
		$key    = 'sulayr_panel_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		delete_transient( $key );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		echo '<p class="sulayr-notice" role="status">' . esc_html( (string) $notice['message'] ) . '</p>';
	}

	/**
	 * @param bool $manager Gestor o administrador.
	 * @return void
	 */
	private function nav( $manager ) {
		echo '<nav class="sulayr-nav" aria-label="Reservas">';
		$this->link( 'Calendario', array() );
		$this->link( 'Mis reservas', array( 'sulayr_vista' => 'reservas' ) );
		$this->link( 'Mi perfil', array( 'sulayr_vista' => 'perfil' ) );
		if ( $manager ) {
			$this->link( 'Nadadores', array( 'sulayr_vista' => 'nadadores' ) );
			$this->link( 'Alta de nadador', array( 'sulayr_vista' => 'alta' ) );
			$this->link( 'Meses anteriores', array( 'sulayr_vista' => 'historial' ) );
		}
		echo '</nav>';
	}

	/**
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function calendar( $manager, \DateTimeImmutable $now ) {
		$month    = $this->visibleMonth( $manager, $now );
		$sessions = $this->query->monthSessions( $month, get_current_user_id() );
		echo '<h2>' . esc_html( $this->monthLabel( $month ) ) . '</h2>';
		if ( $manager ) {
			$this->generateForm( $now );
			$next = $now->modify( 'first day of next month' )->format( 'Y-m' );
			if ( $month === $now->format( 'Y-m' ) && $this->query->monthHasSessions( $next ) ) {
				$this->link( 'Ver ' . $this->monthLabel( $next ), array( 'mes' => $next ) );
			}
			if ( $month !== $now->format( 'Y-m' ) ) {
				$this->link( 'Volver al mes en curso', array() );
			}
		}
		if ( array() === $sessions ) {
			echo '<p>No hay sesiones en este mes.</p>';
			return;
		}

		echo '<ul class="sulayr-sessions">';
		foreach ( $sessions as $session ) {
			echo '<li class="sulayr-session">';
			echo '<p><strong>' . esc_html( $this->dayLabel( $session->session_date ) ) . '</strong> ';
			echo esc_html( $this->hour( $session->start_time ) . '–' . $this->hour( $session->end_time ) ) . '</p>';
			$this->sessionFacts( $session, $now );
			$this->link( 'Detalle', array( 'sulayr_vista' => 'sesion', 'sesion' => (int) $session->id ) );
			$this->quickActions( $session, $manager, $now );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function session( $manager, \DateTimeImmutable $now ) {
		$session_id = isset( $_GET['sesion'] ) ? absint( $_GET['sesion'] ) : 0;
		$session    = $this->query->session( $session_id, get_current_user_id() );
		if ( ! $session || ( ! $manager && substr( (string) $session->session_date, 0, 7 ) !== $now->format( 'Y-m' ) ) ) {
			echo '<p>La sesión no está disponible.</p>';
			return;
		}

		echo '<h2>' . esc_html( $this->dayLabel( $session->session_date ) . ' ' . $this->hour( $session->start_time ) ) . '</h2>';
		$this->sessionFacts( $session, $now );
		$this->quickActions( $session, $manager, $now );

		if ( $manager && current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
			$this->capacityForm( $session );
			$this->enabledForm( $session );
		}

		if ( $manager && current_user_can( Capabilities::BOOK_FOR_OTHERS ) ) {
			$this->bookForForm( $session );
		}

		if ( ! $manager ) {
			return;
		}

		$attendees = $this->query->attendees( (int) $session->id );
		echo '<h3>Reservas</h3>';
		if ( array() === $attendees ) {
			echo '<p>Nadie ha reservado esta sesión.</p>';
			return;
		}
		echo '<ul class="sulayr-people">';
		foreach ( $attendees as $attendee ) {
			echo '<li><span>' . esc_html( $attendee->first_name . ' ' . $attendee->last_name ) . '</span> ';
			echo '<span>' . esc_html( (string) $attendee->email ) . '</span> ';
			if ( 1 === (int) $attendee->is_manual ) {
				echo '<span>Añadida por un gestor</span> ';
			}
			if ( current_user_can( Capabilities::CANCEL_ANY ) ) {
				$this->post( 'cancel', 'Cancelar reserva', array( 'booking_id' => (int) $attendee->id ) );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * @param int                $user_id  Usuario.
	 * @param \DateTimeImmutable $now      Ahora.
	 * @param bool               $foreign  True si el gestor mira a otro nadador.
	 * @return void
	 */
	private function bookings( $user_id, \DateTimeImmutable $now, $foreign ) {
		if ( $foreign && ! current_user_can( Capabilities::VIEW_ALL_BOOKINGS ) ) {
			echo '<p>No tienes permiso para ver estas reservas.</p>';
			return;
		}

		$lists = $this->query->bookingsFor( $user_id, $now );
		echo '<h2>Reservas activas</h2>';
		$this->bookingList( $lists['active'], true, $now );
		echo '<h2>Historial</h2>';
		$this->bookingList( $lists['past'], false, $now );
	}

	/**
	 * @param object[]           $rows   Reservas.
	 * @param bool               $active True si aún no han empezado.
	 * @param \DateTimeImmutable $now    Ahora.
	 * @return void
	 */
	private function bookingList( array $rows, $active, \DateTimeImmutable $now ) {
		if ( array() === $rows ) {
			echo '<p>' . esc_html( $active ? 'No hay reservas activas.' : 'No hay historial.' ) . '</p>';
			return;
		}

		echo '<ul class="sulayr-sessions">';
		foreach ( $rows as $row ) {
			echo '<li><span>' . esc_html( $this->dayLabel( $row->session_date ) . ' ' . $this->hour( $row->start_time ) . '–' . $this->hour( $row->end_time ) ) . '</span> ';
			if ( 1 === (int) $row->is_manual ) {
				echo '<span>Añadida por un gestor</span> ';
			}
			$start    = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $row->session_date . ' ' . $row->start_time, wp_timezone() );
			$in_time  = $start instanceof \DateTimeImmutable && $now <= $start->modify( '-60 minutes' );
			$can_stop = current_user_can( Capabilities::CANCEL_ANY ) || ( current_user_can( Capabilities::CANCEL_OWN ) && $in_time );
			if ( $active && $can_stop ) {
				$this->post( 'cancel', 'Cancelar', array( 'booking_id' => (int) $row->id ) );
			}
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * @return void
	 */
	private function swimmers() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<p>No tienes permiso para ver los nadadores.</p>';
			return;
		}

		echo '<h2>Nadadores</h2>';
		$rows = $this->query->swimmers();
		if ( array() === $rows ) {
			echo '<p>No hay nadadores.</p>';
			return;
		}

		echo '<ul class="sulayr-people">';
		foreach ( $rows as $row ) {
			$state = 1 === (int) $row->active ? 'Activo' : 'Desactivado';
			$origin = 1 === (int) $row->linked_to_club ? 'Vinculado al club' : 'Alta manual';
			echo '<li><a href="' . esc_url( $this->url( array( 'sulayr_vista' => 'nadador', 'usuario' => (int) $row->user_id ) ) ) . '">' . esc_html( $row->first_name . ' ' . $row->last_name ) . '</a> ';
			echo '<span>' . esc_html( $state . '. ' . $origin ) . '</span></li>';
		}
		echo '</ul>';
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function swimmerDetail( \DateTimeImmutable $now ) {
		$user_id = isset( $_GET['usuario'] ) ? absint( $_GET['usuario'] ) : 0;
		$row     = $this->query->swimmer( $user_id );
		if ( ! $row || ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<p>El nadador no está disponible.</p>';
			return;
		}

		echo '<h2>' . esc_html( $row->first_name . ' ' . $row->last_name ) . '</h2>';
		echo '<p>' . esc_html( 1 === (int) $row->active ? 'Activo' : 'Desactivado' ) . '. ';
		echo esc_html( 1 === (int) $row->linked_to_club ? 'Vinculado al club.' : 'Alta manual.' ) . '</p>';
		echo '<dl class="sulayr-profile">';
		$this->pair( 'Correo', (string) $row->email );
		$this->pair( 'Teléfono', (string) $row->phone );
		$this->pair( 'DNI', (string) $row->dni );
		echo '</dl>';

		if ( 1 === (int) $row->active ) {
			$this->post( 'swimmer_deactivate', 'Desactivar', array( 'swimmer_user_id' => (int) $row->user_id ) );
		} else {
			$this->post( 'swimmer_reactivate', 'Reactivar', array( 'swimmer_user_id' => (int) $row->user_id ) );
		}
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="swimmer_remove">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'nadadores' ) ) ) . '">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $row->user_id ) . '">';
		echo '<label><input type="checkbox" name="confirm_remove" value="1"> Confirmo la retirada de este nadador del sitio de reservas</label> ';
		echo '<button type="submit">Retirar del sitio</button></form>';
		$this->bookings( (int) $row->user_id, $now, true );
	}

	/**
	 * @return void
	 */
	private function ownProfile() {
		$row = $this->query->swimmer( get_current_user_id() );
		if ( ! $row ) {
			echo '<p>No hay una ficha de nadador para esta cuenta.</p>';
			return;
		}

		echo '<h2>Mi perfil</h2>';
		echo '<p>' . esc_html( 1 === (int) $row->active ? 'Activo' : 'Desactivado' ) . '.</p>';
		echo '<dl class="sulayr-profile">';
		$this->pair( 'Nombre', (string) $row->first_name );
		$this->pair( 'Apellidos', (string) $row->last_name );
		$this->pair( 'Correo', (string) $row->email );
		$this->pair( 'Teléfono', (string) $row->phone );
		$this->pair( 'DNI', (string) $row->dni );
		echo '</dl>';
	}

	/**
	 * @return void
	 */
	private function createForm() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<p>No tienes permiso para dar de alta nadadores.</p>';
			return;
		}

		$key    = 'sulayr_panel_form_' . get_current_user_id();
		$stored = get_transient( $key );
		delete_transient( $key );
		$values = is_array( $stored ) ? $stored : array();
		$fields = array(
			'first_name' => 'Nombre',
			'last_name'  => 'Apellidos',
			'email'      => 'Correo',
			'phone'      => 'Teléfono',
			'dni'        => 'DNI',
		);

		echo '<h2>Alta de nadador</h2>';
		echo '<form method="post" action="' . esc_url( $this->url( array( 'sulayr_vista' => 'alta' ) ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="swimmer_create">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'alta' ) ) ) . '">';
		foreach ( $fields as $name => $label ) {
			$value = isset( $values[ $name ] ) ? (string) $values[ $name ] : '';
			$type  = 'email' === $name ? 'email' : 'text';
			echo '<label>' . esc_html( $label ) . ' <input type="' . esc_attr( $type ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" required></label>';
		}
		echo '<button type="submit">Crear nadador</button></form>';
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function history( \DateTimeImmutable $now ) {
		echo '<h2>Meses anteriores</h2>';
		$months = $this->query->pastMonths( $now->format( 'Y-m' ) );
		if ( array() === $months ) {
			echo '<p>No hay meses anteriores con sesiones.</p>';
			return;
		}
		echo '<ul>';
		foreach ( $months as $month ) {
			echo '<li><a href="' . esc_url( $this->url( array( 'mes' => $month ) ) ) . '">' . esc_html( $this->monthLabel( $month ) ) . '</a></li>';
		}
		echo '</ul>';
	}

	/**
	 * @param object             $session Sesión.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function sessionFacts( $session, \DateTimeImmutable $now ) {
		$occupied = (int) $session->occupied;
		$capacity = (int) $session->capacity;
		$free     = max( 0, $capacity - $occupied );
		$start    = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $session->session_date . ' ' . $session->start_time, wp_timezone() );
		$started  = $start instanceof \DateTimeImmutable && $now >= $start;
		$labels   = array();
		if ( 1 !== (int) $session->enabled ) {
			$labels[] = 'Deshabilitada';
		}
		if ( $occupied >= $capacity ) {
			$labels[] = 'Completa';
		}
		if ( $started ) {
			$labels[] = 'Ya ha comenzado';
		}
		if ( (int) $session->own_booking_id > 0 ) {
			$labels[] = 'Reservada por ti';
		}
		if ( array() === $labels && 1 === (int) $session->enabled ) {
			$labels[] = 'Disponible';
		}

		echo '<p>' . esc_html( implode( '. ', $labels ) ) . '.</p>';
		echo '<p>' . esc_html( $occupied . ' plazas ocupadas. ' . $free . ' plazas disponibles.' ) . '</p>';
	}

	/**
	 * @param object             $session Sesión.
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function quickActions( $session, $manager, \DateTimeImmutable $now ) {
		$start   = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $session->session_date . ' ' . $session->start_time, wp_timezone() );
		$started = $start instanceof \DateTimeImmutable && $now >= $start;
		$own     = (int) $session->own_booking_id;
		if ( $own > 0 ) {
			$in_time = $start instanceof \DateTimeImmutable && $now <= $start->modify( '-60 minutes' );
			if ( current_user_can( Capabilities::CANCEL_ANY ) || ( current_user_can( Capabilities::CANCEL_OWN ) && $in_time ) ) {
				$this->post( 'cancel', 'Cancelar mi reserva', array( 'booking_id' => $own ) );
			}
			return;
		}

		if ( $manager ) {
			return;
		}

		$full = (int) $session->occupied >= (int) $session->capacity;
		if ( ! $started && 1 === (int) $session->enabled && ! $full && current_user_can( Capabilities::BOOK_SELF ) ) {
			$this->post( 'book', 'Reservar', array( 'session_id' => (int) $session->id, 'swimmer_user_id' => get_current_user_id() ) );
		}
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function generateForm( \DateTimeImmutable $now ) {
		if ( ! current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
			return;
		}

		$month = ( new SessionGenerator( $this->blog_context ) )->offeredMonth( $now );
		if ( ! is_string( $month ) || '' === $month ) {
			echo '<p>Hoy no corresponde generar un mes.</p>';
			return;
		}

		$this->post( 'generate', 'Generar sesiones de ' . $this->monthLabel( $month ), array() );
	}

	/**
	 * @param object $session Sesión.
	 * @return void
	 */
	private function capacityForm( $session ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="capacity">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'sesion', 'sesion' => (int) $session->id ) ) ) . '">';
		echo '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session->id ) . '">';
		echo '<label>Capacidad <input type="number" name="capacity" min="0" max="65535" value="' . esc_attr( (string) $session->capacity ) . '" required></label> ';
		echo '<button type="submit">Guardar capacidad</button></form>';
	}

	/**
	 * @param object $session Sesión.
	 * @return void
	 */
	private function enabledForm( $session ) {
		$enable = 1 !== (int) $session->enabled;
		$this->post(
			'enabled',
			$enable ? 'Habilitar sesión' : 'Deshabilitar sesión',
			array(
				'session_id' => (int) $session->id,
				'enabled'    => $enable ? '1' : '0',
			),
			array( 'sulayr_vista' => 'sesion', 'sesion' => (int) $session->id )
		);
	}

	/**
	 * @param object $session Sesión.
	 * @return void
	 */
	private function bookForForm( $session ) {
		$swimmers = $this->query->swimmers();
		$options  = array();
		foreach ( $swimmers as $swimmer ) {
			if ( 1 === (int) $swimmer->active ) {
				$options[] = $swimmer;
			}
		}
		if ( array() === $options ) {
			return;
		}

		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="book">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'sesion', 'sesion' => (int) $session->id ) ) ) . '">';
		echo '<input type="hidden" name="session_id" value="' . esc_attr( (string) $session->id ) . '">';
		echo '<label>Reservar para <select name="swimmer_user_id">';
		foreach ( $options as $swimmer ) {
			echo '<option value="' . esc_attr( (string) $swimmer->user_id ) . '">' . esc_html( $swimmer->first_name . ' ' . $swimmer->last_name ) . '</option>';
		}
		echo '</select></label> <button type="submit">Reservar</button></form>';
	}

	/**
	 * @param string              $action Campos de la operación.
	 * @param string              $label  Texto del botón.
	 * @param array<string, int|string> $fields Campos ocultos.
	 * @param array<string, int|string> $return Argumentos de vuelta.
	 * @return void
	 */
	private function post( $action, $label, array $fields, array $return = array() ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-inline">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="' . esc_attr( $action ) . '">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( $return ) ) . '">';
		foreach ( $fields as $name => $value ) {
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '">';
		}
		echo '<button type="submit">' . esc_html( $label ) . '</button></form>';
	}

	/**
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return string
	 */
	private function visibleMonth( $manager, \DateTimeImmutable $now ) {
		$current = $now->format( 'Y-m' );
		if ( ! $manager || empty( $_GET['mes'] ) ) {
			return $current;
		}

		$month = sanitize_text_field( wp_unslash( $_GET['mes'] ) );
		$next  = $now->modify( 'first day of next month' )->format( 'Y-m' );
		if ( $month === $current ) {
			return $current;
		}
		if ( $month === $next && $this->query->monthHasSessions( $month ) ) {
			return $month;
		}
		if ( $month < $current && in_array( $month, $this->query->pastMonths( $current ), true ) ) {
			return $month;
		}

		return $current;
	}

	/**
	 * @param string               $label Texto.
	 * @param array<string, mixed> $args  Parámetros.
	 * @return void
	 */
	private function link( $label, array $args ) {
		echo '<a class="sulayr-link" href="' . esc_url( $this->url( $args ) ) . '">' . esc_html( $label ) . '</a> ';
	}

	/**
	 * @param array<string, mixed> $args Parámetros permitidos.
	 * @return string
	 */
	private function url( array $args ) {
		$base = ( new PanelPage( $this->config ) )->url();
		if ( '' === $base ) {
			$base = home_url( '/' );
		}

		$clean = array();
		foreach ( $args as $key => $value ) {
			if ( in_array( $key, array( 'sulayr_vista', 'mes' ), true ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $value );
			} elseif ( in_array( $key, array( 'sesion', 'usuario' ), true ) ) {
				$clean[ $key ] = absint( $value );
			}
		}

		return add_query_arg( $clean, $base );
	}

	/**
	 * @param string $label Etiqueta.
	 * @param string $value Valor.
	 * @return void
	 */
	private function pair( $label, $value ) {
		echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
	}

	/**
	 * @param string $year_month Mes `YYYY-MM`.
	 * @return string
	 */
	private function monthLabel( $year_month ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $year_month . '-01', wp_timezone() );
		if ( ! $date instanceof \DateTimeImmutable ) {
			return $year_month;
		}

		return $this->monthName( (int) $date->format( 'n' ) ) . ' de ' . $date->format( 'Y' );
	}

	/**
	 * @param string $day Fecha `Y-m-d`.
	 * @return string
	 */
	private function dayLabel( $day ) {
		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $day, wp_timezone() );
		if ( ! $date instanceof \DateTimeImmutable ) {
			return $day;
		}

		$week = array( 1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo' );
		$name = isset( $week[ (int) $date->format( 'N' ) ] ) ? $week[ (int) $date->format( 'N' ) ] : '';

		return $name . ' ' . $date->format( 'j' ) . ' de ' . $this->monthName( (int) $date->format( 'n' ) );
	}

	/**
	 * @param int $month Mes 1-12.
	 * @return string
	 */
	private function monthName( $month ) {
		$names = array( 1 => 'enero', 2 => 'febrero', 3 => 'marzo', 4 => 'abril', 5 => 'mayo', 6 => 'junio', 7 => 'julio', 8 => 'agosto', 9 => 'septiembre', 10 => 'octubre', 11 => 'noviembre', 12 => 'diciembre' );

		return isset( $names[ $month ] ) ? $names[ $month ] : '';
	}

	/**
	 * @param string $time Hora de MySQL.
	 * @return string
	 */
	private function hour( $time ) {
		return substr( (string) $time, 0, 5 );
	}
}
