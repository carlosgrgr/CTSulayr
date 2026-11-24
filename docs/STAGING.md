# Entorno de staging (preproducción)

**Estado:** propuesto el 24 de noviembre de 2026. Todavía no está montado en Hostinger.

Staging es una copia de PROD, con datos reales, en la que se prueba un cambio justo antes de subirlo a PROD. "Staging" y "preproducción" significan lo mismo. Usamos "staging" porque es el término que usa WordPress (`WP_ENVIRONMENT_TYPE = 'staging'`).

Flujo de trabajo: **local → staging → PROD**. El código se sube a staging igual que a PROD y, si todo va bien allí, se repite la subida en PROD.

| Entorno | Club (blog 1) | Reservas (blog 2) |
|---|---|---|
| Local | `clubtriatlonsulayr.local.com` | `cnnazari.clubtriatlonsulayr.local.com` |
| Staging | `staging.clubtriatlonsulayr.com` | `cnnazari.staging.clubtriatlonsulayr.com` |
| PROD | `clubtriatlonsulayr.com` | `cnnazari.clubtriatlonsulayr.com` |

Archivos de esta guía:

- [`staging/wp-config-staging-sample.php`](staging/wp-config-staging-sample.php): qué cambiar en el `wp-config.php` de PROD para que sirva en staging.
- [`staging/preparar-bd-staging.sh`](staging/preparar-bd-staging.sh): convierte un volcado de PROD en un volcado para staging. Se ejecuta en local con WP-CLI.
- [`staging/sulayr-staging.php`](staging/sulayr-staging.php): mu-plugin para staging. Bloquea los correos, impide la indexación y muestra un aviso. No hace nada fuera de staging.

## Qué hay que tener en cuenta

Staging contiene datos reales de socios: DNI, teléfonos, recibos y documentos privados. Además, el código de staging hace lo mismo que el de PROD. Por eso, antes de abrir staging hay que asegurar todo esto:

| Riesgo | Cómo se evita |
|---|---|
| Correos reales a socios (altas, recibos, reservas) | El mu-plugin corta `wp_mail()` y deja el correo en `debug.log`. Además, WP Mail SMTP queda en «Do not send» (`WPMS_DO_NOT_SEND`). Si se quiere, el script anonimiza los emails de los usuarios. |
| Tareas diarias (`sulayr_members_daily`: recibos, monedero, correos; `sulayr_billing_daily` en reservas) | `DISABLE_WP_CRON`. Las tareas se lanzan a mano desde Socios → «Ejecutar ahora». |
| Cobros | Hoy no hay pasarela con cobro automático (Stripe se quitó). Aun así, hay que revisar WooCommerce → Ajustes → Pagos en staging y dejar desactivado todo lo que no sea transferencia o pago en mano. |
| Estadísticas de Analytics/GTM | El script borra los códigos de Scripts Manager en los dos sitios. |
| Que Google indexe staging | El script pone `blog_public = 0`. El mu-plugin lo fuerza y envía `X-Robots-Tag: noindex`. La contraseña de acceso también lo impide. |
| Datos personales visibles en internet (RGPD) | Contraseña de acceso a todo staging (paso 5). |
| Cuotas de servicios externos (Imagify, Instagram Feed, Akismet) | Desactivar Imagify en staging, o no optimizar imágenes allí. Instagram y Akismet pueden quedarse como están. |
| Confundir staging con PROD | Barra de administración con el aviso «STAGING» (mu-plugin). Salts nuevas, así que una sesión de PROD no sirve en staging. |

## Preparación única en Hostinger

Hay que comprobar antes que el plan permite otra base de datos y dos subdominios más, y que el disco aguanta otra copia de `wp-content/uploads`. La herramienta de staging de Hostinger no sirve porque no admite multisite.

1. **Subdominios.** En hPanel → Dominios → Subdominios, crea `staging` y `cnnazari.staging`, **los dos apuntando a la misma carpeta**, por ejemplo `public_html/staging`. Es un multisite, así que una sola instalación sirve a los dos. Si hPanel no deja crear `cnnazari.staging`, la alternativa es un subdominio comodín `*.staging` hacia esa misma carpeta.
2. **SSL.** Activa el certificado gratuito para cada subdominio. Un certificado `*.clubtriatlonsulayr.com` **no** cubre `cnnazari.staging.clubtriatlonsulayr.com`.
3. **Base de datos.** En hPanel → Bases de datos, crea una BD y un usuario nuevos solo para staging. Nunca se reutiliza la de PROD.
4. **Archivos.** En PROD, `domains/clubtriatlonsulayr.com/public_html` contiene el multisite y además otras carpetas que no son del club. Staging lleva solo el multisite.
   - Comprime desde el Administrador de archivos solo lo del multisite: `wp-admin`, `wp-includes`, `wp-content`, los `.php` de la raíz, `.htaccess` y `robots.txt`.
   - **Fuera de la copia:** `la-joya-escondida` (otra web, con su propio subdominio), `docs` y la propia carpeta `staging`. Si no excluyes `staging`, cada vez que refresques staging se copiaría dentro de sí misma.
   - Descomprime lo copiado en `public_html/staging`.
   - Si falta espacio, puedes copiar solo los últimos meses de `uploads`. Las imágenes que falten saldrán rotas en staging, pero no afecta a la lógica.
   - Comprueba que `wp-content/uploads/sulayr-documentos/.htaccess` ha llegado.
5. **Contraseña de acceso.** En hPanel → Avanzado → Proteger directorios con contraseña, protege `public_html/staging`. Así se pide usuario y contraseña antes de llegar a WordPress, en los dos subdominios.
6. **wp-config.php.** Edita el `wp-config.php` copiado siguiendo [`staging/wp-config-staging-sample.php`](staging/wp-config-staging-sample.php): BD de staging, salts nuevas, dominio de la red, `WP_ENVIRONMENT_TYPE`, `DISABLE_WP_CRON` y correo.
7. **mu-plugin.** Crea `public_html/staging/wp-content/mu-plugins/` y sube allí [`staging/sulayr-staging.php`](staging/sulayr-staging.php). Este archivo no se sube a PROD.

## Preparación única en local

El script usa una BD temporal para no tocar la BD local de trabajo. Para eso, el `wp-config.php` local (no está en git) tiene que poder leer el nombre de la BD y el dominio de variables de entorno. Cambia estas dos líneas:

```php
define( 'DB_NAME', getenv( 'SULAYR_DB_NAME' ) ?: 'nombre_de_tu_bd_local' );
define( 'DOMAIN_CURRENT_SITE', getenv( 'SULAYR_DOMAIN' ) ?: 'clubtriatlonsulayr.local.com' );
```

Y añade esta, antes de `require_once ABSPATH . 'wp-settings.php';`:

```php
if ( getenv( 'SULAYR_DB_NAME' ) && ! defined( 'DISABLE_WP_CRON' ) ) {
	define( 'DISABLE_WP_CRON', true );
}
```

Sin las variables, el sitio local funciona exactamente igual que ahora. El usuario de MySQL local necesita permiso para crear bases de datos.

## Copiar la BD de PROD a staging

Esto se hace la primera vez y cada vez que se quiera refrescar staging con datos actuales.

1. **Exportar PROD.** En hPanel → phpMyAdmin, entra en la BD del multisite del club, no en la de `la-joya-escondida`. Si las dos webs compartieran BD, exporta solo las tablas del prefijo del multisite (`wp_`). Después ve a Exportar → método personalizado → formato SQL. Desmarca `CREATE DATABASE / USE` y guárdalo, por ejemplo, como `prod.sql`. El script se niega a seguir si el volcado incluye esas sentencias.
2. **Convertirlo en local**, desde la raíz del repo:

   ```bash
   ANONIMIZAR=1 CONSERVAR_EMAILS="tu@email.com,otro@admin.com" BORRAR_TMP=1 \
     ./docs/staging/preparar-bd-staging.sh ~/Descargas/prod.sql ~/Descargas/staging.sql
   ```

   El script hace, en una BD temporal (`ctsulayr_staging_tmp`):
   - Importa el volcado.
   - Lanza `wp search-replace` sobre todas las tablas `wp_*`, también las de los plugins del club. Respeta los datos serializados y reemplaza:
     - `//cnnazari.clubtriatlonsulayr.com` → `//cnnazari.staging.clubtriatlonsulayr.com`
     - `//www.clubtriatlonsulayr.com` y `//clubtriatlonsulayr.com` → `//staging.clubtriatlonsulayr.com`
     - Lo mismo escapado en JSON (`\/\/…`).
     - Solo reemplaza lo que empieza por `//`, así que los emails tipo `info@clubtriatlonsulayr.com` no cambian.
   - Pone `blog_public = 0` y borra los códigos de Scripts Manager en cada sitio.
   - Con `ANONIMIZAR=1`, cambia el email de cada usuario por `usuarioID@staging.invalid`, salvo los de `CONSERVAR_EMAILS`. Pon ahí los emails de los administradores que vayan a entrar en staging. Los usuarios siguen pudiendo entrar con su nombre de usuario.
   - Cambia los dominios de la red en `wp_site` y `wp_blogs`.
   - Busca URLs de PROD que hayan quedado y avisa de cada tabla con restos. `wp search-replace` se salta las tablas sin clave primaria, pero todas las tablas de los plugins del club la tienen.
   - Exporta `staging.sql` y, con `BORRAR_TMP=1`, borra la BD temporal.

   Sin `ANONIMIZAR=1`, los emails se quedan como en PROD y la única protección es el bloqueo de correo.

3. **Importar en staging.** En phpMyAdmin, entra en la BD de **staging**, vacía sus tablas (Estructura → marcar todas → Eliminar) e importa `staging.sql`. Comprueba dos veces el nombre de la BD antes de vaciar.
4. **Borrar los volcados** del ordenador cuando termines, porque contienen datos personales.

Las comprobaciones de los pasos 1 y 2 se probaron en local el 24 de noviembre de 2026 con un multisite de prueba. Los datos serializados, el JSON, las tablas `wp_2_*`, la anonimización y el arranque con `cnnazari.staging…` funcionaron. Todavía no se ha probado con el volcado real de PROD.

## Comprobaciones después de cada importación

- [ ] `https://staging.clubtriatlonsulayr.com` pide la contraseña de Hostinger y después muestra el club.
- [ ] `https://cnnazari.staging.clubtriatlonsulayr.com` muestra reservas, y entrar desde el club lleva a reservas en staging, no en PROD.
- [ ] La barra de administración muestra «STAGING · los correos no se envían».
- [ ] Al pedir «He olvidado mi contraseña» con un email conservado, no llega ningún correo y en `wp-content/debug.log` aparece `[staging] Correo NO enviado`.
- [ ] WP Mail SMTP → Ajustes → Varios muestra «Do not send» activado por constante.
- [ ] Ajustes → Lectura → «Disuade a los motores de búsqueda» está marcado en los dos sitios.
- [ ] Scripts Manager está vacío en los dos sitios.
- [ ] WooCommerce → Ajustes → Pagos: no hay ninguna pasarela que cobre de verdad.
- [ ] El código fuente de una página no contiene `//clubtriatlonsulayr.com` en enlaces, imágenes ni CSS.

## Subir un cambio a staging

Se suben los archivos cambiados a `public_html/staging` igual que se hace en PROD, siguiendo el `despliegue-prod.md` de la spec si lo tiene. Si el cambio crea o modifica tablas, la migración se ejecuta en staging primero. Cuando todo funciona en staging, se repiten los mismos pasos en PROD.
