<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Modules\Ranking\Database\RankingSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Registro de cambios del ranking (SPEC-RANKING-INTERNO §2.3 y §4.6).
 *
 * Solo inserta: no hay método para editar ni borrar entradas. No usa
 * MemberLog porque ese registro exige un socio y dispara la comprobación de
 * recibos.
 */
class RankingLog {

    public const ENTITY_COMPETITION = 'competicion';
    public const ENTITY_REFERENCE = 'referencia';
    public const ENTITY_RESULT = 'resultado';
    public const ENTITY_SETTING = 'ajuste';
    public const ENTITY_RECALC = 'recalculo';

    public const PER_PAGE = 50;

    /** Nota que se añade a las entradas mientras dura with_note(). */
    private static string $note = '';

    /**
     * Ejecuta $callback añadiendo $note a todas las entradas que se creen dentro.
     *
     * @param   string   $note     Texto corto.
     * @param   callable $callback Código a ejecutar.
     * @return 	mixed    Lo que devuelva $callback.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function with_note( string $note, callable $callback ) {
        $previous = self::$note;
        self::$note = $note;
        try {
            return $callback();
        } finally {
            self::$note = $previous;
        }
    }

    /**
     * Añade una entrada.
     *
     * @param   string $entity         ENTITY_*.
     * @param   int    $entity_id      Fila afectada (0 en ajustes).
     * @param   int    $competition_id Competición (0 si no aplica).
     * @param   int    $user_id        Socio afectado (0 si no aplica).
     * @param   string $field          Campo, o alta / borrado / publicar / despublicar.
     * @param   mixed  $old            Valor anterior.
     * @param   mixed  $new            Valor nuevo.
     * @param   string $note           Nota opcional.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function record( string $entity, int $entity_id, int $competition_id, int $user_id, string $field, $old, $new, string $note = '' ): bool {
        global $wpdb;

        $note = trim( implode( ' · ', array_unique( array_filter( [ self::$note, $note ] ) ) ) );

        return false !== $wpdb->insert(
            RankingSchema::table( RankingSchema::LOG ),
            [
                'entity' => $entity,
                'entity_id' => $entity_id,
                'competition_id' => $competition_id,
                'user_id' => $user_id,
                'field' => substr( $field, 0, 64 ),
                'old_value' => self::to_text( $old ),
                'new_value' => self::to_text( $new ),
                'note' => substr( $note, 0, 255 ),
                'author_id' => get_current_user_id(),
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s' ]
        );
    }

    /**
     * Entradas filtradas, de la más reciente a la más antigua.
     *
     * @param   array $filters competition_id, user_id, entity.
     * @param   int   $page    Página (desde 1).
     * @return 	array{rows: object[], total: int}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function query( array $filters, int $page = 1 ): array {
        global $wpdb;

        $table = RankingSchema::table( RankingSchema::LOG );
        $where = [ '1=1' ];
        $args = [];
        if ( ! empty( $filters['competition_id'] ) ) {
            $where[] = 'competition_id = %d';
            $args[] = (int) $filters['competition_id'];
        }
        if ( ! empty( $filters['user_id'] ) ) {
            $where[] = 'user_id = %d';
            $args[] = (int) $filters['user_id'];
        }
        if ( ! empty( $filters['entity'] ) ) {
            $where[] = 'entity = %s';
            $args[] = (string) $filters['entity'];
        }
        $where_sql = implode( ' AND ', $where );
        $offset = max( 0, $page - 1 ) * self::PER_PAGE;

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Tabla y condiciones propias; los valores van con prepare().
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
        $total = (int) ( empty( $args ) ? $wpdb->get_var( $count_sql ) : $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) );
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $args, [ self::PER_PAGE, $offset ] ) ) );
        // phpcs:enable

        return [ 'rows' => $rows, 'total' => $total ];
    }

    /**
     * Valor como texto para guardarlo.
     *
     * @param   mixed $value Valor.
     * @return 	string
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function to_text( $value ): string {
        if ( null === $value ) {
            return '';
        }
        if ( is_array( $value ) || is_object( $value ) ) {
            return (string) wp_json_encode( $value, JSON_UNESCAPED_UNICODE );
        }
        if ( is_bool( $value ) ) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
