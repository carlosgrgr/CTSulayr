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

	/** Clave del ítem en el menú de Mi cuenta. No es un endpoint de WooCommerce. */
	const MENU_ITEM = 'reservas-piscina';

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	/** @var bool|null Resultado de la petición actual. */
	private $pool_link_visible;

	/** @var int|null Nivel del buffer del menú de Mi cuenta. */
	private $nav_buffer_level;

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
		add_filter( 'woocommerce_account_menu_items', array( $this, 'addPoolMenuItem' ), 20 );
		add_filter( 'woocommerce_get_endpoint_url', array( $this, 'poolMenuUrl' ), 10, 4 );
		add_action( 'woocommerce_before_account_navigation', array( $this, 'bufferAccountNav' ), 0 );
		add_action( 'woocommerce_after_account_navigation', array( $this, 'decorateAccountNav' ), 999 );
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
		$url = $this->poolLinkUrl();
		if ( '' === $url ) {
			return;
		}

		echo '<p class="sulayr-pool-link-wrap"><a class="sulayr-pool-link" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">Reservas de piscina' . $this->externalLinkIcon() . '<span class="screen-reader-text"> (se abre en una pestaña nueva)</span></a></p>';
	}

	/**
	 * Empieza a capturar el menú de Mi cuenta para marcar el enlace externo.
	 *
	 * @return void
	 */
	public function bufferAccountNav() {
		if ( ! $this->showsPoolLink() ) {
			return;
		}

		ob_start();
		$this->nav_buffer_level = ob_get_level();
	}

	/**
	 * Abre el enlace del menú en una pestaña nueva y le añade el icono.
	 *
	 * @return void
	 */
	public function decorateAccountNav() {
		if ( null === $this->nav_buffer_level || ob_get_level() !== $this->nav_buffer_level ) {
			return;
		}

		$html = ob_get_clean();
		$this->nav_buffer_level = null;
		if ( ! is_string( $html ) ) {
			return;
		}

		$endpoint = preg_quote( self::MENU_ITEM, '/' );
		$pattern  = '/<li class="([^"]*\bwoocommerce-MyAccount-navigation-link--' . $endpoint . '\b[^"]*)">\s*<a href="([^"]*)"[^>]*>\s*Reservas de piscina\s*<\/a>/';
		$icon     = $this->externalLinkIcon();
		$replaced = preg_replace_callback(
			$pattern,
			static function ( $matches ) use ( $icon ) {
				return '<li class="' . $matches[1] . '"><a href="' . $matches[2] . '" target="_blank" rel="noopener noreferrer">Reservas de piscina' . $icon . '<span class="screen-reader-text"> (se abre en una pestaña nueva)</span></a>';
			},
			$html,
			1
		);

		echo is_string( $replaced ) ? $replaced : $html;
	}

	/**
	 * Añade el enlace al menú de Mi cuenta, junto al perfil del deportista.
	 *
	 * @param array<string, string> $items Etiquetas del menú.
	 * @return array<string, string>
	 */
	public function addPoolMenuItem( $items ) {
		if ( ! is_array( $items ) || '' === $this->poolLinkUrl() ) {
			return is_array( $items ) ? $items : array();
		}

		$label    = 'Reservas de piscina';
		$menu     = array();
		$inserted = false;

		foreach ( $items as $key => $item_label ) {
			$menu[ $key ] = $item_label;
			if ( 'perfil-deportista' === $key ) {
				$menu[ self::MENU_ITEM ] = $label;
				$inserted                = true;
			}
		}

		if ( $inserted ) {
			return $menu;
		}

		$menu = array();
		foreach ( $items as $key => $item_label ) {
			if ( 'customer-logout' === $key ) {
				$menu[ self::MENU_ITEM ] = $label;
			}
			$menu[ $key ] = $item_label;
		}

		if ( ! isset( $menu[ self::MENU_ITEM ] ) ) {
			$menu[ self::MENU_ITEM ] = $label;
		}

		return $menu;
	}

	/**
	 * El ítem del menú apunta al sitio de reservas, no a un endpoint local.
	 *
	 * @param string $url       URL que WooCommerce iba a usar.
	 * @param string $endpoint  Endpoint pedido.
	 * @param string $value     Valor del endpoint.
	 * @param string $permalink Permalink base.
	 * @return string
	 */
	public function poolMenuUrl( $url, $endpoint, $value, $permalink ) {
		unset( $value, $permalink );

		if ( self::MENU_ITEM !== $endpoint ) {
			return $url;
		}

		$target = $this->poolLinkUrl();

		return '' !== $target ? $target : $url;
	}

	/**
	 * Icono de enlace externo. El texto del enlace queda fuera del dibujo.
	 *
	 * @return string
	 */
	private function externalLinkIcon() {
		return '<svg class="sulayr-external-icon" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 5h5v5"></path><path d="M19 5l-8 8"></path><path d="M17 13.5V18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V8a1 1 0 0 1 1-1h4.5"></path></svg>';
	}

	/**
	 * URL del enlace si el usuario actual puede verlo. Vacía en caso contrario.
	 *
	 * @return string
	 */
	private function poolLinkUrl() {
		if ( ! $this->showsPoolLink() ) {
			return '';
		}

		return $this->bookingsEntryUrl();
	}

	/**
	 * Misma condición que el enlace de la ficha: sitio del club y natación activa.
	 *
	 * @return bool
	 */
	private function showsPoolLink() {
		if ( null !== $this->pool_link_visible ) {
			return $this->pool_link_visible;
		}

		$this->pool_link_visible = $this->config->isClubSite()
			&& is_user_logged_in()
			&& ( new SyncService( $this->blog_context ) )->isEligibleAthlete( get_current_user_id() );

		return $this->pool_link_visible;
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
