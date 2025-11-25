<?php
/**
 * Template: Vista de listado (wrapper)
 *
 * @var string $back_url URL para volver
 * @var string $content Contenido del listado a mostrar
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="sulayr-settings-list">
        <p>
            <a href="<?php echo esc_url( $back_url ); ?>" 
               class="button">
                <span class="dashicons dashicons-arrow-left-alt"></span>
                <?php esc_html_e( 'Volver', SULAYR_TEXTDOMAIN ); ?>
            </a>
        </p>
        
        <?php echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
</div>

