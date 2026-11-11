<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\Catalog\Service;
use ClubTriatlonSulayr\Modules\Members\Catalog\ServiceCatalog;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Equivalencia entre los campos antiguos (tarifa, modalidad, natación,
 * directiva, estado) y el modelo nuevo (spec §11.1).
 */
class LegacyMapping {

    public const SLUG_BASE = 'cuota-base';
    public const SLUG_TRAINING = 'entrenamiento-generico';
    public const SLUG_SWIMMING = 'natacion';

    public const TYPE_NORMAL = 'normal';
    public const TYPE_BOARD = 'junta';

    /** Modalidad antigua => clave de opción del servicio de entrenamiento. */
    public const TRAINING_OPTIONS = [
        'sprint' => 'sprint',
        'olimpic' => 'olimpico',
    ];

    /**
     * Fecha de un campo date_picker de ACF (Ymd) a Y-m-d. Deja igual lo que ya es Y-m-d.
     *
     * @param   string $value Valor guardado.
     * @return 	string Y-m-d o ''.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function acf_date_to_iso( string $value ): string {
        $value = trim( $value );
        if ( 1 === preg_match( '/^(\d{4})(\d{2})(\d{2})$/', $value, $m ) ) {
            return $m[1] . '-' . $m[2] . '-' . $m[3];
        }

        return 1 === preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : '';
    }

    /**
     * Fecha Y-m-d al formato que guarda ACF (Ymd).
     *
     * @param   string $iso Y-m-d.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function iso_to_acf_date( string $iso ): string {
        return str_replace( '-', '', $iso );
    }

    /**
     * Servicios del catálogo que usa la equivalencia, o null si falta alguno.
     *
     * @return 	array{base: Service, training: Service, swimming: Service}|null
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function services(): ?array {
        $base = ServiceCatalog::get_by_slug( self::SLUG_BASE );
        $training = ServiceCatalog::get_by_slug( self::SLUG_TRAINING );
        $swimming = ServiceCatalog::get_by_slug( self::SLUG_SWIMMING );

        if ( null === $base || null === $training || null === $swimming ) {
            return null;
        }

        return [
            'base' => $base,
            'training' => $training,
            'swimming' => $swimming,
        ];
    }

    /**
     * Servicios que corresponden a los campos antiguos de un socio.
     *
     * @param   int   $user_id  Socio.
     * @param   array $services Resultado de services().
     * @return 	array{items: array<int, array{service_id: int, option_key: string}>, warnings: string[]}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function desired_services( int $user_id, array $services ): array {
        $plan = (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_MEMBERSHIP_PLAN, true );
        $training_type = (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_TRAINING_TYPE, true );
        $swimming = (string) get_user_meta( $user_id, CustomFields::USER_PROFILE_SWIMMING, true );

        $items = [ [ 'service_id' => $services['base']->id, 'option_key' => '' ] ];
        $warnings = [];

        if ( '' === $plan ) {
            $warnings[] = __( 'Sin tarifa: se le pone solo la cuota base.', SULAYR_TEXTDOMAIN );
        } elseif ( 'premium' === $plan ) {
            $option = self::TRAINING_OPTIONS[ $training_type ] ?? '';
            if ( '' === $option ) {
                $warnings[] = __( 'Tarifa de entrenamiento sin modalidad: el servicio queda sin opción.', SULAYR_TEXTDOMAIN );
            }
            $items[] = [ 'service_id' => $services['training']->id, 'option_key' => $option ];
        } elseif ( 'basic' !== $plan ) {
            $warnings[] = sprintf( __( 'Tarifa desconocida «%s»: se le pone solo la cuota base.', SULAYR_TEXTDOMAIN ), $plan );
        }

        if ( '1' === $swimming ) {
            $items[] = [ 'service_id' => $services['swimming']->id, 'option_key' => '' ];
        }

        return [
            'items' => $items,
            'warnings' => $warnings,
        ];
    }

    /**
     * Tipo de socio según la marca de directiva.
     *
     * @param   int $user_id Socio.
     * @return 	string
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function member_type( int $user_id ): string {
        return (bool) get_user_meta( $user_id, CustomFields::USER_PROFILE_IS_DIRECTIVE, true ) ? self::TYPE_BOARD : self::TYPE_NORMAL;
    }

    /**
     * Estado nuevo a partir del antiguo.
     *
     * @param   string $legacy Valor antiguo.
     * @return 	array{status: string, warning: string}
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function status( string $legacy ): array {
        switch ( $legacy ) {
            case '':
            case CustomFields::STATUS_ACTIVE:
                return [ 'status' => CustomFields::STATUS_ACTIVE, 'warning' => '' ];
            case 'standby':
            case CustomFields::STATUS_FROZEN:
                return [ 'status' => CustomFields::STATUS_FROZEN, 'warning' => '' ];
            case CustomFields::STATUS_LEFT:
                return [ 'status' => CustomFields::STATUS_LEFT, 'warning' => '' ];
            case 'moroso':
                return [ 'status' => CustomFields::STATUS_ACTIVE, 'warning' => __( 'Estaba como «moroso»: pasa a activo (moroso ya no es un estado).', SULAYR_TEXTDOMAIN ) ];
            case CustomFields::STATUS_PENDING:
                return [ 'status' => CustomFields::STATUS_PENDING, 'warning' => '' ];
            default:
                return [ 'status' => CustomFields::STATUS_ACTIVE, 'warning' => sprintf( __( 'Estado desconocido «%s»: pasa a activo.', SULAYR_TEXTDOMAIN ), $legacy ) ];
        }
    }
}
