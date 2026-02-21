<?php

use ClubTriatlonSulayr\Infrastructure\CustomTemplates;
/**
 * Template part user profile menu
 */

$my_profile   = CustomTemplates::TEMPLATE_MY_PROFILE;
$unsusbscribe = CustomTemplates::TEMPLATE_UNSUBSCRIBE;
$is_myaccount = function_exists( 'is_account_page' ) && is_account_page();
?>

<nav class="user-profile-panel mb-4">
    <ul class="nav flex-column shadow-sm rounded bg-white py-2 px-0">
        <li class="nav-item">
            <a href="<?php echo esc_url( site_url( $my_profile['url'] ) ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo is_page( 'mi-perfil' ) ? 'active' : ''; ?>">
               <i class="bi bi-person-circle me-2"></i>
               <?php echo $my_profile['name']; ?>
            </a>
        </li>
        <?php if ( class_exists( 'WooCommerce' ) && function_exists( 'wc_get_page_permalink' ) ) : ?>
        <li class="nav-item">
            <a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo $is_myaccount ? 'active' : ''; ?>">
               <i class="bi bi-bag me-2"></i>
               <?php _e( 'Mis pedidos', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
        <?php endif; ?>
        <li class="nav-item">
            <a href="<?php echo esc_url( site_url( $unsusbscribe['url'] ) ); ?>"
               class="nav-link d-flex align-items-center px-4 py-3 <?php echo is_page( 'darse-de-baja' ) ? 'active' : ''; ?>">
               <i class="bi bi-box-arrow-right me-2"></i>
               <?php echo $unsusbscribe['name']; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link d-flex align-items-center px-4 py-3" href="<?php echo esc_url( wp_logout_url( site_url( CustomTemplates::TEMPLATE_LOGIN['url'] ) ) ); ?>">
                <i class="bi bi-box-arrow-right me-2"></i>    
                <?php _e( 'Cerrar sesión', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </li>
    </ul>
</nav>