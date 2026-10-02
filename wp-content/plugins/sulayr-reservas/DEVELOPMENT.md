# Desarrollo del plugin de reservas

Referencia técnica para implementar `sulayr-reservas`. Los requisitos funcionales están en [SPEC.md](../../../SPEC.md). Por qué el código existente obliga a ciertas adaptaciones está en [ANALISIS.md](../../../ANALISIS.md). El estado de las fases está en [FASES.md](../../../FASES.md).

Si este documento contradice al SPEC, manda el SPEC. No se cambian requisitos ni decisiones ya aprobadas desde aquí.

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
| `Admin\NetworkSettingsPage` | Pantalla de red para los identificadores de sitio |
| `Sessions\Schedule` | Horario fijo y plazas de un mes |
| `Sessions\GenerationWindow` | Mes que corresponde ofrecer en una fecha |
| `Sessions\SessionGenerator` | Crea las sesiones en el blog 2 sin modificar las ya existentes |
| `Bookings\BookingService` | Crea y cancela reservas en el blog 2 |
| `Bookings\BookingRepository` | Lectura y escritura de esas reservas, dentro de la transacción |
| `Swimmers\SwimmerService` | Alta, activación, desactivación y baja de nadadores en el blog 2 |
| `Swimmers\SwimmerRepository` | Ficha local y reservas asociadas de ese sitio |

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
- Paneles públicos mediante shortcode del plugin. Aún no implementados.
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

Hasta que exista el panel, la redirección lleva a la portada del sitio de reservas. No es una página de reservas. La decisión de a quién bloquear se probó con usuarios de prueba. La redirección HTTP no se ha abierto en un navegador.

### Sales de `wp-config.php`

Siguen siendo los placeholders de instalación. Afectan a las sesiones reales de WordPress y no forman parte del plugin.

## Pruebas pendientes

No hay suite PHPUnit. Lo comprobado hasta la fase 6 se ha ejecutado con scripts que cargan WordPress y, en la fase 5, con dos procesos PHP distintos. Queda pendiente decidir e implantar una estrategia de pruebas del plugin, incluidos los servicios y las operaciones de concurrencia.

- Generación simultánea del mismo mes. La fase 4 probó un mes nuevo, la repetición, meses consecutivos, la ausencia de duplicados en una sola ejecución, el aislamiento en el blog 2, la conservación de sesiones deshabilitadas y reservas, el `ROLLBACK` y la zona horaria. No se lanzaron dos procesos a la vez sobre el mismo mes. La transacción y las claves únicas de `generated_month` y de `(session_date, start_time)` son la base, no la prueba. Hay que comprobar que no quedan sesiones duplicadas ni un mes a medias. No bloquea el avance de las fases siguientes.
- Envío real del correo de alta. La fase 6 comprobó que una cuenta nueva pide `wp_send_new_user_notifications()` y que una cuenta reutilizada no lo pide. El envío se interceptó. No se ha visto el mensaje en un buzón.
- PHP 7.4. Sigue siendo la versión objetivo. El código evita sintaxis de PHP 8. El entorno de las pruebas es PHP 8.4.17. No se ha ejecutado el plugin con PHP 7.4.
- Redirección HTTP de `nadador` y `gestor` al entrar en `wp-admin` del blog 2. La regla se probó sin navegador.
- Paneles de nadador y de gestor, en escritorio y en móvil, cuando existan. Aún no están implementados.
- La concurrencia de reservas de la fase 5 sí se probó. No vuelve a esta lista.

## Mejoras futuras

- Dejar de ocultar el error de WooCommerce y resolverlo de otra forma. Antes, comprobar si hay más ganchos de plugins instalados con el mismo efecto. No crear `wp_2_wc_customer_lookup` solo para callar el aviso.
- Reintento limitado si una reserva o una cancelación termina en interbloqueo. Hoy responde `failed`.
- Cuando exista el panel, la salida del `wp-admin` debe ir allí. Mientras tanto es la portada.
- Retención del registro técnico de sincronización. Propuesta aún no implementada: 1000 filas o 180 días.
- Confirmar si el agrupamiento de capacidades de la tabla anterior se mantiene. Traduce el SPEC y no añade permisos; no está cerrado como decisión de producto.

## Verificaciones para la sincronización

- Debe leer el club en `profile_update` con prioridad 30 y escribir solo la tabla local de nadadores.
- Si la cuenta ya está en el blog 2, debe usar `add_role( 'nadador' )`. El alta manual ya lo hace así.
- El bloqueo de `wp-admin` debe seguir limitado al blog 2 cuando aparezcan formularios públicos. Esos formularios no deben depender de `admin-post.php`.

## Fase 3

Implementada en `Roles\Capabilities`, `Roles\RoleRegistrar` y `Access\AdminAccess`.

`RoleRegistrar` sincroniza los roles al activar el plugin, dentro de `onBookings()`, y en cada petición del blog 2 si la versión de roles (`sulayr_reservas_roles_version`, valor `1`) no coincide o falta un rol. Repetir la activación no crea roles duplicados ni toca el blog 1.

`AdminAccess` redirige en `admin_init` y oculta la barra de administración. No actúa en el blog 1, ni con administradores, ni con superadministradores, ni si el usuario tiene otro rol además de `nadador` o `gestor`. No interrumpe AJAX ni cron.

La redirección HTTP no se ha recorrido en un navegador. La decisión de bloquear o no se ha comprobado con usuarios de prueba creados y eliminados en el blog 2. El recorrido en navegador está en «Pruebas pendientes».

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

Implementada en `Swimmers\SwimmerService`. No hay formulario. Operaciones: `create()`, `deactivate()`, `reactivate()` y `remove()`. El SPEC no define editar el nombre, el teléfono, el correo ni el DNI de una ficha ya creada, así que no hay operación de edición. Cambiar el correo tocaría `wp_users` y el perfil del club.

Una cuenta nueva se crea en el blog 2 con el rol `nadador` ya en `wp_insert_user()`, para que el gancho del club no la convierta en `athlete`. Después se llama a `wp_send_new_user_notifications()` solo para el usuario, para que elija contraseña. En las pruebas esa llamada se interceptó: no se comprobó un buzón real. Si el correo ya existe, se reutiliza esa cuenta y no se envía aviso: no se cambia la contraseña ni `first_name`, `sulayr_user_phone` ni `sulayr_user_dni`. Si todavía no está en el blog 2, `add_user_to_blog()` le asigna `nadador`. Si ya está, `add_role( 'nadador' )`, para no quitar `gestor`, `editor` o `administrator`. La ficha local nace activa y con `linked_to_club = 0`. El vínculo con el club lo pondrá la sincronización.

La creación del usuario de WordPress y la inserción de la ficha no comparten una transacción. Si la ficha falla después de crear la cuenta, otra alta con el mismo correo reutiliza esa cuenta. La baja confirma primero las tablas del plugin y después llama a `remove_user_from_blog()`. Si esa llamada fallara, una segunda baja vuelve a intentar retirarlo del sitio.

Desactivar pone `active = 0` y borra las reservas cuya sesión aún no ha empezado. El historial pasado conserva `user_id` y el snapshot. Reactivar no las recupera. `BookingService` rechaza reservas de un nadador inactivo. La baja borra las futuras, pone `user_id` a NULL en las pasadas, elimina la ficha (y con ella el DNI) y retira al usuario del blog 2. No usa `wp_delete_user()`. Si además tiene otro rol en ese blog, o es superadministrador, solo se le quita `nadador` y sigue en el sitio.

Comprobado con usuarios de prueba, borrados al terminar: alta, duplicado, correo inválido, nadador sin permiso, reutilización de una cuenta del club sin tocar su contraseña ni sus metas, desactivación, reserva rechazada, reactivación sin restaurar la plaza, fallo antes del `COMMIT`, baja de quien solo es nadador y baja de quien también es editor. El blog al terminar seguía siendo el 1. El aviso de WooCommerce no interrumpe la retirada; ocultarlo no es la solución, y queda en «Incidencias conocidas».
