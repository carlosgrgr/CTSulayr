# SPEC — Alta y ciclo de vida del socio

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr`
**Estado:** Definida el 11 de noviembre de 2026. Decisiones D1 a D58 cerradas con el club (sección 3). Parte A en `main` (`fab85c343` y `892d6fcff`) y desplegada en PROD el 11 de noviembre de 2026 con FileZilla. Stripe limpiado y migración terminada en PROD ese día (sección 16.5). Parte B implementada y probada en local el 11 de noviembre de 2026, con commit en la rama `altas-socio-parte-b` sin mergear: no está en `main` ni en PROD (sección 18). Pruebas manuales pendientes en `altas-y-ciclo-de-vida-socio/pruebas.md`. Partes C y D pendientes (sección 13).
**Fecha:** 11 de noviembre de 2026

Esta spec sustituye el alta manual de socios (WhatsApp + Excel + alta a mano en la web) por un proceso en la web del club (blog 1): solicitud, aprobación, cambios, congelaciones, bajas, recibos mensuales y listados. Conserva el histórico de altas, bajas y cambios de servicios, y deja los Excel de Drive solo para ingresos y gastos del club.

Es la base sobre la que se apoyará más adelante [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) (cobro online con TPV): los recibos de esta spec son los que ese borrador cobrará. Esta spec no cobra dinero.

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
| D53 | Pestaña «Mis cuotas» en Mi cuenta: recibos con todas sus líneas y estado, PDF, servicios actuales y cambios pendientes. Sustituye a «Mis pagos». |

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
| B | Formulario público y de gestor, aprobación, detección de duplicados y reingresos, consentimientos, email de bienvenida, pasos de alta (5) | Implementada y probada en local el 11 de noviembre de 2026. Commit en la rama `altas-socio-parte-b`, sin mergear: no está en `main` ni en PROD. Pruebas manuales pendientes en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md). Ver sección 18. |
| C | Cambios, bajas, congelaciones, solicitudes desde Mi cuenta, tarea nocturna, sincronización con reservas (6, 7) | Pendiente |
| D | Recibos, PDF, Mis cuotas, monedero de la junta, exportación, listado del entrenador, welcome pack, tareas pendientes (8, 9) | Pendiente |

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

Implementada el 11 de noviembre de 2026 en la rama `altas-socio-parte-b`, creada desde `main`. Probada en local por HTTP. Commit en esa rama, sin mergear: no está en `main` ni en PROD. Las pruebas manuales que faltan (navegador, emails reales, PROD) están en [pruebas.md](altas-y-ciclo-de-vida-socio/pruebas.md).

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
| Pantallas de gestión | `Admin\RequestsPage` | **Socios → Solicitudes** (con globo de pendientes): listado por estado y detalle con coincidencias, datos editables, aprobación y rechazo. **Socios → Nueva alta** (5.2): mismos campos. «Crear solicitud» o «Crear y aprobar»: si nadie de la red tiene ese email ni ese DNI, aprueba en el mismo paso; si hay coincidencias, lleva a la solicitud para decidir. Envían a `admin-post.php` con nonce y `sulayr_manage_members`. |
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

- `ReservationsAccess::apply()` es el punto único para escribir las metas de reservas a partir del historial. La tarea nocturna de la parte C puede llamarlo para cada socio con un cambio que empiece ese día.
- `Join\JoinDailyTask` es la tarea diaria: la parte C le añade cambios, congelaciones, bajas y la retribución de la junta (7).
- `LegacyFieldsSync`, `LegacyMapping` (incluido `write_plan_fields()`) y los avisos de `AthleteUpdate` se retiran en la parte C, que sustituye la edición de la ficha de usuario.
- `MemberTasks::create()` ya admite los contextos `cambio` y `baja`; los pasos de cambio y de baja están en Socios → Ajustes.
- La matrícula aprobada está en `data.approval.fee` de la solicitud aprobada (`user_id`). La parte D la añade al primer recibo; si el recibo del mes de alta ya existe, lo genera al aprobar (5.3).
- La vista general de pasos pendientes (9.1) es de la parte D.

