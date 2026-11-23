<link rel="stylesheet" type="text/css" href="<?php echo esc_url($template_vars['staticPath']); ?>/css/estilos.css" />

<div id="div_paymentform">
    <div class="col-md-8" id="div_radio_reference" style="display: none;">
        <input type="radio" name="payment_type" value="reference"> 
        <p style="display: inline;"><?php echo esc_html($template_vars['referenceTitle']); ?></p> 
        <?php echo $template_vars['cardBrandLogo']; ?> 
        <a href="<?php echo esc_url($template_vars['deleteRefUrl']); ?>"><?php esc_html_e('Eliminar', REDSYSPUR_DOMAIN); ?></a>
    </div>

    <div class="col-md-8" id="div_radio_usual" style="display: none;">
        <input type="radio" name="payment_type" value="usual" checked> 
        <p style="display: inline;"><?php esc_html_e('Usar otra tarjeta', REDSYSPUR_DOMAIN); ?></p><br>
    </div>

    <div class="col-md-8" id="div_usual">
        <div id="insite-form-container" class="form-container"></div>
        <div id="insite-form-partes">
            <div class='input-wrapper' id="card-number"></div>
            <div class='input-wrapper' id="card-expiration"></div>
            <div class='input-wrapper' id="cvv"></div>
            <div id="boton"></div>
        </div>

        <div id="div_save_reference" style="width: auto; max-width: 350px; margin: 0 auto; display: none;">
            <input type="checkbox" id="save_reference" name="save_reference"/>
            <label for="save_reference"><?php esc_html_e('Guardar tarjeta para futuras compras', REDSYSPUR_DOMAIN); ?></label>
        </div>  
    </div>
</div>

<input type="hidden" id="token" value="">
<input type="hidden" id="errorCode" value="">
<input type="hidden" id="redsysOrder" name="redsysOrder" value="<?php echo esc_attr($template_vars['idCart']); ?>">

<input type="hidden" id="allowReference" value="<?php echo esc_attr($template_vars['allowReference']); ?>">
<input type="hidden" id="hasReference" value="<?php echo esc_attr($template_vars['hasReference']); ?>">
<input type="hidden" id="insiteUnificado" value="<?php echo esc_attr($template_vars['insiteUnificado']); ?>">
<input type="hidden" id="btnStyle" value="<?php echo esc_attr($template_vars['btnStyle']); ?>">
<input type="hidden" id="body_style" value="<?php echo esc_attr($template_vars['body_style']); ?>">
<input type="hidden" id="form_style" value="<?php echo esc_attr($template_vars['form_style']); ?>">
<input type="hidden" id="form_text_style" value="<?php echo esc_attr($template_vars['form_text_style']); ?>">
<input type="hidden" id="btnText" value="<?php echo esc_attr($template_vars['btnText']); ?>">
<input type="hidden" id="merchantCode" value="<?php echo esc_attr($template_vars['merchantCode']); ?>">
<input type="hidden" id="merchantTerminal" value="<?php echo esc_attr($template_vars['merchantTerminal']); ?>">
<input type="hidden" id="idCart" value="<?php echo esc_attr($template_vars['idCart']); ?>">
<input type="hidden" id="idioma_tpv" value="<?php echo esc_attr($template_vars['idioma_tpv']); ?>">
<input type="hidden" id="orderId" value="<?php echo esc_attr($template_vars['orderId']); ?>">
<input type="hidden" id="orderTotal" value="<?php echo esc_attr($template_vars['orderTotal']); ?>">
<input type="hidden" id="billingFirstName" value="<?php echo esc_attr($template_vars['billingFirstName']); ?>">
<input type="hidden" id="billingLastName" value="<?php echo esc_attr($template_vars['billingLastName']); ?>">
<input type="hidden" id="email" value="<?php echo esc_attr($template_vars['email']); ?>">
<input type="hidden" id="procUrl" value="<?php echo esc_url($template_vars['procUrl']); ?>">
<input type="hidden" id="errorText" value="<?php echo esc_attr($template_vars['errorText']); ?>">
<input type="hidden" id="errorCodeLiterales" value="<?php echo esc_attr($template_vars['errorCodeLiterales']); ?>">

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

