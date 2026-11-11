<?php
namespace ClubTriatlonSulayr\Modules\Members;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;
use ClubTriatlonSulayr\Modules\Members\Join\ReservationsSync;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Escribe, a partir del historial, las dos metas que lee reservas (D38):
 * sulayr_user_athlete_status y sulayr_user_swimming.
 *
 * - Estado: el del periodo que cubre hoy. Si no hay ninguno pero sí uno
 *   futuro (alta con fecha posterior), «pendiente».
 * - Natación: «1» solo si está activo hoy y tiene vigente algún servicio
 *   que da acceso a reservas.
 *
 * Si cambia algo, lanza la sincronización de reservas.
 */
class ReservationsAccess {

    /**
     * Aplica el historial de hoy a las metas.
     *
     * @param   int  $user_id Socio.
     * @param   bool $sync    Lanzar la sincronización si cambia algo.
     * @return 	bool True si cambió alguna meta.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function apply( int $user_id, bool $sync = true ): bool {
        $today = current_time( 'Y-m-d' );

        $period = StatusPeriods::on( $user_id, $today );
        if ( null !== $period ) {
            $status = (string) $period->status;
        } elseif ( StatusPeriods::has_future( $user_id, $today ) ) {
            $status = CustomFields::STATUS_PENDING;
        } else {
            return false;
        }

        $swimming = '0';
        if ( CustomFields::STATUS_ACTIVE === $status ) {
            foreach ( MemberServices::active_on( $user_id, $today ) as $row ) {
                $service = ServiceCatalog::get( (int) $row->service_id );
                if ( null !== $service && $service->grants_swimming ) {
                    $swimming = '1';
                    break;
                }
            }
        }

        $changed = false;
        foreach ( [ CustomFields::USER_PROFILE_ATHLETE_STATUS => $status, CustomFields::USER_PROFILE_SWIMMING => $swimming ] as $key => $value ) {
            if ( (string) get_user_meta( $user_id, $key, true ) !== $value ) {
                update_user_meta( $user_id, $key, $value );
                $changed = true;
            }
        }

        if ( $changed && $sync ) {
            ReservationsSync::sync( $user_id );
        }

        return $changed;
    }
}
