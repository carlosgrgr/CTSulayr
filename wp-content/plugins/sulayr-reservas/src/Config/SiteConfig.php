<?php

namespace SulayrReservas\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Identificadores de los sitios del club y de reservas.
 *
 * Los valores viven en opciones de red para no repetirlos por el código.
 */
class SiteConfig {

	const OPTION_CLUB_BLOG_ID     = 'sulayr_reservas_club_blog_id';
	const OPTION_BOOKINGS_BLOG_ID = 'sulayr_reservas_bookings_blog_id';

	const DEFAULT_CLUB_BLOG_ID     = 1;
	const DEFAULT_BOOKINGS_BLOG_ID = 2;

	/**
	 * @return int
	 */
	public function clubBlogId() {
		return (int) get_site_option( self::OPTION_CLUB_BLOG_ID, self::DEFAULT_CLUB_BLOG_ID );
	}

	/**
	 * @return int
	 */
	public function bookingsBlogId() {
		return (int) get_site_option( self::OPTION_BOOKINGS_BLOG_ID, self::DEFAULT_BOOKINGS_BLOG_ID );
	}

	/**
	 * @return bool
	 */
	public function isClubSite() {
		return get_current_blog_id() === $this->clubBlogId();
	}

	/**
	 * @return bool
	 */
	public function isBookingsSite() {
		return get_current_blog_id() === $this->bookingsBlogId();
	}

	/**
	 * @return bool
	 */
	public function isValid() {
		return array() === $this->errors();
	}

	/**
	 * Errores que impiden operar sobre los sitios configurados.
	 *
	 * @return string[]
	 */
	public function errors() {
		if ( ! is_multisite() ) {
			return array( 'El plugin requiere una instalación WordPress Multisite.' );
		}

		$club     = $this->clubBlogId();
		$bookings = $this->bookingsBlogId();
		$errors   = array();

		if ( $club < 1 ) {
			$errors[] = 'El identificador del sitio del club no es válido.';
		}

		if ( $bookings < 1 ) {
			$errors[] = 'El identificador del sitio de reservas no es válido.';
		}

		if ( $club > 0 && $club === $bookings ) {
			$errors[] = 'El sitio del club y el de reservas deben ser distintos.';
		}

		if ( $club > 0 && $club !== $bookings ) {
			$club_error = $this->siteError( $club, 'club' );
			if ( null !== $club_error ) {
				$errors[] = $club_error;
			}
		}

		if ( $bookings > 0 && $club !== $bookings ) {
			$bookings_error = $this->siteError( $bookings, 'reservas' );
			if ( null !== $bookings_error ) {
				$errors[] = $bookings_error;
			}
		}

		return $errors;
	}

	/**
	 * @param int    $blog_id Identificador a comprobar.
	 * @param string $label   Nombre funcional del sitio.
	 * @return string|null
	 */
	private function siteError( $blog_id, $label ) {
		$site = get_site( (int) $blog_id );

		if ( ! $site instanceof \WP_Site ) {
			return sprintf( 'No existe el sitio de %s (ID %d).', $label, $blog_id );
		}

		if ( (int) $site->archived === 1 || (int) $site->deleted === 1 || (int) $site->spam === 1 ) {
			return sprintf( 'El sitio de %s (ID %d) no está disponible.', $label, $blog_id );
		}

		return null;
	}
}
