<?php
namespace ClubTriatlonSulayr\Modules\Members\Migration;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Borra los datos que dejó la integración con Stripe (spec §12, D57):
 * opciones con claves, metas de usuario, productos de tarifas y posts del
 * antiguo histórico de cambios. Un producto de tarifa que aparece en algún
 * pedido pasa a borrador en vez de borrarse, para no romper el pedido.
 */
class StripeCleanup {

    /** Meta con la que se marcaban los productos de tarifa. */
    private const PRODUCT_META = '_sulayr_membership_type';
    private const OLD_CHANGES_POST_TYPE = 'sulayr_subscription_changes';

    /**
     * Ejecuta la limpieza.
     *
     * @param   bool $dry_run true = solo describe.
     * @return 	string[] Líneas de resultado.
     * @access 	public
     * @package	club-triatlon-sulayr
     */
    public static function run( bool $dry_run ): array {
        global $wpdb;

        $verb = $dry_run ? 'Se borrarían' : 'Borradas';
        $lines = [];

        // Opciones (claves, secretos y transitorios de Stripe).
        $options = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
            $wpdb->esc_like( 'sulayr_stripe_' ) . '%',
            $wpdb->esc_like( '_transient_sulayr_stripe_' ) . '%',
            $wpdb->esc_like( '_transient_timeout_sulayr_stripe_' ) . '%',
            $wpdb->esc_like( '_transient_sulayr_checkout_url_' ) . '%',
            $wpdb->esc_like( '_transient_sulayr_subscription_change_blocked_' ) . '%'
        ) );
        $lines[] = sprintf( '%s %d opciones: %s', $verb, count( $options ), implode( ', ', $options ) ?: '—' );
        if ( false === $dry_run ) {
            foreach ( $options as $option ) {
                delete_option( $option );
            }
        }

        // Metas de usuario.
        $meta_counts = $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_key, COUNT(*) AS total FROM {$wpdb->usermeta} WHERE meta_key LIKE %s OR meta_key LIKE %s GROUP BY meta_key",
            $wpdb->esc_like( 'sulayr_stripe_' ) . '%',
            $wpdb->esc_like( 'sulayr_subscription_' ) . '%'
        ) );
        $meta_text = [];
        foreach ( $meta_counts as $row ) {
            $meta_text[] = $row->meta_key . ' (' . (int) $row->total . ')';
            if ( false === $dry_run ) {
                delete_metadata( 'user', 0, $row->meta_key, '', true );
            }
        }
        $lines[] = sprintf( '%s metas de usuario: %s', $verb, implode( ', ', $meta_text ) ?: '—' );

        // Productos de tarifa.
        $product_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s",
            self::PRODUCT_META
        ) ) );
        $order_items_table = $wpdb->prefix . 'woocommerce_order_itemmeta';
        $has_order_items = $order_items_table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $order_items_table ) );

        foreach ( $product_ids as $product_id ) {
            $title = get_the_title( $product_id );
            $used = $has_order_items && 0 < (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$order_items_table} WHERE meta_key IN ('_product_id', '_variation_id') AND meta_value = %s",
                (string) $product_id
            ) );

            if ( $used ) {
                $lines[] = sprintf( 'Producto #%d «%s» aparece en pedidos: %s a borrador.', $product_id, $title, $dry_run ? 'se pasaría' : 'pasado' );
                if ( false === $dry_run ) {
                    wp_update_post( [ 'ID' => $product_id, 'post_status' => 'draft' ] );
                    delete_post_meta( $product_id, self::PRODUCT_META );
                }
                continue;
            }

            $lines[] = sprintf( 'Producto #%d «%s»: %s.', $product_id, $title, $dry_run ? 'se borraría' : 'borrado' );
            if ( false === $dry_run ) {
                $product = function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : null;
                if ( $product ) {
                    $product->delete( true );
                } else {
                    wp_delete_post( $product_id, true );
                }
            }
        }
        if ( empty( $product_ids ) ) {
            $lines[] = 'No hay productos de tarifa.';
        }

        // Posts del antiguo histórico de cambios (el CPT nunca llegó a registrarse).
        $old_posts = array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s",
            self::OLD_CHANGES_POST_TYPE
        ) ) );
        $lines[] = sprintf( '%s %d posts de «%s».', $verb, count( $old_posts ), self::OLD_CHANGES_POST_TYPE );
        if ( false === $dry_run ) {
            foreach ( $old_posts as $post_id ) {
                wp_delete_post( $post_id, true );
            }
        }

        return $lines;
    }
}
