<?php
/**
 * Template: Mensaje cuando no hay atletas
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="card sulayr-athletes-list-card">
    <div class="card-body">
        <p><?php esc_html_e( 'No se encontraron deportistas.', SULAYR_TEXTDOMAIN ); ?></p>
    </div>
</div>

