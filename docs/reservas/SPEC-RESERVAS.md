# SPEC-RESERVAS.md — Sistema de reservas de sesiones de natación

Este documento fija los requisitos del sistema de reservas (`sulayr-reservas`). El sufijo `-RESERVAS` identifica las specs de este sistema. Las de la web del club (`club-triatlon-sulayr`) conservan su nombre. El índice que separa unas y otras está en [FASES.md](../FASES.md).

Aquí están los requisitos funcionales y las decisiones ya aprobadas de reservas. La adaptación al código existente está en [ANALISIS.md](ANALISIS.md). Las fases 0 a 11, ya cerradas, están en [FASES.md](../FASES.md).

## 1. Objetivo

Desarrollar un plugin de WordPress que implemente un sistema de reservas de sesiones de natación para un club deportivo.

El sistema funcionará en una instalación WordPress Multisite que contiene, al menos, dos sitios:

1. **Sitio del club:** web existente donde se gestionan los deportistas y sus datos.
2. **Sitio de reservas:** nueva web donde los nadadores consultan las sesiones y gestionan sus reservas.

El plugin debe ser independiente del tema y proporcionar los paneles, reglas de negocio, persistencia y operaciones necesarias para gestionar las reservas.

El sistema debe permitir:
- A los nadadores consultar el calendario del mes en curso y reservar sesiones.
- A los gestores administrar nadadores, sesiones y reservas desde un panel público.
- A los administradores acceder a las mismas funcionalidades que los gestores y, además, al `wp-admin`.
- Sincronizar desde el sitio del club los deportistas que cumplan las condiciones de natación, creando o actualizando su acceso al sitio de reservas.

La interfaz debe ser responsive y usable desde móvil y escritorio.

## 2. Arquitectura y alcance

### 2.1. WordPress Multisite

El sistema se ejecutará en una única instalación WordPress Multisite con dos sitios diferenciados.

- El sitio del club conserva su funcionalidad actual.
- El sitio de reservas contiene la interfaz y los datos propios del sistema de reservas.
- El plugin debe ser consciente del sitio en el que se está ejecutando y no alterar el funcionamiento del sitio del club salvo por la integración de sincronización descrita en este documento.
- Los usuarios comparten la tabla global `wp_users`, pero su pertenencia y roles son específicos de cada sitio.
- Un mismo `user_id` puede tener el rol `athlete` en el club y `nadador` en reservas, con una única cuenta y contraseña. El slug `athlete` es el rol existente del club; su etiqueta visible es «Deportista».
- Un usuario creado únicamente en reservas no tiene por qué pertenecer al sitio del club.

La implementación podrá utilizar `switch_to_blog()` para ejecutar operaciones en el contexto del sitio de reservas desde el sitio del club. Todo cambio de contexto debe restaurarse mediante `restore_current_blog()` en un bloque `finally` o mecanismo equivalente.

No se debe eliminar una cuenta global de WordPress al retirar a un usuario de un sitio.

### 2.2. Independencia del tema

El plugin no debe depender de plantillas ni estilos de un tema concreto. Debe proporcionar sus propias vistas, estilos y scripts, integrándose mediante shortcodes, bloques o rutas públicas según se determine durante la implementación.

No se establece dependencia de ACF para el funcionamiento del sistema de reservas. La integración con los campos ACF del club se realizará desde el sitio del club, que enviará la orden de sincronización.

### 2.3. Sitios y configuración

Los identificadores de los sitios (blog IDs) del club y de reservas no deben quedar dispersos ni codificados en múltiples lugares. Deben centralizarse en una configuración del plugin o mecanismo equivalente, con validaciones para evitar ejecutar operaciones en el sitio equivocado.

## 3. Roles y permisos

El sistema contempla tres roles:

- `nadador`
- `gestor`
- `administrator` (rol nativo de WordPress)

Los permisos deben implementarse mediante capacidades específicas, comprobadas siempre en el servidor. No se debe confiar únicamente en ocultar botones o elementos de la interfaz.

### 3.1. Nadador

El nadador:
- Accede únicamente al panel público de reservas.
- No puede acceder al `wp-admin`.
- Puede consultar el calendario del mes en curso.
- Puede consultar las plazas ocupadas y disponibles de cada sesión.
- Puede reservar una plaza para sí mismo.
- Puede cancelar sus propias reservas si faltan al menos 60 minutos para el inicio.
- Puede consultar sus reservas activas y su historial.
- No puede consultar ni modificar los datos de otros nadadores.
- No puede modificar la capacidad ni el estado de las sesiones.
- Tiene un máximo de 5 reservas registradas por semana natural (lunes a domingo).

### 3.2. Gestor

El gestor:
- Accede al panel público de gestión.
- No debe tener acceso al `wp-admin`.
- Puede consultar el calendario y las reservas de todos los nadadores.
- Puede consultar sesiones pasadas y sus asistentes.
- Puede reservar para sí mismo y para otros usuarios.
- Puede cancelar cualquier reserva, independientemente del tiempo que falte para el inicio.
- Puede consultar sus propias reservas activas e históricas.
- Puede listar nadadores y consultar el perfil y el historial de reservas de cada uno.
- Puede crear nadadores manualmente.
- Puede activar o desactivar manualmente nadadores.
- Puede eliminar definitivamente un nadador del sitio de reservas, respetando las reglas de conservación de historial.
- Puede modificar la capacidad de una sesión.
- Puede habilitar o deshabilitar una sesión sin eliminarla.
- Puede generar las sesiones del siguiente mes conforme a la ventana de generación definida.
- No puede modificar los datos ni los campos del deportista en el sitio del club.

La activación o desactivación manual en reservas es independiente de los campos del club. Sin embargo, cuando se ejecute una sincronización desde el club, el estado enviado por el club prevalecerá y podrá volver a activar o desactivar al usuario.

### 3.3. Administrador

El administrador tiene todas las funcionalidades del gestor y, además, acceso normal al `wp-admin` del sitio de reservas.

En el sitio del club, únicamente los administradores podrán modificar los campos que determinan la sincronización y utilizar el botón de sincronización manual. La pantalla de registro técnico también será exclusiva para administradores del club.

## 4. Usuarios y pertenencia a los sitios

Un usuario puede encontrarse en una de estas situaciones:

1. **Solo deportista:** pertenece al sitio del club, pero no al de reservas.
2. **Solo nadador:** pertenece al sitio de reservas y ha sido creado manualmente por un gestor.
3. **Deportista y nadador:** pertenece a ambos sitios y está vinculado mediante el mismo `user_id`.

Los usuarios existentes en el sitio del club que se incorporen a reservas deben conservar su cuenta y contraseña actuales. No se debe crear una segunda cuenta de WordPress.

Cuando el gestor crea un usuario nuevo desde reservas:
- El gestor no establece la contraseña.
- El sistema crea la cuenta con el mecanismo nativo de WordPress para establecer la contraseña y envía al usuario el correo correspondiente.
- Si el email ya pertenece a una cuenta de WordPress, se debe reutilizar esa cuenta y añadirla al sitio de reservas con el rol correspondiente, sin cambiar su contraseña.
- El nombre de usuario debe generarse automáticamente si es necesario, garantizando su unicidad.

La desactivación en reservas debe impedir el acceso al sistema de reservas, pero no debe bloquear la cuenta global ni el acceso del usuario al sitio del club.

## 5. Sincronización entre el sitio del club y el sitio de reservas

### 5.1. Fuente de verdad

El sitio del club es la fuente de verdad para determinar si un deportista cumple las condiciones de sincronización. La comunicación es unidireccional:

**Club → Reservas**

Los cambios realizados en reservas nunca deben modificar los campos ni los datos del deportista en el club.

La sincronización se ejecutará desde el sitio del club cuando:
- Un administrador guarde el perfil de un deportista.
- Un administrador pulse el botón «Sincronizar ahora en reservas» en el perfil del deportista.

No se requiere una sincronización inicial automática masiva. La primera incorporación de los deportistas existentes se realizará manualmente desde el club, activando los campos correspondientes y guardando los perfiles o utilizando el botón de sincronización.

### 5.2. Condiciones para estar sincronizado y activo

El usuario debe tener el rol `athlete` en el sitio del club y cumplir simultáneamente estas condiciones:

- `sulayr_user_swimming = "1"`
- `sulayr_user_athlete_status = "active"`

Los valores se deben comparar con sus valores almacenados reales, teniendo en cuenta que son campos select de ACF. No se debe inferir la condición a partir de las etiquetas visuales del campo.

### 5.3. Alta o actualización

Cuando se ejecute la sincronización y el deportista cumpla las condiciones:

- Si el usuario no existe en el sitio de reservas, se le añadirá con el rol `nadador` y quedará activo.
- Si ya existe como usuario independiente creado manualmente en reservas, se vinculará a la sincronización del club sin crear otra cuenta.
- Si ya existe y está vinculado, se actualizarán los datos sincronizados y se activará.
- Se conservarán sus reservas e historial existentes al vincularlo o reactivarlo.
- La operación no debe duplicar usuarios ni reservas.

La relación de sincronización se identificará mediante el `user_id` global de WordPress. El sistema debe distinguir si el usuario está vinculado al club o si es un nadador independiente creado manualmente.

### 5.4. Actualización de datos

Al sincronizar, se actualizarán únicamente los datos personales que necesita el plugin de reservas para gestionar al nadador. Como mínimo:

- Nombre
- Apellidos
- Email
- Teléfono
- DNI

Los valores se leen del perfil del deportista en el sitio del club:

- Nombre: `first_name`.
- Apellidos: `last_name`.
- Email: `wp_users.user_email`.
- Teléfono: `sulayr_user_phone`.
- DNI: `sulayr_user_dni`.

La copia se guarda en la tabla local de nadadores del sitio de reservas. La sincronización no escribe esas claves globales.

Si el valor de un campo del club está vacío, se sobrescribirá con un valor vacío en la copia local de reservas. No se conservará el valor anterior.

Los campos propios del sistema de reservas que no formen parte de los datos sincronizados no deben sobrescribirse.

### 5.5. Desactivación por sincronización

Si el usuario deja de cumplir cualquiera de las condiciones de sincronización, el sitio del club enviará una orden para desactivarlo en reservas.

Esto incluye, entre otros casos:
- Natación desactivada.
- Estado del deportista distinto de `active` (por ejemplo, `baja`, `standby` o `moroso`).
- El usuario deja de tener el rol `athlete`.
- El usuario deja de pertenecer al sitio del club o es eliminado de él.

Al desactivarlo:
- Se impedirá su acceso al sistema de reservas.
- Se impedirá que realice nuevas reservas.
- Se eliminarán sus reservas futuras.
- Se conservará su historial de sesiones pasadas.
- Se conservarán los datos personales necesarios para mostrar el historial.

Si el usuario vuelve a cumplir las condiciones, se reactivará el mismo usuario, siempre que conserve el mismo `user_id`. Las reservas futuras eliminadas no se restaurarán y el usuario deberá reservar de nuevo.

### 5.6. Prevalencia de la sincronización

El gestor puede activar o desactivar manualmente a cualquier nadador desde reservas, incluidos los usuarios sincronizados.

Una modificación manual en reservas no modifica los datos del club ni inicia una sincronización inversa.

El estado manual permanecerá vigente hasta que se produzca una nueva sincronización desde el club. Cuando esta se ejecute, el estado se recalculará conforme a las condiciones del club:
- Si cumple las condiciones, se activará.
- Si no las cumple, se desactivará.

Por tanto, la sincronización del club prevalece en el momento en que se ejecuta, aunque el gestor haya realizado previamente una modificación manual.

### 5.7. Botón de sincronización manual en el club

En el perfil de cada deportista del `wp-admin` del club debe aparecer un botón «Sincronizar ahora en reservas» (o un texto equivalente).

El botón:
- Estará disponible para todos los deportistas, independientemente de los valores actuales de sus campos.
- No requerirá que se haya modificado previamente ningún dato.
- Ejecutará la misma lógica que la sincronización automática al guardar el perfil.
- Comprobará las condiciones actuales del usuario.
- Enviará los datos actuales y solicitará el alta, actualización, activación o desactivación que corresponda.
- Mostrará un resultado comprensible al administrador.
- Registrará el resultado en el registro técnico.

El botón debe estar protegido mediante las capacidades adecuadas y un nonce. La operación debe validar en el servidor que el usuario objetivo tiene el rol `athlete` en el sitio del club y que quien la solicita es un administrador autorizado.

### 5.8. Sincronización al guardar el perfil

Al guardar el perfil de un deportista desde el sitio del club, se debe ejecutar la sincronización inmediatamente después de que los datos relevantes hayan quedado guardados.

La integración debe contemplar el orden de guardado de ACF y WordPress para evitar sincronizar valores anteriores. Debe utilizar el hook adecuado para ejecutarse una vez que los metadatos relevantes estén persistidos.

La sincronización no debe ejecutarse en cada carga del perfil ni por cambios que no impliquen guardar el perfil.

### 5.9. Errores y reintentos

Si la sincronización falla:
- Se debe mostrar un aviso al administrador del club cuando el fallo se produzca durante una acción interactiva.
- Se debe registrar el error.
- No se realizarán reintentos automáticos.
- El administrador podrá volver a ejecutar la operación con el botón manual.

Los errores deben gestionarse sin dejar el sitio en un contexto de blog incorrecto. Se debe restaurar siempre el contexto original, incluso si se produce una excepción o un error inesperado.

### 5.10. Idempotencia

La sincronización debe ser idempotente. Ejecutar varias veces una misma orden con los mismos datos no debe:
- Crear usuarios duplicados.
- Crear reservas duplicadas.
- Alterar reservas existentes sin motivo.
- Generar cambios innecesarios en los datos.
- Cambiar la contraseña del usuario.

Las operaciones deben localizar al usuario mediante su `user_id` global y comprobar su pertenencia al sitio de reservas antes de añadirlo.

## 6. Registro técnico de sincronización

Debe existir una pantalla de registro técnico en el `wp-admin` del sitio del club, accesible únicamente para administradores.

Como mínimo, cada registro debe incluir:
- Fecha y hora.
- `user_id` del usuario afectado, cuando esté disponible.
- Nombre o identificador del deportista.
- Tipo de operación: automática al guardar o manual desde el botón.
- Resultado: correcto o fallido.
- Acción realizada: alta, actualización, activación, desactivación u operación sin cambios.
- Mensaje de error, si lo hubiera.

El registro debe servir para diagnosticar errores y comprobar cuándo se sincronizó un usuario. No debe mostrar contraseñas, claves, tokens ni otros secretos.

La retención, paginación y mecanismo de limpieza del registro se definirán durante la implementación, evitando un crecimiento ilimitado de datos.

## 7. Sesiones de natación

### 7.1. Horario fijo

Las sesiones se generan de acuerdo con el siguiente horario:

| Día | Hora de inicio | Hora de fin |
|---|---:|---:|
| Lunes | 15:00 | 16:30 |
| Lunes | 21:00 | 22:30 |
| Martes | 15:00 | 16:30 |
| Martes | 21:00 | 22:30 |
| Miércoles | 15:00 | 16:30 |
| Miércoles | 21:00 | 22:30 |
| Jueves | 15:00 | 16:30 |
| Jueves | 21:00 | 22:30 |
| Sábado | 13:00 | 14:30 |

No hay sesiones los viernes ni los domingos.

### 7.2. Capacidad

La capacidad inicial predeterminada de cada sesión será de 24 plazas.

El gestor y el administrador podrán modificar la capacidad de cada sesión individualmente.

Si se reduce la capacidad por debajo del número de reservas existentes:
- Las reservas existentes se conservarán.
- La sesión mostrará que está completa.
- No se permitirán nuevas reservas mientras el número de reservas sea igual o superior a la capacidad configurada.

La capacidad no puede ser inferior a cero. La validación de valores debe realizarse en el servidor.

### 7.3. Estado de la sesión

El gestor y el administrador podrán habilitar o deshabilitar individualmente una sesión.

Una sesión deshabilitada:
- No se elimina.
- Conserva sus reservas e historial.
- No admite nuevas reservas ordinarias.
- Debe mostrarse como no disponible.

La desactivación de una sesión no debe borrar las reservas existentes.

La posibilidad de añadir una reserva retroactiva de asistencia a una sesión pasada deshabilitada debe seguir estando disponible para el gestor, ya que esa operación es una corrección administrativa del historial y no una reserva ordinaria.

### 7.4. Generación mensual

Las sesiones se generan en bloque para un mes mediante una acción manual del gestor o administrador. No se generan automáticamente mediante cron.

La ventana de generación se rige por estas reglas para un mes objetivo **M**:

1. El botón para generar el mes M aparece el día 26 del mes anterior, siempre que ese mes anterior ya esté generado.
2. Si el día 26 el mes en curso todavía no está generado, el botón sigue ofreciendo ese mes y no el siguiente.
3. El botón del mes M permanece disponible hasta que se generen sus sesiones o hasta que llegue el día 27 del propio mes M.
4. Si se generan las sesiones de M, el botón desaparece inmediatamente.
5. Si llega el día 27 de M y todavía no se han generado sus sesiones, el mes M ya no se puede crear y el botón pasa a ofrecer la generación del mes siguiente.
6. El botón nunca debe permitir generar dos veces el mismo mes.
7. Nunca se ofrecen dos meses a la vez.

Ejemplo para octubre, con septiembre ya generado:
- 25 de septiembre: no aparece el botón para generar octubre.
- 26 de septiembre: aparece «Generar sesiones de octubre».
- 30 de septiembre: sigue disponible.
- 1 de octubre: sigue disponible.
- 26 de octubre: sigue disponible si aún no se ha generado octubre. Ese día no se ofrece noviembre.
- 27 de octubre: si octubre no se ha generado, el botón ofrece generar noviembre.

Si septiembre todavía no está generado:
- 26 de septiembre: se puede generar septiembre y no octubre.
- 27 de septiembre: septiembre ya no se puede crear y el botón ofrece octubre.

Las sesiones se podrán reservar desde el momento en que se hayan generado.

La operación debe ser atómica o utilizar un mecanismo equivalente que impida duplicados si dos gestores o administradores intentan generar el mismo mes simultáneamente. Debe existir una restricción única o un registro inequívoco de los meses generados.

La generación debe crear únicamente las sesiones correspondientes al horario fijo y a los días válidos del mes. No debe crear sesiones los viernes ni los domingos.

## 8. Calendario

### 8.1. Calendario del nadador

El nadador solo puede consultar el mes en curso. No puede navegar a meses anteriores ni posteriores.

Cada sesión debe mostrar:
- Fecha.
- Hora de inicio y fin.
- Estado de la sesión.
- Número de plazas ocupadas.
- Número de plazas disponibles.
- Si el usuario actual tiene una reserva en esa sesión.
- Acción de reservar o cancelar, cuando proceda.

El número de plazas ocupadas se calcula a partir de las reservas que siguen registradas. Las reservas canceladas y eliminadas no cuentan.

Las sesiones deshabilitadas o completas deben mostrarse claramente y no permitir nuevas reservas.

### 8.2. Calendario del gestor y administrador

El calendario de gestión mostrará las sesiones y permitirá consultar:
- Plazas ocupadas y disponibles.
- Usuarios que han reservado cada sesión.
- Estado de las reservas.
- Sesiones pasadas y sus asistentes.

El gestor podrá acceder a la información histórica de sesiones y asistentes. El calendario operativo seguirá centrado en el mes en curso; el acceso a sesiones pasadas debe estar disponible mediante la vista histórica o el detalle correspondiente.

El gestor podrá acceder desde una sesión a las operaciones de administración permitidas: modificar capacidad, habilitar/deshabilitar, reservar para un usuario o cancelar una reserva.

## 9. Reservas

### 9.1. Reserva ordinaria

Un nadador activo puede reservar una sesión si:
- La sesión existe.
- La sesión no ha comenzado.
- La sesión está habilitada.
- Hay plazas disponibles.
- No tiene ya una reserva registrada para esa sesión.
- No ha alcanzado el máximo de 5 reservas registradas en la semana de la sesión.

El servidor debe comprobar todas estas condiciones en el momento de confirmar la reserva, no solo al mostrar el calendario.

Cuando se alcance la capacidad, la sesión se mostrará como completa y se impedirán nuevas reservas.

No se implementará lista de espera en esta versión.

### 9.2. Límite semanal

Cada usuario puede tener un máximo de 5 reservas registradas por semana natural, de lunes a domingo.

El límite se calcula según la fecha de la sesión, no según la fecha en que se creó la reserva. Esto es especialmente importante para las reservas retroactivas que pueda añadir el gestor.

Las reservas canceladas dentro del plazo permitido se eliminan y dejan de contar para el límite.

Las reservas que no se hayan cancelado dentro del plazo permitido siguen contando.

Las reservas creadas por un gestor para otro usuario cuentan para el límite de ese usuario. Las reservas realizadas por un gestor o administrador para sí mismos cuentan para su propio límite.

El sistema debe comprobar el límite de forma segura ante solicitudes simultáneas, evitando que dos reservas concurrentes permitan superar el máximo.

### 9.3. Reserva por parte del gestor

El gestor puede reservar para sí mismo o para otro nadador.

Para las reservas ordinarias de sesiones actuales o futuras se aplican las reglas de disponibilidad, capacidad, sesión habilitada y límite semanal.

El gestor también puede añadir una reserva retroactiva para registrar que un usuario asistió a una sesión pasada aunque no hubiera reservado previamente. Una sesión es pasada en cuanto llega su hora de inicio, también si todavía no ha terminado. Hasta ese instante la reserva es ordinaria. A partir de ese instante solo puede registrarla el gestor o el administrador.

Esta operación:
- Solo puede realizarla el gestor o el administrador.
- Debe permitir seleccionar al usuario y la sesión pasada.
- Se registra como una reserva normal a efectos del historial.
- Debe incluir un indicador de que fue añadida manualmente por un gestor.
- Cuenta para el límite semanal del usuario correspondiente, tomando como referencia la semana de la sesión.
- Respeta la capacidad de la sesión. Si no quedan plazas, no se crea.
- Puede registrarse aunque la sesión esté deshabilitada.
- No debe tratarse como una reserva ordinaria disponible para los nadadores.

Si una reserva retroactiva provocaría que el usuario superase el límite semanal de 5, el sistema debe impedirla salvo que se defina expresamente una excepción administrativa. No se ha definido ninguna excepción en esta especificación. Tampoco hay excepción de aforo.

### 9.4. Reserva duplicada

Un usuario solo puede tener una reserva registrada por sesión.

La restricción debe imponerse también en la base de datos o mediante un mecanismo transaccional equivalente, no únicamente en la interfaz.

## 10. Cancelaciones

### 10.1. Cancelación por el nadador

El nadador puede cancelar su propia reserva siempre que falte al menos una hora para el inicio de la sesión.

- Con 60 minutos o más de antelación: puede cancelar.
- Con menos de 60 minutos: no puede cancelar.

Cuando la cancelación está permitida:
- La reserva se elimina físicamente.
- Se libera la plaza.
- Deja de contar para el límite semanal.
- Desaparece del historial.

Si no puede cancelar por estar fuera de plazo, la reserva permanece registrada, cuenta para el límite semanal y se conserva en el historial.

### 10.2. Cancelación por el gestor o administrador

El gestor y el administrador pueden cancelar cualquier reserva, incluso si faltan menos de 60 minutos para el inicio o la sesión ya ha comenzado.

La cancelación elimina físicamente la reserva, libera la plaza y hace que deje de contar para el límite semanal. No se conserva un registro histórico de cancelaciones en esta versión.

## 11. Historial de reservas

El historial debe mostrar las reservas que siguen registradas y corresponden a sesiones pasadas, así como las reservas actuales o futuras en la sección de reservas activas.

Las reservas canceladas y eliminadas no aparecen en el historial.

No se implementará control de asistencia independiente. La existencia de una reserva registrada para una sesión pasada será el dato que aparecerá en el historial.

Las reservas añadidas retroactivamente por el gestor aparecerán en el historial como reservas normales, con un indicador visual de que fueron añadidas manualmente.

El gestor podrá consultar el historial de cada nadador y las personas que reservaron cada sesión pasada.

## 12. Gestión de nadadores

### 12.1. Alta manual

El gestor podrá crear nadadores desde el panel público de gestión.

Los datos requeridos para el alta serán:
- Nombre.
- Apellidos.
- Email.

El teléfono y el DNI son opcionales, tanto en el alta manual como en la importación por CSV y en la edición de la ficha.

El gestor no asignará una contraseña. Para una cuenta nueva, se utilizará el flujo nativo de WordPress para que el usuario establezca su contraseña mediante el correo correspondiente.

Si el email ya existe en WordPress, se debe comprobar si corresponde a una cuenta existente y reutilizarla cuando proceda, sin crear una cuenta duplicada ni modificar su contraseña.

Los nadadores creados manualmente son independientes del sitio del club, salvo que posteriormente el mismo usuario se incorpore al club y cumpla las condiciones de sincronización. En ese caso, se vinculará a la sincronización conservando reservas e historial.

### 12.2. Desactivación manual

El gestor puede desactivar manualmente a un nadador desde reservas, independientemente de su origen.

Al desactivarlo:
- No podrá acceder al sistema de reservas.
- No podrá crear nuevas reservas.
- Se eliminarán sus reservas futuras.
- Se conservará el historial pasado.
- Se conservarán sus datos.

La desactivación manual no modifica ningún dato del sitio del club.

Si el usuario está sincronizado, la siguiente sincronización del club podrá volver a activarlo o desactivarlo según las condiciones actuales del club.

### 12.3. Reactivación manual

El gestor puede reactivar manualmente a un nadador desactivado.

Al reactivarlo:
- Recupera el acceso al sistema de reservas.
- Puede realizar nuevas reservas si cumple las reglas generales.
- No se restauran las reservas futuras eliminadas durante la desactivación.

La reactivación manual no modifica los datos del club. Si el usuario está sincronizado, una sincronización posterior del club prevalecerá.

### 12.4. Eliminación definitiva desde reservas

El gestor puede eliminar definitivamente un nadador del sistema de reservas.

La eliminación debe:
- Eliminar sus reservas futuras.
- Conservar las reservas pasadas en el historial.
- Conservar en esas entradas históricas únicamente el nombre, apellidos, teléfono y email como datos identificativos necesarios.
- No conservar el DNI en el historial.
- Retirar al usuario del sitio de reservas sin eliminar por ello la cuenta global de WordPress ni su pertenencia al sitio del club.

Si el usuario estaba sincronizado con el club y sigue cumpliendo las condiciones, la siguiente sincronización podrá volver a incorporarlo a reservas. Esta es la conducta esperada y no debe impedirse mediante un bloqueo permanente.

Si se elimina del sitio de reservas un usuario que también pertenece al club, no se debe ejecutar `wp_delete_user()` ni afectar a su acceso al club.

## 13. Datos y modelo de persistencia

Se recomienda utilizar tablas propias para sesiones y reservas, en lugar de Custom Post Types, debido a la necesidad de aplicar restricciones de unicidad, consultas por fecha, control de capacidad y operaciones concurrentes.

El diseño definitivo de tablas debe seguir las convenciones de WordPress, utilizar el prefijo de tablas del sitio correspondiente y contemplar la instalación/actualización mediante `dbDelta()` o un mecanismo compatible.

### 13.1. Tabla de sesiones

Como mínimo, cada sesión debe almacenar:
- Identificador único.
- Fecha de la sesión.
- Hora de inicio.
- Hora de fin.
- Capacidad.
- Estado habilitado/deshabilitado.
- Fecha de creación.
- Fecha de última modificación.

Debe existir una restricción que impida duplicar una sesión con la misma fecha y horario.

### 13.2. Tabla de reservas

Como mínimo, cada reserva debe almacenar:
- Identificador único.
- Identificador de sesión.
- `user_id` de WordPress, nullable para permitir conservar historial tras retirar la cuenta del sitio.
- Nombre y apellidos como snapshot.
- Teléfono como snapshot.
- Email como snapshot.
- Fecha y hora de creación.
- Usuario que creó la reserva, cuando proceda.
- Indicador de reserva añadida manualmente por un gestor.
- Identificador del gestor que añadió la reserva, cuando proceda.

La reserva debe conservar los datos snapshot necesarios para el historial aunque el usuario deje de pertenecer al sitio de reservas o se elimine su cuenta global en una operación administrativa externa.

No es necesario conservar un estado de cancelación en la tabla: las cancelaciones definidas en esta versión eliminan físicamente la reserva.

Debe existir una restricción única o un mecanismo transaccional equivalente que impida más de una reserva por usuario y sesión. El tratamiento de `user_id` nulo debe permitir conservar registros históricos sin crear colisiones.

### 13.3. Registro de meses generados

Debe existir un mecanismo persistente para identificar qué meses se han generado, evitando generar un mismo mes dos veces.

Debe ser seguro ante ejecuciones simultáneas. Puede implementarse mediante una tabla específica o mediante una restricción única equivalente.

### 13.4. Datos personales

Los datos personales necesarios para gestionar al nadador se almacenarán en una tabla propia del sitio de reservas, sin duplicar la cuenta global de WordPress y sin escribir las claves de usuario del club.

La ficha local incluye nombre, apellidos, email, teléfono, DNI, estado activo y si el nadador está vinculado al club.

Los datos conservados en el historial tras retirar o eliminar al usuario se limitarán a nombre, apellidos, teléfono y email, como snapshot en la tabla de reservas. El DNI no se conservará en el historial.

## 14. Fechas, horas y zona horaria

Todas las fechas y horas de sesiones se interpretarán en la zona horaria configurada en WordPress, no en la zona horaria del servidor.

La lógica de:
- mes en curso;
- ventana de generación;
- inicio de sesión;
- plazo de cancelación;
- semana de reservas;

debe utilizar la zona horaria del sitio y las APIs de fecha de WordPress o `DateTimeImmutable` con la zona horaria correspondiente.

La semana de límite comienza el lunes y termina el domingo. Los cambios de horario estacional deben gestionarse utilizando la zona horaria configurada, evitando cálculos basados en offsets fijos.

## 15. Paneles y experiencia de usuario

### 15.1. Panel público del nadador

Debe incluir:
- Calendario del mes en curso.
- Ocupación y plazas libres por sesión.
- Acción para reservar.
- Acción para cancelar cuando se permita.
- Listado de reservas activas.
- Historial de reservas pasadas.
- Datos básicos del perfil, según los datos disponibles.

El nadador no debe poder acceder al `wp-admin`. Las rutas del panel deben requerir autenticación y validar que el usuario está activo en reservas.

### 15.2. Panel público del gestor

Debe incluir:
- Calendario y detalle de sesiones.
- Listado de reservas y asistentes.
- Gestión de nadadores.
- Alta, activación, desactivación y eliminación de nadadores.
- Perfil e historial de cada nadador.
- Reserva para sí mismo o para otros.
- Cancelación de reservas.
- Modificación de capacidad y estado de sesiones.
- Acción para generar el siguiente mes cuando esté dentro de la ventana permitida.

El gestor no debe poder acceder al `wp-admin`. La restricción debe aplicarse también a las URLs y acciones directas, no solo a la navegación visible.

### 15.3. Accesibilidad y responsive

Las interfaces deben ser utilizables desde móvil y escritorio. Los estados de sesión (completa, deshabilitada, reservada por el usuario) deben distinguirse mediante texto y no únicamente mediante color.

Los formularios deben mostrar errores comprensibles y conservar los datos introducidos cuando sea seguro hacerlo.

## 16. Seguridad y validación

Todas las operaciones que modifiquen datos deben:
- Comprobar la autenticación.
- Comprobar capacidades/permisos en el servidor.
- Validar nonces en acciones iniciadas desde formularios o peticiones del navegador.
- Validar y sanear los datos recibidos.
- Escapar los datos al mostrarlos.
- Utilizar consultas preparadas con `$wpdb->prepare()` cuando corresponda.
- Evitar confiar en identificadores, roles o estados enviados por el cliente.
- Evitar exponer datos personales a usuarios que no tengan permiso para consultarlos.

Las operaciones de reserva, cancelación, generación mensual y sincronización deben protegerse frente a concurrencia y solicitudes repetidas.

La integración entre sitios debe validar que se ejecuta desde el sitio esperado y que el usuario que inicia una sincronización tiene permisos de administrador del club.

## 17. Requisitos de implementación

- Seguir las convenciones y estándares de desarrollo de WordPress y PHP compatibles con el proyecto existente.
- Separar lógica de negocio, persistencia, controladores y presentación.
- No introducir dependencias innecesarias.
- Mantener el plugin independiente del tema.
- No modificar datos del sitio del club desde el sitio de reservas.
- No almacenar contraseñas en tablas propias ni incluirlas en logs.
- No eliminar usuarios globales de WordPress como parte de la desactivación o eliminación local en reservas.
- Utilizar capacidades específicas para las acciones del plugin.
- Garantizar que el contexto de `switch_to_blog()` siempre se restaura.
- Implementar la generación mensual y las reservas con protección frente a concurrencia.
- Incluir mecanismos de actualización de esquema y una versión de base de datos del plugin.

## 18. Pruebas y criterios de aceptación

La implementación se considerará funcional cuando se verifiquen, como mínimo, los siguientes casos.

### 18.1. Usuarios y permisos
- Un nadador activo puede acceder al panel público y no puede acceder al `wp-admin`.
- Un nadador desactivado no puede acceder ni reservar.
- Un gestor puede utilizar el panel público de gestión, pero no el `wp-admin`.
- Un administrador puede utilizar el panel de gestión y el `wp-admin`.
- Un usuario puede tener roles distintos en los dos sitios sin que se alteren entre sí.

### 18.2. Sincronización
- Un deportista que cumple las condiciones se crea en reservas con el mismo `user_id`, rol `nadador` y estado activo.
- Un usuario existente en reservas se vincula sin duplicar cuenta ni perder reservas o historial.
- Un cambio en los datos personales del club actualiza los campos correspondientes en reservas.
- Los campos vacíos del club sobrescriben los valores existentes en reservas.
- Si deja de cumplir las condiciones, se desactiva y se eliminan sus reservas futuras.
- Si vuelve a cumplirlas, se reactiva sin recuperar las reservas eliminadas.
- Una desactivación manual en reservas no modifica el club.
- La siguiente sincronización del club prevalece sobre el estado manual.
- La eliminación de la pertenencia al sitio del club desactiva al usuario en reservas.
- El botón manual ejecuta la misma lógica que el guardado del perfil.
- La repetición de una sincronización no duplica usuarios ni reservas.
- Un error se registra y se muestra al administrador cuando corresponda, sin reintentos automáticos.
- El contexto del sitio original se restaura incluso cuando falla la sincronización.

### 18.3. Sesiones
- La generación crea únicamente las sesiones del horario establecido.
- No se generan sesiones en viernes ni domingo.
- La ventana de generación cambia exactamente en las fechas definidas.
- No es posible generar dos veces el mismo mes, incluso con peticiones simultáneas.
- Una sesión deshabilitada conserva sus reservas, pero no admite nuevas reservas ordinarias.
- Reducir la capacidad por debajo de las reservas existentes no elimina reservas y bloquea nuevas altas.

### 18.4. Reservas y cancelaciones
- No se puede reservar una sesión completa, deshabilitada o ya iniciada.
- No se puede duplicar una reserva del mismo usuario para la misma sesión.
- Se respeta el máximo de 5 reservas por semana natural.
- Las reservas para otros usuarios cuentan para el límite del usuario reservado.
- Las reservas retroactivas cuentan en la semana de la sesión.
- El nadador puede cancelar con al menos 60 minutos de antelación.
- El nadador no puede cancelar cuando faltan menos de 60 minutos.
- El gestor puede cancelar independientemente del tiempo restante.
- Una reserva cancelada se elimina, libera la plaza y deja de contar para el límite.

### 18.5. Historial y eliminación
- Las reservas pasadas que siguen registradas aparecen en el historial.
- Las reservas canceladas no aparecen en el historial.
- Las reservas retroactivas muestran el indicador correspondiente.
- Al desactivar un usuario se conservan sus reservas pasadas y se eliminan las futuras.
- Al eliminar un usuario del sitio de reservas se conservan los datos históricos permitidos.
- La eliminación desde reservas no elimina la cuenta global ni afecta a la pertenencia al sitio del club.

## 19. Fuera de alcance de esta versión

Salvo que se acuerde expresamente durante el desarrollo, no forman parte de esta versión:
- Lista de espera.
- Control de asistencia independiente de las reservas.
- Pagos o gestión de cuotas.
- Generación automática de sesiones mediante cron.
- Reintentos automáticos de sincronización.
- Sincronización de cambios desde reservas hacia el club.
- Recuperación de reservas futuras eliminadas por una desactivación.
- Registro histórico de cancelaciones.

## 20. Decisiones aprobadas

- En el club, la condición de sincronización usa el rol `athlete`. Su etiqueta visible es «Deportista». En reservas, el rol del nadador es `nadador`.
- El sitio del club es el blog `1`. El sitio de reservas es el blog `2` (`cnnazari.clubtriatlonsulayr.local.com`).
- La ficha del nadador vive en una tabla del sitio de reservas. No se escriben las claves globales del perfil del club.
- El mes generado se identifica con `generated_month`, en formato `YYYY-MM`.
- El mes siguiente solo se habilita cuando el mes en curso ya está generado. El día 26, si el mes en curso sigue pendiente, se completa ese mes y no el siguiente. A partir del día 27, el mes en curso ya no se crea y el botón ofrece el mes siguiente.
- Los paneles públicos se integran con un shortcode del plugin.
- Una sesión pasa a ser retroactiva en cuanto llega su hora de inicio, aunque no haya terminado. Solo el gestor o el administrador pueden registrarla. Hace falta plaza libre y no se supera el límite semanal. Puede registrarse aunque la sesión esté deshabilitada.

## 21. Pendiente de confirmar

La zona horaria de ambos sitios debe ser la de WordPress, preferiblemente `Europe/Madrid`, y no un desplazamiento UTC fijo. No está fijada en el repositorio.
