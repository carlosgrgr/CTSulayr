<div style="margin: 1.5em 1.5em 0 0">
    <h1><?php esc_html_e('Diagnóstico Redsys', REDSYSPUR_DOMAIN); ?></h1>
    <p>
        <?php esc_html_e('Información sobre el funcionamiento de la Pasarela Unificada de Redsys para Woocommerce. Esta información es de gran utilidad a la hora de enviar un ticket de petición de soporte a tu entidad bancaria o a Redsys y se recomienda su inclusión dentro de la información de la incidencia.', REDSYSPUR_DOMAIN); ?>
    </p>

    <table class="widefat striped fixed" cellspacing="0" id="status">
        <thead>
            <tr>
                <th colspan="3">
                    <h2><?php esc_html_e('Estado de la conexión con Redsys', REDSYSPUR_DOMAIN); ?></h2>
                </th>
            </tr>
            <tr>
                <th colspan="3">
                    <?php esc_html_e('Estado de la conexión Host-to-Host con Redsys. Esta conexión es necesaria para toda la mensajería vía REST, usada en la integración inSite así como en la gestión de devoluciones, anulaciones y confirmaciones desde el Backoffice.', REDSYSPUR_DOMAIN); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Conexión Redsys:', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td style="color: <?php echo esc_attr($template_vars['connectionRedsysColor']); ?>;">
                    <span class="dashicons dashicons-<?php echo esc_attr($template_vars['connectionRedsysMark']); ?>"></span>
                    <?php echo esc_html($template_vars['connectionRedsys']); ?>
                </td>
            </tr>
        </tbody>
    </table>

    <p>
        <?php esc_html_e('Si la conexión con Redsys falla, es posible que tu servidor no disponga de los certificados necesarios para poder conectar con nuestros servidores. Accede a esta página de documentación para obtener más información', REDSYSPUR_DOMAIN); ?>
        <a href="https://sis-d.redsys.es/sis/CAs/comunicadoCertificado.html" target="_blank">
            ↗
        </a>
    </p>

    <br>

    <table class="widefat striped fixed" cellspacing="0" id="status">
        <thead>
            <tr>
                <th colspan="3">
                    <h2><?php esc_html_e('Información de la instalación', REDSYSPUR_DOMAIN); ?></h2>
                </th>
            </tr>
            <tr>
                <th colspan="3">
                    <?php esc_html_e('Información sobre las versiones de PHP y Woocommerce que utiliza tu servidor, así como otros datos útiles para la resolución de incidencias.', REDSYSPUR_DOMAIN); ?>
                </th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><?php esc_html_e('Version del servidor:', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td><?php echo esc_html($template_vars['serverVersion']); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Version PHP:', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td><?php echo esc_html($template_vars['phpVersion']); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Version del módulo Redsys:', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td><?php echo esc_html($template_vars['redsysVersion']); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Version Wordpress', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td><?php echo esc_html($template_vars['wordpressVersion']); ?></td>
            </tr>
            <tr>
                <td><?php esc_html_e('Version WooCommerce', REDSYSPUR_DOMAIN); ?></td>
                <td></td>
                <td><?php echo esc_html($template_vars['woocommerceVersion']); ?></td>
            </tr>
        </tbody>
    </table>
</div>

