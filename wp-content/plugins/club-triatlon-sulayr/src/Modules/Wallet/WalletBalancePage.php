<?php

namespace ClubTriatlonSulayr\Modules\Wallet;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Página en wp-admin: estado total del monedero por deportista (listado de deportistas con saldo).
 * Subpágina del menú del CPT Monedero.
 */
class WalletBalancePage {

    const PAGE_SLUG = 'sulayr-wallet-balance';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'add_submenu_page' ], 20 );
    }

    public function add_submenu_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . WalletPostType::POST_TYPE_NAME,
            __( 'Estado del monedero', SULAYR_TEXTDOMAIN ),
            __( 'Estado del monedero', SULAYR_TEXTDOMAIN ),
            'manage_options',
            self::PAGE_SLUG,
            [ $this, 'render_page' ]
        );
    }

    public function render_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'No tienes permiso para acceder a esta página.', SULAYR_TEXTDOMAIN ) );
        }

        $athletes = get_users( [ 'role' => 'athlete', 'orderby' => 'display_name', 'number' => -1 ] );
        $balances = $this->get_balances_by_athlete();

        $total_all = 0;
        $rows = [];
        foreach ( $athletes as $user ) {
            $balance = isset( $balances[ $user->ID ] ) ? (float) $balances[ $user->ID ] : 0;
            $total_all += $balance;
            $rows[] = [
                'user_id'  => $user->ID,
                'name'     => $user->display_name,
                'balance'  => $balance,
            ];
        }

        $orderby = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'name';
        $order   = isset( $_GET['order'] ) ? strtolower( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'asc';
        if ( ! in_array( $orderby, [ 'name', 'balance' ], true ) ) {
            $orderby = 'name';
        }
        if ( $order !== 'desc' ) {
            $order = 'asc';
        }

        usort( $rows, function ( $a, $b ) use ( $orderby, $order ) {
            if ( $orderby === 'balance' ) {
                $cmp = $a['balance'] <=> $b['balance'];
            } else {
                $cmp = strcasecmp( $a['name'], $b['name'] );
            }
            return $order === 'asc' ? $cmp : -$cmp;
        } );

        $base_url = admin_url( 'edit.php?post_type=' . WalletPostType::POST_TYPE_NAME . '&page=' . self::PAGE_SLUG );
        $link_name_asc  = add_query_arg( [ 'orderby' => 'name',  'order' => 'asc' ],  $base_url );
        $link_name_desc = add_query_arg( [ 'orderby' => 'name',  'order' => 'desc' ], $base_url );
        $link_bal_asc   = add_query_arg( [ 'orderby' => 'balance', 'order' => 'asc' ],  $base_url );
        $link_bal_desc  = add_query_arg( [ 'orderby' => 'balance', 'order' => 'desc' ], $base_url );

        ?>
        <div class="wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            <p class="description"><?php esc_html_e( 'Saldo actual del monedero de cada deportista.', SULAYR_TEXTDOMAIN ); ?></p>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="column-name sortable <?php echo $orderby === 'name' ? 'sorted ' . esc_attr( $order ) : ''; ?>">
                            <a href="<?php echo esc_url( $orderby === 'name' && $order === 'asc' ? $link_name_desc : $link_name_asc ); ?>">
                                <span><?php esc_html_e( 'Deportista', SULAYR_TEXTDOMAIN ); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                        <th scope="col" class="column-balance sortable <?php echo $orderby === 'balance' ? 'sorted ' . esc_attr( $order ) : ''; ?>" style="width:140px;text-align:right;">
                            <a href="<?php echo esc_url( $orderby === 'balance' && $order === 'asc' ? $link_bal_desc : $link_bal_asc ); ?>">
                                <span><?php esc_html_e( 'Saldo', SULAYR_TEXTDOMAIN ); ?></span>
                                <span class="sorting-indicator"></span>
                            </a>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( empty( $rows ) ) : ?>
                        <tr>
                            <td colspan="2"><?php esc_html_e( 'No hay deportistas.', SULAYR_TEXTDOMAIN ); ?></td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ( $rows as $row ) : ?>
                            <tr>
                                <td class="column-name">
                                    <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . WalletPostType::POST_TYPE_NAME . '&sulayr_wallet_athlete=' . $row['user_id'] ) ); ?>">
                                        <?php echo esc_html( $row['name'] ); ?>
                                    </a>
                                </td>
                                <td class="column-balance" style="text-align:right;">
                                    <strong><?php echo esc_html( number_format_i18n( $row['balance'], 2 ) . ' €' ); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
                <?php if ( ! empty( $rows ) ) : ?>
                <tfoot>
                    <tr>
                        <th scope="row" style="text-align:right;"><?php esc_html_e( 'Total', SULAYR_TEXTDOMAIN ); ?></th>
                        <td style="text-align:right;"><strong><?php echo esc_html( number_format_i18n( $total_all, 2 ) . ' €' ); ?></strong></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>
        <?php
    }

    /**
     * Devuelve un array [ user_id => saldo_total ] para todos los deportistas que tienen movimientos.
     */
    private function get_balances_by_athlete(): array {
        global $wpdb;

        $meta_athlete = CustomFields::WALLET_ATHLETE;
        $meta_amount = CustomFields::WALLET_AMOUNT;
        $post_type = WalletPostType::POST_TYPE_NAME;

        $query = $wpdb->prepare(
            "SELECT pm_athlete.meta_value AS user_id, SUM( CAST( pm_amount.meta_value AS DECIMAL(12,2) ) ) AS total
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm_athlete ON p.ID = pm_athlete.post_id AND pm_athlete.meta_key = %s
            INNER JOIN {$wpdb->postmeta} pm_amount ON p.ID = pm_amount.post_id AND pm_amount.meta_key = %s
            WHERE p.post_type = %s AND p.post_status IN ('publish','draft','pending')
            GROUP BY pm_athlete.meta_value",
            $meta_athlete,
            $meta_amount,
            $post_type
        );

        $results = $wpdb->get_results( $query );
        $out = [];
        if ( $results ) {
            foreach ( $results as $row ) {
                $out[ (int) $row->user_id ] = $row->total;
            }
        }
        return $out;
    }
}
