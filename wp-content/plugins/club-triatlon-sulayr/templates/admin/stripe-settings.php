<?php
/**
 * Template: Configuración de Stripe (API keys y webhook secret).
 *
 * @var string $stripe_publishable_key
 * @var string $stripe_secret_key
 * @var string $stripe_webhook_secret
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
$updated = isset( $_GET['updated'] ) && $_GET['updated'] === '1';
?>
<div class="wrap sulayr-stripe-settings">
    <h1><?php esc_html_e( 'Configuración de Stripe (Suscripciones)', SULAYR_TEXTDOMAIN ); ?></h1>
    <?php if ( $updated ) : ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Configuración guardada correctamente.', SULAYR_TEXTDOMAIN ); ?></p></div>
    <?php endif; ?>
    <p class="description"><?php esc_html_e( 'Introduce las credenciales de tu cuenta de Stripe para habilitar las suscripciones mensuales.', SULAYR_TEXTDOMAIN ); ?></p>
    <form method="post" action="">
        <?php wp_nonce_field( 'sulayr_stripe_settings', '_wpnonce' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row">
                    <label for="sulayr_stripe_publishable_key"><?php esc_html_e( 'Clave pública (Publishable key)', SULAYR_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="text" id="sulayr_stripe_publishable_key" name="sulayr_stripe_publishable_key" value="<?php echo esc_attr( $stripe_publishable_key ); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php esc_html_e( 'Empieza por pk_test_ o pk_live_', SULAYR_TEXTDOMAIN ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sulayr_stripe_secret_key"><?php esc_html_e( 'Clave secreta (Secret key)', SULAYR_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="password" id="sulayr_stripe_secret_key" name="sulayr_stripe_secret_key" value="<?php echo esc_attr( $stripe_secret_key ); ?>" class="regular-text" autocomplete="off" />
                    <p class="description"><?php esc_html_e( 'Empieza por sk_test_ o sk_live_. No la compartas.', SULAYR_TEXTDOMAIN ); ?></p>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="sulayr_stripe_webhook_secret"><?php esc_html_e( 'Webhook signing secret', SULAYR_TEXTDOMAIN ); ?></label>
                </th>
                <td>
                    <input type="password" id="sulayr_stripe_webhook_secret" name="sulayr_stripe_webhook_secret" value="<?php echo esc_attr( $stripe_webhook_secret ); ?>" class="large-text" autocomplete="off" />
                    <p class="description"><?php esc_html_e( 'Secret del endpoint de webhook (whsec_...). Configura en Stripe Dashboard → Developers → Webhooks.', SULAYR_TEXTDOMAIN ); ?></p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="submit" name="sulayr_stripe_save" class="button button-primary"><?php esc_html_e( 'Guardar configuración', SULAYR_TEXTDOMAIN ); ?></button>
        </p>
    </form>
</div>
