<?php

namespace SulayrReservas\Access;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Roles\RoleRegistrar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Impide el wp-admin a nadador y gestor en el sitio de reservas.
 */
class AdminAccess {

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Identificadores de los sitios.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'blockAdmin' ), 1 );
		add_filter( 'show_admin_bar', array( $this, 'filterAdminBar' ) );
	}

	/**
	 * @return void
	 */
	public function blockAdmin() {
		if ( ! $this->shouldBlockCurrentUser() ) {
			return;
		}

		$url = ( new \SulayrReservas\Front\PanelPage( $this->config ) )->url();
		wp_safe_redirect( '' !== $url ? $url : home_url( '/' ) );
		exit;
	}

	/**
	 * @param bool $show Valor que WordPress iba a usar.
	 * @return bool
	 */
	public function filterAdminBar( $show ) {
		if ( $this->shouldHideAdminBar() ) {
			return false;
		}

		return $show;
	}

	/**
	 * @return bool
	 */
	public function shouldBlockCurrentUser() {
		if ( ! $this->config->isBookingsSite() || ! is_user_logged_in() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}

		$user = wp_get_current_user();

		return $user instanceof \WP_User && $this->mustBlockUser( $user );
	}

	/**
	 * @return bool
	 */
	public function shouldHideAdminBar() {
		if ( ! $this->config->isBookingsSite() || ! is_user_logged_in() ) {
			return false;
		}

		$user = wp_get_current_user();

		return $user instanceof \WP_User && $this->mustBlockUser( $user );
	}

	/**
	 * Bloquea solo a quien tiene nadador o gestor y ningún otro rol.
	 * Administrador, superadministrador y cualquier rol adicional siguen entrando.
	 *
	 * @param \WP_User $user Usuario ya cargado en el sitio actual.
	 * @return bool
	 */
	public function mustBlockUser( \WP_User $user ) {
		if ( ! $user->exists() || is_super_admin( $user->ID ) ) {
			return false;
		}

		$roles = array_values( (array) $user->roles );

		if ( in_array( 'administrator', $roles, true ) ) {
			return false;
		}

		$restricted = array( RoleRegistrar::ROLE_NADADOR, RoleRegistrar::ROLE_GESTOR );
		$matched    = array_intersect( $roles, $restricted );

		if ( array() === $matched ) {
			return false;
		}

		$others = array_diff( $roles, $restricted );

		return array() === $others;
	}
}
