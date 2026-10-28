<?php

namespace SulayrReservas\Admin;

use SulayrReservas\Billing\BillingSettings;
use SulayrReservas\Billing\Money;
use SulayrReservas\Billing\SummaryFormatter;
use SulayrReservas\Billing\SummaryService;
use SulayrReservas\Billing\Tariff;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ajustes de facturación en el wp-admin del sitio de reservas (spec 4.3 y 7.1).
 *
 * Solo para quien tiene `sulayr_manage_billing`: administrador del sitio y superadministrador.
 */
class BillingSettingsPage {

	const PAGE_SLUG       = 'sulayr-facturacion';
	const ACTION_SAVE     = 'sulayr_billing_save';
	const ACTION_GENERATE = 'sulayr_billing_generate';

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Sitios.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * @return void
	 */
	public function register() {
		if ( ! $this->config->isBookingsSite() ) {
			return;
		}

		add_action( 'admin_menu', array( $this, 'addMenu' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'save' ) );
		add_action( 'admin_post_' . self::ACTION_GENERATE, array( $this, 'generate' ) );
	}

	/**
	 * @return void
	 */
	public function addMenu() {
		add_menu_page(
			'Facturación de reservas',
			'Facturación',
			Capabilities::MANAGE_BILLING,
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-money-alt',
			58
		);
	}

	/**
	 * @return void
	 */
	public function render() {
		if ( ! current_user_can( Capabilities::MANAGE_BILLING ) ) {
			wp_die( esc_html( 'No tienes permiso para ver esta pantalla.' ) );
		}

		$now      = new \DateTimeImmutable( 'now', wp_timezone() );
		$settings = new BillingSettings();
		$service  = new SummaryService( $settings );
		$service->maybeAutoGenerate( $now );

		$month   = $now->format( 'Y-m' );
		$summary = $service->summary( $month );
		$tariff  = $settings->tariff();
		$notice  = $this->takeNotice();
		$input   = isset( $notice['input'] ) && is_array( $notice['input'] ) ? $notice['input'] : $this->inputOf( $tariff );
		?>
		<div class="wrap">
			<h1>Facturación de reservas</h1>
			<?php $this->renderNotice( $notice ); ?>
			<?php if ( null === $tariff->athleteAmount() ) : ?>
				<div class="notice notice-warning"><p><?php echo esc_html( SummaryFormatter::MISSING_ATHLETE_AMOUNT . '. El resumen se genera, pero la liquidación queda pendiente.' ); ?></p></div>
			<?php endif; ?>

			<h2>Mes en curso: <?php echo esc_html( SummaryFormatter::month( $month ) ); ?></h2>
			<?php if ( null === $summary ) : ?>
				<p>Estado: <strong>Sin generar</strong>.</p>
			<?php else : ?>
				<?php if ( SummaryFormatter::isStalePending( $summary, $tariff ) ) : ?>
					<div class="notice notice-warning inline"><p><?php echo esc_html( SummaryFormatter::STALE_PENDING . ' pulsa «Generar resumen del mes en curso» para completar la liquidación.' ); ?></p></div>
				<?php endif; ?>
				<?php $this->renderSummary( $summary ); ?>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_GENERATE ); ?>">
				<?php wp_nonce_field( self::ACTION_GENERATE ); ?>
				<?php submit_button( 'Generar resumen del mes en curso', 'primary', 'submit', false ); ?>
				<p class="description">Usa los nadadores activos y la tarifa de este momento. Sustituye al resumen anterior de este mes. Los meses terminados no se pueden regenerar.</p>
			</form>

			<h2>Valores económicos</h2>
			<p>Un cambio se aplica en cuanto se guarda y afecta a la próxima generación del mes en curso. No cambia los meses terminados. No se aplica IVA.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>">
				<?php wp_nonce_field( self::ACTION_SAVE ); ?>
				<table class="form-table" role="presentation">
					<?php
					$this->field( Tariff::BASE_FEE, $input, '€ al mes.' );
					$this->field( Tariff::INCLUDED_USERS, $input, 'Número entero.' );
					$this->field( Tariff::EXTRA_PRICE, $input, '€ por usuario y mes.' );
					$this->field( Tariff::MAX_FEE, $input, '€ al mes. No puede ser menor que la cuota base.' );
					$this->field( Tariff::ATHLETE_AMOUNT, $input, '€ que paga el Sulayr a CN Nazarí por cada deportista vinculado al club. Vacío: liquidación pendiente.' );
					?>
				</table>
				<?php submit_button( 'Guardar valores' ); ?>
			</form>

			<h2>Registro de cambios</h2>
			<?php $this->renderChanges( $settings->changes() ); ?>
		</div>
		<?php
	}

	/**
	 * @return void
	 */
	public function save() {
		if ( ! current_user_can( Capabilities::MANAGE_BILLING ) ) {
			wp_die( esc_html( 'No tienes permiso para modificar estos valores.' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_SAVE );

		$input = array();
		foreach ( array_keys( Tariff::labels() ) as $field ) {
			$input[ $field ] = isset( $_POST[ $field ] ) ? sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) : '';
		}

		$settings = new BillingSettings();
		$result   = $settings->validate( $input );
		if ( null === $result['tariff'] ) {
			$this->back(
				array(
					'type'     => 'error',
					'messages' => $result['errors'],
					'input'    => $input,
				)
			);
		}

		$changed = $settings->save( $result['tariff'], wp_get_current_user() );
		$this->back(
			array(
				'type'     => 'success',
				'messages' => array( 0 === $changed ? 'No había cambios que guardar.' : 'Valores guardados. Vuelve a generar el mes en curso para aplicarlos a su resumen.' ),
			)
		);
	}

	/**
	 * @return void
	 */
	public function generate() {
		if ( ! current_user_can( Capabilities::MANAGE_BILLING ) ) {
			wp_die( esc_html( 'No tienes permiso para generar el resumen.' ), '', array( 'response' => 403 ) );
		}

		check_admin_referer( self::ACTION_GENERATE );

		$done = ( new SummaryService() )->generate( new \DateTimeImmutable( 'now', wp_timezone() ), wp_get_current_user() );
		$this->back(
			array(
				'type'     => $done ? 'success' : 'error',
				'messages' => array( $done ? 'Resumen del mes en curso generado.' : 'No se ha podido generar el resumen. Vuelve a intentarlo.' ),
			)
		);
	}

	/**
	 * @param object $summary Resumen.
	 * @return void
	 */
	private function renderSummary( $summary ) {
		?>
		<table class="widefat striped" style="max-width:720px">
			<tbody>
				<tr><th scope="row">Estado</th><td><strong>Provisional</strong></td></tr>
				<tr><th scope="row">Generado</th><td><?php echo esc_html( SummaryFormatter::generated( $summary ) ); ?></td></tr>
				<tr><th scope="row">Usuarios facturables</th><td><?php echo esc_html( (string) (int) $summary->billable_users ); ?></td></tr>
				<tr><th scope="row">Deportistas Sulayr</th><td><?php echo esc_html( (string) (int) $summary->club_athletes ); ?></td></tr>
				<tr><th scope="row">Mes con sesiones</th><td><?php echo esc_html( SummaryFormatter::sessions( $summary ) ); ?></td></tr>
				<tr><th scope="row">Coste del sistema</th><td><?php echo esc_html( SummaryFormatter::cost( $summary ) ); ?></td></tr>
				<tr><th scope="row">Pago por deportistas</th><td><?php echo esc_html( SummaryFormatter::payment( $summary ) ); ?></td></tr>
				<tr><th scope="row">Importe neto a transferir</th><td><strong><?php echo esc_html( SummaryFormatter::net( $summary ) ); ?></strong></td></tr>
				<tr><th scope="row">Tarifa aplicada</th><td><?php echo esc_html( SummaryFormatter::tariff( SummaryService::tariffOf( $summary ) ) ); ?></td></tr>
				<tr><th scope="row">Cálculo</th><td><?php echo implode( '<br>', array_map( 'esc_html', SummaryFormatter::formula( $summary ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
			</tbody>
		</table>
		<p class="description">La cifra puede cambiar si se vuelve a generar antes de que termine el mes.</p>
		<?php
	}

	/**
	 * @param object[] $changes Registro.
	 * @return void
	 */
	private function renderChanges( array $changes ) {
		if ( array() === $changes ) {
			echo '<p>Sin cambios registrados.</p>';
			return;
		}

		echo '<table class="widefat striped" style="max-width:960px"><thead><tr>';
		echo '<th scope="col">Fecha y hora</th><th scope="col">Usuario</th><th scope="col">Valor</th><th scope="col">Anterior</th><th scope="col">Nuevo</th>';
		echo '</tr></thead><tbody>';
		foreach ( $changes as $change ) {
			$row = SummaryFormatter::change( $change );
			echo '<tr><td>' . esc_html( $row['when'] ) . '</td><td>' . esc_html( $row['who'] ) . '</td><td>' . esc_html( $row['field'] ) . '</td><td>' . esc_html( $row['old'] ) . '</td><td>' . esc_html( $row['new'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	/**
	 * @param string               $field       Clave.
	 * @param array<string, mixed> $input       Valores a mostrar.
	 * @param string               $description Ayuda.
	 * @return void
	 */
	private function field( $field, array $input, $description ) {
		$labels = Tariff::labels();
		$id     = 'sulayr-billing-' . str_replace( '_', '-', $field );
		$value  = isset( $input[ $field ] ) ? (string) $input[ $field ] : '';
		$mode   = Tariff::isMoney( $field ) ? 'decimal' : 'numeric';
		echo '<tr><th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $labels[ $field ] ) . '</label></th><td>';
		echo '<input type="text" inputmode="' . esc_attr( $mode ) . '" class="small-text" style="width:8em" name="' . esc_attr( $field ) . '" id="' . esc_attr( $id ) . '" value="' . esc_attr( $value ) . '">';
		echo '<p class="description">' . esc_html( $description ) . '</p></td></tr>';
	}

	/**
	 * @param Tariff $tariff Tarifa actual.
	 * @return array<string, string>
	 */
	private function inputOf( Tariff $tariff ) {
		$input = array();
		foreach ( $tariff->toArray() as $field => $value ) {
			if ( null === $value ) {
				$input[ $field ] = '';
			} elseif ( Tariff::isMoney( $field ) ) {
				$input[ $field ] = Money::plain( $value );
			} else {
				$input[ $field ] = (string) $value;
			}
		}

		return $input;
	}

	/**
	 * @param array<string, mixed> $notice Aviso para la próxima carga.
	 * @return void
	 */
	private function back( array $notice ) {
		set_transient( $this->noticeKey(), $notice, MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function takeNotice() {
		$notice = get_transient( $this->noticeKey() );
		delete_transient( $this->noticeKey() );

		return is_array( $notice ) ? $notice : array();
	}

	/**
	 * @param array<string, mixed> $notice Aviso.
	 * @return void
	 */
	private function renderNotice( array $notice ) {
		if ( empty( $notice['messages'] ) || ! is_array( $notice['messages'] ) ) {
			return;
		}

		$class = 'success' === ( isset( $notice['type'] ) ? $notice['type'] : '' ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $class ) . '">';
		foreach ( $notice['messages'] as $message ) {
			echo '<p>' . esc_html( (string) $message ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * @return string
	 */
	private function noticeKey() {
		return 'sulayr_billing_notice_' . get_current_user_id();
	}
}
