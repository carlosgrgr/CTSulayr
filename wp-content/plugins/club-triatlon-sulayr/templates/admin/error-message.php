<?php
/**
 * Template: Mensaje de error
 *
 * @var string $message Mensaje de error
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="notice notice-error">
    <p><?php echo esc_html( $message ); ?></p>
</div>

