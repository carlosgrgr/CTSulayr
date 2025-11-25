<?php
/**
 * Template: Listado de nadadores
 *
 * @var array $swimmers Lista de nadadores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card sulayr-swimmers-card">
    <div class="card-header">
        <h2><?php esc_html_e( 'Listado de nadadores', SULAYR_TEXTDOMAIN ); ?></h2>
        <p><?php esc_html_e( 'Deportistas con suplemento de natación activo', SULAYR_TEXTDOMAIN ); ?></p>
    </div>
    <div class="card-body">
        <?php if ( empty( $swimmers ) ) : ?>
            <p><?php esc_html_e( 'No se encontraron nadadores con suplemento activo.', SULAYR_TEXTDOMAIN ); ?></p>
        <?php else : ?>
            <?php sulayr_load_template( 'admin/swimmers-table', [ 'swimmers' => $swimmers ] ); ?>
        <?php endif; ?>
    </div>
</div>

