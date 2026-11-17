<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Calc\RaceTime;
use ClubTriatlonSulayr\Modules\Ranking\Database\RankingSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resultados de los socios (SPEC-RANKING-INTERNO §2.3).
 *
 * Un resultado por socio y competición. Lo que mete el gestor se registra
 * campo a campo; los puntos los escribe RankingService.
 */
class Results {

    /** Campos que mete el gestor. */
    public const FIELDS = [ 'status', 'time_s', 'race_category', 'position_sex', 'position_age_group', 'team_category' ];

    /**
     * Resultados de una competición, del mejor al peor.
     *
     * @param   int $competition_id Competición.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_competition( int $competition_id ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::RESULTS );

        // Primero los que terminan, luego DNF, DSQ, DNS y NC (SPEC §4.2).
        $order = implode( ', ', array_fill( 0, count( Keys::STATUSES ), '%s' ) );

        return self::cast( $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY FIELD( status, {$order} ), team_category, position_sex IS NULL, position_sex, time_s IS NULL, time_s, id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            array_merge( [ $competition_id ], Keys::STATUSES )
        ) ) );
    }

    /**
     * Resultados de una temporada con los datos de su competición.
     *
     * @param   int      $season         Año.
     * @param   bool     $published_only Solo competiciones publicadas.
     * @param   int|null $user_id        Solo los de un socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_season( int $season, bool $published_only = true, ?int $user_id = null ): array {
        global $wpdb;

        $results = RankingSchema::table( RankingSchema::RESULTS );
        $competitions = RankingSchema::table( RankingSchema::COMPETITIONS );
        $sql = "SELECT r.*, c.name AS competition_name, c.race_date, c.season, c.modality, c.distance, c.format, c.status AS competition_status, c.interest_label
            FROM {$results} r INNER JOIN {$competitions} c ON c.id = r.competition_id
            WHERE c.season = %d";
        $args = [ $season ];
        if ( $published_only ) {
            $sql .= ' AND c.status = %s';
            $args[] = Keys::COMPETITION_PUBLISHED;
        }
        if ( null !== $user_id ) {
            $sql .= ' AND r.user_id = %d';
            $args[] = $user_id;
        }

        return self::cast( $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY c.race_date DESC, c.id DESC', $args ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Temporadas en las que un socio tiene resultados publicados.
     *
     * @param   int $user_id Socio.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function seasons_for_user( int $user_id ): array {
        global $wpdb;

        $results = RankingSchema::table( RankingSchema::RESULTS );
        $competitions = RankingSchema::table( RankingSchema::COMPETITIONS );

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT c.season FROM {$results} r INNER JOIN {$competitions} c ON c.id = r.competition_id WHERE r.user_id = %d AND c.status = %s ORDER BY c.season DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $user_id,
            Keys::COMPETITION_PUBLISHED
        ) ) );
    }

    /**
     * Identificadores de las competiciones en las que tiene resultados un socio.
     *
     * @param   int $user_id Socio.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function competitions_of_user( int $user_id ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::RESULTS );

        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT competition_id FROM {$table} WHERE user_id = %d", $user_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Guarda las filas de la tabla de resultados: inserta, cambia y borra
     * comparando por socio, y registra cada campo cambiado.
     *
     * @param   int   $competition_id Competición.
     * @param   array $rows           Filas limpias de ResultValidator (user_id, sex, age_group y FIELDS).
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function save_rows( int $competition_id, array $rows ): bool {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::RESULTS );
        $current = [];
        foreach ( self::for_competition( $competition_id ) as $row ) {
            $current[ (int) $row->user_id ] = $row;
        }

        $now = current_time( 'mysql' );
        $author = get_current_user_id();
        $kept = [];

        foreach ( $rows as $row ) {
            $user_id = (int) $row['user_id'];
            $kept[ $user_id ] = true;
            $values = self::values( $row );
            $existing = $current[ $user_id ] ?? null;

            if ( null === $existing ) {
                if ( false === $wpdb->insert( $table, array_merge( $values, [
                    'competition_id' => $competition_id,
                    'user_id' => $user_id,
                    'sex' => (string) $row['sex'],
                    'age_group' => (string) $row['age_group'],
                    'created_by' => $author,
                    'created_at' => $now,
                ] ) ) ) {
                    return false;
                }
                if ( false === RankingLog::record( RankingLog::ENTITY_RESULT, (int) $wpdb->insert_id, $competition_id, $user_id, 'alta', '', self::text( $values ) ) ) {
                    return false;
                }
                continue;
            }

            $changes = [];
            foreach ( $values as $field => $value ) {
                if ( self::differs( $existing->$field, $value ) ) {
                    $changes[ $field ] = $value;
                    if ( false === RankingLog::record( RankingLog::ENTITY_RESULT, (int) $existing->id, $competition_id, $user_id, $field, self::field_text( $field, $existing->$field ), self::field_text( $field, $value ) ) ) {
                        return false;
                    }
                }
            }
            if ( ! empty( $changes ) ) {
                $changes['updated_by'] = $author;
                $changes['updated_at'] = $now;
                if ( false === $wpdb->update( $table, $changes, [ 'id' => (int) $existing->id ] ) ) {
                    return false;
                }
            }
        }

        foreach ( $current as $user_id => $existing ) {
            if ( isset( $kept[ $user_id ] ) ) {
                continue;
            }
            if ( false === $wpdb->delete( $table, [ 'id' => (int) $existing->id ], [ '%d' ] ) ) {
                return false;
            }
            if ( false === RankingLog::record( RankingLog::ENTITY_RESULT, (int) $existing->id, $competition_id, $user_id, 'borrado', $existing, '' ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Escribe el cálculo de un resultado (sin registro: los puntos se registran en RankingService).
     *
     * @param   int   $id   Resultado.
     * @param   array $data Columnas.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function update_calc( int $id, array $data ): bool {
        global $wpdb;

        return false !== $wpdb->update( RankingSchema::table( RankingSchema::RESULTS ), $data, [ 'id' => $id ] );
    }

    /**
     * Columnas que mete el gestor, con NULL donde no hay dato.
     *
     * @param   array $row Fila limpia.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function values( array $row ): array {
        return [
            'status' => (string) $row['status'],
            'time_s' => null === $row['time_s'] ? null : (int) $row['time_s'],
            'race_category' => (string) $row['race_category'],
            'position_sex' => null === $row['position_sex'] ? null : (int) $row['position_sex'],
            'position_age_group' => null === $row['position_age_group'] ? null : (int) $row['position_age_group'],
            'team_category' => (string) $row['team_category'],
        ];
    }

    /**
     * True si dos valores de columna son distintos (NULL distinto de '').
     *
     * @param   mixed $a Guardado.
     * @param   mixed $b Nuevo.
     * @return 	bool
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function differs( $a, $b ): bool {
        if ( null === $a || null === $b ) {
            return ( null === $a ) !== ( null === $b );
        }

        return (string) $a !== (string) $b;
    }

    /**
     * Un campo como texto para el registro.
     *
     * @param   string $field Campo.
     * @param   mixed  $value Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function field_text( string $field, $value ): string {
        if ( 'time_s' === $field ) {
            return RaceTime::format( null === $value ? null : (int) $value );
        }

        return null === $value ? '' : (string) $value;
    }

    /**
     * Resultado nuevo como texto para el registro.
     *
     * @param   array $values Columnas.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function text( array $values ): string {
        $parts = [ $values['status'] ];
        foreach ( [ 'time_s', 'race_category', 'position_sex', 'position_age_group', 'team_category' ] as $field ) {
            $text = self::field_text( $field, $values[ $field ] );
            if ( '' !== $text ) {
                $parts[] = $text;
            }
        }

        return implode( ' · ', $parts );
    }

    /**
     * Tipos de las columnas numéricas.
     *
     * @param   object[] $rows Filas.
     * @return 	object[]
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function cast( array $rows ): array {
        foreach ( $rows as $row ) {
            foreach ( [ 'time_s', 'position_sex', 'position_age_group' ] as $field ) {
                $row->$field = null === $row->$field ? null : (int) $row->$field;
            }
            $row->points_age_group = null === $row->points_age_group ? null : (float) $row->points_age_group;
            $row->points_absolute = (float) $row->points_absolute;
            $row->interest_bonus = (int) ( $row->interest_bonus ?? 0 );
        }

        return $rows;
    }
}
