# Manual del administrador — Documentos

Este manual es para quien publica documentos para los deportistas desde el escritorio del club. Explica cómo subir un documento, decidir quién lo ve, ordenarlo, cambiarlo y retirarlo.

No incluye capturas. Los nombres en negrita son los textos de los botones y los apartados del menú.

La especificación técnica completa está en `docs/club/SPEC-DOCUMENTOS.md`.

## 1. Qué es

Cada documento tiene un título, una descripción corta y un archivo (PDF, JPG o PNG, de 10 MB como máximo). Los deportistas en activo los ven en **Mi cuenta → Documentos** y los abren en una pestaña nueva.

Un documento puede ser para todos los deportistas o solo para algunos. Los que son para alguien en concreto le aparecen arriba, en el grupo **Para ti**. El resto se agrupa por categorías.

Los archivos no tienen una dirección pública. Solo se pueden abrir desde **Mi cuenta → Documentos**, y cada vez se comprueba quién los pide. Si alguien copia el enlace y lo pasa a otra persona, a esa persona no se le abre.

## 2. Quién puede hacer qué

- **Publicar y gestionar documentos y categorías**: solo los administradores del club.
- **Ver documentos**: los deportistas en estado activo. Un estado vacío cuenta como activo.
- Un deportista en **standby**, **baja** o **moroso** no ve la sección ni abre ningún documento, **tampoco los que van dirigidos a él**. Si tienes que hacer llegar un documento a alguien de baja, usa otro canal (por ejemplo, el correo).
- Los clientes de la tienda que no son deportistas no ven la sección.

## 3. Publicar un documento

1. **Documentos → Añadir documento**.
2. Escribe el **título**. Es lo que el deportista ve en grande. Sin título, el documento no se publica: se guarda como borrador y aparece un aviso.
3. Escribe la **Descripción**: una o dos frases que expliquen qué es (300 caracteres como máximo).
4. En **Archivo**, pulsa **Añadir archivo** y **sube el archivo desde tu ordenador**. No elijas un archivo que ya estuviera en la biblioteca de medios: el documento no se guardará y verás un aviso. Los archivos subidos aquí van a una carpeta protegida.
5. Elige una **Categoría** (opcional). Ver el apartado 5.
6. Elige **Visible para** (ver el apartado 4).
7. Si quieres, cambia el **Orden** en la caja **Orden** de la derecha. Ver el apartado 6.
8. Pulsa **Publicar**.

En lugar de publicar, puedes:

- **Guardar borrador**: no lo ve nadie.
- **Programar**: en **Publicar → Publicar inmediatamente → Editar**, pon una fecha futura y pulsa **Programar**. Aparece solo a esa hora.

## 4. Visible para

- **Todos los deportistas activos**: lo ve cualquier deportista en activo.
- **Deportistas concretos**: aparece el campo **Deportistas**. Escribe el nombre y elige uno o varios. Solo ellos lo verán, y solo mientras estén activos. Hay que elegir al menos uno.

**Si el documento tiene datos personales** (una autorización, un certificado, datos de un deportista), elige siempre **Deportistas concretos**. El sistema no puede saber si un documento es personal: si lo publicas para «Todos», lo verán todos.

Si quitas a alguien de la lista, deja de verlo en ese momento. Si se borra un usuario, sale solo de la lista.

## 5. Categorías

Las categorías se gestionan en **Documentos → Categorías**: crear, cambiar el nombre y borrar. Desde la pantalla del documento solo se elige entre las que ya existen; para una nueva, créala antes ahí.

Cada documento tiene una categoría como mucho. En **Mi cuenta → Documentos**:

- Arriba, **Para ti**, con los documentos para deportistas concretos, sea cual sea su categoría.
- Después, un grupo por categoría, por orden alfabético.
- Al final, **Otros documentos**, con los que no tienen categoría.
- Si ningún documento general tiene categoría, la lista sale sin títulos de grupo (o con el título **Documentos del club** si también hay **Para ti**).

Una categoría sin documentos visibles no aparece. Si borras una categoría, sus documentos pasan a **Otros documentos**. Las subcategorías se pueden crear, pero en la sección se muestran como un grupo más, al mismo nivel.

## 6. Orden

Dentro de cada grupo, los documentos van por el campo **Orden**: el número más bajo, más arriba. Admite negativos. Con el mismo número, el más reciente va primero.

El orden también se cambia desde el listado con **Edición rápida**.

## 7. Cambiar el archivo

1. Abre el documento.
2. En **Archivo**, pasa el ratón por encima del archivo actual y pulsa la **×** para quitarlo.
3. Pulsa **Añadir archivo** y sube el nuevo.
4. Pulsa **Actualizar**.

Al guardar, **el archivo anterior se borra** del servidor (no se guardan versiones antiguas). El deportista verá «Actualizado el …» con la fecha del cambio.

Si subes varios archivos de prueba antes de quedarte con uno, los que no uses también se borran al guardar.

## 8. Retirar o borrar

- **Ocultarlo un tiempo**: cámbialo a **Borrador** (en **Publicar → Estado**, o con **Edición rápida**). Deja de verse al momento.
- **Retirarlo**: **Papelera**. Deja de verse y el archivo se conserva por si lo restauras.
- **Borrarlo del todo**: en **Papelera**, **Borrar permanentemente** (o **Vaciar papelera**). Se borra también el archivo.

No borres los archivos de los documentos desde **Medios**: el documento se queda sin archivo y deja de verse. Si ocurre, aparece como **Sin archivo** en el listado; sube el archivo otra vez.

## 9. El listado

**Documentos → Todos los documentos** muestra:

| Columna | Qué indica |
|---|---|
| **Categorías** | La categoría del documento. Al pulsarla, filtra por ella. |
| **Visible para** | **Todos**, el nombre del deportista o «N deportistas» (pasa el ratón por encima para ver los nombres). |
| **Archivo** | Tipo y tamaño, por ejemplo «PDF · 1,2 MB». |
| **Orden** | El número de orden. Se puede ordenar por esta columna. |
| **Fecha** | Publicación, programación o última modificación. |

Los avisos en rojo señalan documentos que **no ve nadie**:

- **Sin archivo**: falta el archivo o se ha borrado.
- **Sin destinatarios**: es para deportistas concretos y la lista está vacía.
- **Sin definir**: no tiene elegido **Visible para**.

Arriba hay dos filtros: **Todas las categorías** y **Cualquier visibilidad** (para ver solo los documentos personales, elige **Deportistas concretos**). Pulsa **Filtrar**.

En cada fila, **Ver archivo** abre el archivo tal y como lo recibe el deportista, también en borradores y documentos personales.

## 10. Qué ve el deportista

Para comprobar cómo queda, abre `/mi-cuenta/documentos/` con tu sesión de administrador: ves la sección aunque no tengas el enlace en el menú. Ten en cuenta que el administrador ve **todos** los documentos generales y solo los personales que le correspondan; no puedes ver la sección «como» otro deportista.

El deportista ve, para cada documento: la etiqueta PDF o IMG, el título, la descripción, el tipo, el tamaño y la fecha, y el botón **Abrir**.

## 11. Si un deportista dice que no ve un documento

Repasa, en este orden:

1. ¿Está **publicado**? No en borrador, programado o en la papelera.
2. ¿Tiene archivo? En el listado no debe poner **Sin archivo**.
3. Si es para deportistas concretos, ¿está él en la lista? Pasa el ratón por **Visible para**.
4. ¿El deportista está en estado **activo**? Míralo en su perfil (**Usuarios**). En standby, baja o moroso no ve nada.
5. ¿Ha entrado con su cuenta? La sección está en **Mi cuenta → Documentos**, detrás de **Mis pagos**.

Si el deportista pulsa un enlace antiguo de un documento que ya no puede ver, vuelve a la sección con el aviso «Este documento ya no está disponible.».

## 12. Lo que no hace (por ahora)

- No avisa por correo a los deportistas cuando se publica algo.
- No registra quién ha abierto cada documento.
- No guarda versiones anteriores de un archivo.
- No tiene buscador en la sección del deportista.
- No admite documentos de Word, Excel u otros formatos: conviértelos a PDF.

## 13. Comprobación técnica tras desplegar

Para quien despliega, una vez por servidor:

1. La carpeta `wp-content/uploads/sulayr-documentos/` existe y contiene `.htaccess` e `index.html`. Si falta, el plugin la crea al entrar un administrador en el escritorio; si no puede, muestra un aviso rojo.
2. Abrir `https://<dominio>/wp-content/uploads/sulayr-documentos/index.html` en el navegador devuelve **403 Forbidden**. Si devuelve 200, el servidor no aplica el `.htaccess` (Apache sin `AllowOverride`, o Nginx): no publiques documentos personales hasta resolverlo.
3. En **Medios → Añadir nuevo**, el texto «Tamaño máximo de archivo de subida» indica **10 MB o más**. Si es menos, hay que subir `upload_max_filesize` y `post_max_size` en PHP.
4. **Mi cuenta → Documentos** abre sin error 404 con un deportista activo. Si da 404, guarda una vez **Ajustes → Enlaces permanentes**.

Comprobado en producción el 22/10/2026, desde fuera y sin sesión:

- Puntos 1 y 2: la carpeta devuelve 403 incluso para archivos que no existen, y el resto de `uploads` responde con normalidad.
- Punto 4: `/mi-cuenta/documentos/` responde (una ruta inventada de Mi cuenta da 404), y una descarga sin sesión lleva a Mi cuenta.
- Punto 3: queda por revisar desde el escritorio.
