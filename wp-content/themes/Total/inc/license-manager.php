<?php

namespace TotalTheme;

defined( 'ABSPATH' ) || exit;

/**
 * License Manager.
 */
class License_Manager {

	/**
	 * Stores the site URL to prevent extra checks.
	 */
	private $site_url = null;

	/**
	 * Check if this is a dev environment.
	 */
	private $is_dev_environment = null;

	/**
	 * Is license network enabled.
	 */
	private $is_license_network_actived = false;

	/**
	 * Stores the site license to prevent added checks.
	 */
	private $license = null;

	/**
	 * Instance.
	 */
	private static $instance = null;

	/**
	 * Create or retrieve the instance of our class.
	 */
	public static function instance() {
		if ( null === static::$instance ) {
			static::$instance = new self();
		}
		return static::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Return the site url used for the license.
	 */
	public function get_site_url() {
		if ( null === $this->site_url ) {
			$url = $this->is_license_network_actived() ? network_site_url() : site_url();
			if ( $url && is_string( $url ) ) {
				$this->site_url = trim( $url );
			} else {
				$this->site_url = '';
			}
		}
		return $this->site_url;
	}

	/**
	 * Return the site url for use with the activation and deactivation function.
	 * 
	 * This works differently from get_site_url because of the added is_network_admin() check.
	 */
	public function get_site_url_for_api() {
		$url = ( is_multisite() && is_network_admin() ) ? network_site_url() : site_url();
		if ( $url ) {
			return rawurlencode( $url );
		}
	}

	/**
	 * Checks if a license is valid.
	 * 
	 * license is version 4 UUID with the format: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
	 * where x is any hexadecimal digit and y is one of 8, 9, A, or B.
	 */
	protected function is_license_valid( string $license = '' ): bool {
		if ( ! $license ) {
			$license = $this->get_license();
			if ( ! $license ) {
				return false;
			}
		}
		$pattern = '/^[0-9A-F]{8}-[0-9A-F]{4}-4[0-9A-F]{3}-[89AB][0-9A-F]{3}-[0-9A-F]{12}$/i';
		return (bool) preg_match( $pattern, $license );
	}

	/**
	 * Retrieve the license from the database.
	 */
	protected function get_license_from_db() {
		$license = get_option( 'totaltheme_license', 'not-set' );
		if ( 'not-set' === $license ) {
			// Check the old option name
			$license = get_option( 'active_theme_license' );
			if ( $license ) {
				$updated = update_option( 'totaltheme_license', sanitize_text_field( $license ) );
				if ( true === $updated ) {
					delete_option( 'active_theme_license' );
				}
			}
		}
		return $license;
	}

	/**
	 * Update dp license.
	 */
	protected function update_license_in_db( string $license ): void {
		update_option( 'totaltheme_license', sanitize_text_field( $license ) );
	}

	/**
	 * Remove license from the database.
	 */
	protected function delete_license_from_db(): void {
		delete_option( 'totaltheme_license' );
		delete_option( 'active_theme_license' );
	}

	/**
	 * Gets the network saved theme license.
	 */
	public function get_network_license() {
		if ( is_multisite() ) {
			switch_to_blog( get_main_site_id() );
				$license = $this->get_license_from_db();
				if ( $license ) {
					$this->is_license_network_actived = true;
				}
			restore_current_blog();
			return $license;
		}
	}

	/**
	 * Get theme license.
	 */
	public function get_license() {
		if ( null === $this->license ) {
			$license = $this->get_license_from_db();
			if ( ! $license && is_multisite() && ! is_main_site() ) {
				$license = $this->get_network_license();
			}
			$this->license = $license ? sanitize_text_field( $license ) : '';
		}
		return $this->license;
	}

	/**
	 * Checks if the license is network activated.
	 */
	public function is_license_network_actived(): bool {
		$this->get_license();
		return $this->is_license_network_actived;
	}
	
	/**
	 * Set a temporary license status.
	 */
	protected function set_temporary_license_status( string $status, int $expires = 0 ): void {
		if ( ! $expires ) {
			$expires = 10 * MINUTE_IN_SECONDS;
		}
		set_transient( 'totaltheme_license_status', $status, $expires );
	}

	/**
	 * Check if the license is active.
	 */
	public function is_license_active( $force_check = false ): bool {
		return 'active' === $this->get_license_status( $force_check );
	}
	
	/**
	 * Returns the label for a license status.
	 */
	public function get_license_status_label( string $status ): string {
		switch ( $status ) {
			case 'active':
				return esc_html__( 'Active', 'total' );
			case 'duplicate':
				return esc_html__( 'Invalid: Already in Use', 'total' );
			case 'invalid':
				return esc_html__( 'Invalid', 'total' );
			case 'pending':
				return esc_html__( 'Pending', 'total' );
			case 'inactive':
			case 'unregistered':
			default:
				return esc_html__( 'Not Active', 'total' );
		}
	}

	/**
	 * Get license status.
	 *
	 * inactive: No license.
	 * active: Registered and functioning correctly.
	 * invalid: Not properly formatted license.
	 * unregistered: Not registered.
	 * duplicate: Already registered elsewhere.
	 * pending: License registration is pending.
	 */
	public function get_license_status( bool $force_check = false ): string {
		if ( ! $this->get_license() ) {
			return 'inactive';
		}
		if ( ! $this->is_license_valid() ) {
			return 'invalid';
		}
		if ( $force_check ) {
			delete_transient( 'totaltheme_license_status' );
			delete_transient( 'totaltheme_is_license_registered' );
		}
		$is_registered = $this->is_license_registered( $force_check );
		$transient_check = get_transient( 'totaltheme_license_status' );
		if ( false !== $transient_check ) {
			return $transient_check;
		}
		return ( $is_registered ? 'active' : 'unregistered' );
	}

	/**
	 * Check if the license is registered.
	 */
	private function is_license_registered( bool $force_check = false ): bool {
		$license = $this->get_license();
		if ( ! $license || ! $this->is_license_valid( $license ) ) {
			return false;
		}
		if ( ! $force_check ) {
			$transient_check = get_transient( 'totaltheme_is_license_registered' );
			if ( false !== $transient_check ) {
				return 'yes' === $transient_check;
			}
		}
		/**
		 * Allow the license to be "registered" if there are issues with the API server to prevent licenses
		 * from being removed from sites if the API is down.
		 */
		$check = false;
		$response = wp_remote_get( "https://my.totalwptheme.com/wp-json/twpt-license-manager/check/{$license}" );
		if ( ! is_wp_error( $response ) ) {
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 === (int) $response_code ) {
				$body = json_decode( wp_remote_retrieve_body( $response ) );
				$status = $body->status ?? '';
				$domain = $body->domain ?? '';
				if ( 'inactive' === $status ) {
					$this->set_temporary_license_status( 'unregistered' );
					$this->delete_license_from_db();
					$check = false;
				} elseif ( $domain !== $this->get_site_url() ) {
					$this->set_temporary_license_status( 'duplicate' );
					$check = false;
				} else {
					$check = true;
				}
			}
		}
		set_transient( 'totaltheme_is_license_registered', $check ? 'yes' : 'no', MONTH_IN_SECONDS );
		return $check;
	}

	/**
	 * Activate License.
	 */
	protected function activate_license( $license ): array {
		if ( ! $this->is_license_valid( $license ) ) {
			return [
				'message'       => esc_html__( 'Invalid license.', 'total' ),
				'messageClass'  => 'notice-error',
				'licenseStatus' => $this->get_license_status_label( 'inactive' ),
			];
		}
		$remote_response = wp_remote_post( 'https://my.totalwptheme.com/wp-json/twpt-license-manager/activate/', [
			'body' => [
				'key'    => $license,
				'domain' => $this->get_site_url_for_api(),
			],
		] );
		$response = [];
		if ( is_wp_error( $remote_response ) ) {
			$response['message'] = $remote_response->get_error_message();
		} else {
			$remote_response_code       = (int) wp_remote_retrieve_response_code( $remote_response );
			$response['response_code']  = $remote_response_code;
			$response['messageClass']   = 'notice-error';

			if ( 200 ===  $remote_response_code ) {
				$body = json_decode( wp_remote_retrieve_body( $remote_response ) );
				if ( ! empty( $body->activated ) ) {
					$response['success']       = true;
					$response['licenseStatus'] = $this->get_license_status_label( 'active' );
					$this->update_license_in_db( $license );
					delete_transient( 'totaltheme_license_status' );
					set_transient( 'totaltheme_is_license_registered', 'yes', MONTH_IN_SECONDS );
				} elseif ( ! empty( $body->error ) ) {
					switch ( $body->error ) {
						case 'api_error':
							$response['message'] = esc_html__( 'The license code is not properly formated or couldn\'t be validated by the Envato API.', 'total' );
							break;
						case 'wrong_product':
							$response['message'] = esc_html__( 'This license code is for a different product.', 'total' );
							break;
						case 'invalid':
							$response['message'] = esc_html__( 'This license code is not valid.', 'total' );
							break;
						case 'duplicate':
							$response['message'] = esc_html__( 'This license is currently in use. To deactivate it, click the "Manage Licenses" link beneath the form and remove it via the "License Manager".', 'total' );
							break;
						default:
							$response['message'] = esc_html( $body->error );
							break;
					}
				} else {
					$response['message'] = esc_html__( 'Something wen\'t wrong, please try again.', 'total' );
				}
			} else {
				switch ( $response_code ) {
					case 301:
						$response['message'] = esc_html__( 'Error 301: Firewall blocking access.', 'total' );
						break;
					case 403:
						$response['message'] = esc_html__( 'Error 403: Your server has been blocked by our firewall for security reasons.', 'total' );
						break;
					case 404:
						$response['message'] = esc_html__( 'Error 404: The API is down or your theme is outdated or it has been modified and is using the wrong URL.', 'total' );
						break;
					case 405:
						$response['message'] = esc_html__( 'Error 405: Method Not Allowed.', 'total' );
						break;
					default:
						$response['message'] = esc_html__( 'Can not connect to the verification server at this time. Please make sure outgoing connections are enabled on your server and try again. If it still does not work please wait a few minutes and try again.', 'total' );
					break;
				}
			}
		}
		return $response;
	}

	/**
	 * Deactivate License.
	 */
	protected function deactivate_license( $license, $license_status ): array {
		if ( ! $this->is_license_valid( $license ) || 'active' !== $license_status ) {
			$this->delete_license_from_db();
			return [
				'success'       => true,
				'licenseStatus' => $this->get_license_status_label( 'inactive' ),
			];
		}
		$remote_response = wp_remote_post( 'https://my.totalwptheme.com/wp-json/twpt-license-manager/deactivate/', [
			'body' => [
				'key'    => $license,
				'domain' => $this->get_site_url_for_api(),
			],
		] );
		$response = [];
		if ( is_wp_error( $remote_response ) ) {
			$response['message'] = $remote_response->get_error_message();
		} else {
			$remote_response_code = wp_remote_retrieve_response_code( $remote_response );
			$response['response_code'] = $remote_response_code;
			if ( 200 === (int) $remote_response_code ) {
				$body = json_decode( wp_remote_retrieve_body( $remote_response ) );
				if ( ! empty( $body->deactivated ) ) {
					$this->delete_license_from_db();
					$response['success']       = true;
					$response['licenseStatus'] = $this->get_license_status_label( 'inactive' );
				} elseif ( ! empty( $body->error ) ) {
					$response['message'] = esc_html( $body->error );
				} else {
					$response['message'] = esc_html( 'Something wen\'t wrong, please try again.', 'total' );
				}
			} else {
				$response['message'] = esc_html( 'Can not connect to the verification server at this time, please try again in a few minutes.', 'total' );
			}
		}
		return $response;
	}

	/**
	 * Helper to cache and return true.
	 */
	protected function set_dev_environment( bool $value ): bool {
		$this->is_dev_environment = $value;
		return $value;
	}

	/**
	 * Checks if currently a dev environment.
	 */
	protected function is_dev_environment(): bool {
		if ( null !== $this->is_dev_environment ) {
			return $this->is_dev_environment;
		}

		$this->is_dev_environment = false;
		$host = $this->get_site_url();

		if ( ! $host ) {
			return false;
		}

		$host = wp_parse_url( $host, PHP_URL_HOST );

		if ( ! $host ) {
			return false;
		}

		// IP address check (IPv4 and IPv6)
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $this->set_dev_environment( true );
		}

		$host = strtolower( $host );

		// Common local/dev TLDs
		$local_tlds = [
			'local',
			'dev',
			'wp',
			'test',
			'example',
			'localhost',
			'invalid',
		];

		foreach ( $local_tlds as $tld ) {
			if ( $tld === $host || str_ends_with( $host, "{$tld}" ) ) {
				return $this->set_dev_environment( true );
			}
		}

		return false;
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing.
	 */
	public function __wakeup() {
		trigger_error( 'Cannot unserialize a Singleton.', E_USER_WARNING);
	}

}
