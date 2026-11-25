<input type="button" value="<?php esc_attr_e('Abrir formulario de pago', REDSYSPUR_DOMAIN); ?>" id="openModal" class="button alt wp-element-button">

<input type="hidden" id="url_modal" value="<?php echo esc_url($template_vars['url_modal']); ?>">
<input type="hidden" id="Ds_SignatureVersion" value="<?php echo esc_attr($template_vars['Ds_SignatureVersion']); ?>">
<input type="hidden" id="Ds_MerchantParameters" value="<?php echo esc_attr($template_vars['Ds_MerchantParameters']); ?>">
<input type="hidden" id="Ds_Signature" value="<?php echo esc_attr($template_vars['Ds_Signature']); ?>">
<input type="hidden" id="environment_modal" value="<?php echo esc_attr($template_vars['environment_modal']); ?>">

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

