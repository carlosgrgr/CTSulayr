# SPEC — Protección de datos (RGPD y LOPDGDD)

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr` (web del club, blog 1), con efectos en `sulayr-reservas` (blog 2)
**Estado:** En definición desde el 17 de noviembre de 2026. Alcance y forma de trabajo decididos con el club (L1 a L9). Las decisiones L10 a L27 son propuestas pendientes de confirmar. P2 hecha en parte dentro del pago online (rama `pago-online-parte-b`, 17 de noviembre de 2026, sección 7); P1, P3 y P4 sin empezar.
**Fecha:** 17 de noviembre de 2026

Esta spec es la prioridad 5 de [PRIORIDADES.md](../PRIORIDADES.md). Cubre dos cosas:

1. Lo que el pago online ([SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md)) exige en protección de datos: la autorización de cargos recurrentes (C12), la información al socio y los cambios en la política de privacidad.
2. La conservación real de los datos. Hoy el plazo de antiguos socios solo avisa (D36 de la spec de altas). Esta spec define cómo se anonimizan y los plazos que faltan.

Además, enumera los documentos internos que el RGPD exige al club. Se preparan como borradores (sección 8).

Los textos legales de esta spec son **borradores**. Los revisa y aprueba la junta, que puede pasarlos a un asesor. El código no da por buena ninguna cláusula: los textos se guardan en ajustes editables y con versión.

---

## 1. Situación actual

### 1.1. Lo que ya existe

- **Política de privacidad** (página 2817) y **Política de cookies** (página 2818) en el sitio del club. Las dos están publicadas y llevan fecha de 11 de noviembre de 2026. La política de privacidad nombra al responsable (CIF G02642114), las finalidades y sus bases legales, los plazos, los destinatarios, los encargados, las transferencias, los menores y los derechos.
- **Banner de cookies** con CookieYes (`cookie-law-info`). Los códigos de Google Analytics van en `sulayr-scripts-manager`.
- **Consentimientos del alta** (D10, parte B de la spec de altas, en PROD desde el 12 de noviembre de 2026): privacidad (obligatorio), normas del club (obligatorio) e imagen (opcional, revocable en Mi cuenta). Se guardan en `sulayr_member_consents` con tipo, versión, aceptado, quién (socio o tutor), autor y fecha. Textos y versiones en Socios → Ajustes → Consentimientos (`MemberSettings`, claves `consent_*_text` y `consent_*_version`).
- **Conservación** (Socios → Ajustes → Conservación de datos):
  - Las solicitudes rechazadas (30 días) y las pendientes sin tocar (60 días) pierden los datos personales (D16, `JoinDailyTask`).
  - Los antiguos socios con más de 6 años de baja salen en un aviso del listado de Socios (`&conservacion=1`). **No se borra nada** (D36).
- **Reservas**: al retirar a un nadador, el historial guarda solo nombre, apellidos, teléfono y email, sin DNI (SPEC-RESERVAS).

### 1.2. Lo que falta

- La política de privacidad todavía habla de «datos bancarios para el cobro de cuotas» y nombra a SportEasy para cobrar. Con el TPV, el club guarda una referencia de tarjeta y cobra sin que el socio esté delante. Hay que contarlo y pedir la autorización.
- Nadie anonimiza a los antiguos socios cuando pasa el plazo.
- No hay plazos para los pedidos de la tienda, las cuentas que nunca fueron de socio, las transacciones del TPV ni el registro de cambios del socio (D58).
- No hay registro de actividades de tratamiento, inventario de encargos ni procedimiento ante brechas.

### 1.3. Fuera de alcance

Decidido con el club el 17 de noviembre de 2026 (L2):

- El sitio de reservas de CN Nazarí (página de privacidad, banner y responsable). Sí se anonimizan allí los datos de los socios del club (L15). El registro de actividades lo nombra como tratamiento (sección 8).
- Exportación y borrado a petición del interesado desde las herramientas de WordPress. Mientras tanto, los derechos se atienden a mano por email (sección 8.4). La anonimización de esta spec (sección 5) sirve también para atender una petición de supresión.
- Datos de salud: el club no los pide (D8 de la spec de altas).

---

## 2. Marco legal en breve

Esta sección es solo orientativa y no es asesoramiento legal.

- **RGPD** (Reglamento UE 2016/679) y **LOPDGDD** (Ley Orgánica 3/2018).
- **Base legal** del cobro de cuotas: ejecución de la relación asociativa (art. 6.1.b RGPD). No hace falta un consentimiento RGPD para cobrar, pero las normas de las marcas de tarjetas sí exigen que el titular **autorice** los cargos recurrentes antes del primero. Por eso la casilla de C12 es una autorización de cargo y no un consentimiento de datos, aunque se guarde en la misma tabla.
- **Información en dos capas** (art. 11 LOPDGDD): un resumen junto al formulario y un enlace a la política completa.
- **Conservación**: los documentos contables se guardan 6 años (art. 30 del Código de Comercio, que se aplica al club por su obligación de llevar contabilidad). Los fiscales, 4 años (art. 66 de la Ley General Tributaria). Pasados los plazos, los datos se suprimen o se anonimizan (art. 5.1.e RGPD). Un dato anonimizado ya no es un dato personal.
- **Menores**: hasta los 14 años da el consentimiento el titular de la patria potestad (art. 7 LOPDGDD). La web pide tutor a los menores de 18 (spec de altas) y lo mantiene.
- **Tarjetas**: la web no ve ni guarda el número completo (C10). Solo guarda la referencia de Redsys, la tarjeta enmascarada y la caducidad.

---

## 3. Decisiones

### Alcance y forma de trabajo

Decididas con el club el 17 de noviembre de 2026.

| # | Decisión |
|---|---|
| L1 | La prioridad 5 cubre el pago online y la conservación real. |
| L2 | Quedan fuera el sitio de reservas (salvo la anonimización de socios) y las herramientas de exportar y borrar de WordPress. |
| L3 | Los textos legales los redacta Claude como borradores en esta spec. Los revisa y aprueba la junta. |
| L4 | Los documentos internos (registro de actividades, encargos y brechas) se preparan como borradores con la plantilla Word de Sulayr en Drive. La spec solo los enumera (sección 8). |

### Conservación

Decididas con el club el 17 de noviembre de 2026.

| # | Decisión |
|---|---|
| L5 | Cuando pasa el plazo, el antiguo socio se **anonimiza**: no se borra. Se quitan los datos que le identifican y quedan las filas con importes, fechas y estados. Así no cambian los totales, ni la facturación con CN Nazarí, ni el histórico. |
| L6 | La anonimización la lanza un **gestor con un botón**, desde el aviso de conservación que ya existe. No se hace sola. |
| L7 | También se anonimizan los resultados del ranking interno y las reservas de natación de ese socio. |
| L8 | Se fijan plazos para los pedidos de la tienda, las tarjetas y transacciones del TPV, el registro de cambios y las cuentas que nunca fueron de socio. |
| L9 | Al anonimizar, el registro de cambios del socio (D58) pierde los valores personales. Es la única excepción a «no se puede editar ni borrar». |

### Pago online (propuestas)

| # | Decisión |
|---|---|
| L10 | La autorización de cargos recurrentes (C12) se guarda en `sulayr_member_consents` con el tipo `cargos_recurrentes`. Su texto y su versión van en Socios → Ajustes → Consentimientos, como los demás. El borrador está en la sección 4.1. |
| L11 | La casilla aparece **siempre que se vaya a guardar una tarjeta**: con «Guardar tarjeta», con «Cambiar tarjeta» y con «Pagar con tarjeta» desde Mis recibos, porque ese pago también la guarda (C11). Sin la casilla marcada no se manda al socio a Redsys. |
| L12 | Junto a la casilla va la primera capa de información (sección 4.2), con el enlace a la política de privacidad. |
| L13 | «Quitar tarjeta» guarda una fila de revocación (`accepted = 0`) de `cargos_recurrentes`. Guardar otra tarjeta guarda una aceptación nueva con la versión vigente. |
| L14 | La política de privacidad se actualiza con los cambios de la sección 4.3 **antes** de activar el pago de cuotas con tarjeta (parte B del pago online). Sube a la versión 2 (`consent_privacy_version`) para las altas nuevas. A los socios actuales se les avisa por email con un resumen de los cambios. No hace falta que vuelvan a aceptar, porque la base es la relación asociativa. |

### Conservación (propuestas)

| # | Decisión |
|---|---|
| L15 | **Cuándo se puede anonimizar.** El plazo (6 años, ajustable) cuenta desde la **más tardía** de estas fechas: la baja efectiva, el último recibo, el último pago y el último pedido de la tienda. Así no se pierde un documento contable dentro de su plazo. |
| L16 | **Bloqueos.** No se puede anonimizar a un socio que tenga un recibo pendiente o parcial, una solicitud abierta, una transacción del TPV `enviada` o un estado distinto de `baja`. El botón explica el motivo. |
| L17 | **Qué queda.** El ID de usuario y el número de socio. Ninguno de los dos identifica a nadie sin el resto de los datos, y así no se rompen las referencias. Si la persona vuelve al club, entra como socio nuevo con otro número: la detección de reingresos por DNI ya no la encuentra. |
| L18 | **Tarjetas.** En cuanto una tarjeta deja de estar activa (`sustituida`, `quitada`, `caducada` o `borrada`), se vacían su referencia y su `Ds_Merchant_Cof_Txnid`. Amplía C14, que solo lo hacía en la baja. La tarjeta enmascarada y la caducidad se borran al anonimizar. |
| L19 | **Transacciones del TPV.** A los 18 meses de su fecha se vacía la respuesta de Redsys (JSON), porque ese es el plazo máximo de una reclamación de tarjeta. La fila se queda (importe, `Ds_Order`, estado, código de autorización) y se anonimiza con el socio. |
| L20 | **Pedidos de la tienda.** En WooCommerce → Ajustes → Cuentas y privacidad: los pedidos pendientes y fallidos van a la papelera a los 3 meses, los cancelados al año y los completados se anonimizan a los 6 años. Los reembolsados también se anonimizan a los 6 años, si la versión de WooCommerce lo permite. Los pedidos que se quedan en «procesando» no se anonimizan: el club los pasa a «completado» al entregarlos. |
| L21 | **Cuentas sin socio.** Las cuentas que solo tienen el rol `customer` y llevan 3 años sin actividad se borran con el ajuste de WooCommerce «Retener cuentas inactivas». Sus pedidos se conservan con sus propios datos de facturación. El plugin del club excluye de esa limpieza a cualquier usuario con el rol `athlete` o un rol de gestor (filtro `woocommerce_delete_inactive_account_roles` o equivalente), porque en local hay al menos un usuario con `customer` y `athlete` a la vez. |
| L22 | Los consentimientos se conservan mientras el socio exista y, después, anonimizados: sin nada que los una a una persona. Sirven de prueba ante una reclamación mientras pueda haberla (3 años, art. 78 LOPDGDD), y el plazo de 6 años ya lo cubre. |

### Pantalla y registro (propuestas)

| # | Decisión |
|---|---|
| L23 | El aviso de conservación pasa a ser una lista con un botón «Anonimizar» por socio y un botón «Anonimizar todos los que se puedan». Ambos piden confirmación escribiendo «ANONIMIZAR». |
| L24 | Solo puede anonimizar quien tenga una capacidad nueva, `sulayr_anonymize_members`. Por defecto la tienen los administradores. |
| L25 | Cada anonimización deja una entrada en el registro del socio, con área `privacidad`, autor y fecha. No guarda ningún valor personal. Además, va un email al club con el número de socio y la fecha. |
| L26 | Antes de la primera anonimización en PROD se hace una copia de seguridad. La operación no se puede deshacer. |
| L27 | Un socio anonimizado sale en el listado de Socios como «Socio n.º N (anonimizado)» y solo con el filtro de antiguos socios. |

---

## 4. Pago online

### 4.1. Autorización de cargos recurrentes (borrador del texto de C12)

Casilla obligatoria y sin marcar:

> Autorizo al Club Triatlón Sulayr a guardar la referencia de esta tarjeta en su TPV virtual (Redsys) y a cargar en ella, sin que yo tenga que hacer nada, el importe pendiente de cada recibo mensual de mis cuotas. El cargo se hace a partir del día {dia_cobro} de cada mes, después de avisarme por email con el importe y el desglose. Si el cargo falla, se reintenta hasta dos veces en el mismo mes. Puedo retirar esta autorización cuando quiera con «Quitar tarjeta» en Mi cuenta → Mis recibos. Retirarla no anula las cuotas que ya deba.

Si es menor, la casilla la marca el tutor y el texto empieza por «Como tutor de {nombre}, autorizo…» (D6 y D10). `{dia_cobro}` sale del ajuste «día de cobro» del pago online (sección 4.3 de su spec).

Versión inicial: `1`. Cada cambio del texto sube la versión. Las autorizaciones ya dadas siguen valiendo con su versión.

### 4.2. Primera capa de información (borrador)

Bajo la casilla, en letra pequeña:

> **Protección de datos.** Responsable: Club Triatlón Sulayr. Finalidad: cobrar tus cuotas con la tarjeta que elijas. Base: tu relación con el club como socio. El club no ve ni guarda el número de tu tarjeta. Solo guarda la referencia que da Redsys, los últimos dígitos y la caducidad. Destinatarios: la entidad bancaria del TPV y Redsys. Derechos: acceso, rectificación, supresión y los demás, en clubtriatlonsulayr@gmail.com. Más información en la [política de privacidad]({url_privacidad}).

### 4.3. Cambios en la política de privacidad (borrador)

En la página 2817:

1. **Sección 2, datos económicos.** Cambiar «datos bancarios para el cobro de cuotas» por: «datos de pago: si pagas con tarjeta, la referencia que nos da Redsys, los últimos dígitos y la caducidad de la tarjeta (nunca el número completo), y el historial de recibos, pagos y cobros».
2. **Sección 3, finalidades.** Añadir una fila: «Cobrar las cuotas mensuales con la tarjeta que has guardado, sin que tengas que hacer nada cada mes, después de avisarte por email. | Ejecución de la relación asociativa (art. 6.1.b RGPD), con tu autorización previa de los cargos recurrentes, que puedes retirar en cualquier momento».
3. **Sección 4, conservación.** Añadir: «La referencia de la tarjeta se borra en cuanto la quitas o la cambias, y al darte de baja. Los recibos, los pagos y los cobros con tarjeta se conservan 6 años desde el último movimiento, por obligación contable. Pasado ese plazo, el club anonimiza tus datos: quedan solo importes y fechas, sin nada que te identifique».
4. **Sección 5, destinatarios.** Cambiar «Entidades bancarias y pasarelas de pago» por: «La entidad bancaria del TPV virtual del club y Redsys (Servicios de Procesamiento), para cobrar con tarjeta las cuotas y los pedidos de la tienda».
5. **Sección 5, encargados.** Quitar «y cobro de cuotas» de SportEasy al terminar el piloto (parte D del pago online, C26). Si el club deja de usar SportEasy del todo, se quita la línea entera.
6. Fecha de «Última actualización».

Falta confirmar con el contrato del TPV si el banco trata los datos como responsable propio o como encargado del club (sección 9). Según la respuesta, el punto 4 va en «destinatarios» o en «encargados».

### 4.4. Emails

El email «Tu tarjeta se ha guardado» no existe en C23. Se propone no crearlo: el aviso en pantalla y la tarjeta en «Mi tarjeta» bastan. Los emails de cobro de C23 llevan al pie una línea con el enlace a la política y a Mis recibos para quitar la tarjeta.

---

## 5. Anonimización de un antiguo socio

### 5.1. Qué se hace

En una sola operación por socio y dentro de una transacción de base de datos donde sea posible:

| Dónde | Qué se hace |
|---|---|
| Usuario (`wp_users`) | `user_login` = `anonimo-{ID}`, `user_email` = `anonimo-{ID}@invalid.invalid`, `display_name` y `user_nicename` = «Socio {número}» o `anonimo-{ID}`, `user_url` vacío, contraseña aleatoria. Se quitan todos los roles en los dos sitios. Se cierran sus sesiones. |
| Metas de usuario | Se borran nombre, apellidos, DNI, teléfono, fecha de nacimiento, foto (y el adjunto con su archivo), tutor (`sulayr_member_guardian`), talla, observaciones, datos de facturación y envío de WooCommerce y cualquier meta `sulayr_*` con datos personales. Quedan el número de socio, el tipo, la fecha de baja y la marca nueva `sulayr_anonymized_at`. |
| `sulayr_member_requests` | `data` (JSON) se reduce a lo que deja ya D16 (tipo, servicios, menor sí o no). La IP y el motivo se vacían. |
| `sulayr_member_log` | `old_value` y `new_value` se sustituyen por `[anonimizado]` en los campos personales. Quedan área, campo, autor y fecha. Las filas de servicios, estados e importes no se tocan (L9). |
| `sulayr_member_consents` | No se toca: no guarda datos personales, solo `user_id`, tipo, versión y fecha. |
| Recibos, líneas y pagos | No se tocan los importes. Las notas libres (`note`) se vacían si contienen texto. El PDF del recibo, que se genera al momento (D51), sale con «Socio n.º N (anonimizado)». |
| Servicios, periodos, subvenciones, tipos, tareas y welcome pack | No se tocan, salvo los campos `reason` y `label` libres, que se vacían. |
| Monedero (`WalletPostType`) | Se vacían los conceptos o notas libres de sus movimientos. Se quedan los importes. |
| Documentos personales (`DocumentPostType`) | Los documentos cuyo único destinatario es el socio se borran con su archivo. En los demás, el socio se quita de la lista de destinatarios. |
| Tarjetas (`sulayr_payment_cards`) | Se vacían la tarjeta enmascarada y la caducidad. La referencia ya estaba vacía (L18). |
| Transacciones del TPV | Se vacía la respuesta (JSON) si quedaba algo. |
| Ranking (`sulayr_ranking_results`, `_log`) | Los resultados se quedan con su `user_id`. En wp-admin salen como «Socio n.º N (anonimizado)». En el front ya no salen (R4). Los valores de nombre del registro del ranking, si los hay, se sustituyen. |
| Pedidos de WooCommerce | Los pedidos del socio de más de 6 años se anonimizan con el borrador de datos de WooCommerce. Los más recientes bloquean la operación (L15). |
| Reservas (blog 2) | En `sulayr_swim_swimmers`: nombre «Antiguo», apellidos «socio», email, teléfono, DNI, foto y token vacíos, inactivo. En `sulayr_swim_bookings`: nombre y apellidos de la misma forma, y teléfono y email vacíos. En `sulayr_billing_users`: nombre y apellidos de la misma forma. Los recuentos y la facturación con CN Nazarí no cambian. |

### 5.2. Pantalla

En Socios → listado → «antiguos socios fuera de plazo» (`&conservacion=1`):

- Columnas: número, fecha de baja, fecha desde la que cuenta el plazo (L15), estado («Se puede anonimizar» o el motivo del bloqueo, L16).
- Botón «Anonimizar» por fila y «Anonimizar todos los que se puedan» arriba. Confirmación escribiendo «ANONIMIZAR» (L23).
- Al terminar, un aviso con cuántos se anonimizaron y cuántos se saltaron, y el motivo.

El mensaje actual («El sistema no borra nada») cambia a: «{n} antiguos socios han pasado el plazo de conservación. Revisa y anonimiza sus datos».

### 5.3. Código

- `src/Modules/Members/Privacy/MemberAnonymizer.php`: `can_anonymize( int $user_id ): true|WP_Error` y `anonymize( int $user_id, int $author_id ): array` con el resumen. Compatible con PHP 7.4.
- Gancho `sulayr_member_anonymize` (`$user_id`), para que el ranking y el pago online limpien lo suyo sin que `Members` dependa de ellos.
- `sulayr-reservas` se engancha a ese gancho y hace su parte con `switch_to_blog( 2 )`, como en la sincronización actual.
- Tarea diaria (`MembersDailyTask` o la del pago online): vacía las respuestas de Redsys de más de 18 meses (L19) y las referencias de tarjetas cerradas que se hubieran quedado (L18).
- Filtro de L21 para las cuentas inactivas de WooCommerce.

---

## 6. Ajustes nuevos

En Socios → Ajustes:

- **Consentimientos**: texto y versión de `cargos_recurrentes` (L10) y de la primera capa de información del pago (4.2).
- **Conservación de datos**: «Plazo de conservación de antiguos socios (años)» sustituye a «Avisar de antiguos socios con más de (años) de baja». Mantiene la misma clave, `retention_former_years`. Se añade «Vaciar la respuesta de Redsys a los (meses)», con 18 por defecto.

En WooCommerce → Ajustes → Cuentas y privacidad: los valores de L20 y L21 (configuración, no código).

---

## 7. Entrega por partes

| Parte | Contenido | Requisito |
|---|---|---|
| P1 | Anonimización (sección 5), pantalla, capacidad, ajustes de conservación y filtro de cuentas inactivas. Configurar los plazos de WooCommerce. | Ninguno. Se puede hacer ya. |
| P2 | Tipo `cargos_recurrentes`, casilla y primera capa en Mis recibos, ajustes de textos, L13, L18 y L19. | Va dentro de la parte B del pago online. Hecho el 17 de noviembre de 2026 en la rama `pago-online-parte-b` (paso B2 del pago online), con los borradores de 4.1 y 4.2 como textos por defecto: tipo, casilla obligatoria al pagar con tarjeta (L11), selector de quién autoriza si hay tutor, primera capa, ajustes y L13 (quitar la tarjeta guarda la revocación). Faltan L18 (vaciar la referencia de las tarjetas sustituidas o quitadas) y L19 (vaciar la respuesta de Redsys a los 18 meses), que siguen siendo propuestas. |
| P3 | Política de privacidad, versión 2, y email a los socios (L14). | Textos aprobados por la junta. Antes de que la parte B del pago online llegue a PROD. |
| P4 | Documentos internos (sección 8). | Revisión de la junta. |

### 7.1. Criterios de aceptación

P1:

1. Un antiguo socio con 7 años de baja y sin nada pendiente se puede anonimizar. Después no hay en la base de datos (de los dos sitios) ni su nombre, ni su DNI, ni su email, ni su teléfono, ni su fecha de nacimiento, ni su foto. Se comprueba con una búsqueda de cada valor en todas las tablas.
2. Los totales de Socios → Recibos de los meses antiguos y los de la facturación de reservas son iguales antes y después.
3. Un socio con un recibo pendiente, una baja de hace 3 años o un pedido de hace 2 años no se puede anonimizar, y la pantalla dice por qué.
4. Sin la capacidad `sulayr_anonymize_members` no se ve el botón y la petición directa da 403. Un nonce falso también da 403.
5. Anonimizar dos veces al mismo socio no falla ni cambia nada.
6. Una cuenta con los roles `customer` y `athlete` no entra en la limpieza de cuentas inactivas de WooCommerce.

P2:

7. Sin la casilla de autorización no se va a Redsys, ni para guardar la tarjeta ni para pagar desde Mis recibos.
8. Guardar, cambiar y quitar la tarjeta dejan una fila de `cargos_recurrentes` con versión, fecha y quién (socio o tutor).
9. Una tarjeta sustituida o quitada no conserva la referencia ni el `Cof_Txnid`.

---

## 8. Documentos internos del club (borradores)

Se preparan con la plantilla Word de Sulayr (Drive: Junta Directiva → IDENTIDAD CORPORATIVA → PLANTILLAS) y se guardan en la carpeta de la junta. La spec no los contiene.

### 8.1. Registro de actividades de tratamiento (art. 30 RGPD)

El club lo necesita aunque sea pequeño, porque gestionar a los socios no es un tratamiento ocasional. Tratamientos propuestos: socios y cuotas; cobro con tarjeta; tienda; reservas de natación con CN Nazarí; ranking interno; documentos del socio; imagen y redes sociales; comunicaciones (email y WhatsApp); web y cookies; licencias federativas y seguro. En cada uno: finalidad, base, categorías de datos y de interesados, destinatarios, transferencias, plazo y medidas de seguridad.

### 8.2. Inventario de encargados

Hostinger, SportEasy, TrainingPeaks, Google (Drive, Forms y Analytics), Meta (WhatsApp), CookieYes y el banco o Redsys según la sección 9. Para cada uno: qué trata, dónde están sus condiciones de encargo (normalmente sus términos online) y si hay transferencia fuera del EEE. CN Nazarí también, según cómo se defina su relación con el sitio de reservas.

### 8.3. Procedimiento ante brechas de seguridad

Quién la detecta y a quién avisa en la junta, cómo se registra, cuándo se notifica a la AEPD (en 72 horas si hay riesgo) y cuándo a los afectados. Incluye un modelo de registro de brechas.

### 8.4. Procedimiento de derechos

Cómo se atiende un email de acceso, rectificación, supresión, oposición, limitación o portabilidad. El plazo es de un mes. Incluye cómo se verifica la identidad y cómo se usa la anonimización (sección 5) para una supresión cuando no hay obligación de conservar.

### 8.5. Compromiso de confidencialidad

Modelo de una página que firman los miembros de la junta y los colaboradores con acceso a wp-admin.

---

## 9. Preguntas abiertas

**Al club**

- [ ] Confirmar las propuestas L10 a L27.
- [ ] Revisar los borradores de 4.1, 4.2 y 4.3.
- [ ] ¿Sigue SportEasy para algo después del piloto del pago online (gestión deportiva) o se deja del todo?
- [ ] ¿Qué relación hay con CN Nazarí en cuanto a datos? ¿Recibe datos de los nadadores del club o solo ve el sistema de reservas?
- [ ] Quién de la junta es el contacto de privacidad y recibe los avisos de brechas.

**Al técnico de Redsys o al banco**

- [ ] ¿El banco trata los datos de las tarjetas como responsable propio o como encargado del club? ¿Lleva el contrato del TPV una cláusula de protección de datos?
- [ ] ¿Cuál es el plazo máximo de reclamación (contracargo) que aplica el banco? Confirma o cambia los 18 meses de L19.
