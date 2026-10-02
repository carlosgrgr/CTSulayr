<?php

namespace SulayrReservas\Sync;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Botón, guardado del perfil y pantalla de registro en el sitio del club.
 */
class ClubSync {

	const ACTION = 'sulayr_sync_swimmer';

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param SiteConfig  $config       Sitios.
	 * @param BlogContext $blog_context Cambio de sitio.
	 */
	public function __construct( SiteConfig $config, BlogContext $blog_context ) {
		$this->config       = $config;
		$this->blog_context = $blog_context;
	}

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'profile_update', array( $this, 'onProfileUpdate' ), 30, 1 );
		add_action( 'set_user_role', array( $this, 'onRoleChange' ), 30, 3 );
		add_action( 'remove_user_from_blog', array( $this, 'onRemoval' ), 30, 2 );
		add_action( 'show_user_profile', array( $this, 'renderButton' ) );
		add_action( 'edit_user_profile', array( $this, 'renderButton' ) );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handleButton' ) );
		add_action( 'admin_menu', array( $this, 'registerScreen' ) );
		add_action( 'admin_notices', array( $this, 'renderNotice' ) );
	}

	/**
	 * @param int $user_id Usuario guardado.
	 * @return void
	 */
	public function onProfileUpdate( $user_id ) {
		if ( ! $this->canRunAutomatic( (int) $user_id ) ) {
			return;
		}

		$result = $this->service()->sync( (int) $user_id, 'automatic' );
		if ( SyncResult::FAILED === $result->result() ) {
			$this->remember( $result );
		}
	}

	/**
	 * Si deja de ser deportista en el club y tiene ficha, se desactiva en reservas.
	 *
	 * @param int      $user_id   Usuario.
	 * @param string   $role      Rol nuevo.
	 * @param string[] $old_roles Roles anteriores.
	 * @return void
	 */
	public function onRoleChange( $user_id, $role = '', $old_roles = array() ) {
		if ( ! $this->config->isClubSite() || 'athlete' === $role || ! in_array( 'athlete', (array) $old_roles, true ) ) {
			return;
		}

		if ( ! $this->hasSwimmer( (int) $user_id ) ) {
			return;
		}

		$this->service()->sync( (int) $user_id, 'automatic', true );
	}

	/**
	 * @param int $user_id Usuario.
	 * @param int $blog_id Sitio del que sale.
	 * @return void
	 */
	public function onRemoval( $user_id, $blog_id ) {
		if ( (int) $blog_id !== $this->config->clubBlogId() || ! $this->hasSwimmer( (int) $user_id ) ) {
			return;
		}

		$this->service()->sync( (int) $user_id, 'automatic', true );
	}

	/**
	 * @param \WP_User $user Perfil que se está editando.
	 * @return void
	 */
	public function renderButton( $user ) {
		if ( ! $user instanceof \WP_User || ! $this->showButton( $user ) ) {
			return;
		}

		echo '<h2>Reservas de natación</h2>';
		echo '<p>Comprueba las condiciones actuales y actualiza la ficha de reservas de este deportista.</p>';
		$url = add_query_arg(
			array(
				'action'  => self::ACTION,
				'user_id' => (int) $user->ID,
			),
			admin_url( 'admin-post.php' )
		);
		$url = wp_nonce_url( $url, self::ACTION . '_' . (int) $user->ID );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">Sincronizar ahora en reservas</a></p>';
	}

	/**
	 * @return void
	 */
	public function handleButton() {
		if ( ! $this->config->isClubSite() || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para sincronizar.' ), '', array( 'response' => 403 ) );
		}

		$user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $user_id );

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User || ! in_array( 'athlete', (array) $user->roles, true ) ) {
			$this->remember( new SyncResult( SyncResult::FAILED, SyncResult::FAILED, 'Solo se puede sincronizar a un deportista del club.' ) );
			$this->redirect( $user_id );
		}

		$this->remember( $this->service()->sync( $user_id, 'manual' ) );
		$this->redirect( $user_id );
	}

	/**
	 * @return void
	 */
	public function registerScreen() {
		if ( ! $this->config->isClubSite() ) {
			return;
		}

		add_users_page(
			'Registro de sincronización',
			'Registro de sincronización',
			'manage_options',
			'sulayr-reservas-sync-log',
			array( $this, 'renderScreen' )
		);
	}

	/**
	 * @return void
	 */
	public function renderScreen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html( 'No tienes permiso para ver este registro.' ), '', array( 'response' => 403 ) );
		}

		$page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$data = ( new SyncLog( $this->blog_context ) )->page( $page, 50 );
		$total = (int) $data['total'];
		$pages = max( 1, (int) ceil( $total / 50 ) );

		echo '<div class="wrap">';
		echo '<h1>Registro de sincronización</h1>';
		echo '<p>Se conservan 180 días y, como máximo, las 1000 operaciones más recientes.</p>';
		echo '<table class="widefat striped"><thead><tr>';
		foreach ( array( 'Fecha', 'Deportista', 'Usuario', 'Origen', 'Resultado', 'Acción', 'Detalle' ) as $heading ) {
			echo '<th>' . esc_html( $heading ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		if ( array() === $data['rows'] ) {
			echo '<tr><td colspan="7">Todavía no hay operaciones.</td></tr>';
		}

		foreach ( $data['rows'] as $row ) {
			echo '<tr>';
			echo '<td>' . esc_html( (string) $row->created_at ) . '</td>';
			echo '<td>' . esc_html( (string) $row->athlete_name ) . '</td>';
			echo '<td>' . esc_html( (string) $row->user_id ) . '</td>';
			echo '<td>' . esc_html( $this->label( (string) $row->origin ) ) . '</td>';
			echo '<td>' . esc_html( $this->label( (string) $row->result ) ) . '</td>';
			echo '<td>' . esc_html( $this->label( (string) $row->action ) ) . '</td>';
			echo '<td>' . esc_html( (string) $row->error_message ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		if ( $pages > 1 ) {
			echo '<p>';
			for ( $index = 1; $index <= $pages; $index++ ) {
				$url = add_query_arg( 'paged', $index );
				echo '<a href="' . esc_url( $url ) . '">' . esc_html( (string) $index ) . '</a> ';
			}
			echo '</p>';
		}
		echo '</div>';
	}

	/**
	 * @return void
	 */
	public function renderNotice() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$notice = get_transient( $this->noticeKey() );
		if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
			return;
		}

		delete_transient( $this->noticeKey() );
		$class = ( isset( $notice['result'] ) && SyncResult::OK === $notice['result'] ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( (string) $notice['message'] ) . '</p></div>';
	}

	/**
	 * @param int $user_id Usuario.
	 * @return bool
	 */
	private function canRunAutomatic( $user_id ) {
		if ( ! $this->config->isClubSite() || ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		$user = get_userdata( $user_id );

		return $user instanceof \WP_User && in_array( 'athlete', (array) $user->roles, true );
	}

	/**
	 * @param \WP_User $user Perfil.
	 * @return bool
	 */
	private function showButton( \WP_User $user ) {
		return $this->config->isClubSite()
			&& current_user_can( 'manage_options' )
			&& in_array( 'athlete', (array) $user->roles, true );
	}

	/**
	 * @param int $user_id Usuario.
	 * @return bool
	 */
	private function hasSwimmer( $user_id ) {
		return (bool) $this->blog_context->onBookings(
			function () use ( $user_id ) {
				return ( new \SulayrReservas\Swimmers\SwimmerRepository() )->find( $user_id );
			}
		);
	}

	/**
	 * @return SyncService
	 */
	private function service() {
		return new SyncService( $this->blog_context );
	}

	/**
	 * @param SyncResult $result Resultado.
	 * @return void
	 */
	private function remember( SyncResult $result ) {
		set_transient(
			$this->noticeKey(),
			array(
				'result'  => $result->result(),
				'message' => $result->message(),
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * @param int $user_id Usuario editado.
	 * @return void
	 */
	private function redirect( $user_id ) {
		$url = $user_id > 0 ? get_edit_user_link( $user_id ) : admin_url( 'users.php' );
		if ( ! is_string( $url ) || '' === $url ) {
			$url = admin_url( 'users.php' );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * @return string
	 */
	private function noticeKey() {
		return 'sulayr_sync_notice_' . get_current_user_id();
	}

	/**
	 * @param string $token Código guardado.
	 * @return string
	 */
	private function label( $token ) {
		$labels = array(
			'automatic'   => 'Al guardar el perfil',
			'manual'      => 'Botón manual',
			'ok'          => 'Correcto',
			'failed'      => 'Fallido',
			'created'     => 'Alta',
			'updated'     => 'Actualización',
			'activated'   => 'Activación',
			'deactivated' => 'Desactivación',
			'unchanged'   => 'Sin cambios',
		);

		return isset( $labels[ $token ] ) ? $labels[ $token ] : $token;
	}
}
