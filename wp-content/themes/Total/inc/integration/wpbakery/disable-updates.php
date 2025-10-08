<?php

namespace TotalTheme\Integration\WPBakery;

defined( 'ABSPATH' ) || exit;

final class Disable_Updates {

	/**
	 * Static-only class.
	 */
	private function __construct() {}

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', [ self::class, 'remove_admin_license_tab' ], 999 );
		add_filter( 'auto_update_plugin', [ self::class, 'disable_auto_updates' ], 10, 2 );
		self::disable_updater(); // prevents the initializatin of Vc_Updater() in admin only due to wpb bug
		add_action( 'vc_after_init_updater', [ self::class, 'on_vc_after_init_updater' ] );
		add_action( 'vc_after_init_license', [ self::class, 'on_vc_after_init_license' ] );
	}

	/**
	 * Hooks into "vc_after_init_updater".
	 */
	public static function on_vc_after_init_updater(): void {
		add_action( 'vc_after_init', [ self::class, 'remove_updater' ] );
	}

	/**
	 * Hooka into "on_vc_after_init_license".
	 */
	public static function on_vc_after_init_license(): void {
		if ( ! function_exists( 'vc_license' ) ) {
			return;
		}
		$vc_license = vc_license();
		if ( is_object( $vc_license ) ) {
			add_action( 'vc_after_init', function() use ( $vc_license ) {
				remove_action( 'admin_notices', [ $vc_license, 'adminNoticeLicenseActivation' ], 10 );
			} );
		}
	}

	/**
	 * Remove plugin license admin tab.
	 */
	public static function remove_admin_license_tab(): void {
		if ( defined( 'VC_PAGE_MAIN_SLUG' ) ) {
			remove_submenu_page( VC_PAGE_MAIN_SLUG, 'vc-updater' );
		}
	}

	/**
	 * Disable WP auto updates.
	 */
	public static function disable_auto_updates( $update, $item ) {
		if ( ! empty( $item->slug ) && 'js_composer' === $item->slug ) {
			return false;
		}
		return $update;
	}

	/**
	 * Disable VC updater.
	 */
	private static function disable_updater() {
		if ( ! function_exists( 'vc_manager' ) ) {
			return;
		}
		$vc_manager = vc_manager();
		if ( is_callable( [ $vc_manager, 'disableUpdater' ] ) ) {
			$vc_manager->disableUpdater( true );
		}
	}

	/**
	 * Remove VC updater.
	 */
	public static function remove_updater() {
		if ( ! class_exists( 'Vc_Updater', false ) ) {
			return; // prevents vc_updater() from initializing if it hasn't already
		}
	
		if ( ! function_exists( 'vc_updater' ) ) {
			return;
		}

		$vc_updater = vc_updater();

		if ( ! is_object( $vc_updater ) ) {
			return;
		}

		remove_filter( 'upgrader_pre_download', [ $vc_updater, 'preUpgradeFilter' ], 10 );

		if ( is_callable( [ $vc_updater, 'updateManager' ] ) ) {
			$update_manager = $vc_updater->updateManager();
			remove_filter( 'pre_set_site_transient_update_plugins', [ $update_manager, 'check_update' ], 10 );
			remove_filter( 'plugins_api', [ $update_manager, 'check_info', ], 10 );
			if ( function_exists( 'vc_plugin_name' ) ) {
				remove_action(
					'in_plugin_update_message-' . vc_plugin_name(),
					[ $update_manager, 'addUpgradeMessageLink' ]
				);
			}
		}	
	}

	/**
	 * Deprecated.
	 */
	public static function disable_vc_updater() {
		_deprecated_function( __METHOD__, 'Total 6.4', '' );
	}

}
