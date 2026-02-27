<?php
/**
 * Admin: Gestión de suscripciones – listado de deportistas y enlace de pago.
 *
 * @var \ClubTriatlonSulayr\Modules\Subscriptions\SubscriptionService $subscription_service
 * @var array $athletes
 * @var int   $generado_user_id
 * @var string $checkout_url
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap sulayr-subscriptions-admin">
    <h1><?php esc_html_e( 'Gestión de suscripciones', SULAYR_TEXTDOMAIN ); ?></h1>
    <?php if ( ! $subscription_service->is_available() ) : ?>
        <div class="notice notice-warning"><p><?php esc_html_e( 'Configura las claves de Stripe en "Configuración Stripe" para poder generar enlaces de pago.', SULAYR_TEXTDOMAIN ); ?></p></div>
    <?php endif; ?>
    <?php if ( $generado_user_id && $checkout_url ) : ?>
        <div class="notice notice-success">
            <p><strong><?php esc_html_e( 'Enlace de pago generado.', SULAYR_TEXTDOMAIN ); ?></strong></p>
            <p><?php esc_html_e( 'Copia este enlace y envíalo al deportista para que active su suscripción:', SULAYR_TEXTDOMAIN ); ?></p>
            <p><input type="text" readonly class="large-text code" value="<?php echo esc_url( $checkout_url ); ?>" onclick="this.select();" style="width:100%;max-width:600px;" /></p>
        </div>
    <?php endif; ?>
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e( 'Nombre', SULAYR_TEXTDOMAIN ); ?></th>
                <th><?php esc_html_e( 'Email', SULAYR_TEXTDOMAIN ); ?></th>
                <th><?php esc_html_e( 'Tarifa', SULAYR_TEXTDOMAIN ); ?></th>
                <th><?php esc_html_e( 'Suscripción', SULAYR_TEXTDOMAIN ); ?></th>
                <th><?php esc_html_e( 'Acciones', SULAYR_TEXTDOMAIN ); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ( $athletes as $a ) : ?>
                <tr>
                    <td><?php echo esc_html( $a['name'] ); ?></td>
                    <td><?php echo esc_html( $a['email'] ); ?></td>
                    <td><?php echo esc_html( $a['plan'] ?: '—' ); ?></td>
                    <td><?php echo $a['has_sub'] ? esc_html__( 'Activa', SULAYR_TEXTDOMAIN ) : esc_html__( 'Sin suscripción', SULAYR_TEXTDOMAIN ); ?></td>
                    <td>
                        <?php if ( ! $a['has_sub'] && $subscription_service->is_available() && ( $a['plan'] === 'basic' || $a['plan'] === 'premium' ) ) : ?>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field( 'sulayr_generar_enlace_' . $a['user_id'], '_wpnonce' ); ?>
                                <input type="hidden" name="user_id" value="<?php echo esc_attr( $a['user_id'] ); ?>" />
                                <button type="submit" name="sulayr_generar_enlace_suscripcion" value="1" class="button button-secondary"><?php esc_html_e( 'Generar enlace de pago', SULAYR_TEXTDOMAIN ); ?></button>
                            </form>
                        <?php else : ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ( empty( $athletes ) ) : ?>
        <p><?php esc_html_e( 'No hay deportistas activos con tarifa asignada.', SULAYR_TEXTDOMAIN ); ?></p>
    <?php endif; ?>
</div>
