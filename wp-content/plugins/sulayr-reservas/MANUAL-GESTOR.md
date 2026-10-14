# Manual del gestor — Reservas de natación

Este manual es para quien entra al panel con el rol de gestor. Explica cómo preparar el calendario, apuntar y dar de baja a nadadores, y mantener sus fichas.

El nadador tiene su propio manual: `MANUAL-USUARIO.md`. Aquí solo se resume lo que el gestor hace consigo mismo.

No incluye capturas. Los nombres en negrita son los textos de los botones y los apartados del menú.

## 1. Qué puedes hacer

Desde la página **Reservas de natación**:

- Ver el mes en curso, el mes siguiente cuando ya está generado y los meses anteriores.
- Generar las sesiones de un mes, cuando toca.
- En cada sesión: ver quién está apuntado, cambiar las plazas, deshabilitarla o habilitarla, reservar por otra persona y cancelar cualquier reserva.
- Listar nadadores, filtrarlos, ordenarlos y exportarlos.
- Dar de alta a una persona, de una en una o con un CSV.
- Cambiar el límite semanal, pedir un cambio de correo y poner o quitar la fotografía de reservas.
- Desactivar, reactivar o retirar a un nadador.

También puedes reservar y cancelar tus propias plazas, si tienes ficha de nadador activa. Esas reservas cuentan para tu límite, igual que las de cualquier otra persona.

El panel lateral es siempre el tuyo: tu nombre, tu próxima sesión y tu resumen. Si abres la ficha de otra persona, ese lateral sigue mostrando tus datos.

## 2. Qué queda fuera de tu panel

La sincronización con el club la hace un administrador del club, desde el perfil del deportista. Tú puedes activar o desactivar a alguien en reservas; la siguiente sincronización deja el estado que tenga el club en ese momento.

El registro técnico de esas sincronizaciones está en el escritorio del club, en **Usuarios → Registro de sincronización**. Solo lo ve un administrador del club.

Tú no cambias la contraseña de otra persona. Cada cual la cambia en **Mi perfil**.

El DNI se ve en la ficha y no se edita en reservas. En una ficha vinculada al club, el nombre, los apellidos y el teléfono tampoco se editan aquí: los copia el club.

El escritorio de administración de WordPress no es tu sitio de trabajo. Si lo abres, el sitio te devuelve al panel.

## 3. Entrar y salir

1. Abre la página **Reservas de natación**.
2. Pulsa **Iniciar sesión**.
3. Entra con tu cuenta. Al terminar vuelves a esa página.

En el menú aparecen **Calendario**, **Mis reservas**, **Mi perfil**, **Nadadores**, **Alta de nadador**, **Importar** y **Meses anteriores**.

Si además eres deportista activo del club y tu ficha de reservas está activa, verás **Mi perfil en el club**. Abre tu perfil del club y no cierra la sesión de reservas.

**Cerrar sesión** cierra la sesión del club y la de reservas a la vez. Si habías llegado desde **Reservas de piscina**, vuelves a la portada del club. Si habías entrado directamente en reservas, vuelves a la página del panel, ya sin sesión.

Una ficha de nadador desactivada impide reservar a esa persona. La tuya, si estuviera desactivada, no te quita el menú de gestión.

## 4. Tu perfil

**Mi perfil** muestra tu nombre, apellidos, correo, teléfono, DNI y límite semanal.

Si tu ficha no está vinculada al club, puedes guardar nombre, apellidos y teléfono. Esos cambios se quedan en reservas. El DNI no se edita.

Si está vinculada, esos datos los actualiza el club. Tú puedes pedir otro correo, cambiar la fotografía de reservas y cambiar tu contraseña.

Si tu cuenta de gestor no tiene ficha de nadador, **Mi perfil** solo ofrece el cambio de contraseña.

El correo nuevo no se aplica al momento. Llega un mensaje a esa dirección con un enlace del panel, válido 24 horas. Hasta que se confirme sigues entrando con el correo anterior. Si la ficha está vinculada al club, la siguiente sincronización puede volver a dejar el correo del club.

La fotografía admite JPG, PNG o WebP, hasta 5 MB. Es solo de reservas.

## 5. El calendario

Al entrar ves el mes en curso. Cada sesión muestra la hora, el estado en texto y las plazas ocupadas y libres.

El estado puede ser disponible, completa, deshabilitada, ya ha comenzado o reservada por ti. El texto lo dice; el color lo acompaña.

En el ordenador el mes se ve en columnas. En el móvil y en la tablet se ve un día: los siete días de la semana arriba, el día elegido resaltado, y al abrir el día de hoy en España (Madrid). Puedes cambiar de día con las flechas, tocando un día o deslizando en horizontal sobre las sesiones. El viernes y el domingo, si no tienen clase, dicen **No hay sesiones programadas.**

En cada sesión, **Detalle** abre la ficha de esa sesión. Ahí está la gestión. En el calendario no hay un botón **Reservar** para apuntar a otra persona.

Si ya tienes plaza en esa sesión, el calendario muestra **Cancelar mi reserva**. Puedes usarlo aunque falte menos de una hora o la sesión ya haya empezado.

### Mes siguiente y meses anteriores

Cuando el mes siguiente ya está generado, el calendario ofrece **Ver** seguido del nombre de ese mes, y **Volver al mes en curso** para regresar.

Los meses ya pasados que tienen sesiones están en **Meses anteriores**. Cada nombre abre ese calendario. El mes siguiente solo se abre cuando ya se ha generado.

## 6. Generar las sesiones del mes

Las sesiones no aparecen solas. En el calendario, cuando corresponde crear un mes, verás **Generar sesiones de…** con el nombre de ese mes. Solo se ofrece un mes cada vez:

- Si el mes en curso todavía no existe, el botón lo ofrece hasta el día 26.
- Desde el día 27, si ese mes sigue sin crearse, ya no se puede crear y el botón pasa al mes siguiente.
- Si el mes en curso ya existe, el mes siguiente se ofrece a partir del día 26.

Si no toca generar ninguno, el calendario dice: «Hoy no corresponde generar un mes.»

Generar otra vez un mes que ya existe no cambia las sesiones, las plazas ni las reservas.

Horario de las sesiones nuevas:

| Día | Horario |
|---|---|
| Lunes a jueves | 15:00–16:30 y 21:00–22:30 |
| Sábado | 13:00–14:30 |

No hay sesión los viernes ni los domingos. Cada sesión nueva tiene 24 plazas. Ese número se cambia después, sesión por sesión.

## 7. Gestionar una sesión

En **Detalle**:

1. Revisa el estado y las plazas.
2. Para cambiar el aforo, escribe la capacidad y pulsa **Guardar capacidad**.
3. Pulsa **Deshabilitar sesión** o **Habilitar sesión**.
4. En **Reservar para**, elige un nadador activo y pulsa **Reservar**.
5. En la lista **Reservas**, pulsa **Cancelar reserva** junto a quien corresponda.

La lista muestra nombre, apellidos y correo. Si la plaza se registró después de que la sesión hubiera empezado, aparece el texto «Añadida por un gestor».

Bajar el número de plazas no borra a quien ya estaba apuntado. Mientras las plazas ocupadas lleguen al nuevo máximo, no se admiten más reservas.

Una sesión deshabilitada sigue en el calendario y conserva sus reservas. Antes de la hora de inicio no admite reservas nuevas. Cuando ya ha empezado, sí puedes registrar una plaza si queda sitio y esa persona no supera el límite de su ficha. Esa reserva queda marcada como añadida por un gestor.

La reserva que haces tú para otra persona, si la sesión todavía no ha empezado, no lleva ese texto. En el CSV de sus reservas sí figura como hecha por un gestor.

El desplegable **Reservar para** solo incluye fichas activas. Para apuntarte a ti, elige tu nombre en ese desplegable. Hace falta que tu ficha esté activa, que quede plaza y que no hayas llegado a tu límite de esa semana.

La semana va de lunes a domingo, según el día de la sesión. El límite es el de la ficha de quien se apunta, entre 1 y 5.

Cancelar borra la reserva. La plaza queda libre y deja de contar para el límite de esa semana. No se guarda un historial de la cancelación. Puedes cancelar aunque falte menos de una hora o la sesión ya haya empezado. El nadador, en ese plazo, ya no puede hacerlo solo.

## 8. Listado de nadadores

**Nadadores** muestra una tabla: foto, nombre, apellidos, correo, teléfono, si está activa o desactivada, si viene del club o se dio de alta a mano, el límite semanal y la fecha de alta. El nombre y los apellidos abren la ficha.

Debajo del filtro aparece el total de ese estado: por ejemplo, «Total: 4 nadadores», «Total: 4 nadadores activos» o «Total: 0 nadadores inactivos». Buscar por nombre o correo no cambia ese total. Solo reduce las filas.

Puedes filtrar por **Todos**, **Activos** o **Inactivos**, y buscar por nombre o correo. Pulsa **Filtrar**. Los dos criterios se aplican a la vez. La tabla no está paginada.

Para ordenar, pulsa el título de la columna. La primera vez ordena de menor a mayor, o de la A a la Z. Pulsar otra vez invierte el orden. Al entrar, la tabla va por apellidos, de la A a la Z. La foto no se ordena. El filtro y la búsqueda se mantienen al cambiar el orden.

**Exportar CSV** descarga esas mismas filas, en el orden que se está viendo: nombre, apellidos, email, teléfono, estado, fecha de alta y límite semanal.

## 9. Ficha de un nadador

Al abrirla ves el correo, el teléfono, el DNI, el límite y sus reservas, separadas en **Reservas activas** e **Historial**.

En las reservas activas puedes pulsar **Cancelar**. En el historial, una plaza registrada después del inicio lleva el texto «Añadida por un gestor». Una reserva cancelada no aparece.

Si la ficha no está vinculada al club, puedes guardar nombre, apellidos y teléfono con **Guardar datos**. El DNI no tiene campo.

Si está vinculada, la ficha lo dice: «El nombre, los apellidos, el teléfono y el DNI los gestiona el club.»

En los dos casos puedes:

- Elegir el límite semanal, de 1 a 5, y pulsar **Guardar límite**. Bajarlo no cancela las reservas ya hechas. A partir de ese momento no se admiten más reservas de esa semana si ya se ha llegado al nuevo número.
- Pedir un cambio de correo con **Solicitar cambio de correo**. El enlace de confirmación dura 24 horas y llega a la dirección nueva. Hasta que se confirme, el correo no cambia. Si hay uno pendiente, la ficha lo indica.
- Subir una fotografía con **Guardar fotografía** o quitarla con **Quitar fotografía**.
- Pulsar **Exportar reservas CSV**.

Ese CSV trae las reservas que siguen registradas: fecha y hora de la sesión, el texto «Registrada», la fecha de la reserva y si la hizo el nadador o un gestor. Una cancelación no aparece, porque la reserva se borra.

## 10. Alta de un nadador

1. Entra en **Alta de nadador**.
2. Rellena nombre, apellidos, correo, teléfono y DNI. Los cinco son obligatorios.
3. Elige el límite semanal. Si lo dejas en 3, ese es el valor de la ficha.
4. Pulsa **Crear nadador**.

Si el correo ya tiene cuenta, se usa esa cuenta y no se cambia la contraseña. Si es un correo nuevo, la persona recibe un mensaje para elegirla. El nombre de usuario lo crea el sistema.

Estas altas son independientes del club hasta que, más adelante, esa misma persona cumpla las condiciones de natación en el club y un administrador sincronice su perfil.

Si falta algún dato o el correo no es válido, el formulario conserva lo escrito y muestra el motivo. Un correo que ya tiene ficha de nadador no se vuelve a crear.

## 11. Importar un CSV

1. Entra en **Importar**.
2. Si lo necesitas, pulsa **Descargar plantilla CSV**.
3. Elige el archivo y pulsa **Validar y previsualizar**.
4. Revisa las filas válidas y las que se rechazan.
5. Pulsa **Confirmar importación de las filas válidas** cuando el listado sea el que quieres crear.

Columnas: `nombre`, `apellidos`, `email`, `telefono`, `limite_semanal` y `estado`. Obligatorios: nombre, apellidos y email. El teléfono puede ir vacío. El límite vacío queda en 3; si se indica, tiene que ser de 1 a 5. El estado vacío crea la ficha activa (`1`). Solo se aceptan `0` y `1`. El CSV no lleva DNI: la ficha queda sin él.

El archivo usa punto y coma (;) como separador, igual que la plantilla y la exportación; también se aceptan archivos antiguos separados por comas. No puede superar 2 MB. La vista previa se conserva unos 15 minutos. Una fila incorrecta no impide crear las demás. Un correo que ya exista no se modifica: esa fila se rechaza y se indica dónde está la cuenta. El mensaje para elegir contraseña se envía cuando la fila se crea de verdad, después de confirmar.

La plantilla incluye una fila de ejemplo (Ana García López). Si se confirma tal cual, esa persona se daría de alta. Cámbiala o bórrala antes.

## 12. Desactivar, reactivar y retirar

En la ficha del nadador:

- **Desactivar** impide reservar y borra las reservas de sesiones que aún no han empezado. El historial de sesiones ya empezadas se conserva. La persona sigue pudiendo entrar al club si también es deportista. La ficha sigue en **Nadadores**, como desactivada.
- **Reactivar** vuelve a permitir reservas. No recupera las plazas que se borraron al desactivar.

Desactivar no borra la cuenta.

Retirar es otra operación y no se puede deshacer desde el panel.

1. Marca **Confirmo la retirada de este nadador del sitio de reservas**.
2. Pulsa **Retirar del sitio**.

Qué ocurre:

- Desaparece la ficha, incluido el DNI.
- Se borran las reservas de sesiones que aún no han empezado.
- El historial de sesiones ya empezadas conserva el nombre y el contacto, sin quedar ligado a la cuenta.
- La persona deja de tener acceso a reservas.
- Su cuenta del club, si la tiene, no se borra.

Si más adelante vuelve a cumplir las condiciones del club, una sincronización puede volver a crearle la ficha. Tendrá que reservar de nuevo.

## 13. Qué puede cambiar el club después

Cuando un administrador del club sincroniza a un deportista, reservas copia nombre, apellidos, correo, teléfono y DNI. Un campo vacío en el club sustituye al valor que hubiera en reservas. El límite semanal y la fotografía de reservas se conservan.

La persona queda activa en reservas cuando, a la vez, es deportista del club, tiene la natación activada y su estado de deportista es activo. Si deja de cumplir alguna condición, la sincronización desactiva la ficha y borra las reservas de sesiones que aún no han empezado. El historial pasado se conserva.

Por eso una desactivación o un cambio de correo que hagas tú puede quedar deshecho en la siguiente sincronización, si el club sigue enviando otros datos. El aviso de la sincronización lo ve quien pulsa el botón en el club.

## 14. Preguntas frecuentes

**¿Por qué no aparece el botón de generar?**
El mes que tocaba ya existe, o todavía no ha llegado el día en que se puede crear el siguiente. El calendario lo dice con «Hoy no corresponde generar un mes.»

**He generado el mes y quiero cambiar una hora.**
El horario sale fijo al generar. Después puedes cambiar las plazas y deshabilitar una sesión concreta. No hay una pantalla para mover la hora.

**¿Por qué no puedo apuntar a alguien si quedan plazas?**
La sesión puede estar deshabilitada y aún no haber empezado, la persona puede estar inactiva, ya tener plaza en esa sesión o haber llegado a su límite de esa semana. Si la sesión ya empezó, una deshabilitada sí admite el alta del gestor, con las mismas condiciones de plaza y de límite.

**He bajado el límite y el sistema sigue diciendo que esa semana está llena.**
Las reservas ya hechas siguen. Hay que cancelar alguna de esa semana o esperar a la siguiente.

**He cambiado el teléfono de una ficha del club y ha vuelto el anterior.**
En una ficha vinculada, el teléfono lo copia el club en la siguiente sincronización.

**He pedido cambiar el correo y la persona no puede entrar con el nuevo.**
Tiene que abrir el enlace que llegó a la dirección nueva. Caduca a las 24 horas. Hasta entonces sigue valiendo el correo anterior.

**¿Desactivar y retirar es lo mismo?**
Desactivar mantiene la ficha y se puede volver a activar. Retirar saca a la persona del sitio de reservas y borra la ficha.

**¿Por qué el total de nadadores no baja al buscar?**
El total cuenta el estado elegido (todos, activos o inactivos). La búsqueda solo acorta la tabla.

## 15. Mensajes habituales

| Lo que ves | Qué hacer |
|---|---|
| «Entra con tu cuenta para ver las reservas» | Inicia sesión |
| «Hoy no corresponde generar un mes» | El calendario del mes que tocaba ya existe, o todavía no ha llegado el día del siguiente |
| «No quedan plazas» | Sube la capacidad, cancela una reserva o elige otra sesión |
| «La sesión no admite reservas» | Está deshabilitada y aún no ha empezado. Habilítala o espera a registrarla cuando haya comenzado |
| «Se ha alcanzado el máximo de reservas de la semana» | Cancela alguna reserva de esa semana para esa persona, sube su límite o espera a la semana siguiente |
| «El usuario no está activo en reservas» | Reactiva la ficha antes de reservar |
| «Ya existe una reserva para esta sesión» | Esa persona ya tiene plaza |
| «Ya hay un nadador con ese correo» | Abre su ficha en lugar de crear otra |
| «El CSV supera el tamaño máximo de 2 MB» | Parte el archivo o quita filas y vuelve a validar |
| «Revisa los datos del nadador» | Completa nombre, apellidos, correo, teléfono y DNI en el alta manual |
