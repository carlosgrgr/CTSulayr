<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberFields;
use ClubTriatlonSulayr\Modules\Members\MemberServices;
use ClubTriatlonSulayr\Modules\Members\StatusPeriods;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Transitorio hasta la parte C de la spec de altas.
 *
 * Mientras los cambios se sigan haciendo en los campos antiguos de la ficha
 * de usuario (tarifa, modalidad, natación, directiva, estado), este puente
 * los traslada al histórico nuevo con fecha de hoy, como un cambio inmediato
 * hecho por el gestor. Solo actúa después de la migración.
 *
 * Un deportista sin histórico (p. ej. creado a mano antes de la parte B)
 * recibe su histórico inicial desde la fecha de alta.
 */
class LegacyFieldsSync {

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        add_action( 'acf/save_post', [ $this, 'on_acf_save' ], 30 );
    }

    /**
     * acf/save_post para usuarios.
     *
     * @param   int|string $post_id «user_X».
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function on_acf_save( $post_id ): void {
        if ( ! is_string( $post_id ) || 0 !== strpos( $post_id, 'user_' ) ) {
            return;
        }

        if ( false === get_option( LegacyMigrator::MIGRATED_OPTION ) ) {
            return;
        }

        $user_id = (int) substr( $post_id, 5 );
        $user = get_userdata( $user_id );
        if ( false === $user || false === in_array( 'athlete', (array) $user->roles, true ) ) {
            return;
        }

        self::sync_user( $user_id );
    }

    /**
     * Aplica al histórico lo que digan hoy los campos antiguos.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sync_user( int $user_id ): void {
        $services = LegacyMapping::services();
        if ( null === $services ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );

        // Tipo de socio según la marca de directiva.
        $type = LegacyMapping::member_type( $user_id );
        if ( (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true ) !== $type ) {
            update_user_meta( $user_id, CustomFields::MEMBER_TYPE, $type );
        }

        $status = LegacyMapping::status( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) )['status'];
        $current = StatusPeriods::current( $user_id );

        if ( null === $current && empty( StatusPeriods::for_user( $user_id ) ) ) {
            self::initialize( $user_id, $status, $services );
            return;
        }

        if ( null === $current || $current->status !== $status ) {
            if ( null !== $current ) {
                StatusPeriods::close( (int) $current->id, null !== $current->start_date && $current->start_date > $yesterday ? (string) $current->start_date : $yesterday );
            }
            StatusPeriods::open( $user_id, $status, $today, __( 'Cambio en la ficha de usuario', SULAYR_TEXTDOMAIN ) );

            if ( CustomFields::STATUS_LEFT === $status ) {
                update_user_meta( $user_id, CustomFields::MEMBER_LEAVE_DATE, $yesterday );
                foreach ( MemberServices::open_rows( $user_id ) as $row ) {
                    MemberServices::close( (int) $row->id, $row->start_date > $yesterday ? (string) $row->start_date : $yesterday );
                }
                return;
            }
        }

        if ( CustomFields::STATUS_LEFT === $status ) {
            return;
        }

        // Diferencias entre los servicios deseados y los vigentes.
        $desired = [];
        foreach ( LegacyMapping::desired_services( $user_id, $services )['items'] as $item ) {
            $desired[ $item['service_id'] . '|' . $item['option_key'] ] = $item;
        }

        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            $key = $row->service_id . '|' . $row->option_key;
            if ( isset( $desired[ $key ] ) ) {
                unset( $desired[ $key ] );
                continue;
            }

            // Una fila que empieza hoy o después se cierra en su propio inicio.
            MemberServices::close( (int) $row->id, $row->start_date > $yesterday ? (string) $row->start_date : $yesterday );
        }

        foreach ( $desired as $item ) {
            MemberServices::open( $user_id, $item['service_id'], $item['option_key'], $today, MemberServices::ORIGIN_MANAGER );
        }
    }

    /**
     * Histórico inicial de un deportista que no lo tiene.
     *
     * @param   int    $user_id  Socio.
     * @param   string $status   Estado.
     * @param   array  $services Catálogo inicial.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function initialize( int $user_id, string $status, array $services ): void {
        $join = LegacyMapping::acf_date_to_iso( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_JOIN_DATE, true ) );
        if ( '' === $join ) {
            $join = current_time( 'Y-m-d' );
            update_user_meta( $user_id, CustomFields::USER_PROFILE_JOIN_DATE, LegacyMapping::iso_to_acf_date( $join ) );
        }

        if ( '' === (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true ) ) {
            update_user_meta( $user_id, CustomFields::MEMBER_NUMBER, MemberFields::next_number() );
        }

        StatusPeriods::open( $user_id, $status, $join, __( 'Alta en la ficha de usuario', SULAYR_TEXTDOMAIN ) );

        if ( CustomFields::STATUS_LEFT === $status ) {
            return;
        }

        foreach ( LegacyMapping::desired_services( $user_id, $services )['items'] as $item ) {
            MemberServices::open( $user_id, $item['service_id'], $item['option_key'], $join, MemberServices::ORIGIN_MANAGER );
        }
    }
}
