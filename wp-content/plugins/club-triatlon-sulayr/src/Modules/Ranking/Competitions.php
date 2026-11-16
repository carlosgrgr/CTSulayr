<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Modules\Ranking\Calc\Keys;
use ClubTriatlonSulayr\Modules\Ranking\Database\RankingSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Competiciones y sus referencias (SPEC-RANKING-INTERNO §2.3).
 *
 * Cada cambio queda en RankingLog, campo a campo.
 */
class Competitions {

    /** Campos editables de una competición. */
    public const FIELDS = [ 'name', 'race_date', 'location', 'modality', 'distance', 'format', 'notes' ];

    /**
     * Una competición, o null.
     *
     * @param   int $id Competición.
     * @return 	object|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function get( int $id ): ?object {
        global $wpdb;

        if ( $id <= 0 ) {
            return null;
        }
        $table = RankingSchema::table( RankingSchema::COMPETITIONS );

        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) ) ?: null; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    /**
     * Competiciones de una temporada, de la más reciente a la más antigua, con su número de resultados.
     *
     * @param   int         $season Año.
     * @param   string|null $status Solo las de ese estado.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_season( int $season, ?string $status = null ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::COMPETITIONS );
        $results = RankingSchema::table( RankingSchema::RESULTS );
        $sql = "SELECT c.*, (SELECT COUNT(*) FROM {$results} r WHERE r.competition_id = c.id) AS result_count FROM {$table} c WHERE c.season = %d";
        $args = [ $season ];
        if ( null !== $status ) {
            $sql .= ' AND c.status = %s';
            $args[] = $status;
        }

        return $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY c.race_date DESC, c.id DESC', $args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    }

    /**
     * Temporadas con competiciones, de la más reciente a la más antigua.
     *
     * @param   bool $published_only Solo las que tienen alguna publicada.
     * @return 	int[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function seasons( bool $published_only = false ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::COMPETITIONS );
        if ( $published_only ) {
            $years = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT season FROM {$table} WHERE status = %s ORDER BY season DESC", Keys::COMPETITION_PUBLISHED ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        } else {
            $years = $wpdb->get_col( "SELECT DISTINCT season FROM {$table} ORDER BY season DESC" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        }

        return array_map( 'intval', $years );
    }

    /**
     * Crea una competición en borrador.
     *
     * @param   array $data Campos limpios.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function insert( array $data ): int {
        global $wpdb;

        $row = self::row( $data );
        $row['status'] = Keys::COMPETITION_DRAFT;
        $row['created_by'] = get_current_user_id();
        $row['created_at'] = current_time( 'mysql' );
        if ( false === $wpdb->insert( RankingSchema::table( RankingSchema::COMPETITIONS ), $row ) ) {
            return 0;
        }
        $id = (int) $wpdb->insert_id;

        if ( false === RankingLog::record( RankingLog::ENTITY_COMPETITION, $id, $id, 0, 'alta', '', $row['name'] ) ) {
            return 0;
        }

        return $id;
    }

    /**
     * Actualiza los datos de una competición y registra cada campo cambiado.
     *
     * @param   int   $id   Competición.
     * @param   array $data Campos limpios.
     * @return 	bool  True si guardó (o no había nada que cambiar); false si falló la base de datos.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function update( int $id, array $data ): bool {
        global $wpdb;

        $current = self::get( $id );
        if ( null === $current ) {
            return false;
        }

        $row = self::row( $data );
        $changes = [];
        foreach ( $row as $field => $value ) {
            if ( (string) $current->$field !== (string) $value ) {
                $changes[ $field ] = $value;
                if ( false === RankingLog::record( RankingLog::ENTITY_COMPETITION, $id, $id, 0, $field, $current->$field, $value ) ) {
                    return false;
                }
            }
        }
        if ( empty( $changes ) ) {
            return true;
        }

        $changes['updated_by'] = get_current_user_id();
        $changes['updated_at'] = current_time( 'mysql' );

        return false !== $wpdb->update( RankingSchema::table( RankingSchema::COMPETITIONS ), $changes, [ 'id' => $id ] );
    }

    /**
     * Cambia el estado (borrador / publicada).
     *
     * @param   int    $id     Competición.
     * @param   string $status Keys::COMPETITION_*.
     * @return 	bool   True si está en ese estado al terminar; false si no existe o falla la base de datos.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function set_status( int $id, string $status ): bool {
        global $wpdb;

        $current = self::get( $id );
        if ( null === $current ) {
            return false;
        }
        if ( $current->status === $status ) {
            return true;
        }

        if ( false === $wpdb->update(
            RankingSchema::table( RankingSchema::COMPETITIONS ),
            [ 'status' => $status, 'updated_by' => get_current_user_id(), 'updated_at' => current_time( 'mysql' ) ],
            [ 'id' => $id ]
        ) ) {
            return false;
        }

        return RankingLog::record( RankingLog::ENTITY_COMPETITION, $id, $id, 0, Keys::COMPETITION_PUBLISHED === $status ? 'publicar' : 'despublicar', $current->status, $status );
    }

    /**
     * Borra una competición con sus referencias y resultados. Queda entera en el registro.
     *
     * @param   int $id Competición.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function delete( int $id ): bool {
        global $wpdb;

        $current = self::get( $id );
        if ( null === $current ) {
            return false;
        }

        $snapshot = [
            'competicion' => $current,
            'referencias' => array_values( self::references( $id ) ),
            'resultados' => Results::for_competition( $id ),
        ];
        RankingLog::record( RankingLog::ENTITY_COMPETITION, $id, $id, 0, 'borrado', $snapshot, '' );

        $wpdb->delete( RankingSchema::table( RankingSchema::RESULTS ), [ 'competition_id' => $id ], [ '%d' ] );
        $wpdb->delete( RankingSchema::table( RankingSchema::REFERENCES ), [ 'competition_id' => $id ], [ '%d' ] );
        $wpdb->delete( RankingSchema::table( RankingSchema::COMPETITIONS ), [ 'id' => $id ], [ '%d' ] );

        return true;
    }

    /**
     * Referencias de una competición por clave (Keys::ref_key).
     *
     * @param   int $id Competición.
     * @return 	array<string, object>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function references( int $id ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::REFERENCES );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE competition_id = %d ORDER BY scope, team_category, sex, age_group", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $out = [];
        foreach ( $rows as $row ) {
            $row->winner_time = null === $row->winner_time ? null : (int) $row->winner_time;
            $row->participants = null === $row->participants ? null : (int) $row->participants;
            $out[ Keys::ref_key( $row->scope, $row->team_category, $row->sex, $row->age_group ) ] = $row;
        }

        return $out;
    }

    /**
     * Guarda las referencias: inserta, cambia o borra (las que llegan vacías) y registra cada cambio.
     *
     * @param   int   $id         Competición.
     * @param   array $references Clave => [ scope, team_category, sex, age_group, winner_time, participants ].
     * @return 	bool  False en cuanto falla una escritura; el llamador debe deshacer la transacción.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function save_references( int $id, array $references ): bool {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::REFERENCES );
        $current = self::references( $id );

        foreach ( $references as $key => $ref ) {
            $existing = $current[ $key ] ?? null;
            $empty = null === $ref['winner_time'] && null === $ref['participants'];

            if ( null === $existing ) {
                if ( $empty ) {
                    continue;
                }
                if ( false === $wpdb->insert( $table, [
                    'competition_id' => $id,
                    'scope' => $ref['scope'],
                    'team_category' => $ref['team_category'],
                    'sex' => $ref['sex'],
                    'age_group' => $ref['age_group'],
                    'winner_time' => $ref['winner_time'],
                    'participants' => $ref['participants'],
                ] ) ) {
                    return false;
                }
                if ( false === RankingLog::record( RankingLog::ENTITY_REFERENCE, (int) $wpdb->insert_id, $id, 0, $key, '', self::ref_text( $ref ) ) ) {
                    return false;
                }
                continue;
            }

            if ( $empty ) {
                if ( false === $wpdb->delete( $table, [ 'id' => (int) $existing->id ], [ '%d' ] ) ) {
                    return false;
                }
                if ( false === RankingLog::record( RankingLog::ENTITY_REFERENCE, (int) $existing->id, $id, 0, $key, self::ref_text( (array) $existing ), '' ) ) {
                    return false;
                }
                continue;
            }

            if ( $existing->winner_time !== $ref['winner_time'] || $existing->participants !== $ref['participants'] ) {
                if ( false === $wpdb->update( $table, [ 'winner_time' => $ref['winner_time'], 'participants' => $ref['participants'] ], [ 'id' => (int) $existing->id ] ) ) {
                    return false;
                }
                if ( false === RankingLog::record( RankingLog::ENTITY_REFERENCE, (int) $existing->id, $id, 0, $key, self::ref_text( (array) $existing ), self::ref_text( $ref ) ) ) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Referencia como texto para el registro: tiempo · finalizados.
     *
     * @param   array $ref Referencia.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function ref_text( array $ref ): string {
        return trim( Calc\RaceTime::format( null === $ref['winner_time'] ? null : (int) $ref['winner_time'] ) . ' · ' . ( null === $ref['participants'] ? '' : (int) $ref['participants'] ), ' ·' );
    }

    /**
     * Columnas de la tabla a partir de los campos limpios.
     *
     * @param   array $data Campos.
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function row( array $data ): array {
        $row = [];
        foreach ( self::FIELDS as $field ) {
            $row[ $field ] = (string) ( $data[ $field ] ?? '' );
        }
        $row['season'] = (int) substr( $row['race_date'], 0, 4 );

        return $row;
    }
}
