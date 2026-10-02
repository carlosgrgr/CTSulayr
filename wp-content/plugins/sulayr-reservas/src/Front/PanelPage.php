<?php

namespace SulayrReservas\Front;

use SulayrReservas\Config\SiteConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Localiza la página pública que contiene el shortcode del panel.
 */
class PanelPage {

	const SHORTCODE = 'sulayr_reservas';
	const OPTION    = 'sulayr_reservas_panel_page';

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Identificadores de los sitios.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Crea la página si un administrador entra y todavía no existe.
	 *
	 * @return void
	 */
	public function ensure() {
		if ( ! $this->config->isBookingsSite() || ! is_user_logged_in() || ! current_user_can( 'publish_pages' ) ) {
			return;
		}

		if ( $this->id() > 0 ) {
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => 'Reservas de natación',
				'post_name'    => 'reservas-natacion',
				'post_content' => '[' . self::SHORTCODE . ']',
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);

		if ( ! is_wp_error( $page_id ) && $page_id > 0 ) {
			update_option( self::OPTION, (int) $page_id, false );
		}
	}

	/**
	 * @return string
	 */
	public function url() {
		$id = $this->id();
		if ( $id < 1 ) {
			return '';
		}

		$url = get_permalink( $id );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * @return int
	 */
	public function id() {
		if ( ! $this->config->isBookingsSite() ) {
			return 0;
		}

		$stored = (int) get_option( self::OPTION, 0 );
		if ( $this->containsShortcode( $stored ) ) {
			return $stored;
		}

		global $wpdb;

		$found = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%[' . $wpdb->esc_like( self::SHORTCODE ) . '%'
			)
		);

		if ( $this->containsShortcode( $found ) ) {
			update_option( self::OPTION, $found, false );
			return $found;
		}

		return 0;
	}

	/**
	 * @param int $page_id Página.
	 * @return bool
	 */
	private function containsShortcode( $page_id ) {
		$post = $page_id > 0 ? get_post( $page_id ) : null;

		return $post instanceof \WP_Post
			&& 'publish' === $post->post_status
			&& has_shortcode( $post->post_content, self::SHORTCODE );
	}
}
