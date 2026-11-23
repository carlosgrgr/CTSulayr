<style>
.wc-block-components-radio-control__input.change-parameters {
  display: inline-block !important;
}
.wc-block-components-radio-control__option.radio-redsys {
  padding-left: 3.5em !important;
}
</style>

<form id="redsys_payment_form" name="redsys_payment_form">
    <div id="div-redirect-options">
        <div id="div-options" class="wc-block-components-radio-control" style='margin-top:1%;'>
            <label for="ref" class="wc-block-components-radio-control__option radio-redsys">
                <input type="radio" class="wc-block-components-radio-control__input radio-redsys change-parameters" name="redsys_payment"
                    value="ref" />
                <div class="wc-block-components-radio-control__option-layout">
                    <div class="wc-block-components-radio-control__label-group">
                        <span class="wc-block-components-checkbox__label">
                            <?php esc_html_e('Usar la tarjeta guardada', REDSYSPUR_DOMAIN); ?>
                        </span>
                    </div>
                    <div class="wc-block-components-radio-control__description-group">
                        <span class="wc-block-components-radio-control__description">
                            <?php echo sprintf(
                                esc_html__('%1$s que termina en %2$s', REDSYSPUR_DOMAIN),
                                esc_html($template_vars['brand']),
                                '<b>' . esc_html($template_vars['maskedCard']) . '</b>'
                            ); ?>
                        </span>
                        <a href="<?php echo esc_url($template_vars['deleteRefUrl']); ?>">
                            <?php esc_html_e('Eliminar', REDSYSPUR_DOMAIN); ?>
                        </a>
                    </div>
                </div>
            </label>

            <label for="new" class="wc-block-components-radio-control__option radio-redsys">
                <input type="radio" class="wc-block-components-radio-control__input radio-redsys change-parameters" name="redsys_payment"
                    value="new" checked="checked" />
                <div class="wc-block-components-radio-control__option-layout">
                    <div class="wc-block-components-radio-control__label-group">
                        <span class="wc-block-components-checkbox__label">
                            <?php esc_html_e('Registrar una nueva tarjeta', REDSYSPUR_DOMAIN); ?>
                        </span>
                    </div>
                    <div class="wc-block-components-radio-control__description-group">
                        <span class="wc-block-components-radio-control__description">
                            <?php esc_html_e('Al guardar tu tarjeta, esta se almacenará en formato token en el servidor del comercio de manera 100% segura. Tu antigua tarjeta se sustituirá por la nueva.', REDSYSPUR_DOMAIN); ?>
                        </span>
                    </div>
                </div>
            </label>
        </div>

        <div id="div-noref">
            <div class="wc-block-components-checkbox">
                <label for="check_guardar">
                    <input type="checkbox" class="wc-block-components-checkbox__input change-parameters" id="check_guardar"
                        name="check_guardar" />
                    <svg class="wc-block-components-checkbox__mark" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        viewBox="0 0 24 20">
                        <path d="M9 16.2L4.8 12l-1.4 1.4L9 19 21 7l-1.4-1.4L9 16.2z"></path>
                    </svg>
                    <span class="wc-block-components-checkbox__label">
                        <?php esc_html_e('Guardar la tarjeta para pagar más rápido la próxima vez', REDSYSPUR_DOMAIN); ?>
                    </span>
                </label>
                <div style="margin-top: 2%;">
                    <span style='font-style:italic; font-size: 0.75em'>
                        <?php esc_html_e('Al guardar tu tarjeta, esta se almacenará en formato token en el servidor del comercio de manera 100% segura.', REDSYSPUR_DOMAIN); ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</form>

<input type="hidden" id="maskedCard" value="<?php echo esc_attr($template_vars['maskedCard']); ?>">
<input type="hidden" id="sessionVarsUrl" value="<?php echo esc_url($template_vars['sessionVarsUrl']); ?>">

<script src="<?php echo esc_url($template_vars['js']); ?>"></script>

