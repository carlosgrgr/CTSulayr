<?php
namespace ClubTriatlonSulayr\Modules\Members\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tablas de socios, solicitudes, recibos y registro de cambios.
 *
 * Se crean con dbDelta en el blog donde está activo el plugin (blog 1).
 * Subir VERSION cuando cambie una tabla; dbDelta conserva los datos.
 *
 * Versión 2 (parte B): updated_at en solicitudes (para caducar las pendientes
 * sin tocar) y created_by en consentimientos (gestor que los registró).
 * Versión 3 (parte C): applied_at en solicitudes (cambios, bajas y
 * congelaciones aprobadas que ya se aplicaron al historial).
 */
class MembersSchema {

    public const VERSION = 3;
    public const VERSION_OPTION = 'sulayr_members_db_version';

    public const SERVICES = 'sulayr_member_services';
    public const STATUS_PERIODS = 'sulayr_member_status_periods';
    public const REQUESTS = 'sulayr_member_requests';
    public const SUBSIDIES = 'sulayr_member_subsidies';
    public const CONSENTS = 'sulayr_member_consents';
    public const TASKS = 'sulayr_member_tasks';
    public const WELCOME_DELIVERIES = 'sulayr_member_welcome_deliveries';
    public const RECEIPTS = 'sulayr_receipts';
    public const RECEIPT_LINES = 'sulayr_receipt_lines';
    public const RECEIPT_PAYMENTS = 'sulayr_receipt_payments';
    public const LOG = 'sulayr_member_log';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'init', [ $this, 'maybe_install' ], 5 );
    }

    /**
     * Nombre completo de una tabla con el prefijo del blog actual.
     *
     * @param   string $table Una de las constantes de esta clase.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function table( string $table ): string {
        global $wpdb;
        return $wpdb->prefix . $table;
    }

    /**
     * Instala o actualiza las tablas si cambió la versión.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function maybe_install(): void {
        if ( self::VERSION === (int) get_option( self::VERSION_OPTION ) ) {
            return;
        }

        self::install();
    }

    /**
     * Crea o actualiza todas las tablas.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function install(): void {
        global $wpdb;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $n = [];
        foreach ( [ 'SERVICES', 'STATUS_PERIODS', 'REQUESTS', 'SUBSIDIES', 'CONSENTS', 'TASKS', 'WELCOME_DELIVERIES', 'RECEIPTS', 'RECEIPT_LINES', 'RECEIPT_PAYMENTS', 'LOG' ] as $const ) {
            $n[ $const ] = self::table( constant( self::class . '::' . $const ) );
        }

        $sql = [];

        $sql[] = "CREATE TABLE {$n['SERVICES']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  service_id bigint(20) unsigned NOT NULL,
  option_key varchar(64) NOT NULL DEFAULT '',
  start_date date NOT NULL,
  end_date date DEFAULT NULL,
  origin varchar(20) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY service_id (service_id),
  KEY dates (start_date,end_date)
) $charset;";

        $sql[] = "CREATE TABLE {$n['STATUS_PERIODS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  status varchar(20) NOT NULL,
  start_date date DEFAULT NULL,
  end_date date DEFAULT NULL,
  planned_end_date date DEFAULT NULL,
  reason varchar(255) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY status (status)
) $charset;";

        $sql[] = "CREATE TABLE {$n['REQUESTS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  type varchar(20) NOT NULL,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pendiente',
  data longtext NOT NULL,
  effective_date date DEFAULT NULL,
  resolved_by bigint(20) unsigned NOT NULL DEFAULT 0,
  resolved_at datetime DEFAULT NULL,
  reason varchar(255) NOT NULL DEFAULT '',
  ip varchar(45) NOT NULL DEFAULT '',
  created_at datetime NOT NULL,
  updated_at datetime DEFAULT NULL,
  applied_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY type_status (type,status),
  KEY effective_date (effective_date),
  KEY ip_created (ip,created_at),
  KEY user_id (user_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['SUBSIDIES']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  mode varchar(10) NOT NULL,
  value decimal(10,2) NOT NULL,
  service_ids varchar(255) NOT NULL DEFAULT '',
  start_date date NOT NULL,
  end_date date DEFAULT NULL,
  reason varchar(255) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['CONSENTS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  request_id bigint(20) unsigned NOT NULL DEFAULT 0,
  type varchar(32) NOT NULL,
  version varchar(32) NOT NULL,
  accepted tinyint(1) NOT NULL,
  given_by varchar(10) NOT NULL DEFAULT 'socio',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY request_id (request_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['TASKS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  label varchar(255) NOT NULL,
  context varchar(20) NOT NULL DEFAULT 'alta',
  done_at datetime DEFAULT NULL,
  done_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY done_at (done_at)
) $charset;";

        $sql[] = "CREATE TABLE {$n['WELCOME_DELIVERIES']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  item varchar(100) NOT NULL,
  delivered_on date NOT NULL,
  pack_id varchar(32) NOT NULL DEFAULT '',
  delivered_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['RECEIPTS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  number varchar(20) NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  period char(7) NOT NULL,
  total decimal(10,2) NOT NULL DEFAULT 0,
  status varchar(20) NOT NULL DEFAULT 'pendiente',
  outdated tinyint(1) NOT NULL DEFAULT 0,
  generated_by bigint(20) unsigned NOT NULL DEFAULT 0,
  generated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY number (number),
  UNIQUE KEY user_period (user_id,period),
  KEY period (period)
) $charset;";

        $sql[] = "CREATE TABLE {$n['RECEIPT_LINES']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  receipt_id bigint(20) unsigned NOT NULL,
  type varchar(20) NOT NULL,
  concept varchar(255) NOT NULL,
  amount decimal(10,2) NOT NULL,
  service_id bigint(20) unsigned NOT NULL DEFAULT 0,
  cancelled tinyint(1) NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY receipt_id (receipt_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['RECEIPT_PAYMENTS']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  receipt_id bigint(20) unsigned NOT NULL,
  amount decimal(10,2) NOT NULL,
  paid_on date NOT NULL,
  method varchar(20) NOT NULL,
  wallet_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY receipt_id (receipt_id)
) $charset;";

        $sql[] = "CREATE TABLE {$n['LOG']} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  area varchar(20) NOT NULL,
  field varchar(64) NOT NULL,
  old_value text NOT NULL,
  new_value text NOT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY created_at (created_at)
) $charset;";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        $previous = (int) get_option( self::VERSION_OPTION );
        update_option( self::VERSION_OPTION, self::VERSION );

        // La versión 1 quita el endpoint «mis-pagos»: WooCommerceIntegration regenera los enlaces en init.
        if ( $previous < 1 ) {
            update_option( 'sulayr_wc_flush_rewrite_rules', '1' );
        }
    }
}
