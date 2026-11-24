<?php
/**
 * wp-config.php de ejemplo para STAGING (staging.clubtriatlonsulayr.com).
 *
 * No es un wp-config completo para copiar tal cual. Parte del wp-config.php
 * de PROD (descárgalo de Hostinger) y cambia o añade solo estos bloques.
 * Todo lo demás (prefijo de tablas, constantes de cookies, límites de memoria,
 * etc.) se queda igual que en PROD, para que staging se comporte como PROD.
 *
 * Instrucciones completas: docs/STAGING.md.
 */

// ── 1. Base de datos de staging (la que crees en hPanel, NUNCA la de PROD) ──
define( 'DB_NAME', 'uXXXXXXXXX_staging' );
define( 'DB_USER', 'uXXXXXXXXX_staging' );
define( 'DB_PASSWORD', 'contraseña-de-la-bd-de-staging' );
define( 'DB_HOST', 'localhost' );
// $table_prefix = 'wp_';  ← el mismo que en PROD.

// ── 2. Claves y salts NUEVAS ──
// Genera un bloque nuevo en https://api.wordpress.org/secret-key/1.1/salt/
// Si reutilizas las de PROD, una cookie de sesión de PROD serviría en staging.
define( 'AUTH_KEY',         'pon-aqui-una-clave-nueva' );
define( 'SECURE_AUTH_KEY',  'pon-aqui-una-clave-nueva' );
define( 'LOGGED_IN_KEY',    'pon-aqui-una-clave-nueva' );
define( 'NONCE_KEY',        'pon-aqui-una-clave-nueva' );
define( 'AUTH_SALT',        'pon-aqui-una-clave-nueva' );
define( 'SECURE_AUTH_SALT', 'pon-aqui-una-clave-nueva' );
define( 'LOGGED_IN_SALT',   'pon-aqui-una-clave-nueva' );
define( 'NONCE_SALT',       'pon-aqui-una-clave-nueva' );

// ── 3. Multisite: solo cambia el dominio ──
define( 'WP_ALLOW_MULTISITE', true );
define( 'MULTISITE', true );
define( 'SUBDOMAIN_INSTALL', true );
define( 'DOMAIN_CURRENT_SITE', 'staging.clubtriatlonsulayr.com' );
define( 'PATH_CURRENT_SITE', '/' );
define( 'SITE_ID_CURRENT_SITE', 1 );
define( 'BLOG_ID_CURRENT_SITE', 1 );
// Si el wp-config de PROD define COOKIE_DOMAIN, cámbialo aquí a
// '.staging.clubtriatlonsulayr.com' o quítalo. Nunca lo dejes con el dominio de PROD.

// ── 4. Marcar el entorno ──
// wp_get_environment_type() devolverá 'staging'. El mu-plugin
// docs/staging/sulayr-staging.php solo actúa con este valor.
define( 'WP_ENVIRONMENT_TYPE', 'staging' );

// ── 5. Sin WP-Cron automático ──
// Así no se ejecutan solas la tarea diaria de socios (sulayr_members_daily:
// recibos, monedero, correos) ni la de reservas (sulayr_billing_daily).
// Para probarlas: Socios → «Ejecutar ahora», o abrir a mano
// https://staging.clubtriatlonsulayr.com/wp-cron.php
define( 'DISABLE_WP_CRON', true );

// ── 6. Correo: no enviar nada ──
// Primera barrera: WP Mail SMTP en modo «Do not send». Necesita WPMS_ON.
// Con WPMS_ON, WP Mail SMTP toma de constantes las opciones que estén
// definidas aquí y del panel las demás.
define( 'WPMS_ON', true );
define( 'WPMS_DO_NOT_SEND', true );
// Segunda barrera: el mu-plugin sulayr-staging.php corta wp_mail() y lo deja en el log.

// ── 7. Depuración: al log, nunca en pantalla ──
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
@ini_set( 'display_errors', '0' );

/* Lo que sigue («That's all, stop editing!», ABSPATH, require wp-settings.php)
   se queda exactamente como en PROD. */
