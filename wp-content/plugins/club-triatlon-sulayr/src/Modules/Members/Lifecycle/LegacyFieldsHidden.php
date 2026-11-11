<?php
namespace ClubTriatlonSulayr\Modules\Members\Lifecycle;

use ClubTriatlonSulayr\Infrastructure\CustomFields;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Oculta los campos antiguos de tarifa, modalidad, natación, directiva y
 * estado en la ficha de usuario y en Mi cuenta (parte C).
 *
 * Servicios, tipo y estado se gestionan en Socios. Las metas de natación y
 * estado las sigue escribiendo ReservationsAccess para reservas (D38); las
 * de tarifa, modalidad y directiva se quedan en la base sin usarse. Un campo
 * que ACF no pinta tampoco se guarda, así que nadie las cambia desde ACF.
 */
class LegacyFieldsHidden {

    public const FIELDS = [
        CustomFields::USER_PROFILE_MEMBERSHIP_PLAN,
        CustomFields::USER_PROFILE_TRAINING_TYPE,
        CustomFields::USER_PROFILE_SWIMMING,
        CustomFields::USER_PROFILE_IS_DIRECTIVE,
        CustomFields::USER_PROFILE_ATHLETE_STATUS,
    ];

    /**
     * __construct()
     *
     * @return 	void
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public function __construct() {
        foreach ( self::FIELDS as $name ) {
            add_filter( 'acf/prepare_field/name=' . $name, '__return_false', 99 );
        }
    }
}
