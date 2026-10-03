# SPEC — Mejoras de gestión de nadadores, perfiles, importación/exportación y perfiles Bento Grid

## 1. Contexto

Este documento define una nueva fase de desarrollo del sistema de reservas de natación del Club Triatlón Sulayr.

El proyecto ya dispone de una implementación funcional de:

* Generación de sesiones.
* Reservas y cancelaciones.
* Límite semanal de reservas.
* Gestión de nadadores.
* Gestión de sesiones.
* Sincronización con el sitio del Club Triatlón Sulayr.
* Panel frontend para nadadores, gestores y administradores.
* Calendario mensual responsive.
* Diseño Bento Grid del panel de reservas.

Este documento define únicamente nuevas funcionalidades y mejoras.

El comportamiento existente definido en `SPEC.md` debe mantenerse salvo que este documento indique explícitamente una modificación.

---

# 2. Alcance de la fase

La fase incluirá las siguientes funcionalidades:

1. Límite semanal de reservas personalizado por nadador.
2. Edición del perfil propio del nadador.
3. Fotografía de perfil.
4. Importación masiva de nadadores mediante CSV.
5. Exportación de datos mediante CSV.
6. Filtros en el listado de nadadores.
7. Rediseño Bento Grid de los perfiles del tema `ct-sulayr-theme`.

Todas las funcionalidades forman parte de una única fase de desarrollo, pero deben analizarse, implementarse y probarse individualmente.

---

# 3. Límite semanal personalizado

## 3.1. Campo

Cada nadador tendrá un límite semanal individual de reservas.

El límite será un dato asociado al nadador en el sitio de reservas.

El valor por defecto será:

**3 reservas por semana.**

## 3.2. Valores permitidos

El límite podrá tener únicamente los valores enteros:

* 1
* 2
* 3
* 4
* 5

No deben admitirse valores inferiores a 1 ni superiores a 5.

## 3.3. Permisos

El límite:

* Será visible para el nadador.
* Será editable únicamente por gestores y administradores con las capacidades correspondientes.
* No podrá ser modificado por un nadador.

## 3.4. Gestión

El gestor podrá modificar el límite desde:

* Formulario de creación de nadador.
* Formulario de edición de nadador.

Los administradores deberán conservar la posibilidad de realizar las mismas operaciones.

## 3.5. Aplicación del límite

El límite debe aplicarse a todas las reservas que contabilicen para el nadador, independientemente de quién realice la reserva.

Por tanto:

* Una reserva realizada por el propio nadador cuenta.
* Una reserva realizada por un gestor en nombre del nadador también cuenta.

La regla semanal continúa siendo:

* Semana de lunes a domingo.
* Solo cuentan las reservas activas según las reglas actuales del sistema.

## 3.6. Reducción del límite

Si un nadador ya tiene más reservas activas en una semana que el nuevo límite establecido:

* Las reservas existentes NO se cancelan.
* El sistema conserva las reservas existentes.
* El nadador no podrá realizar nuevas reservas durante esa semana mientras haya alcanzado o superado el nuevo límite.

Ejemplo:

Un nadador tiene 5 reservas activas y su límite se reduce de 5 a 3.

Las 5 reservas permanecen.

El nadador no puede realizar ninguna reserva adicional durante esa semana.

## 3.7. Interfaz

El límite debe mostrarse al nadador en su perfil y en el panel donde actualmente se informa del límite semanal.

La información debe dejar claro el límite individual del usuario.

---

# 4. Edición del perfil del nadador

## 4.1. Acceso

Un usuario con rol `nadador` podrá editar sus propios datos desde su página de perfil frontend.

No debe necesitar acceso a `wp-admin`.

## 4.2. Campos editables por el propio nadador

El nadador podrá modificar:

* Nombre.
* Apellidos.
* Correo electrónico.
* Teléfono.
* Contraseña.
* Fotografía de perfil.

No podrá modificar:

* Límite semanal.
* Estado activo/inactivo.
* Identificadores internos.
* Datos de gestión.
* Cualquier otro dato reservado a gestores o administradores.

## 4.3. Edición por gestor

El gestor podrá modificar los datos personales del nadador:

* Nombre.
* Apellidos.
* Correo electrónico.
* Teléfono.
* Fotografía.
* Límite semanal.
* Estado, según las funcionalidades de gestión existentes.

El gestor NO podrá establecer ni conocer la contraseña actual del nadador.

La gestión de contraseña deberá continuar utilizando mecanismos seguros de WordPress.

## 4.4. Cambio de correo electrónico

Cuando un nadador solicite cambiar su correo electrónico:

1. El nuevo correo debe validarse.
2. No debe sustituirse inmediatamente el correo actual.
3. Debe enviarse un correo de confirmación a la nueva dirección.
4. El cambio solo debe aplicarse después de que el usuario confirme mediante un enlace seguro.
5. El enlace debe ser de un solo uso y tener una caducidad razonable.
6. Mientras no se confirme, el correo actual seguirá siendo el activo.
7. El usuario debe recibir información clara indicando que el cambio está pendiente de confirmación.

Debe utilizarse el mecanismo nativo de WordPress siempre que sea compatible con el flujo frontend del plugin. No debe utilizarse directamente una llamada a `wp_update_user()` que permita cambiar el correo sin confirmación.

El enlace de confirmación debe llevar a una página frontend apropiada y no debe obligar al nadador a entrar en `wp-admin`.

## 4.5. Cambio de contraseña

El perfil debe incluir un formulario específico para cambiar la contraseña.

Debe solicitar:

* Contraseña actual.
* Nueva contraseña.
* Confirmación de nueva contraseña.

Debe:

* Verificar la contraseña actual.
* Validar la nueva contraseña.
* No mostrar nunca la contraseña.
* Utilizar las funciones seguras de WordPress.
* Gestionar correctamente la sesión después del cambio según el comportamiento de WordPress.

---

# 5. Fotografía de perfil

## 5.1. Usuarios

La fotografía podrá ser gestionada:

* Por el propio nadador sobre su perfil.
* Por un gestor sobre la ficha de un nadador.
* Por un administrador cuando tenga las capacidades correspondientes.

## 5.2. Formatos

Se permitirán:

* JPG/JPEG.
* PNG.
* WebP.

El servidor debe realizar también las comprobaciones correspondientes del tipo real del archivo y no confiar únicamente en la extensión proporcionada por el navegador.

## 5.3. Tamaño máximo

El archivo original no podrá superar:

**5 MB.**

## 5.4. Dimensiones

La imagen deberá procesarse para evitar conservar fotografías innecesariamente grandes.

Como referencia:

* máximo aproximado: 1200 × 1200 px;
* conservar proporción;
* utilizar el procesamiento de imágenes de WordPress cuando sea adecuado.

El sistema podrá generar el tamaño utilizado para el avatar a partir de esta imagen.

No debe ser necesario almacenar una fotografía de varios miles de píxeles para mostrar un avatar pequeño.

## 5.5. Presentación

La fotografía se mostrará como avatar circular.

Debe aparecer, como mínimo, en:

* Perfil del nadador.
* Tarjeta de usuario del panel de reservas.
* Listado de nadadores del gestor.
* Ficha del nadador.

## 5.6. Usuarios sin fotografía

Los usuarios que no tengan fotografía deben conservar el mecanismo visual existente:

* avatar con iniciales o equivalente actualmente utilizado.

No debe aparecer un espacio vacío ni romperse el diseño.

## 5.7. Seguridad

La subida debe:

* Requerir usuario autenticado.
* Requerir nonce.
* Comprobar permisos.
* Permitir que un nadador modifique únicamente su propia fotografía.
* Impedir que un nadador modifique la fotografía de otro usuario.
* Validar tamaño y tipo.
* Gestionar correctamente la eliminación/reemplazo de una fotografía anterior.

---

# 6. Importación masiva de nadadores mediante CSV

## 6.1. Acceso

La importación estará disponible únicamente para:

* Gestores.
* Administradores.

Los nadadores no tendrán acceso.

## 6.2. Campos del CSV

El CSV tendrá las siguientes columnas:

* `nombre`
* `apellidos`
* `email`
* `telefono`
* `limite_semanal`
* `estado`

Los campos obligatorios son:

* `nombre`
* `apellidos`
* `email`

Los demás serán opcionales.

## 6.3. Estado

El campo `estado` aceptará:

* `1` = activo.
* `0` = inactivo.

Si el campo está vacío, el usuario se creará como:

**activo (`1`).**

Cualquier otro valor debe producir un error de validación de esa fila.

## 6.4. Límite semanal

Si `limite_semanal` está vacío:

* utilizar el valor por defecto: 3.

Si contiene un valor:

* debe estar entre 1 y 5.

Cualquier otro valor debe producir un error de validación.

## 6.5. Usuarios existentes

Antes de importar, el sistema debe comprobar si el correo electrónico ya existe.

Debe comprobar tanto:

* Sitio del club.
* Sitio de reservas.

Si el correo ya existe en alguno de ellos, esa fila no se importará.

El informe debe indicar claramente:

* Correo.
* Usuario encontrado, cuando sea posible.
* En qué sitio existe:

  * Club.
  * Reservas.
  * Ambos.

No debe sobrescribirse automáticamente ningún usuario existente.

## 6.6. Validación previa

La importación debe utilizar un proceso en dos etapas:

### Paso 1 — Subir y validar

El gestor sube el CSV.

El sistema:

* Comprueba formato.
* Comprueba columnas.
* Valida cada fila.
* Comprueba emails.
* Comprueba duplicados dentro del propio CSV.
* Comprueba usuarios existentes.
* Valida límite semanal.
* Valida estado.

Debe mostrar una vista previa antes de crear usuarios.

La vista previa debe mostrar:

* Filas válidas.
* Filas con errores.
* Motivo de cada error.

### Paso 2 — Confirmar importación

El gestor confirma expresamente la importación.

Solo entonces se crearán los usuarios válidos.

Las filas inválidas no deben bloquear la importación de las filas válidas.

## 6.7. Creación de usuarios

Cada fila válida dará lugar a un nadador en el sitio de reservas.

Debe reutilizarse, siempre que sea posible, la lógica existente de creación de nadadores para evitar duplicar reglas.

Los usuarios nuevos deben recibir el correo de configuración de contraseña utilizado actualmente por la creación individual de nadadores.

La importación no debe conocer ni establecer contraseñas manualmente.

## 6.8. Plantilla CSV

El gestor podrá descargar una plantilla CSV.

La plantilla deberá incluir las columnas esperadas y, preferiblemente, una fila de ejemplo o instrucciones suficientemente claras sobre:

* Campos obligatorios.
* Valores de `estado`.
* Valores permitidos para `limite_semanal`.

---

# 7. Exportación CSV

## 7.1. Permisos

La exportación estará disponible únicamente para:

* Gestores.
* Administradores.

## 7.2. Exportación de nadadores

El gestor podrá descargar un CSV del listado de nadadores.

Debe incluir:

* Nombre.
* Apellidos.
* Email.
* Teléfono.
* Estado actual.
* Fecha de alta.
* Límite semanal.

La exportación debe respetar los filtros actualmente aplicados en el listado.

Por tanto:

* Si se muestran todos, exporta todos.
* Si se filtran activos, exporta activos.
* Si se filtran inactivos, exporta inactivos.
* Si existe búsqueda por nombre o email, también debe respetarse.

## 7.3. Exportación de reservas

Desde la ficha de un nadador, el gestor podrá descargar sus reservas en CSV.

El archivo debe incluir:

* Fecha de la sesión.
* Hora de inicio.
* Estado de la reserva.
* Fecha/hora en la que se realizó la reserva.
* Indicación de si la reserva fue realizada por:

  * El propio nadador.
  * Un gestor.

Debe utilizarse la información realmente disponible en el modelo de datos existente.

No deben inventarse estados que no estén soportados por el sistema.

Las cancelaciones propias que hayan sido eliminadas de acuerdo con las reglas actuales no podrán aparecer en esta exportación.

---

# 8. Filtros del listado de nadadores

El listado de nadadores deberá permitir:

* Filtrar por estado.
* Buscar por nombre.
* Buscar por email.

El filtro de estado tendrá:

* Todos.
* Activos.
* Inactivos.

Los filtros podrán combinarse.

Ejemplo:

Buscar `garcia` + estado `activo`.

## 8.1. Estado inicial

Al abrir el listado:

**Todos los nadadores**.

No debe aplicarse inicialmente el filtro de activos.

## 8.2. Interfaz

La interfaz debe integrarse visualmente con el diseño Bento Grid existente.

Debe conservarse la paginación o mecanismo equivalente existente si ya existe.

---

# 9. Rediseño de perfiles en ct-sulayr-theme

Esta parte afecta al tema:

`ct-sulayr-theme`

No afecta directamente al plugin de reservas.

## 9.1. Perfiles afectados

Se rediseñarán:

1. Perfil que ve el propio deportista.
2. Perfil público del deportista.
3. Página de edición del propio perfil del deportista.

## 9.2. Funcionalidad

Debe conservarse exactamente la funcionalidad actual.

No se deben eliminar, renombrar ni cambiar campos o acciones funcionales.

El cambio debe centrarse en:

* Presentación.
* Distribución.
* Jerarquía visual.
* Responsive.
* Experiencia de usuario.

Antes de modificar nada, debe analizarse completamente cómo funcionan actualmente estas tres vistas.

## 9.3. Estilo

El diseño debe inspirarse en el Bento Grid utilizado actualmente en el sistema de reservas.

Debe compartir:

* Filosofía de tarjetas.
* Espaciado.
* Bordes.
* Jerarquía.
* Tratamiento de avatares.
* Organización de información.

Pero debe adaptarse a:

* Colores.
* Tipografía.
* Componentes.
* Identidad visual.

del `ct-sulayr-theme`.

No se debe copiar ciegamente el CSS del plugin de reservas.

## 9.4. Responsive

Debe funcionar correctamente en:

* Escritorio.
* Tablet.
* Móvil.

Debe comprobarse específicamente una anchura aproximada de 390 px.

---

# 10. Compatibilidad con funcionalidades existentes

Esta fase no debe romper:

* Reservas.
* Cancelaciones.
* Límite semanal.
* Generación de sesiones.
* Gestión de sesiones.
* Gestión de nadadores.
* Sincronización club → reservas.
* Control de permisos.
* Restricción de `wp-admin`.
* Calendario.
* Panel responsive.
* Historial.
* Retirada de nadadores.

El nuevo límite personalizado sustituye únicamente al límite global actual de 5 como regla de cada nadador.

---

# 11. Seguridad

Todas las acciones frontend deben conservar:

* Nonces.
* Comprobación de autenticación.
* Comprobación de capacidades.
* Validación.
* Saneamiento.
* Escape de salida.

Especial atención a:

* Subida de imágenes.
* Importación CSV.
* Exportación CSV.
* Cambio de email.
* Cambio de contraseña.
* Edición de usuarios.

Un nadador nunca podrá modificar datos de otro nadador manipulando IDs en formularios o peticiones.

---

# 12. Documentación

Durante la implementación deberán actualizarse:

* `DEVELOPMENT.md`
* `ACEPTACION.md`
* `FASES.md`

No modificar `SPEC.md` para introducir estos nuevos requisitos.

`SPEC-MEJORAS.md` será la especificación de esta fase.

`DEVELOPMENT.md` debe recoger las decisiones técnicas que se adopten durante la implementación.

`ACEPTACION.md` debe incluir criterios verificables para cada funcionalidad.

`FASES.md` debe reflejar el estado de la nueva fase.

---

# 13. Pruebas obligatorias

Cada bloque funcional debe probarse individualmente.

## Límite semanal

Probar:

* Valor por defecto 3.
* Valores 1–5.
* Rechazo de valores fuera del rango.
* Nadador puede visualizar el límite.
* Nadador no puede modificarlo.
* Gestor puede modificarlo.
* Reserva propia cuenta.
* Reserva realizada por gestor cuenta.
* Reducir el límite no elimina reservas existentes.
* El nuevo límite impide nuevas reservas cuando ya se ha alcanzado.

## Perfil

Probar:

* Edición de nombre.
* Edición de apellidos.
* Edición de teléfono.
* Cambio de email.
* Confirmación de email.
* Cambio de contraseña.
* Rechazo de contraseña actual incorrecta.
* Gestor puede editar datos.
* Gestor no puede modificar contraseña.
* Nadador no puede modificar otro usuario.

## Foto

Probar:

* JPG.
* PNG.
* WebP.
* Archivo superior a 5 MB.
* Archivo inválido.
* Foto propia.
* Sustitución de foto.
* Usuario sin foto.
* Visualización en todas las ubicaciones definidas.
* Comportamiento responsive.

## Importación

Probar:

* CSV válido.
* Campos obligatorios ausentes.
* Email inválido.
* Duplicados dentro del CSV.
* Email existente en club.
* Email existente en reservas.
* Email existente en ambos.
* Estado 1.
* Estado 0.
* Estado vacío.
* Estado inválido.
* Límite válido.
* Límite vacío.
* Límite inválido.
* Vista previa.
* Confirmación.
* Importación parcial con errores.
* Creación de usuarios.
* Envío del correo de configuración.
* Plantilla CSV.

## Exportación

Probar:

* Exportación de todos los nadadores.
* Exportación solo de activos.
* Exportación solo de inactivos.
* Búsqueda por nombre.
* Búsqueda por email.
* Combinación de filtros.
* Exportación de reservas de un nadador.
* Contenido correcto de las columnas.
* Distinción entre reserva realizada por nadador y gestor.
* Permisos de acceso.

## Perfiles ct-sulayr-theme

Probar:

* Perfil propio.
* Perfil público.
* Edición propia.
* Escritorio.
* Tablet.
* Móvil.
* Conservación de todos los campos y funcionalidades existentes.

---

# 14. Pruebas automatizadas

No existe actualmente una suite PHPUnit completa en el proyecto.

Esta fase no debe bloquearse obligatoriamente por la creación completa de PHPUnit si no forma parte del alcance autorizado, pero las nuevas funcionalidades de lógica de negocio deben implementarse de forma que puedan probarse posteriormente.

Debe prestarse especial atención a pruebas futuras de:

* Límite semanal personalizado.
* Concurrencia de reservas.
* Importación.
* Validación de CSV.
* Permisos.

No introducir una suite de PHPUnit completa como trabajo paralelo no solicitado; documentar únicamente los puntos que deberían cubrirse posteriormente.

---

# 15. Criterio general de finalización

La fase se considerará terminada cuando:

1. Todas las funcionalidades definidas estén implementadas.
2. Cada bloque haya sido probado.
3. Las funcionalidades existentes sigan funcionando.
4. No existan errores PHP o JavaScript relacionados con los cambios.
5. Se haya comprobado el comportamiento responsive.
6. La documentación esté actualizada.
7. `ACEPTACION.md` refleje los resultados reales.
8. `FASES.md` indique correctamente el estado de la fase.
9. No existan cambios funcionales no documentados.
10. No se haya creado ningún commit sin autorización explícita.
