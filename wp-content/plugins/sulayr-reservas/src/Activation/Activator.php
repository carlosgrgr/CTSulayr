<?php

namespace SulayrReservas\Activation;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Context\BlogContextException;
use SulayrReservas\Database\Schema;

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
			( new Schema( $config, new BlogContext( $config ) ) )->install();
		} catch ( BlogContextException $exception ) {
			return;
		}
	}
}
