<?php
/**
 * Template para email de actualización de perfil de usuario (USUARIO)
 * 
 * Variables disponibles:
 * @var \WP_User $user_data Datos del usuario
 * @var int $user_id ID del usuario
 * @var array $changes Array con los cambios realizados
 * @var string $new_plan Nuevo valor de tarifa
 * @var string $new_training_type Nuevo valor de tipo de entrenamiento
 * @var string $new_swimming Nuevo valor de suplemento natación
 */

use ClubTriatlonSulayr\Services\HelperService;
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
            esc_html( $user_data->first_name )
        );
    ?></h2>
</div>

<p><?php echo esc_html__( 'Se ha modificado tu tarifa.', SULAYR_TEXTDOMAIN ); ?></p>

<?php if ( ! empty( $changes ) ) : ?>
    <div class="changes-section">
        <h3><?php echo esc_html__( 'Cambios realizados:', SULAYR_TEXTDOMAIN ); ?></h3>

        <?php foreach ( $changes as $field_key => $change ) : 
            $old_formatted = esc_html( $change['old_formatted'] );
            $new_formatted = esc_html( $change['new_formatted'] );
        ?>
            <div class="change-item">
                <div class="change-label"><?php echo esc_html( $change['label'] ); ?></div>
                <div class="old-value">
                    <strong><?php echo esc_html__( 'Anterior:', SULAYR_TEXTDOMAIN ); ?></strong> 
                    <?php echo $old_formatted; ?>
                </div>
                <div class="new-value">
                    <strong><?php echo esc_html__( 'Nuevo:', SULAYR_TEXTDOMAIN ); ?></strong> 
                    <?php echo $new_formatted; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<hr>

<div class="summary-section">
    <h3><?php echo esc_html__( 'Tu tarifa actual es:', SULAYR_TEXTDOMAIN ); ?></h3>
    
    <div class="summary-item">
        <strong><?php echo esc_html__( 'Tarifa:', SULAYR_TEXTDOMAIN ); ?></strong> 
        <?php echo esc_html( HelperService::format_membership_plan( $new_plan ) ); ?>
    </div>

    <?php if ( $new_plan === 'premium' ) : ?>
        <div class="summary-item">
            <strong><?php echo esc_html__( 'Tipo de entrenamiento:', SULAYR_TEXTDOMAIN ); ?></strong> 
            <?php echo esc_html( HelperService::format_training_type( $new_training_type ) ); ?>
        </div>
    <?php endif; ?>
        
    <div class="summary-item">
        <strong><?php echo esc_html__( 'Suplemento natación:', SULAYR_TEXTDOMAIN ); ?></strong> 
        <?php echo esc_html( HelperService::format_swimming_supplement_to_label( $new_swimming ) ); ?>
    </div>
</div>

<?php
// Cargar footer
echo TemplateService::render( 'email/parts/footer' );
?>

