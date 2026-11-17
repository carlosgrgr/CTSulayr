# SPEC — Pago online: cuotas y tienda con TPV virtual

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr` (web del club, blog 1)
**Estado:** Definida el 17 de noviembre de 2026. Decisiones C1 a C30 cerradas con el club (sección 3). Sin implementar. Contrato del TPV pendiente de firma; sin credenciales de pruebas todavía.
**Fecha:** 17 de noviembre de 2026 (borrador inicial del 28 de octubre de 2026)

El club cobrará con el TPV virtual de su banco (Redsys) dos cosas: las cuotas mensuales de los socios, con la tarjeta guardada y sin que el socio tenga que hacer nada cada mes, y los pedidos de la tienda. Esta spec se apoya en [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md), que ya genera los recibos mensuales y lleva el control manual de pagos. Esta spec no cambia cómo se calcula un recibo: solo cómo se paga.

Amplía dos decisiones de la spec de altas: D41 (se añade el medio de pago «Tarjeta (TPV)») y D52 (los socios que pagan con tarjeta reciben emails de cobro, sección 3).

---

## 1. Situación actual

### 1.1. Cuotas

- Los recibos mensuales los genera la web el día 1 (Socios → Recibos, `sulayr_receipts`). En noviembre de 2026: 45 pendientes (1.776,50 €) y 1 «no aplica».
- Los socios pagan por SportEasy, que solo se usa para cobrar. Un gestor apunta cada pago a mano en el recibo, con su medio: SportEasy, Bizum, efectivo, transferencia o monedero (`Receipts::add_payment`).
- Un recibo pendiente pasa a verse como «impagado» el día 20 (`limit_date`). Ese día el gestor puede aplicar la penalización de 2 € (D46).
- Stripe se eliminó el 11 de noviembre de 2026 (D57 de la spec de altas).

### 1.2. Tienda

- WooCommerce 11.2.2 con el catálogo de equipación en PROD (spec de la tienda online). Checkout clásico (shortcode), HPOS activo, compra como invitado permitida.
- Único medio de pago activo: transferencia bancaria (`bacs`). Los pedidos quedan «en espera» hasta que el club confirma el ingreso.
- La página «Política de devoluciones y reembolsos» está configurada en WooCommerce como página de términos y de devoluciones (comprobado el 17 de noviembre de 2026 con la copia de PROD en local).

### 1.3. TPV virtual

Respuesta del banco del 17 de noviembre de 2026:

- Permite pago por referencia con cobros iniciados por el comercio (**pagos MIT**). Hay que pedir que los activen. Sin coste adicional.
- 0,70 % por operación con tarjeta nacional, 2 % internacional, 5 € al mes de mantenimiento (no lo rebajan). Sin cuota de alta ni permanencia.
- Las devoluciones de cobros rechazados no tienen coste. Lo cobrado se abona en cuenta al día siguiente.
- Sin Bizum por ahora.
- La instalación la hace un técnico de Redsys, que contacta con la persona del club que se indique. Él da el entorno de pruebas, las credenciales y el manual.
- Un único terminal para cuotas y tienda.

---

## 2. Objetivos y alcance

### 2.1. Objetivos

- Cada socio guarda su tarjeta una vez. Cada mes la web cobra su recibo sola, después de que un gestor apruebe el cobro.
- Cada cobro queda apuntado en el recibo en cuanto el banco lo confirma, sin trabajo manual.
- Los fallos se reintentan y se avisan solos. El gestor solo atiende excepciones.
- Los pedidos de la tienda se pagan con tarjeta al momento.
- Dejar de cobrar cuotas por SportEasy.

### 2.2. Fuera de alcance

- Bizum (el banco no lo ofrece).
- Devoluciones desde la web (C24).
- Cobro con tarjeta en el formulario público de alta (C20).
- Contabilidad del club.
- El texto legal de autorización de cargos y de protección de datos lo redacta el club ([PRIORIDADES.md](../PRIORIDADES.md), punto 5). Esta spec solo fija dónde aparece.

### 2.3. Por qué TPV y no SEPA ni Stripe

Valoración de octubre de 2026, el mes con más deportistas: 47 deportistas y 1.626,58 € a cobrar.

| Opción | Coste anual aprox. | Automatización | Comentario |
|---|---|---|---|
| SportEasy (actual) | 182,49 € (3,96 €/deportista/año; 46 deportistas) | Solo cobros | La diferencia con el TPV es de unos 20 € al año |
| TPV virtual | 160–196,80 € | Casi total | 0,70 % tarjetas nacionales, 5 €/mes |
| Domiciliación SEPA (norma 19) | 181 € + 0,60 €/devolución | Parcial | Fichero manual cada mes, mandatos firmados, devoluciones días después |
| Stripe | Más del doble que el TPV | Total | 1,5 % + 0,25 € por cobro |

La diferencia de dinero es pequeña. El motivo para el TPV es el tiempo de gestión: tarjeta guardada una vez, cobro automático y pagos apuntados solos. A partir de unos 60 socios el TPV sale claramente más barato. El club asume la comisión: el socio paga el importe del recibo.

---

## 3. Decisiones del club

Cerradas con el club el 17 de noviembre de 2026. C2, C5, C9, C12, C13, C17, C22 y C28 a C30 salieron como propuestas y el club las confirmó el mismo día.

### Alcance

| # | Decisión |
|---|---|
| C1 | Una spec con dos ámbitos: tienda (parte A) y cuotas (partes B a D). Comparten terminal y credenciales. |
| C2 | Un único terminal Redsys para cuotas y tienda. Separarlos se valorará más adelante. |

### Tienda

| # | Decisión |
|---|---|
| C3 | La tienda cobra con el **módulo oficial de Redsys para WooCommerce** (gratuito), sin programar. Se activa en cuanto haya credenciales, sin esperar a las cuotas. |
| C4 | En la tienda solo se paga con tarjeta. La transferencia bancaria se desactiva. Los pedidos ya hechos por transferencia siguen como están. |
| C5 | Antes de activar el pago con tarjeta, debe haber una página de condiciones de venta y devoluciones configurada como página de términos de WooCommerce. Hecho: «Política de devoluciones y reembolsos». |

### Medio de cobro del socio

| # | Decisión |
|---|---|
| C6 | Guardar la tarjeta es obligatorio, con excepciones. Cada socio tiene un campo **«Medio de cobro»**: «Tarjeta (TPV)» u «Otro medio». Solo los de «Tarjeta (TPV)» entran en el cobro automático. |
| C7 | Los de «Otro medio» pagan como hoy (Bizum, efectivo, transferencia…, apuntado por un gestor) y no reciben emails de cobro (D52 sigue para ellos). También pueden pagar con tarjeta desde Mis recibos. |
| C8 | Un ajuste fija el medio de cobro de las altas nuevas. Durante el piloto es «Otro medio»; al arrancar con todos pasa a «Tarjeta (TPV)». |
| C9 | El cambio de medio de cobro lo hace un gestor desde la ficha del socio y queda en el registro de cambios (D58). |

### Tarjeta

| # | Decisión |
|---|---|
| C10 | La web nunca ve ni guarda datos de tarjeta. El socio los mete en la página de Redsys; la web guarda solo la referencia que devuelve Redsys, la tarjeta enmascarada y la caducidad. |
| C11 | El socio guarda la tarjeta de dos formas desde Mis recibos: pagando con tarjeta cualquier recibo pendiente, o con el botón «Guardar tarjeta» sin pagar nada (validación sin importe), si el técnico de Redsys confirma que se puede. |
| C12 | Al guardar la tarjeta, el socio marca una casilla obligatoria de autorización de cargos recurrentes. Se guarda como consentimiento con versión y fecha (tabla de consentimientos de la spec de altas). Si es menor, puede aceptarla el tutor (D6, D10). |
| C13 | Una tarjeta activa por socio. Guardar otra sustituye a la anterior. El socio puede quitar su tarjeta: si su medio de cobro es «Tarjeta (TPV)», se avisa al club. |
| C14 | Al hacerse efectiva la baja, se borra la referencia de la tarjeta. Si quedan recibos pendientes, se intentan cobrar antes. Recibos y transacciones se conservan con el plazo de antiguos socios (D36, 6 años). |

### Cobro mensual

| # | Decisión |
|---|---|
| C15 | Los recibos se siguen generando el día 1 (D39). El cobro con tarjeta es el **día 5**. Los dos días son configurables. |
| C16 | El cobro necesita aprobación: un gestor pulsa **«Aprobar cobro de <mes>»** después de revisar los recibos. Si se aprueba antes del día de cobro, se cobra ese día. Si se aprueba después, se cobra al momento. Si el día de cobro llega sin aprobar, no se cobra nada y se avisa al email del club. |
| C17 | Entran en el cobro los recibos del mes en estado pendiente o parcial, de socios con medio «Tarjeta (TPV)» y tarjeta activa. Se cobra lo pendiente (total menos pagos ya apuntados). No entran los recibos «no aplica», anulados ni pagados, ni los de meses anteriores. |
| C18 | El monedero no se descuenta solo. Si hay que pagar con monedero, el gestor lo apunta antes de aprobar, como hoy (D47). |
| C19 | Si falla un cobro: email al socio con enlace para pagar o cambiar la tarjeta y **2 reintentos**, a los 3 y a los 6 días. Tras el último fallo se avisa al club. La penalización del día 20 no cambia (D46). |

### Altas y pagos manuales

| # | Decisión |
|---|---|
| C20 | El socio nuevo paga su primer recibo (matrícula y parte proporcional) con tarjeta desde Mis recibos. El email de bienvenida lleva el enlace. Así guarda la tarjeta y desde el mes siguiente se le cobra solo. Si no paga, sigue el flujo normal de impagados. |
| C21 | Cualquier socio puede pagar con tarjeta cualquier recibo propio pendiente desde Mis recibos, tenga el medio de cobro que tenga. |
| C22 | Un gestor puede lanzar «Cobrar con la tarjeta guardada» en un recibo concreto (p. ej. uno generado después de aprobar el cobro del mes o tras un reingreso). |

### Avisos

| # | Decisión |
|---|---|
| C23 | Emails al socio con medio «Tarjeta (TPV)», con textos editables en Socios → Ajustes: aviso previo al aprobar el cobro (importe, desglose y día de cargo); cobro realizado (con enlace al recibo); cobro fallido (con enlace para pagar o cambiar la tarjeta); tarjeta a punto de caducar (30 días antes). Si es menor, también al tutor (D6). |

### Devoluciones

| # | Decisión |
|---|---|
| C24 | Las devoluciones se hacen en el portal de comercio de Redsys. En la web, el gestor borra el pago con motivo o lo pasa al monedero, con las herramientas que ya existen. |

### Arranque

| # | Decisión |
|---|---|
| C25 | Piloto real de un mes con un grupo de socios que **no** son de la junta. El gestor les pone el medio «Tarjeta (TPV)». El resto sigue con SportEasy. |
| C26 | Tras el piloto, todos los socios pasan a «Tarjeta (TPV)» a la vez, salvo las excepciones. Quien no tenga tarjeta guardada el día de cobro paga desde Mis recibos. Se deja de cobrar por SportEasy. |
| C27 | Sin fecha objetivo: el piloto empieza cuando todo esté probado en el entorno de pruebas de Redsys. |

### Permisos y seguridad

| # | Decisión |
|---|---|
| C28 | Aprobar el cobro, cobrar un recibo concreto y cambiar el medio de cobro requieren `sulayr_manage_members` (administrador y gestor de socios). El deportista solo ve y paga sus recibos y gestiona su tarjeta. |
| C29 | Las credenciales de Redsys (comercio, terminal, clave SHA-256 y entorno de pruebas o real) van en `wp-config.php`, nunca en la base de datos ni en el repositorio. También las del módulo de la tienda: el plugin del club sustituye con un filtro los valores que el módulo guarda en sus ajustes por los de `wp-config.php`. Así una copia de la base de datos de PROD en local nunca lleva la clave real ni cobra de verdad. |
| C30 | Cada cobro y cada cambio de tarjeta queda en el registro de cambios del socio (D58). |

---

## 4. Modelo de datos

### 4.1. Ficha del socio

Meta de usuario `sulayr_charge_method`: `tpv` u `otro`. Si no existe, vale el ajuste de altas nuevas (C8). En la ficha de wp-admin aparece junto al estado del socio, con el estado de su tarjeta (enmascarada, caducidad, fecha de alta).

### 4.2. Tablas nuevas

Con el prefijo de blog 1 y en `MembersSchema` (sube la versión del esquema). Importes en euros con 2 decimales, como `sulayr_receipts`.

| Tabla | Contenido |
|---|---|
| `sulayr_payment_cards` | Tarjetas: usuario, referencia de Redsys (`Ds_Merchant_Identifier`), identificador de la operación inicial (`Ds_Merchant_Cof_Txnid`), tarjeta enmascarada, caducidad (AAMM), estado (`activa`, `sustituida`, `quitada`, `caducada`, `borrada`), creada, cerrada. Al borrarse (C14) se vacían la referencia y el identificador; la fila queda para el histórico. |
| `sulayr_tpv_transactions` | Cada operación enviada a Redsys: recibo (o 0 si es validación), usuario, `Ds_Order`, tipo (`registro`, `pago_manual`, `recurrente`, `reintento`, `validacion`), intento, importe, estado (`enviada`, `autorizada`, `denegada`, `error`), `Ds_Response`, código de autorización, respuesta sin datos sensibles (JSON), pago del recibo creado, creada, actualizada. `Ds_Order` único. |

### 4.3. Opciones

- `sulayr_tpv_charges`: por mes (`AAAA-MM`), quién aprobó, cuándo, día de cobro y resumen del lote (cobrados, fallidos, sin tarjeta).
- Ajustes nuevos en Socios → Ajustes: día de cobro (5), días de reintento (3 y 6), medio de cobro de altas nuevas, días de aviso de caducidad (30), textos de los emails de C23, texto y versión de la autorización de cargos recurrentes (C12).

### 4.4. Medio de pago

`Receipts::add_payment` gana el medio fijo `tpv` («Tarjeta (TPV)»), como `monedero`: no es editable en los ajustes y solo lo apunta la web cuando Redsys autoriza un cobro. El pago guarda el `Ds_Order` en la nota y el autor es el usuario del sistema (0) o el socio si pagó desde Mis recibos.

---

## 5. Integración con Redsys

Los nombres de parámetros son los habituales de Redsys. Hay que confirmarlos con el manual que dé el técnico.

### 5.1. Pago con el socio delante (redirección)

Lo usan el pago desde Mis recibos (C20, C21) y la validación sin importe (C11).

1. La web crea la transacción y manda al socio a la página de Redsys con la firma HMAC SHA-256. Pide guardar la tarjeta con `DS_MERCHANT_IDENTIFIER=REQUIRED`, `DS_MERCHANT_COF_INI=S` y `DS_MERCHANT_COF_TYPE=R`. El socio se autentica con su banco (3D Secure).
2. Redsys avisa a la URL de notificación de la web (`DS_MERCHANT_MERCHANTURL`, por HTTPS) con el resultado, la referencia, el `Ds_Merchant_Cof_Txnid` y la caducidad.
3. La web verifica la firma. Si está autorizada (`Ds_Response` de 0000 a 0099): apunta el pago en el recibo con medio `tpv` y guarda la tarjeta (sustituye la anterior).
4. El socio vuelve a Mis recibos con un aviso de pago hecho o fallido. La página de vuelta **no** apunta nada: solo la notificación lo hace.

### 5.2. Cobro sin el socio (MIT)

Lo usan el cobro mensual, los reintentos y el cobro de un recibo concreto (C22).

1. Llamada a la API REST de Redsys con la referencia guardada, `DS_MERCHANT_DIRECTPAYMENT=true`, `DS_MERCHANT_EXCEP_SCA=MIT`, `DS_MERCHANT_COF_INI=N`, `DS_MERCHANT_COF_TYPE=R` y el `DS_MERCHANT_COF_TXNID` guardado.
2. La respuesta llega en la misma llamada. La web apunta el pago o el fallo.
3. Si no hay respuesta (tiempo agotado o error de red), la transacción queda `enviada` y no se reintenta sola hasta consultar su estado en Redsys. Nunca se lanza otro cobro sobre un recibo con una transacción `enviada`.

### 5.3. Detalles

- **`Ds_Order`:** 12 caracteres, los 4 primeros numéricos: `AAMM` + `Q` + id de la transacción con ceros a la izquierda (7 cifras). La letra evita choques con los pedidos de la tienda, que usan el mismo terminal (C2).
- **Importe:** en céntimos y moneda 978 (euro).
- **Firma:** HMAC SHA-256 con la clave del comercio, al enviar y al recibir.
- **Notificación:** endpoint REST propio (`/wp-json/sulayr/v1/redsys`). Idempotente: si la transacción ya está cerrada, responde y no hace nada.
- **Código:** un cliente de Redsys propio en el plugin (`src/Modules/Payments/`), compatible con PHP 7.4. La tienda no lo usa (C3).

---

## 6. Ciclo mensual

| Día | Qué pasa |
|---|---|
| 1 | Se generan los recibos (como hoy). |
| 1 a 4 | El gestor revisa: recibos desfasados, pagos con monedero, excepciones. Pulsa «Aprobar cobro». Cada socio con medio «Tarjeta (TPV)» recibe el aviso previo (C23). |
| 5 | La tarea diaria cobra los recibos aprobados (C17). Email de cobro realizado o fallido. |
| 8 y 11 | Reintentos de los fallidos (C19). Tras el último, aviso al club. |
| 20 | Día límite: impagado y penalización, como hoy (D46). |

### 6.1. Pantalla «Aprobar cobro»

En Socios → Recibos, para el mes en curso:

- Resumen: número de recibos e importe que se cobrarán, socios con medio «Tarjeta (TPV)» sin tarjeta activa o con la tarjeta caducada, recibos desfasados (D40).
- Los recibos desfasados se avisan, pero no bloquean: el gestor decide si los regenera antes.
- Botón «Aprobar cobro de <mes>», con confirmación. Una vez aprobado se puede anular la aprobación mientras no haya empezado el cobro.

### 6.2. Tarea diaria

Se amplía la tarea diaria de la spec de altas (`MembersDailyTask`):

- Día de cobro con el mes aprobado: cobra los recibos. Uno por uno, con bloqueo para que dos ejecuciones no cobren a la vez y comprobando antes de cada cargo que el recibo sigue con importe pendiente.
- Días de reintento: reintenta los fallidos que sigan pendientes.
- Avisa de las tarjetas que caducan en 30 días (una vez por tarjeta) y marca como caducadas las vencidas.
- Borra las referencias de los socios cuya baja se hizo efectiva (C14).
- Día de cobro sin aprobar: avisa al club una vez.

WP-Cron depende de las visitas. En PROD se usa una tarea cron del servidor que llama a `wp-cron.php` de los dos sitios cada 5 minutos, con `DISABLE_WP_CRON` en `wp-config.php`.

---

## 7. Mi cuenta

En la pestaña «Mis recibos» (20.8 de la spec de altas):

- Botón «Pagar con tarjeta» en cada recibo pendiente o parcial (C21), salvo que tenga una transacción en curso.
- Bloque «Mi tarjeta»: tarjeta enmascarada y caducidad, «Cambiar tarjeta» (guardar otra, C11 y C13) y «Quitar tarjeta». Si no tiene, «Guardar tarjeta», con la casilla de autorización (C12).
- Si el medio de cobro es «Tarjeta (TPV)», texto con el día de cobro del mes y aviso si no hay tarjeta activa.

La pestaña «Mis cuotas» muestra el medio de cobro.

---

## 8. Tienda

Parte A. Sin código propio.

1. Comprobar que la versión del módulo oficial de Redsys para WooCommerce funciona con la versión de PHP de PROD y con WooCommerce 11.
2. Instalar y configurar con las credenciales de pruebas. Probar pago autorizado, denegado y cancelado. Comprobar que el pedido pasa a «procesando» y que el CSV de Inverse y el nombre del tritraje no cambian.
3. Añadir el filtro de C29 para que el módulo lea las credenciales de `wp-config.php`. Los nombres de sus ajustes se ven al instalarlo.
4. Pasar a las credenciales reales, desactivar la transferencia (C4) y hacer una compra real pequeña.
5. Actualizar `club-triatlon-sulayr/MANUAL-TIENDA.md` y `docs/club/guia-tienda-sulayr.html`.

Si el módulo oficial no sirve en PROD, se valora una pasarela propia con el cliente de la sección 5 antes de seguir.

---

## 9. Entrega por partes

| Parte | Contenido | Requisito |
|---|---|---|
| A | Tienda con tarjeta (sección 8). | Credenciales de pruebas. |
| B | Tablas, cliente Redsys, URL de notificación, pago y guardado de tarjeta desde Mis recibos, validación sin importe, medio de cobro en la ficha, consentimiento, emails de bienvenida con enlace (C20). | Credenciales de pruebas y pagos MIT activados. |
| C | Aprobar cobro, cobro mensual, reintentos, cobro de un recibo concreto, avisos, caducidad, baja (C14). | Parte B. |
| D | Piloto con socios que no son de la junta (C25), un mes; luego arranque con todos (C26) y retirada de SportEasy de los medios de pago. | Parte C en PROD con credenciales reales. |

### 9.1. Criterios de aceptación

Parte A:

1. Un pedido pagado con tarjeta en pruebas queda «procesando» con el pago apuntado.
2. Un pago denegado o cancelado deja el pedido «pendiente de pago» y no reduce el stock más allá de lo que hace hoy WooCommerce.
3. En PROD, el checkout solo ofrece tarjeta y muestra la casilla de condiciones.

Parte B:

4. Un socio paga un recibo pendiente desde Mis recibos: el recibo queda pagado con medio «Tarjeta (TPV)» y su tarjeta aparece en «Mi tarjeta».
5. Una notificación repetida o con firma falsa no crea pagos.
6. Volver a la web sin que llegue la notificación no apunta el pago.
7. Guardar otra tarjeta sustituye a la anterior. Quitarla avisa al club si su medio es «Tarjeta (TPV)».
8. Sin la casilla de autorización no se puede guardar la tarjeta, y el consentimiento queda con versión y fecha.

Parte C:

9. Sin aprobación, el día de cobro no se cobra nada y el club recibe el aviso.
10. Con aprobación, se cobran solo los recibos de C17 y por lo pendiente. Un socio con medio «Otro medio» no recibe ningún cargo ni email.
11. Ejecutar la tarea dos veces el mismo día no cobra dos veces.
12. Un cobro denegado se reintenta los días 8 y 11; tras el último, el club recibe el aviso.
13. El socio recibe el aviso previo, el de cobro realizado o fallido y el de caducidad.
14. Al hacerse efectiva una baja, la referencia de la tarjeta queda vacía.

---

## 10. Preguntas abiertas

**Al técnico de Redsys**

- [ ] Activar los pagos MIT (pago por referencia, COF) en el terminal.
- [ ] Credenciales y URL del entorno de pruebas, y manual de la API REST.
- [ ] ¿Permite una operación de validación sin importe para guardar o cambiar la tarjeta (C11)?
- [ ] ¿Hay comisión mínima o fija por operación además del 0,70 %?
- [ ] ¿Cómo se consulta el estado de una operación sin respuesta (5.2, paso 3)?

**Al club**

- [ ] Persona de contacto para el técnico de Redsys (nombre y móvil) y firma del contrato.
- [ ] Texto de la autorización de cargos recurrentes (C12).
- [ ] Socios del piloto (C25).
- [x] PROD usa PHP 8.2 (se puede subir a 8.3, 8.4 o 8.5). El hosting permite desactivar WP-Cron y usar una tarea cron del servidor (6.2).

**Respuestas del banco** (28 de octubre al 17 de noviembre de 2026)

- [x] Pago por referencia con cobros iniciados por el comercio: sí, pagos MIT, a pedir, sin coste adicional.
- [x] Cuota de alta: no. Permanencia: no.
- [x] Rebaja de los 5 €/mes: no.
- [x] Bizum: no lo tienen de momento.
- [ ] Comisión mínima o fija por operación: sin respuesta (pasa al técnico).
- [x] Entorno de pruebas y manual: los da el técnico de Redsys al activar el servicio.
- Devoluciones de cobros rechazados sin coste. Abono en cuenta al día siguiente. Para contratar piden la URL del club (`https://clubtriatlonsulayr.com/`).
