<iframe name="redsys_iframe_acs" id="redsys_iframe_acs" src=""
    target="_parent" referrerpolicy="origin"
    sandbox="allow-same-origin allow-scripts allow-top-navigation allow-forms"
    height="100%" width="100%" style="border: none; display: none;"></iframe>

<form name="redsysAcsForm" id="redsysAcsForm"
    action="<?php echo esc_url($template_vars['action']); ?>" method="POST"
    target="_parent" style="border: none;">
    <table name="dataTable" border="0" cellpadding="0">
        <input type="hidden" name="creq"
            value="<?php echo esc_attr($template_vars['creq']); ?>">
        <br>
        <p style="font-family: Arial; font-size: 16px; font-weight: bold; color: black; text-align: center;">
            <?php esc_html_e('Conectando con el emisor...', REDSYSPUR_DOMAIN); ?>
        </p>
    </table>
</form>

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

