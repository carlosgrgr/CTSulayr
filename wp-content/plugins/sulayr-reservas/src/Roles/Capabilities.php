<?php

namespace SulayrReservas\Roles;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capacidades del plugin. Traducen los permisos del SPEC; no añaden otros.
 */
class Capabilities {

	const VIEW_CALENDAR      = 'sulayr_view_calendar';
	const BOOK_SELF          = 'sulayr_book_self';
	const CANCEL_OWN         = 'sulayr_cancel_own';
	const VIEW_OWN_BOOKINGS  = 'sulayr_view_own_bookings';
	const VIEW_ALL_BOOKINGS  = 'sulayr_view_all_bookings';
	const BOOK_FOR_OTHERS    = 'sulayr_book_for_others';
	const CANCEL_ANY         = 'sulayr_cancel_any';
	const MANAGE_SWIMMERS    = 'sulayr_manage_swimmers';
	const MANAGE_SESSIONS    = 'sulayr_manage_sessions';

	/**
	 * @return array<string, bool>
	 */
	public static function nadador() {
		$caps = array( 'read' => true );

		foreach ( self::nadadorNames() as $name ) {
			$caps[ $name ] = true;
		}

		return $caps;
	}

	/**
	 * @return array<string, bool>
	 */
	public static function gestor() {
		$caps = self::nadador();

		foreach ( self::gestorOnlyNames() as $name ) {
			$caps[ $name ] = true;
		}

		return $caps;
	}

	/**
	 * @return string[]
	 */
	public static function nadadorNames() {
		return array(
			self::VIEW_CALENDAR,
			self::BOOK_SELF,
			self::CANCEL_OWN,
			self::VIEW_OWN_BOOKINGS,
		);
	}

	/**
	 * @return string[]
	 */
	public static function gestorOnlyNames() {
		return array(
			self::VIEW_ALL_BOOKINGS,
			self::BOOK_FOR_OTHERS,
			self::CANCEL_ANY,
			self::MANAGE_SWIMMERS,
			self::MANAGE_SESSIONS,
		);
	}

	/**
	 * Capacidades del plugin que puede tener el gestor, administrador incluido.
	 *
	 * @return string[]
	 */
	public static function pluginNames() {
		return array_merge( self::nadadorNames(), self::gestorOnlyNames() );
	}
}
