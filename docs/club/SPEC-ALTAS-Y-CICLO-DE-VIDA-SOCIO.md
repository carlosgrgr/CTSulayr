# SPEC — Alta y ciclo de vida del socio

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr`
**Estado:** Definida el 11 de noviembre de 2026. Decisiones D1 a D58 cerradas con el club (sección 3). Parte A en `main` (`fab85c343` y `892d6fcff`) y desplegada en PROD el 11 de noviembre de 2026 con FileZilla. Stripe limpiado y migración terminada en PROD ese día (sección 16.5). Partes B, C y D (secciones 18, 19 y 20, con la identidad Sulayr) implementadas y probadas en local el 11 y 12 de noviembre de 2026, en `main` (merge `21914c88d`) y su código subido a PROD el 12 de noviembre de 2026 con FileZilla (pasos 1 a 4 de [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md): esquema en la versión 4 y menú Socios completo). Pasos 5 a 7 hechos ese día (archivos retirados borrados, Socios → Ajustes configurado con los datos reales y comprobaciones con un alta de prueba). Recibos en marcha desde noviembre de 2026 (paso 8). Pruebas manuales de local y PROD hechas el 12 de noviembre de 2026; quedan las que dependen de una fecha (penalización del día 20, generación automática del 1 de diciembre, baja de Diego Lardón el 1 de enero de 2027), en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md). Pestaña «Mis recibos» (20.8) hecha, probada y subida a PROD el 12 de noviembre de 2026.
**Fecha:** 11 de noviembre de 2026

Esta spec sustituye el alta manual de socios (WhatsApp + Excel + alta a mano en la web) por un proceso en la web del club (blog 1): solicitud, aprobación, cambios, congelaciones, bajas, recibos mensuales y listados. Conserva el histórico de altas, bajas y cambios de servicios, y deja los Excel de Drive solo para ingresos y gastos del club.

Es la base sobre la que se apoyará más adelante [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) (cobro online con TPV): los recibos de esta spec son los que esa spec cobrará. Esta spec no cobra dinero.

---

## 1. Situación actual

### 1.1. Proceso manual

1. Un interesado contacta con el club. Se le envía un PDF con condiciones, tarifas y horarios. A veces prueba un entrenamiento.
2. Si se apunta, se le piden por WhatsApp: nombre y apellidos, teléfono, email, DNI, fecha de nacimiento, tarifa (básica / entrenamientos), modalidad si es entrenamientos (sprint / olímpico) y suplemento de natación (sí / no).
3. El gestor:
   - crea el usuario en la web con rol `athlete` y sus datos;
   - lo añade a la hoja «Datos de socios» de Drive;
   - añade una fila en «Pagos de cuotas 2026» en el mes y con el importe que toca;
   - añade una fila en la hoja «Welcome pack»;
   - lo da de alta en SportEasy (cobro de mensualidades).
4. Se le explica que recibirá emails de la web y de SportEasy para crear contraseñas. Si tiene entrenamientos, se le pasan los enlaces de alta en TrainingPeaks y de vinculación con el entrenador.
5. Se le añade a la comunidad de WhatsApp y se le da la bienvenida.

### 1.2. Lo que ya hay en el plugin

Datos del entorno local del 11 de noviembre de 2026; no comprobados contra PROD.

- **Rol `athlete`** (`src/Modules/Roles/AthleteRole.php`), solo en blog 1. `UserService::assign_athlete_role` lo asigna en `user_register` si el usuario no tiene rol o es `subscriber`. Mi cuenta comprueba `in_array( 'athlete', roles )`.
- **Campos ACF del deportista** (`src/Modules/Fields/UserFields/UserFields.php`): `sulayr_user_photo`, `sulayr_user_phone`, `sulayr_user_birth_date`, `sulayr_user_join_date`, `sulayr_user_dni`, `sulayr_user_membership_plan` (`basic` / `premium`), `sulayr_user_training_type` (`sprint` / `olimpic`), `sulayr_user_swimming` (`'1'` / `'0'`), `sulayr_user_is_directive`, `sulayr_user_athlete_status` (`active` / `standby` / `baja` / `moroso`). No hay número de socio, fecha de baja ni tallas.
- **Tarifas escritas en código** (`src/Modules/Subscriptions/SubscriptionProducts.php`) como productos WooCommerce ocultos: Básica 22 €, Entrenamientos 33 €, Natación 25 €, Natación directiva 29,50 €. En local hay 10 productos duplicados; en PROD, 4.
- **Stripe** (`StripeService`, `SubscriptionService`, `StripeWebhookHandler`, endpoint `mis-pagos`): suscripción mensual de un único precio. Ya no se usa (D57).
- **Histórico.** Solo `sulayr_user_join_date` y el estado actual. El CPT `sulayr_subscription_changes` no se registra (nombre de 27 caracteres, límite 20), así que no hay cambios guardados.
- **Correos.** Al crear un usuario el plugin no envía nada. Existen avisos de cambio de tarifa y de paso a baja (`AthleteUpdate.php`).
- **Monedero** (CPT `sulayr_wallet`). Usa `capability_type = post`: editores y autores pueden crear movimientos.
- **Reservas.** `sulayr-reservas` (`Sync/SyncService.php`) da de alta como `nadador` en blog 2 a quien sea `athlete` + `sulayr_user_swimming = '1'` + estado `active`. Hoy los 29 nadadores son también deportistas del club.

### 1.3. Hojas de Drive que se sustituyen

| Hoja | Qué guarda | Qué la sustituye |
|---|---|---|
| Datos de socios → «Datos de los socios» | Nº socio, nombre, contacto, DNI, nacimiento, alta, baja, IBAN, comentarios | Ficha del socio (sección 4) |
| Datos de socios → «Antiguos deportistas» | Socios de baja, con «1970» como fecha de baja desconocida | Socios en estado `baja` (sección 11) |
| Datos de socios → «Posibles deportistas» | Interesados | Nada. Queda fuera del sistema (D2) |
| Pagos de cuotas 2026 → bloque de cada mes | Cuota, piscina, € a favor, total, pagado, comentarios | Recibos mensuales (sección 8) |
| Pagos de cuotas 2026 → ingresos, gastos, visión anual | Contabilidad del club | Nada. Siguen en el Excel |
| Welcome pack | Entregas por artículo, marcadas con colores | Entregas de welcome pack (sección 9.3) |

---

## 2. Objetivos y alcance

### 2.1. Objetivos

- Que el deportista rellene sus datos en un formulario y el gestor solo apruebe.
- Guardar el histórico de altas, bajas, congelaciones y cambios de servicios, con fechas.
- Calcular solo lo que paga cada socio cada mes, y llevar el control de lo pagado.
- Poder cambiar tarifas, servicios, textos y pasos **sin tocar código**.
- Distinguir siempre a los socios del club de los usuarios de otras webs del multisite.

### 2.2. Incluido

- Catálogo configurable de servicios y tipos de socio.
- Formulario público de solicitud y formulario de alta para gestores.
- Aprobación, detección de duplicados y reingresos.
- Cambios, bajas y congelaciones, pedidos por el deportista o hechos por el gestor.
- Recibos mensuales con PDF, pagos y monedero.
- Listados: recibos del mes, entrenador, welcome pack, tareas pendientes.
- Roles «Gestor de socios» y «Gestor de tienda».
- Registro de cambios.
- Migración desde la web y las hojas de Drive.
- Eliminación de la integración con Stripe.

### 2.3. Fuera de alcance

- Interesados y entrenamientos de prueba (D2).
- Licencia federativa (D4).
- Cobro online. Lo cubrirá la spec de cobros.
- Descuentos automáticos por tipo (promoción amigo, bonos de incentivo). Hoy van como línea manual (D45).
- Tarifas de menores. El modelo las admite como tipo de socio cuando se decidan (D19).
- Borrado automático de antiguos socios (D36).
- Integración con SportEasy, TrainingPeaks o WhatsApp. Se quedan como pasos manuales (D9).
- Cambios en `sulayr-reservas` (D38).
- Facturas legales e IVA (D49).

---

## 3. Decisiones del club

Cerradas con el club el 11 de noviembre de 2026.

### Alcance y alta

| # | Decisión |
|---|---|
| D1 | El sistema cubre desde la solicitud de alta hasta la baja y el reingreso. |
| D2 | Interesados y pruebas siguen fuera del sistema, con contacto humano. No se importa «Posibles deportistas». |
| D3 | Dos formas de alta: formulario público (principal) y formulario de gestor. La cuenta se crea **al aprobar**, no al enviar. |
| D4 | La licencia federativa no entra. No existe cuota anual. |
| D5 | Matrícula con el primer recibo: 20 € normal, 10 € si es un reingreso (regla fija). Hay periodos de promoción con matrícula gratis. Todo configurable. Al aprobar, el sistema propone el importe y el gestor puede cambiarlo con motivo. |
| D6 | Se admiten menores. Tienen email propio (si no, el tutor le crea uno). Su ficha guarda los datos del tutor y los avisos van también al tutor. |
| D7 | El formulario pide la fecha de inicio deseada. El gestor la confirma o la cambia al aprobar. |
| D8 | Formulario: nombre, apellidos, email, teléfono, DNI/NIE con validación de letra, fecha de nacimiento, servicios y opciones, fecha de inicio, talla de camiseta (opcional), observaciones, datos del tutor si es menor, consentimientos y antispam. Sin contacto de emergencia ni datos médicos. La foto la sube el socio después desde Mi cuenta. |
| D9 | Email de bienvenida automático y lista de pasos manuales por socio. Textos y pasos editables desde el panel. |
| D10 | Consentimientos separados y sin marcar: política de privacidad (obligatoria), normas y condiciones del club (obligatoria), uso de imagen (opcional, revocable desde Mi cuenta). Se guarda versión del texto, fecha y quién aceptó (socio o tutor). |
| D11 | Al enviar una solicitud, aviso en pantalla y email solo al club. Al deportista no se le envía email de «solicitud recibida». |
| D12 | Pasos iniciales de alta: dar de alta en SportEasy, añadir a la comunidad de WhatsApp, comprobar TrainingPeaks vinculado (solo con entrenamiento), bienvenida dada. |
| D13 | Antes de aprobar se busca el email y el DNI en toda la red. Antiguo socio: se recupera su cuenta y su número (reingreso). Usuario de otra web: se le añade el rol `athlete` en blog 1 sin crear otra cuenta. El gestor elige «vincular» o «es otra persona». |
| D14 | Número de socio propio. Los actuales conservan el del Excel, que es su ID de usuario de WordPress. En la migración, quien no tenga número recibe su ID de usuario si está libre (decidido el 11 de noviembre de 2026). Las altas nuevas reciben el siguiente al mayor. En un reingreso se recupera. |
| D15 | El email de bienvenida incluye el enlace para crear contraseña y sustituye al email estándar de WordPress. Incluye el enlace a la comunidad de WhatsApp y el bloque de texto de cada servicio que tenga. |
| D16 | Las solicitudes rechazadas se borran a los 30 días y las pendientes sin tocar a los 60 (configurable). Queda un registro sin datos personales. |

### Catálogo y precios

| # | Decisión |
|---|---|
| D17 | Modelo general: cuota base + servicios contratables, cada uno con precio por fecha de vigencia y opciones. Debe servir para el modelo 2025/26 y para el borrador de servicios 2027 sin tocar código. |
| D18 | Catálogo como tipo de contenido «Servicio», con una pantalla de edición por servicio. Tipos de socio y artículos del welcome pack en una página de ajustes. |
| D19 | Tipos de socio configurables: normal, junta y, en el futuro, menor. |
| D20 | Cada servicio indica si aparece en el formulario público y con qué servicios es incompatible. Hoy solo hay una incompatibilidad: entrenamiento genérico ↔ entrenamiento individualizado. |
| D21 | Cada servicio indica si se prorratea. La natación nunca se prorratea. |
| D22 | Cada servicio puede tener un precio distinto por tipo de socio. La junta nunca paga la cuota base. El resto de servicios son opcionales para la junta, con su precio (natación junta: 29,50 €). |
| D23 | Cada servicio indica si se anula al congelar en el mes en curso. Solo la cuota base. |

### Ciclo de vida

| # | Decisión |
|---|---|
| D24 | Primer mes: los servicios prorrateables se cobran por días restantes (incluido el día de alta), con 2 decimales. Los no prorrateables, el mes completo. |
| D25 | Cambios, congelaciones y bajas se aplican el día 1 del mes siguiente. Nunca a mitad de mes. |
| D26 | Plazo de aviso para cambios y bajas: 5 días antes de fin de mes, configurable. Si se avisa más tarde, se aplica el día 1 del mes siguiente al siguiente. |
| D27 | Un gestor puede aplicar un cambio al momento si el recibo del mes en curso no está pagado. El gestor confirma que no está pagado. Se guarda quién y por qué. El importe nuevo del mes se calcula con D24. |
| D28 | Estados: `pendiente`, `activo`, `congelado`, `baja`. `standby` pasa a `congelado`. «Moroso» no es estado: se deriva de los recibos. |
| D29 | Congelado: con motivo, fecha de inicio y fecha de fin prevista. No hay recibos mientras dura. Pierde el acceso a reservas. Al terminar vuelve con los mismos servicios. |
| D30 | Congelación al momento (D27): solo se anula la cuota base del recibo del mes en curso. Natación y entrenamientos se cobran igual y se conservan hasta fin de mes. |
| D31 | El deportista pide cambios y bajas desde Mi cuenta. El gestor aprueba o rechaza. El deportista recibe email con el resultado. |
| D32 | Baja: la cuenta se conserva, sin acceso a Mi cuenta del deportista ni a reservas. Puede seguir comprando en la tienda. |
| D33 | Desde Mi cuenta, el deportista cambia teléfono, foto, email (con verificación de WordPress) y consentimiento de imagen. Nombre, apellidos, DNI y fecha de nacimiento solo los cambia un gestor. Servicios y baja, por solicitud. |
| D34 | Retribución de la junta: cantidad mensual fija por miembro, apuntada sola en su monedero el día 1. |
| D35 | Subvenciones por socio, decididas por la junta caso a caso: porcentaje o importe, servicios afectados, fechas y motivo. |
| D36 | Plazo de conservación de datos de antiguos socios configurable (6 años por defecto). Solo avisa; no borra. |
| D37 | El plazo es el mismo para cambios y bajas (D26). «Condiciones temporada» y «Bienvenida Sulayr» se corrigen fuera del código para decir lo mismo. |
| D38 | Reservas no se toca. El sistema sigue escribiendo `sulayr_user_swimming` y `sulayr_user_athlete_status` a partir de los servicios y el estado. Una tarea nocturna aplica los cambios que empiezan ese día. Un cambio inmediato se aplica al momento. |

### Recibos

| # | Decisión |
|---|---|
| D39 | Recibos mensuales guardados, uno por socio y mes, con líneas por concepto. Se generan con un botón o solos el día configurado (día 1 por defecto) si nadie lo ha pulsado. |
| D40 | Si cambia algo que afecta a un recibo ya generado, el sistema avisa. No lo cambia solo. |
| D41 | Pagos por recibo: uno o varios, con fecha, medio (SportEasy, Bizum, efectivo, monedero) y quién lo marcó. |
| D42 | Estados del recibo: pendiente, pagado parcialmente, pagado, no aplica, anulado. Pendiente pasado el día límite se muestra como impagado. |
| D43 | Recibo editable mientras no esté pagado. Pagado, queda bloqueado; las correcciones van como línea en el recibo siguiente. |
| D44 | Numeración `R-AAAA-NNNN`, correlativa por año, asignada al generar, nunca reutilizada. |
| D45 | Líneas manuales con concepto e importe positivo o negativo. |
| D46 | Penalización por retraso con un botón «Aplicar penalización a impagados» desde el día configurado (20 por defecto, 2 € por defecto). |
| D47 | Pago con el saldo del monedero, total o parcial, marcado por un gestor. Genera un cargo en el monedero. |
| D48 | Cuando el precio del tipo de socio es más bajo que el general, el recibo muestra el precio general y una línea «Bonificación <tipo>» con la diferencia. Si es igual o más alto, muestra el precio del tipo. |
| D49 | Es un recibo, no una factura. Sin IVA. Datos del club configurables (nombre, CIF, dirección, logo). |
| D50 | La junta siempre tiene recibo, aunque salga a 0 €. Congelados y bajas, no. Un recibo de total 0 queda en «no aplica». |
| D51 | El PDF se genera al pulsar «Ver recibo», a partir de los datos guardados. No se guarda en disco. |
| D52 | Sin emails de recibos. Ajuste «Avisar por email del recibo mensual» desactivado por defecto. |
| D53 | Pestaña «Mis cuotas» en Mi cuenta: servicios actuales, cambios pendientes y un resumen de los recibos. Los recibos, con todas sus líneas y estado (también «No aplica» y «Anulado») y el PDF, van en su propia pestaña «Mis recibos» (20.8). Sustituye a «Mis pagos». |

### Listados, roles y registro

| # | Decisión |
|---|---|
| D54 | Listado del entrenador: socios con entrenamiento activo, nombre completo, email y modalidad. Botón para copiar el texto para WhatsApp, novedades desde el último envío marcadas, «Marcar como enviado» y botón para enviarlo por email a las direcciones configuradas (entrenador y club). Nunca se envía solo. |
| D55 | Welcome pack: artículos configurables, entregas por socio (artículos, fecha, quién), varias a la vez como un solo pack, lista de socios activos sin pack. Sin control de stock. No depende de la tarifa. |
| D56 | Roles adicionales que se suman a `athlete`: «Gestor de socios» (solo menú Socios en wp-admin) y «Gestor de tienda» (pedidos, productos, cupones, informes, CSV de Inverse; sin usuarios ni ajustes ni pagos). Solo un administrador los asigna. Avisos a gestores solo al email del club. |
| D57 | Se elimina por completo Stripe: código, webhook, botón de suscripción, «Mis pagos», claves, metas de usuario y productos de tarifas. No se exporta nada. |
| D58 | Registro de cambios de cada socio (datos, servicios, estado, roles, recibos, consentimientos): fecha, autor, valor anterior y nuevo. No se puede editar ni borrar. Solo visible en wp-admin. |

---

## 4. Modelo de datos

Todo vive en blog 1. Las tablas propias usan el prefijo de blog 1 tras `switch_to_blog` si se accede desde otra web, y el prefijo `sulayr_` en tablas, opciones y capacidades. Esquema con `dbDelta` y versión en la opción `sulayr_members_db_version`.

### 4.1. Ficha del socio

Se mantiene el usuario de WordPress y los metas ACF actuales. Se añaden:

| Meta | Contenido |
|---|---|
| `sulayr_member_number` | Número de socio (entero, único) |
| `sulayr_member_type` | Clave del tipo de socio (D19) |
| `sulayr_user_athlete_status` | Se mantiene, con valores `pendiente` / `active` / `congelado` / `baja` |
| `sulayr_member_leave_date` | Fecha de baja, o `desconocida` |
| `sulayr_member_tshirt_size` | Talla de camiseta |
| `sulayr_member_guardian` | Datos del tutor: nombre, DNI, teléfono, email |
| `sulayr_member_board_payment` | Retribución mensual de la junta (D34) |
| `sulayr_member_sex` | Sexo (`m` / `f`). Lo añade [SPEC-RANKING-INTERNO.md](SPEC-RANKING-INTERNO.md) (paso R0, 16 de noviembre de 2026): obligatorio en el formulario de alta y en «Nueva alta», visible en la ficha y registrado en el historial. El socio no lo cambia desde Mi cuenta; el gestor de ranking lo rellena en Ranking → Deportistas. No cambia ninguna decisión de esta spec. |

`sulayr_user_membership_plan`, `sulayr_user_training_type`, `sulayr_user_is_directive` dejan de ser la fuente de verdad tras la migración. `sulayr_user_swimming` se sigue escribiendo para reservas (D38).

### 4.2. Tablas nuevas

| Tabla | Contenido |
|---|---|
| `sulayr_member_services` | Servicios de cada socio: usuario, servicio, opción, fecha de inicio, fecha de fin (nula = vigente), origen (alta, solicitud, gestor, migración). **El histórico sale de aquí.** |
| `sulayr_member_status_periods` | Periodos de estado: usuario, estado, inicio, fin, motivo. Altas, congelaciones, bajas y reingresos. |
| `sulayr_member_requests` | Solicitudes de alta, cambio y baja: tipo, datos, estado (pendiente / aprobada / rechazada), fecha efectiva calculada, quién resolvió, motivo. |
| `sulayr_member_subsidies` | Subvenciones (D35). |
| `sulayr_member_consents` | Consentimientos (D10): usuario o solicitud, tipo, versión del texto, aceptado/revocado, fecha, quién (socio o tutor). |
| `sulayr_member_tasks` | Pasos pendientes por socio (D9, D12) con su estado, fecha y quién lo marcó. |
| `sulayr_member_welcome_deliveries` | Entregas del welcome pack (D55). |
| `sulayr_receipts` | Recibos (D39 a D44): número, usuario, mes, total, estado, fecha de generación, marca de desfase. |
| `sulayr_receipt_lines` | Líneas: recibo, tipo (cuota, servicio, prorrateo, matrícula, bonificación, subvención, penalización, manual, corrección), concepto, importe, servicio de origen. |
| `sulayr_receipt_payments` | Pagos: recibo, importe, fecha, medio, movimiento de monedero si lo hay, quién. |
| `sulayr_member_log` | Registro de cambios (D58). |

### 4.3. Catálogo

CPT `sulayr_service` (nombre de menos de 20 caracteres). Campos ACF:

- Es cuota base (sí / no). Solo puede haber una activa.
- Precios: repetidor con tipo de socio (o general), importe y fecha de inicio de vigencia.
- Se prorratea (sí / no).
- Se anula al congelar en el mes en curso (sí / no).
- Visible en el formulario público (sí / no).
- Opciones (lista de claves y nombres; p. ej. `sprint` / `olimpico`).
- Incompatible con (otros servicios).
- Da acceso a reservas de natación (sí / no). Es lo que escribe `sulayr_user_swimming` (D38).
- Aparece en el listado del entrenador (sí / no).
- Texto para el email de bienvenida.
- Pasos de alta que añade (opcional), además de los generales.

El precio aplicable a un mes es el de la entrada de vigencia más reciente con fecha de inicio ≤ día 1 de ese mes, para el tipo de socio si existe, si no la general.

### 4.4. Ajustes

Página «Socios → Ajustes»:

- Tipos de socio (clave, nombre).
- Matrícula: importe normal, importe de reingreso, periodos de promoción (inicio, fin, importe).
- Plazo de aviso para cambios y bajas (días).
- Penalización: importe y día del mes.
- Día de generación automática de recibos.
- Emails del entrenador.
- Email del club para avisos.
- Datos del club para recibos: nombre, CIF, dirección, logo.
- Enlace a la comunidad de WhatsApp.
- Plazos de conservación: rechazadas, pendientes sin tocar, antiguos socios.
- Artículos del welcome pack.
- Pasos generales de alta, cambio y baja.
- Textos de emails (con marcadores como `{nombre}`, `{bloques_servicios}`, `{enlace_contrasena}`).
- Textos de consentimientos, con versión.
- Avisar por email del recibo mensual (no por defecto).

---

## 5. Alta

### 5.1. Formulario público

Shortcode en una página del club. Campos según D8. Comprobaciones:

- DNI o NIE con letra de control correcta.
- Email con formato válido.
- Si la fecha de nacimiento da menos de 18 años en la fecha de inicio, se piden los datos del tutor y los consentimientos los marca el tutor.
- Servicios: la cuota base va siempre incluida. No se pueden elegir servicios incompatibles (D20). Si un servicio tiene opciones, hay que elegir una.
- Antispam: campo trampa y límite de envíos por IP.

Al enviar: se guarda la solicitud y los consentimientos, se muestra un aviso en pantalla y se envía un email al club (D11). No se crea usuario.

### 5.2. Formulario de gestor

En wp-admin, con los mismos campos. Crea la solicitud y permite aprobarla en el mismo paso.

### 5.3. Aprobación

1. El gestor abre la solicitud. El sistema muestra coincidencias de email y DNI en toda la red (D13).
2. El gestor elige vincular o crear, confirma fecha de alta, servicios y matrícula propuesta (D5).
3. Al aprobar:
   - Se crea o vincula el usuario y se le pone el rol `athlete` en blog 1. Para usuarios nuevos se evita el email estándar de WordPress (D15).
   - Se asigna el número de socio (D14).
   - Se crea el periodo `activo` y los servicios con fecha de inicio = fecha de alta.
   - Se crean los pasos de alta: los generales más los de cada servicio.
   - Si el recibo del mes de alta ya está generado, se genera el recibo del nuevo socio para ese mes. Si no, se incluirá cuando se genere.
   - Se envía el email de bienvenida al socio (y al tutor si es menor).
   - Si algún servicio da acceso a reservas y la fecha de alta es hoy o antes, se escribe `sulayr_user_swimming` y se dispara la sincronización. Si es futura, lo hace la tarea nocturna.
4. Al rechazar: se pide motivo, se envía email al solicitante y la solicitud queda para su borrado (D16).

### 5.4. Primer recibo

Ver 8.2. Ejemplo: alta el 20 de noviembre con entrenamiento genérico y natación, socio normal, sin promoción.

| Línea | Importe |
|---|---|
| Cuota base 22 € × 11/30 | 8,07 € |
| Entrenamiento genérico 11 € × 11/30 | 4,03 € |
| Natación (mes completo) | 25,00 € |
| Matrícula | 20,00 € |
| **Total** | **57,10 €** |

---

## 6. Cambios, congelaciones y bajas

### 6.1. Fecha efectiva

Para una solicitud hecha el día *d* del mes *M*:

- Si faltan al menos *plazo* días para el fin de *M*: día 1 de *M+1*.
- Si no: día 1 de *M+2*.

El gestor puede elegir otra fecha posterior, o aplicar al momento según D27.

### 6.2. Solicitudes desde Mi cuenta

Desde «Mi cuenta → Mis cuotas», el deportista puede pedir:

- Añadir o quitar servicios, o cambiar su opción.
- Baja.

El formulario muestra la fecha efectiva calculada. La solicitud llega al club por email y aparece en «Solicitudes pendientes». El gestor aprueba o rechaza; el deportista recibe email con el resultado. Al aprobar se cierran y abren los servicios o el periodo de estado con esa fecha y se crean los pasos de cambio o baja (p. ej. «Actualizar importe en SportEasy»).

### 6.3. Cambio inmediato (D27)

Solo un gestor, solo si el recibo del mes en curso no está pagado y con confirmación explícita. El servicio empieza hoy. El recibo del mes se recalcula con las reglas del primer mes: los servicios añadidos prorrateables por días restantes, los no prorrateables completos. Queda en el registro con autor y motivo.

### 6.4. Congelación

- La crea un gestor con motivo, fecha de inicio y fecha de fin prevista.
- Empieza el día 1 del mes siguiente, salvo congelación inmediata (D30): en ese caso se anulan solo las líneas de servicios marcados «se anula al congelar» del recibo en curso; el resto sigue y se conserva el acceso a reservas hasta fin de mes.
- Mientras dura, no se generan recibos y `sulayr_user_athlete_status = congelado`, de modo que reservas lo retira.
- Al llegar la fecha de fin, la tarea nocturna lo devuelve a `activo` el día 1 con los servicios que tenía. El gestor puede adelantar o alargar la fecha.

### 6.5. Baja

- Se cierran todos los servicios y el periodo activo en la fecha efectiva, y se abre el periodo `baja`. Se guarda `sulayr_member_leave_date`.
- El usuario conserva el rol `athlete` (para el histórico y Mis cuotas), pero Mi cuenta solo le muestra Mis cuotas y la tienda. Reservas lo retira por el estado.
- Se crean los pasos de baja (p. ej. «Dar de baja en SportEasy», «Quitar de la comunidad de WhatsApp»).
- Se envía email de confirmación.

### 6.6. Reingreso

Una solicitud de alta cuyo email o DNI coincide con un socio de baja se marca como reingreso: se recupera usuario y número, se abre un nuevo periodo activo y se propone la matrícula de reingreso.

---

## 7. Tareas programadas

Una tarea diaria (WP-Cron) en blog 1:

- Aplica los cambios de servicio y de estado cuya fecha efectiva es hoy y actualiza `sulayr_user_swimming` y `sulayr_user_athlete_status` (D38), lanzando la sincronización de reservas.
- El día 1: apunta la retribución de la junta en el monedero (D34).
- El día configurado: genera los recibos del mes si no se han generado (D39).
- Borra las solicitudes caducadas (D16).
- Marca los antiguos socios que superan el plazo de conservación (D36).

Cada acción es idempotente: ejecutarla dos veces no duplica apuntes ni recibos.

---

## 8. Recibos

### 8.1. Generación

«Socios → Recibos → Generar recibos de <mes>». Crea un recibo por cada socio con estado `activo` en el mes (incluidos los de la junta, D50). No crea recibo a congelados ni bajas. Un socio que ya tiene recibo de ese mes no recibe otro.

### 8.2. Cálculo de líneas

Para cada servicio vigente en el mes:

1. Precio = precio vigente para el tipo de socio, o el general (4.3).
2. Si el servicio empezó después del día 1 y se prorratea: importe × días restantes / días del mes, a 2 decimales. Si no se prorratea: importe completo.
3. Si el precio del tipo es más bajo que el general: línea con el precio general y línea «Bonificación <tipo>» con la diferencia (D48).

Después:

- Matrícula, si es el primer recibo tras un alta o reingreso, con el importe aprobado.
- Subvenciones vigentes (D35), como líneas negativas.
- Correcciones pendientes de recibos anteriores (D43).

Si el total es 0, el recibo queda en «no aplica».

### 8.3. Desfase

Si después de generar un recibo cambia algo que afecta al mes (servicios, fechas, subvenciones), el recibo se marca como desfasado y se muestra un aviso con la diferencia. Mientras no esté pagado, el gestor puede regenerarlo. Si está pagado, puede crear una línea de corrección en el recibo siguiente.

### 8.4. Pagos

Desde el recibo, el gestor añade pagos (D41). Con medio «monedero» se crea un cargo en el monedero del socio por ese importe, siempre que haya saldo. El estado se actualiza solo según la suma de pagos.

### 8.5. Penalización

Botón «Aplicar penalización a impagados» desde el día configurado. Añade una línea de penalización a cada recibo pendiente del mes que no la tenga.

### 8.6. PDF

Botón «Ver recibo» en wp-admin y en Mis cuotas. Se genera en el momento (D51) con una librería de PDF compatible con PHP 7.4, instalada con Composer en el plugin. Contiene: «Recibo», número, datos del club, datos del socio (y del tutor si es menor), mes, líneas, total, estado y pagos. Usa la identidad visual Sulayr.

Un deportista solo puede ver sus propios recibos.

### 8.7. Exportación

«Exportar a Excel» del mes: nº socio, nombre, tipo, una columna por línea de concepto, total, pagado, pendiente, estado, medio de pago y comentarios generados (alta, cambios, congelación, baja).

---

## 9. Listados y seguimiento

### 9.1. Tareas pendientes

Vista con todos los pasos pendientes de todos los socios, filtrable por tipo de paso. Cada paso se marca como hecho con fecha y autor.

### 9.2. Listado del entrenador

Ver D54. Incluye a los socios con algún servicio marcado «aparece en el listado del entrenador» vigente hoy. Las novedades se calculan contra la última vez que se marcó como enviado. El texto copiado agrupa por modalidad.

### 9.3. Welcome pack

Ver D55. Desde la ficha del socio: «Registrar entrega» con casillas de artículos y fecha. Vista general con los socios activos sin ninguna entrega.

### 9.4. Monedero

Se mantiene el CPT `sulayr_wallet`. Sus capacidades pasan a ser propias y solo las tienen el administrador y el gestor de socios. Nuevos conceptos automáticos: retribución de la junta y pago de recibo.

---

## 10. Roles y permisos

| Capacidad | Administrador | Gestor de socios | Gestor de tienda | Deportista |
|---|---|---|---|---|
| `sulayr_manage_members` (menú Socios: solicitudes, fichas, catálogo, ajustes, recibos, listados, monedero, tareas) | ✓ | ✓ | | |
| `sulayr_view_member_log` | ✓ | ✓ | | |
| Pedidos, productos, cupones, informes y CSV de Inverse de WooCommerce | ✓ | | ✓ | |
| Asignar roles | ✓ | | | |
| Mi cuenta del deportista | si es `athlete` | si es `athlete` | si es `athlete` | ✓ |

- Los roles `sulayr_member_manager` y `sulayr_shop_manager` se crean solo en blog 1 y se **suman** al rol del usuario.
- En la ficha de usuario, un administrador ve las casillas «Gestor de socios» y «Gestor de tienda».
- Los gestores tienen acceso a wp-admin (WooCommerce no los redirige) y solo ven sus menús.
- El gestor de tienda no recibe `edit_users` ni `manage_woocommerce` completo.
- Las comprobaciones usan capacidades, no nombres de rol.

---

## 11. Migración

Un comando WP-CLI con modo ensayo (por defecto) y modo real.

1. **Desde la web**, para cada `athlete`:
   - `basic` → cuota base. `premium` → cuota base + entrenamiento genérico con su modalidad. `sulayr_user_swimming = 1` → natación. `sulayr_user_is_directive` → tipo «junta».
   - Fecha de inicio de los servicios = `sulayr_user_join_date`. No se reconstruye historial anterior.
   - `standby` → `congelado`. `baja` → `baja`.
2. **Desde el Excel de socios** (exportado a CSV), cruce por email o DNI normalizados (sin espacios ni caracteres invisibles, en mayúsculas):
   - Trae el número de socio y la fecha de baja. «1970» → `desconocida`.
   - Los antiguos deportistas que no están en la web se crean como usuarios sin acceso, con rol `athlete` y estado `baja`.
3. **Informe de discrepancias**: socios sin cruce, datos distintos entre web y Excel, emails mal formados, fechas imposibles (p. ej. 1905), números de socio repetidos.
4. El club revisa el informe, corrige y lanza el modo real.

No se importan «Posibles deportistas» ni las pestañas mensuales de pagos.

---

## 12. Eliminación de Stripe

- Borrar `StripeService`, `SubscriptionService`, `StripeWebhookHandler`, el endpoint REST `sulayr/v1/stripe-webhook`, el botón «Activar suscripción», el endpoint `mis-pagos`, la sección de Stripe de los ajustes y `SubscriptionChangeValidator`.
- Borrar las opciones con las claves de Stripe y los metas `sulayr_stripe_*` y `sulayr_subscription_*` de los usuarios.
- Borrar los productos de tarifas (`_sulayr_membership_type`) y `SubscriptionProducts`. Antes, comprobar que ningún pedido de la tienda los usa; si alguno los usa, pasarlos a borrador en lugar de borrarlos.
- Sustituir los avisos de `AthleteUpdate` (cambio de tarifa, paso a baja) por los de esta spec.
- Quitar el CPT `sulayr_subscription_changes`, que nunca se registró.

---

## 13. Entrega por partes

| Parte | Contenido | Estado |
|---|---|---|
| A | Catálogo y tipos de socio (4.3, 4.4), tablas (4.2), registro de cambios (D58), migración (11), eliminación de Stripe (12), roles (10) y capacidades del monedero (9.4) | En `main` (`fab85c343` y `892d6fcff`) y desplegada en PROD el 11 de noviembre de 2026. Ver sección 16. |
| B | Formulario público y de gestor, aprobación, detección de duplicados y reingresos, consentimientos, email de bienvenida, pasos de alta (5) | En `main` y desplegada en PROD el 12 de noviembre de 2026, configurada y comprobada. Pruebas manuales en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md). Ver sección 18. |
| C | Cambios, bajas, congelaciones, solicitudes desde Mi cuenta, tarea nocturna, sincronización con reservas (6, 7) | En `main` y desplegada en PROD el 12 de noviembre de 2026, configurada y comprobada. Ver sección 19. |
| D | Recibos, PDF, Mis cuotas, monedero de la junta, exportación, listado del entrenador, welcome pack, tareas pendientes (8, 9) | En `main` y desplegada en PROD el 12 de noviembre de 2026, configurada y comprobada. Recibos desde noviembre de 2026. Ver sección 20. |

Cada parte se puede usar en cuanto está terminada. Hasta la parte D, el control de cuotas sigue en el Excel.

---

## 14. Criterios de aceptación

### Parte A

- A1. Crear un servicio nuevo con precio por tipo de socio no requiere tocar código.
- A2. Un cambio de precio con fecha de vigencia futura no altera meses anteriores.
- A3. El ensayo de migración no escribe nada en la base de datos y genera el informe.
- A4. Tras la migración, cada socio activo tiene los servicios equivalentes a su tarifa anterior y reservas mantiene los mismos nadadores.
- A5. No queda código, opción ni meta de Stripe; la tienda sigue funcionando.
- A6. Un gestor de socios que es también deportista entra en wp-admin, ve solo el menú Socios y sigue usando Mi cuenta.
- A7. Un gestor de tienda no puede editar usuarios.
- A8. Un editor no puede crear movimientos del monedero.

### Parte B

- B1. Enviar el formulario no crea usuario y envía un único email, al club.
- B2. Un DNI con letra incorrecta se rechaza.
- B3. Un menor no puede enviar el formulario sin datos del tutor.
- B4. No se pueden elegir dos servicios incompatibles.
- B5. Aprobar una solicitud con email de un socio de baja recupera su cuenta y su número y propone la matrícula de reingreso.
- B6. Aprobar una solicitud con email de un usuario de otra web le añade `athlete` en blog 1 sin crear otra cuenta.
- B7. El socio nuevo recibe un solo email (bienvenida), con el enlace de contraseña y solo los bloques de sus servicios.
- B8. Cada consentimiento queda guardado con su versión y fecha.

### Parte C

- C1. Una solicitud a falta de 4 días de fin de mes calcula la fecha efectiva del mes siguiente al siguiente.
- C2. Una baja aprobada retira al socio de reservas el día efectivo, no antes.
- C3. Una congelación inmediata anula solo la cuota base del recibo en curso y el socio conserva reservas hasta fin de mes.
- C4. Al acabar una congelación, el socio vuelve con los mismos servicios.
- C5. Un cambio inmediato solo es posible si el recibo del mes no está pagado.
- C6. Ejecutar la tarea nocturna dos veces el mismo día no duplica nada.

### Parte D

- D1. El recibo del ejemplo de 5.4 da 57,10 €.
- D2. Un miembro de la junta sin servicios tiene un recibo de 0 € en «no aplica» con la línea de bonificación.
- D3. Un pago parcial con monedero deja el recibo en «pagado parcialmente» y crea el cargo en el monedero.
- D4. Un recibo pagado no se puede editar; la corrección aparece en el recibo siguiente.
- D5. Cambiar un servicio de un mes ya generado marca el recibo como desfasado.
- D6. Un deportista no puede abrir el PDF de otro.
- D7. La retribución de la junta se apunta una sola vez al mes.
- D8. El listado del entrenador marca las altas y bajas desde el último envío.

---

## 15. Tareas del club fuera del código

- Unificar el plazo de 5 días en «Condiciones temporada» y «Bienvenida Sulayr».
- Quitar WodBuster de la Bienvenida: la natación se reserva en CN Nazarí.
- Revisar y cerrar la cuenta de Stripe.
- Redactar la política de privacidad y los textos de consentimiento.
- Exportar a CSV la hoja «Datos de socios» para la migración.

---

## 16. Parte A: implementación

Implementada el 11 de noviembre de 2026 en la rama `altas-socio-parte-a`. Código en `wp-content/plugins/club-triatlon-sulayr/src/Modules/Members/`.

### 16.1. Qué hay

| Pieza | Clase | Notas |
|---|---|---|
| Tablas de §4.2 | `Database\MembersSchema` | Las 11 tablas, con `dbDelta` en `init`. Versión en `sulayr_members_db_version`. Al instalarse marca `sulayr_wc_flush_rewrite_rules` para que desaparezca `/mi-cuenta/mis-pagos/`. |
| Registro de cambios (D58) | `MemberLog` | Solo inserta. Registra metas de la ficha, email, roles y los cambios de servicios y estados. Autor 0 = sistema (WP-CLI). No escribe en un blog sin las tablas (p. ej. reservas tras `switch_to_blog`). |
| Servicios y estados con fechas | `MemberServices`, `StatusPeriods` | Fechas incluidas; fin nulo = vigente. Las filas no se borran. |
| Catálogo (§4.3) | `Catalog\ServicePostType`, `ServiceFields`, `ServiceCatalog` | CPT `sulayr_service` bajo Socios. Su slug es la clave estable. El precio «general» usa la clave `general` (no puede ser un tipo de socio). Validaciones: una sola cuota base publicada y al menos un precio general. |
| Ajustes (§4.4) | `MemberSettings` | Página de opciones ACF «Socios → Ajustes», opciones `sulayr_members_*`. Todo tiene valor por defecto si se deja vacío. |
| Ficha en wp-admin | `MemberFields`, `Admin\MembersMenu` | Grupo ACF «Ficha del socio» solo en `user-edit` (no sale en Mi cuenta). Menú Socios con listado y ficha de solo lectura (servicios, estados y registro), porque el gestor no tiene `edit_users` en multisite. |
| Roles (§10) | `MemberRoles`, `Admin\ManagerAccess` | Roles `sulayr_member_manager` y `sulayr_shop_manager`, sincronizados con versión en `sulayr_member_roles_version`. Casillas en la ficha de usuario solo para administradores; se guardan en `profile_update`, después del desplegable de rol de WordPress, que borraría los roles añadidos. `UserService` ya no saca de wp-admin al deportista que es gestor. |
| Monedero (§9.4) | `WalletPostType`, `WalletBalancePage` | `capability_type` `sulayr_wallet`: solo administrador y gestor de socios. Ahora cuelga de Socios. |
| Migración (§11) | `Migration\LegacyMigrator`, `SheetCsvReader`, `CatalogSeeder`, `MembersCommand`, `Admin\MigrationPage` | `wp sulayr socios migrar` o **Socios → Migración**. Eliminadas tras la migración en PROD (16.6); están en el commit `fab85c343`. |
| Puente transitorio | `Migration\LegacyFieldsSync`, `LegacyMapping` | Hasta la parte C: si un administrador cambia tarifa, modalidad, natación, directiva o estado en la ficha de usuario, se aplica al histórico con fecha de hoy. Solo actúa tras la migración. Desde la parte B solo toca lo que cambió en ese guardado (18.3). |
| Limpieza de Stripe (§12) | `Migration\StripeCleanup` | `wp sulayr socios limpiar-stripe`. Eliminada tras usarla en PROD (16.6). |

Eliminado: `Modules/Subscriptions/*`, el CPT y los campos de `sulayr_subscription_changes`, la sección Stripe y «Gestión de suscripciones» de Sulayr Info, «Mis pagos» y el botón de suscripción de Mi cuenta, el bloqueo de 5 días de `AthleteUpdate` y la librería `stripe/stripe-php` de Composer.

Sin cambiar todavía: los avisos por email de `AthleteUpdate` al cambiar tarifa o pasar a baja. Se sustituyen en la parte C.

### 16.2. Decisiones de implementación

- **Catálogo inicial.** La migración crea `cuota-base` (22 €, junta 0 €), `entrenamiento-generico` (11 €, opciones `sprint` / `olimpico`, listado del entrenador, paso «Comprobar que TrainingPeaks está vinculado») y `natacion` (25 €, junta 29,50 €, no se prorratea, da acceso a reservas), con precios desde el 1 de enero de 2000, y los tipos «normal» y «junta». Los textos de bienvenida son un borrador para editar.
- **Equivalencias.** `basic` → cuota base; `premium` → cuota base + entrenamiento con su modalidad (`olimpic` → `olimpico`); natación `1` → natación; directiva → tipo «junta». Sin tarifa → solo cuota base (aviso). `moroso` → activo (aviso). Estado vacío → `active`.
- **Fechas de ACF.** ACF guarda las fechas como `Ymd`. La migración las convierte a `Y-m-d` para comparar y escribe `Ymd` en las metas de ACF.
- **Bajas.** Con fecha conocida: periodo activo hasta esa fecha, baja desde el día siguiente y servicios cerrados en esa fecha. Con fecha desconocida («1970» o sin dato): solo un periodo de baja sin fecha y sin servicios.
- **Congelados** (antes `standby`): periodo activo hasta ayer y congelado desde el día de la migración, con el motivo «Migrado desde Stand By» para revisarlo.
- **Números de socio.** Los del Excel, que son el ID de usuario. Si faltan, el ID de usuario; si está ocupado, el siguiente al mayor (D14). Un número repetido de la misma persona en las dos pestañas no es error.
- **Personas en las dos pestañas.** Se cruzan todas sus filas por DNI o email. Manda la de «Antiguos» si en la web está de baja; si no, la de «Socios». Lo que falte se completa con la otra fila.
- **Solo en «Antiguos deportistas»** y activo en la web: se migra como baja (decidido el 11 de noviembre de 2026: Bettina Hosso y Ramón López Martín ya no son socios).
- **Fecha de baja del Excel.** La fecha de baja es el último día del mes en que el socio deja el club (31/08/2023). Las fechas en día 1 que aparecen desde 2024 (1/07/2026) eran un error de anotación: se guardan como el día anterior, el último día del mes previo. Confirmado por el club el 11 de noviembre de 2026.
- **Filas con columnas corridas** (la fecha de alta es la edad): la fecha de baja de esa fila se guarda como desconocida.
- **Fechas con año de dos cifras** (`09/09/25`, como exporta ahora la hoja): se leen con el siglo actual y, si así quedarían en el futuro, con el anterior (`02/06/65` → 1965).
- **Varias filas de la misma persona en «Antiguos»** (p. ej. dos etapas en el club): una fecha de baja real gana a «desconocida». Solo se guarda la última etapa.
- **Bajas avisadas** (fecha de baja futura y socio no de baja): sigue en su estado y se guarda una solicitud de baja aprobada con su fecha efectiva; si está congelado, la congelación lleva esa fecha como fin previsto. La aplica la parte C. Caso real: Diego Lardón, baja el 1 de enero de 2027.
- **Fecha de alta distinta entre web y Excel.** Manda la web, salvo los usuarios que se pasen en `--alta-excel` (decidido: #17, #40 y #63).
- **Reservas.** La migración no cambia `sulayr_user_swimming` ni pone `active` donde reservas ya lo tenía; avisa si un socio con natación y estado vacío pasa a `active`.

### 16.3. Comprobado en local (11 de noviembre de 2026)

Con una copia de la base de datos, CSV de prueba con el formato de la hoja real y restauración al terminar:

- A1: servicio y precios por tipo creados desde el catálogo; precio de la junta correcto.
- A2: un precio nuevo con fecha 1 de enero de 2027 no cambia noviembre ni diciembre de 2026.
- A3: el ensayo no escribe nada; el informe detecta números repetidos, emails «.con», fechas imposibles (1905), DNI raros, fechas «1970», socios del Excel que no están en la web y diferencias de datos.
- A4: 58 deportistas migrados; solo cambian de estado `standby` → `congelado` y un vacío → `active` sin natación. La tabla de nadadores sigue con los mismos 29.
- Repetir la migración no crea, numera ni duplica nada.
- A5: sin opciones, metas ni productos de Stripe tras la limpieza; portada, tienda, carrito y Mi cuenta responden 200; `/mi-cuenta/mis-pagos/` da 404.
- A6: un deportista con «Gestor de socios» entra en wp-admin y ve Escritorio, Socios y Perfil; abre listado, ficha con registro, servicios, ajustes, monedero, «Añadir movimiento» y «Añadir servicio»; no abre Usuarios ni Pedidos; sigue usando Mi cuenta.
- A7: el gestor de tienda abre Pedidos y Productos, no abre Ajustes de WooCommerce, Usuarios ni Socios, y no puede editar a otro usuario.
- A8: el rol editor no tiene ninguna capacidad del monedero.
- Casillas de roles: el administrador añade y quita; el desplegable de rol no las borra; un gestor no puede asignarlas; todo queda en el registro.
- Puente transitorio: cambiar la tarifa en la ficha cierra natación ayer y abre entrenamiento hoy; quitar «directiva» cambia el tipo a normal; pasar a baja cierra servicios y guarda la fecha de baja.

Migración real en local con la hoja de Drive (11 de noviembre de 2026, `--alta-excel=17,40,63`): 58 deportistas migrados y 20 antiguos socios creados. Estados: 43 activos, 1 congelado y 34 de baja (78 socios; 17 bajas con fecha y 17 con fecha desconocida). Repetida tras corregir el Excel: Bettina Hosso y Ramón López Martín, de baja desde el 1 de noviembre de 2026; Antonio Valero, del 12 de mayo al 30 de junio de 2026. Servicios vigentes: 44 cuotas base, 11 entrenamientos sprint y 27 de natación. Ningún número repetido ni socio sin número. La baja avisada de Diego Lardón está guardada. La tabla de nadadores no cambia hasta sincronizar. Quedan 6 errores esperados en local: socios del Excel que solo existen en PROD.

No comprobado: el paso a borrador de un producto de tarifa que aparezca en pedidos (en local no hay ninguno).

### 16.4. Despliegue y puesta en marcha

Así se hizo el 11 de noviembre de 2026. La pantalla Socios → Migración y los comandos ya no existen (16.6); para otra importación habría que recuperarlos del commit `fab85c343`.

PROD está en Hostinger, sin SSH ni WP-CLI. El código se sube con FileZilla y la migración se hace desde **Socios → Migración** (`Admin\MigrationPage`, solo administradores), que hace lo mismo que `wp sulayr socios migrar` y `wp sulayr socios limpiar-stripe`. Los comandos de WP-CLI siguen sirviendo en local.

**Antes**

1. Copia de seguridad: en hPanel, Copias de seguridad (archivos y base de datos), o descargar con FileZilla la carpeta `wp-content/plugins/club-triatlon-sulayr/` y exportar la base de datos desde phpMyAdmin.
2. Elegir una hora con poco uso: durante la subida, la web puede dar error unos segundos si un archivo llega antes que otro del que depende.

**Subir el código (FileZilla)**

3. Subir la carpeta nueva `wp-content/plugins/club-triatlon-sulayr/src/Modules/Members/` completa. Sola no cambia nada: nadie la usa todavía.
4. Subir el resto de la carpeta del plugin sobrescribiendo: `club-triatlon-sulayr.php`, `composer.json`, `composer.lock`, `src/`, `templates/`, `vendor/`, `MANUAL-DOCUMENTOS.md`.
5. Borrar en el servidor lo que ya no existe (FileZilla no lo borra al subir):
   - `src/Modules/Subscriptions/` (carpeta entera)
   - `src/Modules/PostTypes/SubscriptionChangesPostType.php`
   - `src/Modules/Fields/SubscriptionChangesFields/` (carpeta entera)
   - `templates/admin/stripe-settings.php`
   - `templates/admin/subscriptions-management.php`
   - `templates/parts/content-payment-history.php`
   - `templates/parts/content-subscription-activation.php`
   - `vendor/stripe/` (carpeta entera)

   Borrarlos después del paso 4, no antes: el código antiguo los usa.
6. Entrar en wp-admin del club. La primera carga crea las tablas, los roles y regenera los enlaces (desaparece `/mi-cuenta/mis-pagos/`). Comprobar que aparece el menú **Socios** y que la portada, la tienda y Mi cuenta cargan.

**Limpiar Stripe (Socios → Migración)**

7. «1. Limpiar Stripe» sin marcar la casilla: revisar la lista. Repetir marcando «Borrar de verdad».

**Migrar socios (Socios → Migración)**

8. En la hoja «Datos de socios» de Drive: Archivo → Descargar → Valores separados por comas (.csv), primero con la pestaña «Datos de los socios» activa y después con «Antiguos deportistas».
9. «2. Migrar socios»: subir los dos CSV, escribir `17,40,63` en «Usar la fecha de alta del Excel en» y enviar **sin** marcar la casilla (ensayo). Revisar el informe: arriba salen los errores, luego los avisos. En PROD no deben salir los 6 socios «sin cuenta en la web» del ensayo local. Si hay algo que corregir, corregirlo en la web o en la hoja, descargar de nuevo y repetir el ensayo.
10. Con el informe limpio, repetir marcando «Migrar de verdad».
11. Comprobar en **Socios** varios socios: número, tipo, estado, servicios y estados. Diego Lardón: congelado, con fin previsto el 31 de diciembre de 2026.

**Después**

12. Solo si el informe dice que alguien pasa a baja o que un socio con natación pasa a activo: en el wp-admin del club, Usuarios → editar a ese deportista → «Sincronizar ahora en reservas» (o guardar su ficha, que también sincroniza). No hay botón general. En el despliegue del 11 de noviembre de 2026 no hizo falta.
13. Revisar en Socios el motivo y la fecha prevista de los congelados, los socios sin tarifa, los textos de bienvenida de cada servicio y **Socios → Ajustes** (enlace de WhatsApp, emails del entrenador, datos del club).
14. Marcar «Gestor de socios» o «Gestor de tienda» a quien corresponda, en la ficha de cada usuario.
15. Borrar los CSV descargados y los de `docs/club/altas-y-ciclo-de-vida-socio/`: tienen datos personales y no deben llegar a un commit.
16. Actualizar el estado de esta spec y de `docs/FASES.md` con la fecha del despliegue.

### 16.5. Despliegue en PROD (11 de noviembre de 2026)

- Código subido con FileZilla. Después quedó en `main`: `fab85c343` (lo desplegado) y `892d6fcff` (retirada de la migración, 16.6).
- Limpieza de Stripe desde Socios → Migración: 3 opciones, 7 metas de usuario y los 4 productos de tarifa (#2425 a #2428) borrados. Ningún producto estaba en pedidos.
- Migración con `17,40,63`: 61 deportistas migrados, 21 antiguos socios creados, 25 números nuevos (su ID de usuario), 29 avisos y 2 errores.
- David Sánchez Cabrerizo y Lorena Montellano Peláez (los 2 errores) son deportistas externos: no se migran y se quedan solo en el Excel, que deja de mantenerse (decidido el 11 de noviembre de 2026). La migración está terminada.
- 14 antiguos socios no se crearon por no tener email ni DNI en el Excel.
- Reservas no cambió: ningún socio cambió de condición para reservar.

### 16.6. Retirada del código de migración

Con la migración terminada en PROD, se quitan la pantalla **Socios → Migración**, los comandos `wp sulayr socios` y las clases `LegacyMigrator`, `SheetCsvReader`, `CatalogSeeder`, `MembersCommand` y `StripeCleanup`. Quedan en el commit `fab85c343`. Se mantienen `LegacyFieldsSync` y `LegacyMapping` hasta la parte C; la opción `sulayr_members_migrated_at` sigue activando el puente.

Para llevarlo a PROD con FileZilla, en `wp-content/plugins/club-triatlon-sulayr/`:

1. Subir, sobrescribiendo: `src/App/App.php`, `src/Modules/Members/Admin/MembersMenu.php`, `src/Modules/Members/Migration/LegacyFieldsSync.php` y `src/Modules/PostTypes/WalletPostType.php`.
2. Después, borrar del servidor: `src/Modules/Members/Admin/MigrationPage.php` y, en `src/Modules/Members/Migration/`, `LegacyMigrator.php`, `SheetCsvReader.php`, `CatalogSeeder.php`, `MembersCommand.php` y `StripeCleanup.php`.
3. Comprobar que el menú Socios ya no tiene «Migración» y que el monedero sale agrupado.

---

## 17. Traspaso para las partes B a D

Estado al 11 de noviembre de 2026: parte A en `main` y en PROD, migración terminada. Lo que sigue es lo que había que saber antes de empezar la parte B. La parte B ya está hecha (sección 18): el riesgo del puente de 17.2 está resuelto y la validación del DNI está en `Join\Identity`. Lo que queda para las partes C y D está en 18.7.

### 17.1. Lo que ya existe y la parte B debe usar

- **Tablas** (`Database\MembersSchema`): `sulayr_member_requests` (tipo, usuario, estado, `data` en JSON, fecha efectiva, quién resolvió, motivo, IP), `sulayr_member_consents` y `sulayr_member_tasks` ya están creadas y vacías, salvo una solicitud de baja aprobada de Diego Lardón (efecto 1 de enero de 2027, origen `migracion`). Si la parte B necesita columnas nuevas, subir `MembersSchema::VERSION`.
- **Ajustes** (`MemberSettings::get()`): tipos de socio, matrícula normal y de reingreso, promociones, email del club, enlace de WhatsApp, pasos de alta, textos y asuntos de los emails de bienvenida, rechazo y aviso al club (con marcadores `{nombre}`, `{numero_socio}`, `{fecha_alta}`, `{enlace_contrasena}`, `{enlace_whatsapp}`, `{bloques_servicios}`, `{motivo}`, `{tipo}`, `{nombre_completo}`), textos y versiones de los tres consentimientos, y plazos de conservación. Todo tiene valor por defecto.
- **Catálogo** (`ServiceCatalog`): cada servicio dice si sale en el formulario público, sus opciones, con qué es incompatible, su texto de bienvenida y sus pasos de alta. `ServiceCatalog::base()` da la cuota base.
- **Ficha** (`MemberFields`): número (`MemberFields::next_number()` da el siguiente al mayor, D14), tipo, fecha de baja, talla (XS a XXL), retribución de la junta y tutor (grupo ACF `sulayr_member_guardian`: `_name`, `_dni`, `_phone`, `_email`).
- **Historial** (`MemberServices::open()`, `StatusPeriods::open()`) y **registro** (`MemberLog`).
- **Validación de DNI/NIE**: la función de letra de control estaba en `SheetCsvReader::is_valid_dni()`, que se retiró con la migración (commit `fab85c343`). Recuperarla en una clase propia. Hecho en la parte B: `Join\Identity`.

### 17.2. Cuidado en la parte B

- **Puente transitorio** (resuelto en la parte B, ver 18.3). `LegacyFieldsSync` se dispara en `acf/save_post` de cualquier deportista y cierra todo servicio vigente que no se deduzca de los campos antiguos. Con el formulario nuevo eso rompería altas (p. ej. un servicio que no sea cuota base, entrenamiento genérico o natación). Primera tarea de la parte B: que el puente solo toque las filas de esos tres servicios, o que la aprobación escriba también los campos antiguos de forma coherente (tarifa, modalidad, natación, directiva). En cualquier caso, la aprobación debe escribir `sulayr_user_swimming` y `sulayr_user_athlete_status` (D38).
- **Usuarios en multisite.** El email es único en toda la red. `UserService::assign_athlete_role` pone `athlete` en `user_register` si el usuario no tiene rol o es `subscriber`. Reservas crea usuarios en el blog 2 con rol `nadador`. Para «vincular» a un usuario de otra web usar `add_user_to_blog()` o `add_role()`, sin crear otra cuenta (D13).
- **Email de bienvenida (D15).** `wp_insert_user()` no envía aviso; el enlace de contraseña se genera con `get_password_reset_key()`. No usar `wp_new_user_notification()` ni `user-new.php`, que en multisite pide confirmación. Remitente: `Modules\Emails\EmailConfig`.
- **Avisos antiguos.** `AthleteUpdate` sigue enviando emails al cambiar tarifa o pasar a baja desde la ficha de usuario; se sustituyen en la parte C. Escribir metas con `update_user_meta()` no los dispara.
- **Front.** Un deportista con sesión iniciada es redirigido fuera de `wp-admin` (`UserService::redirect_athlete_to_profile`), así que los formularios del front no deben enviar a `admin-post.php`. El formulario público es para visitantes sin sesión. Identidad visual: `docs/club/identidad-sulayr.html`.
- **Pantallas de gestión** bajo el menú Socios (`Admin\MembersMenu`), con la capacidad `sulayr_manage_members`. El gestor no tiene `edit_users`.
- **PROD sin WP-CLI.** Cualquier tarea puntual en PROD tiene que poder hacerse desde wp-admin (ver «Production» en `AGENT.md`).

### 17.3. Pruebas en local

No hay PHPUnit. Lo que funcionó en la parte A:

- Copia de la base con `wp db export` antes de probar y `wp db import` al terminar.
- Usuarios temporales (administrador, gestor) y peticiones con `curl` iniciando sesión en `wp-login.php`; borrarlos al terminar.
- `add_filter( 'pre_wp_mail', ... )` en `wp eval` para no enviar emails reales. Los avisos de WP-CLI con PHP 8.5 son ruido.
- Desde WP-CLI, `is_admin()` es falso y `UserService` bloquea los campos de tarifa: para simular cambios del gestor, escribir las metas con `update_user_meta()`.

### 17.4. Abierto

- **Deportistas externos.** David Sánchez Cabrerizo y Lorena Montellano Peláez no están en la web y se quedan solo en el Excel. Si se quisiera distinguir «externo» como tipo de socio, sería una decisión nueva.

---

## 18. Parte B: implementación

Implementada el 11 de noviembre de 2026 en la rama `altas-socio-parte-b`, creada desde `main`. Probada en local por HTTP. En `main` desde el 12 de noviembre de 2026 (merge `21914c88d`, junto con C y D) y subida a PROD ese día. Las pruebas manuales que faltan (navegador, emails reales, PROD) están en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md).

### 18.1. Decisiones cerradas con el club (11 de noviembre de 2026)

La spec no fijaba estos puntos. Se decidieron con el club antes de implementar:

| # | Decisión |
|---|---|
| B-a | La aprobación lanza la sincronización con reservas sea quien sea quien apruebe (también un gestor de socios sin `manage_options`). El plugin del club llama directamente a la sincronización de `sulayr-reservas`, sin cambiar reservas. |
| B-b | Alta con fecha posterior a hoy: el socio queda en `pendiente`, sin natación, hasta esa fecha. Una tarea diaria mínima lo activa ese día (estado, natación y sincronización) y caduca las solicitudes (D16). La parte C la amplía. |
| B-c | Si el email o el DNI son de un socio en vigor (activo, congelado o pendiente), no se puede aprobar: solo rechazar. |
| B-d | El email es único en la red. «Es otra persona» con el mismo email no se puede aprobar: el gestor corrige los datos de la solicitud (incluido el email) antes de aprobar. Si solo coincide el DNI, «otra persona» crea una cuenta nueva. |
| B-e | Reingreso durante una promoción de matrícula: se propone el importe más bajo de los dos. |
| B-f | El socio da o revoca el consentimiento de imagen desde Mi cuenta → Perfil de deportista (parte B). |
| B-g | En el formulario del gestor hay las mismas casillas de consentimiento, con las obligatorias obligatorias. El gestor confirma que el socio o el tutor ha aceptado. Se guarda «socio» o «tutor» y el gestor como autor. |
| B-h | En la parte B los pasos se ven y se marcan en la ficha del socio. La vista general «Tareas pendientes» (9.1) es de la parte D. |
| B-i | Vinculados y reingresos reciben el mismo email de bienvenida: el enlace sirve para crear o cambiar la contraseña. |
| B-j | Las solicitudes creadas por un gestor no avisan al club (D11 es para el formulario público). |
| B-k | Límite de 3 solicitudes por hora desde una misma IP, configurable en Socios → Ajustes. |
| B-l | Un socio en vigor con sesión iniciada ve «Ya eres socio» en el formulario. Cualquier otro usuario (cliente de la tienda, nadador, antiguo socio) puede enviarlo, y al aprobar se vincula su cuenta. |
| B-m | El enlace de contraseña de la bienvenida caduca a las 24 horas, como todos los de WordPress (`password_reset_expiration`). Pasado ese tiempo, el socio usa «¿Has olvidado tu contraseña?». No se alarga porque afectaría a todos los enlaces de la web. |

### 18.2. Qué hay

Código en `wp-content/plugins/club-triatlon-sulayr/src/Modules/Members/`, conectado en `App::load_members()`.

| Pieza | Clase | Notas |
|---|---|---|
| Formulario público (5.1) | `Join\PublicJoinForm` | Shortcode `[sulayr_solicitud_alta]`. Envía a la propia página (no a `admin-post.php`) y no usa nonce, para que una página en caché no lo rompa. El envío se reconoce por un campo oculto, así que funciona aunque el shortcode esté en una plantilla de Templatera. Antispam: campo trampa y límite por IP. Pide a los plugins de caché que no guarden la página (`DONOTCACHEPAGE`). |
| Campos y validación (D8, 5.1) | `Join\JoinForm`, `Join\Identity`, `public/js/join-form.js` | Los comparten los dos formularios. DNI/NIE con letra de control, email, teléfono (9 cifras), fechas, menor de 18 en la fecha de inicio → datos del tutor, cuota base siempre incluida, incompatibles en los dos sentidos, opción obligatoria si el servicio tiene opciones. El JavaScript solo ayuda (tutor, incompatibles, opciones): el servidor vuelve a comprobarlo todo. Talla de camiseta de `MemberFields::TSHIRT_SIZES`. |
| Solicitudes | `Join\JoinRequests` | Tabla `sulayr_member_requests`, tipo `alta`. Estados `pendiente`, `aprobada`, `rechazada` y `caducada`. Datos del formulario en `data` (JSON). Al aprobar se añade `approval`: modo, usuario, reingreso, tipo, número, matrícula propuesta y aprobada y su motivo. La parte D leerá de ahí la matrícula del primer recibo. |
| Duplicados (D13, 6.6) | `Join\DuplicateFinder` | Email y DNI en toda la red: usuarios y metas de red, y el DNI de la ficha local de reservas (solo lectura). Clasifica cada cuenta como socio en vigor, antiguo socio (reingreso) u otro usuario de la red. Avisa también de otras solicitudes pendientes con el mismo email o DNI. |
| Aprobación y rechazo (5.3) | `Join\JoinApproval` | Ver 18.3. |
| Pantallas de gestión | `Admin\RequestsPage` | **Socios → Solicitudes** (con globo de pendientes, que también sale en el menú padre **Socios**): listado por estado y detalle con coincidencias, datos editables, aprobación y rechazo. **Socios → Nueva alta** (5.2): mismos campos. «Crear solicitud» o «Crear y aprobar»: si nadie de la red tiene ese email ni ese DNI, aprueba en el mismo paso; si hay coincidencias, lleva a la solicitud para decidir. Envían a `admin-post.php` con nonce y `sulayr_manage_members`. |
| Consentimientos (D10, B8) | `MemberConsents` | Cada aceptación o revocación es una fila nueva con tipo, versión del texto, aceptado, quién (socio o tutor), autor y fecha. Al aprobar se pasan de la solicitud al socio y se anotan en el registro. Textos y versiones de Socios → Ajustes. |
| Consentimiento de imagen en Mi cuenta | `Join\ImageConsentAccount` | Casilla bajo el formulario de Perfil de deportista (gancho nuevo `sulayr_athlete_profile_edit_after` en `templates/parts/content-athlete-profile.php`). Con nonce. |
| Pasos (D9, D12) | `MemberTasks` | Al aprobar: los pasos de alta de Socios → Ajustes más los de cada servicio, sin repetir. En la ficha del socio, botón «Marcar hecho» (fecha y autor). |
| Emails (D11, D15) | `Join\MemberMailer` | Asunto y texto de Socios → Ajustes con sus marcadores. HTML. Bienvenida, rechazo y aviso al club. |
| Metas de reservas (D38) | `ReservationsAccess`, `Join\ReservationsSync` | Escribe `sulayr_user_athlete_status` (periodo que cubre hoy, o `pendiente` si solo hay uno futuro) y `sulayr_user_swimming` (`1` si está activo y tiene vigente un servicio que da acceso a reservas). Si cambia algo, lanza la sincronización de reservas llamando a `SulayrReservas\Sync\SyncService::sync()`, si el plugin está activo. |
| Tarea diaria (B-b) | `Join\JoinDailyTask` | WP-Cron `sulayr_members_daily`, a las 00:15 hora del club. Aplica `ReservationsAccess` a los socios `pendiente` y caduca solicitudes (D16). Repetirla el mismo día no cambia nada. |
| Ficha del socio | `Admin\MembersMenu` | Añade talla, tutor, pasos, consentimientos y enlaces a las solicitudes. El listado avisa de las solicitudes pendientes. |
| Ajustes | `MemberSettings` | Pestaña nueva «Formulario de alta»: texto encima del formulario, mensaje tras enviar y límite por IP. |
| Esquema | `Database\MembersSchema` | Versión 2: `updated_at` en solicitudes (para caducar las pendientes sin tocar) y `created_by` en consentimientos. Se aplica sola en la primera carga. |

### 18.3. Decisiones de implementación

- **Puente transitorio (17.2).** `LegacyFieldsSync` guarda los campos antiguos antes de que ACF guarde (prioridad 1 de `acf/save_post`) y solo actúa sobre lo que cambió: tarifa o modalidad → solo el entrenamiento genérico; natación → solo la natación; directiva → tipo de socio, y solo si el tipo es «normal» o «junta»; estado → periodo de estado. Una baja sigue cerrando todos los servicios. La vuelta desde una baja reabre los tres servicios antiguos. Guardar Mi cuenta no cambia nada del historial. Además, la aprobación escribe la tarifa, la modalidad y la directiva antiguas de forma coherente (`LegacyMapping::write_plan_fields()`), para que la ficha de usuario y Mi cuenta muestren lo mismo que el historial.
- **D33 en Mi cuenta.** El deportista podía cambiarse el estado desde Mi cuenta: el desplegable no estaba bloqueado y el servidor lo aceptaba. Con el puente, ponerse «baja» le cerraba los servicios y reservas le quitaba el acceso. Venía de antes de la parte B. Ahora el estado, el DNI y la fecha de nacimiento quedan bloqueados en Mi cuenta, con el mismo mecanismo que la tarifa (`UserService`).
- **Aprobación.** Comprueba la decisión antes de tocar nada (socio en vigor, email libre al crear, cuenta elegida entre las coincidencias, fecha, tipo, servicios y motivo si la matrícula no es la propuesta). Después marca la solicitud como «aprobando» con una actualización condicionada, para que dos clics no la aprueben dos veces. Todo se anota en el registro con la nota «Alta, solicitud #N».
- **Cuenta nueva.** `wp_insert_user()` con rol `athlete`, sin email de WordPress. El usuario sale del email y solo lleva minúsculas y números (regla de multisite), con un número detrás si ya existe.
- **Vincular.** Si la cuenta no es de la web del club, `add_user_to_blog()` con `athlete`; si lo es, `add_role( 'athlete' )`. Si el email de la cuenta es otro, el gestor puede marcar «Cambiar el email de esa cuenta». En ese caso no se envía el aviso de cambio de email de WordPress, para que el socio reciba un solo email. Nombre y apellidos se toman de la solicitud.
- **Reingreso.** Conserva el número y cierra el periodo de baja el día antes del alta. Borra la fecha de baja de la ficha: queda en los periodos. Abre un periodo activo y los servicios nuevos.
- **Ficha.** Teléfono, DNI, nacimiento, fecha de alta (= fecha de alta aprobada), talla, tipo y, si es menor, tutor. Número de socio: el que tenga, o el siguiente al mayor (D14).
- **Matrícula propuesta.** Normal o de reingreso. Si hay una promoción vigente el día de la aprobación, su importe; en un reingreso, el más bajo de los dos. Si el gestor deja la matrícula vacía, se usa la propuesta. Se guarda en la solicitud y en el registro. El cobro llega con los recibos (parte D).
- **Bienvenida.** Un solo envío al socio y, si es menor, también al tutor. El enlace de contraseña lleva a Mi cuenta → contraseña perdida de WooCommerce (`key`, `id` y `login`), que abre el formulario de nueva contraseña. `{bloques_servicios}` junta el texto de bienvenida de los servicios contratados.
- **Rechazo.** Motivo obligatorio, que se envía al solicitante (y al tutor).
- **Caducidad (D16).** Las rechazadas tras su plazo y las pendientes sin tocar tras el suyo pierden los datos personales, la IP y el motivo. Queda la fila con tipo, estado, fechas, origen, menor sí o no y servicios elegidos. Las pendientes pasan a «caducada». Los consentimientos de la solicitud (tipo, versión, sí o no, fecha) se conservan. La baja migrada de Diego Lardón (tipo `baja`) no se toca.
- **5.3, recibo del mes de alta.** No se hace en la parte B: los recibos llegan en la parte D.

### 18.4. Comprobado en local (11 de noviembre de 2026)

Con una copia de la base de datos (`wp db export`), un mu-plugin temporal que no enviaba emails y los guardaba en una opción, usuarios temporales (administrador y deportista con «Gestor de socios», sin `manage_options`) y peticiones HTTP con `curl` iniciando sesión. En local no existían los servicios del catálogo (IDs 2655 a 2657); para probar se recrearon en la copia, con un servicio «Entrenamiento individualizado» incompatible con el genérico. Al terminar se restauró la base, se borró el mu-plugin y los usuarios y la página de prueba desaparecieron con la restauración.

- B1: el formulario público guarda la solicitud y sus consentimientos, no crea usuario y envía un único email, al club.
- B2: un DNI con letra incorrecta se rechaza.
- B3: un menor sin datos del tutor no se puede enviar. Con tutor, los consentimientos quedan como «tutor».
- B4: dos servicios incompatibles se rechazan, y también un servicio con opciones sin opción elegida.
- B5: una solicitud con el email de un antiguo socio (de baja) propone vincular su cuenta como reingreso. Al aprobar recupera la cuenta y el número, propone 10 € de matrícula, cierra la baja el día anterior y vuelve a reservas.
- B6: una solicitud con el email de un usuario que solo existía en la web de reservas le añade `athlete` en el club, sin otra cuenta (el número de usuarios no cambia).
- B7: el socio nuevo recibe un solo email, de bienvenida, con el enlace de contraseña (abre el formulario de nueva contraseña de Mi cuenta) y solo los textos de sus servicios.
- B8: cada consentimiento queda con su versión, fecha, socio o tutor y autor (0 en el formulario público, el gestor en el suyo).
- Aprobación por un gestor de socios sin `manage_options`: el socio con natación queda en reservas como nadador activo.
- Fecha de alta futura: queda `pendiente` sin natación. Simulando que llega el día, la tarea diaria lo activa y lo sincroniza. La segunda ejecución no hace nada.
- Email de un socio activo: no se puede aprobar, ni siquiera forzando la petición. Rechazar envía el motivo al solicitante.
- «Crear y aprobar» sin coincidencias aprueba en un paso. Una matrícula distinta de la propuesta sin motivo se rechaza antes de crear la solicitud.
- Caducidad: rechazada de 31 días y pendiente de 61 quedan sin datos; la solicitud reciente y la baja migrada no cambian.
- Campo trampa: responde como si fuera bien y no guarda nada. La cuarta solicitud en una hora desde la misma IP se rechaza.
- Mi cuenta: dar y revocar el consentimiento de imagen crea filas nuevas; un nonce falso da 403. Un socio dado de alta con «Entrenamiento individualizado» guarda su perfil y no se le cierra nada. Un envío manipulado con estado «baja» ya no cambia el estado.
- Puente: con un socio migrado, guardar sin cambios no toca nada; quitar la natación cierra solo la natación; marcar directiva no cambia un tipo «menor».
- Pantallas de Socios (listado, ficha, solicitudes, detalle, nueva alta, ajustes) responden 200 sin avisos de PHP, como gestor y como administrador. «Marcar hecho» guarda fecha y autor. Un deportista sin rol de gestor no puede aprobar.

No comprobado:

- El formulario en un navegador (aspecto en móvil y el JavaScript): las pruebas fueron por HTTP. No hay Node en local para revisar el JavaScript.
- PHP 7.4: no está instalado en local. Se revisó a mano que no hay sintaxis de PHP 8.
- El envío real de emails en PROD (WP Mail SMTP) y cómo se ven en un cliente de correo.

### 18.5. Despliegue en PROD

**Desplegado el 12 de noviembre de 2026** junto con B, C y D, siguiendo [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md) (pasos 1 a 8 hechos; primer mes con recibos: noviembre de 2026). Lo que sigue queda como referencia.

Para subir B, C y D juntas, que es lo previsto, sigue [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md), que tiene el cambio neto de archivos. Lo que sigue es para subir solo la parte B.

Con FileZilla, en `wp-content/plugins/club-triatlon-sulayr/`:

1. Copia de seguridad (hPanel o FileZilla + phpMyAdmin), como en 16.4.
2. Subir primero los archivos nuevos:
   - `src/Modules/Members/Join/` (carpeta entera: `DuplicateFinder.php`, `Identity.php`, `ImageConsentAccount.php`, `JoinApproval.php`, `JoinDailyTask.php`, `JoinForm.php`, `JoinRequests.php`, `MemberMailer.php`, `PublicJoinForm.php`, `ReservationsSync.php`)
   - `src/Modules/Members/Admin/RequestsPage.php`
   - `src/Modules/Members/MemberConsents.php`, `MemberTasks.php`, `ReservationsAccess.php`
   - `public/js/join-form.js`
3. Después, sobrescribir:
   - `src/App/App.php`
   - `src/Services/UserService.php`
   - `src/Modules/Members/Admin/MembersMenu.php`
   - `src/Modules/Members/Database/MembersSchema.php`
   - `src/Modules/Members/MemberFields.php`, `MemberSettings.php`, `StatusPeriods.php`
   - `src/Modules/Members/Migration/LegacyFieldsSync.php`, `LegacyMapping.php`
   - `templates/parts/content-athlete-profile.php`
   - `public/css/styles.css`
4. No hay nada que borrar.
5. Entrar en wp-admin del club. La primera carga sube el esquema a la versión 2 y programa la tarea diaria. Comprobar que salen **Socios → Solicitudes** y **Socios → Nueva alta**.
6. En **Socios → Ajustes**: enlace de WhatsApp (si está vacío, la bienvenida deja la línea sin enlace), email del club, textos y versiones de los consentimientos (los redacta el club, sección 15), pestaña «Formulario de alta» y textos de los emails. Revisar el texto de bienvenida y los pasos de cada servicio en el catálogo.
7. Crear una página del club con el shortcode `[sulayr_solicitud_alta]` y enlazarla donde corresponda.
8. Prueba en PROD con un email propio: enviar una solicitud, comprobar el aviso al club, rechazarla o aprobarla y revisar la bienvenida. En **Socios → Solicitudes**, abrir la solicitud y comprobar que la IP no es la misma en todas (ver 18.6).
9. Actualizar el estado de esta spec y de `docs/FASES.md` con la fecha.

### 18.6. Abierto

- **IP detrás de un proxy.** El límite usa la IP que ve el servidor (`REMOTE_ADDR`), sin fiarse de cabeceras. Si Hostinger pone un proxy o una CDN delante, todas las solicitudes llevarían la misma IP y el límite sería para todos a la vez. Comprobarlo en el paso 8 de 18.5.
- **WP-Cron.** La tarea diaria corre con las visitas a la web. Un día sin visitas se ejecuta con la primera visita del día siguiente.

### 18.7. Para las partes C y D

La parte C ya está hecha (sección 19); lo que sigue para la D está en 19.7.


- `ReservationsAccess::apply()` es el punto único para escribir las metas de reservas a partir del historial. La tarea nocturna de la parte C puede llamarlo para cada socio con un cambio que empiece ese día.
- `Join\JoinDailyTask` es la tarea diaria: la parte C le añade cambios, congelaciones, bajas y la retribución de la junta (7).
- `LegacyFieldsSync`, `LegacyMapping` (incluido `write_plan_fields()`) y los avisos de `AthleteUpdate` se retiran en la parte C, que sustituye la edición de la ficha de usuario.
- `MemberTasks::create()` ya admite los contextos `cambio` y `baja`; los pasos de cambio y de baja están en Socios → Ajustes.
- La matrícula aprobada está en `data.approval.fee` de la solicitud aprobada (`user_id`). La parte D la añade al primer recibo; si el recibo del mes de alta ya existe, lo genera al aprobar (5.3).
- La vista general de pasos pendientes (9.1) es de la parte D.

---

## 19. Parte C: implementación

Implementada el 11 de noviembre de 2026 en la rama `altas-socio-parte-c`, creada desde el commit de la parte B (`f5450b29a`). Probada en local con una copia de la base de PROD. Commit `beeacf224`. En `main` desde el 12 de noviembre de 2026 (merge `21914c88d`) y subida a PROD ese día.

### 19.1. Decisiones cerradas con el club (11 de noviembre de 2026)

| # | Decisión |
|---|---|
| C-a | La pestaña «Mis cuotas» de Mi cuenta se crea en la parte C: servicios actuales, cambios programados y formulario de solicitud. La parte D le añade los recibos. |
| C-b | Una sola solicitud pendiente por socio. El socio la puede cancelar mientras nadie la haya resuelto. |
| C-c | Plazo de aviso (6.1): días que faltan = último día del mes − día de la solicitud. Con 5 días, en noviembre el 25 entra para el 1 de diciembre y el 26 para el 1 de enero. El plazo solo se aplica a cambios y bajas (D26), no a congelaciones. |
| C-d | Congelación: el gestor elige el mes de vuelta; la congelación acaba el último día del mes anterior y la vuelta es el día 1. Durante la congelación los servicios siguen abiertos (no hay recibos por el estado) y al volver los recupera tal cual. |
| C-e | Congelación inmediata (D30): el estado `congelado` empieza el día 1 del mes siguiente, para que conserve reservas hasta fin de mes. Se guarda la marca «anular los servicios que se anulan al congelar en el recibo de este mes», que usará la parte D. |
| C-f | Cambio de tipo de socio (normal ↔ junta): como los servicios, el día 1 del mes siguiente por defecto o al momento con D27. |
| C-g | Cambio inmediato (D27): casilla «Confirmo que el recibo de este mes no está pagado» y motivo. El recálculo del recibo lo hará la parte D. |
| C-h | El gestor puede anular un cambio programado que aún no se ha aplicado; queda en el registro. |
| C-i | Emails cuando el gestor cambia algo por su cuenta: solo la confirmación de baja (6.5). Las solicitudes del socio avisan al club al llegar y el socio recibe el resultado (D31). |
| C-j | Un socio de baja ve en Mi cuenta pedidos, tienda, «Mis cuotas» y «Mi monedero»; no ve «Perfil de deportista» ni «Documentos». |
| C-k | Sistema antiguo: se quitan `LegacyFieldsSync`, `LegacyMapping` y los avisos de `AthleteUpdate`. Mi cuenta, la página «Sulayr Info» y el listado de usuarios leen los servicios del historial. Las metas antiguas se quedan en la base sin usarse. |

### 19.2. Qué hay

| Pieza | Clase | Notas |
|---|---|---|
| Cambios programados | `Lifecycle\MemberChanges` | Cada cambio de servicios, de tipo, baja o congelación es una fila de `sulayr_member_requests` (tipos `cambio`, `tipo`, `baja`, `congelacion`) con su fecha efectiva. Estados: `pendiente` (lo pidió el socio), `aprobada` (decidido; programado mientras `applied_at` sea nulo), `rechazada`, `cancelada` (por el socio) y `anulada` (por el gestor, o porque al llegar el día ya no se podía aplicar, con el motivo). El historial solo se escribe al aplicar, con las fechas efectivas: anular no toca el historial. |
| Fechas | `MemberDates` | Fecha efectiva (6.1), día 1 del mes siguiente y conversión de fechas de ACF (antes en `LegacyMapping`). |
| Mi cuenta → Mis cuotas | `Lifecycle\FeesAccount` | Endpoint `mis-cuotas`. Estado, tipo, vuelta prevista o último día, servicios con el precio del mes, cambios programados y solicitud pendiente con «Cancelar solicitud». Activo: pedir cambio de servicios (los públicos y los que ya tiene) y la baja. Congelado: solo la baja. Muestra la fecha efectiva calculada. |
| Acciones del gestor | `Admin\MemberActions` | En la ficha del socio (Socios → socio → «Cambios, congelación y baja»): aprobar (desde un mes o al momento) o rechazar la solicitud del socio, anular programados, cambiar servicios y tipo, congelar (con «congelación inmediata»), adelantar o alargar la vuelta y dar de baja. `admin-post.php`, nonce por socio y `sulayr_manage_members`. |
| Solicitudes | `Admin\RequestsPage` | Socios → Solicitudes muestra también los cambios y bajas pedidos por socios (se resuelven en la ficha). El globo del menú los cuenta. |
| Tarea diaria (§7) | `Lifecycle\MembersDailyTask` | Sustituye a `Join\JoinDailyTask` (mismo evento `sulayr_members_daily`, 00:15). Aplica los cambios del día, devuelve a los congelados cuyo fin previsto pasó, activa las altas pendientes y caduca solicitudes. Botón «Ejecutar ahora» en Socios con la fecha de la última ejecución. |
| Reservas (D38) | `ReservationsAccess` | Cada cambio aplicado y cada vuelta de congelación escriben estado y natación y sincronizan con reservas. |
| Conservación (D36) | `Admin\MembersMenu` | El listado de Socios avisa de los antiguos socios con más años de baja que el plazo y deja verlos (`&conservacion=1`). No borra nada. |
| Campos antiguos | `Lifecycle\LegacyFieldsHidden`, `MemberSummary` | Tarifa, modalidad, natación, directiva y estado ya no salen en la ficha de usuario ni en Mi cuenta. En la ficha de usuario hay un recuadro con estado, tipo y servicios y el botón a la ficha de socio. `MemberSummary` da servicios, entrenamiento y natación a `UserService::get_athlete_formatted_data()` (Mi cuenta y «Sulayr Info») y al listado de usuarios (columnas «Servicios», «Natación» y «Tipo de socio»). |
| Mi cuenta de un socio de baja | `WooCommerceIntegration` | Sin «Perfil de deportista» (y «Documentos» ya era solo para activos). |
| Esquema | `Database\MembersSchema` | Versión 3: `applied_at` en solicitudes. |

Eliminado: `Migration\LegacyFieldsSync`, `Migration\LegacyMapping`, `Users\AthleteUpdate` y `templates/email/` (avisos antiguos de cambio de tarifa y de baja), y `Join\JoinDailyTask` (renombrada).

### 19.3. Decisiones de implementación

- **Fecha elegida por el gestor.** Un cambio que decide el gestor puede empezar cualquier día 1 desde el mes siguiente; la pantalla propone la fecha con el plazo de aviso. Al aprobar una solicitud del socio, la fecha mínima es la calculada al pedirla (o el mes siguiente si esa ya pasó).
- **Al aplicar** (día efectivo `E`): servicios que sobran se cierran en `E − 1` y los nuevos empiezan en `E` (origen «solicitud» si lo pidió el socio, «gestor» si no); baja: servicios y periodo cerrados en `E − 1`, periodo `baja` desde `E` y fecha de baja `E − 1`; congelación: periodo activo cerrado en `E − 1` y `congelado` desde `E` con fin previsto el día antes de la vuelta; tipo: se cambia la meta. Si al llegar el día el socio ya está de baja (o no está activo para congelar), el cambio queda «anulado» con el motivo.
- **Orden de la tarea diaria:** primero los cambios del día, después las vueltas de congelación. Así la baja avisada de Diego Lardón (1 de enero de 2027, congelado hasta el 31 de diciembre) pasa a baja y no vuelve a activo.
- **Reservas en la tarea diaria.** Solo se recalculan los socios que cambian y los `pendiente`, no todos: así no cambia el acceso de nadie cuyas metas no salgan del historial (datos migrados).
- **Pasos.** Al aprobar o programar un cambio o un cambio de tipo, los «Pasos de cambio» de Ajustes; en una baja, los «Pasos de baja». La congelación no crea pasos.
- **Emails.** Solicitud del socio → «Aviso al club». Aprobada → «Resultado de solicitud» (o «Confirmación de baja» si es una baja). Rechazada → «Resultado de solicitud» con el motivo. Baja decidida por el gestor → «Confirmación de baja». Van al socio y, si tiene tutor, también al tutor.
- **Ficha de usuario.** Los campos antiguos no se pintan, y ACF no guarda un campo que no pinta: nadie puede cambiarlos desde ahí.

### 19.4. Comprobado en local (11 de noviembre de 2026)

Con la base de PROD importada en local, copia antes de probar, el mu-plugin que no envía emails, dos socios de prueba con historial propio, un gestor de socios (sin `manage_options`) y un administrador, y peticiones HTTP. Al terminar se restauró la base y se borraron el mu-plugin y los archivos temporales.

- C1: 25 de noviembre → 1 de diciembre; 26 y 30 de noviembre → 1 de enero; 27 de diciembre → 1 de febrero; 23 de febrero de 2027 → 1 de marzo y 24 → 1 de abril.
- C2: una baja aprobada queda programada; el socio sigue activo y en reservas. Al llegar el día (simulado), la tarea la aplica y reservas lo retira.
- C3: la congelación inmediata guarda la marca del recibo del mes y el socio sigue activo y en reservas hasta el día 1. Una inmediata que no empiece el mes siguiente se rechaza.
- C4: al llegar la vuelta (simulada), vuelve a activo con los mismos servicios. Cambiar la vuelta actualiza el fin previsto.
- C5: «al momento» sin confirmar se rechaza; confirmado, se aplica hoy, con el motivo en el registro, y el socio entra en reservas.
- C6: la tarea diaria dos veces seguidas: la segunda no aplica nada.
- Mis cuotas: el socio pide un cambio y la baja; una segunda petición se rechaza; puede cancelar la suya. Cada petición avisa al club.
- Gestor: aprueba y rechaza (motivo obligatorio, email al socio), programa un cambio de tipo y lo anula. Los pasos de cambio y de baja se crean.
- Socio de baja en Mi cuenta: sin perfil de deportista ni documentos; Mis cuotas muestra el último día y no deja pedir nada.
- Baja avisada migrada de Diego Lardón (simulada): pasa de congelado a baja.
- Ficha de usuario sin los cinco campos antiguos y con el recuadro del socio. Listado de usuarios, «Sulayr Info» (por tarifa, por estado y nadadores), Mi cuenta y las pantallas de Socios responden 200 sin avisos de PHP. «Sulayr Info» agrupa por el entrenamiento del historial.
- Aviso de conservación con un antiguo socio con baja de 2018 (simulada).
- Regresión de la parte B: «Crear y aprobar» sigue dando de alta y sincronizando con reservas.

No comprobado: el aspecto en el navegador (Mis cuotas y los formularios de la ficha), los emails reales y PHP 7.4. Las pruebas manuales que faltan están en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md).

### 19.5. Despliegue en PROD

**Desplegado el 12 de noviembre de 2026** junto con B, C y D, siguiendo [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md) (pasos 1 a 8 hechos; primer mes con recibos: noviembre de 2026). Lo que sigue queda como referencia.

Para subir B, C y D juntas, que es lo previsto, sigue [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md), que tiene el cambio neto de archivos. Lo que sigue es para subir solo la parte C.

Si la parte B aún no está en PROD, desplegar primero la B (18.5) y después esto. Con FileZilla, en `wp-content/plugins/club-triatlon-sulayr/`:

1. Copia de seguridad, como en 16.4.
2. Subir los archivos nuevos:
   - `src/Modules/Members/Lifecycle/` (carpeta entera: `FeesAccount.php`, `LegacyFieldsHidden.php`, `MemberChanges.php`, `MembersDailyTask.php`)
   - `src/Modules/Members/Admin/MemberActions.php`
   - `src/Modules/Members/MemberDates.php`, `MemberSummary.php`
3. Sobrescribir:
   - `src/App/App.php`
   - `src/Services/UserService.php`
   - `src/Modules/Members/Admin/MembersMenu.php`, `Admin/RequestsPage.php`
   - `src/Modules/Members/Database/MembersSchema.php`
   - `src/Modules/Members/Join/JoinApproval.php`, `Join/JoinForm.php`
   - `src/Modules/Members/StatusPeriods.php`
   - `src/Modules/Users/UserAdminList.php`
   - `src/Modules/WooCommerce/WooCommerceIntegration.php`
   - `templates/parts/content-athlete-profile.php`
   - `public/css/styles.css`
4. Después, borrar del servidor:
   - `src/Modules/Members/Migration/` (carpeta entera: `LegacyFieldsSync.php`, `LegacyMapping.php`)
   - `src/Modules/Users/AthleteUpdate.php`
   - `templates/email/` (carpeta entera)
   - `src/Modules/Members/Join/JoinDailyTask.php` (solo existe si se subió la parte B)
5. Entrar en wp-admin del club: la primera carga sube el esquema a la versión 3 y regenera los enlaces de Mi cuenta (aparece «Mis cuotas»). Si «Mis cuotas» diera 404, guardar Ajustes → Enlaces permanentes.
6. Revisar en Socios → Ajustes los «Pasos de cambio», «Pasos de baja» y los textos de «Resultado de solicitud» y «Confirmación de baja».
7. En Socios, pulsar «Ejecutar ahora» una vez y comprobar el resumen. La baja avisada de Diego Lardón sale como programada para el 1 de enero de 2027 en su ficha.
8. Actualizar el estado de esta spec y de `docs/FASES.md`.

### 19.6. Abierto

- ~~El cambio de tipo de socio se guarda como meta (sin historial por fechas). Si la parte D regenera recibos de meses anteriores a un cambio de tipo, usaría el tipo nuevo.~~ Resuelto en la parte D con un historial de tipos (20.2).
- El cambio de la meta de natación que hace `ReservationsAccess` queda en el registro sin la nota del cambio que lo causó (la entrada del cambio está justo antes).

### 19.7. Para la parte D

Hecho en la parte D (sección 20).

- Matrícula del primer recibo: `data.approval.fee` de la solicitud de alta aprobada (18.7).
- Congelación inmediata: `data.cancel_month` (`AAAA-MM`) de la fila `congelacion` aplicada; ese mes se anulan los servicios con «se anula al congelar».
- Cambio inmediato (D27): filas `cambio`/`tipo`/`baja` con `data.immediate = true`; el recibo del mes en curso se recalcula con las reglas del primer mes y C5 debe comprobar que no está pagado.
- La tarea diaria (`MembersDailyTask::run()`) es donde van la generación automática de recibos y la retribución de la junta, después de aplicar los cambios del día.
- «Mis cuotas» (`FeesAccount::render()`) es donde van los recibos y el PDF. (Desde el 12 de noviembre de 2026 van en «Mis recibos», 20.8.)

---

## 20. Parte D: implementación

Implementada el 11 de noviembre de 2026 en la rama `altas-socio-parte-d`, creada desde la de la parte C (`beeacf224`), y completada el 12 de noviembre de 2026 con la identidad Sulayr en las partes B, C y D. Probada en local con la copia de la base de PROD. En `main` desde el 12 de noviembre de 2026 (merge `21914c88d`) y subida a PROD ese día.

### 20.1. Decisiones cerradas con el club (11 de noviembre de 2026)

La spec no fijaba estos puntos. Se decidieron antes de implementar:

| # | Decisión |
|---|---|
| D-a | El tipo de socio del día 1 vale para todo el mes. Un cambio de tipo al momento no cambia el recibo del mes en curso. |
| D-b | Un servicio vigente el día 1 se cobra el mes completo aunque se quite a mitad de mes (también en un cambio al momento). Solo se prorratea el que empieza después del día 1 (D24). |
| D-c | Subvenciones (D35) desde la ficha del socio. Porcentaje: sobre lo que se cobra de esos servicios, ya descontada la bonificación. Importe: entero cada mes, sin pasar de lo que se cobra de esos servicios. Sin servicios marcados: todos. |
| D-d | La matrícula va solo en el recibo del mes de la fecha de alta. Las altas cuyo mes queda antes del primer mes con recibos no la llevan (se cobró con el Excel). |
| D-e | Ajuste «Primer mes con recibos». A mano se genera el mes en curso o uno anterior no generado, desde ese mes; nunca un mes futuro. La generación automática solo hace el mes en curso. Vacío: no se generan recibos ni se apunta la retribución de la junta. |
| D-f | Tras un cambio al momento (D27) o una congelación inmediata (D30), el recibo del mes en curso se regenera solo. Cualquier otro cambio solo lo marca como desfasado (D40). El «al momento» y la congelación inmediata se bloquean si el recibo del mes ya tiene algún pago; la casilla de confirmación se mantiene. |
| D-g | Congelación inmediata con el recibo del mes pagado: bloqueada (el mes siguiente no hay recibo donde corregir). Las líneas anuladas se ven tachadas y no suman. |
| D-h | Corrección de un recibo pagado sin recibo siguiente (baja o congelado): queda pendiente en la ficha. Si es a favor del socio, un botón la pasa a su monedero; si no, se puede descartar con motivo. |
| D-i | La penalización se aplica a los recibos pendientes y a los pagados en parte. El «día límite» de impagado es el día de penalización. |
| D-j | Un pago apuntado por error se puede borrar con motivo (queda en el registro). Si era con monedero, se apunta la devolución; el cargo no se borra. |
| D-k | Medios de pago editables en Socios → Ajustes (SportEasy, Bizum y Efectivo por defecto). «Monedero» va siempre. |
| D-l | Retribución de la junta: a todo socio con importe mayor que 0 que esté activo el día 1 (congelados y bajas no). El gestor la cambia en la ficha de Socios. |
| D-m | Listado del entrenador: los congelados no salen aunque conserven el servicio; un cambio de modalidad es novedad; enviarlo por email lo marca también como enviado. |
| D-n | El email del recibo mensual (D52) se implementa desactivado, con asunto y texto en Ajustes. |
| D-o | Exportación en CSV (punto y coma, coma decimal, UTF-8 con BOM), que Excel en español abre directamente. |
| D-p | El año de `R-AAAA-NNNN` es el del mes del recibo. |
| D-q | PDF con dompdf (Composer, fijado a PHP 7.4). Los conceptos de las líneas y de los movimientos automáticos del monedero son textos de Socios → Ajustes → Recibos. |
| D-r | Identidad Sulayr (manual de marca del club) en todo lo de las partes B, C y D: PDF del recibo, todos los emails de socios, las pantallas del menú Socios (listado, ficha, Solicitudes, Nueva alta, Recibos, Tareas pendientes, Entrenador, Welcome pack), el formulario público de alta, el consentimiento de imagen y Mis cuotas. Pedido el 12 de noviembre de 2026. Quedan fuera las pantallas de WordPress y ACF (Ajustes, catálogo, monedero) y el recuadro «Socio» de la ficha de usuario. |

### 20.2. Puntos abiertos de 18.6 y 19.6

- **Tipo de socio sin fechas (19.6).** Resuelto. Hay una tabla de periodos de tipo (`MemberTypes`); al subir el esquema a la versión 4 cada socio recibe un periodo «desde siempre» con su tipo actual. Los cambios de tipo programados cierran un periodo y abren otro con la fecha efectiva, y la meta sigue siendo el tipo actual para el resto de pantallas. Si alguien cambia la meta por otro camino (la ficha de usuario), entra en el historial con fecha de hoy.
- **WP-Cron sin visitas (18.6).** La retribución de la junta y la generación automática son «una vez por mes, a partir de su día». Si un día no hay visitas, se hacen al siguiente; el apunte de la junta lleva igualmente fecha del día 1.
- **IP detrás de un proxy (18.6)** y **nota del registro de reservas (19.6)**: no afectan a los recibos; siguen abiertos.

### 20.3. Qué hay

| Pieza | Clase | Notas |
|---|---|---|
| Historial de tipos | `MemberTypes` | Tabla `sulayr_member_type_periods`. `for_month()` da el tipo del día 1 (o el primero del mes si el socio entra más tarde). `MemberChanges` y `JoinApproval` escriben ahí con la fecha efectiva o de alta. |
| Cálculo (8.2) | `Receipts\ReceiptCalculator` | Líneas automáticas de un socio y mes: servicios (prorrateo D24, D-b), bonificación (D48), anulación por congelación inmediata (`data.cancel_month`), matrícula (`data.approval.fee` del alta del mes) y subvenciones. Recibo si el socio está activo algún día del mes; junta siempre (D50). |
| Recibos (8.1, 8.3 a 8.5) | `Receipts\Receipts` | Generación del mes o de un socio, numeración, regeneración (conserva líneas manuales, penalizaciones y correcciones), desfase, correcciones, líneas manuales, anulación, penalización y pagos. Estados: pendiente, parcial, pagado, no aplica, anulado; «impagado» se calcula al mostrar. Meses generados en la opción `sulayr_receipts_months`. |
| Desfase (D40) | `Receipts\Receipts` | `MemberLog::record()` lanza `sulayr_member_history_changed` en los cambios de servicios y de estado (también `MemberTypes` y `Subsidies`). Al final de la petición se vuelven a calcular los recibos de esos socios y se marcan los que no coinciden. Guardar un servicio del catálogo comprueba los de todos. |
| PDF (8.6) | `Receipts\ReceiptPdf` | dompdf 3.1.6 en `vendor/`. Se genera al pulsar, con los datos guardados. Diseño de documento Sulayr: escudo de Ajustes y número en mono, filete Noche, «Recibo» en Archivo Black Italic, tabla con cabecera Noche y filas alternas Nieve, cifras en JetBrains Mono, estado con el color de la paleta y pie con la franja tricolor. Fuentes TTF estáticas con sus métricas en `assets/pdf-fonts/` (licencia OFL); si faltan, DejaVu Sans. Sin GD no se pinta el logo PNG. |
| Diseño de los emails | `Join\MemberMailer::layout()`, `button()` | Todos los emails de socios (bienvenida, rechazo, aviso al club, resultado de solicitud, confirmación de baja, recibo mensual y listado del entrenador): banda Noche con el escudo y el nombre del club en mono Cielo, franja tricolor, el asunto como titular en Archivo Black Italic, texto Noche sobre blanco, enlaces en `#0A7C8A`, la acción principal como botón Sol de Alba con texto Noche («Crear mi contraseña», «Ver mis cuotas») y firma con filete Menta (nombre en Archivo Bold Italic, ciudad, email del club y web en mono). Estilos en línea y tablas; donde no cargan las fuentes de marca, Arial Black, Arial y Consolas. El escudo va incrustado (`cid:sulayr-escudo`, `phpmailer_init` solo durante el envío) para que se vea sin descargar imágenes; sin archivo de logo, se enlaza por URL. `send( …, false )` manda HTML simple. |
| Diseño de wp-admin | `Admin\BrandStyles` | Clase `.sulayr-brand` en todas las pantallas del menú Socios: etiqueta mono sobre el título, título en Archivo con franja tricolor, tablas con cabecera Noche y filas alternas Nieve, cifras y fechas en mono, etiquetas de la ficha en mono, desplegables de «Cambios, congelación y baja» como tarjetas, botones Noche, «Rechazar» subrayado en Sol y la acción principal en Sol («Aprobar y enviar la bienvenida», «Crear y aprobar», «Aprobar y avisar al socio», «Generar recibos», «Apuntar», «Regenerar», «Enviar por email»). Fuentes de Google Fonts (como el tema del club) solo en el menú Socios. |
| Diseño del front (B y C) | `public/css/styles.css` | Formulario `[sulayr_solicitud_alta]`, consentimiento de imagen y Mis cuotas con las variables del tema (`--sulayr-*`): grupos con filete Noche y leyenda en Archivo, campos con foco en `#0A7C8A`, errores en Noche con filete Sol, avisos con filete menta o sol, «Enviar solicitud» en Sol con texto Noche, tarjetas de Mis cuotas con etiquetas en mono. |
| Exportación (8.7) | `Receipts\ReceiptExport` | Una columna por servicio y por tipo de línea, y comentarios del mes sacados del historial. |
| Monedero (D34, D47, 9.4) | `Receipts\WalletLedger`, `Receipts\BoardPayments` | Movimientos automáticos como los manuales (metas de ACF con su referencia). Pago de recibo, devolución, corrección a favor y retribución de la junta (meta `sulayr_wallet_board_period` para no repetirla). |
| Subvenciones (D35) | `Subsidies` | Tabla ya existente. Se crean y terminan en la ficha. |
| Pantalla Recibos | `Admin\ReceiptsPage` | **Socios → Recibos**: mes, generar (o generar los que faltan), penalizar desde el día límite, exportar, totales. Detalle: líneas, línea manual, volver a calcular o regenerar, corrección, pagos (añadir y borrar), anular y PDF. En la ficha del socio: sus recibos, correcciones pendientes, subvenciones y retribución de la junta. |
| Listados (9.1 a 9.3) | `CoachList`, `WelcomePack`, `Admin\ListsPage` | **Tareas pendientes** (filtro por paso y por alta/cambio/baja, con globo), **Entrenador** (agrupado por modalidad, novedades, texto para WhatsApp con «Copiar», «Marcar como enviado», «Enviar por email») y **Welcome pack** (activos sin entrega y últimas entregas). En la ficha, «Registrar entrega» y borrar una entrega. |
| Mis cuotas (D53) | `Lifecycle\FeesAccount` | Ahora en «Mis recibos» (20.8). Bloque «Tus recibos»: cada mes con líneas, pagos, pendiente y «Ver recibo». El PDF se abre con `?recibo=ID` en la pestaña y solo si el recibo es del socio (D6). Estilos en `public/css/styles.css` con las variables de marca del tema (`--sulayr-*`): mes en Archivo, importes en mono, estado con el color de la paleta. |
| Tarea diaria (§7) | `Lifecycle\MembersDailyTask` | Añade la retribución de la junta y la generación automática después de los cambios del día. El resumen de «Ejecutar ahora» las cuenta. |
| Ajustes | `MemberSettings` | En «Recibos»: primer mes con recibos, medios de pago, pie del recibo, textos de las líneas y de los movimientos del monedero. En «Emails»: recibo mensual. |
| Esquema | `Database\MembersSchema` | Versión 4: tabla de tipos; `user_id` y `source_receipt_id` en líneas; `note` en recibos y pagos; medio de pago de 60 caracteres. |

### 20.4. Decisiones de implementación

- **Mismo servicio con otra opción en el mes** (p. ej. sprint → olímpico al momento): el servicio ya está cobrado entero por la fila del día 1; la fila nueva no se cobra otra vez.
- **Precio** del día 1 del mes para el tipo del mes (4.3). Con bonificación, cada línea se prorratea por separado y la bonificación es la diferencia de las dos ya redondeadas.
- **Editable** quiere decir no pagado del todo ni anulado: un recibo pagado en parte admite líneas y regenerarse, siempre que el total no quede por debajo de lo pagado.
- **Correcciones** (D43): la diferencia entre lo calculado hoy y las líneas automáticas del recibo pagado, menos las correcciones ya creadas desde él. Va al siguiente recibo editable del socio o queda pendiente (`receipt_id = 0`) y entra en el próximo que se genere. Anular un recibo devuelve sus correcciones a pendientes.
- **Total ≤ 0** sin pagos: «no aplica» (D50).
- **Anular** solo un recibo sin pagos, con motivo. Ese socio no vuelve a tener recibo de ese mes (número no reutilizado, D44).
- **Alta en un mes ya generado** (5.3): `JoinApproval` genera su recibo después de enviar la bienvenida.
- **Emails** con HTML en los marcadores `{listado}` y `{enlace_mis_cuotas}`.
- **Retribución de la junta:** solo desde el primer mes con recibos, para no duplicar meses pagados con el Excel.

### 20.5. Comprobado en local (11 de noviembre de 2026)

Con copia de la base (`wp db export`), un mu-plugin temporal que guardaba los emails en una opción en vez de enviarlos, socios de prueba `@example.com` dados de alta por `JoinApproval`, un administrador temporal y un socio de prueba con «Gestor de socios» (sin `manage_options`), y peticiones HTTP iniciando sesión. «Primer mes con recibos» en octubre de 2026. Al terminar se restauró la base y se borraron el mu-plugin, los usuarios y los archivos temporales.

- D1: alta el 20 de noviembre con entrenamiento sprint y natación: 8,07 + 4,03 + 25,00 + 20,00 = 57,10 €.
- D2: junta solo con cuota base: 22,00 − 22,00 = 0 €, «no aplica». Junta con natación: 29,50 € sin bonificación.
- D3: 10 € con monedero de un recibo de 47 €: «pagado parcialmente», cargo de −10 € y saldo 0. Más de lo que hay en el monedero se rechaza. Borrar ese pago devuelve el saldo con un movimiento nuevo.
- D4: un recibo pagado no admite líneas ni regenerarse. Una subvención del 50 % de natación en octubre lo marca desfasado (−12,50 €); la corrección aparece en noviembre (34,50 €) y octubre deja de estar desfasado.
- D5: un servicio añadido con fecha pasada marca octubre y noviembre como desfasados sin cambiar su total; regenerar noviembre da 58 €.
- D6: por HTTP, el PDF propio en Mis cuotas da 200 (`application/pdf`); el de otro socio, 403; sin sesión, al login. Un deportista sin rol de gestor no abre Socios → Recibos (403).
- D7: la retribución de la junta se apunta una vez; la segunda ejecución no crea nada.
- D8: tras «Marcar como enviado», un alta nueva sale como «nuevo», una baja al momento en «Ya no están» y un cambio de sprint a olímpico como «antes Sprint».
- La tarea diaria genera el mes en curso (55 recibos) y la segunda ejecución no genera nada. Septiembre (antes del primer mes) y diciembre (futuro) no se pueden generar.
- C5 con recibos: un cambio al momento con el recibo pagado en parte se rechaza.
- Congelación inmediata: la cuota base del mes queda anulada (tachada) y la natación se cobra (25 €).
- Cambio de tipo programado a junta para diciembre: noviembre sigue normal (47 €) y no se marca desfasado; regenerarlo da lo mismo. Un cambio de tipo al momento no cambia el mes.
- Alta aprobada con el mes ya generado: se crea su recibo (42,00 €, con matrícula).
- Penalización: antes del día 20 se rechaza; en octubre se aplicó a 50 recibos y la segunda vez a ninguno. Pendientes de octubre se muestran como «impagado».
- Línea manual, anulación, subvención por importe y retribución desde la ficha (con coma decimal), y regenerar, todo por HTTP como gestor.
- Welcome pack: registrar con dos artículos, sin artículos (error) y borrar. Tareas pendientes: «Marcar hecho» vuelve a la lista. Entrenador: «Enviar por email» llega al entrenador y al club con el listado en HTML y lo marca como enviado.
- Con el aviso de recibo activado, el socio recibe la bienvenida y el email del recibo con el enlace a Mis cuotas.
- PDF revisado: banda con logo, datos del club y del socio, líneas, total, estado y pie.
- Identidad Sulayr (12 de noviembre de 2026, con copia de la base, restaurada después): el PDF lleva incrustadas Archivo Black Italic, Archivo Bold, Instrument Sans y JetBrains Mono y se revisó; los emails del recibo y del entrenador se revisaron pasándolos a PDF; las pantallas Recibos (listado y detalle), Tareas pendientes, Entrenador, Welcome pack, la ficha y Mis cuotas responden 200 sin avisos de PHP y cargan los estilos y las fuentes de marca.
- CSV: abre con columnas por servicio, bonificación, matrícula, corrección y manual, y los comentarios del mes.
- Todas las pantallas nuevas y la ficha responden 200 sin avisos de PHP, como administrador y como gestor. Ningún error del plugin en `debug.log`.

- Identidad en B y C (12 de noviembre de 2026, con copia de la base, restaurada después): la bienvenida se construyó en PHPMailer sin enviarse (SMTP apuntado a un puerto cerrado) y lleva el escudo incrustado con `Content-ID: <sulayr-escudo>`; bienvenida, rechazo, aviso al club, resultado de solicitud y confirmación de baja revisados pasándolos a PDF. Socios, la ficha, Solicitudes (listado y detalle), Nueva alta, el formulario público y Mis cuotas responden 200 sin avisos de PHP con la marca cargada.

No comprobado: el aspecto en el navegador (Mis cuotas en el móvil, las pantallas de wp-admin y el formulario público con la marca), los emails en un cliente de correo real (Gmail, móvil), el botón «Copiar texto» (usa el portapapeles del navegador), el CSV abierto en Excel, los emails reales y PHP 7.4. Pendiente en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md).

### 20.6. Despliegue en PROD

**Desplegado el 12 de noviembre de 2026** junto con B, C y D, siguiendo [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md) (pasos 1 a 8 hechos; primer mes con recibos: noviembre de 2026). Lo que sigue queda como referencia.

Para subir B, C y D juntas, que es lo previsto, sigue [despliegue-prod.md](altas-y-ciclo-de-vida-socio/despliegue-prod.md), que tiene el cambio neto de archivos. Lo que sigue es para subir solo la parte D, con B y C ya en PROD (18.5 y 19.5). Con FileZilla, en `wp-content/plugins/club-triatlon-sulayr/`:

1. Copia de seguridad, como en 16.4.
2. Subir las librerías nuevas: `vendor/dompdf/`, `vendor/masterminds/` y `vendor/sabberworm/` (carpetas enteras), y las fuentes del PDF: `assets/pdf-fonts/` (carpeta entera).
3. Subir la carpeta `vendor/composer/` entera (sobrescribe los archivos de carga y añade `autoload_files.php`, que necesita las librerías del paso 2). Después, `composer.json` y `composer.lock`.
4. Subir los archivos nuevos:
   - `src/Modules/Members/Receipts/` (carpeta entera: `BoardPayments.php`, `ReceiptCalculator.php`, `ReceiptExport.php`, `ReceiptPdf.php`, `Receipts.php`, `WalletLedger.php`)
   - `src/Modules/Members/Admin/BrandStyles.php`, `Admin/ReceiptsPage.php`, `Admin/ListsPage.php`
   - `src/Modules/Members/CoachList.php`, `MemberTypes.php`, `Subsidies.php`, `WelcomePack.php`
5. Sobrescribir:
   - `src/App/App.php`
   - `src/Modules/Members/Admin/MembersMenu.php`, `Admin/RequestsPage.php`, `Admin/MemberActions.php`
   - `src/Modules/Members/Database/MembersSchema.php`
   - `src/Modules/Members/Join/JoinApproval.php`, `Join/MemberMailer.php`
   - `src/Modules/Members/Lifecycle/FeesAccount.php`, `Lifecycle/MemberChanges.php`, `Lifecycle/MembersDailyTask.php`
   - `src/Modules/Members/MemberLog.php`, `MemberSettings.php`, `MemberTasks.php`
   - `public/css/styles.css`
6. No hay nada que borrar.
7. Entrar en wp-admin del club: la primera carga sube el esquema a la versión 4 y crea el periodo de tipo de cada socio. Comprobar que salen **Socios → Recibos**, **Tareas pendientes**, **Entrenador** y **Welcome pack**.
8. En **Socios → Ajustes → Recibos**: medios de pago, pie del recibo y textos de las líneas. Revisar CIF, dirección y logo en «Datos del club», y los emails del entrenador. **Dejar «Primer mes con recibos» vacío** hasta decidir desde qué mes se deja el Excel: mientras esté vacío no se genera nada ni se apunta la retribución de la junta.
9. En la ficha de cada miembro de la junta, poner su retribución mensual.
10. Elegir el primer mes con recibos. Diego Lardón figura activo del 1 al 10 de noviembre de 2026 por la migración (20.7): si se empieza en noviembre, anular su recibo con motivo. Si se empieza en diciembre, no hace falta.
11. Generar el mes desde **Socios → Recibos** (o esperar al día configurado), revisar varios recibos y su PDF, y exportar el CSV.
12. Actualizar el estado de esta spec y de `docs/FASES.md`.

Si PROD no tuviera la extensión GD de PHP, el PDF sale sin logo; si faltaran DOM o mbstring, dompdf no funciona (Herramientas → Salud del sitio las lista).

### 20.7. Abierto

- **Diego Lardón en noviembre de 2026.** La migración dejó su periodo activo hasta el 10 de noviembre y congelado desde el 11 (16.2), así que el sistema le genera recibo de noviembre. Ver el paso 10 de 20.6.
- **Recibo de un socio que deja de corresponder** (p. ej. un alta anulada con fecha pasada): queda desfasado; se regenera sin líneas automáticas o se anula a mano.
- **Total negativo** (correcciones a favor mayores que el mes): el recibo queda «no aplica» con total negativo; el gestor lo compensa a mano (p. ej. en el monedero).
- **Marca pendiente**: las pantallas de WordPress y ACF del menú Socios (Ajustes, catálogo de servicios, monedero) y el recuadro «Socio» de Usuarios → editar siguen con el aspecto de WordPress.
- **Fuentes en los emails**: Gmail y Outlook no cargan Archivo, Instrument Sans ni JetBrains Mono; ahí salen Arial Black, Arial y Consolas (el respaldo del manual).
- **Precios cambiados en el catálogo**: comprueban todos los recibos guardados; con muchos años de recibos podría tardar al guardar un servicio.

### 20.8. Pestaña «Mis recibos» (12 de noviembre de 2026)

**Estado:** en `main` y subida a PROD el 12 de noviembre de 2026, con el texto del email del recibo mensual cambiado en Socios → Ajustes. Comprobada en local y en PROD, en ordenador y móvil.

Pedido por el club: los recibos estaban dentro de «Mis cuotas», mezclados con la situación, los servicios y los formularios, y no se encontraban. Ahora tienen pestaña propia.

| Qué | Dónde | Notas |
|---|---|---|
| Mi cuenta → Mis recibos | `Receipts\ReceiptsAccount` | Endpoint `mis-recibos`, en el menú justo detrás de «Mis cuotas», solo para deportistas. Todos los recibos del socio, del más reciente al más antiguo y en cualquier estado (también «No aplica» a 0 € y «Anulado»): líneas, pagos, pendiente y «Ver recibo». El PDF se abre con `?recibo=ID` en esta pestaña y solo si el recibo es del socio (D6). Pide regenerar los enlaces la primera vez (`sulayr_receipts_endpoint_version`). |
| Mis cuotas | `Lifecycle\FeesAccount` | El bloque «Tus recibos» pasa a ser un resumen: «Tienes X € pendientes de pago» o «Estás al día con tus recibos», con el botón «Ver mis recibos». Los enlaces antiguos `mis-cuotas/?recibo=ID` redirigen a `mis-recibos/?recibo=ID`. `FeesAccount::is_athlete()` pasa a ser pública. |
| Email del recibo mensual | `Receipts::maybe_email()`, `MemberSettings` | Marcador nuevo `{enlace_mis_recibos}` (botón «Ver mis recibos»). `{enlace_mis_cuotas}` sigue funcionando. El texto por defecto ya usa el nuevo, pero el texto guardado en Socios → Ajustes manda: hay que cambiarlo a mano (ver despliegue). |

Comprobado en local con el socio 6 (junta, recibo R-2026-0005 de noviembre a 0 €, «No aplica»): sale en «Mis recibos» con sus líneas; «Mis cuotas» dice «Estás al día» con el enlace. Por HTTP: la pestaña da 200; el PDF propio, 200 (`application/pdf`); el de otro socio, 403; sin sesión, al login; `mis-cuotas/?recibo=5` redirige a `mis-recibos/?recibo=5`.

Comprobado después por el club (12 de noviembre de 2026): el aspecto en ordenador y móvil, en local y en PROD.

### 20.9. «Sulayr Info» por tarifa: mes siguiente (25 de noviembre de 2026)

**Estado:** en la rama `listado-mes-siguiente` (sale de `main`), comprobada en local el 25 de noviembre de 2026. Sin mergear en `main` ni subir a PROD.

Pedido por el club: el listado por tarifa solo mostraba los servicios de hoy, y los cambios aprobados para el mes que viene no salen en el historial hasta que la tarea diaria los aplica el día 1. No había forma de ver de una vez cómo queda el mes siguiente.

| Qué | Dónde | Notas |
|---|---|---|
| Selector «Mes actual / Mes siguiente» | `SulayrSettingsPage::render_athletes_by_plan()`, `templates/admin/athletes-by-plan-tabs.php` | `?action=by_plan&mes=siguiente`. Solo lectura: no aplica nada. |
| Previsión | `MemberSummary::projected()` | Historial el día 1 del mes siguiente más los cambios aprobados sin aplicar con fecha efectiva hasta ese día, en orden: servicios, tipo, baja y congelación (si vuelve después de ese día). Una congelación cuyo fin previsto ya pasó cuenta como activo, como hará la tarea diaria. La natación sale de los servicios (`grants_swimming`). |
| Marca de cambios | `templates/admin/athletes-group.php`, `admin/css/styles.css` | Deportista con algún cambio: fondo, etiqueta «Cambio», la descripción (`MemberChanges::describe()`) y «antes: …» si cambian los servicios. Arriba, cuántos tienen cambios. |
| Salen del listado | `UserService::get_athletes_leaving()` | Activos hoy que el mes siguiente están de baja o congelados por un cambio programado, con el motivo. |

Comprobado en local con tres cambios programados para el 1 de diciembre de 2026 dentro de una transacción deshecha al final (servicios, congelación y baja): el listado pasa de 47 a 45, el cambio de servicios sale marcado en su grupo nuevo con «antes: …» y los otros dos en «Salen del listado». El mes actual no cambia.

