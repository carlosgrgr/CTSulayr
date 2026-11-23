<p style="font-family: Arial; font-size: 16px; font-weight: bold; color: black; text-align: center;">
    <?php esc_html_e('Procesando operación...', REDSYSPUR_DOMAIN); ?>
</p>

<form id="3dsmethod-form" action="<?php echo esc_url($template_vars['threeDSMethodURL']); ?>" method="POST">
    <input type="hidden" name="threeDSMethodData" value="<?php echo esc_attr($template_vars['threeDSMethodData']); ?>"> 
</form>

<iframe id="3dsmethod-iframe" name="3dsmethod" style="display:none;"></iframe>

<input type="hidden" id="redirect_url" value="<?php echo esc_url($template_vars['redirect_url']); ?>">

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

