<div class="wrap">
    <h1>
        <?php
        /* translators: %s: order ID */
        printf(
            esc_html__('Anulación del pago para la orden #%s.', REDSYSPUR_DOMAIN),
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
                esc_html__('Se va a proceder a realizar una anulación del importe total de la orden identificada en Redsys con el ID %s. Confirme los datos de la operación y el número de orden antes de proceder.', REDSYSPUR_DOMAIN),
                esc_html($template_vars['redsys_order'])
            );
            ?>
        </p>

        <p>
            <span style="color:#fa7878; font-weight:bold;">( ! )</span>
            <?php esc_html_e('Esta operación NO SE PUEDE DESHACER. Revisa siempre los detalles del pedido y de los envíos que hayas podido realizar antes de anular la operación.', REDSYSPUR_DOMAIN); ?>
        </p>

        <input type="hidden" name="amount" value="<?php echo esc_attr($template_vars['importe_restante']); ?>">

        <br/>

        <?php echo $template_vars['botonConfirmar']; ?>
        <?php echo $template_vars['botonVolver']; ?>
    </form>
</div>

