<?php
namespace ClubTriatlonSulayr\Modules\Members\Lifecycle;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Join\JoinRequests;
use ClubTriatlonSulayr\Modules\Members\MemberSettings;
use ClubTriatlonSulayr\Modules\Members\ReservationsAccess;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Tarea diaria de socios (spec §7), WP-Cron en el blog del club:
 * 1. aplica los cambios, bajas, congelaciones y cambios de tipo cuya fecha
 *    efectiva ha llegado;
 * 2. devuelve a activo a los congelados cuyo fin previsto pasó;
 * 3. activa las altas pendientes cuya fecha ha llegado (estado, natación y
 *    sincronización con reservas, D38). Los pasos 1 y 2 ya actualizan
 *    reservas de cada socio que tocan; no se recalcula a todos para no cambiar
 *    a nadie cuyas metas no salgan del historial (p. ej. datos migrados);
 * 4. borra los datos de las solicitudes de alta caducadas (D16).
 *
 * Ejecutarla dos veces el mismo día no cambia nada la segunda vez (C6).
 * Recibos y retribución de la junta llegan en la parte D.
 */
class MembersDailyTask {

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
     * @return 	array{applied: int, returned: int, updated: int, purged: int}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function run(): array {
        $applied = MemberChanges::apply_due();
        $returned = MemberChanges::return_from_freezes();

        $updated = 0;
        $pending = get_users( [
            'role' => 'athlete',
            'meta_key' => CustomFields::USER_PROFILE_ATHLETE_STATUS,
            'meta_value' => CustomFields::STATUS_PENDING,
            'fields' => 'ID',
            'number' => -1,
        ] );
        foreach ( $pending as $user_id ) {
            if ( ReservationsAccess::apply( (int) $user_id ) ) {
                $updated++;
            }
        }

        $purged = JoinRequests::purge_expired(
            (int) MemberSettings::get( 'retention_rejected_days' ),
            (int) MemberSettings::get( 'retention_pending_days' )
        );

        $result = [ 'applied' => $applied, 'returned' => $returned, 'updated' => $updated, 'purged' => $purged ];
        update_option( self::LAST_RUN_OPTION, [ 'at' => current_time( 'mysql' ), 'result' => $result ], false );

        return $result;
    }
}
