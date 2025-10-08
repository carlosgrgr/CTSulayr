<?php

namespace TotalTheme\Updates;

use WP_Error;
use TotalTheme\License_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Provides updates for the Total theme.
 */
final class Theme_Updater {

	/**
	 * Total theme updater API url.
	 */
	private const API_URL = 'https://wpexplorer-updates.com/api/v1/';

	/**
	 * Instance.
	 */
	private static $instance = null;

	/**
	 * Create or retrieve the instance of Theme_Updater.
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new self();
		}
		return static::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		if ( ! $this->is_enabled() || ! totaltheme_get_license() ) {
			return;
		}

		add_filter( 'pre_set_site_transient_update_themes', [ $this, '_filter_pre_set_site_transient_update_themes' ] );
		
		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_totaltheme_check_for_updates', [ $this, '_ajax_check_for_updates_callback' ] );
		}
	}

	/**
	 * Checks if auto updates are enabled.
	 */
	public function is_enabled(): bool {
		return (bool) apply_filters( 'totaltheme/updates/theme_updater/is_enabled', true );
	}

	/**
	 * Makes a call to the API.
	 */
	private function call_api( $action, $params ) {
		$request = wp_remote_get( add_query_arg( $params, self::API_URL . $action ) );

		// Check if the request failed
		if ( is_wp_error( $request ) ) {
			return new WP_Error(
				'wpexplorer_updates_error',
				sprintf(
					esc_html__( 'Theme update check failed: %s', 'total' ),
					esc_html( $request->get_error_message() )
				)
			);
		}

		// Get the HTTP response code
		$response_code = wp_remote_retrieve_response_code( $request );

		// Check if the response code is not 200 (OK)
		if ( $response_code !== 200 ) {
			return new WP_Error(
				'wpexplorer_updates_error',
				sprintf(
					esc_html__( 'API request failed with status code %d.', 'total' ),
					absint( $response_code )
				)
			);
		}

		// Get the response body and decode it
		$response = json_decode( wp_remote_retrieve_body( $request ) );
		
		// If response is an empty array, it means no updates are available
		if ( is_array( $response ) && empty( $response ) ) {
			return false;
		}

		// Check if the response is invalid or contains an error
		if ( ! is_object( $response ) ) {
			return new WP_Error(
				'wpexplorer_updates_error',
				esc_html__( 'Invalid response from the theme updater API.', 'total' )
			);
		}

		// Check if the API response contains an error
		if ( isset( $response->error ) ) {
			return new WP_Error( 'wpexplorer_updates_error', esc_html( $response->error ) );
		}

		return $response;
	}

	/**
	 * Gets the update info or returns a WP_Error if something goes wrong.
	 */
	private function get_update_info() {
		$api_response = $this->call_api( 'info', [
			'theme'   => 'Total',
			'license' => urlencode( sanitize_text_field( totaltheme_get_license() ) ),
			'version' => wp_get_theme( 'Total' )->get( 'Version' ),
			'url'     => License_Manager::instance()->get_site_url_for_api(),
		] );

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		if ( isset( $api_response->version )
			&& ! empty( $api_response->package )
			&& version_compare( wp_get_theme( 'Total' )->get( 'Version' ), $api_response->version, '<' )
		) {
			return [
				'new_version'  => $api_response->version,
				'package'      => $api_response->package,
				'requires'     => $api_response->requires ?? '',
				'requires_php' => $api_response->requires_php ?? '',
				'url'          => $api_response->changelog ?? 'https://totalwptheme.com/docs/changelog/',
			];
		}

		return false;
	}

	/**
	 * Hooks into the pre_set_site_transient_update_themes filter.
	 */
	public function _filter_pre_set_site_transient_update_themes( $transient ) {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$update_info = $this->get_update_info();

		if ( is_wp_error( $update_info ) ) {
			return $transient;
		}

		// Update available
		if ( $update_info ) {
			$transient->response['Total'] = [
				'theme'        => 'Total',
				'new_version'  => $update_info['new_version'],
				'package'      => $update_info['package'],
				'requires'     => $update_info['requires'],
				'requires_php' => $update_info['requires_php'],
				'url'          => $update_info['url'],
			];
		}
		// No update available
		elseif ( isset( $transient->no_update ) ) {
			// Adding the "mock" item to the `no_update` property is required
			// for the enable/disable auto-updates links to correctly appear in UI.
			$transient->no_update['Total'] = [
				'theme'        => 'Total',
				'new_version'  => wp_get_theme( 'Total' )->get( 'Version' ),
				'package'      => '',
				'requires'     => '',
				'requires_php' => '',
				'url'          => '',
			];
		}

		return $transient;
	}

	/**
	 * Ajax callback to force update check.
	 */
	public function _ajax_check_for_updates_callback() {
		if ( ! isset( $_POST['nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'totaltheme-check-for-updates' )
		) {
			wp_send_json_error( [ 'message' => esc_html__( 'Invalid nonce.', 'total' ) ] );
		}

		$transient_key = 'totaltheme_manual_update_check';

		if ( get_transient( $transient_key ) ) {
			wp_send_json_error( [
				'message' => esc_html__( 'To ensure optimal performance, manual update checks are limited to once per minute.', 'total' ),
			] );
		}

		$update_info = $this->get_update_info();

		if ( is_wp_error( $update_info ) ) {
			wp_send_json_error( [ 'message' => esc_html( $update_info->get_error_message() ) ] );
		}

		// Set the transient after a successful check to prevent users from spamming the button
		set_transient( $transient_key, true, MINUTE_IN_SECONDS );

		// No updates available
		if ( false === $update_info ) {
			wp_send_json_success( [
				'message' => esc_html__( 'No updates available.', 'total' ),
			] );
		}

		// Force the update transient to be updated.
		$transient_deleted = delete_site_transient( 'update_themes' );

		if ( ! $transient_deleted ) {
			wp_send_json_error( [
				'message' => sprintf(
					esc_html__( 'Version %s is now available. But the WordPress update cache failed to delete.', 'total' ),
					$update_info['new_version']
				),
			] );
		}

		wp_send_json_success( [
			'message' => sprintf(
				esc_html__( 'Version %s is now available.', 'total' ),
				$update_info['new_version']
			),
		] );
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		trigger_error( 'Cannot unserialize a Singleton.', E_USER_WARNING );
	}

}
