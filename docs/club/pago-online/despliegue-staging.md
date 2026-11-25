# Despliegue en staging: pago online, partes B y C

Cómo subir con FileZilla a staging el pago de cuotas con tarjeta de [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](../SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) y probarlo allí con el TPV del club en el **entorno de pruebas de Redsys**:

- **Parte B:** pago desde Mis recibos con tarjeta guardada y cobro con la tarjeta guardada (sección 11).
- **Parte C:** cobro mensual, reintentos y avisos, y las pantallas «Aprobar cobro» y «Cobrar con la tarjeta guardada» (sección 12).

Escrito el 24 de noviembre de 2026. El código está en la rama `pago-online-parte-c`, que contiene la `pago-online-parte-b` y sale de `main`. A staging se sube siempre desde la rama `staging`, así que antes hay que mergear `pago-online-parte-c` en ella. Staging es una copia de PROD, y PROD está como `main`, así que la lista de abajo es la diferencia entre `main` y `staging` en el plugin. La rama `staging` solo añade sus documentos.

**Estado:** sin subir.

Fuera de este despliegue:

- **Parte A, la tienda con tarjeta.** El módulo oficial de Redsys (`wp-content/plugins/redsyspur/`) está en la rama, pero no se ha configurado ni probado, y falta el filtro de C29. No se sube.
- **El entorno real de Redsys.** En staging se usan siempre las credenciales de pruebas. «Pasar a Producción» en el portal de Redsys se hace después, cuando todo haya funcionado aquí.

Todas las rutas de los pasos 1 y 2 son relativas a `public_html/staging/wp-content/plugins/club-triatlon-sulayr/` en el servidor.

## Antes de empezar

1. **Cambios en la rama `staging`.** Haz commit de todo en `pago-online-parte-c`, mergéala en `staging` (`git checkout staging && git merge pago-online-parte-c`) y sube los archivos desde `staging`. El merge solo coincide en `AGENT.md`, en líneas distintas. Comprueba que `git diff --stat main staging -- wp-content/plugins/club-triatlon-sulayr` no lista más archivos que los de los pasos 1 y 2 (más `tests/`, que no se sube).
2. **Staging montado.** Las comprobaciones de [STAGING.md](../../STAGING.md) están hechas: los correos no salen, `DISABLE_WP_CRON` está activo y WooCommerce no tiene ninguna pasarela de cobro activa.
3. **Copia de la base de datos de staging** desde phpMyAdmin. Así se puede volver atrás sin tener que refrescar staging desde PROD.
4. En FileZilla, activa **Servidor → Forzar mostrar archivos ocultos**. El paso 3 sube un `.htaccess`.

## Paso 1. Subir lo nuevo

Nada de esto se usa hasta el paso 2, así que subirlo no cambia la web.

- La carpeta entera `src/Modules/Payments/`, con su subcarpeta `Redsys/`.

## Paso 2. Sustituir lo cambiado

Sube estos archivos en este orden. Mientras se suben, el área de socios puede dar error unos segundos.

1. `src/Modules/Members/Database/MembersSchema.php`
2. `src/Modules/Members/MemberConsents.php`
3. `src/Modules/Members/MemberFields.php`
4. `src/Modules/Members/MemberLog.php`
5. `src/Modules/Members/MemberSettings.php`
6. `src/Modules/Members/Receipts/Receipts.php`
7. `src/Modules/Members/Receipts/ReceiptsAccount.php`
8. `src/Modules/Members/Lifecycle/FeesAccount.php`
9. `src/Modules/Members/Lifecycle/MembersDailyTask.php`
10. `src/Modules/Members/Admin/MembersMenu.php`
11. `src/Modules/Members/Admin/ReceiptsPage.php`
12. `public/css/styles.css`
13. `src/App/App.php`, el último, porque es el que activa todo lo demás.

No se suben `tests/` ni `composer.json`. `vendor/` no cambia.

## Paso 3. Dejar entrar los avisos de Redsys

Staging pide la contraseña de Hostinger en todas sus direcciones, también en `/wp-json/sulayr/v1/redsys`, que responde `401` (comprobado el 24 de noviembre de 2026). Redsys no puede dar esa contraseña, así que su aviso no llegaría a la web: el socio pagaría, pero el recibo seguiría pendiente.

Por eso se sube una carpeta aparte que queda fuera de la contraseña y pasa el aviso a la ruta REST. La firma se comprueba igual.

- Sube la carpeta [`staging/redsys-notify/`](staging/redsys-notify/) de esta guía, con `index.php` y `.htaccess`, a **`public_html/staging/redsys-notify/`**. Va en la raíz de staging, no dentro del plugin.

Esta carpeta es solo para staging. En PROD no hay contraseña y Redsys avisa directamente a la ruta REST.

## Paso 4. Credenciales en el wp-config.php de staging

Edita `public_html/staging/wp-config.php` y añade esto antes de `/* That's all, stop editing! */`. Los valores salen del email de Redsys del 24 de noviembre de 2026:

```php
/* Pago online (SPEC-COBROS C29). TPV del club en el entorno de PRUEBAS de Redsys. */
define( 'SULAYR_REDSYS_MERCHANT_CODE', '186032504' );
define( 'SULAYR_REDSYS_TERMINAL', '001' );
define( 'SULAYR_REDSYS_SECRET_KEY', '<clave de firma del email>' );
define( 'SULAYR_REDSYS_ENVIRONMENT', 'test' );
define( 'SULAYR_REDSYS_NOTIFY_URL', 'https://staging.clubtriatlonsulayr.com/redsys-notify/' );
```

`SULAYR_REDSYS_ENVIRONMENT` se queda en `'test'`. Así, en staging, ninguna tarjeta real puede pagar.

## Paso 5. Primera carga

1. Entra en wp-admin de staging. Al cargar, el plugin crea las tablas `wp_sulayr_payment_cards` y `wp_sulayr_tpv_transactions` y sube `sulayr_members_db_version` a 6. Compruébalo en phpMyAdmin.
2. En Socios → Ajustes debe aparecer la pestaña «Pago con tarjeta».
3. Revisa `wp-content/debug.log` de staging: no debe haber errores nuevos.
4. Comprueba que los avisos de Redsys entran sin la contraseña. Desde una terminal, o pídeselo a Claude:

   ```bash
   curl -s -w ' %{http_code}\n' -X POST -d 'Ds_SignatureVersion=HMAC_SHA256_V1&Ds_MerchantParameters=e30%3D&Ds_Signature=x' \
     https://staging.clubtriatlonsulayr.com/redsys-notify/
   ```

   Lo correcto es `{"result":"firma_no_valida"} 400`. Si responde `404`, la carpeta no está en su sitio. Si responde `401`, el `.htaccess` del paso 3 no ha bastado en el servidor de Hostinger. Último recurso: quitar la contraseña de hPanel solo mientras duran las pruebas del paso 7 y volver a ponerla al terminar. Hay que tener en cuenta que, durante ese rato, staging deja ver datos reales de socios en internet (ver [STAGING.md](../../STAGING.md)).
5. Comprueba que el resto de staging sigue protegido: `https://staging.clubtriatlonsulayr.com/` debe seguir pidiendo la contraseña.

## Paso 6. Configurar

En Socios → Ajustes → «Pago con tarjeta», revisa el texto de la autorización de cargos y el «Día de cobro con tarjeta».

Nadie de la copia de PROD tiene como medio de cobro «Tarjeta (TPV)» ni una tarjeta guardada. Mientras no se cambie a mano un socio, ni «Aprobar cobro» ni la tarea diaria cobran a nadie.

## Paso 7. Pruebas

Usa un socio de prueba: créalo con el formulario de alta o en wp-admin, con un email de los que se conservan en staging. Ponle un recibo pendiente de importe pequeño. Paga con la tarjeta de pruebas de Redsys: `4548810000000003`, caducidad `12/49`, CVV `123`.

1. **Pago desde Mis recibos.** Marca la autorización y paga. En la ficha del socio y en Socios → Recibos, comprueba que el recibo sale pagado con «Tarjeta (TPV)», que la tarjeta está guardada y que la autorización de cargos está registrada. Si el recibo sigue pendiente, el aviso de Redsys no ha llegado: revisa el paso 5.4.
2. **Cobro con la tarjeta guardada.** Pon otro recibo pendiente al mismo socio. En su detalle, pulsa «Cobrar … con la tarjeta guardada». Debe salir pagado con «Tarjeta (TPV)» y autor 0.
3. **Venta denegada.** Con un tercer recibo, paga desde Mis recibos y en la autenticación 3DS elige que falle. El recibo debe quedar pendiente y la tarjeta guardada no debe cambiar.
4. **Cobro mensual.** Con recibos del mes en curso y el socio con medio «Tarjeta (TPV)», pon en Ajustes un día de cobro que ya haya pasado. Pulsa «Aprobar cobro de <mes>» en Socios → Recibos. En staging WP-Cron está desactivado, así que lanza después la tarea con Socios → «Ejecutar ahora». Debe cobrar y salir en la tabla del cobro.
5. **Correos.** Ninguno debe salir. Los avisos y resultados aparecen en `debug.log` como `[staging] Correo NO enviado`.
6. **Portal de Redsys.** En Consultas → Operaciones del portal de pruebas tienen que salir las mismas operaciones que en la web.

## Al terminar

- Deja el socio de prueba o bórralo desde wp-admin. Sus recibos y operaciones solo existen en staging.
- Apunta en la sección 11.2 o 12 de la spec la fecha y el resultado de las pruebas en staging.
- La carpeta `redsys-notify/` y las constantes `SULAYR_REDSYS_*` se quedan en staging para las siguientes pruebas.
