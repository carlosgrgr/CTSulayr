<?php

namespace SulayrReservas\Front;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode único del panel público.
 */
class Shortcode {

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
		add_shortcode( PanelPage::SHORTCODE, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'sulayr_reservas_brand_menu', array( $this, 'brandMenu' ) );
		( new PanelPage( $this->config ) )->ensure();
		( new PanelActions( $this->config, $this->blog_context ) )->register();
	}

	/**
	 * @return string
	 */
	public function render() {
		if ( ! $this->config->isBookingsSite() ) {
			return '<p class="sulayr-panel">Este panel solo está disponible en el sitio de reservas.</p>';
		}

		if ( ! is_user_logged_in() ) {
			$url    = wp_login_url( ( new PanelPage( $this->config ) )->url() );
			$code   = isset( $_GET['sulayr_aviso'] ) ? sanitize_key( wp_unslash( $_GET['sulayr_aviso'] ) ) : '';
			$notice = \SulayrReservas\Swimmers\EmailChangeService::notice( $code );
			$html   = '';
			if ( '' !== $notice ) {
				$html .= '<p class="sulayr-panel" role="status">' . esc_html( $notice ) . '</p>';
			}
			$html .= '<p class="sulayr-panel">Entra con tu cuenta para ver las reservas. <a href="' . esc_url( $url ) . '">Iniciar sesión</a></p>';

			return $html;
		}

		$access = $this->access();
		if ( 'no_capability' === $access ) {
			return '<p class="sulayr-panel">No tienes acceso a las reservas.</p>';
		}

		if ( 'inactive' === $access ) {
			return '<p class="sulayr-panel">No puedes acceder al sistema de reservas.</p>';
		}

		return ( new PanelRenderer( $this->config, $this->blog_context ) )->render( 'manager' === $access );
	}

	/**
	 * Menú del panel dentro de la cabecera del tema. Solo si el panel se va a mostrar.
	 *
	 * @return void
	 */
	public function brandMenu() {
		if ( ! $this->config->isBookingsSite() || ! is_user_logged_in() ) {
			return;
		}

		$access = $this->access();
		if ( 'manager' !== $access && 'swimmer' !== $access ) {
			return;
		}

		echo ( new PanelRenderer( $this->config, $this->blog_context ) )->brandMenu( 'manager' === $access ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * @return string manager, swimmer, no_capability o inactive.
	 */
	private function access() {
		$manager = current_user_can( Capabilities::MANAGE_SWIMMERS )
			|| current_user_can( Capabilities::MANAGE_SESSIONS )
			|| current_user_can( Capabilities::VIEW_ALL_BOOKINGS );

		if ( $manager ) {
			return 'manager';
		}

		if ( ! current_user_can( Capabilities::VIEW_CALENDAR ) ) {
			return 'no_capability';
		}

		if ( ! ( new PanelQuery( $this->blog_context ) )->isActiveSwimmer( get_current_user_id() ) ) {
			return 'inactive';
		}

		return 'swimmer';
	}

	/**
	 * @return void
	 */
	public function assets() {
		if ( ! $this->config->isBookingsSite() || ! is_singular() ) {
			return;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post || ! has_shortcode( $post->post_content, PanelPage::SHORTCODE ) ) {
			return;
		}

		$path    = SULAYR_RESERVAS_DIR . 'assets/panel.css';
		$version = is_readable( $path ) ? (string) filemtime( $path ) : SULAYR_RESERVAS_VERSION;

		wp_enqueue_style(
			'sulayr-reservas-panel',
			plugins_url( 'assets/panel.css', SULAYR_RESERVAS_FILE ),
			array(),
			$version
		);

		$script = SULAYR_RESERVAS_DIR . 'assets/panel.js';
		wp_enqueue_script(
			'sulayr-reservas-panel',
			plugins_url( 'assets/panel.js', SULAYR_RESERVAS_FILE ),
			array(),
			is_readable( $script ) ? (string) filemtime( $script ) : $version,
			true
		);
	}
}
