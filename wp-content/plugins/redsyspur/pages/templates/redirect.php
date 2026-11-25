<form action="<?php echo esc_url($template_vars['env']); ?>" method="post" id="redsys_payment_form" name="redsys_payment_form">
    <input type="hidden" name="Ds_SignatureVersion" value="<?php echo esc_attr($template_vars['Ds_SignatureVersion']); ?>" />
    <input type="hidden" name="Ds_MerchantParameters" value="<?php echo esc_attr($template_vars['Ds_MerchantParameters']); ?>" />
    <input type="hidden" name="Ds_Signature" value="<?php echo esc_attr($template_vars['Ds_Signature']); ?>" />
</form>

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

