<?php

namespace SulayrReservas\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea y actualiza los roles del sitio de reservas.
 *
 * Debe ejecutarse con el blog 2 como sitio actual.
 */
class RoleRegistrar {

	const ROLE_NADADOR = 'nadador';
	const ROLE_GESTOR  = 'gestor';

	const VERSION        = 3;
	const OPTION_VERSION = 'sulayr_reservas_roles_version';

	/**
	 * Sincroniza solo si la definición cambió o falta un rol.
	 *
	 * @return void
	 */
	public function syncIfNeeded() {
		$ready = (int) get_option( self::OPTION_VERSION, 0 ) === self::VERSION
			&& get_role( self::ROLE_NADADOR ) instanceof \WP_Role
			&& get_role( self::ROLE_GESTOR ) instanceof \WP_Role;

		if ( $ready ) {
			return;
		}

		$this->sync();
	}

	/**
	 * Deja los roles y las capacidades del plugin como están definidos en código.
	 *
	 * @return void
	 */
	public function sync() {
		$this->syncOwnedRole( self::ROLE_NADADOR, 'Nadador', Capabilities::nadador() );
		$this->syncOwnedRole( self::ROLE_GESTOR, 'Gestor', Capabilities::gestor() );
		$this->syncAdministrator();

		update_option( self::OPTION_VERSION, self::VERSION, false );
	}

	/**
	 * @param string               $name  Slug del rol.
	 * @param string               $label Nombre visible.
	 * @param array<string, bool>  $caps  Capacidades que el plugin garantiza.
	 * @return void
	 */
	private function syncOwnedRole( $name, $label, array $caps ) {
		$role = get_role( $name );

		if ( ! $role instanceof \WP_Role ) {
			add_role( $name, $label, $caps );
			return;
		}

		foreach ( $caps as $cap => $grant ) {
			if ( $grant ) {
				$role->add_cap( $cap );
			}
		}

		foreach ( array_keys( $role->capabilities ) as $cap ) {
			if ( 0 === strpos( $cap, 'sulayr_' ) && empty( $caps[ $cap ] ) ) {
				$role->remove_cap( $cap );
			}
		}
	}

	/**
	 * Añade al administrador del sitio las capacidades del gestor y las suyas propias.
	 * Solo añade o retira capacidades con prefijo sulayr_.
	 *
	 * @return void
	 */
	private function syncAdministrator() {
		$role = get_role( 'administrator' );

		if ( ! $role instanceof \WP_Role ) {
			return;
		}

		$granted = array();

		foreach ( Capabilities::administratorNames() as $cap ) {
			$role->add_cap( $cap );
			$granted[ $cap ] = true;
		}

		foreach ( array_keys( $role->capabilities ) as $cap ) {
			if ( 0 === strpos( $cap, 'sulayr_' ) && empty( $granted[ $cap ] ) ) {
				$role->remove_cap( $cap );
			}
		}
	}
}
