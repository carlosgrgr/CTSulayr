<?php
/**
 * Template para email de baja de deportista (ADMIN)
 * 
 * Variables disponibles:
 * @var \WP_User $user_data Datos del usuario
 * @var int $user_id ID del usuario
 */

use ClubTriatlonSulayr\Services\TemplateService;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Cargar header
echo TemplateService::render( 'email/parts/header' );
?>

<div class="header">
    <h2><?php echo esc_html__( 'Deportista dado de baja', SULAYR_TEXTDOMAIN ); ?></h2>
</div>

<p><?php 
    printf(
        __( 'El deportista <strong>%s</strong> (%s) ha sido dado de baja.', SULAYR_TEXTDOMAIN ),
        esc_html( $user_data->display_name ),
        esc_html( $user_data->user_email )
    );
?></p>

<p><strong><?php echo esc_html__( 'User ID:', SULAYR_TEXTDOMAIN ); ?></strong> <?php echo esc_html( $user_id ); ?></p>

<?php
// Cargar footer
echo TemplateService::render( 'email/parts/footer' );
?>
