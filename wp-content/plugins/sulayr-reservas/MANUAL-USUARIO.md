# Manual de uso — Reservas de natación

Este manual explica cómo reservar plaza y cómo gestionar las sesiones. No hace falta conocer el funcionamiento interno del sitio.

Quien gestiona el calendario y las fichas tiene un manual aparte: `MANUAL-GESTOR.md`.

No incluye capturas. El calendario con una sesión iniciada no se ha podido abrir en el navegador durante la redacción: solo se comprobó la página antes de entrar.

## 1. Introducción

El sistema permite apuntarse a las sesiones de natación y, a quien gestiona, administrar el calendario y las personas inscritas.

Hay dos formas de entrar:

- Como nadador, para consultar el mes, reservar y cancelar.
- Como gestor, para ver todas las reservas, crear sesiones del mes y dar de alta o de baja a nadadores.

Una persona puede ser deportista del club y, además, nadador en reservas. Es la misma cuenta.

## 2. Requisitos de acceso

Hace falta una cuenta y una ficha activa.

- Si alguien del club te ha dado de alta, usa el correo de siempre. La contraseña no cambia.
- Si eres nuevo y te han creado desde reservas, recibirás un correo para elegir la contraseña.
- Si tu ficha está desactivada, verás que no puedes acceder. No podrás reservar hasta que te reactiven.
- Si no has iniciado sesión, la página solo muestra un enlace para entrar. No muestra las sesiones.

## 3. Acceso al panel de reservas

1. Abre la página **Reservas de natación** del sitio de reservas.
2. Pulsa **Iniciar sesión**.
3. Entra con tu cuenta. Al terminar volverás a esa página.

El nadador y el gestor no usan el escritorio de administración de WordPress. Si intentan abrirlo, el sitio los devuelve al panel.

## 4. Panel del nadador

Arriba aparecen tres enlaces:

- **Calendario**
- **Mis reservas**
- **Mi perfil**

Mi perfil muestra tu nombre, apellidos, correo, teléfono, DNI y el límite semanal que te ha puesto el gestor.

Si tu ficha no está vinculada al club, puedes guardar nombre, apellidos y teléfono. Esos cambios se quedan en reservas y no modifican el nombre ni el teléfono de la cuenta del club. El DNI no se edita aquí.

Si tu ficha está vinculada al club, el nombre, los apellidos, el teléfono y el DNI los ves, pero no los cambias. Los actualiza la sincronización del club.

En los dos casos puedes:

- pedir un cambio de correo
- subir o quitar tu fotografía de reservas
- cambiar tu contraseña

El correo nuevo no se aplica al momento. Llega un mensaje a esa dirección con un enlace del panel de reservas, válido 24 horas. Hasta que lo confirmes sigues entrando con el correo anterior. Si estás vinculado al club, la siguiente sincronización puede volver a dejar el correo que tenga el club.

La fotografía es solo de reservas. No sustituye a la foto del club. Admite JPG, PNG o WebP, hasta 5 MB. Se muestra en círculo.

La contraseña la cambias tú. El gestor no puede verla ni poner otra.

No verás los nombres de las demás personas apuntadas.

## 5. Consulta del calendario

El calendario es solo el mes en curso. No se puede pasar al mes anterior ni al siguiente.

Cada sesión muestra:

- el día
- la hora de inicio y la de fin
- si está disponible, completa, deshabilitada, ya ha comenzado o reservada por ti
- cuántas plazas están ocupadas y cuántas quedan

El estado se lee en el texto. No depende solo del color.

## 6. Reserva de una sesión

1. En el calendario, localiza una sesión disponible.
2. Pulsa **Reservar**.
3. La página confirma el resultado.

No podrás reservar si:

- la sesión está completa
- está deshabilitada
- ya ha empezado
- ya tienes plaza en esa sesión
- ya has llegado a tu límite de esa semana
- tu ficha no está activa

La semana va de lunes a domingo, según el día de la sesión. El límite es el de tu ficha, entre 1 y 5. Si nadie lo ha cambiado, es 3.

El gestor puede apuntarte él mismo. En ese caso la reserva también cuenta para tu límite.

## 7. Cancelación de una reserva

Puedes cancelar mientras falten más de 60 minutos para el inicio. En el minuto exacto en que faltan 60 todavía se puede.

1. Entra en **Mis reservas** o en el detalle de la sesión.
2. Pulsa **Cancelar**.

Si falta menos de una hora, el botón no aparece. Si aun así se enviara la cancelación, el sistema la rechaza y la plaza sigue tuya.

Cancelar borra la reserva. La plaza queda libre y deja de contar para el límite de la semana. No se guarda un historial de la cancelación.

El gestor puede cancelar una reserva aunque falte menos de una hora.

## 8. Consulta de reservas e historial

**Mis reservas** separa:

- **Reservas activas:** sesiones que todavía no han empezado.
- **Historial:** sesiones que ya han empezado y siguen registradas.

Una reserva cancelada no aparece. Si un gestor te apuntó a una sesión que ya había empezado, el historial lo indica con el texto «Añadida por un gestor».

## 9. Panel del gestor

Además de Calendario, Mis reservas y Mi perfil, el gestor ve:

- **Nadadores**
- **Alta de nadador**
- **Importar**
- **Meses anteriores**

En el detalle de una sesión ve quién está apuntado, con su correo, y puede reservar o cancelar por ellos.

## 10. Gestión de sesiones

En el detalle de una sesión el gestor puede:

- cambiar la capacidad y pulsar **Guardar capacidad**
- **Deshabilitar sesión** o **Habilitar sesión**
- elegir un nadador activo y pulsar **Reservar**
- **Cancelar reserva** de cualquier persona apuntada

Bajar el número de plazas no borra a quien ya estaba apuntado. Mientras las plazas ocupadas lleguen al nuevo máximo, no se admiten más reservas ordinarias.

Una sesión deshabilitada sigue en el calendario y conserva sus reservas, pero no admite reservas nuevas salvo que el gestor apunte a alguien cuando la sesión ya ha empezado.

El gestor puede registrar una plaza aunque la sesión ya haya empezado, si queda sitio y esa persona no supera el límite de su ficha. Esa reserva se marca como añadida por un gestor.

## 11. Generación del calendario mensual

Las sesiones no aparecen solas. Hay que generarlas.

En el calendario, si corresponde crear un mes, verás un botón **Generar sesiones de…** con el nombre de ese mes. Solo se ofrece un mes cada vez:

- Si el mes en curso todavía no existe, el botón lo ofrece hasta el día 26.
- Desde el día 27, si ese mes sigue sin crearse, ya no se puede crear y el botón pasa al mes siguiente.
- Si el mes en curso ya existe, el mes siguiente se ofrece a partir del día 26.

Si no toca generar ninguno, el calendario dice: «Hoy no corresponde generar un mes.»

Generar otra vez un mes que ya existe no cambia las sesiones, las plazas ni las reservas.

Los meses ya generados se consultan en **Meses anteriores**. El mes siguiente solo se abre cuando ya se ha generado.

Horario de las sesiones:

| Día | Horario |
|---|---|
| Lunes a jueves | 15:00–16:30 y 21:00–22:30 |
| Sábado | 13:00–14:30 |

No hay sesión los viernes ni los domingos. Cada sesión nueva tiene 24 plazas. Ese número se puede cambiar después.

## 12. Gestión de nadadores

En **Nadadores**, arriba del filtro, hay un **Contador** de todas las fichas. No cambia al filtrar ni al buscar. Tiene tres líneas: **Total**, **CN Nazarí** (altas hechas en reservas) y **CT Sulayr** (fichas que vienen del club). Cada una dice cuántos están activos y cuántos inactivos, por ejemplo «3 nadadores activos / 1 nadador inactivo».

El filtro combina **Estado** (**Todos**, **Activos** o **Inactivos**), **Origen** (**Todos**, **CN Nazarí** o **CT Sulayr**) y la búsqueda por nombre o correo. Pulsa **Filtrar**. Solo cambia la tabla.

Cada fila muestra la foto, el nombre, los apellidos, el correo, el teléfono, el estado, el origen (**CN Nazarí** o **CT Sulayr**), el límite semanal y la fecha de alta. El nombre y los apellidos abren la ficha. La tabla no está paginada. En el móvil se desliza hacia la derecha para ver el resto de columnas.

Para ordenar, pulsa el título de la columna. La primera vez ordena de menor a mayor, o de la A a la Z. Pulsar otra vez invierte el orden. Al entrar, la tabla va por apellidos, de la A a la Z. La foto no se ordena. El estado, el origen y la búsqueda se mantienen al cambiar el orden.

**Exportar CSV** descarga las filas que se están viendo, con el mismo filtro, origen, búsqueda y orden.

Al abrir una ficha se ven el correo, el teléfono, el DNI y sus reservas.

Si la ficha no está vinculada al club, el gestor puede guardar nombre, apellidos y teléfono. El DNI no se edita. Si está vinculada, esos datos se ven y no se cambian: los actualiza el club.

El gestor puede:

- elegir el límite semanal, de 1 a 5, y pulsar **Guardar límite**. Bajarlo no cancela las reservas ya hechas. A partir de ese momento no se admiten más reservas de esa semana si ya se ha llegado al nuevo número
- pedir un cambio de correo con el mismo enlace de confirmación de 24 horas. Hasta que se confirme, el correo no cambia
- subir o quitar la fotografía de reservas
- **Exportar reservas CSV** de esa persona

El gestor no cambia la contraseña.

## 13. Alta manual de nadadores

1. Entra en **Alta de nadador**.
2. Rellena nombre, apellidos, correo, teléfono y DNI.
3. Elige el límite semanal. Si lo dejas en 3, ese es el valor de la ficha.
4. Pulsa **Crear nadador**.

Si el correo ya tiene cuenta, se usa esa cuenta y no se cambia la contraseña. Si es un correo nuevo, la persona recibe un mensaje para elegirla.

Estas altas son independientes del club hasta que, más adelante, esa misma persona cumpla las condiciones de natación en el club y alguien sincronice su perfil.

Si falta algún dato o el correo no es válido, el formulario conserva lo escrito y muestra el motivo.

## 14. Activación y desactivación

En la ficha del nadador:

- **Desactivar** impide reservar y borra las reservas de sesiones que aún no han empezado. El historial de sesiones ya empezadas se conserva. La persona sigue pudiendo entrar al club si también es deportista.
- **Reactivar** vuelve a permitir reservas. No recupera las plazas que se borraron al desactivar.

Desactivar no borra la cuenta ni la saca del sitio de reservas.

## 15. Retirada de nadadores

Retirar es distinto de desactivar y no se puede deshacer desde el panel.

En la ficha:

1. Marca **Confirmo la retirada de este nadador del sitio de reservas**.
2. Pulsa **Retirar del sitio**.

Qué ocurre:

- Desaparece la ficha, incluido el DNI.
- Se borran las reservas de sesiones que aún no han empezado.
- El historial de sesiones ya empezadas conserva el nombre y el contacto, sin quedar ligado a la cuenta.
- La persona deja de tener acceso a reservas.
- Su cuenta del club, si la tiene, no se borra.

Si más adelante vuelve a cumplir las condiciones del club, una sincronización puede volver a crearle la ficha. Tendrá que reservar de nuevo.

## 16. Sincronización con el club

La sincronización la hace un administrador del club, no el nadador ni el gestor de reservas.

Una persona queda activa en reservas cuando, a la vez:

- es deportista del club
- tiene la natación activada
- su estado de deportista es activo

Sirve para incorporarla, actualizar nombre, apellidos, correo, teléfono y DNI, o desactivarla si deja de cumplir alguna condición.

Cómo lanzarla:

1. En el club, abre el perfil del deportista.
2. Busca **Reservas de natación**.
3. Pulsa **Sincronizar ahora en reservas**.

También se lanza al guardar el perfil de un deportista, si quien guarda es administrador.

El resultado aparece en un aviso. Si algo falla, se puede volver a pulsar el botón. No se reintenta solo.

Un campo que en el club esté vacío sustituye al valor que hubiera en reservas. La próxima sincronización vuelve a copiar nombre, apellidos, correo, teléfono y DNI del club. No cambia el límite semanal ni la fotografía de reservas.

Si alguien confirma un correo nuevo y después el club sigue teniendo el anterior, la siguiente sincronización deja otra vez el correo del club.

Salir del club o dejar de ser deportista desactiva la ficha de reservas si existía. No borra el historial pasado.

## 17. Consulta del registro técnico

Solo los administradores del club pueden verlo.

En el escritorio del club: **Usuarios → Registro de sincronización**.

Cada línea indica cuándo se sincronizó a alguien, si fue al guardar el perfil o con el botón, y si el resultado fue un alta, una actualización, una activación, una desactivación, ningún cambio o un fallo.

No muestra contraseñas. Se conservan 180 días y, como máximo, las 1000 operaciones más recientes.

## 18. Preguntas frecuentes

**¿Por qué no veo el mes que viene?**
El nadador solo ve el mes en curso. El gestor ve el mes siguiente cuando ya se ha generado.

**¿Por qué no puedo reservar si el calendario muestra plazas?**
Puede que la sesión esté deshabilitada, que ya haya empezado o que ya hayas llegado al límite de tu ficha esa semana.

**¿Por qué ha desaparecido una reserva futura?**
Alguien ha cancelado, te han desactivado o una sincronización del club te ha dejado inactivo. Las sesiones ya empezadas siguen en el historial.

**¿Desactivar y retirar es lo mismo?**
No. Desactivar mantiene la ficha y el acceso se puede recuperar. Retirar saca a la persona del sitio de reservas y borra la ficha.

**He cambiado el teléfono en reservas y ha vuelto el anterior.**
Si la ficha está vinculada al club, el teléfono no se edita en reservas. Si era una ficha manual y después se sincronizó, la siguiente sincronización copia los datos del club, también si allí están vacíos.

**He pedido cambiar el correo y no puedo entrar con el nuevo.**
Hay que abrir el enlace que llegó a la dirección nueva. Caduca a las 24 horas. Hasta entonces sigue valiendo el correo anterior.

## 19. Problemas habituales y cómo actuar

| Lo que ves | Qué hacer |
|---|---|
| «Entra con tu cuenta para ver las reservas» | Inicia sesión. Si no tienes cuenta, pide el alta al gestor o al club |
| «No puedes acceder al sistema de reservas» | Tu ficha está desactivada o no existe. Contacta con el gestor o con un administrador del club |
| «No quedan plazas» | Elige otra sesión o espera una cancelación |
| «Se ha alcanzado el máximo de reservas de la semana» | Cancela alguna de esa semana o espera a la siguiente |
| «Ya no se puede cancelar esta reserva» | Falta menos de una hora. Solo un gestor puede cancelarla |
| «Hoy no corresponde generar un mes» | El calendario del mes que tocaba ya existe, o todavía no ha llegado el día en que se puede crear el siguiente |
| La sincronización del club muestra un error | Vuelve a pulsar **Sincronizar ahora en reservas**. Si se repite, un administrador puede mirar el registro técnico |
| «Se ha alcanzado el máximo de reservas de la semana» justo después de bajar el límite | Las reservas ya hechas siguen. Hay que cancelar alguna o esperar a la semana siguiente |

## 20. Importar nadadores desde un CSV

Solo el gestor y el administrador del sitio de reservas.

1. Entra en **Importar**.
2. Si lo necesitas, pulsa **Descargar plantilla CSV**.
3. Elige el archivo y pulsa **Validar y previsualizar**.
4. Revisa las filas válidas y las que se rechazan.
5. Confirma solo cuando el listado sea el que quieres crear.

Columnas: `nombre`, `apellidos`, `email`, `telefono`, `limite_semanal` y `estado`. Obligatorios: nombre, apellidos y email. El teléfono puede ir vacío. El límite vacío queda en 3; si se indica, tiene que ser de 1 a 5. El estado vacío crea la ficha activa (`1`). Solo se aceptan `0` y `1`.

Una fila incorrecta no impide crear las demás. Un correo que ya exista, en el club o en reservas, no se modifica: esa fila se rechaza y se indica dónde está la cuenta. No se envía el mensaje de contraseña hasta que la fila se crea de verdad, después de confirmar.

La plantilla incluye una fila de ejemplo. Si se confirma tal cual, esa persona se daría de alta. Cámbiala o bórrala antes.

El archivo no puede superar 2 MB.

## 21. Exportar CSV

**Exportar CSV**, en la tabla de nadadores, descarga las fichas que cumplen el estado, el origen y la búsqueda activos, en el mismo orden de la tabla: nombre, apellidos, email, teléfono, estado, fecha de alta y límite semanal. El contador de arriba no entra en el archivo.

**Exportar reservas CSV**, en la ficha, descarga las reservas que siguen registradas de esa persona: fecha y hora de la sesión, el texto «Registrada», la fecha de la reserva y si la hizo el nadador o un gestor. Una cancelación no aparece, porque la reserva se borra.

## 22. Ir a reservas desde el club

En **Mi cuenta → Perfil de deportista**, quien nada con el club y está activo ve **Reservas de piscina**.

Si ya has entrado en el club, ese enlace abre el panel de reservas sin pedir otra vez la contraseña. Es la misma cuenta. Si tu ficha de reservas no está activa, el panel lo dice y no te da de alta solo por pulsar el enlace.

Quien no tiene natación, o no está activo, no ve ese enlace.

## 23. Volver al perfil del club

En el menú de reservas, **Mi perfil en el club** sale si además eres deportista activo del club y tu ficha de reservas está activa. Abre tu perfil, no el de otra persona. No cierra la sesión de reservas.

## 24. Cerrar sesión

**Cerrar sesión** está en el menú del panel, con el resto de apartados.

Al pulsarlo se cierra la sesión del club y la de reservas. La cuenta es la misma en los dos sitios y no se puede dejar abierta en uno y cerrada en el otro.

Si habías llegado desde **Reservas de piscina**, vuelves a la portada del club. Si habías entrado directamente en reservas, vuelves a la página del panel, ya sin sesión.

## 25. Calendario en el móvil

En el móvil y en la tablet el calendario enseña un día. Arriba están los siete días de la semana, de lunes a domingo. El día elegido va resaltado. Al abrir, es el día de hoy en España (Madrid), aunque el teléfono tenga otra zona.

Puedes cambiar de día con las flechas, tocando un día o deslizando el dedo en horizontal sobre las sesiones. Deslizar hacia arriba o abajo sigue siendo el scroll de la página. Si el gesto empieza en **Reservar**, **Cancelar** o **Detalle**, no cambia el día.

El domingo pasa al lunes siguiente y el lunes vuelve al domingo anterior. El viernes y el domingo, si no tienen clase, dicen **No hay sesiones programadas.**

En el ordenador se mantiene el calendario del mes, en columnas. Reservar y cancelar funcionan igual en las dos presentaciones.
