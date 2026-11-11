<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Modules\Members\Database\MembersSchema;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Pasos manuales por socio (D9, D12): dar de alta en SportEasy, WhatsApp...
 * Los textos salen de Socios → Ajustes y de cada servicio del catálogo.
 */
class MemberTasks {

    public const CONTEXT_JOIN = 'alta';
    public const CONTEXT_CHANGE = 'cambio';
    public const CONTEXT_LEAVE = 'baja';

    /**
     * Crea un paso pendiente.
     *
     * @param   int    $user_id Socio.
     * @param   string $label   Texto.
     * @param   string $context Una de las constantes CONTEXT_*.
     * @return 	int
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function create( int $user_id, string $label, string $context ): int {
        global $wpdb;

        $wpdb->insert(
            MembersSchema::table( MembersSchema::TASKS ),
            [
                'user_id' => $user_id,
                'label' => substr( $label, 0, 255 ),
                'context' => $context,
                'created_at' => current_time( 'mysql' ),
            ],
            [ '%d', '%s', '%s', '%s' ]
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Pasos de un socio: primero los pendientes.
     *
     * @param   int $user_id Socio.
     * @return 	object[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id ): array {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::TASKS );

        return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d ORDER BY ( done_at IS NULL ) DESC, id ASC", $user_id ) );
    }

    /**
     * Marca un paso como hecho, con fecha y autor. No hace nada si ya lo estaba.
     *
     * @param   int $task_id Paso.
     * @return 	object|null El paso, o null si no existe.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function mark_done( int $task_id ): ?object {
        global $wpdb;

        $table = MembersSchema::table( MembersSchema::TASKS );
        $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $task_id ) );
        if ( null === $task || null !== $task->done_at ) {
            return $task;
        }

        $wpdb->update( $table, [ 'done_at' => current_time( 'mysql' ), 'done_by' => get_current_user_id() ], [ 'id' => $task_id ], [ '%s', '%d' ], [ '%d' ] );
        MemberLog::record( (int) $task->user_id, MemberLog::AREA_DATA, __( 'Paso', SULAYR_TEXTDOMAIN ), (string) $task->label, __( 'Hecho', SULAYR_TEXTDOMAIN ) );

        return $task;
    }

    /**
     * Pasos de alta de un socio: los generales y los de sus servicios, sin repetir.
     *
     * @param   int[] $service_ids Servicios contratados.
     * @return 	string[]
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function join_labels( array $service_ids ): array {
        $labels = MemberSettings::labels( 'steps_join' );
        foreach ( $service_ids as $service_id ) {
            $service = Catalog\ServiceCatalog::get( (int) $service_id );
            if ( null !== $service ) {
                $labels = array_merge( $labels, $service->steps );
            }
        }

        return array_values( array_unique( $labels ) );
    }
}
