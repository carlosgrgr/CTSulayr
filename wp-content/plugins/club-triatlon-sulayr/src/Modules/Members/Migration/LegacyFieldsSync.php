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
 *
 * Desde la parte B también hay socios dados de alta con el sistema nuevo,
 * que pueden tener servicios y tipos que los campos antiguos no expresan.
 * Por eso el puente solo actúa sobre lo que cambió en ese guardado: un campo
 * antiguo distinto del que había antes de guardar. Y solo toca las filas del
 * servicio afectado (cuota base, entrenamiento genérico o natación) y el tipo
 * de socio si es «normal» o «junta». Guardar Mi cuenta no cambia nada.
 */
class LegacyFieldsSync {

    /** Fecha de la migración a la que se pasaron los socios (11 de noviembre de 2026 en PROD). */
    public const MIGRATED_OPTION = 'sulayr_members_migrated_at';

    /** Partes del histórico que puede tocar el puente. */
    public const PART_TYPE = 'type';
    public const PART_STATUS = 'status';
    public const PART_TRAINING = 'training';
    public const PART_SWIMMING = 'swimming';

    /** @var array<int, array<string, string>> Campos antiguos antes de guardar, por usuario. */
    private static array $before = [];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        // ACF guarda los valores en la prioridad 10.
        add_action( 'acf/save_post', [ $this, 'remember_before' ], 1 );
        add_action( 'acf/save_post', [ $this, 'on_acf_save' ], 30 );
    }

    /**
     * Valores de los campos antiguos antes de que ACF los guarde.
     *
     * @param   int|string $post_id «user_X».
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function remember_before( $post_id ): void {
        if ( ! is_string( $post_id ) || 0 !== strpos( $post_id, 'user_' ) ) {
            return;
        }

        $user_id = (int) substr( $post_id, 5 );
        self::$before[ $user_id ] = self::legacy_values( $user_id );
    }

    /**
     * Campos antiguos tal como están guardados.
     *
     * @param   int $user_id Socio.
     * @return 	array<string, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function legacy_values( int $user_id ): array {
        $values = [];
        foreach ( [ CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, CustomFields::USER_PROFILE_TRAINING_TYPE, CustomFields::USER_PROFILE_SWIMMING, CustomFields::USER_PROFILE_ATHLETE_STATUS ] as $key ) {
            $values[ $key ] = (string) get_user_meta( $user_id, $key, true );
        }
        $values[ CustomFields::USER_PROFILE_IS_DIRECTIVE ] = (bool) get_user_meta( $user_id, CustomFields::USER_PROFILE_IS_DIRECTIVE, true ) ? '1' : '0';

        return $values;
    }

    /**
     * Partes del histórico afectadas por la diferencia entre dos lecturas de los campos antiguos.
     *
     * @param   array<string, string> $before Antes.
     * @param   array<string, string> $after  Después.
     * @return 	string[] Constantes PART_*.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function changed_parts( array $before, array $after ): array {
        $changed = function ( string $key ) use ( $before, $after ): bool {
            return ( $before[ $key ] ?? '' ) !== ( $after[ $key ] ?? '' );
        };

        $parts = [];
        if ( $changed( CustomFields::USER_PROFILE_IS_DIRECTIVE ) ) {
            $parts[] = self::PART_TYPE;
        }
        if ( LegacyMapping::status( $before[ CustomFields::USER_PROFILE_ATHLETE_STATUS ] ?? '' )['status'] !== LegacyMapping::status( $after[ CustomFields::USER_PROFILE_ATHLETE_STATUS ] ?? '' )['status'] ) {
            $parts[] = self::PART_STATUS;
        }
        if ( $changed( CustomFields::USER_PROFILE_MEMBERSHIP_PLAN ) || $changed( CustomFields::USER_PROFILE_TRAINING_TYPE ) ) {
            $parts[] = self::PART_TRAINING;
        }
        if ( $changed( CustomFields::USER_PROFILE_SWIMMING ) ) {
            $parts[] = self::PART_SWIMMING;
        }

        return $parts;
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

        if ( false === get_option( self::MIGRATED_OPTION ) ) {
            return;
        }

        $user_id = (int) substr( $post_id, 5 );
        $before = self::$before[ $user_id ] ?? null;
        unset( self::$before[ $user_id ] );

        $user = get_userdata( $user_id );
        if ( false === $user || false === in_array( 'athlete', (array) $user->roles, true ) ) {
            return;
        }

        // Sin la lectura previa no se sabe qué cambió: no se toca nada.
        $parts = null !== $before ? self::changed_parts( $before, self::legacy_values( $user_id ) ) : [];

        self::sync_user( $user_id, $parts );
    }

    /**
     * Aplica al histórico lo que digan hoy los campos antiguos.
     *
     * Un socio sin histórico recibe el inicial completo. Con histórico, solo
     * se tocan las partes indicadas.
     *
     * @param   int      $user_id Socio.
     * @param   string[] $parts   Constantes PART_* que cambiaron.
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sync_user( int $user_id, array $parts ): void {
        $services = LegacyMapping::services();
        if ( null === $services ) {
            return;
        }

        $today = current_time( 'Y-m-d' );
        $yesterday = gmdate( 'Y-m-d', strtotime( $today . ' -1 day' ) );

        $status = LegacyMapping::status( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) )['status'];
        $current = StatusPeriods::current( $user_id );

        if ( null === $current && empty( StatusPeriods::for_user( $user_id ) ) ) {
            self::sync_type( $user_id );
            self::initialize( $user_id, $status, $services );
            return;
        }

        if ( in_array( self::PART_TYPE, $parts, true ) ) {
            self::sync_type( $user_id );
        }

        $back_from_left = false;
        if ( in_array( self::PART_STATUS, $parts, true ) && ( null === $current || $current->status !== $status ) ) {
            $back_from_left = null === $current || CustomFields::STATUS_LEFT === $current->status;
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

        // Solo los servicios cuyo campo antiguo cambió; al volver de una baja, los tres.
        $touched = [];
        if ( $back_from_left ) {
            $touched[] = $services['base']->id;
        }
        if ( $back_from_left || in_array( self::PART_TRAINING, $parts, true ) ) {
            $touched[] = $services['training']->id;
        }
        if ( $back_from_left || in_array( self::PART_SWIMMING, $parts, true ) ) {
            $touched[] = $services['swimming']->id;
        }
        if ( empty( $touched ) ) {
            return;
        }

        // Diferencias entre los servicios deseados y los vigentes.
        $desired = [];
        foreach ( LegacyMapping::desired_services( $user_id, $services )['items'] as $item ) {
            if ( in_array( $item['service_id'], $touched, true ) ) {
                $desired[ $item['service_id'] . '|' . $item['option_key'] ] = $item;
            }
        }

        foreach ( MemberServices::open_rows( $user_id ) as $row ) {
            if ( false === in_array( (int) $row->service_id, $touched, true ) ) {
                continue;
            }

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
     * Tipo de socio según la marca de directiva. Un tipo que los campos
     * antiguos no expresan (p. ej. «menor») no se toca.
     *
     * @param   int $user_id Socio.
     * @return 	void
     * @access 	private
     * @package	club-triatlon-sulayr
     */
    private static function sync_type( int $user_id ): void {
        $current = (string) get_user_meta( $user_id, CustomFields::MEMBER_TYPE, true );
        if ( false === in_array( $current, [ '', LegacyMapping::TYPE_NORMAL, LegacyMapping::TYPE_BOARD ], true ) ) {
            return;
        }

        $type = LegacyMapping::member_type( $user_id );
        if ( $current !== $type ) {
            update_user_meta( $user_id, CustomFields::MEMBER_TYPE, $type );
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
