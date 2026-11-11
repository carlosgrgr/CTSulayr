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

        foreach ( MemberServices::active_on( $user_id, $date ) as $row ) {
            $summary['services'][] = MemberServices::label( (int) $row->service_id, (string) $row->option_key );
            $service = ServiceCatalog::get( (int) $row->service_id );
            if ( null !== $service && $service->coach_list && false === $summary['has_training'] ) {
                $summary['has_training'] = true;
                $summary['training_option_key'] = (string) $row->option_key;
                $summary['training_option_label'] = '' !== (string) $row->option_key ? $service->option_label( (string) $row->option_key ) : $service->title;
            }
        }

        return $summary;
    }
}
