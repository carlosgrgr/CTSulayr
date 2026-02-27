<?php
/**
 * Template: Vista principal de configuración
 *
 * @var string $list_by_plan_url URL para listar por tarifa
 * @var string $list_swimmers_url URL para listar nadadores
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap">
    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
    
    <div class="sulayr-settings-main">
        <div class="card">
            <div class="card-body">
                <h2 class="card-title"><?php esc_html_e( 'Listados de deportistas', SULAYR_TEXTDOMAIN ); ?></h2>
                <p class="card-text"><?php esc_html_e( 'Selecciona el tipo de listado que deseas ver:', SULAYR_TEXTDOMAIN ); ?></p>
                
                <div class="sulayr-settings-buttons">
                    <a href="<?php echo esc_url( $list_by_plan_url ); ?>" 
                       class="button button-primary button-large">
                        <span class="dashicons dashicons-groups"></span>
                        <?php esc_html_e( 'Listar deportistas por tarifa', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    
                    <a href="<?php echo esc_url( $list_swimmers_url ); ?>" 
                       class="button button-primary button-large">
                        <span class="dashicons dashicons-swimming"></span>
                        <?php esc_html_e( 'Listar nadadores', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    
                    <a href="<?php echo esc_url( $list_by_status_url ); ?>" 
                       class="button button-primary button-large">
                        <span class="dashicons dashicons-admin-users"></span>
                        <?php esc_html_e( 'Listar deportistas por estado', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    
                    <a href="<?php echo esc_url( $import_url ); ?>" 
                       class="button button-secondary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        <?php esc_html_e( 'Importar deportistas desde CSV', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    
                    <?php if ( ! empty( $stripe_settings_url ) ) : ?>
                    <a href="<?php echo esc_url( $stripe_settings_url ); ?>" 
                       class="button button-secondary button-large">
                        <span class="dashicons dashicons-money-alt"></span>
                        <?php esc_html_e( 'Configuración Stripe (Suscripciones)', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    <?php endif; ?>
                    <?php if ( ! empty( $subscriptions_url ) ) : ?>
                    <a href="<?php echo esc_url( $subscriptions_url ); ?>" 
                       class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-generic"></span>
                        <?php esc_html_e( 'Gestión de suscripciones', SULAYR_TEXTDOMAIN ); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

