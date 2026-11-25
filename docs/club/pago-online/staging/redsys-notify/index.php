<?php
/**
 * SOLO STAGING. Entrada sin contraseña para los avisos de Redsys (SPEC-COBROS §5.1, paso 2).
 *
 * Staging está protegido con la contraseña de Hostinger, que también bloquea
 * /wp-json/sulayr/v1/redsys, así que Redsys no puede avisar a la web. Esta
 * carpeta se sube a public_html/staging/redsys-notify/ con su .htaccess, que
 * la deja fuera de la contraseña, y en el wp-config.php de staging:
 *
 *     define( 'SULAYR_REDSYS_NOTIFY_URL', 'https://staging.clubtriatlonsulayr.com/redsys-notify/' );
 *
 * Solo acepta POST y pasa la petición a la ruta REST del plugin, que comprueba
 * la firma como siempre. No se sube nunca a PROD: allí no hay contraseña y la
 * ruta REST se usa directamente.
 */

if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
    http_response_code( 405 );
    header( 'Allow: POST' );
    exit;
}

$_SERVER['REQUEST_URI'] = '/wp-json/sulayr/v1/redsys';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
unset( $_SERVER['PATH_INFO'] );

require dirname( __DIR__ ) . '/index.php';
