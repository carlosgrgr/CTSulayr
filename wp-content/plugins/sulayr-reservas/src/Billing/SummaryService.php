<?php

namespace SulayrReservas\Billing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resumen mensual de facturación (spec, sección 7).
 *
 * Debe usarse con el blog de reservas como sitio actual.
 */
class SummaryService {

	/** Día del mes a partir del cual se genera solo. Fijo (7.2). */
	const AUTO_DAY = 20;

	const STATUS_PROVISIONAL = 'provisional';
	const STATUS_FINAL       = 'definitivo';
	const STATUS_MISSING     = 'sin_generar';
	const STATUS_NO_DATA     = 'sin_datos';

	/** @var BillingSettings */
	private $settings;

	/**
	 * @param BillingSettings|null $settings Ajustes.
	 */
	public function __construct( $settings = null ) {
		$this->settings = $settings instanceof BillingSettings ? $settings : new BillingSettings();
	}

	/**
	 * Genera el resumen del mes en curso y sustituye al anterior del mismo mes.
	 *
	 * @param \DateTimeImmutable $now  Ahora, en la zona del sitio.
	 * @param \WP_User|null      $user Quien lo genera, o null para «sistema».
	 * @return bool True si se guardó.
	 */
	public function generate( \DateTimeImmutable $now, $user = null ) {
		return $this->run( $now, $user, false );
	}

	/**
	 * Si es día 20 o posterior y el mes en curso no tiene resumen, lo genera como «sistema».
	 *
	 * @param \DateTimeImmutable $now Ahora, en la zona del sitio.
	 * @return bool True si lo generó en esta llamada.
	 */
	public function maybeAutoGenerate( \DateTimeImmutable $now ) {
		if ( (int) $now->format( 'j' ) < self::AUTO_DAY ) {
			return false;
		}

		$month = $now->format( 'Y-m' );
		$start = $this->settings->startMonth();
		if ( '' === $start || $month < $start || null !== $this->summary( $month ) ) {
			return false;
		}

		return $this->run( $now, null, true );
	}

	/**
	 * @param string $month `YYYY-MM`.
	 * @return object|null
	 */
	public function summary( $month ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sulayr_billing_summaries';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE billing_month = %s", $month ) );

		return is_object( $row ) ? $row : null;
	}

	/**
	 * Usuarios facturados de un mes, ordenados por apellidos.
	 *
	 * @param string $month `YYYY-MM`.
	 * @return object[]
	 */
	public function users( $month ) {
		global $wpdb;

		$table = $wpdb->prefix . 'sulayr_billing_users';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT user_id, first_name, last_name, linked_to_club FROM {$table} WHERE billing_month = %s ORDER BY last_name ASC, first_name ASC, user_id ASC", $month ) );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Meses terminados desde el de instalación, del más reciente al más antiguo,
	 * con su resumen si lo tienen.
	 *
	 * @param \DateTimeImmutable $now Ahora.
	 * @return array<string, object|null> Mes `YYYY-MM` => resumen o null.
	 */
	public function history( \DateTimeImmutable $now ) {
		$start = $this->settings->startMonth();
		if ( '' === $start ) {
			return array();
		}

		global $wpdb;

		$table   = $wpdb->prefix . 'sulayr_billing_summaries';
		$current = $now->format( 'Y-m' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE billing_month >= %s AND billing_month < %s", $start, $current ) );
		$by   = array();
		foreach ( is_array( $rows ) ? $rows : array() as $row ) {
			$by[ (string) $row->billing_month ] = $row;
		}

		$months = array();
		$cursor = \DateTimeImmutable::createFromFormat( '!Y-m-d', $current . '-01', wp_timezone() );
		if ( ! $cursor instanceof \DateTimeImmutable ) {
			return array();
		}

		for ( $i = 0; $i < 600; $i++ ) {
			$cursor = $cursor->modify( '-1 month' );
			$month  = $cursor->format( 'Y-m' );
			if ( $month < $start ) {
				break;
			}
			$months[ $month ] = isset( $by[ $month ] ) ? $by[ $month ] : null;
		}

		return $months;
	}

	/**
	 * Estado de un mes (7.4).
	 *
	 * @param string             $month `YYYY-MM`.
	 * @param \DateTimeImmutable $now   Ahora.
	 * @return string Una de las constantes STATUS_*.
	 */
	public function status( $month, \DateTimeImmutable $now ) {
		$start   = $this->settings->startMonth();
		$current = $now->format( 'Y-m' );

		if ( '' === $start || $month < $start || $month > $current ) {
			return self::STATUS_NO_DATA;
		}

		if ( null === $this->summary( $month ) ) {
			return self::STATUS_MISSING;
		}

		return $month === $current ? self::STATUS_PROVISIONAL : self::STATUS_FINAL;
	}

	/**
	 * @param string $status Constante STATUS_*.
	 * @return string
	 */
	public static function statusLabel( $status ) {
		$labels = array(
			self::STATUS_PROVISIONAL => 'Provisional',
			self::STATUS_FINAL       => 'Definitivo',
			self::STATUS_MISSING     => 'Sin generar',
			self::STATUS_NO_DATA     => 'Sin datos',
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $status;
	}

	/**
	 * Tarifa con la que se calculó un resumen.
	 *
	 * @param object $summary Fila del resumen.
	 * @return Tariff
	 */
	public static function tariffOf( $summary ) {
		return new Tariff(
			(int) $summary->base_fee_cents,
			(int) $summary->included_users,
			(int) $summary->extra_price_cents,
			(int) $summary->max_fee_cents,
			null === $summary->athlete_amount_cents ? null : (int) $summary->athlete_amount_cents
		);
	}

	/**
	 * @param \DateTimeImmutable $now       Ahora.
	 * @param \WP_User|null      $user      Quien genera, o null para «sistema».
	 * @param bool               $only_new  Solo si el mes aún no tiene resumen (generación automática).
	 * @return bool
	 */
	private function run( \DateTimeImmutable $now, $user, $only_new ) {
		global $wpdb;

		$month = $now->format( 'Y-m' );
		$start = $this->settings->startMonth();
		if ( '' === $start || $month < $start ) {
			return false;
		}

		// Un cerrojo por mes y sitio: dos generaciones a la vez quedan en serie y gana la última.
		$lock = 'sulayr_billing_' . $wpdb->prefix . $month;
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 10)', $lock ) ) ) {
			return false;
		}

		try {
			if ( $only_new && null !== $this->summary( $month ) ) {
				return false;
			}

			return $this->store( $now, $user );
		} finally {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/**
	 * Lee la foto de nadadores activos y guarda el resumen y su lista en una transacción.
	 *
	 * @param \DateTimeImmutable $now  Ahora.
	 * @param \WP_User|null      $user Quien genera.
	 * @return bool
	 */
	private function store( \DateTimeImmutable $now, $user ) {
		global $wpdb;

		$month     = $now->format( 'Y-m' );
		$summaries = $wpdb->prefix . 'sulayr_billing_summaries';
		$billed    = $wpdb->prefix . 'sulayr_billing_users';
		$swimmers  = $wpdb->prefix . 'sulayr_swim_swimmers';
		$sessions  = $wpdb->prefix . 'sulayr_swim_sessions';
		$first_day = $now->modify( 'first day of this month' )->format( 'Y-m-d' );
		$last_day  = $now->modify( 'last day of this month' )->format( 'Y-m-d' );

		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return false;
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$active = $wpdb->get_results( "SELECT user_id, first_name, last_name, linked_to_club FROM {$swimmers} WHERE active = 1" );
		$failed = ! is_array( $active ) || '' !== $wpdb->last_error;
		$found  = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM {$sessions} WHERE enabled = 1 AND session_date BETWEEN %s AND %s LIMIT 1", $first_day, $last_day ) );
		$failed = $failed || '' !== $wpdb->last_error;
		// phpcs:enable

		if ( $failed ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		$users        = count( $active );
		$athletes     = 0;
		foreach ( $active as $row ) {
			if ( 1 === (int) $row->linked_to_club ) {
				++$athletes;
			}
		}
		$has_sessions = null !== $found;
		$tariff       = $this->settings->tariff();
		$cost         = Calculator::cost( $tariff, $users, $has_sessions );
		$payment      = Calculator::athletesPayment( $tariff, $athletes );
		$net          = Calculator::net( $payment, $cost );

		$by_id   = $user instanceof \WP_User ? (int) $user->ID : 0;
		$by_name = 'sistema';
		if ( $user instanceof \WP_User ) {
			$by_name = '' !== $user->display_name ? $user->display_name : $user->user_login;
		}

		$ok = false !== $wpdb->delete( $billed, array( 'billing_month' => $month ), array( '%s' ) )
			&& false !== $wpdb->delete( $summaries, array( 'billing_month' => $month ), array( '%s' ) );

		if ( $ok ) {
			$ok = false !== $wpdb->insert(
				$summaries,
				array(
					'billing_month'          => $month,
					'billable_users'         => $users,
					'club_athletes'          => $athletes,
					'has_sessions'           => $has_sessions ? 1 : 0,
					'base_fee_cents'         => $tariff->baseFee(),
					'included_users'         => $tariff->includedUsers(),
					'extra_price_cents'      => $tariff->extraPrice(),
					'max_fee_cents'          => $tariff->maxFee(),
					'athlete_amount_cents'   => $tariff->athleteAmount(),
					'cost_cents'             => $cost,
					'athletes_payment_cents' => $payment,
					'net_cents'              => $net,
					'generated_by'           => $by_id,
					'generated_by_name'      => mb_substr( $by_name, 0, 191 ),
					'generated_at'           => $now->format( 'Y-m-d H:i:s' ),
				)
			);
		}

		foreach ( $active as $row ) {
			if ( ! $ok ) {
				break;
			}
			$ok = false !== $wpdb->insert(
				$billed,
				array(
					'billing_month'  => $month,
					'user_id'        => (int) $row->user_id,
					'first_name'     => (string) $row->first_name,
					'last_name'      => (string) $row->last_name,
					'linked_to_club' => 1 === (int) $row->linked_to_club ? 1 : 0,
				),
				array( '%s', '%d', '%s', '%s', '%d' )
			);
		}

		if ( ! $ok || false === $wpdb->query( 'COMMIT' ) ) {
			$wpdb->query( 'ROLLBACK' );
			return false;
		}

		return true;
	}
}
