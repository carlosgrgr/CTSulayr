<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Resumen del socio a partir del historial, para las pantallas que antes
 * leían los campos antiguos (tarifa, modalidad, natación): Mi cuenta, la
 * página «Sulayr» de wp-admin y el listado de usuarios.
 */
class MemberSummary {

    /**
     * Resumen de un socio en una fecha.
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Y-m-d ('' = hoy).
     * @return 	array{services: string[], has_training: bool, training_option_key: string, training_option_label: string, swimming: bool, status: string, type: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function for_user( int $user_id, string $date = '' ): array {
        $date = '' !== $date ? $date : current_time( 'Y-m-d' );
        $summary = [
            'services' => [],
            'has_training' => false,
            'training_option_key' => '',
            'training_option_label' => '',
            'swimming' => '1' === (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_SWIMMING, true ),
            'status' => (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ?: CustomFields::STATUS_ACTIVE,
            'type' => (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true ),
        ];

        $items = array_map( function ( $row ) {
            return [ 'service_id' => (int) $row->service_id, 'option_key' => (string) $row->option_key ];
        }, MemberServices::active_on( $user_id, $date ) );

        return self::with_services( $summary, $items );
    }

    /**
     * Resumen previsto de un socio en una fecha futura: el historial en esa
     * fecha más los cambios aprobados que aún no se han aplicado y que
     * empiezan ese día o antes (el historial solo se escribe al aplicarlos).
     * También tiene en cuenta las congelaciones que terminan antes de esa fecha.
     *
     * @param   int    $user_id Socio.
     * @param   string $date    Y-m-d.
     * @return 	array Lo mismo que for_user() más 'previous_services' (string[], hoy) y 'changes' (string[], descripción de los cambios que entran).
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function projected( int $user_id, string $date ): array {
        $today = self::for_user( $user_id );
        $summary = [
            'services' => [],
            'has_training' => false,
            'training_option_key' => '',
            'training_option_label' => '',
            'swimming' => false,
            'status' => $today['status'],
            'type' => $today['type'],
            'previous_services' => $today['services'],
            'changes' => [],
        ];

        $period = StatusPeriods::on( $user_id, $date );
        if ( null !== $period ) {
            $summary['status'] = (string) $period->status;
            // La tarea diaria devuelve a activo al día siguiente del fin previsto.
            if ( CustomFields::STATUS_FROZEN === $summary['status'] && null === $period->end_date && ! empty( $period->planned_end_date ) && (string) $period->planned_end_date < $date ) {
                $summary['status'] = CustomFields::STATUS_ACTIVE;
            }
        }

        $items = array_map( function ( $row ) {
            return [ 'service_id' => (int) $row->service_id, 'option_key' => (string) $row->option_key ];
        }, MemberServices::active_on( $user_id, $date ) );

        foreach ( Lifecycle\MemberChanges::scheduled_for_user( $user_id ) as $change ) {
            if ( (string) $change->effective_date > $date ) {
                continue;
            }
            $summary['changes'][] = Lifecycle\MemberChanges::describe( $change );
            switch ( $change->type ) {
                case Lifecycle\MemberChanges::TYPE_SERVICES:
                    $items = array_map( function ( $item ) {
                        return [ 'service_id' => (int) $item['service_id'], 'option_key' => (string) $item['option_key'] ];
                    }, (array) ( $change->fields['services'] ?? [] ) );
                    break;
                case Lifecycle\MemberChanges::TYPE_MEMBER_TYPE:
                    $summary['type'] = (string) ( $change->fields['member_type'] ?? $summary['type'] );
                    break;
                case Lifecycle\MemberChanges::TYPE_LEAVE:
                    $summary['status'] = CustomFields::STATUS_LEFT;
                    $items = [];
                    break;
                case Lifecycle\MemberChanges::TYPE_FREEZE:
                    // 'return' es el primer día de vuelta.
                    $return = (string) ( $change->fields['return'] ?? '' );
                    if ( CustomFields::STATUS_LEFT !== $summary['status'] && ( '' === $return || $return > $date ) ) {
                        $summary['status'] = CustomFields::STATUS_FROZEN;
                    }
                    break;
            }
        }

        return self::with_services( $summary, $items );
    }

    /**
     * Rellena servicios, entrenamiento y natación a partir de una lista de servicios.
     *
     * @param   array $summary Resumen.
     * @param   array $items   [['service_id' => int, 'option_key' => string], ...].
     * @return 	array
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function with_services( array $summary, array $items ): array {
        $swimming = false;
        foreach ( $items as $item ) {
            $summary['services'][] = MemberServices::label( $item['service_id'], $item['option_key'] );
            $service = ServiceCatalog::get( $item['service_id'] );
            if ( null !== $service && $service->grants_swimming ) {
                $swimming = true;
            }
            if ( null !== $service && $service->coach_list && false === $summary['has_training'] ) {
                $summary['has_training'] = true;
                $summary['training_option_key'] = $item['option_key'];
                $summary['training_option_label'] = '' !== $item['option_key'] ? $service->option_label( $item['option_key'] ) : $service->title;
            }
        }
        // for_user() mantiene la natación de la meta que lee reservas; la previsión la deduce de los servicios.
        if ( isset( $summary['changes'] ) ) {
            $summary['swimming'] = $swimming;
        }

        return $summary;
    }
}
