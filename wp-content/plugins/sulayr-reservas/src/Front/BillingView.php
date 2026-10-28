<?php

namespace SulayrReservas\Front;

use SulayrReservas\Billing\BillingSettings;
use SulayrReservas\Billing\Money;
use SulayrReservas\Billing\SummaryFormatter;
use SulayrReservas\Billing\SummaryService;
use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Roles\Capabilities;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sección «Facturación» del panel (spec, sección 8). Solo consulta.
 */
class BillingView {

	const VIEW = 'facturacion';

	/** @var SiteConfig */
	private $config;

	/** @var BillingSettings */
	private $settings;

	/** @var SummaryService */
	private $service;

	/**
	 * @param SiteConfig $config Sitios.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config   = $config;
		$this->settings = new BillingSettings();
		$this->service  = new SummaryService( $this->settings );
	}

	/**
	 * @return bool
	 */
	public static function allowed() {
		return current_user_can( Capabilities::VIEW_BILLING );
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	public function render( \DateTimeImmutable $now ) {
		if ( ! self::allowed() ) {
			return;
		}

		$this->service->maybeAutoGenerate( $now );

		$month = isset( $_GET['mes'] ) ? sanitize_text_field( wp_unslash( $_GET['mes'] ) ) : '';
		if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
			$summary = $this->service->summary( $month );
			if ( null !== $summary ) {
				$this->detail( $month, $summary, $now );
				return;
			}
		}

		$this->overview( $now );
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function overview( \DateTimeImmutable $now ) {
		$current = $now->format( 'Y-m' );
		$summary = $this->service->summary( $current );

		echo '<div class="sulayr-card sulayr-billing">';
		echo '<h2>Facturación</h2>';
		$this->missingAmountNotice();
		echo '<h3>Mes en curso: ' . esc_html( SummaryFormatter::month( $current ) ) . '</h3>';
		if ( null === $summary ) {
			echo '<p>Estado: <strong>Sin generar</strong>. El resumen lo genera el administrador desde los ajustes de facturación, o el sistema a partir del día ' . esc_html( (string) SummaryService::AUTO_DAY ) . '.</p>';
		} else {
			if ( SummaryFormatter::isStalePending( $summary, $this->settings->tariff() ) ) {
				echo '<p class="sulayr-notice" role="status">' . esc_html( SummaryFormatter::STALE_PENDING . ' la liquidación se completará cuando el administrador vuelva a generar el mes en curso.' ) . '</p>';
			}
			$this->facts( $summary, SummaryService::STATUS_PROVISIONAL );
			echo '<p class="sulayr-summary-note">Provisional: la cifra puede cambiar si se vuelve a generar antes de que termine el mes.</p>';
			echo '<p>' . $this->detailLink( $current, 'Ver usuarios facturados' ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
		echo '</div>';

		$this->history( $now );
	}

	/**
	 * @param \DateTimeImmutable $now Ahora.
	 * @return void
	 */
	private function history( \DateTimeImmutable $now ) {
		echo '<div class="sulayr-card sulayr-billing">';
		echo '<h2>Histórico</h2>';
		$months = $this->service->history( $now );
		if ( array() === $months ) {
			echo '<p>Todavía no hay meses terminados desde la instalación de la facturación.</p></div>';
			return;
		}

		echo '<div class="sulayr-table-wrap"><table class="sulayr-table">';
		echo '<thead><tr>';
		foreach ( array( 'Mes', 'Estado', 'Usuarios facturables', 'Deportistas Sulayr', 'Con sesiones', 'Coste del sistema', 'Pago por deportistas', 'Importe neto', 'Tarifa aplicada', 'Generado' ) as $label ) {
			echo '<th scope="col">' . esc_html( $label ) . '</th>';
		}
		echo '</tr></thead><tbody>';
		foreach ( $months as $month => $summary ) {
			echo '<tr>';
			if ( null === $summary ) {
				echo '<td>' . esc_html( ucfirst( SummaryFormatter::month( $month ) ) ) . '</td>';
				echo '<td>' . esc_html( SummaryService::statusLabel( SummaryService::STATUS_MISSING ) ) . '</td>';
				echo '<td colspan="8">—</td>';
			} else {
				echo '<td>' . $this->detailLink( $month, ucfirst( SummaryFormatter::month( $month ) ) ) . '</td>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo '<td>' . esc_html( SummaryService::statusLabel( SummaryService::STATUS_FINAL ) ) . '</td>';
				echo '<td>' . esc_html( (string) (int) $summary->billable_users ) . '</td>';
				echo '<td>' . esc_html( (string) (int) $summary->club_athletes ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::sessions( $summary ) ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::cost( $summary ) ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::payment( $summary ) ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::net( $summary ) ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::tariff( SummaryService::tariffOf( $summary ) ) ) . '</td>';
				echo '<td>' . esc_html( SummaryFormatter::generated( $summary ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table></div></div>';
	}

	/**
	 * @param string             $month   `YYYY-MM`.
	 * @param object             $summary Resumen.
	 * @param \DateTimeImmutable $now     Ahora.
	 * @return void
	 */
	private function detail( $month, $summary, \DateTimeImmutable $now ) {
		$status = $this->service->status( $month, $now );
		$users  = $this->service->users( $month );

		echo '<div class="sulayr-card sulayr-billing">';
		echo '<p><a class="sulayr-link" href="' . esc_url( $this->url( array( 'sulayr_vista' => self::VIEW ) ) ) . '">Volver a Facturación</a></p>';
		echo '<h2>' . esc_html( ucfirst( SummaryFormatter::month( $month ) ) ) . '</h2>';
		$this->facts( $summary, $status );

		echo '<h3>Usuarios facturados (' . esc_html( (string) count( $users ) ) . ')</h3>';
		$this->exportForm( $month );
		if ( array() === $users ) {
			echo '<p>No había nadadores activos al generar este resumen.</p>';
		} else {
			echo '<div class="sulayr-table-wrap"><table class="sulayr-table sulayr-table-compact">';
			echo '<thead><tr><th scope="col">Nombre</th><th scope="col">Apellidos</th><th scope="col">Tipo</th></tr></thead><tbody>';
			foreach ( $users as $row ) {
				echo '<tr><td>' . esc_html( (string) $row->first_name ) . '</td><td>' . esc_html( (string) $row->last_name ) . '</td><td>' . esc_html( self::typeName( $row->linked_to_club ) ) . '</td></tr>';
			}
			echo '</tbody></table></div>';
		}
		echo '</div>';
	}

	/**
	 * Datos y cuenta de un resumen.
	 *
	 * @param object $summary Resumen.
	 * @param string $status  Estado del mes.
	 * @return void
	 */
	private function facts( $summary, $status ) {
		$users    = (int) $summary->billable_users;
		$athletes = (int) $summary->club_athletes;
		$tariff   = SummaryService::tariffOf( $summary );

		echo '<dl class="sulayr-profile">';
		$this->pair( 'Estado', SummaryService::statusLabel( $status ) );
		$this->pair( 'Nadadores', (string) $users );
		$this->pair( 'Deportistas CT Sulayr', (string) $athletes );
		$this->pair( 'Nadadores CN Nazarí', (string) max( 0, $users - $athletes ) );
		$this->pair( 'Tarifa aplicada', SummaryFormatter::tariff( $tariff ) );
		echo '</dl>';

		echo '<dl class="sulayr-billing-formula">';
		$this->pair( 'Precio base', Money::format( $tariff->baseFee() ) );
		$this->pair( 'Usuarios adicionales', (string) SummaryFormatter::extraUsers( $summary ) );
		$this->pair( 'Coste total', SummaryFormatter::cost( $summary ) );
		$this->pair( 'Pago de CT Sulayr', SummaryFormatter::payment( $summary ) );
		$this->pair( 'Cálculo final', SummaryFormatter::net( $summary ) );
		echo '</dl>';
	}

	/**
	 * @return void
	 */
	private function missingAmountNotice() {
		if ( null !== $this->settings->tariff()->athleteAmount() ) {
			return;
		}

		echo '<p class="sulayr-notice" role="status">' . esc_html( SummaryFormatter::MISSING_ATHLETE_AMOUNT . '. La liquidación queda pendiente hasta que el administrador lo configure y vuelva a generar el mes en curso.' ) . '</p>';
	}

	/**
	 * @param string $month `YYYY-MM`.
	 * @return void
	 */
	private function exportForm( $month ) {
		echo '<form method="post" action="' . esc_url( $this->url( array( 'sulayr_vista' => self::VIEW ) ) ) . '" class="sulayr-inline">';
		wp_nonce_field( PanelActions::NONCE, 'sulayr_nonce' );
		echo '<input type="hidden" name="sulayr_action" value="billing_export">';
		echo '<input type="hidden" name="mes" value="' . esc_attr( $month ) . '">';
		echo '<input type="hidden" name="sulayr_return" value="' . esc_attr( $this->url( array( 'sulayr_vista' => self::VIEW ) ) ) . '">';
		echo '<button type="submit">Exportar CSV</button></form>';
	}

	/**
	 * @param string $month `YYYY-MM`.
	 * @param string $label Texto.
	 * @return string HTML escapado.
	 */
	private function detailLink( $month, $label ) {
		$href = $this->url(
			array(
				'sulayr_vista' => self::VIEW,
				'mes'          => $month,
			)
		);

		return '<a class="sulayr-link" href="' . esc_url( $href ) . '">' . esc_html( $label ) . '</a>';
	}

	/**
	 * @param string $label Etiqueta.
	 * @param string $value Valor.
	 * @return void
	 */
	private function pair( $label, $value ) {
		echo '<dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd>';
	}

	/**
	 * @param int|string $linked Vinculado al club.
	 * @return string
	 */
	public static function typeName( $linked ) {
		return 1 === (int) $linked ? 'Sulayr' : 'CN Nazarí';
	}

	/**
	 * @param array<string, string> $args Parámetros.
	 * @return string
	 */
	private function url( array $args ) {
		$base = ( new PanelPage( $this->config ) )->url();
		if ( '' === $base ) {
			$base = home_url( '/' );
		}

		return add_query_arg( array_map( 'rawurlencode', $args ), $base );
	}
}
