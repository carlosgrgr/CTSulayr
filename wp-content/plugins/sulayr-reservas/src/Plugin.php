<?php

namespace SulayrReservas;

use SulayrReservas\Access\AdminAccess;
use SulayrReservas\Admin\NetworkSettingsPage;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Database\Schema;
use SulayrReservas\Roles\RoleRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Arranque del plugin. Configura los sitios e instala el esquema si hace falta.
 */
class Plugin {

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	public function init() {
		$this->config       = new SiteConfig();
		$this->blog_context = new BlogContext( $this->config );

		( new Schema( $this->config, $this->blog_context ) )->maybeInstall();

		if ( $this->config->isBookingsSite() ) {
			( new RoleRegistrar() )->syncIfNeeded();
		}

		( new AdminAccess( $this->config ) )->register();

		if ( is_admin() ) {
			( new NetworkSettingsPage( $this->config ) )->register();
		}
	}

	/**
	 * @return SiteConfig
	 */
	public function config() {
		return $this->config;
	}

	/**
	 * @return BlogContext
	 */
	public function blogContext() {
		return $this->blog_context;
	}
}
