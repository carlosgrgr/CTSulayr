<div class="wrap">
    <h1>
        <?php
        /* translators: %s: order ID */
        printf(
            esc_html__('Confirmación del pago para la orden #%s.', REDSYSPUR_DOMAIN),
            esc_html($template_vars['orderId'])
        );
        ?>
    </h1>

    <form method="POST">
        <?php echo $template_vars['message']; ?>

        <p>
            <?php
            /* translators: %s: redsys order ID */
            printf(
                esc_html__('Se va a proceder a realizar una confirmación por el importe total de la orden identificada en Redsys con el ID %s. Confirme los datos de la operación y el número de orden antes de proceder.', REDSYSPUR_DOMAIN),
                esc_html($template_vars['redsys_order'])
            );
            ?>
        </p>
        <p>
            <?php esc_html_e('Recuerde que siempre puede realizar una anulación de la operación en las siguientes 24h desde el Portal de Administración del TPV Virtual.', REDSYSPUR_DOMAIN); ?>
        </p>

        <input type="hidden" name="amount" value="<?php echo esc_attr($template_vars['importe_restante']); ?>">

        <br/>

        <?php echo $template_vars['botonConfirmar']; ?>
        <?php echo $template_vars['botonVolver']; ?>
    </form>
</div>

