<?php
namespace ClubTriatlonSulayr\Modules\Ranking\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tablas del ranking interno (SPEC-RANKING-INTERNO §2.3).
 *
 * Se crean con dbDelta en el blog donde está activo el plugin (blog 1), igual
 * que MembersSchema. Subir VERSION cuando cambie una tabla; dbDelta conserva
 * los datos.
 */
class RankingSchema {

    public const VERSION = 1;
    public const VERSION_OPTION = 'sulayr_ranking_db_version';

    public const COMPETITIONS = 'sulayr_ranking_competitions';
    public const REFERENCES = 'sulayr_ranking_references';
    public const RESULTS = 'sulayr_ranking_results';
    public const LOG = 'sulayr_ranking_log';

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
        $competitions = self::table( self::COMPETITIONS );
        $references = self::table( self::REFERENCES );
        $results = self::table( self::RESULTS );
        $log = self::table( self::LOG );

        $sql = [];

        $sql[] = "CREATE TABLE {$competitions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  name varchar(190) NOT NULL,
  race_date date NOT NULL,
  season smallint(4) unsigned NOT NULL,
  location varchar(190) NOT NULL DEFAULT '',
  modality varchar(20) NOT NULL,
  distance varchar(20) NOT NULL,
  format varchar(20) NOT NULL DEFAULT 'individual',
  status varchar(20) NOT NULL DEFAULT 'borrador',
  notes varchar(255) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  KEY season_status (season,status),
  KEY race_date (race_date)
) $charset;";

        $sql[] = "CREATE TABLE {$references} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  competition_id bigint(20) unsigned NOT NULL,
  scope varchar(10) NOT NULL,
  team_category varchar(10) NOT NULL DEFAULT '',
  sex char(1) NOT NULL DEFAULT '',
  age_group varchar(32) NOT NULL DEFAULT '',
  winner_time int(10) unsigned DEFAULT NULL,
  participants int(10) unsigned DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY ref (competition_id,scope,team_category,sex,age_group)
) $charset;";

        $sql[] = "CREATE TABLE {$results} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  competition_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  sex char(1) NOT NULL DEFAULT '',
  age_group varchar(32) NOT NULL DEFAULT '',
  status varchar(10) NOT NULL DEFAULT 'finalizado',
  time_s int(10) unsigned DEFAULT NULL,
  race_category varchar(8) NOT NULL DEFAULT '',
  position_sex int(10) unsigned DEFAULT NULL,
  position_age_group int(10) unsigned DEFAULT NULL,
  team_category varchar(10) NOT NULL DEFAULT '',
  coefficient decimal(4,2) NOT NULL DEFAULT 1.00,
  points_absolute decimal(8,2) NOT NULL DEFAULT 0.00,
  points_age_group decimal(8,2) DEFAULT NULL,
  method_absolute varchar(10) NOT NULL DEFAULT '',
  method_age_group varchar(10) NOT NULL DEFAULT '',
  calc_note varchar(255) NOT NULL DEFAULT '',
  created_by bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  updated_by bigint(20) unsigned NOT NULL DEFAULT 0,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY competition_user (competition_id,user_id),
  KEY user_id (user_id),
  KEY sex_group (sex,age_group)
) $charset;";

        $sql[] = "CREATE TABLE {$log} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  entity varchar(20) NOT NULL,
  entity_id bigint(20) unsigned NOT NULL DEFAULT 0,
  competition_id bigint(20) unsigned NOT NULL DEFAULT 0,
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  field varchar(64) NOT NULL,
  old_value text NOT NULL,
  new_value text NOT NULL,
  note varchar(255) NOT NULL DEFAULT '',
  author_id bigint(20) unsigned NOT NULL DEFAULT 0,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY competition_id (competition_id),
  KEY user_id (user_id),
  KEY created_at (created_at)
) $charset;";

        foreach ( $sql as $statement ) {
            dbDelta( $statement );
        }

        update_option( self::VERSION_OPTION, self::VERSION );
    }
}
