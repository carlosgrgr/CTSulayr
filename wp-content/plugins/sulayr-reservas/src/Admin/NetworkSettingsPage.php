<?php

namespace SulayrReservas\Admin;

use SulayrReservas\Config\SiteConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pantalla de red para consultar y corregir los identificadores de los sitios.
 */
class NetworkSettingsPage {

	const PAGE_SLUG = 'sulayr-reservas';

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Configuración de los identificadores.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'network_admin_menu', array( $this, 'addMenu' ) );
		add_action( 'network_admin_edit_' . self::PAGE_SLUG, array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'renderInvalidNotice' ) );
		add_action( 'network_admin_notices', array( $this, 'renderInvalidNotice' ) );
	}

	/**
	 * @return void
	 */
	public function addMenu() {
		add_submenu_page(
			'settings.php',
			'Reservas de natación',
			'Reservas de natación',
			'manage_network_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para ver esta pantalla.' ) );
		}

		$errors = $this->config->errors();
		?>
		<div class="wrap">
			<h1>Reservas de natación</h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p>Identificadores guardados.</p></div>
			<?php endif; ?>
			<?php if ( isset( $_GET['error'] ) ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( sanitize_text_field( wp_unslash( $_GET['error'] ) ) ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $errors as $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endforeach; ?>
			<form method="post" action="<?php echo esc_url( network_admin_url( 'edit.php?action=' . self::PAGE_SLUG ) ); ?>">
				<?php wp_nonce_field( self::PAGE_SLUG ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="sulayr-reservas-club">Sitio del club</label></th>
						<td>
							<input name="club_blog_id" id="sulayr-reservas-club" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $this->config->clubBlogId() ); ?>" />
							<p class="description">Blog ID del sitio donde se gestionan los deportistas. En esta instalación es 1.</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="sulayr-reservas-bookings">Sitio de reservas</label></th>
						<td>
							<input name="bookings_blog_id" id="sulayr-reservas-bookings" type="number" min="1" step="1" value="<?php echo esc_attr( (string) $this->config->bookingsBlogId() ); ?>" />
							<p class="description">Blog ID de CN Nazarí. En esta instalación es 2.</p>
						</td>
					</tr>
				</table>
				<?php submit_button( 'Guardar identificadores' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para modificar esta configuración.' ) );
		}

		check_admin_referer( self::PAGE_SLUG );

		$club     = isset( $_POST['club_blog_id'] ) ? absint( wp_unslash( $_POST['club_blog_id'] ) ) : 0;
		$bookings = isset( $_POST['bookings_blog_id'] ) ? absint( wp_unslash( $_POST['bookings_blog_id'] ) ) : 0;

		$previous_club     = get_site_option( SiteConfig::OPTION_CLUB_BLOG_ID, SiteConfig::DEFAULT_CLUB_BLOG_ID );
		$previous_bookings = get_site_option( SiteConfig::OPTION_BOOKINGS_BLOG_ID, SiteConfig::DEFAULT_BOOKINGS_BLOG_ID );

		update_site_option( SiteConfig::OPTION_CLUB_BLOG_ID, $club );
		update_site_option( SiteConfig::OPTION_BOOKINGS_BLOG_ID, $bookings );

		$errors = $this->config->errors();
		if ( array() !== $errors ) {
			update_site_option( SiteConfig::OPTION_CLUB_BLOG_ID, $previous_club );
			update_site_option( SiteConfig::OPTION_BOOKINGS_BLOG_ID, $previous_bookings );

			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => self::PAGE_SLUG,
						'error' => implode( ' ', $errors ),
					),
					network_admin_url( 'settings.php' )
				)
			);
			exit;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'    => self::PAGE_SLUG,
					'updated' => '1',
				),
				network_admin_url( 'settings.php' )
			)
		);
		exit;
	}

	/**
	 * @return void
	 */
	public function renderInvalidNotice() {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		if ( $this->config->isValid() ) {
			return;
		}

		if ( isset( $_GET['page'] ) && self::PAGE_SLUG === $_GET['page'] ) {
			return;
		}

		echo '<div class="notice notice-error"><p>Reservas de natación: la configuración de los sitios no es válida. Revísala en la administración de la red.</p></div>';
	}
}
