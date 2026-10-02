<?php

namespace SulayrReservas\Front;

use SulayrReservas\Bookings\BookingService;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Sessions\SessionEditor;
use SulayrReservas\Sessions\SessionGenerator;
use SulayrReservas\Swimmers\SwimmerService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Procesa los formularios del panel en el front, sin pasar por admin-post.php.
 */
class PanelActions {

	const NONCE = 'sulayr_panel';

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param SiteConfig  $config       Sitios.
	 * @param BlogContext $blog_context Cambio de sitio.
	 */
	public function __construct( SiteConfig $config, BlogContext $blog_context ) {
		$this->config       = $config;
		$this->blog_context = $blog_context;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'handle' ), 20 );
	}

	/**
	 * @return void
	 */
	public function handle() {
		if ( ! $this->config->isBookingsSite() || ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		if ( empty( $_POST['sulayr_action'] ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['sulayr_action'] ) );
		if ( ! is_user_logged_in() ) {
			$this->redirect( 'unauthorized', 'Entra con tu cuenta para continuar.' );
		}

		$nonce = isset( $_POST['sulayr_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['sulayr_nonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE ) ) {
			$this->redirect( 'unauthorized', 'La petición no es válida. Vuelve a intentarlo.' );
		}

		if ( $this->blockedSwimmer() ) {
			$this->redirect( 'unauthorized', 'No puedes acceder al sistema de reservas.' );
		}

		switch ( $action ) {
			case 'book':
				$this->book();
				break;
			case 'cancel':
				$this->cancel();
				break;
			case 'generate':
				$this->generate();
				break;
			case 'capacity':
				$this->capacity();
				break;
			case 'enabled':
				$this->enabled();
				break;
			case 'swimmer_create':
				$this->createSwimmer();
				break;
			case 'swimmer_deactivate':
				$this->changeSwimmer( 'deactivate' );
				break;
			case 'swimmer_reactivate':
				$this->changeSwimmer( 'reactivate' );
				break;
			case 'swimmer_remove':
				$this->removeSwimmer();
				break;
			default:
				$this->redirect( 'unauthorized', 'La petición no es válida. Vuelve a intentarlo.' );
		}
	}

	/**
	 * @return void
	 */
	private function book() {
		if ( ! current_user_can( Capabilities::BOOK_SELF ) && ! current_user_can( Capabilities::BOOK_FOR_OTHERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		$swimmer_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : get_current_user_id();
		if ( $swimmer_id < 1 ) {
			$swimmer_id = get_current_user_id();
		}

		$result = ( new BookingService( $this->blog_context ) )->book( $session_id, $swimmer_id );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function cancel() {
		if ( ! current_user_can( Capabilities::CANCEL_OWN ) && ! current_user_can( Capabilities::CANCEL_ANY ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$booking_id = isset( $_POST['booking_id'] ) ? absint( $_POST['booking_id'] ) : 0;
		$result     = ( new BookingService( $this->blog_context ) )->cancel( $booking_id );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function generate() {
		if ( ! current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$generator = new SessionGenerator( $this->blog_context );
		$month     = $generator->offeredMonth();
		if ( ! is_string( $month ) || '' === $month ) {
			$this->redirect( 'rejected', 'Ahora no se puede generar un mes.' );
		}

		$result  = $generator->generate( $month, get_current_user_id() );
		$message = 'failed' === $result->status() ? 'No se han podido generar las sesiones.' : $result->message();
		$this->redirect( $result->status(), $message );
	}

	/**
	 * @return void
	 */
	private function capacity() {
		if ( ! current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$raw = isset( $_POST['capacity'] ) ? trim( (string) wp_unslash( $_POST['capacity'] ) ) : '';
		if ( ! preg_match( '/^\d+$/', $raw ) ) {
			$this->redirect( 'invalid', 'La capacidad no es válida.' );
		}

		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		$result     = ( new SessionEditor( $this->blog_context ) )->setCapacity( $session_id, (int) $raw );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function enabled() {
		if ( ! current_user_can( Capabilities::MANAGE_SESSIONS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$raw = isset( $_POST['enabled'] ) ? (string) wp_unslash( $_POST['enabled'] ) : '';
		if ( '0' !== $raw && '1' !== $raw ) {
			$this->redirect( 'invalid', 'El estado de la sesión no es válido.' );
		}

		$session_id = isset( $_POST['session_id'] ) ? absint( $_POST['session_id'] ) : 0;
		$result     = ( new SessionEditor( $this->blog_context ) )->setEnabled( $session_id, '1' === $raw );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function createSwimmer() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$data   = array(
			'first_name' => isset( $_POST['first_name'] ) ? (string) wp_unslash( $_POST['first_name'] ) : '',
			'last_name'  => isset( $_POST['last_name'] ) ? (string) wp_unslash( $_POST['last_name'] ) : '',
			'email'      => isset( $_POST['email'] ) ? (string) wp_unslash( $_POST['email'] ) : '',
			'phone'      => isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
			'dni'        => isset( $_POST['dni'] ) ? (string) wp_unslash( $_POST['dni'] ) : '',
		);
		$result = ( new SwimmerService( $this->blog_context ) )->create( $data );
		if ( 'created' !== $result->status() && 'reused' !== $result->status() ) {
			set_transient( $this->formKey(), $data, MINUTE_IN_SECONDS );
		}

		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @param string $operation deactivate o reactivate.
	 * @return void
	 */
	private function changeSwimmer( $operation ) {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$user_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		$service = new SwimmerService( $this->blog_context );
		$result  = 'reactivate' === $operation ? $service->reactivate( $user_id ) : $service->deactivate( $user_id );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function removeSwimmer() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$confirm = isset( $_POST['confirm_remove'] ) ? (string) wp_unslash( $_POST['confirm_remove'] ) : '';
		if ( '1' !== $confirm ) {
			$this->redirect( 'invalid', 'Confirma la retirada del nadador.' );
		}

		$user_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		$result  = ( new SwimmerService( $this->blog_context ) )->remove( $user_id );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * Un nadador inactivo no opera. Un gestor sigue pudiendo gestionar.
	 *
	 * @return bool
	 */
	private function blockedSwimmer() {
		if ( current_user_can( Capabilities::MANAGE_SWIMMERS ) || current_user_can( Capabilities::MANAGE_SESSIONS ) || current_user_can( Capabilities::VIEW_ALL_BOOKINGS ) ) {
			return false;
		}

		return ! ( new PanelQuery( $this->blog_context ) )->isActiveSwimmer( get_current_user_id() );
	}

	/**
	 * @param string $status  Estado de la operación.
	 * @param string $message Texto fijo del servicio o de esta capa.
	 * @return void
	 */
	private function redirect( $status, $message ) {
		set_transient(
			$this->noticeKey(),
			array(
				'status'  => sanitize_key( $status ),
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		$fallback = ( new PanelPage( $this->config ) )->url();
		if ( '' === $fallback ) {
			$fallback = home_url( '/' );
		}

		$return = isset( $_POST['sulayr_return'] ) ? esc_url_raw( wp_unslash( $_POST['sulayr_return'] ) ) : '';
		$target = wp_validate_redirect( $return, $fallback );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * @return string
	 */
	private function noticeKey() {
		return 'sulayr_panel_notice_' . get_current_user_id();
	}

	/**
	 * @return string
	 */
	private function formKey() {
		return 'sulayr_panel_form_' . get_current_user_id();
	}
}
