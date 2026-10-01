<?php

namespace SulayrReservas\Activation;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Context\BlogContextException;
use SulayrReservas\Database\Schema;
use SulayrReservas\Roles\RoleRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * Guarda los identificadores por defecto solo si aún no existen.
	 *
	 * @return void
	 */
	public static function activate() {
		if ( ! is_multisite() ) {
			return;
		}

		add_site_option( SiteConfig::OPTION_CLUB_BLOG_ID, SiteConfig::DEFAULT_CLUB_BLOG_ID );
		add_site_option( SiteConfig::OPTION_BOOKINGS_BLOG_ID, SiteConfig::DEFAULT_BOOKINGS_BLOG_ID );

		$config = new SiteConfig();
		if ( ! $config->isValid() ) {
			return;
		}

		try {
			$context = new BlogContext( $config );
			( new Schema( $config, $context ) )->install();
			$context->onBookings(
				static function () {
					( new RoleRegistrar() )->sync();
				}
			);
		} catch ( BlogContextException $exception ) {
			return;
		}
	}
}
