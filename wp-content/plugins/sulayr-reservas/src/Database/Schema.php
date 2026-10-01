<?php

namespace SulayrReservas\Database;

use SulayrReservas\Config\SiteConfig;
use SulayrReservas\Context\BlogContext;
use SulayrReservas\Context\BlogContextException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Crea y actualiza las tablas del plugin con dbDelta.
 *
 * Las tablas de negocio se instalan en el sitio de reservas.
 * El registro técnico se instala en el sitio del club.
 */
class Schema {

	const VERSION        = 1;
	const OPTION_VERSION = 'sulayr_reservas_db_version';

	/** @var SiteConfig */
	private $config;

	/** @var BlogContext */
	private $blog_context;

	/**
	 * @param SiteConfig  $config       Identificadores de los sitios.
	 * @param BlogContext $blog_context Cambio de sitio con restauración.
	 */
	public function __construct( SiteConfig $config, BlogContext $blog_context ) {
		$this->config       = $config;
		$this->blog_context = $blog_context;
	}

	/**
	 * Instala o actualiza el esquema si la versión guardada es anterior.
	 *
	 * @return void
	 */
	public function maybeInstall() {
		if ( ! $this->config->isValid() ) {
			return;
		}

		try {
			$this->blog_context->onBookings( array( $this, 'installBookingsIfNeeded' ) );
			$this->blog_context->onClub( array( $this, 'installClubIfNeeded' ) );
		} catch ( BlogContextException $exception ) {
			return;
		}
	}

	/**
	 * Vuelve a ejecutar dbDelta en ambos sitios sin borrar datos.
	 *
	 * @return void
	 * @throws BlogContextException Si la configuración de sitios no es válida.
	 */
	public function install() {
		$this->blog_context->onBookings( array( $this, 'installBookingsTables' ) );
		$this->blog_context->onClub( array( $this, 'installClubTables' ) );
	}

	/**
	 * @return void
	 */
	public function installBookingsIfNeeded() {
		if ( $this->isCurrent() && $this->tablesExist( array( 'sulayr_swim_sessions', 'sulayr_swim_bookings', 'sulayr_swim_months', 'sulayr_swim_swimmers' ) ) ) {
			return;
		}

		$this->installBookingsTables();
	}

	/**
	 * @return void
	 */
	public function installClubIfNeeded() {
		if ( $this->isCurrent() && $this->tablesExist( array( 'sulayr_swim_sync_log' ) ) ) {
			return;
		}

		$this->installClubTables();
	}

	/**
	 * @return void
	 */
	public function installBookingsTables() {
		$sessions = $this->table( 'sulayr_swim_sessions' );
		$bookings = $this->table( 'sulayr_swim_bookings' );
		$months   = $this->table( 'sulayr_swim_months' );
		$swimmers = $this->table( 'sulayr_swim_swimmers' );

		$sql = "CREATE TABLE {$sessions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_date date NOT NULL,
			start_time time NOT NULL,
			end_time time NOT NULL,
			capacity smallint(5) unsigned NOT NULL DEFAULT 24,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_slot (session_date, start_time)
		) {$this->charset()};
		CREATE TABLE {$bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NULL,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			email varchar(100) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			created_by bigint(20) unsigned NULL,
			is_manual tinyint(1) NOT NULL DEFAULT 0,
			manager_id bigint(20) unsigned NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_session (session_id, user_id),
			KEY session_id (session_id)
		) {$this->charset()};
		CREATE TABLE {$months} (
			generated_month char(7) NOT NULL,
			generated_at datetime NOT NULL,
			generated_by bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (generated_month)
		) {$this->charset()};
		CREATE TABLE {$swimmers} (
			user_id bigint(20) unsigned NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			linked_to_club tinyint(1) NOT NULL DEFAULT 0,
			first_name varchar(100) NOT NULL DEFAULT '',
			last_name varchar(100) NOT NULL DEFAULT '',
			email varchar(100) NOT NULL DEFAULT '',
			phone varchar(50) NOT NULL DEFAULT '',
			dni varchar(20) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (user_id)
		) {$this->charset()};";

		$this->runDelta( $sql );

		if ( $this->tablesExist( array( 'sulayr_swim_sessions', 'sulayr_swim_bookings', 'sulayr_swim_months', 'sulayr_swim_swimmers' ) ) ) {
			update_option( self::OPTION_VERSION, self::VERSION, false );
		}
	}

	/**
	 * @return void
	 */
	public function installClubTables() {
		$log = $this->table( 'sulayr_swim_sync_log' );

		$sql = "CREATE TABLE {$log} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			user_id bigint(20) unsigned NULL,
			athlete_name varchar(191) NOT NULL DEFAULT '',
			origin varchar(20) NOT NULL DEFAULT '',
			result varchar(20) NOT NULL DEFAULT '',
			action varchar(30) NOT NULL DEFAULT '',
			error_message text NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY created_at (created_at)
		) {$this->charset()};";

		$this->runDelta( $sql );

		if ( $this->tablesExist( array( 'sulayr_swim_sync_log' ) ) ) {
			update_option( self::OPTION_VERSION, self::VERSION, false );
		}
	}

	/**
	 * @return bool
	 */
	private function isCurrent() {
		return (int) get_option( self::OPTION_VERSION, 0 ) >= self::VERSION;
	}

	/**
	 * @param string[] $suffixes Nombres de tabla sin prefijo.
	 * @return bool
	 */
	private function tablesExist( array $suffixes ) {
		global $wpdb;

		foreach ( $suffixes as $suffix ) {
			$table = $this->table( $suffix );
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

			if ( $found !== $table ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $name Nombre de tabla sin prefijo.
	 * @return string
	 */
	private function table( $name ) {
		global $wpdb;

		return $wpdb->prefix . $name;
	}

	/**
	 * @return string
	 */
	private function charset() {
		global $wpdb;

		return $wpdb->get_charset_collate();
	}

	/**
	 * @param string $sql Sentencias CREATE TABLE separadas.
	 * @return void
	 */
	private function runDelta( $sql ) {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql );
	}
}
