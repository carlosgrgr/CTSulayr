<div style="margin-top: 20px; border-top: 1px solid #eee; padding-top: 20px;">
    <iframe id="ifrRedsysQR" name="ifrRedsysQR" frameborder="no" style="display: none;"></iframe>
    <form action="<?php echo esc_attr($template_vars['env']); ?>" method="post" id="frmRedsysPayment" name="frmRedsysPayment" target="ifrRedsysQR">
        <input type="hidden" name="Ds_SignatureVersion" value="<?php echo esc_attr($template_vars['Ds_SignatureVersion']); ?>" />
        <input type="hidden" name="Ds_MerchantParameters" value="<?php echo esc_attr($template_vars['Ds_MerchantParameters']); ?>" />
        <input type="hidden" name="Ds_Signature" value="<?php echo esc_attr($template_vars['Ds_Signature']); ?>" />
    </form>

    <div style="margin-bottom: 24px; display: block; clear: both;">
        <label for="check_qr" style="display: flex; align-items: center; margin: 0; cursor: pointer;">
            <input type="checkbox" id="check_qr" name="check_qr" style="width: 20px; height: 20px; margin: 0 12px 0 0; flex-shrink: 0;" />
            <span style="font-weight: 600; font-size: 16px; color: #333;">
                <?php esc_html_e('Pagar con el móvil', REDSYSPUR_DOMAIN); ?>
            </span>
        </label>
        <div style="margin-left: 32px; margin-top: 4px; font-size: 14px; color: #666; line-height: 1.4;">
            <?php esc_html_e('Deberá escanear un código QR que permitirá completar el pago desde el teléfono móvil.', REDSYSPUR_DOMAIN); ?>
        </div>
    </div>
</div>

<input type="hidden" id="orderId" value="<?php echo esc_attr($template_vars['orderId']); ?>">
<input type="hidden" id="backFromQRUrl" value="<?php echo esc_url($template_vars['backFromQRUrl']); ?>">
<input type="hidden" id="sessionVarsUrl" value="<?php echo esc_url($template_vars['sessionVarsUrl']); ?>">

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

