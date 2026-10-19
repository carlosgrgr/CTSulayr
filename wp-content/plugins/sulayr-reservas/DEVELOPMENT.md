# Desarrollo del plugin de reservas

Referencia técnica para implementar `sulayr-reservas`. Los requisitos funcionales están en [SPEC.md](../../../SPEC.md). Por qué el código existente obliga a ciertas adaptaciones está en [ANALISIS.md](../../../ANALISIS.md). El estado de las fases está en [FASES.md](../../../FASES.md).

Si este documento contradice al SPEC, manda el SPEC. Donde `SPEC-MEJORAS.md` cambia un comportamiento, manda ese documento, y el apartado 16 si un párrafo anterior de las mejoras dice lo contrario. No se cambian requisitos ni decisiones ya aprobadas desde aquí.

## Arquitectura

Plugin de red en `wp-content/plugins/sulayr-reservas/`. PHP 7.4 o superior. Sin Composer y sin ACF.

El archivo principal registra un autoload `SulayrReservas\` → `src/` y arranca `Plugin` en `plugins_loaded`.

| Clase | Responsabilidad |
|---|---|
| `Config\SiteConfig` | Blog del club y blog de reservas, leídos de opciones de red |
| `Context\BlogContext` | Ejecuta una operación en uno de esos dos sitios y restaura el contexto en un `finally` |
| `Database\Schema` | Crea las tablas con `dbDelta` y guarda la versión del esquema en cada sitio |
| `Activation\Activator` | Primera escritura de las opciones de red, del esquema y de los roles del sitio de reservas |
| `Roles\Capabilities` | Nombres de capacidades del plugin |
| `Roles\RoleRegistrar` | Crea y actualiza `nadador` y `gestor` solo en el sitio de reservas |
| `Access\AdminAccess` | Impide el `wp-admin` a nadador y gestor solo en el sitio de reservas |
| `Access\CrossSite` | Enlaces entre el club y reservas, sin autenticar por `user_id` |
| `Admin\NetworkSettingsPage` | Pantalla de red para los identificadores de sitio |
| `Sessions\Schedule` | Horario fijo y plazas de un mes |
| `Sessions\GenerationWindow` | Mes que corresponde ofrecer en una fecha |
| `Sessions\SessionGenerator` | Crea las sesiones en el blog 2 sin modificar las ya existentes |
| `Bookings\BookingService` | Crea y cancela reservas en el blog 2 |
| `Bookings\BookingRepository` | Lectura y escritura de esas reservas, dentro de la transacción |
| `Swimmers\SwimmerService` | Alta, edición de la ficha local, límite, contraseña, activación, desactivación y baja |
| `Swimmers\SwimmerRepository` | Ficha local y reservas asociadas de ese sitio |
| `Swimmers\WeeklyLimit` | Rango 1–5 y valor por defecto 3 |
| `Swimmers\EmailChangeService` | Solicitud y confirmación del correo, con token de 24 horas |
| `Swimmers\PhotoService` | Fotografía local de reservas |
| `Swimmers\CsvImporter` | Lectura y validación del CSV, sin escribir usuarios |
| `Swimmers\CsvExporter` | CSV de fichas y de reservas de una ficha |
| `Sessions\SessionEditor` | Cambia la capacidad o el estado de una sesión ya creada |
| `Front\Shortcode` | Registra `[sulayr_reservas]` y decide qué panel puede ver el usuario |
| `Front\PanelPage` | Localiza o crea la página pública que contiene el shortcode |
| `Front\PanelRenderer` | HTML de los paneles |
| `Front\PanelActions` | Formularios del front: nonce, capacidad y llamada al servicio |
| `Front\PanelQuery` | Lecturas del calendario, las reservas y las fichas |
| `Sync\SyncService` | Lee el club y actualiza la ficha local de reservas |
| `Sync\SyncLog` | Escribe y lista el registro técnico del blog 1 |
| `Sync\ClubSync` | Botón del perfil, guardado automático y pantalla del registro |

`BlogContext` no cambia de sitio si la configuración es inválida. No hace falta volver a comprobar el identificador en cada clase si la operación ya entra por `onClub()` o `onBookings()`.

## Sitios y tablas

| Sitio | Blog | Dominio | Prefijo actual | Tablas del plugin |
|---|---|---|---|---|
| Club Triatlón Sulayr | 1 | `clubtriatlonsulayr.local.com` | `wp_` | `wp_sulayr_swim_sync_log` |
| Reservas CN Nazarí | 2 | `cnnazari.clubtriatlonsulayr.local.com` | `wp_2_` | `wp_2_sulayr_swim_sessions`, `wp_2_sulayr_swim_bookings`, `wp_2_sulayr_swim_months`, `wp_2_sulayr_swim_swimmers` |

El club conserva deportistas, ACF y el rol `athlete`. Reservas conserva sesiones, reservas, meses generados y la ficha local del nadador. El registro técnico de sincronización vive en el club.

Los prefijos salen de `$wpdb->prefix` después de estar en el sitio correcto. No se escriben a mano.

## Roles y capacidades

El SPEC no nombra los slugs de capacidad. Esta tabla es la traducción técnica de las secciones 3.1, 3.2 y 3.3, no un permiso nuevo. Las comprobaciones de las fases posteriores deben usar estos slugs.

El rol también recibe `read`, igual que `athlete` en el club. Sin esa capacidad de WordPress el usuario autenticado no puede usar el front con normalidad. No recibe `manage_options`, `edit_posts` ni otras capacidades generales.

| Capacidad | Nadador | Gestor | Administrador del blog 2 |
|---|---|---|---|
| `sulayr_view_calendar` | sí | sí | sí |
| `sulayr_book_self` | sí | sí | sí |
| `sulayr_cancel_own` | sí | sí | sí |
| `sulayr_view_own_bookings` | sí | sí | sí |
| `sulayr_view_all_bookings` | no | sí | sí |
| `sulayr_book_for_others` | no | sí | sí |
| `sulayr_cancel_any` | no | sí | sí |
| `sulayr_manage_swimmers` | no | sí | sí |
| `sulayr_manage_sessions` | no | sí | sí |

Correspondencia con el SPEC:

- Consultar calendario, plazas y estado de la sesión: `sulayr_view_calendar`.
- Reservar para sí mismo: `sulayr_book_self`.
- Cancelar la propia reserva cuando el plazo lo permita: `sulayr_cancel_own`. El plazo de 60 minutos lo comprueba `BookingService`.
- Ver reservas propias e historial propio: `sulayr_view_own_bookings`.
- Ver reservas de todos, sesiones pasadas y asistentes: `sulayr_view_all_bookings`.
- Reservar para otro usuario, incluida la reserva retroactiva del gestor: `sulayr_book_for_others`.
- Cancelar cualquier reserva: `sulayr_cancel_any`.
- Alta, consulta, activación, desactivación y eliminación de nadadores: `sulayr_manage_swimmers`.
- Capacidad, habilitar o deshabilitar una sesión y generar el mes: `sulayr_manage_sessions`.

Los roles se guardan en las opciones del blog 2. No se crean en el blog 1. Al administrador del blog 2 solo se le añaden o retiran capacidades con prefijo `sulayr_`. No se toca el resto de sus capacidades ni ningún rol del club.

Si una capacidad `sulayr_` deja de estar en el mapa, se retira de `nadador`, `gestor` y `administrator` en el blog 2. No se borran otras capacidades que un administrador hubiera añadido a mano a esos roles.

## Decisiones aprobadas

- Rol del club: `athlete`. Rol del nadador en reservas: `nadador`.
- Blogs 1 y 2, con los dominios de la tabla anterior.
- Ficha del nadador en tabla local del sitio de reservas. No se escriben las metas globales del club.
- Mes generado en `generated_month` (`YYYY-MM`).
- Paneles públicos mediante el shortcode `[sulayr_reservas]`, sin atributos. El SPEC no fija el nombre técnico; se conserva este nombre para que futuras modificaciones no introduzcan otro.
- `PanelPage` reutiliza cualquier página publicada que ya contenga `[sulayr_reservas]`. Si no existe, solo la crea cuando accede un usuario con `publish_pages`. En otro entorno puede haber una página con un título o slug similar: lo determinante es que contenga el shortcode, no su nombre.
- Zona horaria de ambos sitios: `Europe/Madrid`, confirmada en esta fase.

## Convenciones

- PHP compatible con 7.4: sin tipos unión, sin `mixed`, sin promoción de constructor y sin enumeraciones.
- Prefijo de capacidades, opciones, tablas y hooks internos: `sulayr_`.
- Texto de administración en español.
- Consultas dinámicas con `$wpdb->prepare()`.
- Cualquier `switch_to_blog()` pasa por `BlogContext`.
- No editar `club-triatlon-sulayr` ni otros plugins.
- No crear commits salvo petición expresa.

## Incidencias conocidas

### WooCommerce al retirar un usuario del blog 2

`Automattic\WooCommerce\Admin\API\Reports\Customers\DataStore` escucha `remove_user_from_blog` y `deleted_user`. Intenta borrar una fila de `wp_2_wc_customer_lookup`. Esa tabla no existe en el blog 2. MySQL devuelve error. WordPress no aborta la retirada: el usuario sale del sitio y la cuenta global permanece. Se vio en la fase 6, al retirar un nadador y al borrar usuarios de prueba.

`SwimmerService` oculta ese error para que no llegue al gestor. Eso no lo corrige. Este plugin no debe crear las tablas de WooCommerce. La solución queda en «Mejoras futuras». No se ha revisado si otro plugin instalado dispara un gancho parecido.

## Riesgos técnicos

### Esquema de base de datos

La versión actual es `1`, opción `sulayr_reservas_db_version` en cada sitio. Una modificación futura sube esa versión y pasa por `dbDelta` o por una migración explícita. Si el cambio puede alterar datos existentes, la migración tiene que conservarlos. Una actualización del esquema no borra datos. La versión no se marca como instalada si falta alguna tabla prevista.

### Concurrencia de las reservas

Implementada y probada en `BookingService`. Cada operación abre `START TRANSACTION` y cierra con `COMMIT` o `ROLLBACK`. El orden de bloqueo es siempre el mismo: primero la ficha del nadador (`FOR UPDATE`) y después la sesión (`FOR UPDATE`). La cancelación bloquea además la fila de la reserva. Así el límite semanal no depende solo del aforo de una sesión, y dos operaciones sobre sesiones distintas del mismo usuario quedan en serie.

Después de bloquear se vuelven a contar las plazas y las reservas de la semana. La clave única `(session_id, user_id)` cubre el duplicado si el recuento no llegara a verlo. En cinco rondas con dos procesos no apareció un interbloqueo. Si aparece, la operación responde `failed` y no se reintenta. La generación simultánea de un mes no está cubierta por estas pruebas.

### Alta de nadador no atómica

`wp_insert_user()` y la ficha local no comparten una transacción. Si la ficha falla después de crear la cuenta, otra alta con el mismo correo reutiliza esa cuenta y no cambia la contraseña. La baja confirma primero las tablas del plugin y después llama a `remove_user_from_blog()`. Si esa llamada fallara, una segunda baja vuelve a intentar retirarlo del sitio. La API de usuarios de WordPress no entra sola en la transacción de las tablas del plugin.

### Multisite

Cada operación corre en su sitio. El contexto se restaura aunque la operación falle. Las tablas de negocio permanecen en el blog 2 y el registro de sincronización en el blog 1. Crear roles o capacidades fuera de `onBookings()` o de una petición cuyo blog actual sea el 2 filtraría `nadador` y `gestor` al club.

### Fechas y horas

Ambos sitios usan `Europe/Madrid`, leído de la base de datos. La lógica de mes, ventana de generación, inicio de sesión, cancelación y semana debe usar `wp_timezone()` o `DateTimeImmutable` con esa zona. No se usa la zona del servidor ni un desplazamiento fijo. El SPEC, sección 21, todavía dice que la zona no está fijada en el repositorio. El texto del SPEC no se ha cambiado en esta revisión.

### Acceso a wp-admin

El plugin del club redirige a cualquier `athlete` que entre en `wp-admin`, en el sitio donde ese código se ejecuta. El bloqueo de este plugin solo actúa en el blog 2 y solo si el usuario tiene exclusivamente `nadador`, `gestor` o ambos. Un administrador, un superadministrador u otro rol del sitio no se redirige. `admin-ajax.php` no se redirige, porque WordPress lo necesita; la autorización de cada acción seguirá comprobándose por capacidad.

Si existe una página publicada con `[sulayr_reservas]`, la redirección lleva a su enlace permanente. Si no existe, sigue yendo a la portada. Nadador y gestor, con sesión iniciada, salen de `wp-admin` hacia el panel. El administrador entra en el escritorio.

### Sales de `wp-config.php`

Siguen siendo los placeholders de instalación. Afectan a las sesiones reales de WordPress y no forman parte del plugin.

## Pruebas pendientes

No hay suite PHPUnit. Lo comprobado hasta la fase 6 se ha ejecutado con scripts que cargan WordPress y, en la fase 5, con dos procesos PHP distintos. Queda pendiente decidir e implantar una estrategia de pruebas del plugin, incluidos los servicios y las operaciones de concurrencia.

- Generación simultánea del mismo mes. En la fase 9 dos procesos generaron noviembre de 2026 a la vez, con la fecha fija del 26 de octubre. Uno creó el mes y el otro lo dejó igual: 38 sesiones, ninguna duplicada y ninguna en viernes ni domingo. Esas filas se borraron al terminar. No queda noviembre generado.
- Envío real del correo de alta. La fase 6 comprobó que una cuenta nueva pide `wp_send_new_user_notifications()` y que una cuenta reutilizada no lo pide. El envío se interceptó. No se ha visto el mensaje en un buzón. No es un caso del apartado 18 del SPEC y no impide cerrar la fase 9. Queda para una revisión posterior.
- PHP 7.4. Sigue siendo la versión objetivo. El código evita sintaxis de PHP 8. El entorno de las pruebas es PHP 8.4.17. No se ha ejecutado el plugin con PHP 7.4. No es un caso del apartado 18.
- La concurrencia de reservas de la fase 5 sí se probó. No vuelve a esta lista.
- La reserva para otra persona y la reserva retroactiva ya cuentan en la semana que corresponde. Esas dos comprobaciones cierran el apartado 18.4.

## Mejoras futuras

- Dejar de ocultar el error de WooCommerce y resolverlo de otra forma. Antes, comprobar si hay más ganchos de plugins instalados con el mismo efecto. No crear `wp_2_wc_customer_lookup` solo para callar el aviso.
- Reintento limitado si una reserva o una cancelación termina en interbloqueo. Hoy responde `failed`.
- La salida del `wp-admin` ya apunta a la página del shortcode. Nadador y gestor fueron redirigidos; el administrador entró.
- Confirmar si 180 días y 1000 filas siguen siendo la retención adecuada del registro. Ya está aplicada en `SyncLog`.
- Confirmar si el agrupamiento de capacidades de la tabla anterior se mantiene. Traduce el SPEC y no añade permisos; no está cerrado como decisión de producto.

## Verificaciones para la sincronización

- Debe leer el club en `profile_update` con prioridad 30 y escribir solo la tabla local de nadadores.
- Si la cuenta ya está en el blog 2, debe usar `add_role( 'nadador' )`. El alta manual ya lo hace así.
- El bloqueo de `wp-admin` debe seguir limitado al blog 2 cuando aparezcan formularios públicos. Esos formularios no deben depender de `admin-post.php`.

## Fase 3

Implementada en `Roles\Capabilities`, `Roles\RoleRegistrar` y `Access\AdminAccess`.

`RoleRegistrar` sincroniza los roles al activar el plugin, dentro de `onBookings()`, y en cada petición del blog 2 si la versión de roles (`sulayr_reservas_roles_version`, valor `1`) no coincide o falta un rol. Repetir la activación no crea roles duplicados ni toca el blog 1.

`AdminAccess` redirige en `admin_init` y oculta la barra de administración. No actúa en el blog 1, ni con administradores, ni con superadministradores, ni si el usuario tiene otro rol además de `nadador` o `gestor`. No interrumpe AJAX ni cron.

La decisión de bloquear o no se comprobó primero con usuarios de prueba. El recorrido en el navegador se verificó después, en la fase 9: nadador y gestor salen de `wp-admin` y el administrador entra.

## Fase 4

Implementada en `Sessions\Schedule`, `Sessions\GenerationWindow` y `Sessions\SessionGenerator`. No hay cron ni pantalla: la generación se invoca con `generate()`.

La ventana del SPEC, sección 7.4, queda aprobada así:

- Si el mes en curso no está generado, se ofrece hasta el día 26 inclusive. El día 27 pasa al mes siguiente y el mes en curso ya no se puede crear.
- Si el mes en curso ya está generado, el mes siguiente se ofrece desde el día 26.
- El 25 de septiembre no se puede generar octubre. El 26, solo si septiembre ya está generado. Si septiembre sigue pendiente ese día, el botón sigue ofreciendo septiembre.
- Nunca se ofrecen dos meses a la vez.

Repetir la generación no hace `UPDATE`. Conserva capacidad, sesiones deshabilitadas y reservas. Si el mes quedó a medias, solo inserta las sesiones que faltan, también fuera de la ventana, para no dejar el mes marcado e incompleto. Un mes nuevo fuera de la ventana se rechaza. Quien no tiene `sulayr_manage_sessions` también.

Un fallo dentro de la transacción hace `ROLLBACK`. Las fechas de los días se calculan con `wp_timezone()`. Las horas se guardan como hora de pared (`15:00:00`), sin convertirlas a UTC, así el cambio de hora de octubre no las desplaza.

Comprobado cargando WordPress con fechas fijas de 2026: septiembre 40 sesiones, octubre 39, noviembre 38, ninguna en viernes ni domingo. La repetición devolvió `unchanged`. Una sesión deshabilitada con capacidad 10 y una reserva siguieron igual. El 26 de octubre de 2026, lunes posterior al cambio de hora, se guardó a las 15:00. El blog al terminar seguía siendo el 1 y el club no tiene tabla de sesiones. La generación simultánea de dos procesos sigue en «Pruebas pendientes».

## Fase 5

Implementada en `Bookings\BookingService` y `Bookings\BookingRepository`. No hay formulario ni endpoint: las fases siguientes llaman a `book()` y `cancel()`. El actor es el usuario actual, comprobado en el blog 2 después del cambio de sitio.

La ficha que se copia en la reserva sale de `sulayr_swim_swimmers`, no de `wp_usermeta`. El nadador de la reserva tiene que estar activo. Una reserva de gestor para una sesión ya empezada se guarda con `is_manual = 1` y `manager_id`. Una reserva futura, aunque la haga un gestor para otro, no lleva ese indicador. `created_by` es quien ejecuta la operación.

La sesión se considera empezada cuando `now` alcanza la hora de inicio. Hasta ese momento se aplican habilitación, aforo y límite. A partir de ese momento solo puede reservar quien tiene `sulayr_book_for_others`, también si la sesión está deshabilitada, y solo si queda plaza. Esa regla está aprobada en el SPEC, sección 9.3. La cancelación del nadador exige 60 minutos o más; en el instante exacto se permite. El gestor, con `sulayr_cancel_any`, cancela sin ese plazo. La fila se borra.

Comprobado con fechas fijas y usuarios de prueba, borrados al terminar junto con sus sesiones y reservas. Cinco rondas con dos procesos PHP: la última plaza quedó en una sola reserva; la reserva duplicada, en una sola fila; dos sesiones simultáneas del mismo usuario no pasaron de 5 en la semana; reserva y cancelación a la vez dejaron 0 o 1 plazas ocupadas, nunca 2. El blog al terminar seguía siendo el 1 y el club no tiene tabla de reservas. No se escribió `first_name` ni el teléfono global del usuario.

## Fase 6

Implementada en `Swimmers\SwimmerService`. No hay formulario. Operaciones: `create()`, `deactivate()`, `reactivate()` y `remove()`. El SPEC no define editar el nombre, el teléfono, el correo ni el DNI de una ficha ya creada, así que esta fase no tiene operación de edición. La fase 10 sí la define, con las reglas de `SPEC-MEJORAS.md`, apartado 16. Cambiar el correo toca `wp_users` y, por eso, no es inmediato.

Una cuenta nueva se crea en el blog 2 con el rol `nadador` ya en `wp_insert_user()`, para que el gancho del club no la convierta en `athlete`. Después se llama a `wp_send_new_user_notifications()` solo para el usuario, para que elija contraseña. En las pruebas esa llamada se interceptó: no se comprobó un buzón real. Si el correo ya existe, se reutiliza esa cuenta y no se envía aviso: no se cambia la contraseña ni `first_name`, `sulayr_user_phone` ni `sulayr_user_dni`. Si todavía no está en el blog 2, `add_user_to_blog()` le asigna `nadador`. Si ya está, `add_role( 'nadador' )`, para no quitar `gestor`, `editor` o `administrator`. La ficha local nace activa y con `linked_to_club = 0`. El vínculo con el club lo pondrá la sincronización.

La creación del usuario de WordPress y la inserción de la ficha no comparten una transacción. Si la ficha falla después de crear la cuenta, otra alta con el mismo correo reutiliza esa cuenta. La baja confirma primero las tablas del plugin y después llama a `remove_user_from_blog()`. Si esa llamada fallara, una segunda baja vuelve a intentar retirarlo del sitio.

Desactivar pone `active = 0` y borra las reservas cuya sesión aún no ha empezado. El historial pasado conserva `user_id` y el snapshot. Reactivar no las recupera. `BookingService` rechaza reservas de un nadador inactivo. La baja borra las futuras, pone `user_id` a NULL en las pasadas, elimina la ficha (y con ella el DNI) y retira al usuario del blog 2. No usa `wp_delete_user()`. Si además tiene otro rol en ese blog, o es superadministrador, solo se le quita `nadador` y sigue en el sitio.

Comprobado con usuarios de prueba, borrados al terminar: alta, duplicado, correo inválido, nadador sin permiso, reutilización de una cuenta del club sin tocar su contraseña ni sus metas, desactivación, reserva rechazada, reactivación sin restaurar la plaza, fallo antes del `COMMIT`, baja de quien solo es nadador y baja de quien también es editor. El blog al terminar seguía siendo el 1. El aviso de WooCommerce no interrumpe la retirada; ocultarlo no es la solución, y queda en «Incidencias conocidas».

## Fase 7

El shortcode es `[sulayr_reservas]`. No admite atributos. `Plugin` lo registra en cualquier sitio. Fuera del blog 2 solo muestra que el panel no está disponible y no lee fichas ni reservas.

`PanelPage` busca una página publicada que contenga el shortcode y guarda su ID en `sulayr_reservas_panel_page`. Si no hay ninguna y el usuario puede `publish_pages`, crea «Reservas de natación» con el slug `reservas-natacion`. En este entorno esa página es la 7. Un visitante anónimo no la crea.

La presentación está en `Front\PanelRenderer` y los estilos en `assets/panel.css`. No hay librerías externas. Las vistas usan la query `sulayr_vista`, más `sesion`, `usuario` o `mes` cuando hace falta. No son atributos del shortcode.

El nadador ve el mes en curso, sus reservas, su historial y su ficha local. Un `mes` en la URL no le abre otro mes. Una sesión de otro mes responde que no está disponible. No ve nombres, correos ni DNI de otros nadadores. Si su ficha no existe o está inactiva, no ve el calendario y un POST de reserva se rechaza.

El gestor y el administrador del blog 2 ven el calendario, el detalle con asistentes, el alta, la ficha de cada nadador y los meses anteriores que ya tienen sesiones. El mes siguiente solo se abre si ya está generado. No se puede pedir un mes futuro arbitrario. Si la ventana de generación no ofrece mes, el calendario dice «Hoy no corresponde generar un mes.» y no muestra el botón. El botón llama a `offeredMonth()` y luego a `generate()`; ignora cualquier mes enviado en el formulario.

`SessionEditor` existe porque el SPEC pide cambiar capacidad y habilitar o deshabilitar, y ningún servicio anterior escribía esos campos. Solo actualiza `capacity` o `enabled`, con la fila bloqueada y `sulayr_manage_sessions`. No borra reservas. Bajar el aforo por debajo de las reservas ya hechas las conserva. La reserva, la cancelación, el límite de cinco, los 60 minutos y la reserva retroactiva siguen en `BookingService`. El alta, la baja y la activación siguen en `SwimmerService`.

Los formularios hacen POST a la propia página en `init`. No usan `admin-post.php`, porque nadador y gestor serían redirigidos antes de la acción. Cada POST lleva el nonce `sulayr_panel`. El actor es el usuario actual. La retirada pide una casilla de confirmación; es una protección de la interfaz, no una regla nueva del SPEC. El resultado se guarda un minuto en un transient y la respuesta redirige a la misma página. El texto sale de los servicios. Un fallo de generación no muestra el mensaje de la excepción.

Comprobado con un script que carga WordPress, crea usuarios temporales y los borra al terminar. Resultado final: 0 fallos. Cubre anónimo, nadador, nadador inactivo, gestor, administrador, suscriptor, el shortcode en el blog 1, reserva, sesión completa, límite semanal, cancelación a tiempo y fuera de plazo, reserva ajena, nonce ausente o inválido, capacidad, sesión deshabilitada, generación sin aceptar un mes enviado, alta, desactivación, reactivación y retirada. El nombre se sanea en el servicio y se escapa al pintar; un valor con `&` y etiquetas se vio como texto. La hoja de estilo se encola en la página del shortcode.

La petición HTTP anónima a `/reservas-natacion/` respondió 200, incluyó `panel.css` y el enlace de acceso, y no mostró plazas. En el navegador se vio esa misma página en escritorio y en un viewport de 390 px. El enlace abre `wp-login.php` del sitio de reservas y vuelve a la página del panel. No se inició sesión en el navegador, así que el calendario autenticado no se vio ahí.

La prueba de generación dejó octubre de 2026 creado: 39 sesiones, ninguna reserva. Es el mes real, no un resto de prueba. Los usuarios y las sesiones ficticias de la prueba se borraron.

En ese momento seguían sin verse el correo real de alta, la ejecución en PHP 7.4, el aviso de WooCommerce al retirar un usuario y la redirección HTTP autenticada de `wp-admin`. La generación simultánea se comprobó después, en la fase 9. La redirección y el calendario con sesión se comprobaron más tarde y quedan anotados en la fase 9. El correo real sigue pendiente.

## Fase 8

La sincronización vive en `Sync\SyncService`. El botón, el guardado del perfil y la pantalla están en `Sync\ClubSync`. El registro está en `Sync\SyncLog`. No hay cambio de esquema: la tabla `sulayr_swim_sync_log` ya existía en el blog 1.

`SwimmerRepository` ganó `insertSynced()` y `updateSynced()`. El alta manual sigue creando la ficha con `linked_to_club = 0`. La sincronización no usa `SwimmerService`, porque el administrador del club puede no tener capacidades de reservas en el blog 2. Las reglas son las mismas: una reserva es futura cuando aún no ha llegado su hora de inicio.

El club se lee en el blog 1. Se escribe solo la ficha del blog 2. No se llama a `wp_update_user()` ni se cambian las metas del club. Si la persona cumple `athlete`, natación `1` y estado `active`, se añade al blog 2 con `add_user_to_blog()` o, si ya pertenece, con `add_role( 'nadador' )`. La ficha queda activa y vinculada. Un campo vacío del club deja vacío el campo local. Si deja de cumplir las condiciones y la ficha estaba activa, se desactiva y se borran las reservas futuras. El historial pasado se conserva. Una segunda ejecución con los mismos datos no crea otra ficha ni otra reserva.

`profile_update` con prioridad 30 solo actúa en el blog 1, solo si quien guarda tiene `manage_options` y el perfil es de un `athlete`. Así ocurre después de que ACF y `AthleteUpdate` hayan guardado. `remove_user_from_blog` se dispara antes de quitar el rol, por eso la salida del club y la pérdida de `athlete` fuerzan la desactivación en lugar de volver a leer unas condiciones que todavía parecen válidas.

El botón «Sincronizar ahora en reservas» está en el perfil del deportista, en el `wp-admin` del club. Es un enlace con aspecto de botón a `admin-post.php`, protegido con nonce y `manage_options`. No usa un formulario propio porque la pantalla de edición de WordPress ya está dentro de otro formulario: anidarlos impedía guardar el perfil y hacía que el botón solo recargara la página. El objetivo tiene que ser `athlete`. Llama al mismo `sync()`. El registro se abre en Usuarios → Registro de sincronización, solo para administradores del club. Cada fila guarda fecha, usuario, nombre, origen, resultado, acción y un mensaje fijo. No guarda contraseñas. Se conservan 180 días y, como máximo, 1000 filas. Si el registro falla, la ficha no se deshace.

Comprobado con usuarios temporales, borrados al terminar: alta, repetición, actualización, campo vacío, vínculo de una ficha manual sin duplicar la cuenta ni la reserva, desactivación, reactivación sin recuperar la plaza, prevalencia sobre un `active = 0`, salida del club, pérdida del rol, guardado del perfil, rechazo si no hay administrador, botón visible, registro sin la contraseña, borrado de una fila de 2020 y alta que sigue existiendo aunque el registro lance un error. El blog activo volvió al club. Al borrar los usuarios de prueba con `wpmu_delete_user` volvió a aparecer el aviso de WooCommerce por `wp_2_wc_customer_lookup`. La retirada del producto no se ha cambiado.

## Fase 9

La matriz está en [ACEPTACION.md](../../../ACEPTACION.md). La fase queda cerrada.

Con sesión iniciada se verificaron la redirección de nadador y gestor fuera de `wp-admin`, la entrada del administrador, el calendario autenticado, la sesión deshabilitada, el historial y la retirada desde el panel. Después se comprobó que una reserva hecha para otra persona cuenta en el límite semanal de esa persona, y que una reserva retroactiva cuenta en la semana de la sesión.

El correo real de alta sigue sin verse en un buzón. No forma parte del apartado 18 y queda anotado para más adelante. PHP 7.4, PHPUnit y el aviso de WooCommerce tampoco son casos de ese apartado. Siguen en «Pruebas pendientes» y en «Mejoras futuras».

## Rediseño visual del panel

La presentación del shortcode pasa de una lista de sesiones a un calendario mensual en tarjetas. No cambia la reserva, la cancelación, la generación, la sincronización, el esquema ni los permisos. Los formularios siguen haciendo POST a la misma página con el nonce `sulayr_panel` y los mismos nombres de campo.

La cuadrícula va de lunes a domingo. Los días de otro mes solo completan la rejilla y no cargan sesiones. El día actual usa la zona `wp_timezone()` (`Europe/Madrid`) y se marca con borde, fondo y el texto «Hoy». No hay un día seleccionado distinto del día actual.

El color de cada sesión es solo una ayuda. El texto de estado sigue saliendo de las mismas condiciones:

| Apariencia | Cuándo se aplica, por este orden |
|---|---|
| Pasada | La hora de inicio ya ha llegado |
| Reservada por ti | Hay reserva propia y la sesión no ha empezado |
| Deshabilitada | `enabled` no es 1 |
| Completa | Las plazas ocupadas llegan a la capacidad |
| Disponible | El resto de sesiones habilitadas |

No existe un estado de sesión cancelada: cancelar borra la reserva. La leyenda no lo inventa. Una sesión pasada no ofrece reservar. Una deshabilitada o completa tampoco ofrece la reserva ordinaria. Esas reglas siguen en `quickActions` y en `BookingService`.

Las tarjetas laterales leen datos que ya estaban en el panel: la ficha del usuario actual, su próxima reserva que aún no ha empezado, el recuento de activas e historial y las reservas de la semana en curso. La semana es la de `BookingService`: de lunes a domingo. El máximo es el `weekly_limit` de la ficha, no una constante. `WeeklyLimit::DEFAULT_LIMIT` es 3. No muestran reservas de otro nadador. Si el gestor abre la ficha de otra persona, el lateral sigue siendo el suyo.

`bookingsFor()` añade `s.id AS session_id` para enlazar «Detalle» de la próxima sesión. No cambia el filtro ni el orden.

En el tema, el contenido de la página es `alignfull`. El panel ocupa ese ancho y anula el máximo de 645 px que Twenty Twenty-Five aplica a los hijos con `:where`, de especificidad menor. La cuadrícula de siete columnas tiene un máximo para que las celdas no se estiren en pantallas muy anchas. Desde la fase 11, por debajo de 1024 px esa lista deja de mostrarse y el calendario pasa a un solo día. En 390 px no hay desplazamiento horizontal. La columna lateral queda al lado del calendario desde 1380 px; en pantallas más estrechas, las tarjetas pasan debajo. Desde 1680 px el menú queda en una columna a la izquierda.

No hay JavaScript nuevo. La hoja se encola con `filemtime` para que el navegador recoja el CSS nuevo sin subir la versión del plugin.

## Fase 10

Los requisitos están en `SPEC-MEJORAS.md`. El apartado 16 cierra las decisiones de datos. `SPEC.md` no se ha modificado. La versión de esquema pasa a 2. `dbDelta` añade a `sulayr_swim_swimmers`, sin borrar filas:

| Columna | Uso |
|---|---|
| `weekly_limit` | Entero 1–5. Defecto 3, también en las fichas que ya existían |
| `photo_id` | ID del adjunto en el blog 2. `0` si no hay foto |
| `pending_email` | Correo pedido y todavía no confirmado |
| `email_token_hash` | HMAC-SHA256 del token, con `wp_salt( 'auth' )`. Vacío si no hay solicitud |
| `email_token_expires` | Caducidad. El valor vacío de columna es `1970-01-01 00:00:00` para no depender de NULL |

`BookingService` lee el límite dentro de la misma transacción que ya bloquea la ficha. Ya no existe `WEEKLY_LIMIT`. Bajar el límite no borra reservas. La semana sigue siendo de lunes a domingo por la fecha de la sesión, y solo cuentan las filas que siguen en `sulayr_swim_bookings`.

La edición de nombre, apellidos y teléfono escribe solo la ficha local y solo si `linked_to_club = 0`. No toca `wp_users.user_email` ni las metas `first_name`, `last_name` y `sulayr_user_phone`. El DNI no tiene formulario. Un vinculado ve esos datos y no los guarda, ni el nadador ni el gestor. `updateSynced()` no incluye `weekly_limit` ni `photo_id`, así que una sincronización posterior restaura nombre, teléfono y correo del club y conserva el límite y la foto.

El cambio de correo, lo pida el nadador o el gestor, valida que la dirección no esté en otra cuenta, guarda el hash y envía el enlace al correo nuevo. La query es `sulayr_confirmar_email` y apunta al frontend de reservas. Al confirmar se actualizan `wp_users.user_email` y la columna local, y se invalida el token. Dura 24 horas. No usa `wp-admin/profile.php`. Si `wp_mail` falla, se borra la solicitud. Si la ficha no se actualiza después de cambiar la cuenta, se revierte `user_email`. Quien confirma no tiene que tener la sesión iniciada. Una solicitud nueva sustituye a la anterior. `wp_update_user()` puede avisar a la dirección antigua; se acepta. Confirmar un correo de un vinculado actualiza la cuenta, y la siguiente sincronización del club puede volver a copiar el correo que tenga el club, porque el gancho del club lee `user_email` en ese momento.

La contraseña solo la cambia el usuario actual, con la contraseña vigente, un mínimo de 8 caracteres y la repetición. Un `swimmer_user_id` distinto del actor se rechaza antes del servicio. Después se renueva la cookie.

La foto no concede `upload_files`. Comprueba que el archivo venga de una subida HTTP, 5 MB, extensión, tipo real y que sea imagen. Si un lado pasa de 1200 px, se reduce manteniendo la proporción. El adjunto queda en el blog 2 y `photo_id` en la ficha. Sustituir o quitar borra el adjunto anterior. La baja del nadador también lo borra. Puede cambiarla un vinculado. El círculo es CSS.

El CSV de alta no reutiliza `create()`, porque ese método puede vincular una cuenta ya existente. `createImported()` rechaza cualquier correo existente e indica si está en el club, en reservas, en ambos o solo en WordPress, y no modifica esa cuenta. Teléfono y DNI pueden ir vacíos. El aviso de contraseña se envía solo después de guardar la ficha. La vista previa vive 15 minutos en el transient `sulayr_csv_preview_{usuario}`. Confirmar repite la validación. La exportación y la plantilla usan punto y coma. Al importar, el separador es el punto y coma salvo que la cabecera tenga más comas que punto y coma. Se admite BOM. El tope de 2 MB es una protección de esta implementación, no del requisito. No hay tope de filas. La plantilla trae una fila de ejemplo; la pantalla avisa de que confirmarla crearía esa cuenta.

La exportación exige `sulayr_manage_swimmers`, usa consultas preparadas y antepone un apóstrofo si la celda empieza por `=`, `+`, `-`, `@` o un salto. Lleva BOM UTF-8. El estado de una reserva exportada es «Registrada»: no hay otro estado guardado. «Nadador» o «Gestor» sale de comparar `created_by` con `user_id`, no de `is_manual`.

Los filtros de esta fase son `estado=todos|activos|inactivos` (por defecto todos) y `q` (nombre o correo, 100 caracteres). No se ha añadido paginación. El desplegable de «reservar para» sigue llamando a la lista sin filtros. El contador y el filtro por origen se añadieron después y están descritos en «Listado de nadadores».

El tema activo del club en este entorno es Twenty Twenty-Five, no `ct-sulayr-theme`. El rediseño está en `wp-content/themes/ct-sulayr-theme/profile-bento.css` y se encola desde `functions.php` después de la hoja del plugin del club. No cambia PHP, ACF, pestañas, campos ni URLs. Cubre la pestaña de información y la de edición del propio perfil. No hay perfil público de otro deportista. Para verlo hay que tener activo `ct-sulayr-theme`. En la comprobación se activó un momento, se midió la rejilla a 1280 px, a 954 px y a 390 px, y se volvió a dejar Twenty Twenty-Five.

Un gestor sin ficha de nadador puede cambiar su contraseña en Mi perfil y no puede pedir el cambio de correo: no hay fila donde guardar el token.

Cobertura futura con PHPUnit, todavía sin suite: límite semanal y su concurrencia, validación del CSV, permisos de ficha vinculada y de fotografía, y confirmación del correo.

Comprobado con scripts que cargan WordPress y usuarios temporales, borrados al terminar, y con el panel y el perfil del club en el navegador. El envío de correo se interceptó con `pre_wp_mail`. No se ejecutó PHP 7.4. No se subió por HTTP un archivo que no fuera imagen con extensión de imagen. No se recorrió en el navegador el POST de previsualizar y confirmar el CSV: la validación y `createImported()` sí se ejecutaron desde PHP.

Comprobado con `do_shortcode` para nadador, gestor, administrador y anónimo, y en el navegador con el CSS del tema a 1440 px y a 390 px. El día 2 de octubre de 2026 salió como hoy. Las sesiones ya empezadas se vieron atenuadas, las reservadas por el nadador en azul y las libres en verde. El gestor conservó detalle, capacidad, habilitar o deshabilitar, reserva para otro y cancelación. Un nadador que pide la vista de nadadores o un mes futuro sigue en el mes en curso. No se envió ningún POST de reserva o cancelación, para no alterar las reservas existentes. No aparecieron avisos en `debug.log`. No hay PHPUnit. `SPEC.md`, `ACEPTACION.md` y `FASES.md` no se han modificado: esto no reabre ni cierra criterios de aceptación.

## Fase 11

La especificación está en `SPEC-FASE11.md`. `SPEC.md` y `SPEC-MEJORAS.md` no se han modificado.

### Acceso Club → Reservas

Los dos sitios son subdominios de la misma red: `clubtriatlonsulayr.local.com` y `cnnazari.clubtriatlonsulayr.local.com`. `SUBDOMAIN_INSTALL` es verdadero y WordPress define `COOKIE_DOMAIN` como `.clubtriatlonsulayr.local.com`. La cookie `wordpress_logged_in_*` ya es de red. No se ha añadido un token de un solo uso: `wp_set_auth_cookie()` escribiría esa misma cookie, así que un handshake no crearía una sesión distinta ni evitaría el cierre de sesión de red.

La identidad sale de la cookie firmada y del token de sesión guardado en la cuenta. No viaja la contraseña. Un `user_id` en la URL no autentica a nadie. Cambiar el identificador dentro de la cookie rompe la firma y `wp_validate_auth_cookie()` la rechaza. Una cookie caducada también se rechaza.

Estar autenticado en el club no autoriza reservas. El shortcode del blog 2 sigue exigiendo la capacidad y, si no es gestor ni administrador, una ficha activa. Si no se cumple, el panel muestra el aviso que ya existía y no crea una ficha. La sincronización no se dispara al pulsar el enlace.

El enlace **Reservas de piscina** sale en la tarjeta del perfil del deportista, con `do_action( 'sulayr_athlete_profile_card' )`. Lo pinta `CrossSite` solo si `SyncService::isEligibleAthlete()` es verdadero: rol `athlete` en el club, `sulayr_user_swimming = 1` y `sulayr_user_athlete_status = active`. Es la misma condición que `profile()` usa para sincronizar. El perfil de Mi cuenta solo lo ve el propio deportista autenticado. Quien no es elegible no ve el enlace. La URL apunta al panel con `sulayr_desde=club`. Ese parámetro no elige al usuario: solo marca el origen para la vuelta tras cerrar sesión.

En este entorno el club se sirve por HTTP y `is_ssl()` es falso. La cookie de WordPress lleva el indicador Secure cuando el sitio es HTTPS. Si `home` del sitio de reservas empieza por `https://`, la marca de origen no se guarda en una petición que no sea HTTPS.

### Vuelta al perfil del club

**Mi perfil en el club** aparece en el menú del panel cuando el usuario actual es `athlete` con estado `active` en el club y tiene ficha activa en reservas. El destino es el endpoint de WooCommerce `perfil-deportista` del propio usuario, calculado en el blog 1. No lleva `user_id`. Un nadador que no es deportista del club no ve el enlace. Pulsarlo no cierra la sesión.

### Cerrar sesión

**Cerrar sesión** usa `wp_logout_url()`, con el nonce `log-out` de WordPress. `wp_logout()` borra el token de sesión y la cookie de `COOKIE_DOMAIN`, así que la sesión termina en el club y en reservas a la vez. No hay un cierre solo de reservas: la cookie es la misma y dejarla viva mantendría al usuario autenticado en los dos sitios.

Si la petición trae `sulayr_desde=club` o la cookie de origen `sulayr_reservas_origen=club`, el destino es la portada del club. Si no, es la página del panel, ya sin sesión. La cookie de origen es HttpOnly, SameSite Lax, dura 12 horas, no es de autenticación y se borra al salir. `allowed_redirect_hosts` solo añade los dos hosts configurados. Otra dirección se rechaza.

### Menú en la cabecera

El tema `reservations-theme` llama a `do_action( 'sulayr_reservas_brand_menu' )` dentro de `div.reservations-brand`. `Shortcode::brandMenu()` pinta ahí `nav.sulayr-menu` si el sitio es el de reservas y el usuario es gestor o nadador con ficha activa. `PanelRenderer` marca que el menú ya está impreso y el panel no repite la barra: la rejilla usa `sulayr-shell-no-nav`. Si otro tema no llama al hook, el panel pinta el menú en `div.sulayr-nav-card` como antes. Se quitó el título «Reservas de natación».

`panel.js` suma el ancho de los enlaces y lo compara con el hueco del menú. Si no caben, añade `is-collapsed` y muestra el botón hamburguesa. El desplegable se abre bajo la cabecera, alineado a la derecha. `aria-expanded` cambia con él. Escape o un clic fuera lo cierran. El cálculo se repite al cambiar el ancho.

### Calendario móvil

Por debajo de 1024 px el calendario muestra un día. Desde 1024 px sigue la cuadrícula de siete columnas. La tablet de 768 px usa la vista de un día.

La semana va de lunes a domingo. El día inicial es hoy en `wp_timezone()` (`Europe/Madrid`), no la zona del navegador. El selector y el contenido comparten `data-selected`. `data-today` guarda esa fecha. El día de hoy lleva `is-today` en el selector aunque esté elegido otro día, y el título del día muestra «Hoy» solo en esa fecha. En la cuadrícula de escritorio sigue el borde y la etiqueta que ya tenía la celda. Anterior, siguiente y cada día son enlaces. Sin JavaScript recargan `sulayr_dia`. Con JavaScript cambian el día ya pintado. Una fecha fuera de la ventana cargada, o más allá de 18 meses, vuelve a pedirse al servidor o se ignora.

La ventana inicial va de tres semanas antes a cuatro después, alineada a lunes y domingo. Los meses que toca esa ventana se leen con `PanelQuery::monthSessions()`. No hay otra consulta ni otra librería. Viernes y domingo salen en el selector. Si no tienen filas, el texto es «No hay sesiones programadas.» Los horarios no están escritos en el JavaScript: salen de las sesiones.

El swipe escucha `touchstart` y `touchend` en el día visible. Exige más de 48 px en horizontal y que ese desplazamiento supere al vertical. No actúa si el gesto empieza en un enlace, botón o formulario. `touch-action: pan-y` deja el scroll vertical. Las flechas del teclado en el selector también cambian de día.

Reservar y cancelar siguen siendo los formularios de `quickActions`, con el nonce `sulayr_panel`. Al volver se conserva `sulayr_dia` de ese día. La cuadrícula de escritorio no lo añade.

### Pruebas de esta fase

Usuarios temporales, borrados al terminar. Elegible, sin natación, deportista inactivo, anónimo, nadador, ficha inactiva, suscriptor, gestor y administrador. Cookie válida, caducada, manipulada y basura. Reserva y cancelación de una sesión futura. Cuarta reserva de la misma semana rechazada por el límite y las tres anteriores canceladas. CSV de plantilla analizado. Sincronización manual de un elegible, con la ficha creada y luego borrada.

En el navegador, a 390, 414 y 768 px, la vista de un día, sin scroll horizontal. Anterior, siguiente, viernes, domingo, el paso de domingo a lunes y el swipe. El gesto que empieza en «Detalle» no cambia de día. A 1024 y 1280 px sigue la cuadrícula y la vista móvil no se muestra. No se recorrió el formulario de acceso de WordPress dentro del navegador: la página autenticada se pidió con la cookie de sesión generada en el servidor.

## Listado de nadadores

El listado de la fase 10 ganó un contador y un filtro por origen. No cambia el esquema ni las fichas.

`PanelQuery::swimmerCensus()` cuenta todas las filas de `sulayr_swim_swimmers`, sin leer `estado`, `origen` ni `q`. Devuelve activos e inactivos en tres grupos: total, `nazari` (`linked_to_club = 0`) y `sulayr` (`linked_to_club = 1`). `PanelRenderer::swimmerCensus()` lo pinta encima del filtro, en `p.sulayr-swimmer-total`, con el texto «Contador:», «Total:», «CN Nazarí:» y «CT Sulayr:». Cada grupo sale como «N nadadores activos / N nadadores inactivos». El singular se usa solo cuando la cantidad es 1.

El filtro añade `origen=todos|nazari|sulayr`. `todos` no se escribe en la URL. Se combina con `estado` y `q`. `swimmers()` traduce `nazari` a `linked_to_club = 0` y `sulayr` a `linked_to_club = 1`. La columna Origen muestra «CN Nazarí» o «CT Sulayr». La ficha sigue diciendo «Alta manual» o «Vinculado al club».

Ordenar conserva `origen`. **Exportar CSV** recibe el mismo `origen` y exporta las filas filtradas. El archivo no añade una columna de origen: sigue siendo nombre, apellidos, email, teléfono, estado, fecha de alta y límite semanal. El desplegable «Reservar para» no usa este filtro.
