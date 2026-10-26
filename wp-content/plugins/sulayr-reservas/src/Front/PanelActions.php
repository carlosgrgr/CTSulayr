<?php

namespace SulayrReservas\Front;

use SulayrReservas\Bookings\BookingService;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;
use SulayrReservas\Sessions\SessionEditor;
use SulayrReservas\Sessions\SessionGenerator;
use SulayrReservas\Swimmers\CsvExporter;
use SulayrReservas\Swimmers\CsvImporter;
use SulayrReservas\Swimmers\EmailChangeService;
use SulayrReservas\Swimmers\PhotoService;
use SulayrReservas\Swimmers\SwimmerRepository;
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
			case 'swimmer_release':
				$this->releaseSwimmer();
				break;
			case 'swimmer_remove':
				$this->removeSwimmer();
				break;
			case 'profile_save':
				$this->saveProfile();
				break;
			case 'weekly_limit':
				$this->saveLimit();
				break;
			case 'email_request':
				$this->requestEmail();
				break;
			case 'password_change':
				$this->changePassword();
				break;
			case 'photo_save':
				$this->savePhoto();
				break;
			case 'photo_delete':
				$this->deletePhoto();
				break;
			case 'csv_preview':
				$this->previewCsv();
				break;
			case 'csv_confirm':
				$this->confirmCsv();
				break;
			case 'export_swimmers':
				$this->exportSwimmers();
				break;
			case 'export_bookings':
				$this->exportBookings();
				break;
			case 'export_template':
				$this->exportTemplate();
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
			'phone'        => isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
			'dni'          => isset( $_POST['dni'] ) ? (string) wp_unslash( $_POST['dni'] ) : '',
			'weekly_limit' => isset( $_POST['weekly_limit'] ) ? (string) wp_unslash( $_POST['weekly_limit'] ) : '',
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
	private function releaseSwimmer() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$confirm = isset( $_POST['confirm_release'] ) ? (string) wp_unslash( $_POST['confirm_release'] ) : '';
		if ( '1' !== $confirm ) {
			$this->redirect( 'invalid', 'Confirma el paso a CN Nazarí.' );
		}

		$user_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		$result  = ( new SwimmerService( $this->blog_context ) )->releaseFromClub( $user_id );
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
	 * @return void
	 */
	private function saveProfile() {
		$user_id = $this->targetSwimmer();
		$data    = array(
			'first_name' => isset( $_POST['first_name'] ) ? (string) wp_unslash( $_POST['first_name'] ) : '',
			'last_name'  => isset( $_POST['last_name'] ) ? (string) wp_unslash( $_POST['last_name'] ) : '',
			'phone'      => isset( $_POST['phone'] ) ? (string) wp_unslash( $_POST['phone'] ) : '',
		);
		$result  = ( new SwimmerService( $this->blog_context ) )->updatePersonal( $user_id, $data );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function saveLimit() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$user_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		$limit   = isset( $_POST['weekly_limit'] ) ? (string) wp_unslash( $_POST['weekly_limit'] ) : '';
		$result  = ( new SwimmerService( $this->blog_context ) )->updateWeeklyLimit( $user_id, $limit );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function requestEmail() {
		$user_id = $this->targetSwimmer();
		$email   = isset( $_POST['new_email'] ) ? (string) wp_unslash( $_POST['new_email'] ) : '';
		$result  = ( new EmailChangeService( $this->config, $this->blog_context ) )->request( $user_id, $email );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function changePassword() {
		$posted = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		if ( $posted > 0 && $posted !== get_current_user_id() ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$result = ( new SwimmerService( $this->blog_context ) )->changePassword(
			isset( $_POST['current_password'] ) ? (string) wp_unslash( $_POST['current_password'] ) : '',
			isset( $_POST['new_password'] ) ? (string) wp_unslash( $_POST['new_password'] ) : '',
			isset( $_POST['confirm_password'] ) ? (string) wp_unslash( $_POST['confirm_password'] ) : ''
		);
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function savePhoto() {
		$user_id = $this->targetSwimmer();
		$file    = isset( $_FILES['photo'] ) && is_array( $_FILES['photo'] ) ? $_FILES['photo'] : array();
		$result  = ( new PhotoService( $this->blog_context ) )->replace( $user_id, $file );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function deletePhoto() {
		$user_id = $this->targetSwimmer();
		$result  = ( new PhotoService( $this->blog_context ) )->remove( $user_id );
		$this->redirect( $result->status(), $result->message() );
	}

	/**
	 * @return void
	 */
	private function previewCsv() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$file = isset( $_FILES['csv'] ) && is_array( $_FILES['csv'] ) ? $_FILES['csv'] : array();
		if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
			$this->redirect( 'invalid', 'Selecciona un archivo CSV.' );
		}
		if ( ! empty( $file['size'] ) && (int) $file['size'] > 2097152 ) {
			$this->redirect( 'invalid', 'El CSV supera el tamaño máximo de 2 MB.' );
		}

		$contents = file_get_contents( $file['tmp_name'] );
		$parsed   = ( new CsvImporter() )->parse( is_string( $contents ) ? $contents : '' );
		if ( '' !== $parsed['error'] ) {
			delete_transient( $this->previewKey() );
			$this->redirect( 'invalid', $parsed['error'] );
		}

		$rows = ( new CsvImporter() )->validate( $parsed['rows'], new EmailChangeService( $this->config, $this->blog_context ) );
		set_transient(
			$this->previewKey(),
			array(
				'token' => bin2hex( random_bytes( 16 ) ),
				'rows'  => $rows,
			),
			15 * MINUTE_IN_SECONDS
		);
		$this->redirect( 'updated', 'Revisa la vista previa antes de crear nadadores.' );
	}

	/**
	 * @return void
	 */
	private function confirmCsv() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$stored = get_transient( $this->previewKey() );
		$token  = isset( $_POST['csv_token'] ) ? (string) wp_unslash( $_POST['csv_token'] ) : '';
		if ( ! is_array( $stored ) || empty( $stored['token'] ) || ! hash_equals( (string) $stored['token'], $token ) ) {
			$this->redirect( 'invalid', 'La vista previa ha caducado. Vuelve a subir el archivo.' );
		}

		delete_transient( $this->previewKey() );
		$service = new SwimmerService( $this->blog_context );
		$created = 0;
		$skipped = 0;
		foreach ( (array) $stored['rows'] as $row ) {
			if ( empty( $row['ok'] ) || empty( $row['data'] ) || ! is_array( $row['data'] ) ) {
				++$skipped;
				continue;
			}
			$result = $service->createImported( $row['data'] );
			if ( 'created' === $result->status() ) {
				++$created;
			} else {
				++$skipped;
			}
		}

		$this->redirect( 'updated', 'Se han creado ' . $created . ' nadadores. Filas no importadas: ' . $skipped . '.' );
	}

	/**
	 * @return void
	 */
	private function exportSwimmers() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$query     = new PanelQuery( $this->blog_context );
		$status    = isset( $_POST['estado'] ) ? sanitize_key( wp_unslash( $_POST['estado'] ) ) : 'todos';
		$search    = isset( $_POST['q'] ) ? (string) wp_unslash( $_POST['q'] ) : '';
		$sort      = isset( $_POST['orden'] ) ? sanitize_key( wp_unslash( $_POST['orden'] ) ) : 'apellidos';
		$direction = isset( $_POST['dir'] ) ? sanitize_key( wp_unslash( $_POST['dir'] ) ) : 'asc';
		$origin    = isset( $_POST['origen'] ) ? sanitize_key( wp_unslash( $_POST['origen'] ) ) : 'todos';
		$filters   = $query->cleanFilters( $status, $search, $sort, $direction, $origin );
		$rows    = $query->swimmers( $filters );
		( new CsvExporter() )->download( 'nadadores.csv', ( new CsvExporter() )->swimmers( $rows ) );
	}

	/**
	 * @return void
	 */
	private function exportBookings() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		$user_id = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		$rows    = $this->blog_context->onBookings(
			function () use ( $user_id ) {
				if ( ! ( new SwimmerRepository() )->find( $user_id ) ) {
					return null;
				}

				return ( new SwimmerRepository() )->bookingsForExport( $user_id );
			}
		);
		if ( null === $rows ) {
			$this->redirect( 'missing', 'El nadador no existe.' );
		}

		( new CsvExporter() )->download( 'reservas-nadador-' . $user_id . '.csv', ( new CsvExporter() )->bookings( $rows ) );
	}

	/**
	 * @return void
	 */
	private function exportTemplate() {
		if ( ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			$this->redirect( 'unauthorized', 'No tienes permiso para esta operación.' );
		}

		( new CsvExporter() )->download( 'plantilla-nadadores.csv', ( new CsvImporter() )->template() );
	}

	/**
	 * El nadador solo actúa sobre sí mismo. El gestor puede indicar otra ficha.
	 *
	 * @return int
	 */
	private function targetSwimmer() {
		$actor  = get_current_user_id();
		$posted = isset( $_POST['swimmer_user_id'] ) ? absint( $_POST['swimmer_user_id'] ) : 0;
		if ( current_user_can( Capabilities::MANAGE_SWIMMERS ) && $posted > 0 ) {
			return $posted;
		}

		return $actor;
	}

	/**
	 * @return string
	 */
	private function previewKey() {
		return 'sulayr_csv_preview_' . get_current_user_id();
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
