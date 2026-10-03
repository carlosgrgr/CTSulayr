<?php

namespace ClubTriatlonSulayr\Modules\Wallet;

use ClubTriatlonSulayr\Infrastructure\CustomFields;
use ClubTriatlonSulayr\Modules\PostTypes\WalletPostType;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Listado admin del CPT Monedero: columnas y filtro por deportista.
 */
class WalletAdminList {

    public function __construct() {
        $this->init();
    }

    public function init(): void {
        add_filter( 'manage_edit-' . WalletPostType::POST_TYPE_NAME . '_columns', [ $this, 'add_columns' ] );
        add_action( 'manage_' . WalletPostType::POST_TYPE_NAME . '_posts_custom_column', [ $this, 'render_column' ], 10, 2 );
        add_action( 'restrict_manage_posts', [ $this, 'add_athlete_filter' ], 10, 2 );
        add_filter( 'pre_get_posts', [ $this, 'filter_by_athlete' ] );
        add_filter( 'manage_edit-' . WalletPostType::POST_TYPE_NAME . '_sortable_columns', [ $this, 'sortable_columns' ] );
    }

    public function add_columns( array $columns ): array {
        $new = [];
        $new['cb'] = $columns['cb'] ?? '';
        $new['title'] = $columns['title'] ?? __( 'Título', SULAYR_TEXTDOMAIN );
        $new[ CustomFields::WALLET_ATHLETE ] = __( 'Deportista', SULAYR_TEXTDOMAIN );
        $new[ CustomFields::WALLET_AMOUNT ] = __( 'Importe', SULAYR_TEXTDOMAIN );
        $new[ CustomFields::WALLET_CONCEPT ] = __( 'Concepto', SULAYR_TEXTDOMAIN );
        $new[ CustomFields::WALLET_DATE ] = __( 'Fecha movimiento', SULAYR_TEXTDOMAIN );
        $new['date'] = $columns['date'] ?? __( 'Fecha', SULAYR_TEXTDOMAIN );
        return $new;
    }

    public function render_column( string $column_name, int $post_id ): void {
        if ( $column_name === CustomFields::WALLET_ATHLETE ) {
            $user_id = (int) get_field( CustomFields::WALLET_ATHLETE, $post_id );
            if ( $user_id ) {
                $user = get_userdata( $user_id );
                echo $user ? esc_html( $user->display_name ) : '—';
            } else {
                echo '—';
            }
            return;
        }
        if ( $column_name === CustomFields::WALLET_AMOUNT ) {
            $amount = get_field( CustomFields::WALLET_AMOUNT, $post_id );
            if ( $amount !== '' && $amount !== null ) {
                echo esc_html( number_format_i18n( (float) $amount, 2 ) . ' €' );
            } else {
                echo '—';
            }
            return;
        }
        if ( $column_name === CustomFields::WALLET_CONCEPT ) {
            $concept = get_field( CustomFields::WALLET_CONCEPT, $post_id );
            echo $concept ? esc_html( wp_trim_words( $concept, 8 ) ) : '—';
            return;
        }
        if ( $column_name === CustomFields::WALLET_DATE ) {
            $date = get_field( CustomFields::WALLET_DATE, $post_id );
            if ( $date ) {
                echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $date ) ) );
            } else {
                echo '—';
            }
        }
    }

    public function add_athlete_filter( string $post_type, string $which ): void {
        if ( $post_type !== WalletPostType::POST_TYPE_NAME || $which !== 'top' ) {
            return;
        }

        $current = isset( $_GET['sulayr_wallet_athlete'] ) ? sanitize_text_field( wp_unslash( $_GET['sulayr_wallet_athlete'] ) ) : '';
        $athletes = get_users( [ 'role' => 'athlete', 'orderby' => 'display_name', 'number' => -1 ] );

        echo '<label class="screen-reader-text" for="sulayr-wallet-athlete-filter">' . esc_html__( 'Filtrar por deportista', SULAYR_TEXTDOMAIN ) . '</label>';
        echo '<select name="sulayr_wallet_athlete" id="sulayr-wallet-athlete-filter">';
        echo '<option value="">' . esc_html__( 'Todos los deportistas', SULAYR_TEXTDOMAIN ) . '</option>';
        foreach ( $athletes as $user ) {
            printf(
                '<option value="%s"%s>%s</option>',
                esc_attr( (string) $user->ID ),
                selected( $current, (string) $user->ID, false ),
                esc_html( $user->display_name )
            );
        }
        echo '</select>';
    }

    public function filter_by_athlete( \WP_Query $query ): void {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        if ( ! $screen || $screen->post_type !== WalletPostType::POST_TYPE_NAME ) {
            return;
        }

        $athlete_id = isset( $_GET['sulayr_wallet_athlete'] ) ? absint( $_GET['sulayr_wallet_athlete'] ) : 0;
        if ( $athlete_id >= 1 ) {
            $query->set( 'meta_query', [
                [
                    'key' => CustomFields::WALLET_ATHLETE,
                    'value' => $athlete_id,
                    'compare' => '=',
                ],
            ] );
        }

        $orderby = $query->get( 'orderby' );
        if ( $orderby === CustomFields::WALLET_AMOUNT ) {
            $query->set( 'meta_key', CustomFields::WALLET_AMOUNT );
            $query->set( 'orderby', 'meta_value_num' );
        } elseif ( $orderby === CustomFields::WALLET_DATE ) {
            $query->set( 'meta_key', CustomFields::WALLET_DATE );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    public function sortable_columns( array $columns ): array {
        $columns[ CustomFields::WALLET_AMOUNT ] = CustomFields::WALLET_AMOUNT;
        $columns[ CustomFields::WALLET_DATE ] = CustomFields::WALLET_DATE;
        return $columns;
    }
}
