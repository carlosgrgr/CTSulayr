<?php
/**
 * Bloque de activación / estado de suscripción en perfil deportista.
 * Requiere: $has_active_subscription, $can_activate_subscription, $subscription_blocked_msg, $activado.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="sulayr-subscription-block">
    <?php if ( ! empty( $activado ) ) : ?>
        <div class="notice notice-success sulayr-subscription-notice">
            <p><?php esc_html_e( 'Suscripción activada correctamente. A partir de ahora se te cobrará la cuota mensualmente.', SULAYR_TEXTDOMAIN ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $suscripcion_error ) ) : ?>
        <div class="notice notice-error sulayr-subscription-notice">
            <p><?php esc_html_e( 'No se pudo generar el enlace de pago.', SULAYR_TEXTDOMAIN ); ?></p>
            <?php if ( ! empty( $stripe_error_msg ) ) : ?>
                <p><strong><?php echo esc_html( $stripe_error_msg ); ?></strong></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Comprueba que tengas tarifa asignada (o marca de directiva) y que el club tenga configurada la clave secreta de Stripe en Ajustes → Configuración Sulayr → Configuración Stripe.', SULAYR_TEXTDOMAIN ); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php if ( ! empty( $subscription_blocked_msg ) ) : ?>
        <div class="notice notice-warning sulayr-subscription-notice">
            <p><?php echo esc_html( $subscription_blocked_msg ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( ! empty( $has_active_subscription ) ) : ?>
        <div class="sulayr-subscription-status sulayr-subscription-active">
            <p><strong><?php esc_html_e( 'Suscripción activa', SULAYR_TEXTDOMAIN ); ?></strong></p>
            <p><?php esc_html_e( 'Tu cuota se cobra automáticamente cada mes. Puedes ver el historial en "Mis pagos".', SULAYR_TEXTDOMAIN ); ?></p>
        </div>
    <?php elseif ( ! empty( $can_activate_subscription ) ) : ?>
        <div class="sulayr-subscription-activate">
            <?php if ( ! empty( $user_is_directive ) ) : ?>
                <p><?php esc_html_e( 'Activa tu suscripción de natación para directiva (29,50 €/mes) para que se cobre automáticamente cada mes.', SULAYR_TEXTDOMAIN ); ?></p>
            <?php else : ?>
                <p><?php esc_html_e( 'Activa tu suscripción para que la cuota se cobre automáticamente cada mes.', SULAYR_TEXTDOMAIN ); ?></p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url( isset( $subscription_form_action ) ? $subscription_form_action : '' ); ?>">
                <?php wp_nonce_field( 'sulayr_activar_suscripcion', '_wpnonce' ); ?>
                <button type="submit" name="sulayr_activar_suscripcion" value="1" class="button button-primary">
                    <?php esc_html_e( 'Activar suscripción', SULAYR_TEXTDOMAIN ); ?>
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>
