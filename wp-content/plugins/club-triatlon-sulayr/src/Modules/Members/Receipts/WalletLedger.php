<?php
namespace ClubTriatlonSulayr\Modules\Members\Receipts;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\Members\MemberDates;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Movimientos automáticos del monedero (CPT sulayr_wallet, §9.4): pagos de
 * recibos, devoluciones, correcciones y retribución de la junta.
 *
 * Se guardan igual que los que se crean a mano (metas de ACF con su
 * referencia), para que salgan en el listado, el saldo y Mi monedero.
 */
class WalletLedger {

    /** Meta del movimiento: recibo relacionado. */
    public const META_RECEIPT = 'sulayr_wallet_receipt';
    /** Meta del movimiento: mes (AAAA-MM) de una retribución de la junta. */
    public const META_BOARD_PERIOD = 'sulayr_wallet_board_period';

    /**
     * Saldo del socio: suma de sus movimientos (como «Estado del monedero»).
     *
     * @param   int $user_id Socio.
     * @return 	float
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function balance( int $user_id ): float {
        global $wpdb;

        $total = $wpdb->get_var( $wpdb->prepare(
            "SELECT SUM( CAST( pm_amount.meta_value AS DECIMAL(12,2) ) )
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_athlete ON p.ID = pm_athlete.post_id AND pm_athlete.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            WHERE p.post_type = %s AND p.post_status IN ('publish','draft','pending') AND pm_athlete.meta_value = %s",
            CustomFields::WALLET_ATHLETE,
            CustomFields::WALLET_AMOUNT,
            WalletPostType::POST_TYPE_NAME,
            (string) $user_id
        ) );

        return round( (float) $total, 2 );
    }

    /**
     * Crea un movimiento.
     *
     * @param   int    $user_id Socio.
     * @param   float  $amount  Positivo = ingreso, negativo = cargo.
     * @param   string $concept Concepto (también es el título).
     * @param   string $date    Fecha Y-m-d.
     * @param   array  $extra   Metas adicionales.
     * @return 	int|\WP_Error ID del movimiento.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function add( int $user_id, float $amount, string $concept, string $date, array $extra = [] ) {
        $post_id = wp_insert_post( [
            'post_type' => WalletPostType::POST_TYPE_NAME,
            'post_status' => 'publish',
            'post_title' => $concept,
            'post_author' => get_current_user_id(),
        ], true );
        if ( is_wp_error( $post_id ) ) {
            return $post_id;
        }

        $fields = [
            CustomFields::WALLET_ATHLETE => (string) $user_id,
            CustomFields::WALLET_AMOUNT => (string) round( $amount, 2 ),
            CustomFields::WALLET_CONCEPT => $concept,
            CustomFields::WALLET_DATE => MemberDates::iso_to_acf( $date ),
        ];
        if ( get_current_user_id() > 0 ) {
            $fields[ CustomFields::WALLET_CREATED_BY ] = (string) get_current_user_id();
        }
        foreach ( $fields as $name => $value ) {
            update_post_meta( $post_id, $name, $value );
            update_post_meta( $post_id, '_' . $name, 'field_' . md5( $name ) );
        }
        foreach ( $extra as $name => $value ) {
            update_post_meta( $post_id, $name, $value );
        }

        return (int) $post_id;
    }

    /**
     * True si existe un movimiento del socio con esa meta y valor.
     *
     * @param   int    $user_id Socio.
     * @param   string $key     Meta.
     * @param   string $value   Valor.
     * @return 	bool
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function exists( int $user_id, string $key, string $value ): bool {
        global $wpdb;

        return 0 < (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} a ON p.ID = a.post_id AND a.meta_key = %s AND a.meta_value = %s
            INNER JOIN {$wpdb->postmeta} m ON p.ID = m.post_id AND m.meta_key = %s AND m.meta_value = %s
            WHERE p.post_type = %s AND p.post_status <> 'trash'",
            CustomFields::WALLET_ATHLETE,
            (string) $user_id,
            $key,
            $value,
            WalletPostType::POST_TYPE_NAME
        ) );
    }
}
