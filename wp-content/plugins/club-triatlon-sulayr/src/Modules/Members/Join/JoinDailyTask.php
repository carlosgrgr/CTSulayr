<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\ReservationsAccess;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tarea diaria mínima de la parte B (WP-Cron, blog del club):
 * - activa las altas aprobadas cuya fecha ha llegado (pendiente → activo,
 *   natación y sincronización con reservas);
 * - borra los datos personales de las solicitudes caducadas (D16).
 *
 * La parte C la amplía con cambios, congelaciones y bajas (§7). Ejecutarla
 * dos veces el mismo día no cambia nada la segunda vez.
 */
class JoinDailyTask {

    public const HOOK = 'sulayr_members_daily';
    public const LAST_RUN_OPTION = 'sulayr_members_daily_last_run';

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'init', [ $this, 'schedule' ], 30 );
        add_action( self::HOOK, [ self::class, 'run' ] );
    }

    /**
     * Programa la tarea a las 00:15 (hora del club) si no lo está.
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function schedule(): void {
        if ( false !== wp_next_scheduled( self::HOOK ) ) {
            return;
        }

        $next = new \DateTimeImmutable( 'tomorrow 00:15', wp_timezone() );
        wp_schedule_event( $next->getTimestamp(), 'daily', self::HOOK );
    }

    /**
     * Ejecuta la tarea.
     *
     * @return 	array{activated: int, purged: int}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function run(): array {
        $activated = 0;

        $pending = get_users( [
            'role' => 'athlete',
            'meta_key' => CustomFields::USER_PROFILE_ATHLETE_STATUS,
            'meta_value' => CustomFields::STATUS_PENDING,
            'fields' => 'ID',
            'number' => -1,
        ] );
        foreach ( $pending as $user_id ) {
            if ( ReservationsAccess::apply( (int) $user_id ) ) {
                $activated++;
            }
        }

        $purged = JoinRequests::purge_expired(
            (int) MemberSettings::get( 'retention_rejected_days' ),
            (int) MemberSettings::get( 'retention_pending_days' )
        );

        update_option( self::LAST_RUN_OPTION, current_time( 'mysql' ), false );

        return [ 'activated' => $activated, 'purged' => $purged ];
    }
}
