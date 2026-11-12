<?php
namespace ClubTriatlonSulayr\Modules\Members\Join;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Lanza la sincronización de reservas para un socio (D38).
 *
 * La sincronización automática de sulayr-reservas solo corre cuando guarda un
 * administrador. Para que también funcione cuando aprueba un gestor de socios
 * o la tarea diaria, se llama a su servicio directamente si el plugin está
 * activo. Reservas no se modifica: sigue leyendo sulayr_user_swimming y
 * sulayr_user_athlete_status.
 */
class ReservationsSync {

    /**
     * Sincroniza un usuario. No hace nada si reservas no está activo.
     *
     * @param   int $user_id Socio.
     * @return 	bool True si se pudo lanzar y no falló.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sync( int $user_id ): bool {
        $service = '\\SulayrReservas\\Sync\\SyncService';
        $context = '\\SulayrReservas\\Context\\BlogContext';
        $config = '\\SulayrReservas\\Config\\SiteConfig';
        if ( false === class_exists( $service ) || false === class_exists( $context ) || false === class_exists( $config ) ) {
            return false;
        }

        try {
            $result = ( new $service( new $context( new $config() ) ) )->sync( $user_id, 'automatic' );
        } catch ( \Throwable $e ) {
            return false;
        }

        return is_object( $result ) && method_exists( $result, 'result' ) && 'failed' !== $result->result();
    }
}
