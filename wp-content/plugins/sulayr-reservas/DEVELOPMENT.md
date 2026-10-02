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

## Riesgos

### Esquema de base de datos

La versión actual es `1`, opción `sulayr_reservas_db_version` en cada sitio. Una modificación futura sube esa versión y pasa por `dbDelta` o por una migración explícita. Si el cambio puede alterar datos existentes, la migración tiene que conservarlos. Una actualización del esquema no borra datos. La versión no se marca como instalada si falta alguna tabla prevista.

### Concurrencia de las reservas

Implementada y probada en `BookingService`. Cada operación abre `START TRANSACTION` y cierra con `COMMIT` o `ROLLBACK`. El orden de bloqueo es siempre el mismo: primero la ficha del nadador (`FOR UPDATE`) y después la sesión (`FOR UPDATE`). La cancelación bloquea además la fila de la reserva. Así el límite semanal no depende solo del aforo de una sesión, y dos operaciones sobre sesiones distintas del mismo usuario quedan en serie.

Después de bloquear se vuelven a contar las plazas y las reservas de la semana. La clave única `(session_id, user_id)` cubre el duplicado si el recuento no llegara a verlo. Un interbloqueo devuelve `failed` y no se reintenta solo. En las pruebas no apareció. Un reintento limitado queda pendiente de decidir; no forma parte de esta fase.

Queda sin probar la generación simultánea de un mismo mes. Está en «Pruebas pendientes».

### Multisite

Cada operación corre en su sitio. El contexto se restaura aunque la operación falle. Las tablas de negocio permanecen en el blog 2 y el registro de sincronización en el blog 1. Crear roles o capacidades fuera de `onBookings()` o de una petición cuyo blog actual sea el 2 filtraría `nadador` y `gestor` al club.

### Fechas y horas

Ambos sitios usan `Europe/Madrid`. La lógica de mes, ventana de generación, inicio de sesión, cancelación y semana debe usar `wp_timezone()` o `DateTimeImmutable` con esa zona. No se usa la zona del servidor ni un desplazamiento fijo.

### WooCommerce en el sitio de reservas

Al borrar un usuario de prueba del blog 2, WooCommerce ejecutó ganchos de `remove_user_from_blog` y `deleted_user` contra tablas `wp_2_wc_*` que no existen. El usuario sí se eliminó. No es un cambio de esta fase. Cuando se retire a un nadador del sitio de reservas habrá que comprobar que esos avisos no interrumpen la operación. Este plugin no debe crear esas tablas.

### Acceso a wp-admin

El plugin del club redirige a cualquier `athlete` que entre en `wp-admin`, en el sitio donde ese código se ejecuta. El bloqueo de este plugin solo actúa en el blog 2 y solo si el usuario tiene exclusivamente `nadador`, `gestor` o ambos. Un administrador, un superadministrador u otro rol del sitio no se redirige. `admin-ajax.php` no se redirige, porque WordPress lo necesita; la autorización de cada acción seguirá comprobándose por capacidad.

Hasta que exista el panel, la redirección lleva a la portada del sitio de reservas. No es una página de reservas.

## Decisiones pendientes

- Confirmar si el agrupamiento de capacidades de la tabla anterior se mantiene. Cubre el SPEC sin añadir permisos.
- Valorar más adelante un reintento limitado si una reserva o una cancelación termina en interbloqueo. Ahora responde `failed` y no reintenta.
- La URL de salida del `wp-admin` pasará a ser el panel cuando exista. Mientras tanto es la portada.
- Retención del registro técnico: propuesta de 1000 filas o 180 días, aún no implementada.
- Las sales de `wp-config.php` siguen siendo los placeholders de instalación. Afectan a las sesiones reales y no forman parte del plugin.

## Verificaciones para fases posteriores

- La generación ya usa transacción y claves únicas; la prueba de dos peticiones a la vez está en «Pruebas pendientes».
- La sincronización debe leer el club en `profile_update` con prioridad 30 y escribir solo la tabla local de nadadores.
- Al asignar `nadador` a una cuenta nueva hay que pasar el rol en la creación, para que `UserService::assign_athlete_role()` no lo sustituya si el usuario aún es `subscriber`.
- Usar `add_role( 'nadador' )` y no `set_role()`, para no quitar `gestor` o `administrator`.
- El bloqueo de `wp-admin` debe seguir limitado al blog 2 cuando aparezcan formularios públicos. Esos formularios no deben depender de `admin-post.php`.
- Comprobar en el navegador, en escritorio y en móvil, el panel de nadador y el de gestor cuando existan.

## Fase 3

Implementada en `Roles\Capabilities`, `Roles\RoleRegistrar` y `Access\AdminAccess`.

`RoleRegistrar` sincroniza los roles al activar el plugin, dentro de `onBookings()`, y en cada petición del blog 2 si la versión de roles (`sulayr_reservas_roles_version`, valor `1`) no coincide o falta un rol. Repetir la activación no crea roles duplicados ni toca el blog 1.

`AdminAccess` redirige en `admin_init` y oculta la barra de administración. No actúa en el blog 1, ni con administradores, ni con superadministradores, ni si el usuario tiene otro rol además de `nadador` o `gestor`. No interrumpe AJAX ni cron.

La redirección HTTP no se ha recorrido en un navegador. La decisión de bloquear o no se ha comprobado con usuarios de prueba creados y eliminados en el blog 2.

## Pruebas pendientes

Generación simultánea del mismo mes. La transacción y las claves únicas de `generated_month` y de `(session_date, start_time)` son la base, pero no se ha lanzado la prueba con dos peticiones concurrentes. Hay que comprobar que no quedan sesiones duplicadas ni un mes a medias si dos gestores generan el mismo mes a la vez. No bloquea el avance de las fases siguientes.

## Fase 4

Implementada en `Sessions\Schedule`, `Sessions\GenerationWindow` y `Sessions\SessionGenerator`. No hay cron ni pantalla: la generación se invoca con `generate()`.

La ventana del SPEC, sección 7.4, queda aprobada así:

- Si el mes en curso no está generado, se ofrece hasta el día 26 inclusive. El día 27 pasa al mes siguiente y el mes en curso ya no se puede crear.
- Si el mes en curso ya está generado, el mes siguiente se ofrece desde el día 26.
- El 25 de septiembre no se puede generar octubre. El 26, solo si septiembre ya está generado. Si septiembre sigue pendiente ese día, el botón sigue ofreciendo septiembre.
- Nunca se ofrecen dos meses a la vez.

Repetir la generación no hace `UPDATE`. Conserva capacidad, sesiones deshabilitadas y reservas. Si el mes quedó a medias, solo inserta las sesiones que faltan, también fuera de la ventana, para no dejar el mes marcado e incompleto. Un mes nuevo fuera de la ventana se rechaza. Quien no tiene `sulayr_manage_sessions` también.

Un fallo dentro de la transacción hace `ROLLBACK`. Las fechas de los días se calculan con `wp_timezone()`. Las horas se guardan como hora de pared (`15:00:00`), sin convertirlas a UTC, así el cambio de hora de octubre no las desplaza.

Comprobado cargando WordPress con fechas fijas de 2026: septiembre 40 sesiones, octubre 39, noviembre 38, ninguna en viernes ni domingo. La repetición devolvió `unchanged`. Una sesión deshabilitada con capacidad 10 y una reserva siguieron igual. El 26 de octubre de 2026, lunes posterior al cambio de hora, se guardó a las 15:00. El blog al terminar seguía siendo el 1 y el club no tiene tabla de sesiones. La generación simultánea queda como prueba pendiente.

## Fase 5

Implementada en `Bookings\BookingService` y `Bookings\BookingRepository`. No hay formulario ni endpoint: las fases siguientes llaman a `book()` y `cancel()`. El actor es el usuario actual, comprobado en el blog 2 después del cambio de sitio.

La ficha que se copia en la reserva sale de `sulayr_swim_swimmers`, no de `wp_usermeta`. El nadador de la reserva tiene que estar activo. Una reserva de gestor para una sesión ya empezada se guarda con `is_manual = 1` y `manager_id`. Una reserva futura, aunque la haga un gestor para otro, no lleva ese indicador. `created_by` es quien ejecuta la operación.

La sesión se considera empezada cuando `now` alcanza la hora de inicio. Hasta ese momento se aplican habilitación, aforo y límite. A partir de ese momento solo puede reservar quien tiene `sulayr_book_for_others`, también si la sesión está deshabilitada, y solo si queda plaza. Esa regla está aprobada en el SPEC, sección 9.3. La cancelación del nadador exige 60 minutos o más; en el instante exacto se permite. El gestor, con `sulayr_cancel_any`, cancela sin ese plazo. La fila se borra.

Comprobado con fechas fijas y usuarios de prueba, borrados al terminar junto con sus sesiones y reservas. Cinco rondas con dos procesos PHP: la última plaza quedó en una sola reserva; la reserva duplicada, en una sola fila; dos sesiones simultáneas del mismo usuario no pasaron de 5 en la semana; reserva y cancelación a la vez dejaron 0 o 1 plazas ocupadas, nunca 2. El blog al terminar seguía siendo el 1 y el club no tiene tabla de reservas. No se escribió `first_name` ni el teléfono global del usuario.
