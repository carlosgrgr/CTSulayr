<?php

namespace SulayrReservas\Billing;

use SulayrReservas\Config\SiteConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tarea diaria que genera el resumen del mes a partir del día 20 si falta (7.2).
 *
 * WP-Cron depende de las visitas; la misma comprobación se hace al abrir la sección y los ajustes.
 */
class BillingCron {

	const HOOK = 'sulayr_billing_daily';

	/** @var SiteConfig */
	private $config;

	/**
	 * @param SiteConfig $config Sitios.
	 */
	public function __construct( SiteConfig $config ) {
		$this->config = $config;
	}

	/**
	 * Solo en el sitio de reservas: así la tarea queda en su propio cron.
	 *
	 * @return void
	 */
	public function register() {
		if ( ! $this->config->isBookingsSite() ) {
			return;
		}

		add_action( self::HOOK, array( $this, 'run' ) );

		if ( false === wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 5 * MINUTE_IN_SECONDS, 'daily', self::HOOK );
		}
	}

	/**
	 * @return void
	 */
	public function run() {
		if ( ! $this->config->isBookingsSite() ) {
			return;
		}

		( new SummaryService() )->maybeAutoGenerate( new \DateTimeImmutable( 'now', wp_timezone() ) );
	}
}
