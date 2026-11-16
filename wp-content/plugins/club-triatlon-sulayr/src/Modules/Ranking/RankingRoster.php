<?php
namespace ClubTriatlonSulayr\Modules\Ranking;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\Ranking\Calc\AgeGroups;
use ClubTriatlonSulayr\Services\HelperService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Socios del ranking (SPEC-RANKING-INTERNO R4, §6.3).
 *
 * Salen en el ranking los deportistas (rol athlete) activos o congelados.
 * Los de baja conservan sus resultados, pero no se muestran (R34). La lista
 * se lee una vez por petición.
 */
class RankingRoster {

    /** Estados que salen en el ranking. */
    public const VISIBLE_STATUSES = [ CustomFields::STATUS_ACTIVE, CustomFields::STATUS_FROZEN ];

    /** @var array<int, array>|null */
    private static ?array $members = null;

    /** @var array<int, array> Datos de cualquier usuario ya leído. */
    private static array $info = [];

    /**
     * Socios que salen en el ranking, por user_id.
     *
     * @return 	array<int, array{user_id: int, name: string, sort: string, sex: string, birth: string, status: string, number: string}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function members(): array {
        if ( null !== self::$members ) {
            return self::$members;
        }

        $ids = get_users( [
            'role' => 'athlete',
            'fields' => 'ID',
            'number' => -1,
            'meta_query' => [
                'relation' => 'OR',
                [ 'key' => CustomFields::USER_PROFILE_ATHLETE_STATUS, 'value' => self::VISIBLE_STATUSES, 'compare' => 'IN' ],
                [ 'key' => CustomFields::USER_PROFILE_ATHLETE_STATUS, 'compare' => 'NOT EXISTS' ],
            ],
        ] );

        self::$members = [];
        foreach ( $ids as $id ) {
            $info = self::info( (int) $id );
            if ( in_array( $info['status'], self::VISIBLE_STATUSES, true ) ) {
                self::$members[ (int) $id ] = $info;
            }
        }
        uasort( self::$members, function ( $a, $b ) {
            return strcmp( $a['sort'], $b['sort'] );
        } );

        return self::$members;
    }

    /**
     * True si el socio sale en el ranking.
     *
     * @param   int $user_id Usuario.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function is_visible( int $user_id ): bool {
        return isset( self::members()[ $user_id ] );
    }

    /**
     * Socios que se pueden elegir en un resultado: salen en el ranking y tienen sexo.
     *
     * @param   AgeGroups $groups Grupos de la temporada.
     * @param   int       $season Año.
     * @return 	array<int, array{sex: string, group: string}>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function selectable( AgeGroups $groups, int $season ): array {
        $out = [];
        foreach ( self::members() as $id => $member ) {
            if ( '' !== $member['sex'] ) {
                $out[ $id ] = [ 'sex' => $member['sex'], 'group' => $groups->for_birth( $member['birth'], $season ) ];
            }
        }

        return $out;
    }

    /**
     * Datos de un usuario, salga o no en el ranking.
     *
     * @param   int $user_id Usuario.
     * @return 	array{user_id: int, name: string, sort: string, sex: string, birth: string, status: string, number: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function info( int $user_id ): array {
        if ( isset( self::$info[ $user_id ] ) ) {
            return self::$info[ $user_id ];
        }

        $first = (string) get_user_meta( $user_id, 'first_name', true );
        $last = (string) get_user_meta( $user_id, 'last_name', true );
        $name = HelperService::get_user_full_name( $user_id );
        $sex = (string) get_user_meta( $user_id, CustomFields::MEMBER_SEX, true );

        self::$info[ $user_id ] = [
            'user_id' => $user_id,
            'name' => '' !== $name ? $name : sprintf( __( 'Usuario %d', SULAYR_TEXTDOMAIN ), $user_id ),
            'sort' => strtolower( remove_accents( trim( $last . ' ' . $first ) ?: $name ) ),
            'sex' => in_array( $sex, [ CustomFields::SEX_MALE, CustomFields::SEX_FEMALE ], true ) ? $sex : '',
            'birth' => MemberDates::acf_to_iso( (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_BIRTH, true ) ),
            'status' => (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_ATHLETE_STATUS, true ) ?: CustomFields::STATUS_ACTIVE,
            'number' => (string) get_user_meta( $user_id, CustomFields::MEMBER_NUMBER, true ),
        ];

        return self::$info[ $user_id ];
    }

    /**
     * Textos para ordenar empates, ya normalizados.
     *
     * @return 	array<int, string>
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function sort_names(): array {
        return array_map( function ( $member ) {
            return $member['sort'];
        }, self::members() );
    }

    /**
     * Olvida lo leído (tras cambiar el sexo, el estado o la fecha de nacimiento).
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function flush(): void {
        self::$members = null;
        self::$info = [];
    }

    /**
     * URL de la foto del socio, o '' si no tiene.
     *
     * @param   int $user_id Usuario.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function photo_url( int $user_id ): string {
        $photo = (int) get_user_meta( $user_id, CustomFields::USER_PROFILE_PHOTO, true );
        if ( $photo <= 0 ) {
            return '';
        }

        $url = wp_get_attachment_image_url( $photo, 'thumbnail' );

        return false !== $url ? (string) $url : '';
    }

    /**
     * Iniciales del nombre.
     *
     * @param   string $name Nombre completo.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function initials( string $name ): string {
        $parts = preg_split( '/\s+/u', trim( $name ) );
        $initials = '';
        foreach ( array_slice( (array) $parts, 0, 2 ) as $part ) {
            $initials .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
        }

        return $initials;
    }
}
