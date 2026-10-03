<?php
/**
 * Template: Tabla de nadadores (partial)
 *
 * @var array $swimmers Lista de nadadores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$total_count = count( $swimmers );
?>
<p class="sulayr-swimmers-count">
    <strong><?php esc_html_e( 'Total de nadadores:', SULAYR_TEXTDOMAIN ); ?></strong> 
    <?php echo esc_html( $total_count ); ?>
</p>

<table class="wp-list-table widefat fixed striped sulayr-list-table">
    <thead>
        <tr>
            <th><?php esc_html_e( 'ID', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'Nombre', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'Email', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'Teléfono', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'DNI', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></th>
            <th><?php esc_html_e( 'Tipo de entrenamiento', SULAYR_TEXTDOMAIN ); ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ( $swimmers as $swimmer ) : ?>
            <?php sulayr_load_template( 'admin/swimmer-row', [ 'swimmer' => $swimmer ] ); ?>
        <?php endforeach; ?>
    </tbody>
</table>

