<?php

namespace SulayrReservas\Access;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Front\PanelPage;
use SulayrReservas\Front\PanelQuery;
use SulayrReservas\Sync\SyncService;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enlaces entre el club y reservas.
 *
 * La identidad es la cookie de red de WordPress. Este clase no autentica
 * a partir de un user_id ni transmite contraseñas. Solo decide el enlace,
 * la autorización de vuelta al perfil y a dónde volver tras cerrar sesión.
 */
class CrossSite {

	const ORIGIN_QUERY  = 'sulayr_desde';
	const ORIGIN_COOKIE = 'sulayr_reservas_origen';

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
		add_filter( 'allowed_redirect_hosts', array( $this, 'allowSiblingHosts' ) );
		add_action( 'init', array( $this, 'captureOrigin' ) );
		add_action( 'wp_logout', array( $this, 'forgetOrigin' ) );
		add_action( 'sulayr_athlete_profile_card', array( $this, 'renderPoolLink' ) );
	}

	/**
	 * El cierre de sesión puede volver al otro sitio. El destino permitido
	 * sale de la configuración, no de un parámetro abierto.
	 *
	 * @param string[] $hosts Hosts que WordPress ya acepta.
	 * @return string[]
	 */
	public function allowSiblingHosts( $hosts ) {
		$hosts = is_array( $hosts ) ? $hosts : array();

		foreach ( array( $this->config->clubBlogId(), $this->config->bookingsBlogId() ) as $blog_id ) {
			$host = wp_parse_url( (string) get_home_url( (int) $blog_id, '/' ), PHP_URL_HOST );
			if ( is_string( $host ) && '' !== $host ) {
				$hosts[] = $host;
			}
		}

		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Recuerda que el acceso llegó desde el club. No identifica al usuario.
	 *
	 * @return void
	 */
	public function captureOrigin() {
		if ( ! $this->config->isBookingsSite() || ! $this->transportAcceptsCookie() ) {
			return;
		}

		$value = isset( $_GET[ self::ORIGIN_QUERY ] ) ? sanitize_key( wp_unslash( $_GET[ self::ORIGIN_QUERY ] ) ) : '';
		if ( 'club' !== $value ) {
			return;
		}

		$this->setOriginCookie( 'club', time() + 12 * HOUR_IN_SECONDS );
	}

	/**
	 * @return void
	 */
	public function forgetOrigin() {
		$this->setOriginCookie( '', time() - HOUR_IN_SECONDS );
	}

	/**
	 * Enlace del perfil del deportista. Solo si el club ya lo considera elegible.
	 *
	 * @return void
	 */
	public function renderPoolLink() {
		if ( ! $this->config->isClubSite() || ! is_user_logged_in() ) {
			return;
		}

		if ( ! ( new SyncService( $this->blog_context ) )->isEligibleAthlete( get_current_user_id() ) ) {
			return;
		}

		$url = $this->bookingsEntryUrl();
		if ( '' === $url ) {
			return;
		}

		echo '<p class="sulayr-pool-link-wrap"><a class="sulayr-pool-link" href="' . esc_url( $url ) . '">Reservas de piscina</a></p>';
	}

	/**
	 * URL del panel. La marca de origen no elige al usuario.
	 *
	 * @return string
	 */
	public function bookingsEntryUrl() {
		try {
			return (string) $this->blog_context->onBookings(
				function () {
					$url = ( new PanelPage( $this->config ) )->url();
					if ( '' === $url ) {
						$url = (string) get_home_url( $this->config->bookingsBlogId(), '/' );
					}

					return add_query_arg( self::ORIGIN_QUERY, 'club', $url );
				}
			);
		} catch ( \Throwable $exception ) {
			return '';
		}
	}

	/**
	 * Perfil del usuario autenticado en el club. Ignora cualquier user_id de la petición.
	 *
	 * @return string Vacío si no es deportista activo con ficha activa en reservas.
	 */
	public function clubProfileUrlForCurrentUser() {
		$user_id = get_current_user_id();
		if ( $user_id < 1 ) {
			return '';
		}

		if ( ! ( new SyncService( $this->blog_context ) )->isActiveAthlete( $user_id ) ) {
			return '';
		}

		if ( ! ( new PanelQuery( $this->blog_context ) )->isActiveSwimmer( $user_id ) ) {
			return '';
		}

		try {
			return (string) $this->blog_context->onClub(
				function () {
					if ( function_exists( 'wc_get_page_permalink' ) && function_exists( 'wc_get_endpoint_url' ) ) {
						$account = wc_get_page_permalink( 'myaccount' );
						if ( is_string( $account ) && '' !== $account ) {
							return wc_get_endpoint_url( 'perfil-deportista', '', $account );
						}
					}

					return (string) get_home_url( $this->config->clubBlogId(), '/mi-cuenta/perfil-deportista/' );
				}
			);
		} catch ( \Throwable $exception ) {
			return '';
		}
	}

	/**
	 * Cierra la sesión de red. WordPress protege la acción con nonce.
	 *
	 * @return string
	 */
	public function logoutUrl() {
		$target = '';
		if ( $this->config->isBookingsSite() ) {
			$target = ( new PanelPage( $this->config ) )->url();
		}
		if ( '' === $target ) {
			$target = (string) home_url( '/' );
		}

		if ( $this->cameFromClub() ) {
			$club = (string) get_home_url( $this->config->clubBlogId(), '/' );
			if ( '' !== $club ) {
				$target = $club;
			}
		}

		return wp_logout_url( $target );
	}

	/**
	 * @return bool
	 */
	public function cameFromClub() {
		$query = isset( $_GET[ self::ORIGIN_QUERY ] ) ? sanitize_key( wp_unslash( $_GET[ self::ORIGIN_QUERY ] ) ) : '';
		if ( 'club' === $query ) {
			return true;
		}

		if ( ! isset( $_COOKIE[ self::ORIGIN_COOKIE ] ) ) {
			return false;
		}

		return 'club' === sanitize_key( wp_unslash( $_COOKIE[ self::ORIGIN_COOKIE ] ) );
	}

	/**
	 * El sitio local se sirve por HTTP. En un sitio configurado con HTTPS
	 * no se guarda la marca de origen si la petición no es segura.
	 *
	 * @return bool
	 */
	private function transportAcceptsCookie() {
		$home = (string) get_option( 'home' );
		if ( 0 === strpos( $home, 'https://' ) ) {
			return is_ssl();
		}

		return true;
	}

	/**
	 * @param string $value   Valor o cadena vacía para borrarla.
	 * @param int    $expires Marca de tiempo.
	 * @return void
	 */
	private function setOriginCookie( $value, $expires ) {
		if ( headers_sent() ) {
			return;
		}

		$path = defined( 'COOKIEPATH' ) && is_string( COOKIEPATH ) && '' !== COOKIEPATH ? COOKIEPATH : '/';

		setcookie(
			self::ORIGIN_COOKIE,
			$value,
			array(
				'expires'  => (int) $expires,
				'path'     => $path,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}
}
