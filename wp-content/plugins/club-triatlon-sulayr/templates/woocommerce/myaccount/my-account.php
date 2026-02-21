<?php
/**
 * My Account page - Override para deportistas (rol athlete).
 * Misma estructura que el panel Sulayr: menú lateral + contenido.
 *
 * @see woocommerce/templates/myaccount/my-account.php
 */

defined( 'ABSPATH' ) || exit;

$menu_path = SULAYR_TEMPLATE_DIR . '/parts/content-user-menu.php';
?>

<div class="container">
    <div class="main-body">
        <div class="row gutters-sm">
            <?php if ( file_exists( $menu_path ) ) : ?>
            <div class="col-md-4 mb-3">
                <?php include $menu_path; ?>
            </div>
            <?php endif; ?>
            <div class="col-md-8">
                <div class="woocommerce-MyAccount-content">
                    <?php do_action( 'woocommerce_account_content' ); ?>
                </div>
            </div>
        </div>
    </div>
</div>
