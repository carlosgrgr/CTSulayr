<?php

namespace SulayrReservas\Front;

use SulayrReservas\Access\CrossSite;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Sessions\SessionGenerator;
use SulayrReservas\Swimmers\WeeklyLimit;

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
	 * Fecha que los formularios del día móvil deben conservar al volver.
	 *
	 * @var string
	 */
	private $return_day = '';

	/**
	 * El tema ya pintó el menú en su cabecera durante esta petición.
	 *
	 * @var bool
	 */
	private static $menu_printed = false;

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
		$now     = new \DateTimeImmutable( 'now', wp_timezone() );
		$view    = $this->view( $manager );
		$user_id = get_current_user_id();
		$profile = $this->query->swimmer( $user_id );
		$own     = $this->query->bookingsFor( $user_id, $now );

		$own_menu = ! self::$menu_printed;

		ob_start();
		echo '<div class="sulayr-panel">';
		$this->notice();
		echo '<div class="' . esc_attr( $own_menu ? 'sulayr-shell' : 'sulayr-shell sulayr-shell-no-nav' ) . '">';
		if ( $own_menu ) {
			echo '<div class="sulayr-nav-card">';
			$this->nav( $manager, $view );
			echo '</div>';
		}
		echo '<div class="sulayr-main">';
		if ( 'sesion' === $view ) {
			$this->session( $manager, $now );
		} elseif ( 'reservas' === $view ) {
			echo '<div class="sulayr-card">';
			$this->bookings( $user_id, $now, false, $own );
			echo '</div>';
		} elseif ( 'perfil' === $view ) {
			$this->ownProfile( $profile );
		} elseif ( 'nadadores' === $view && $manager ) {
			$this->swimmers();
		} elseif ( 'nadador' === $view && $manager ) {
			$this->swimmerDetail( $now );
		} elseif ( 'alta' === $view && $manager ) {
			$this->createForm();
		} elseif ( 'importar' === $view && $manager ) {
			$this->importView();
		} elseif ( 'historial' === $view && $manager ) {
			$this->history( $now );
		} else {
			$this->calendar( $manager, $now );
		}
		echo '</div>';
		$this->aside( $manager, $now, $view, $profile, $own );
		echo '</div></div>';

		return (string) ob_get_clean();
	}

	/**
	 * Menú para la cabecera del tema. El panel ya no pinta el suyo después.
	 *
	 * @param bool $manager Gestor o administrador.
	 * @return string
	 */
	public function brandMenu( $manager ) {
		self::$menu_printed = true;

		ob_start();
		$this->nav( $manager, $this->view( $manager ) );

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
			$allowed = array_merge( $allowed, array( 'nadadores', 'nadador', 'alta', 'importar', 'historial' ) );
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
		if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
			echo '<p class="sulayr-notice" role="status">' . esc_html( (string) $notice['message'] ) . '</p>';
		}

		$code = isset( $_GET['sulayr_aviso'] ) ? sanitize_key( wp_unslash( $_GET['sulayr_aviso'] ) ) : '';
		$text = $this->emailNotice( $code );
		if ( '' !== $text ) {
			echo '<p class="sulayr-notice" role="status">' . esc_html( $text ) . '</p>';
		}
	}

	/**
	 * @param bool   $manager Gestor o administrador.
	 * @param string $view    Vista actual.
	 * @return void
	 */
	private function nav( $manager, $view ) {
		$current = $view;
		if ( 'sesion' === $view ) {
			$current = 'calendario';
		} elseif ( 'nadador' === $view ) {
			$current = 'nadadores';
		}

		echo '<nav class="sulayr-menu" aria-label="Reservas">';
		echo '<button type="button" class="sulayr-nav-toggle" aria-expanded="false" aria-controls="sulayr-nav-menu" aria-label="Abrir menú">';
		echo '<span class="sulayr-nav-toggle-bars" aria-hidden="true"></span>';
		echo '</button>';
		echo '<div class="sulayr-nav" id="sulayr-nav-menu">';
		$this->navItem( 'Calendario', 'calendar', array(), 'calendario', $current );
		$this->navItem( 'Mis reservas', 'list', array( 'sulayr_vista' => 'reservas' ), 'reservas', $current );
		$this->navItem( 'Mi perfil', 'user', array( 'sulayr_vista' => 'perfil' ), 'perfil', $current );
		if ( $manager ) {
			$this->navItem( 'Nadadores', 'users', array( 'sulayr_vista' => 'nadadores' ), 'nadadores', $current );
			$this->navItem( 'Alta de nadador', 'plus', array( 'sulayr_vista' => 'alta' ), 'alta', $current );
			$this->navItem( 'Importar', 'list', array( 'sulayr_vista' => 'importar' ), 'importar', $current );
			$this->navItem( 'Meses anteriores', 'clock', array( 'sulayr_vista' => 'historial' ), 'historial', $current );
		}

		$bridge = new CrossSite( $this->config, $this->blog_context );
		$club   = $bridge->clubProfileUrlForCurrentUser();
		if ( '' !== $club ) {
			$this->externalNavItem( 'Mi perfil en el club', 'external', $club );
		}
		$this->externalNavItem( 'Cerrar sesión', 'logout', $bridge->logoutUrl() );
		echo '</div></nav>';
	}

	/**
	 * @param string               $label   Texto.
	 * @param string               $icon    Icono decorativo.
	 * @param array<string, mixed> $args    Parámetros.
	 * @param string               $key     Vista de este enlace.
	 * @param string               $current Vista activa.
	 * @return void
	 */
	private function navItem( $label, $icon, array $args, $key, $current ) {
		$class = 'sulayr-nav-link';
		if ( $key === $current ) {
			$class .= ' is-current';
		}

		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $this->url( $args ) ) . '"';
		if ( $key === $current ) {
			echo ' aria-current="page"';
		}
		echo '>' . $this->icon( $icon ) . '<span>' . esc_html( $label ) . '</span></a>';
	}

	/**
	 * @param string $label Texto.
	 * @param string $icon  Icono decorativo.
	 * @param string $href  URL absoluta ya construida en el servidor.
	 * @return void
	 */
	private function externalNavItem( $label, $icon, $href ) {
		echo '<a class="sulayr-nav-link" href="' . esc_url( $href ) . '">';
		echo $this->icon( $icon ) . '<span>' . esc_html( $label ) . '</span></a>';
	}

	/**
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @param string             $view    Vista actual.
	 * @param object|null        $profile Ficha del usuario actual.
	 * @param array{active: object[], past: object[]} $own Reservas propias.
	 * @return void
	 */
	private function aside( $manager, \DateTimeImmutable $now, $view, $profile, array $own ) {
		echo '<aside class="sulayr-side" aria-label="Resumen">';
		$this->userCard( $manager, $profile );
		$this->nextCard( $manager, $now, $own['active'] );
		if ( 'calendario' === $view ) {
			$this->legendCard();
		}
		$this->summaryCard( $now, $own, $profile );
		echo '</aside>';
	}

	/**
	 * @param bool        $manager Gestor o administrador.
	 * @param object|null $profile Ficha local.
	 * @return void
	 */
	private function userCard( $manager, $profile ) {
		$name = '';
		if ( $profile ) {
			$name = trim( (string) $profile->first_name . ' ' . (string) $profile->last_name );
		}
		if ( '' === $name ) {
			$user = wp_get_current_user();
			$name = $user->display_name ? (string) $user->display_name : '';
		}

		echo '<section class="sulayr-card sulayr-user-card">';
		$this->avatar( $name, $profile && ! empty( $profile->photo_url ) ? (string) $profile->photo_url : '' );
		echo '<div>';
		echo '<h2>' . esc_html( '' !== $name ? 'Hola, ' . $name : 'Hola' ) . '</h2>';
		echo '<p class="sulayr-role">' . esc_html( $this->roleLabel( $manager ) ) . '</p>';
		echo '</div></section>';
	}

	/**
	 * @param bool     $manager Gestor o administrador.
	 * @param object[] $active  Reservas propias que aún no han empezado.
	 * @return void
	 */
	private function nextCard( $manager, \DateTimeImmutable $now, array $active ) {
		$next = null;
		foreach ( $active as $row ) {
			$stamp = $row->session_date . ' ' . $row->start_time;
			if ( null === $next || $stamp < ( $next->session_date . ' ' . $next->start_time ) ) {
				$next = $row;
			}
		}

		echo '<section class="sulayr-card">';
		echo '<h2 class="sulayr-card-title">' . $this->icon( 'clock' ) . '<span>Tu próxima sesión</span></h2>';
		if ( ! $next ) {
			echo '<p>No tienes una próxima sesión reservada.</p>';
			echo '</section>';
			return;
		}

		echo '<p class="sulayr-next-when">' . esc_html( $this->dayLabel( $next->session_date ) ) . '</p>';
		echo '<p>' . esc_html( $this->hour( $next->start_time ) . '–' . $this->hour( $next->end_time ) ) . '</p>';
		$same_month = substr( (string) $next->session_date, 0, 7 ) === $now->format( 'Y-m' );
		if ( ( $manager || $same_month ) && isset( $next->session_id ) ) {
			$this->link(
				'Detalle',
				array(
					'sulayr_vista' => 'sesion',
					'sesion'       => (int) $next->session_id,
				)
			);
		}
		echo '</section>';
	}

	/**
	 * @return void
	 */
	private function legendCard() {
		$items = array(
			array( 'available', 'Disponible' ),
			array( 'mine', 'Reservada por ti' ),
			array( 'full', 'Completa' ),
			array( 'disabled', 'Deshabilitada' ),
			array( 'past', 'Ya ha comenzado' ),
		);

		echo '<section class="sulayr-card">';
		echo '<h2 class="sulayr-card-title">Leyenda</h2>';
		echo '<ul class="sulayr-legend">';
		foreach ( $items as $item ) {
			echo '<li><span class="sulayr-swatch sulayr-slot-' . esc_attr( $item[0] ) . '" aria-hidden="true"></span><span>' . esc_html( $item[1] ) . '</span></li>';
		}
		echo '</ul></section>';
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @param array{active: object[], past: object[]} $own Reservas propias.
	 * @return void
	 */
	private function summaryCard( \DateTimeImmutable $now, array $own, $profile ) {
		$limit = $profile ? WeeklyLimit::normalize( $profile->weekly_limit ) : WeeklyLimit::DEFAULT_LIMIT;
		$count = $this->weekCount( $own, $now );
		$value = min( $count, $limit );

		echo '<section class="sulayr-card">';
		echo '<h2 class="sulayr-card-title">Tu resumen</h2>';
		echo '<ul class="sulayr-summary">';
		echo '<li><span>Reservas activas</span><strong>' . esc_html( (string) count( $own['active'] ) ) . '</strong></li>';
		echo '<li><span>En el historial</span><strong>' . esc_html( (string) count( $own['past'] ) ) . '</strong></li>';
		echo '<li><span>Límite semanal</span><strong>' . esc_html( $count . ' / ' . $limit ) . '</strong></li>';
		echo '</ul>';
		echo '<meter class="sulayr-meter" min="0" max="' . esc_attr( (string) $limit ) . '" value="' . esc_attr( (string) $value ) . '" aria-hidden="true"></meter>';
		echo '<p class="sulayr-summary-note">Tu límite es de ' . esc_html( (string) $limit ) . ' reservas por semana, de lunes a domingo.</p>';
		echo '</section>';
	}

	/**
	 * @param bool $manager Gestor o administrador.
	 * @return string
	 */
	private function roleLabel( $manager ) {
		if ( current_user_can( 'manage_options' ) ) {
			return 'Administrador';
		}
		if ( $manager ) {
			return 'Gestor';
		}

		return 'Nadador';
	}

	/**
	 * Reservas propias cuya sesión cae en la semana en curso.
	 *
	 * La semana es la misma que usa el límite: de lunes a domingo, en la zona del sitio.
	 *
	 * @param array{active: object[], past: object[]} $own Reservas propias.
	 * @param \DateTimeImmutable                      $now Ahora.
	 * @return int
	 */
	private function weekCount( array $own, \DateTimeImmutable $now ) {
		$monday = $now->modify( '-' . ( (int) $now->format( 'N' ) - 1 ) . ' days' );
		$from   = $monday->format( 'Y-m-d' );
		$to     = $monday->modify( '+6 days' )->format( 'Y-m-d' );
		$count  = 0;
		foreach ( array_merge( $own['active'], $own['past'] ) as $row ) {
			$day = (string) $row->session_date;
			if ( $day >= $from && $day <= $to ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function calendar( $manager, \DateTimeImmutable $now ) {
		$month    = $this->visibleMonth( $manager, $now );
		$sessions = $this->query->monthSessions( $month, get_current_user_id() );
		$by_date  = array();
		foreach ( $sessions as $session ) {
			$by_date[ (string) $session->session_date ][] = $session;
		}

		echo '<section class="sulayr-card sulayr-calendar">';
		echo '<div class="sulayr-cal-toolbar">';
		echo '<h2 class="sulayr-cal-title"><time datetime="' . esc_attr( $month ) . '">' . esc_html( ucfirst( $this->monthLabel( $month ) ) ) . '</time></h2>';
		echo '<div class="sulayr-cal-tools">';
		if ( $manager ) {
			$this->generateForm( $now );
			$next = $now->modify( 'first day of next month' )->format( 'Y-m' );
			if ( $month === $now->format( 'Y-m' ) && $this->query->monthHasSessions( $next ) ) {
				$this->link( 'Ver ' . $this->monthLabel( $next ), array( 'mes' => $next ), 'sulayr-link sulayr-cal-move' );
			}
			if ( $month !== $now->format( 'Y-m' ) ) {
				$this->link( 'Volver al mes en curso', array(), 'sulayr-link sulayr-cal-move' );
			}
		}
		echo '</div></div>';
		if ( array() === $sessions ) {
			echo '<p>No hay sesiones en este mes.</p>';
		}

		$first = \DateTimeImmutable::createFromFormat( '!Y-m-d', $month . '-01', wp_timezone() );
		if ( ! $first instanceof \DateTimeImmutable ) {
			echo '</section>';
			return;
		}

		$last       = $first->modify( 'last day of this month' );
		$grid_start = $first->modify( '-' . ( (int) $first->format( 'N' ) - 1 ) . ' days' );
		$grid_end   = $last->modify( '+' . ( 7 - (int) $last->format( 'N' ) ) . ' days' );
		$today      = $now->format( 'Y-m-d' );

		echo '<div class="sulayr-cal-weekdays" aria-hidden="true">';
		for ( $iso = 1; $iso <= 7; $iso++ ) {
			echo '<span>' . esc_html( $this->weekdayName( $iso, true ) ) . '</span>';
		}
		echo '</div>';
		echo '<div class="sulayr-cal-grid">';
		for ( $day = $grid_start; $day <= $grid_end; $day = $day->modify( '+1 day' ) ) {
			$iso      = $day->format( 'Y-m-d' );
			$outside  = $day->format( 'Y-m' ) !== $month;
			$is_today = $iso === $today;
			$slots    = isset( $by_date[ $iso ] ) ? $by_date[ $iso ] : array();
			$classes  = 'sulayr-day';
			if ( $outside ) {
				$classes .= ' sulayr-day-outside';
			}
			if ( $is_today ) {
				$classes .= ' sulayr-day-today';
			}
			if ( ! $outside && array() === $slots ) {
				$classes .= ' sulayr-day-empty';
			}

			echo '<div class="' . esc_attr( $classes ) . '"';
			if ( $is_today ) {
				echo ' aria-current="date"';
			}
			echo '>';
			echo '<p class="sulayr-day-heading"><span class="sulayr-day-name">' . esc_html( $this->weekdayName( (int) $day->format( 'N' ), true ) ) . '</span> ';
			echo '<time datetime="' . esc_attr( $iso ) . '">' . esc_html( $day->format( 'j' ) ) . '</time>';
			if ( $is_today ) {
				echo ' <span class="sulayr-today-label">Hoy</span>';
			}
			echo '</p>';
			if ( ! $outside && array() === $slots ) {
				echo '<p class="sulayr-day-empty-note">Sin sesiones</p>';
			}
			if ( array() !== $slots ) {
				echo '<ul class="sulayr-day-sessions">';
				foreach ( $slots as $session ) {
					$this->sessionSlot( $session, $manager, $now );
				}
				echo '</ul>';
			}
			echo '</div>';
		}
		echo '</div>';
		$this->mobileCalendar( $now );
		echo '</section>';
	}

	/**
	 * Un día visible. Los datos salen de las mismas sesiones que el mes.
	 *
	 * @param \DateTimeImmutable $now Ahora, en la zona del sitio.
	 * @return void
	 */
	private function mobileCalendar( \DateTimeImmutable $now ) {
		$selected = $this->selectedDay( $now );
		$start    = $selected->modify( '-21 days' );
		$start    = $start->modify( '-' . ( (int) $start->format( 'N' ) - 1 ) . ' days' );
		$end      = $selected->modify( '+28 days' );
		$end      = $end->modify( '+' . ( 7 - (int) $end->format( 'N' ) ) . ' days' );
		$by_date  = $this->sessionsBetween( $start, $end );
		$monday   = $selected->modify( '-' . ( (int) $selected->format( 'N' ) - 1 ) . ' days' );
		$today    = $now->format( 'Y-m-d' );

		echo '<div class="sulayr-mobile-cal" data-selected="' . esc_attr( $selected->format( 'Y-m-d' ) ) . '" data-today="' . esc_attr( $today ) . '">';
		echo '<div class="sulayr-mobile-head">';
		echo '<a class="sulayr-day-prev" href="' . esc_url( $this->dayUrl( $selected->modify( '-1 day' ) ) ) . '" aria-label="Día anterior"><span aria-hidden="true">‹</span></a>';
		echo '<div class="sulayr-week" role="tablist" aria-label="Días de la semana">';
		for ( $index = 0; $index < 7; $index++ ) {
			$day = $monday->modify( '+' . $index . ' days' );
			$this->weekTab( $day, $day->format( 'Y-m-d' ) === $selected->format( 'Y-m-d' ), $today );
		}
		echo '</div>';
		echo '<a class="sulayr-day-next" href="' . esc_url( $this->dayUrl( $selected->modify( '+1 day' ) ) ) . '" aria-label="Día siguiente"><span aria-hidden="true">›</span></a>';
		echo '</div>';
		echo '<div class="sulayr-mobile-stage">';
		for ( $day = $start; $day <= $end; $day = $day->modify( '+1 day' ) ) {
			$this->mobileDay( $day, $by_date, $day->format( 'Y-m-d' ) === $selected->format( 'Y-m-d' ), $now );
		}
		echo '</div></div>';
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return \DateTimeImmutable
	 */
	private function selectedDay( \DateTimeImmutable $now ) {
		$today = $now->setTime( 0, 0 );
		$raw   = isset( $_GET['sulayr_dia'] ) ? sanitize_text_field( wp_unslash( $_GET['sulayr_dia'] ) ) : '';
		$picked = \DateTimeImmutable::createFromFormat( '!Y-m-d', $raw, wp_timezone() );
		if ( ! $picked instanceof \DateTimeImmutable || $picked->format( 'Y-m-d' ) !== $raw ) {
			return $today;
		}

		$min = $today->modify( '-18 months' );
		$max = $today->modify( '+18 months' );
		if ( $picked < $min || $picked > $max ) {
			return $today;
		}

		return $picked;
	}

	/**
	 * @param \DateTimeImmutable $start Primer día inclusive.
	 * @param \DateTimeImmutable $end   Último día inclusive.
	 * @return array<string, object[]>
	 */
	private function sessionsBetween( \DateTimeImmutable $start, \DateTimeImmutable $end ) {
		$by_date = array();
		$cursor  = $start->modify( 'first day of this month' );
		$last    = $end->format( 'Y-m' );
		$user_id = get_current_user_id();

		while ( $cursor->format( 'Y-m' ) <= $last ) {
			foreach ( $this->query->monthSessions( $cursor->format( 'Y-m' ), $user_id ) as $session ) {
				$by_date[ (string) $session->session_date ][] = $session;
			}
			$cursor = $cursor->modify( '+1 month' );
		}

		return $by_date;
	}

	/**
	 * @param \DateTimeImmutable $day      Día de la semana.
	 * @param bool               $selected Está elegido.
	 * @param string             $today    Fecha de hoy, Y-m-d.
	 * @return void
	 */
	private function weekTab( \DateTimeImmutable $day, $selected, $today ) {
		$is_today = $day->format( 'Y-m-d' ) === $today;
		$class    = 'sulayr-week-day';
		if ( $selected ) {
			$class .= ' is-selected';
		}
		if ( $is_today ) {
			$class .= ' is-today';
		}

		echo '<a class="' . esc_attr( $class ) . '" role="tab" href="' . esc_url( $this->dayUrl( $day ) ) . '"';
		echo ' data-date="' . esc_attr( $day->format( 'Y-m-d' ) ) . '"';
		echo ' aria-selected="' . ( $selected ? 'true' : 'false' ) . '"';
		if ( $is_today ) {
			echo ' aria-current="date"';
		}
		echo ' tabindex="' . ( $selected ? '0' : '-1' ) . '"';
		echo '>';
		echo '<span class="sulayr-week-name">' . esc_html( $this->weekdayShort( (int) $day->format( 'N' ) ) ) . '</span>';
		echo '<span class="sulayr-week-num">' . esc_html( $day->format( 'j' ) ) . '</span>';
		if ( $is_today ) {
			echo '<span class="sulayr-week-today">Hoy</span>';
		}
		echo '</a>';
	}

	/**
	 * @param \DateTimeImmutable        $day      Día.
	 * @param array<string, object[]>   $by_date  Sesiones por fecha.
	 * @param bool                      $selected Visible al cargar.
	 * @param \DateTimeImmutable        $now      Ahora.
	 * @return void
	 */
	private function mobileDay( \DateTimeImmutable $day, array $by_date, $selected, \DateTimeImmutable $now ) {
		$iso     = $day->format( 'Y-m-d' );
		$slots   = isset( $by_date[ $iso ] ) ? $by_date[ $iso ] : array();
		$manager = current_user_can( Capabilities::MANAGE_SWIMMERS )
			|| current_user_can( Capabilities::MANAGE_SESSIONS )
			|| current_user_can( Capabilities::VIEW_ALL_BOOKINGS );

		echo '<article class="sulayr-mobile-day" data-date="' . esc_attr( $iso ) . '"';
		echo ' data-weekday="' . esc_attr( $this->weekdayShort( (int) $day->format( 'N' ) ) ) . '"';
		echo ' data-day="' . esc_attr( $day->format( 'j' ) ) . '"';
		if ( ! $selected ) {
			echo ' hidden';
		}
		echo '>';
		echo '<h3 class="sulayr-mobile-date">';
		if ( $iso === $now->format( 'Y-m-d' ) ) {
			echo '<span class="sulayr-today-label">Hoy</span>';
		}
		echo '<span class="sulayr-mobile-kicker">' . esc_html( $this->weekdayName( (int) $day->format( 'N' ), true ) ) . ' </span>';
		echo '<time datetime="' . esc_attr( $iso ) . '">';
		echo esc_html( $day->format( 'j' ) . ' de ' . $this->monthName( (int) $day->format( 'n' ) ) );
		echo '</time></h3>';
		if ( array() === $slots ) {
			echo '<p class="sulayr-mobile-empty">No hay sesiones programadas.</p>';
		} else {
			echo '<ul class="sulayr-day-sessions">';
			$this->return_day = $iso;
			foreach ( $slots as $session ) {
				$this->sessionSlot( $session, $manager, $now );
			}
			$this->return_day = '';
			echo '</ul>';
		}
		echo '</article>';
	}

	/**
	 * @param \DateTimeImmutable $day Día.
	 * @return string
	 */
	private function dayUrl( \DateTimeImmutable $day ) {
		$base = ( new PanelPage( $this->config ) )->url();
		if ( '' === $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg( 'sulayr_dia', $day->format( 'Y-m-d' ), $base );
	}

	/**
	 * @param int $iso_day Día ISO 1-7.
	 * @return string
	 */
	private function weekdayShort( $iso_day ) {
		$week = array( 1 => 'lun', 2 => 'mar', 3 => 'mié', 4 => 'jue', 5 => 'vie', 6 => 'sáb', 7 => 'dom' );

		return isset( $week[ (int) $iso_day ] ) ? $week[ (int) $iso_day ] : '';
	}

	/**
	 * @param object             $session Sesión.
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function sessionSlot( $session, $manager, \DateTimeImmutable $now ) {
		$state = $this->slotState( $session, $now );
		echo '<li class="sulayr-slot sulayr-slot-' . esc_attr( $state ) . '">';
		echo '<p class="sulayr-slot-time">' . esc_html( $this->hour( $session->start_time ) . '–' . $this->hour( $session->end_time ) ) . '</p>';
		$this->sessionFacts( $session, $now );
		$this->link( 'Detalle', array( 'sulayr_vista' => 'sesion', 'sesion' => (int) $session->id ) );
		$this->quickActions( $session, $manager, $now );
		echo '</li>';
	}

	/**
	 * Apariencia de la sesión. El texto de estado se imprime aparte.
	 *
	 * @param object             $session Sesión.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return string
	 */
	private function slotState( $session, \DateTimeImmutable $now ) {
		$start   = $this->sessionStart( $session );
		$started = $start instanceof \DateTimeImmutable && $now >= $start;
		if ( $started ) {
			return 'past';
		}
		if ( (int) $session->own_booking_id > 0 ) {
			return 'mine';
		}
		if ( 1 !== (int) $session->enabled ) {
			return 'disabled';
		}
		if ( (int) $session->occupied >= (int) $session->capacity ) {
			return 'full';
		}

		return 'available';
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
			echo '<div class="sulayr-card"><p>La sesión no está disponible.</p></div>';
			return;
		}

		echo '<div class="sulayr-card">';
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
			echo '</div>';
			return;
		}

		$attendees = $this->query->attendees( (int) $session->id );
		echo '<h3>Reservas</h3>';
		if ( array() === $attendees ) {
			echo '<p>Nadie ha reservado esta sesión.</p></div>';
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
		echo '</ul></div>';
	}

	/**
	 * @param int                $user_id  Usuario.
	 * @param \DateTimeImmutable $now      Ahora.
	 * @param bool               $foreign  True si el gestor mira a otro nadador.
	 * @param array{active: object[], past: object[]}|null $lists Reservas ya leídas.
	 * @return void
	 */
	private function bookings( $user_id, \DateTimeImmutable $now, $foreign, $lists = null ) {
		if ( $foreign && ! current_user_can( Capabilities::VIEW_ALL_BOOKINGS ) ) {
			echo '<p>No tienes permiso para ver estas reservas.</p>';
			return;
		}

		if ( ! is_array( $lists ) ) {
			$lists = $this->query->bookingsFor( $user_id, $now );
		}
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

		echo '<ul class="sulayr-booking-list">';
		foreach ( $rows as $row ) {
			echo '<li><span>' . esc_html( $this->dayLabel( $row->session_date ) . ' ' . $this->hour( $row->start_time ) . '–' . $this->hour( $row->end_time ) ) . '</span> ';
			if ( 1 === (int) $row->is_manual ) {
				echo '<span>Añadida por un gestor</span> ';
			}
			$start    = $this->sessionStart( $row );
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
			echo '<div class="sulayr-card"><p>No tienes permiso para ver los nadadores.</p></div>';
			return;
		}

		$filters = $this->query->swimmerFilters();
		echo '<div class="sulayr-card">';
		echo '<h2>Nadadores</h2>';
		$this->filterForm( $filters );
		$this->swimmerTotal( $this->query->swimmerCount( $filters ), $filters['status'] );
		$rows = $this->query->swimmers( $filters );
		if ( array() === $rows ) {
			echo '<p>No hay nadadores con ese criterio.</p></div>';
			return;
		}

		$this->swimmerTable( $rows, $filters );
		$this->exportSwimmersForm( $filters );
		echo '</div>';
	}

	/**
	 * @param int    $count  Nadadores del estado elegido.
	 * @param string $status Estado `todos`, `activos` o `inactivos`.
	 * @return void
	 */
	private function swimmerTotal( $count, $status ) {
		$count = (int) $count;
		if ( 'activos' === $status ) {
			$text = 1 === $count ? 'Total: 1 nadador activo' : 'Total: ' . $count . ' nadadores activos';
		} elseif ( 'inactivos' === $status ) {
			$text = 1 === $count ? 'Total: 1 nadador inactivo' : 'Total: ' . $count . ' nadadores inactivos';
		} else {
			$text = 1 === $count ? 'Total: 1 nadador' : 'Total: ' . $count . ' nadadores';
		}

		echo '<p class="sulayr-swimmer-total">' . esc_html( $text ) . '</p>';
	}

	/**
	 * @param object[]                                                    $rows    Fichas ya filtradas y ordenadas.
	 * @param array{status: string, search: string, sort: string, direction: string} $filters Orden y filtros visibles.
	 * @return void
	 */
	private function swimmerTable( array $rows, array $filters ) {
		echo '<div class="sulayr-table-wrap">';
		echo '<table class="sulayr-table">';
		echo '<thead><tr>';
		echo '<th scope="col">Foto</th>';
		foreach ( $this->swimmerColumns() as $key => $label ) {
			$this->swimmerSortHeader( $label, $key, $filters );
		}
		echo '</tr></thead><tbody>';
		foreach ( $rows as $row ) {
			$name  = trim( (string) $row->first_name . ' ' . (string) $row->last_name );
			$href  = $this->url( array( 'sulayr_vista' => 'nadador', 'usuario' => (int) $row->user_id ) );
			$phone = trim( (string) $row->phone );
			echo '<tr>';
			echo '<td>';
			$this->avatar( $name, isset( $row->photo_url ) ? (string) $row->photo_url : '', 'sulayr-avatar sulayr-avatar-small' );
			echo '</td>';
			echo '<td><a href="' . esc_url( $href ) . '">' . esc_html( (string) $row->first_name ) . '</a></td>';
			echo '<td><a href="' . esc_url( $href ) . '">' . esc_html( (string) $row->last_name ) . '</a></td>';
			echo '<td>' . esc_html( (string) $row->email ) . '</td>';
			echo '<td>' . esc_html( '' !== $phone ? $phone : '—' ) . '</td>';
			echo '<td>' . esc_html( 1 === (int) $row->active ? 'Activo' : 'Desactivado' ) . '</td>';
			echo '<td>' . esc_html( 1 === (int) $row->linked_to_club ? 'Vinculado al club' : 'Alta manual' ) . '</td>';
			echo '<td>' . esc_html( (string) WeeklyLimit::normalize( $row->weekly_limit ) ) . '</td>';
			echo '<td>' . esc_html( $this->createdLabel( (string) $row->created_at ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Columnas ordenables del listado.
	 *
	 * @return array<string, string>
	 */
	private function swimmerColumns() {
		return array(
			'nombre'    => 'Nombre',
			'apellidos' => 'Apellidos',
			'correo'    => 'Correo',
			'telefono'  => 'Teléfono',
			'estado'    => 'Estado',
			'origen'    => 'Origen',
			'limite'    => 'Límite',
			'alta'      => 'Fecha de alta',
		);
	}

	/**
	 * @param string                                                                     $label   Texto de la cabecera.
	 * @param string                                                                     $column  Clave de orden.
	 * @param array{status: string, search: string, sort: string, direction: string}    $filters Orden actual.
	 * @return void
	 */
	private function swimmerSortHeader( $label, $column, array $filters ) {
		$active    = $column === $filters['sort'];
		$ascending = $active && 'asc' === $filters['direction'];
		$aria      = $active ? ( $ascending ? 'ascending' : 'descending' ) : 'none';
		echo '<th scope="col" aria-sort="' . esc_attr( $aria ) . '">';
		echo '<a href="' . esc_url( $this->swimmerSortUrl( $filters, $column ) ) . '">' . esc_html( $label );
		if ( $active ) {
			echo '<span aria-hidden="true">' . ( $ascending ? ' ↑' : ' ↓' ) . '</span>';
			echo '<span class="sulayr-sr">' . esc_html( $ascending ? ', orden ascendente. Activar para orden descendente.' : ', orden descendente. Activar para orden ascendente.' ) . '</span>';
		} else {
			echo '<span class="sulayr-sr">. Activar para orden ascendente.</span>';
		}
		echo '</a></th>';
	}

	/**
	 * @param array{status: string, search: string, sort: string, direction: string} $filters Filtros que deben conservarse.
	 * @param string                                                                  $column  Columna pulsada.
	 * @return string
	 */
	private function swimmerSortUrl( array $filters, $column ) {
		$direction = ( $column === $filters['sort'] && 'asc' === $filters['direction'] ) ? 'desc' : 'asc';
		$args      = array( 'sulayr_vista' => 'nadadores' );
		if ( 'apellidos' !== $column || 'asc' !== $direction ) {
			$args['orden'] = $column;
			$args['dir']   = $direction;
		}
		if ( 'todos' !== $filters['status'] ) {
			$args['estado'] = $filters['status'];
		}
		if ( '' !== $filters['search'] ) {
			$args['q'] = $filters['search'];
		}

		return $this->url( $args );
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function swimmerDetail( \DateTimeImmutable $now ) {
		$user_id = isset( $_GET['usuario'] ) ? absint( $_GET['usuario'] ) : 0;
		$row     = $this->query->swimmer( $user_id );
		if ( ! $row || ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<div class="sulayr-card"><p>El nadador no está disponible.</p></div>';
			return;
		}

		$linked = 1 === (int) $row->linked_to_club;
		$name   = trim( $row->first_name . ' ' . $row->last_name );
		echo '<div class="sulayr-card">';
		$this->avatar( $name, isset( $row->photo_url ) ? (string) $row->photo_url : '' );
		echo '<h2>' . esc_html( $name ) . '</h2>';
		echo '<p>' . esc_html( 1 === (int) $row->active ? 'Activo' : 'Desactivado' ) . '. ';
		echo esc_html( $linked ? 'Vinculado al club.' : 'Alta manual.' ) . '</p>';
		echo '<dl class="sulayr-profile">';
		$this->pair( 'Correo', (string) $row->email );
		$this->pair( 'Teléfono', (string) $row->phone );
		$this->pair( 'DNI', (string) $row->dni );
		$this->pair( 'Límite semanal', (string) WeeklyLimit::normalize( $row->weekly_limit ) );
		echo '</dl>';
		if ( ! empty( $row->pending_email ) ) {
			echo '<p>Cambio de correo pendiente de confirmación en ' . esc_html( (string) $row->pending_email ) . '. El correo actual sigue vigente.</p>';
		}
		if ( $linked ) {
			echo '<p>El nombre, los apellidos, el teléfono y el DNI los gestiona el club.</p>';
		} else {
			$this->personalForm( (int) $row->user_id, $row, array( 'sulayr_vista' => 'nadador', 'usuario' => (int) $row->user_id ) );
		}
		$this->limitForm( (int) $row->user_id, $row );
		$this->emailForm( (int) $row->user_id, array( 'sulayr_vista' => 'nadador', 'usuario' => (int) $row->user_id ) );
		$this->photoForm( (int) $row->user_id, $row, array( 'sulayr_vista' => 'nadador', 'usuario' => (int) $row->user_id ) );
		$this->exportBookingsForm( (int) $row->user_id );

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
		echo '</div>';
	}

	/**
	 * @param object|null $row Ficha ya leída.
	 * @return void
	 */
	private function ownProfile( $row = null ) {
		if ( null === $row ) {
			$row = $this->query->swimmer( get_current_user_id() );
		}
		if ( ! $row ) {
			echo '<div class="sulayr-card"><p>No hay una ficha de nadador para esta cuenta.</p>';
			$this->passwordForm( array( 'sulayr_vista' => 'perfil' ) );
			echo '</div>';
			return;
		}

		$linked = 1 === (int) $row->linked_to_club;
		$name   = trim( (string) $row->first_name . ' ' . (string) $row->last_name );
		$back   = array( 'sulayr_vista' => 'perfil' );
		echo '<div class="sulayr-card">';
		$this->avatar( $name, isset( $row->photo_url ) ? (string) $row->photo_url : '' );
		echo '<h2>Mi perfil</h2>';
		echo '<p>' . esc_html( 1 === (int) $row->active ? 'Activo' : 'Desactivado' ) . '.</p>';
		echo '<dl class="sulayr-profile">';
		$this->pair( 'Nombre', (string) $row->first_name );
		$this->pair( 'Apellidos', (string) $row->last_name );
		$this->pair( 'Correo', (string) $row->email );
		$this->pair( 'Teléfono', (string) $row->phone );
		$this->pair( 'DNI', (string) $row->dni );
		$this->pair( 'Límite semanal', (string) WeeklyLimit::normalize( $row->weekly_limit ) );
		echo '</dl>';
		echo '<p>El límite semanal lo establece el gestor. No puedes cambiarlo.</p>';
		if ( ! empty( $row->pending_email ) ) {
			echo '<p>Cambio de correo pendiente de confirmación en ' . esc_html( (string) $row->pending_email ) . '. El correo actual sigue vigente.</p>';
		}
		if ( $linked ) {
			echo '<p>El nombre, los apellidos, el teléfono y el DNI los gestiona el club. Puedes cambiar la contraseña, solicitar otro correo y actualizar tu fotografía de reservas.</p>';
		} else {
			$this->personalForm( (int) $row->user_id, $row, $back );
		}
		$this->emailForm( (int) $row->user_id, $back );
		$this->photoForm( (int) $row->user_id, $row, $back );
		$this->passwordForm( $back );
		echo '</div>';
	}

	/**
	 * @return void
	 */
	private function createForm() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<div class="sulayr-card"><p>No tienes permiso para dar de alta nadadores.</p></div>';
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

		echo '<div class="sulayr-card">';
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
		$selected = isset( $values['weekly_limit'] ) ? (string) $values['weekly_limit'] : (string) WeeklyLimit::DEFAULT_LIMIT;
		echo '<label>Límite semanal <select name="weekly_limit">';
		for ( $limit = WeeklyLimit::MIN; $limit <= WeeklyLimit::MAX; $limit++ ) {
			echo '<option value="' . esc_attr( (string) $limit ) . '"' . selected( $selected, (string) $limit, false ) . '>' . esc_html( (string) $limit ) . '</option>';
		}
		echo '</select></label>';
		echo '<button type="submit">Crear nadador</button></form></div>';
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function history( \DateTimeImmutable $now ) {
		echo '<div class="sulayr-card">';
		echo '<h2>Meses anteriores</h2>';
		$months = $this->query->pastMonths( $now->format( 'Y-m' ) );
		if ( array() === $months ) {
			echo '<p>No hay meses anteriores con sesiones.</p></div>';
			return;
		}
		echo '<ul class="sulayr-months">';
		foreach ( $months as $month ) {
			echo '<li><a href="' . esc_url( $this->url( array( 'mes' => $month ) ) ) . '">' . esc_html( $this->monthLabel( $month ) ) . '</a></li>';
		}
		echo '</ul></div>';
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
		$start    = $this->sessionStart( $session );
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

		echo '<p class="sulayr-slot-status">' . esc_html( implode( '. ', $labels ) ) . '.</p>';
		echo '<p class="sulayr-slot-places">' . esc_html( $occupied . ' plazas ocupadas. ' . $free . ' plazas disponibles.' ) . '</p>';
	}

	/**
	 * @param object             $session Sesión.
	 * @param bool               $manager Gestor o administrador.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function quickActions( $session, $manager, \DateTimeImmutable $now ) {
		$start   = $this->sessionStart( $session );
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
			echo '<p class="sulayr-cal-note">Hoy no corresponde generar un mes.</p>';
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
	 * @param string                    $action Campos de la operación.
	 * @param string                    $label  Texto del botón.
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
	 * @param string               $class Clases del enlace.
	 * @return void
	 */
	private function link( $label, array $args, $class = 'sulayr-link' ) {
		echo '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $this->url( $args ) ) . '">' . esc_html( $label ) . '</a> ';
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
			} elseif ( 'estado' === $key && in_array( sanitize_key( (string) $value ), array( 'activos', 'inactivos' ), true ) ) {
				$clean['estado'] = sanitize_key( (string) $value );
			} elseif ( 'q' === $key ) {
				$search = sanitize_text_field( (string) $value );
				if ( mb_strlen( $search ) > 100 ) {
					$search = mb_substr( $search, 0, 100 );
				}
				if ( '' !== $search ) {
					$clean['q'] = $search;
				}
			} elseif ( 'orden' === $key && in_array( sanitize_key( (string) $value ), array( 'nombre', 'apellidos', 'correo', 'telefono', 'estado', 'origen', 'limite', 'alta' ), true ) ) {
				$clean['orden'] = sanitize_key( (string) $value );
			} elseif ( 'dir' === $key && in_array( sanitize_key( (string) $value ), array( 'asc', 'desc' ), true ) ) {
				$clean['dir'] = sanitize_key( (string) $value );
			}
		}

		if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $this->return_day ) ) {
			$clean['sulayr_dia'] = $this->return_day;
		}

		return add_query_arg( $clean, $base );
	}

	/**
	 * @return void
	 */
	private function importView() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			echo '<div class="sulayr-card"><p>No tienes permiso para importar nadadores.</p></div>';
			return;
		}

		echo '<div class="sulayr-card">';
		echo '<h2>Importar nadadores</h2>';
		echo '<p>Columnas: nombre, apellidos, email, telefono, limite_semanal y estado. Obligatorios: nombre, apellidos y email. El estado vacío se crea activo (1). El límite vacío queda en 3. Solo se admiten límites del 1 al 5 y estados 0 o 1.</p>';
		echo '<p>La fila de ejemplo de la plantilla se importaría si confirmas ese archivo. Cámbiala o bórrala antes.</p>';
		echo '<form method="post" action="' . esc_url( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '" class="sulayr-inline">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="export_template">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '">';
		echo '<button type="submit">Descargar plantilla CSV</button></form>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="csv_preview">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '">';
		echo '<label>Archivo CSV <input type="file" name="csv" accept=".csv,text/csv" required></label>';
		echo '<button type="submit">Validar y previsualizar</button></form>';

		$preview = get_transient( 'sulayr_csv_preview_' . get_current_user_id() );
		if ( is_array( $preview ) && ! empty( $preview['rows'] ) && ! empty( $preview['token'] ) ) {
			$this->importPreview( $preview );
		}
		echo '</div>';
	}

	/**
	 * @param array<string, mixed> $preview Vista previa guardada.
	 * @return void
	 */
	private function importPreview( array $preview ) {
		$valid = 0;
		echo '<h3>Vista previa</h3>';
		echo '<ul class="sulayr-people">';
		foreach ( $preview['rows'] as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$data = isset( $row['data'] ) && is_array( $row['data'] ) ? $row['data'] : array();
			$line = isset( $row['line'] ) ? (int) $row['line'] : 0;
			if ( ! empty( $row['ok'] ) ) {
				++$valid;
				echo '<li>Fila ' . esc_html( (string) $line ) . ': ' . esc_html( ( isset( $data['first_name'] ) ? $data['first_name'] : '' ) . ' ' . ( isset( $data['last_name'] ) ? $data['last_name'] : '' ) ) . ' — válida</li>';
				continue;
			}
			$messages = isset( $row['messages'] ) && is_array( $row['messages'] ) ? implode( ' ', $row['messages'] ) : '';
			echo '<li>Fila ' . esc_html( (string) $line ) . ': ' . esc_html( $messages ) . '</li>';
		}
		echo '</ul>';
		echo '<p>' . esc_html( (string) $valid ) . ' filas válidas. Las filas con error no se crearán.</p>';
		if ( $valid > 0 ) {
			echo '<form method="post" action="' . esc_url( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '" class="sulayr-form">';
			wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
			echo '<input type="hidden" name="sulayr_action" value="csv_confirm">';
			echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'importar' ) ) ) . '">';
			echo '<input type="hidden" name="csv_token" value="' . esc_attr( (string) $preview['token'] ) . '">';
			echo '<button type="submit">Confirmar importación de las filas válidas</button></form>';
		}
	}

	/**
	 * @param array{status: string, search: string, sort: string, direction: string} $filters Filtros actuales.
	 * @return void
	 */
	private function filterForm( array $filters ) {
		echo '<form method="get" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form sulayr-filters">';
		echo '<input type="hidden" name="sulayr_vista" value="nadadores">';
		if ( 'apellidos' !== $filters['sort'] || 'asc' !== $filters['direction'] ) {
			echo '<input type="hidden" name="orden" value="' . esc_attr( $filters['sort'] ) . '">';
			echo '<input type="hidden" name="dir" value="' . esc_attr( $filters['direction'] ) . '">';
		}
		echo '<label>Estado <select name="estado">';
		foreach ( array( 'todos' => 'Todos', 'activos' => 'Activos', 'inactivos' => 'Inactivos' ) as $value => $label ) {
			echo '<option value="' . esc_attr( $value ) . '"' . selected( $filters['status'], $value, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select></label>';
		echo '<label>Nombre o correo <input type="search" name="q" value="' . esc_attr( $filters['search'] ) . '"></label>';
		echo '<button type="submit">Filtrar</button></form>';
	}

	/**
	 * @param array{status: string, search: string, sort: string, direction: string} $filters Filtros a exportar.
	 * @return void
	 */
	private function exportSwimmersForm( array $filters ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-inline">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="export_swimmers">';
		echo '<input type="hidden" name="estado" value="' . esc_attr( $filters['status'] ) . '">';
		echo '<input type="hidden" name="q" value="' . esc_attr( $filters['search'] ) . '">';
		echo '<input type="hidden" name="orden" value="' . esc_attr( $filters['sort'] ) . '">';
		echo '<input type="hidden" name="dir" value="' . esc_attr( $filters['direction'] ) . '">';
		echo '<button type="submit">Exportar CSV</button></form>';
	}

	/**
	 * @param int $user_id Nadador.
	 * @return void
	 */
	private function exportBookingsForm( $user_id ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-inline">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="export_bookings">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $user_id ) . '">';
		echo '<button type="submit">Exportar reservas CSV</button></form>';
	}

	/**
	 * @param int                     $user_id Nadador.
	 * @param object                  $row     Ficha.
	 * @param array<string, mixed>    $return  Vuelta.
	 * @return void
	 */
	private function personalForm( $user_id, $row, array $return ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="profile_save">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( $return ) ) . '">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $user_id ) . '">';
		echo '<label>Nombre <input type="text" name="first_name" value="' . esc_attr( (string) $row->first_name ) . '" required></label>';
		echo '<label>Apellidos <input type="text" name="last_name" value="' . esc_attr( (string) $row->last_name ) . '" required></label>';
		echo '<label>Teléfono <input type="text" name="phone" value="' . esc_attr( (string) $row->phone ) . '" required></label>';
		echo '<button type="submit">Guardar datos</button></form>';
	}

	/**
	 * @param int    $user_id Nadador.
	 * @param object $row     Ficha.
	 * @return void
	 */
	private function limitForm( $user_id, $row ) {
		$current = (string) WeeklyLimit::normalize( $row->weekly_limit );
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="weekly_limit">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => 'nadador', 'usuario' => $user_id ) ) ) . '">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $user_id ) . '">';
		echo '<label>Límite semanal <select name="weekly_limit">';
		for ( $limit = WeeklyLimit::MIN; $limit <= WeeklyLimit::MAX; $limit++ ) {
			echo '<option value="' . esc_attr( (string) $limit ) . '"' . selected( $current, (string) $limit, false ) . '>' . esc_html( (string) $limit ) . '</option>';
		}
		echo '</select></label>';
		echo '<button type="submit">Guardar límite</button></form>';
	}

	/**
	 * @param int                  $user_id Nadador.
	 * @param array<string, mixed> $return  Vuelta.
	 * @return void
	 */
	private function emailForm( $user_id, array $return ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="email_request">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( $return ) ) . '">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $user_id ) . '">';
		echo '<label>Nuevo correo <input type="email" name="new_email" required></label>';
		echo '<button type="submit">Solicitar cambio de correo</button></form>';
	}

	/**
	 * @param array<string, mixed> $return Vuelta.
	 * @return void
	 */
	private function passwordForm( array $return ) {
		echo '<form method="post" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="password_change">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( $return ) ) . '">';
		echo '<label>Contraseña actual <input type="password" name="current_password" autocomplete="current-password" required></label>';
		echo '<label>Nueva contraseña <input type="password" name="new_password" autocomplete="new-password" required></label>';
		echo '<label>Confirmar contraseña <input type="password" name="confirm_password" autocomplete="new-password" required></label>';
		echo '<button type="submit">Cambiar contraseña</button></form>';
	}

	/**
	 * @param int                  $user_id Nadador.
	 * @param object               $row     Ficha.
	 * @param array<string, mixed> $return  Vuelta.
	 * @return void
	 */
	private function photoForm( $user_id, $row, array $return ) {
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( $this->url( array() ) ) . '" class="sulayr-form">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="photo_save">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( $return ) ) . '">';
		echo '<input type="hidden" name="swimmer_user_id" value="' . esc_attr( (string) $user_id ) . '">';
		echo '<label>Fotografía (JPG, PNG o WebP, máximo 5 MB) <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required></label>';
		echo '<button type="submit">Guardar fotografía</button></form>';
		if ( ! empty( $row->photo_id ) ) {
			$this->post( 'photo_delete', 'Quitar fotografía', array( 'swimmer_user_id' => $user_id ), $return );
		}
	}

	/**
	 * @param string $name Nombre visible, para la inicial.
	 * @param string $url  URL de la foto. Vacía si no hay.
	 * @param string $class Clases.
	 * @return void
	 */
	private function avatar( $name, $url, $class = 'sulayr-avatar' ) {
		if ( '' !== $url ) {
			echo '<img class="' . esc_attr( $class ) . ' sulayr-avatar-photo" src="' . esc_url( $url ) . '" alt="" width="96" height="96">';
			return;
		}
		if ( '' !== $name && preg_match( '/^\p{L}/u', $name, $found ) ) {
			echo '<p class="' . esc_attr( $class ) . '" aria-hidden="true">' . esc_html( $found[0] ) . '</p>';
		}
	}

	/**
	 * @param string $code Código de la query, sin datos personales.
	 * @return string
	 */
	private function emailNotice( $code ) {
		return \SulayrReservas\Swimmers\EmailChangeService::notice( $code );
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
	 * @param string $created_at Fecha de alta `Y-m-d H:i:s`.
	 * @return string
	 */
	private function createdLabel( $created_at ) {
		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $created_at, wp_timezone() );
		if ( ! $date instanceof \DateTimeImmutable ) {
			return '' !== $created_at ? $created_at : '—';
		}

		return $date->format( 'd/m/Y' );
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

		return ucfirst( $this->weekdayName( (int) $date->format( 'N' ) ) . ' ' . $date->format( 'j' ) . ' de ' . $this->monthName( (int) $date->format( 'n' ) ) );
	}

	/**
	 * @param int  $iso_day Día ISO 1-7.
	 * @param bool $capital True si la inicial va en mayúscula.
	 * @return string
	 */
	private function weekdayName( $iso_day, $capital = false ) {
		$week = array( 1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo' );
		$name = isset( $week[ (int) $iso_day ] ) ? $week[ (int) $iso_day ] : '';

		return $capital ? ucfirst( $name ) : $name;
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

	/**
	 * @param object $session Fila con fecha y hora de inicio.
	 * @return \DateTimeImmutable|null
	 */
	private function sessionStart( $session ) {
		$start = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $session->session_date . ' ' . $session->start_time, wp_timezone() );

		return $start instanceof \DateTimeImmutable ? $start : null;
	}

	/**
	 * Icono decorativo. El texto visible del control sigue fuera del SVG.
	 *
	 * @param string $name Nombre interno.
	 * @return string
	 */
	private function icon( $name ) {
		$paths = array(
			'calendar' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"></rect><path d="M3.5 10h17M8 3.5v3M16 3.5v3"></path>',
			'list'     => '<path d="M9 7h11M9 12h11M9 17h11"></path><path d="M4.5 7h.01M4.5 12h.01M4.5 17h.01"></path>',
			'user'     => '<circle cx="12" cy="8" r="3.25"></circle><path d="M5.5 19.5c1.4-3 3.6-4.5 6.5-4.5s5.1 1.5 6.5 4.5"></path>',
			'users'    => '<circle cx="9" cy="8.5" r="2.75"></circle><path d="M3.5 19c1.1-2.6 2.9-4 5.5-4s4.4 1.4 5.5 4"></path><circle cx="17" cy="9" r="2.1"></circle><path d="M16 15.2c1.8.3 3.2 1.3 4.2 3.3"></path>',
			'plus'     => '<path d="M12 5v14M5 12h14"></path>',
			'clock'    => '<circle cx="12" cy="12" r="8"></circle><path d="M12 8v4.5l3 2"></path>',
			'logout'   => '<path d="M9 7V5.5A1.5 1.5 0 0 1 10.5 4h7A1.5 1.5 0 0 1 19 5.5v13a1.5 1.5 0 0 1-1.5 1.5h-7A1.5 1.5 0 0 1 9 18.5V17"></path><path d="M13 12H4M6.5 9.5 4 12l2.5 2.5"></path>',
			'external' => '<path d="M14 5h5v5"></path><path d="M19 5l-8 8"></path><path d="M17 13.5V18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h4.5"></path>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="sulayr-icon" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">' . $paths[ $name ] . '</svg>';
	}
}
