<?php
/**
 * Template: Listado de nadadores
 *
 * @var array $swimmers Lista de nadadores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$total_count = count( $swimmers );
?>
<div class="card sulayr-athletes-list-card">
    <div class="card-body">
        <h2 class="sulayr-athletes-title">
            *<?php esc_html_e( 'NADADORES', SULAYR_TEXTDOMAIN ); ?>* (<?php echo esc_html( $total_count ); ?>)
        </h2>
        
        <?php if ( empty( $swimmers ) ) : ?>
            <?php sulayr_load_template( 'admin/empty-athletes' ); ?>
        <?php else : ?>
            <?php sulayr_load_template( 'admin/swimmers-group', [ 'swimmers' => $swimmers ] ); ?>
        <?php endif; ?>
    </div>
</div>

