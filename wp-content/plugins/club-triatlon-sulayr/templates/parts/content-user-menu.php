<?php

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
/**
 * Template part user profile menu
 */

$my_profile = CustomTemplates::TEMPLATE_MY_PROFILE;

$is_wc_active = class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' );
if ( $is_wc_active ) {
    $myaccount_url   = wc_get_page_permalink( 'myaccount' );
    $wc_orders_url   = wc_get_account_endpoint_url( 'orders', '', $myaccount_url );
    $wc_addresses_url = wc_get_account_endpoint_url( 'edit-address', '', $myaccount_url );
    $wc_account_url  = wc_get_account_endpoint_url( 'edit-account', '', $myaccount_url );
    $is_orders       = function_exists( 'wc_is_current_account_menu_item' ) && wc_is_current_account_menu_item( 'orders' );
    $is_edit_address = function_exists( 'wc_is_current_account_menu_item' ) && wc_is_current_account_menu_item( 'edit-address' );
    $is_edit_account = function_exists( 'wc_is_current_account_menu_item' ) && wc_is_current_account_menu_item( 'edit-account' );
} else {
    $wc_orders_url = $wc_addresses_url = $wc_account_url = '';
    $is_orders = $is_edit_address = $is_edit_account = false;
}
?>

<nav class="user-profile-panel mb-4">
    <ul class="nav flex-column shadow-sm rounded bg-white py-2 px-0">
        <li class="nav-item">
            <a href="<?php echo esc_url( site_url( $my_profile['url'] ) ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo is_page( 'perfil-deportista' ) ? 'active' : ''; ?>">
               <i class="bi bi-person-circle me-2"></i>
               <?php echo $my_profile['name']; ?>
            </a>
        </li>
        <?php if ( $is_wc_active && $wc_orders_url ) : ?>
        <li class="nav-item">
            <a href="<?php echo esc_url( $wc_orders_url ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo $is_orders ? 'active' : ''; ?>">
               <i class="bi bi-bag me-2"></i>
               <?php _e( 'Pedidos', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
        <?php endif; ?>
        <?php if ( $is_wc_active && $wc_addresses_url ) : ?>
        <li class="nav-item">
            <a href="<?php echo esc_url( $wc_addresses_url ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo $is_edit_address ? 'active' : ''; ?>">
               <i class="bi bi-geo-alt me-2"></i>
               <?php _e( 'Direcciones', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
        <?php endif; ?>
        <?php if ( $is_wc_active && $wc_account_url ) : ?>
        <li class="nav-item">
            <a href="<?php echo esc_url( $wc_account_url ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo $is_edit_account ? 'active' : ''; ?>">
               <i class="bi bi-person-lines-fill me-2"></i>
               <?php _e( 'Detalles de cuenta', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center px-4 py-3" href="<?php echo esc_url( wp_logout_url( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) ) ); ?>">
                <i class="bi bi-box-arrow-right me-2"></i>    
                <?php _e( 'Cerrar sesión', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
    </ul>
</nav>