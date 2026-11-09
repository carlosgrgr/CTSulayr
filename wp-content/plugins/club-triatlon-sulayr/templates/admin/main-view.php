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
                        <span class="dashicons sulayr-icon-svg" aria-hidden="true">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="currentColor" focusable="false">
                                <circle cx="17.5" cy="7" r="2.5"/>
                                <path d="M8.2 6.3l4.6-1.6c.6-.2 1.2 0 1.6.5l3.1 4.4-2.1 1.5-1.4-2-2.6 1.6 3.4 2.6-1.9 1.4c-.8-.3-1.6-.3-2.4.1-.9.4-1.8.4-2.7 0L6 13.4l3.5-2.4-.7-1.1-2.5.9-.8-2.3 2.7-.9z"/>
                                <path d="M2 17.6c1.3 0 2-.5 2.7-.9.6-.4 1.1-.7 2-.7s1.4.3 2 .7c.7.4 1.4.9 2.7.9s2-.5 2.7-.9c.6-.4 1.1-.7 2-.7s1.4.3 2 .7c.7.4 1.4.9 2.7.9v2c-1.3 0-2-.5-2.7-.9-.6-.4-1.1-.7-2-.7s-1.4.3-2 .7c-.7.4-1.4.9-2.7.9s-2-.5-2.7-.9c-.6-.4-1.1-.7-2-.7s-1.4.3-2 .7c-.7.4-1.4.9-2.7.9z"/>
                            </svg>
                        </span>
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
                    
                    <?php /* if ( ! empty( $stripe_settings_url ) ) : ?>
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
                    <?php endif; */?>
                </div>
            </div>
        </div>
    </div>
</div>

