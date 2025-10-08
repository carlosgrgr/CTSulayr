<?php

namespace TotalTheme\Admin;

use TotalTheme\License_Manager;

defined( 'ABSPATH' ) || exit;

/**
 * Theme License Activation and De-activation.
 */
if ( class_exists( 'TotalTheme\License_Manager' ) ) {
	final class License_Panel extends License_Manager {

		/**
		 * Instance.
		 */
		private static $instance = null;

		/**
		 * Create or retrieve the instance of License_Panel.
		 */
		public static function instance() {
			if ( null === static::$instance ) {
				static::$instance = new self();
			}

			return static::$instance;
		}

		/**
		 * Private Constructor.
		 */
		private function __construct() {
			$this->init_hooks();
		}

		/**
		 * Hook into actions and filters.
		 */
		public function init_hooks() {
			if ( ! $this->is_license_valid() ) {
				add_action( 'admin_notices', [ $this, '_on_admin_notices' ] );
			}

			if ( self::is_enabled() ) {
				if ( $this->is_network_admin() ) {
					add_action( 'network_admin_menu', [ $this, 'add_network_admin_page' ] );
				} else {
					add_action( 'admin_menu', [ $this, 'add_admin_submenu_page' ], 1 );
				}
				add_action( 'wp_ajax_wpex_theme_license_form', [ $this, 'license_form_ajax' ] );
			}
		}

		/**
		 * Check if we should display the admin page.
		 */
		public static function is_enabled(): bool {
			$check = true;
			if ( is_multisite() && ! is_main_site() && totaltheme_get_network_license() ) {
				$check = false;
			}
			return $check;
		}

		/**
		 * Check if we are on a multisite network admin screen.
		 */
		private function is_network_admin(): bool {
			return is_multisite() && is_network_admin();
		}

		/**
		 * Add network admin page.
		 */
		public function add_network_admin_page() {
			$hook_suffix = add_submenu_page(
				'themes.php',
				esc_html__( 'Total Theme License', 'total' ),
				esc_html__( 'Total Theme License', 'total' ),
				'manage_network_options',
				'total-theme-license',
				[ $this, 'render_admin_page' ]
			);

			add_action( "admin_print_styles-{$hook_suffix}", [ $this, 'enqueue_styles' ] );
			add_action( "admin_print_scripts-{$hook_suffix}", [ $this, 'enqueue_scripts' ] );
		}

		/**
		 * Add sub menu page.
		 */
		public function add_admin_submenu_page() {
			$hook_suffix = add_submenu_page(
				WPEX_THEME_PANEL_SLUG,
				esc_html__( 'Theme License', 'total' ),
				esc_html__( 'Theme License', 'total' ),
				'administrator', // admin only!
				WPEX_THEME_PANEL_SLUG . '-theme-license',
				[ $this, 'render_admin_page' ]
			);

			add_action( "load-{$hook_suffix}", [ $this, 'admin_help_tab' ] );
			add_action( "admin_print_styles-{$hook_suffix}", [ $this, 'enqueue_styles' ] );
			add_action( "admin_print_scripts-{$hook_suffix}", [ $this, 'enqueue_scripts' ] );
		}

		/**
		 * Add admin help tab.
		 */
		public function admin_help_tab() {
			$screen = get_current_screen();

			if ( ! $screen ) {
				return;
			}

			$screen->add_help_tab(
				[
					'id'      => 'totaltheme_panel',
					'title'   => esc_html__( 'Overview', 'total' ),
					'content' => '<p><strong>Please Read !!!</strong><p>A valid theme license is required for each domain using the Total WordPress theme. If you don\'t own a license please purchase one from <a href="https://1.envato.market/EPJRe" target="_blank" rel="noopener noreferrer">ThemeForest.com &#8599;</a></p>This theme is exclusively sold at ThemeForest. Anywhere else providing a download for theme is doing so illegally and most likely includes malicous code.</p><p>A lot of effort, time and money is put into the development and maintance of this product which was created and developed by AJ Clarke from <a href="https://www.wpexplorer.com/" target="_blank" rel="noopener noreferrer">WPExplorer.com &#8599;</a> and he kindly requests that you abide by the product\'s licensing terms.</p>',
				]
			);
		}

		/**
		 * Enqueue styles.
		 */
		public function enqueue_styles() {
			wp_enqueue_style(
				'totaltheme-admin-license-activation',
				totaltheme_get_css_file( 'admin/license-activation' ),
				[],
				WPEX_THEME_VERSION
			);
		}

		/**
		 * Enqueue scripts.
		 */
		public function enqueue_scripts() {
			wp_enqueue_script(
				'totaltheme-admin-license-activation',
				totaltheme_get_js_file( 'admin/license-activation' ),
				[ 'jquery' ],
				WPEX_THEME_VERSION,
				true
			);
		}

		/**
		 * Returns user capability for the admin page.
		 */
		private function get_user_capability() {
			return $this->is_network_admin() ? 'manage_network_options' : 'administrator';
		}

		/**
		 * Settings page output.
		 */
		public function render_admin_page() {
			if ( ! current_user_can( $this->get_user_capability() ) ) {
				return;
			}

			$this->enqueue_scripts();

			$license_status = $this->get_license_status( true );
			$license        = ( 'unregistered' !== $license_status ) ? $this->get_license() : '';
			?>

			<div class="wrap wpex-license-activation">
				<?php
				// Show h1 for network page.
				if ( $this->is_network_admin() || ( is_multisite() && is_main_site() ) ) {
					echo '<h1>' . esc_html__( 'Total Theme Network License', 'total' ) . '</h1>';
				} else {
					// Need to insert h1 for notices.
					echo '<h1 style="display:none;" aria-hidden="true"></h1>';
				} ?>

				<div class="wpex-license-activation__notice notice"></div>

				<div class="wpex-license-activation__card">

					<h2 class="wpex-license-activation__heading"><?php esc_html_e( 'Verify your License', 'total' ); ?><span class="wpex-license-activation__status-badge wpex-license-activation__status-badge--<?php echo esc_attr( $license_status ); ?>"><?php echo esc_html( $this->get_license_status_label( $license_status ) ); ?></span></h2>

					<div class="wpex-license-activation__card-inner">

						<p class="wpex-license-activation__info"><?php echo \sprintf( esc_html__( 'Enter your purchase code below. Learn how to %sfind your purchase code%s.', 'total' ), '<a target="_blank" rel="noopener noreferrer" href="https://totalwptheme.com/docs/how-to-find-your-total-theme-license/">', ' &#8599;</a>' ); ?></p>

						<form method="post" class="wpex-license-activation__form">

							<?php if ( $license ) { ?>
								<input type="text" class="wpex-license-activation__input" name="license" placeholder="<?php echo esc_attr( $license ); ?>" value="<?php echo esc_attr( $license ); ?>" data-license-status="<?php echo esc_attr( $license_status ); ?>" readonly="readonly" autocomplete="off" onclick="select()" required>
							<?php } else { ?>
								<input type="text" class="wpex-license-activation__input" name="license" placeholder="<?php esc_html_e( 'Enter your purchase code here.', 'total' ); ?>" autocomplete="off">
							<?php } ?>

							<?php wp_nonce_field( 'wpex_theme_license_form_nonce', 'wpex_theme_license_form_nonce' ); ?>

							<div class="wpex-license-activation__submit">
								<?php
								$submit_classes = 'wpex-license-activation__button primary button-hero ';
								$submit_classes .= $license ? 'deactivate' : 'activate';
								$activate_txt   = esc_html__( 'Activate your license', 'total' );
								$deactivate_txt = esc_html__( 'Deactivate your license', 'total' );
								submit_button(
									$license ? $deactivate_txt : $activate_txt,
									$submit_classes,
									'submit',
									false,
									[
										'data-activate'   => $activate_txt,
										'data-deactivate' => $deactivate_txt,
									]
								); ?>
								<div class="wpex-license-activation__spinner"><?php echo totaltheme_get_loading_icon( 'wordpress' ); ?></div>
							</div>

						</form>

						<p class="wpex-license-activation__description"><?php echo wp_kses_post( __( 'A purchase code (license) is only valid for <strong>One WordPress Installation</strong> (single or multisite). Are you already using this theme on another installation? Purchase <a target="_blank" rel="noopener noreferrer" href="https://themeforest.net/item/total-responsive-multipurpose-wordpress-theme/6339019?ref=WPExplorer&license=regular&open_purchase_for_item_id=6339019">new license here &#8599;</a> to get your new purchase code. If you are running a multisite network you only need to activate your license on the main site.', 'total' ) ); ?></p>

					</div>

				</div>

				<div class="wpex-license-activation__links">
					<a href="https://my.totalwptheme.com" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Manage your licenses', 'total' ); ?> &#8599;</a>
				</div>

			</div>
		<?php }

		/**
		 * License form ajax.
		 */
		public function license_form_ajax() {
			if ( ! isset( $_POST['nonce'] )
				|| ! isset( $_POST['process'] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'wpex_theme_license_form_nonce' )
			) {
				wp_die();
			}

			$response = [
				'message'       => '',
				'messageClass'  => 'notice-error',
				'success'       => false,
				'response_code' => '',
			];

			if ( empty( $_POST['license'] ) ) {
				$response['message'] = esc_html__( 'Please enter a license.', 'total' );
				$response['messageClass'] = 'notice-warning';
			} else {
				$license = sanitize_key( wp_unslash( $_POST['license'] ) );
				$process = sanitize_key( wp_unslash( $_POST['process'] ) );
				switch ( $process ) {
					case 'deactivate':
						$license_status = sanitize_key( wp_unslash( $_POST['licenseStatus'] ) );
						$response = wp_parse_args( $this->deactivate_license( $license, $license_status ), $response );
						break;
					case 'activate':
						$response = wp_parse_args( $this->activate_license( $license ), $response );
						break;
				}
			}

			wp_send_json( $response );

			wp_die();
		}

		/**
		 * Hooks into the admin_notices action hook.
		 */
		public function _on_admin_notices(): void {
			$screen = get_current_screen();
			if ( isset( $screen->id ) && is_string( $screen->id ) && str_contains( $screen->id, 'wpex' ) ) {
				return;
			}
			if ( $this->is_dev_environment() ) {
				return;
			}
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php esc_html_e( 'Theme License Missing', 'total' ); ?></strong>: <?php echo esc_html_e( 'Please activate your theme license. Each domain where the theme is used requires a valid license.', 'total' ); ?></p>
				<p><strong><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpex-panel-theme-license' ) ); ?>"><?php esc_html_e( 'Activate Your License Now', 'total' ); ?></a></strong></p>
			</div>
			<?php
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
}