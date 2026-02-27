<?php

namespace ClubTriatlonSulayr\Modules\Subscriptions;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Creates and manages WooCommerce reference products for membership plans.
 * Products are simple (not subscription type); used for price/name reference only.
 */
class SubscriptionProducts {

    const META_MEMBERSHIP_TYPE = '_sulayr_membership_type';

    const MEMBERSHIP_BASIC    = 'basic';
    const MEMBERSHIP_PREMIUM  = 'premium';
    const MEMBERSHIP_SWIMMING = 'swimming';
    const DIRECTIVE_SWIMMING = 'directive_swimming';

    /** Default products config: slug => [ name, price, meta_value ] */
    private static $defaults = [
        'sulayr-tarifa-basica'       => [
            'name'  => 'Tarifa Básica',
            'price' => 22,
            'type'  => self::MEMBERSHIP_BASIC,
        ],
        'sulayr-tarifa-entrenamientos' => [
            'name'  => 'Tarifa Entrenamientos',
            'price' => 33,
            'type'  => self::MEMBERSHIP_PREMIUM,
        ],
        'sulayr-suplemento-natacion'  => [
            'name'  => 'Suplemento Natación',
            'price' => 25,
            'type'  => self::MEMBERSHIP_SWIMMING,
        ],
        'sulayr-directiva-natacion'  => [
            'name'  => 'Natación para directiva',
            'price' => 29.5,
            'type'  => self::DIRECTIVE_SWIMMING,
        ],
    ];

    public function __construct() {
        add_action( 'init', [ $this, 'ensure_products' ], 20 );
    }

    /**
     * Ensure the 3 reference products exist. Create if missing.
     */
    public function ensure_products(): void {
        if ( ! class_exists( 'WooCommerce' ) ) {
            return;
        }

        foreach ( self::$defaults as $slug => $config ) {
            $product_id = $this->get_product_id_by_membership_type( $config['type'] );
            if ( $product_id ) {
                continue;
            }
            $this->create_product( $slug, $config );
        }
    }

    /**
     * Get WooCommerce product ID by membership type meta.
     *
     * @param string $type One of basic, premium, swimming.
     * @return int|null Product ID or null.
     */
    public function get_product_id_by_membership_type( string $type ): ?int {
        $posts = get_posts( [
            'post_type'      => 'product',
            'posts_per_page' => 1,
            'post_status'    => 'any',
            'meta_query'     => [
                [
                    'key'   => self::META_MEMBERSHIP_TYPE,
                    'value' => $type,
                    'compare' => '=',
                ],
            ],
            'fields' => 'ids',
        ] );

        if ( empty( $posts ) ) {
            return null;
        }
        return (int) $posts[0];
    }

    /**
     * Create a single reference product.
     *
     * @param string $slug   Product slug.
     * @param array  $config [ 'name', 'price', 'type' ].
     * @return int|null Product ID or null on failure.
     */
    private function create_product( string $slug, array $config ): ?int {
        if ( ! function_exists( 'wc_get_product_object' ) ) {
            return null;
        }

        $product = new \WC_Product_Simple();
        $product->set_name( $config['name'] );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' );
        $product->set_sold_individually( true );
        $product->set_regular_price( (string) $config['price'] );
        $product->set_virtual( true );
        $product->set_slug( $slug );

        $id = $product->save();
        if ( ! $id ) {
            return null;
        }

        update_post_meta( $id, self::META_MEMBERSHIP_TYPE, $config['type'] );
        return $id;
    }

    /**
     * Get price for a membership type from the reference product.
     *
     * @param string $type One of basic, premium, swimming.
     * @return float Price or 0.
     */
    public function get_price_for_type( string $type ): float {
        $product_id = $this->get_product_id_by_membership_type( $type );
        if ( ! $product_id ) {
            $default = array_filter( self::$defaults, function ( $c ) use ( $type ) {
                return $c['type'] === $type;
            } );
            return $default ? (float) array_values( $default )[0]['price'] : 0.0;
        }
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return 0.0;
        }
        return (float) $product->get_regular_price();
    }

    /**
     * Get product name for a membership type.
     *
     * @param string $type One of basic, premium, swimming.
     * @return string
     */
    public function get_name_for_type( string $type ): string {
        $product_id = $this->get_product_id_by_membership_type( $type );
        if ( ! $product_id ) {
            $default = array_filter( self::$defaults, function ( $c ) use ( $type ) {
                return $c['type'] === $type;
            } );
            return $default ? array_values( $default )[0]['name'] : $type;
        }
        $product = wc_get_product( $product_id );
        return $product ? $product->get_name() : $type;
    }
}
