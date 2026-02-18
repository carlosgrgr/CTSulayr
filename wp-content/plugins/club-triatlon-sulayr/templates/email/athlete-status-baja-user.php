<?php
/**
 * Template para email de baja de deportista (USUARIO)
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
    <h2><?php 
        printf(
            __( '¡Hola, %s!', SULAYR_TEXTDOMAIN ),
            esc_html( $user_data->first_name ?: $user_data->display_name )
        );
    ?></h2>
</div>

<p><?php echo esc_html__( 'Te informamos que has sido dado de baja en el club.', SULAYR_TEXTDOMAIN ); ?></p>

<div class="summary-section">
    <p><?php echo esc_html__( 'Queremos agradecerte sinceramente tu estancia en el Club Triatlón Sulayr. Ha sido un placer contar contigo como parte de nuestra comunidad deportiva.', SULAYR_TEXTDOMAIN ); ?></p>
    
    <p><?php echo esc_html__( 'Esperamos poder volver a contar contigo pronto y seguir compartiendo nuestra pasión por el triatlón.', SULAYR_TEXTDOMAIN ); ?></p>
    
    <p><?php echo esc_html__( '¡Te deseamos lo mejor en tus próximos desafíos deportivos!', SULAYR_TEXTDOMAIN ); ?></p>
</div>

<?php
// Cargar footer
echo TemplateService::render( 'email/parts/footer' );
?>
