# Despliegue en PROD — partes B, C y D (altas y ciclo de vida del socio)

Proceso para subir con FileZilla todo lo que queda de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](../SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md):

- **Parte B**: solicitudes de alta, aprobación, consentimientos y bienvenida (sección 18).
- **Parte C**: cambios, congelaciones, bajas, Mis cuotas y tarea diaria (sección 19).
- **Parte D**: recibos, PDF, monedero de la junta, exportación, listados y welcome pack (sección 20).
- **Identidad Sulayr** en las pantallas de Socios, el formulario de alta, Mis cuotas, el PDF y todos los emails de socios (B, C y D).

Escrito el 12 de noviembre de 2026. PROD tiene la parte A (`main`, último commit de código `892d6fcff`). Las tres partes están en la rama `altas-socio-parte-d`, que contiene la B y la C. Se suben juntas en una sola vez: la lista de abajo es el cambio neto entre `main` y esa rama, así que no hace falta seguir 18.5, 19.5 y 20.6 una tras otra (archivos que la B sube y la C borra, como `Join/JoinDailyTask.php`, ya no aparecen).

Todas las rutas son relativas a `wp-content/plugins/club-triatlon-sulayr/` en el servidor.

## Antes de empezar

1. **Código cerrado.** La rama `altas-socio-parte-d` tiene su commit (12 de noviembre de 2026). Mejor mergearla en `main` antes de subir y desplegar desde `main`, para saber exactamente qué hay en PROD.
2. **Copia de seguridad.** En hPanel → Copias de seguridad (archivos y base de datos). O bien descarga con FileZilla la carpeta `wp-content/plugins/club-triatlon-sulayr/` y exporta la base desde phpMyAdmin.
3. **Hora tranquila.** Mientras se sube, la web puede dar error unos segundos si un archivo llega antes que otro del que depende.
4. **PROD está como `main`.** En el servidor no deben existir `src/Modules/Members/Admin/MigrationPage.php` ni `src/Modules/Members/Migration/LegacyMigrator.php` (retirados en 16.6). Si existen, bórralos antes de seguir. `src/Modules/Members/Migration/LegacyFieldsSync.php` y `LegacyMapping.php` sí existen y se borran en el paso 5.
5. **Extensiones de PHP.** En wp-admin → Herramientas → Salud del sitio → Información → Servidor, comprueba que estén `dom`, `mbstring` y `gd` (el PDF las necesita; sin `gd` sale sin logo).

## Paso 1. Subir lo nuevo

Nada de esto se usa hasta el paso 4, así que subirlo no cambia la web.

Carpetas enteras:

- `vendor/dompdf/`
- `vendor/masterminds/`
- `vendor/sabberworm/`
- `assets/pdf-fonts/` (fuentes del PDF y sus licencias OFL)
- `src/Modules/Members/Join/`
- `src/Modules/Members/Lifecycle/`
- `src/Modules/Members/Receipts/`

Archivos sueltos:

- `public/js/join-form.js`
- `src/Modules/Members/Admin/BrandStyles.php`
- `src/Modules/Members/Admin/ListsPage.php`
- `src/Modules/Members/Admin/MemberActions.php`
- `src/Modules/Members/Admin/ReceiptsPage.php`
- `src/Modules/Members/Admin/RequestsPage.php`
- `src/Modules/Members/CoachList.php`
- `src/Modules/Members/MemberConsents.php`
- `src/Modules/Members/MemberDates.php`
- `src/Modules/Members/MemberSummary.php`
- `src/Modules/Members/MemberTasks.php`
- `src/Modules/Members/MemberTypes.php`
- `src/Modules/Members/ReservationsAccess.php`
- `src/Modules/Members/Subsidies.php`
- `src/Modules/Members/WelcomePack.php`

## Paso 2. Subir la carga de Composer

Después del paso 1, porque `vendor/composer/autoload_files.php` carga archivos de `vendor/sabberworm/`:

- `vendor/composer/` (carpeta entera, sobrescribiendo)
- `composer.json`
- `composer.lock`

## Paso 3. Sobrescribir lo que cambia

En este orden, con `src/App/App.php` al final (es el que empieza a usar todo lo nuevo):

- `public/css/styles.css`
- `templates/parts/content-athlete-profile.php`
- `src/Services/UserService.php`
- `src/Modules/Users/UserAdminList.php`
- `src/Modules/WooCommerce/WooCommerceIntegration.php`
- `src/Modules/Members/Database/MembersSchema.php`
- `src/Modules/Members/MemberFields.php`
- `src/Modules/Members/MemberLog.php`
- `src/Modules/Members/MemberSettings.php`
- `src/Modules/Members/StatusPeriods.php`
- `src/Modules/Members/Admin/MembersMenu.php`
- `src/App/App.php`

## Paso 4. Primera carga

Entra en wp-admin del club. La primera carga:

- sube el esquema de socios de la versión 1 a la 4 (tablas y columnas nuevas; no borra nada) y crea el periodo de tipo de socio de cada socio con su tipo actual;
- programa la tarea diaria `sulayr_members_daily` (00:15);
- pide regenerar los enlaces de Mi cuenta (aparece «Mis cuotas»).

Comprueba que el menú **Socios** tiene: Solicitudes, Nueva alta, Recibos, Tareas pendientes, Entrenador, Welcome pack, el catálogo, el monedero y Ajustes. Comprueba que cargan la portada, la tienda y Mi cuenta.

Si «Mis cuotas» da 404, guarda Ajustes → Enlaces permanentes sin cambiar nada.

## Paso 5. Borrar lo que ya no existe

Después del paso 4 (FileZilla no borra al subir):

- `src/Modules/Members/Migration/` (carpeta entera: `LegacyFieldsSync.php`, `LegacyMapping.php`)
- `src/Modules/Users/AthleteUpdate.php`
- `templates/email/` (carpeta entera)

Vuelve a cargar wp-admin y Mi cuenta para confirmar que nada los echaba de menos.

## Paso 6. Configurar en Socios → Ajustes

- **Datos del club**: email del club para avisos, emails del entrenador, enlace a la comunidad de WhatsApp, nombre, CIF, dirección y logo (sale en el PDF y en los emails de recibos y del entrenador).
- **Formulario de alta**: texto encima del formulario, mensaje tras enviar y límite por IP.
- **Consentimientos**: textos y versiones (los redacta el club).
- **Pasos**: de alta, de cambio y de baja.
- **Emails**: bienvenida, rechazo, aviso al club, resultado de solicitud, confirmación de baja, listado del entrenador y recibo mensual.
- **Recibos**: medios de pago, pie del recibo y textos de las líneas y de los movimientos del monedero. **Deja «Primer mes con recibos» vacío** hasta decidir desde qué mes se deja el Excel: mientras esté vacío no se genera ningún recibo ni se apunta la retribución de la junta. «Avisar por email del recibo mensual» sigue desactivado (D52).

En el catálogo, revisa el texto de bienvenida y los pasos de cada servicio.

## Paso 7. Comprobar

1. Socios → **«Ejecutar ahora»**: el resumen no aplica nada inesperado. La ficha de Diego Lardón muestra la baja programada para el 1 de enero de 2027.
2. **Usuarios → editar** un deportista: no salen tarifa, modalidad, natación, directiva ni estado; sale el recuadro «Socio: …».
3. Crea una página del club con el shortcode `[sulayr_solicitud_alta]` y enlázala donde corresponda.
4. **Prueba completa con un email propio**: envía una solicitud (llega el aviso al club), apruébala y revisa la bienvenida. En Socios → Solicitudes, comprueba que la IP no es la misma en todas (18.6).
5. Con ese socio de prueba: pide un cambio desde Mi cuenta → Mis cuotas y apruébalo o recházalo. Después, dalo de baja o anula su alta.
6. Marca «Gestor de socios» a quien corresponda en la ficha de usuario.
7. **Diseño de los emails**: en la bienvenida de la prueba del punto 4 el escudo debe verse sin pulsar «Mostrar imágenes» (va incrustado). Si no se ve, revisa en WP Mail SMTP que el mailer sea «Otro SMTP» (con un mailer por API el escudo se enlaza por URL y depende de que el cliente cargue imágenes).
8. **Retribución de la junta**: ponla en la ficha de Socios de cada miembro de la junta que la tenga.

## Paso 8. Empezar con los recibos

1. Decide el primer mes con recibos. **Diego Lardón** figura activo del 1 al 10 de noviembre de 2026 por la migración: si empiezas en noviembre, anula su recibo con motivo; si empiezas en diciembre, no hace falta (20.7).
2. Pon ese mes en Socios → Ajustes → Recibos → «Primer mes con recibos».
3. Socios → **Recibos** → «Generar recibos de …» (o espera al día configurado; la tarea diaria lo hace sola). Revisa varios recibos y su PDF, en especial uno de la junta con natación (29,50 €) y uno de la junta sin servicios (0 €, «no aplica»).
4. «Exportar a Excel (CSV)» y ábrelo en Excel.
5. El día 1 siguiente, sin pulsar nada: los recibos del mes se han generado y la retribución de la junta está en el monedero una sola vez.

## Si algo va mal

- **Error al cargar la web**: sube de nuevo la carpeta del plugin de la copia de seguridad (sobrescribiendo) y borra lo que no estaba en ella. Los cambios del esquema solo añaden tablas y columnas: con el código anterior no estorban, así que no hace falta restaurar la base salvo que se hayan escrito datos que haya que deshacer.
- **El PDF no se abre**: revisa en Salud del sitio que estén `dom` y `mbstring`, y que el servidor pueda escribir en su carpeta temporal (dompdf guarda ahí la caché de las fuentes).

## Después

Actualiza el día del despliegue en:

- la línea **Estado** y la sección 13 de la spec, y las secciones 18.5, 19.5 y 20.6 («desplegado el …»);
- `docs/FASES.md`;
- `AGENT.md` (sección «Club members module»);
- [pruebas.md](pruebas.md): marca las pruebas de PROD que hayas hecho.
