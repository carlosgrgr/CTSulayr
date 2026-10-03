<?php

namespace SulayrReservas\Swimmers;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Front\PanelPage;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cambio de correo con confirmación en el frontend de reservas.
 *
 * El correo vigente no cambia hasta que el enlace de un solo uso se abre antes de 24 horas.
 */
class EmailChangeService {

	const QUERY    = 'sulayr_confirmar_email';
	const LIFETIME = DAY_IN_SECONDS;

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	/** @var SwimmerRepository */
	private $repository;

	/**
	 * @param SiteConfig             $config       Sitios.
	 * @param BlogContext            $blog_context Cambio de sitio.
	 * @param SwimmerRepository|null $repository   Ficha local.
	 */
	public function __construct( SiteConfig $config, BlogContext $blog_context, ?SwimmerRepository $repository = null ) {
		$this->config       = $config;
		$this->blog_context = $blog_context;
		$this->repository   = $repository ? $repository : new SwimmerRepository();
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'init', array( $this, 'maybeConfirm' ), 5 );
	}

	/**
	 * @param int    $user_id   Nadador.
	 * @param string $new_email Correo solicitado.
	 * @return SwimmerResult
	 */
	public function request( $user_id, $new_email ) {
		return $this->blog_context->onBookings(
			function () use ( $user_id, $new_email ) {
				return $this->requestOnBookings( (int) $user_id, (string) $new_email );
			}
		);
	}

	/**
	 * Confirma el token de la URL y redirige al panel sin dejar el token en la dirección.
	 *
	 * @return void
	 */
	public function maybeConfirm() {
		if ( ! $this->config->isBookingsSite() || ! isset( $_GET[ self::QUERY ] ) ) {
			return;
		}

		$token = sanitize_text_field( wp_unslash( $_GET[ self::QUERY ] ) );
		$code  = $this->confirm( $token );
		$url   = ( new PanelPage( $this->config ) )->url();
		if ( '' === $url ) {
			$url = home_url( '/' );
		}

		wp_safe_redirect( add_query_arg( 'sulayr_aviso', $code, $url ) );
		exit;
	}

	/**
	 * @param string $token Token en claro, el de la URL.
	 * @return string Código de aviso, sin el correo ni el token.
	 */
	public function confirm( $token ) {
		$token = (string) $token;
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $token ) ) {
			return 'email_invalid';
		}

		return $this->blog_context->onBookings(
			function () use ( $token ) {
				return $this->confirmOnBookings( $token );
			}
		);
	}

	/**
	 * Dónde está ya un correo: club, reservas, ambos o una cuenta de la red sin esos sitios.
	 *
	 * @param string $email Correo.
	 * @param int    $except_user_id Usuario que puede conservar el suyo.
	 * @return string Vacío si está libre. Si no, `club`, `reservas`, `ambos` o `cuenta`.
	 */
	public function emailPlace( $email, $except_user_id = 0 ) {
		$email = sanitize_email( $email );
		if ( ! is_email( $email ) ) {
			return 'cuenta';
		}

		$user    = get_user_by( 'email', $email );
		$club    = false;
		$booking = false;
		if ( $user instanceof \WP_User && (int) $user->ID !== (int) $except_user_id ) {
			$club    = is_user_member_of_blog( $user->ID, $this->config->clubBlogId() );
			$booking = is_user_member_of_blog( $user->ID, $this->config->bookingsBlogId() );
			if ( ! $club && ! $booking ) {
				return 'cuenta';
			}
		}

		$local = $this->blog_context->onBookings(
			function () use ( $email, $except_user_id ) {
				if ( (int) $except_user_id > 0 ) {
					return $this->repository->findOtherUserIdByEmail( $email, (int) $except_user_id ) > 0;
				}

				return $this->repository->findUserIdByEmail( $email ) > 0;
			}
		);
		if ( $local ) {
			$booking = true;
		}

		if ( $club && $booking ) {
			return 'ambos';
		}
		if ( $club ) {
			return 'club';
		}
		if ( $booking ) {
			return 'reservas';
		}

		return '';
	}

	/**
	 * @param string $code Código público del resultado, sin correo ni token.
	 * @return string
	 */
	public static function notice( $code ) {
		$messages = array(
			'email_ok'      => 'El correo se ha actualizado.',
			'email_expired' => 'El enlace de confirmación ha caducado. Solicítalo de nuevo.',
			'email_invalid' => 'El enlace de confirmación no es válido.',
			'email_taken'   => 'Ese correo ya está en uso. El cambio no se ha aplicado.',
		);

		return isset( $messages[ $code ] ) ? $messages[ $code ] : '';
	}

	/**
	 * @param string $place Resultado de emailPlace().
	 * @return string
	 */
	public static function placeLabel( $place ) {
		if ( 'club' === $place ) {
			return 'Club';
		}
		if ( 'reservas' === $place ) {
			return 'Reservas';
		}
		if ( 'ambos' === $place ) {
			return 'Ambos';
		}
		if ( 'cuenta' === $place ) {
			return 'Cuenta de WordPress';
		}

		return '';
	}

	/**
	 * @param int    $user_id   Nadador.
	 * @param string $new_email Correo solicitado.
	 * @return SwimmerResult
	 */
	private function requestOnBookings( $user_id, $new_email ) {
		$actor = get_current_user_id();
		if ( $actor < 1 || $user_id < 1 ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$own = $actor === $user_id;
		if ( ! $own && ! current_user_can( Capabilities::MANAGE_SWIMMERS ) ) {
			return $this->result( SwimmerResult::UNAUTHORIZED, 'No tienes permiso para esta operación.' );
		}

		$row = $this->repository->find( $user_id );
		if ( ! $row ) {
			return $this->result( SwimmerResult::MISSING, 'El nadador no existe.' );
		}

		$email = sanitize_email( $new_email );
		if ( ! is_email( $email ) || strlen( $email ) > 100 ) {
			return $this->result( SwimmerResult::INVALID, 'El correo no es válido.' );
		}
		if ( strtolower( $email ) === strtolower( (string) $row->email ) ) {
			return $this->result( SwimmerResult::INVALID, 'Ese ya es el correo actual.' );
		}

		$place = $this->emailPlace( $email, $user_id );
		if ( '' !== $place ) {
			return $this->result( SwimmerResult::DUPLICATE, 'Ese correo ya está en uso (' . self::placeLabel( $place ) . ').' );
		}

		$token   = bin2hex( random_bytes( 32 ) );
		$hash    = self::hash( $token );
		$now     = new \DateTimeImmutable( 'now', wp_timezone() );
		$expires = $now->modify( '+' . self::LIFETIME . ' seconds' );
		$this->repository->setPendingEmail( $user_id, $email, $hash, $expires->format( 'Y-m-d H:i:s' ), $now->format( 'Y-m-d H:i:s' ) );

		$sent = wp_mail( $email, 'Confirma tu correo de reservas', $this->message( $token, $expires ) );
		if ( ! $sent ) {
			$this->repository->setPendingEmail( $user_id, '', '', '1970-01-01 00:00:00', $now->format( 'Y-m-d H:i:s' ) );
			return $this->result( SwimmerResult::FAILED, 'No se ha podido enviar el correo de confirmación.' );
		}

		return $this->result( SwimmerResult::UPDATED, 'Te hemos enviado un enlace al nuevo correo. El actual sigue vigente durante 24 horas.', $user_id );
	}

	/**
	 * @param string $token Token en claro.
	 * @return string
	 */
	private function confirmOnBookings( $token ) {
		$row = $this->repository->findByEmailToken( self::hash( $token ) );
		if ( ! $row || '' === (string) $row->pending_email ) {
			return 'email_invalid';
		}

		$expires = \DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', (string) $row->email_token_expires, wp_timezone() );
		$now     = new \DateTimeImmutable( 'now', wp_timezone() );
		if ( ! $expires instanceof \DateTimeImmutable || $now > $expires ) {
			return 'email_expired';
		}

		$email = sanitize_email( (string) $row->pending_email );
		$user  = get_userdata( (int) $row->user_id );
		if ( ! $user instanceof \WP_User || ! is_email( $email ) ) {
			return 'email_invalid';
		}

		$taken = get_user_by( 'email', $email );
		if ( ( $taken instanceof \WP_User && (int) $taken->ID !== (int) $row->user_id ) || $this->repository->findOtherUserIdByEmail( $email, (int) $row->user_id ) > 0 ) {
			return 'email_taken';
		}

		$old     = (string) $user->user_email;
		$updated = wp_update_user(
			array(
				'ID'         => (int) $row->user_id,
				'user_email' => $email,
			)
		);
		if ( is_wp_error( $updated ) ) {
			return 'email_taken';
		}

		$applied = $this->repository->applyConfirmedEmail( (int) $row->user_id, $email, (string) $row->email_token_hash, $now->format( 'Y-m-d H:i:s' ) );
		if ( ! $applied ) {
			wp_update_user(
				array(
					'ID'         => (int) $row->user_id,
					'user_email' => $old,
				)
			);
			return 'email_invalid';
		}

		return 'email_ok';
	}

	/**
	 * @param string             $token   Token en claro.
	 * @param \DateTimeImmutable $expires Caducidad.
	 * @return string
	 */
	private function message( $token, \DateTimeImmutable $expires ) {
		$url = ( new PanelPage( $this->config ) )->url();
		if ( '' === $url ) {
			$url = home_url( '/' );
		}
		$link = add_query_arg( self::QUERY, $token, $url );

		return "Hola,\n\nPara usar este correo en las reservas de natación abre este enlace antes del " . $expires->format( 'd/m/Y H:i' ) . ":\n\n" . $link . "\n\nEl enlace solo puede usarse una vez. Hasta entonces seguirá activo el correo anterior.\n\nSi no has pedido este cambio, ignora este mensaje.\n";
	}

	/**
	 * @param string $token Token en claro.
	 * @return string
	 */
	private static function hash( $token ) {
		return hash_hmac( 'sha256', $token, wp_salt( 'auth' ) );
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
}
