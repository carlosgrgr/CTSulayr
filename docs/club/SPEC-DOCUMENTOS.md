# SPEC — Documentos en el perfil del deportista

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr`
**Estado:** Decisiones D1–D8 tomadas (sección 12). Fases D1 a D7 en `main`. D1 a D4 también en producción (`cfed7450`). D5 a D7 en `deb5398e` (11.8).
**Fecha:** octubre 2026

Este documento analiza el código actual, compara arquitecturas y propone una solución para publicar documentos desde la administración y ofrecerlos a los deportistas en su área privada. Las decisiones que dependían de información ausente en el código se marcaron como pendientes y ya están resueltas en la sección 12. El texto de las secciones 4 a 11 refleja esas decisiones.

---



## 1. Análisis del proyecto actual



### 1.1. Qué se ha revisado


| Área                     | Archivos                                                                                                                                                                                               |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Arranque y dependencias  | `club-triatlon-sulayr.php`, `src/App/App.php`, `src/App/WpDependencies.php`                                                                                                                            |
| Clases base              | `Modules/PostTypes/PostType.php`, `Modules/Taxonomies/Taxonomy.php`, `Modules/Fields/Fields.php`, `Modules/Roles/Role.php`, `Modules/Endpoints/Endpoint.php`, `Modules/SettingsPages/SettingsPage.php` |
| Entidades existentes     | `WalletPostType`, `WalletFields`, `WalletAdminList`, `WalletBalancePage`, `SubscriptionChangesPostType`, `SubscriptionChangesFields`                                                                   |
| Perfil del deportista    | `Modules/WooCommerce/WooCommerceIntegration.php`, `templates/page-user-profile.php`, `templates/parts/*.php`                                                                                           |
| Usuarios y permisos      | `Services/UserService.php`, `Modules/Roles/AthleteRole.php`, `Modules/Users/*`, `Modules/Fields/UserFields/UserFields.php`                                                                             |
| Nombres centralizados    | `Infrastructure/CustomFields.php`, `Infrastructure/CustomTemplates.php`                                                                                                                                |
| Plantillas y assets      | `Services/TemplateService.php`, `public/css/styles.css`, `themes/ct-sulayr-theme/functions.php`, `themes/ct-sulayr-theme/profile-bento.css`                                                            |
| Integración con reservas | `plugins/sulayr-reservas/src/Access/CrossSite.php` (enlace en el menú de Mi cuenta)                                                                                                                    |
| Entorno                  | `wp-config.php`, `.htaccess`, vhost de Apache, `wp-content/uploads/`, opciones y roles leídos con WP-CLI                                                                                               |


Versiones del entorno local al escribir esta spec: WordPress 7.1.2, WooCommerce 11.1.1, ACF PRO 6.8.10, PHP 8.4 (objetivo del plugin: PHP 7.4). El 9 de noviembre de 2026 el entorno local se actualizó a WordPress 7.1.4, WooCommerce 11.2.2 y ACF PRO 6.8.11.

### 1.2. Cómo está estructurado el plugin

- Composer con PSR-4 `ClubTriatlonSulayr\` → `src/`. `vendor/` está en el repositorio.
- `App` comprueba dependencias (ACF PRO obligatorio) y, en `plugins_loaded`, instancia todas las clases en `load_classes()`. No hay contenedor ni registro de módulos: añadir una funcionalidad es añadir `new Clase()` ahí.
- Cada clase registra sus propios hooks en el constructor.
- Los nombres de campos ACF son constantes en `Infrastructure\CustomFields`. Las claves de campo y de grupo se derivan con `md5()` del nombre.
- El plugin solo está activo en el blog 1 (club). No está activado en red. El blog 2 (reservas) no carga este código.



### 1.3. Cómo funciona el perfil del deportista

El área del deportista **es la página Mi cuenta de WooCommerce** (`/mi-cuenta/`, página 2388). No hay un sistema propio de secciones.

`WooCommerceIntegration` añade tres endpoints de Mi cuenta, solo para el rol `athlete`:


| Endpoint            | Etiqueta             | Contenido                                              |
| ------------------- | -------------------- | ------------------------------------------------------ |
| `perfil-deportista` | Perfil de deportista | Formulario ACF del usuario y activación de suscripción |
| `mi-monedero`       | Mi monedero          | Movimientos del CPT `sulayr_wallet`                    |
| `mis-pagos`         | Mis pagos            | Facturas de Stripe                                     |


El patrón de cada sección es siempre el mismo:

1. Constante privada con el slug del endpoint.
2. `woocommerce_get_query_vars` para registrar el endpoint.
3. `woocommerce_account_menu_items` para insertar el ítem, solo si el usuario es `athlete`.
4. `woocommerce_account_{endpoint}_endpoint` para pintar el contenido.
5. `woocommerce_endpoint_{endpoint}_title` para el título.
6. El método de salida prepara variables y hace `include` de `templates/parts/content-*.php`.

Menú actual para un deportista: Escritorio, Perfil de deportista, Mi monedero, Mis pagos, Pedidos, Direcciones, Detalles de cuenta, Cerrar sesión. `sulayr-reservas` añade además «Reservas de piscina» detrás de «Perfil de deportista» (prioridad 20).

`templates/page-user-profile.php` y `templates/parts/content-user-menu.php` son la versión antigua del perfil. Los deportistas que entran en `/perfil-deportista` se redirigen a Mi cuenta (`redirect_old_athlete_pages_to_wc`). La nueva sección no debe tocar esa plantilla.

### 1.4. Permisos y roles

- `athlete` solo tiene `read`. Se crea con `add_role()` en `init` (`Role::add_new_role`).
- No existe un rol de gestor en el club. Toda la administración propia (Configuración Sulayr, Saldo del monedero, importación CSV) exige `manage_options`, es decir, administrador.
- `UserService::redirect_athlete_to_profile` saca a cualquier `athlete` de `wp-admin` en `admin_init`, salvo AJAX. Consecuencia: **un deportista no puede usar** `admin-post.php`. Cualquier descarga o acción del front tiene que ir por una URL pública controlada por el plugin.
- El acceso a las secciones de Mi cuenta se decide con `in_array( 'athlete', $user->roles )`. No hay capacidades propias.
- El estado del deportista (`active`, `standby`, `baja`, `moroso`) **no limita** hoy el acceso a ninguna sección del perfil.



### 1.5. Uso de ACF

- Los grupos de campos se registran por código con `acf_add_local_field_group()` (clase base `Fields`). No hay grupos en base de datos para estas entidades.
- Las entidades del club se modelan como **CPT no público + grupo ACF**: `sulayr_wallet` (movimientos) y `sulayr_subscription_changes` (histórico). Los datos van en postmeta con `get_field()` / `update_field()`.
- El único campo de archivo es la foto de perfil (`sulayr_user_photo`, tipo `file`, `return_format => id`). Se sube a la Media Library normal y es pública.
- No se usan páginas de opciones de ACF ni repeaters en el plugin.



### 1.6. Plantillas y assets

- `App::load_public_assets()` encola Bootstrap 5.3.8 y `public/css/styles.css` en todo el front.
- El tema hijo `ct-sulayr-theme` encola `profile-bento.css` después de los estilos del plugin. Ahí está el diseño Bento del perfil (`.sulayr-athlete-profile-content`, `.sulayr-info-block`, `.sulayr-profile-card`…).
- Las plantillas usan iconos `bi bi-*` (Bootstrap Icons), pero **ni el plugin ni el tema encolan la fuente de Bootstrap Icons**. No conviene depender de ellos.
- Existe `TemplateService::load()` / `render()` y el helper `sulayr_load_template()`, pero las secciones de Mi cuenta hacen `include` directo de `SULAYR_TEMPLATE_DIR . '/parts/…'`.
- Hook de extensión ya existente: `do_action( 'sulayr_athlete_profile_card' )`, usado por reservas.



### 1.7. Archivos y documentos

- **No existe ninguna funcionalidad de documentos.** El menú «Descargas» de WooCommerce se elimina expresamente (`remove_downloads_menu_item`).
- La Media Library del blog 1 tiene muy pocos adjuntos y todos son imágenes.
- Precedente útil: WooCommerce guarda sus descargables en `uploads/woocommerce_uploads/` con un `.htaccess` `deny from all` y los sirve por PHP (`woocommerce_file_download_method = force`). Es el mismo patrón que se propone aquí.
- Las páginas de adjunto están desactivadas (`wp_attachment_pages_enabled = 0`).
- `sulayr-reservas/src/Swimmers/PhotoService.php` valida subidas con `wp_check_filetype_and_ext()` y `wp_handle_upload()`. Es de otro plugin; sirve como referencia, no se reutiliza.



### 1.8. Otros condicionantes encontrados


| Hallazgo                                                                                                                                                                     | Impacto en esta funcionalidad                                                                                                       |
| ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| `register_post_type()` rechaza nombres de más de 20 caracteres. `sulayr_subscription_changes` tiene 27 y **no llega a registrarse** (`post_type_exists()` devuelve `false`). | El nuevo CPT debe tener 20 caracteres o menos. El fallo del histórico es independiente y se comunica aparte.                        |
| `PostType::add_custom_post_type()` llama a `flush_rewrite_rules()` en cada carga. `WalletPostType` y `SubscriptionChangesPostType` sobrescriben el método para evitarlo.     | El nuevo CPT también debe sobrescribirlo.                                                                                           |
| `Role::add_new_role()` usa `add_role()`, que no hace nada si el rol ya existe.                                                                                               | Añadir una capacidad a `athlete` exige `get_role()->add_cap()` con una versión guardada, no tocar `$capabilities` en `AthleteRole`. |
| `sulayr_wallet` usa `capability_type = post`. Cualquier editor o autor puede crear movimientos de monedero.                                                                  | No repetirlo: los documentos tendrán capacidades propias. El caso del monedero se comunica aparte.                                  |
| Los endpoints nuevos de Mi cuenta necesitan regenerar las reglas de reescritura. El plugin ya lo hace con la opción `sulayr_wc_flush_rewrite_rules`, pero solo al activarse. | Hay que marcar esa opción al desplegar (subida de versión) o guardar los enlaces permanentes a mano.                                |
| Servidor local: Apache con `AllowOverride All` y `mod_rewrite`. No hay `mod_xsendfile`.                                                                                      | La protección por `.htaccess` funciona en local. En producción hay que confirmarlo (ver decisiones pendientes).                     |
| `handle_subscription_activation_request` tiene la comprobación del nonce comentada.                                                                                          | No afecta a documentos. Se comunica aparte.                                                                                         |


---



## 2. Comparativa de arquitecturas

Se comparan cuatro opciones. La A es la opción 1 propuesta, la B es la opción 2, la C usa solo la Media Library y la D usa tablas propias.


| Criterio                                               | A. CPT + ACF (+ taxonomía)                                                    | B. Opciones ACF + Repeater                                                                        | C. Solo Media Library                                                         | D. Tablas propias                       |
| ------------------------------------------------------ | ----------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------- | --------------------------------------- |
| Complejidad inicial                                    | Baja-media. Hay clases base listas.                                           | Baja                                                                                              | Baja en datos, alta en UI de gestión                                          | Alta: esquema, CRUD y pantallas propias |
| Mantenibilidad                                         | Alta. Mismo patrón que monedero.                                              | Media. Toda la lista en un solo formulario.                                                       | Baja. Mezcla documentos con imágenes del sitio.                               | Media. Todo es código propio.           |
| Escalabilidad                                          | Alta. Cada documento es una fila con índices de WP.                           | Baja. Cada edición reescribe la lista. Formulario lento con muchas filas.                         | Media                                                                         | Alta                                    |
| Integración con WordPress                              | Nativa: listado, búsqueda, papelera, borradores, programación, autor, fechas. | Mínima: sin estado, sin papelera, sin fechas por elemento.                                        | Nativa pero pensada para medios, no para documentos con descripción y estado. | Ninguna                                 |
| Integración con ACF                                    | Natural: grupo local sobre el CPT, como `WalletFields`.                       | Natural, pero el repeater es la única estructura.                                                 | Campos ACF sobre adjuntos: posible pero poco habitual.                        | Nula                                    |
| Gestión administrativa                                 | Pantalla propia, columnas, filtros, orden.                                    | Una sola pantalla. Reordenar es arrastrar filas.                                                  | Media Library: no muestra descripción, categoría ni orden.                    | A construir                             |
| Gestión de archivos                                    | Un adjunto por documento. Se puede controlar dónde se guarda y cómo se sirve. | Igual, pero sin un ID estable de documento.                                                       | Es el propio archivo. Público por defecto.                                    | A construir                             |
| Seguridad                                              | Permisos por capacidad sobre cada documento. Descarga por ID de documento.    | Un documento no tiene identidad: su ID es la posición en la lista y cambia al reordenar o borrar. | Las URL de `/uploads/` son públicas.                                          | Control total, todo a mano              |
| Añadir categorías                                      | Taxonomía nativa                                                              | Campo select dentro del repeater, sin gestión de términos                                         | Taxonomía sobre adjuntos                                                      | Tabla y pantalla nuevas                 |
| Visibilidad por colectivo en el futuro                 | Meta o taxonomía por documento + una regla en el servicio                     | Campo dentro de cada fila. Filtrar exige cargar toda la lista.                                    | Posible, poco natural                                                         | Fácil, pero todo a mano                 |
| Nuevas funcionalidades (lectura, caducidad, histórico) | Se apoyan en el ID del post                                                   | Difícil sin ID estable                                                                            | Difícil                                                                       | Fácil                                   |
| Impacto en el código existente                         | Bajo: clases nuevas y un `new` en `App`                                       | Bajo                                                                                              | Bajo                                                                          | Medio                                   |




### Por qué B no es adecuada

- Un elemento del repeater no tiene identidad propia. ACF guarda `options_documentos_0_titulo`, `options_documentos_1_titulo`…: al reordenar o borrar, los índices cambian. Una URL de descarga, una confirmación de lectura o un permiso futuro no pueden apuntar a «el documento 3».
- Sin estado por documento: no hay borrador, programación, papelera ni fecha de publicación por elemento.
- Dos administradores editando a la vez se pisan la lista entera.
- Los archivos siguen en la Media Library pública. B no resuelve el problema de seguridad.



### Por qué C no es adecuada

- Las URL de `/uploads/AAAA/MM/` son públicas y previsibles.
- La Media Library mezcla documentos del club con imágenes de la web. Habría que filtrarla en todas partes.
- La UI de medios no está pensada para título, descripción, orden y estado de publicación.



### Por qué D no es adecuada ahora

- Rehace lo que WordPress ya da: listado, edición, papelera, búsqueda y fechas.
- No encaja con el patrón del plugin del club (CPT + ACF). Las tablas propias son el patrón de `sulayr-reservas` porque allí hay concurrencia y volumen, que aquí no existen.

---



## 3. Recomendación

**Arquitectura A, con una corrección imprescindible: CPT** `sulayr_document` **+ campos ACF + taxonomía de categorías + archivo guardado en un directorio privado y servido por PHP tras comprobar permisos.**

La opción 1 es la base correcta, pero sola no basta. Si el archivo se sube a la Media Library normal, cualquiera con la URL lo descarga sin sesión, por mucho que la sección del perfil esté protegida.

Por qué encaja en este proyecto:

- Reutiliza las clases base que ya existen (`PostType`, `Taxonomy`, `Fields`) y repite el patrón de `sulayr_wallet`.
- Reutiliza el sistema de secciones que ya usa el perfil (endpoints de Mi cuenta de WooCommerce) sin inventar otro.
- El ID del post da a cada documento una identidad estable para la URL de descarga y para cualquier evolución (lectura, visibilidad, caducidad).
- El estado, las fechas, la papelera y la búsqueda vienen de WordPress sin código.
- La protección del archivo es una pieza aislada que sigue el mismo patrón que WooCommerce para sus descargables.

---



## 4. Especificación funcional



### 4.1. Administrador / gestor

Quién gestiona: solo el **administrador** del blog 1 (D1). Es el mismo criterio que el resto de la administración del club. Las capacidades propias permiten dárselo después a otro perfil sin tocar código.


| Acción                        | Cómo                                                                                                       |
| ----------------------------- | ---------------------------------------------------------------------------------------------------------- |
| Crear documento               | Documentos → Añadir nuevo. Título, descripción, archivo, categoría y destinatarios.                        |
| Elegir destinatarios          | Campo «Visible para»: «Todos los deportistas» o «Deportistas concretos», con selector de deportistas (D3). |
| Editar título y descripción   | Pantalla de edición del documento                                                                          |
| Subir o reemplazar el archivo | Campo «Archivo». El archivo nuevo se guarda en el directorio privado.                                      |
| Ocultar sin borrar            | Pasar a borrador                                                                                           |
| Programar                     | Fecha de publicación futura (estado nativo «Programado»)                                                   |
| Eliminar                      | Papelera nativa. El borrado definitivo elimina también el archivo.                                         |
| Categorizar                   | Una categoría por documento (taxonomía `sulayr_doc_category`)                                              |
| Gestionar categorías          | Documentos → Categorías: crear, renombrar, borrar                                                          |
| Ordenar                       | Campo «Orden» (`menu_order`). Menor número, más arriba.                                                    |




### 4.2. Deportista

Solo los deportistas **en estado activo** tienen acceso (D2). Un deportista en `standby`, `baja` o `moroso` no ve la sección ni puede descargar nada, tampoco los documentos dirigidos a él (D3b). Un estado vacío cuenta como activo, igual que en `UserService::get_athlete_formatted_data()`.


| Acción                           | Resultado                                                                                               |
| -------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Entrar en Mi cuenta → Documentos | Primero el grupo «Para ti» con los documentos dirigidos a él; después los generales por categoría (D3c) |
| Ver un documento                 | Título, descripción, tipo de archivo, tamaño y fecha                                                    |
| Abrir un documento               | PDF o imagen, en una pestaña nueva, servido por el plugin (D5, D6)                                      |




### 4.3. Casos límite


| Caso                                                                 | Comportamiento                                                                                                                                                     |
| -------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Sin sesión, entra en `/mi-cuenta/documentos/`                        | WooCommerce muestra el formulario de acceso (comportamiento actual de Mi cuenta)                                                                                   |
| Sin sesión, abre una URL de descarga                                 | Redirección al acceso. Tras iniciar sesión, el deportista vuelve a Mi cuenta, no al archivo: `redirect_athlete_to_my_account` fuerza ese destino. Se acepta en v1. |
| Usuario con sesión pero sin rol `athlete` (cliente de la tienda)     | No ve el ítem del menú. La URL de la sección y la de descarga responden 403.                                                                                       |
| Deportista no activo (`standby`, `baja`, `moroso`)                   | No ve el ítem del menú. La URL de la sección redirige a Mi cuenta con un aviso. La descarga responde 403.                                                          |
| Deportista activo y documento dirigido a otros deportistas           | No aparece en su lista. Su URL de descarga responde **404**, no 403, para no revelar que el documento existe.                                                      |
| Documento «Deportistas concretos» sin ningún deportista seleccionado | No lo ve nadie. El guardado lo impide (campo obligatorio) y el listado de administración lo marca.                                                                 |
| Se quita a un deportista de la lista de destinatarios                | Pierde el acceso de inmediato                                                                                                                                      |
| Se borra un usuario destinatario                                     | Su ID desaparece de la lista de destinatarios (`deleted_user`)                                                                                                     |
| Un deportista vuelve a estado activo                                 | Recupera el acceso a todo lo que le corresponda                                                                                                                    |
| Administrador                                                        | Puede abrir cualquier descarga para comprobarla, incluidos los borradores y los documentos personales de otros                                                     |
| Documento en borrador, programado o en papelera                      | No aparece en la lista. Su URL de descarga responde 404.                                                                                                           |
| Documento publicado sin archivo, o con el archivo borrado del disco  | No aparece en la lista. La descarga responde 404. El listado de administración lo marca como «Sin archivo».                                                        |
| ID inexistente o de otro tipo de post                                | 404                                                                                                                                                                |
| Ningún documento publicado                                           | Estado vacío con un mensaje                                                                                                                                        |
| Acceso directo a la URL física del archivo                           | 403 del servidor                                                                                                                                                   |
| El archivo cambia mientras alguien lo descarga                       | La descarga en curso termina con el archivo anterior. Las siguientes sirven el nuevo.                                                                              |


---



## 5. Modelo de datos


| Dato                                 | Almacenamiento   | Tipo                                                                                            | Nombre                          |
| ------------------------------------ | ---------------- | ----------------------------------------------------------------------------------------------- | ------------------------------- |
| Título                               | Nativo           | `post_title`                                                                                    | —                               |
| Descripción                          | ACF              | `textarea`, máx. 300 caracteres, sin HTML                                                       | `sulayr_document_description`   |
| Archivo                              | ACF              | `file`, `return_format => id`                                                                   | `sulayr_document_file`          |
| Categoría                            | Taxonomía        | `sulayr_doc_category`, un término por documento                                                 | —                               |
| Visible para                         | ACF              | `radio`: `all` (todos los deportistas) / `users` (deportistas concretos). Por defecto `all`.    | `sulayr_document_audience`      |
| Deportistas destinatarios (edición)  | ACF              | `user`, múltiple, rol `athlete`, `return_format id`. Visible solo si «Visible para» es `users`. | `sulayr_document_users`         |
| Deportistas destinatarios (consulta) | Meta             | Una fila de postmeta por deportista (no única), escrita por el plugin desde el campo anterior   | `_sulayr_document_user`         |
| Orden                                | Nativo           | `menu_order` (`supports` incluye `page-attributes`)                                             | —                               |
| Estado                               | Nativo           | `post_status`: `publish`, `draft`, `future`, `trash`                                            | —                               |
| Fecha de publicación                 | Nativo           | `post_date`                                                                                     | —                               |
| Fecha de modificación                | Nativo           | `post_modified`                                                                                 | —                               |
| Fecha de cambio del archivo          | Meta             | `Y-m-d H:i:s`, la escribe el plugin al cambiar el archivo                                       | `_sulayr_document_file_updated` |
| Autor                                | Nativo           | `post_author`                                                                                   | —                               |
| Marca de archivo privado             | Meta del adjunto | `1`                                                                                             | `_sulayr_private_document`      |


Por qué la descripción va en ACF y no en `post_excerpt`: en el editor clásico (el CPT no usa REST, así que no usa bloques) la caja de extracto está **oculta por defecto** en Opciones de pantalla. Un campo ACF obligatorio y visible evita documentos sin descripción por despiste. Coste: la búsqueda del listado de administración no busca en la descripción, solo en el título.

Por qué la categoría es taxonomía y no un `select` de ACF: los términos se gestionan sin tocar código, aparecen como columna y filtro en el listado y sirven para agrupar en el front. El campo se pinta con un campo ACF `taxonomy` en modo `radio` (`save_terms` y `load_terms` activos) para forzar una sola categoría. La caja nativa de la taxonomía se oculta (`meta_box_cb => false`).

Por qué los destinatarios se guardan dos veces: ACF guarda un campo `user` múltiple como **un array serializado** en una sola fila de postmeta. Buscar «documentos de este deportista» exigiría un `LIKE` sobre el texto serializado. Esa búsqueda es frágil (`"12"` coincide dentro de `"123"` si no se acota bien) y no usa índices. El campo ACF queda como interfaz de edición. Al guardar, el plugin reescribe las filas `_sulayr_document_user` (una por ID), y la consulta del front usa `meta_query` con `compare '='` exacto. El guion bajo inicial oculta esa meta en la caja de campos personalizados.

Las constantes nuevas van en `Infrastructure\CustomFields`, con el mismo estilo que las del monedero:

```php
/** Documentos CPT */
const DOCUMENT_FIELDS_GROUP = 'document_fields';
const DOCUMENT_DESCRIPTION  = 'sulayr_document_description';
const DOCUMENT_FILE         = 'sulayr_document_file';
const DOCUMENT_CATEGORY     = 'sulayr_document_category'; // campo ACF que escribe la taxonomía
const DOCUMENT_AUDIENCE     = 'sulayr_document_audience'; // 'all' | 'users'
const DOCUMENT_USERS        = 'sulayr_document_users';    // campo ACF de edición
const DOCUMENT_USER_INDEX   = '_sulayr_document_user';    // una fila por destinatario, para consultar
```

---



## 6. Seguridad



### 6.1. Nivel de confidencialidad

La v1 incluye **documentos con datos personales dirigidos a deportistas concretos** (D3): autorizaciones, documentación federativa o administrativa individual. Por tanto la v1 maneja datos personales. Eso fija los requisitos mínimos:

- **Protección del archivo físico obligatoria**: directorio privado + descarga controlada (6.3 y 6.5). Proteger solo la interfaz no es aceptable.
- **Control de acceso por documento y por usuario**, comprobado en servidor en cada descarga, no solo al pintar la lista.
- **Mínima exposición**: quien no es destinatario recibe 404. El título de un documento personal no aparece en ningún listado ajeno.
- **Gestión restringida a administradores** (D1).
- **Borrado real del archivo** al eliminar el documento o sustituir el archivo (D7), para no acumular copias de datos personales.

Regla de acceso de un usuario a un documento publicado:

```
puede_ver = current_user_can( 'sulayr_view_documents' )
         && estado del deportista == 'active'          (vacío cuenta como activo)
         && ( audiencia == 'all'
              || usuario está en _sulayr_document_user )
```

El administrador (`edit_post` sobre el documento) puede ver cualquiera, incluidos los borradores. La regla vive solo en `DocumentService::user_can_view()`. La lista y la descarga la usan las dos.

Fuera de alcance: cifrado en reposo y registro de accesos. El registro se puede añadir después con el hook `sulayr_document_downloaded` (sección 10). Las obligaciones legales sobre datos personales (base legal, conservación, información al interesado) son del club y no las resuelve el código.

### 6.2. Por qué no basta con proteger la interfaz

Un archivo subido por la Media Library normal queda en `wp-content/uploads/AAAA/MM/nombre.pdf`. Apache lo sirve sin pasar por WordPress: no hay sesión ni permisos. La URL se filtra con facilidad (correo reenviado, historial, captura) y es previsible. Ocultar el enlace en el perfil no protege el archivo.

### 6.3. Protección del archivo

1. **Directorio privado:** `wp-content/uploads/sulayr-documentos/`. Al subir desde el campo `sulayr_document_file`, el plugin aplica un filtro `upload_dir` solo durante esa subida (enganchado en `acf/upload_prefilter/name=sulayr_document_file`). Funciona tanto con el modal de medios como con el uploader básico.
2. **Bloqueo en el servidor:** el plugin crea en ese directorio un `.htaccess` (`Require all denied`, con alternativa `deny from all`) y un `index.html` vacío, igual que `woocommerce_uploads/`. Lo comprueba y lo recrea si falta.
3. **El adjunto sigue registrado en la Media Library** (lo necesita ACF). Se marca con `_sulayr_private_document = 1`.
4. **No se aceptan archivos ya existentes de la biblioteca:** el campo usa `library => uploadedTo`. Además, una validación (`acf/validate_value/name=sulayr_document_file`) rechaza un adjunto que no esté dentro del directorio privado. Así no se puede elegir una imagen pública ya subida.
5. **Descarga por PHP:** el archivo solo sale por la URL de descarga del plugin (6.5), tras comprobar permisos.

Limitaciones:

- Producción usa **Apache con** `.htaccess` (D4). El bloqueo depende de que el vhost de producción tenga `AllowOverride` que permita `Require` o `Deny` (`All` o `AuthConfig Limit`). En la fase D7 se comprueba con una petición real a la URL física en producción, que debe devolver 403. Si algún día se migra a Nginx, hará falta una regla `location` equivalente.
- En la Media Library, las miniaturas de imágenes privadas aparecerán rotas para el administrador, porque el servidor bloquea su URL. Se acepta en v1. Opcional: ocultar los adjuntos privados de la biblioteca general (filtro `ajax_query_attachments_args`), dejándolos visibles solo en el campo del documento.
- Una CDN o caché delante del sitio no debe cachear las URL de descarga. Se envían `Cache-Control: private, no-store`.



### 6.4. Capacidades

CPT con `capability_type => [ 'sulayr_document', 'sulayr_documents' ]` y `map_meta_cap => true`. WordPress deriva las capacidades primitivas:


| Capacidad                                                                                                                           | Administrador | Deportista |
| ----------------------------------------------------------------------------------------------------------------------------------- | ------------- | ---------- |
| `edit_sulayr_documents`, `edit_others_sulayr_documents`, `edit_published_sulayr_documents`, `edit_private_sulayr_documents`         | sí            | no         |
| `publish_sulayr_documents`                                                                                                          | sí            | no         |
| `delete_sulayr_documents`, `delete_others_sulayr_documents`, `delete_published_sulayr_documents`, `delete_private_sulayr_documents` | sí            | no         |
| `read_private_sulayr_documents`                                                                                                     | sí            | no         |
| `sulayr_view_documents` (nueva, del plugin)                                                                                         | sí            | sí         |


Taxonomía: `manage_terms`, `edit_terms` y `delete_terms` → `edit_others_sulayr_documents`. `assign_terms` → `edit_sulayr_documents`.

Las capacidades se añaden con `get_role()->add_cap()` desde una clase de sincronización con versión (`sulayr_documents_caps_version`). Se ejecuta en `init` (prioridad 20, después de que `AthleteRole` cree el rol) si la versión guardada no coincide o si a un rol gestionado le falta alguna capacidad del mapa. Motivo: `add_role()` no actualiza un rol que ya existe (1.8). Si una capacidad de documentos (`sulayr_view_documents` o `*_sulayr_documents`) deja de estar en el mapa, la sincronización la retira, pero solo de los roles gestionados (`administrator`, `athlete`). Si falta un rol, la versión no se guarda y se reintenta en la siguiente petición.

El front comprueba `current_user_can( 'sulayr_view_documents' )`, no el nombre del rol. El menú sigue mostrándose solo a deportistas, como el resto de secciones.

### 6.5. Descarga controlada

URL: `/mi-cuenta/documentos/{ID}/`. Es el mismo endpoint de Mi cuenta con un valor, que WooCommerce ya soporta (`wc_get_endpoint_url( 'documentos', $id )`). No hace falta una regla de reescritura propia ni pasar por `admin-post.php`, que un deportista no puede usar (1.4).

En `template_redirect`, antes de que WooCommerce pinte nada:

1. Si el valor del endpoint no es un entero positivo → la sección se pinta normal.
2. Sin sesión → `auth_redirect()` o redirección a Mi cuenta.
3. Sin `sulayr_view_documents`, o deportista no activo → 403.
4. El post no existe, no es `sulayr_document`, no está publicado o el usuario no es destinatario (`DocumentService::user_can_view()`) → 404. El administrador (`edit_post`) puede ver borradores y documentos personales.
5. Archivo sin ID, sin la marca privada, inexistente o fuera del directorio privado (`realpath`) → 404 y una entrada en `debug.log`.
6. Envío del archivo:
  - `nocache_headers()`, `X-Content-Type-Options: nosniff`, `X-Robots-Tag: noindex`.
  - `Content-Type` desde `wp_check_filetype()`. Solo tipos de la lista permitida.
  - `Content-Disposition: inline` (D5: todos los tipos permitidos se abren en el navegador). Nombre del archivo: `sanitize_file_name( título )` + extensión real.
  - `Content-Length` y lectura por bloques. Se vacían los búferes de salida antes de enviar.

No se usa nonce en la URL de descarga. Es una lectura sin efectos, protegida por la sesión y la capacidad. Un nonce caducaría el enlace a las 24 horas y no añade protección frente a CSRF en una operación de solo lectura.

Alternativa evaluada: `WC_Download_Handler::download_file_force()`. Fuerza siempre la descarga (`attachment`) e impide abrir el PDF en el navegador. Se descarta por D5.

### 6.6. Validación y sanitización


| Entrada                       | Regla                                                                                                                                                           |
| ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Título                        | Nativo de WP. Obligatorio: si falta, el documento no se publica (`wp_insert_post_data` lo devuelve a borrador con aviso).                                       |
| Descripción                   | ACF `textarea`, `maxlength 300`. Salida con `esc_html()` y `nl2br` si se permiten saltos de línea.                                                              |
| Archivo                       | Obligatorio. Extensiones permitidas por campo ACF (`mime_types`) y comprobación real con `wp_check_filetype_and_ext()`. Tamaño máximo en el campo (`max_size`). |
| Tipos permitidos v1           | `pdf`, `jpg`, `jpeg`, `png`. Máximo **10 MB** por archivo (D6). En local `upload_max_filesize` es 512M. En producción debe ser 10M o más (se comprueba en D7).  |
| Visible para                  | Solo `all` o `users`. Cualquier otro valor se rechaza en validación. Nunca se convierte en `all`, para no publicar a todos un documento personal por error.     |
| Deportistas destinatarios     | Obligatorio si «Visible para» es `users`. Cada ID se comprueba con `get_userdata()` y el rol `athlete`. Los que no cumplen se descartan al guardar.             |
| ID de descarga                | `absint()`                                                                                                                                                      |
| Formularios de administración | Los del editor de WP y ACF, con sus nonces. El plugin no añade formularios propios en v1.                                                                       |




### 6.7. Eliminación y sustitución


| Situación                                  | Comportamiento                                                                                                                                                                              |
| ------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Documento a la papelera                    | Deja de verse y su descarga da 404. El archivo se conserva para poder restaurar.                                                                                                            |
| Borrado definitivo                         | En `before_delete_post`, se elimina el adjunto privado (`wp_delete_attachment( $id, true )`) si ningún otro documento lo usa.                                                               |
| Sustitución del archivo                    | Al guardar, si el ID del archivo cambia, se borra el adjunto anterior (mismo control) y se actualiza `_sulayr_document_file_updated` (D7: sin versionado).                                  |
| Cambio de destinatarios                    | Al guardar se reescriben las filas `_sulayr_document_user`. El acceso cambia de inmediato.                                                                                                  |
| Borrado del adjunto desde la Media Library | El documento queda sin archivo: deja de listarse y la administración lo marca como «Sin archivo».                                                                                           |
| Archivos subidos y no usados               | Al guardar, se borran los adjuntos privados subidos a ese documento (`post_parent`) que no son su archivo actual, con el mismo control. Así no se acumulan las pruebas del modal de medios. |


---



## 7. Arquitectura del código

Todo en el plugin `club-triatlon-sulayr`. Nada en el tema, salvo estilos opcionales en `profile-bento.css` si se quiere el mismo acabado Bento (8.3).

### 7.1. Clases nuevas


| Clase                      | Ubicación                        | Responsabilidad                                                                                                                                                                                                                                                                                                                        |
| -------------------------- | -------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `DocumentPostType`         | `Modules/PostTypes/`             | Registra `sulayr_document` (extiende `PostType`). Sobrescribe `add_custom_post_type()` sin `flush_rewrite_rules()`, como `WalletPostType`.                                                                                                                                                                                             |
| `DocumentCategoryTaxonomy` | `Modules/Taxonomies/`            | Registra `sulayr_doc_category` (extiende `Taxonomy`, hoy sin uso).                                                                                                                                                                                                                                                                     |
| `DocumentFields`           | `Modules/Fields/DocumentFields/` | Grupo ACF local (extiende `Fields`). Validación del archivo y de los destinatarios. Sincroniza el campo de destinatarios con las filas `_sulayr_document_user`.                                                                                                                                                                        |
| `DocumentCapabilities`     | `Modules/Documents/`             | Mapa de capacidades y sincronización versionada en `administrator` y `athlete`.                                                                                                                                                                                                                                                        |
| `DocumentStorage`          | `Modules/Documents/`             | Directorio privado, `.htaccess`, filtro `upload_dir` en la subida, marca del adjunto, limpieza al borrar o sustituir.                                                                                                                                                                                                                  |
| `DocumentService`          | `Services/`                      | Consultas y reglas: documentos visibles para un usuario (personales y generales por separado), `user_can_view()` como única regla de acceso (6.1), si el deportista está activo (`HelperService::get_user_field()` sobre `sulayr_user_athlete_status`) y datos de presentación del archivo (tipo, tamaño, URL de descarga). Sin hooks. |
| `DocumentsAccountEndpoint` | `Modules/Documents/`             | Endpoint `documentos` de Mi cuenta: query var, ítem del menú, título, contenido y descarga en `template_redirect`.                                                                                                                                                                                                                     |
| `DocumentAdminList`        | `Modules/Documents/`             | Columnas, orden por defecto y aviso «Sin archivo» en el listado (patrón de `WalletAdminList`). Solo en `is_admin()`.                                                                                                                                                                                                                   |


No se crea repositorio: `DocumentService` hace las consultas con `WP_Query` / `get_posts()`, como hace hoy el monedero. Separar repositorio y servicio no aporta nada con una sola consulta.

**Por qué un endpoint en su propia clase y no dentro de** `WooCommerceIntegration` **(D8):** esa clase ya mezcla tres endpoints, Stripe y redirecciones en 400 líneas. La nueva clase usa los mismos filtros de WooCommerce con el mismo patrón, y `WooCommerceIntegration` no cambia.

### 7.2. Registro en `App::load_classes()`

```php
new DocumentPostType();
new DocumentCategoryTaxonomy();
new DocumentFields();
new DocumentCapabilities();
new DocumentStorage();
if ( is_admin() ) {
    new DocumentAdminList();
}
if ( class_exists( 'WooCommerce' ) ) {
    new DocumentsAccountEndpoint( new DocumentService() );
}
```



### 7.3. Hooks


| Hook                                               | Clase                                          | Para qué                                                                                                 |
| -------------------------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------------------------------------------- |
| `init`                                             | `DocumentPostType`, `DocumentCategoryTaxonomy` | Registro                                                                                                 |
| `init`                                             | `DocumentCapabilities`                         | Sincronizar capacidades si cambia la versión                                                             |
| `acf/upload_prefilter/name=sulayr_document_file`   | `DocumentStorage`                              | Activar `upload_dir` privado durante la subida                                                           |
| `add_attachment`                                   | `DocumentStorage`                              | Marcar el adjunto si está en el directorio privado                                                       |
| `acf/validate_value/name=sulayr_document_file`     | `DocumentFields`                               | Rechazar adjuntos fuera del directorio privado                                                           |
| `acf/validate_value/name=sulayr_document_users`    | `DocumentFields`                               | Exigir al menos un deportista si la audiencia es `users`                                                 |
| `acf/save_post` (prioridad 20)                     | `DocumentFields`                               | Reescribir las filas `_sulayr_document_user`. Con audiencia `all` se borran todas.                       |
| `deleted_user`                                     | `DocumentFields`                               | Quitar al usuario borrado del campo y de las filas de destinatarios                                      |
| `acf/save_post` (prioridad 20)                     | `DocumentStorage`                              | Detectar cambio de archivo, borrar el anterior, actualizar la fecha                                      |
| `before_delete_post`                               | `DocumentStorage`                              | Borrar el adjunto privado                                                                                |
| `wp_insert_post_data`                              | `DocumentPostType`                             | Devolver a borrador un documento sin título                                                              |
| `woocommerce_get_query_vars`                       | `DocumentsAccountEndpoint`                     | Registrar `documentos`                                                                                   |
| `woocommerce_account_menu_items` (prioridad 15)    | `DocumentsAccountEndpoint`                     | Ítem «Documentos» detrás de «Mis pagos» (o de «Mi monedero» si no existe), solo para deportistas activos |
| `woocommerce_account_documentos_endpoint`          | `DocumentsAccountEndpoint`                     | Pintar la lista                                                                                          |
| `woocommerce_endpoint_documentos_title`            | `DocumentsAccountEndpoint`                     | Título «Documentos»                                                                                      |
| `template_redirect` (prioridad 2)                  | `DocumentsAccountEndpoint`                     | Descarga controlada                                                                                      |
| `manage_sulayr_document_posts_columns` y similares | `DocumentAdminList`                            | Columnas del listado                                                                                     |
| `pre_get_posts`                                    | `DocumentAdminList`                            | Orden por defecto del listado de administración                                                          |


Hooks propios, para extender sin tocar el código (coste mínimo):

- `apply_filters( 'sulayr_document_user_can_view', bool $can, int $document_id, int $user_id )`: punto de entrada de la visibilidad por colectivo (sección 10).
- `do_action( 'sulayr_document_downloaded', int $document_id, int $user_id )`: punto de entrada de la confirmación de lectura o del histórico.



### 7.4. Plantillas y assets

- `templates/parts/content-documents.php`: lista agrupada. Recibe `$personal` (documentos dirigidos al usuario), `$groups` (generales por categoría) y `$total`, preparados por el endpoint. Sigue el patrón `include` de las demás secciones.
- Estilos en `public/css/styles.css` con prefijo `.sulayr-documents-*`. No se añade JS en v1.
- Se reutiliza Bootstrap ya encolado. No se usan iconos `bi` (1.6): el tipo de archivo se muestra con una etiqueta de texto (PDF, DOC, XLS, IMG).



### 7.5. AJAX / REST

No aportan valor en v1. La lista se pinta en servidor y la descarga es una URL GET. `show_in_rest => false` en el CPT y la taxonomía: nada de esto se expone por `/wp-json/`. La clase base `Endpoint` (REST) existe, pero no se usa.

---



## 8. Backend



### 8.1. CPT `sulayr_document`


| Argumento                  | Valor                                       | Motivo                                                                                                             |
| -------------------------- | ------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Nombre                     | `sulayr_document` (15 caracteres)           | Máximo 20 (1.8)                                                                                                    |
| `public`                   | `false`                                     | Sin URL pública ni archivo                                                                                         |
| `publicly_queryable`       | `false`                                     | `?sulayr_document=…` no resuelve                                                                                   |
| `exclude_from_search`      | `true`                                      |                                                                                                                    |
| `show_ui` / `show_in_menu` | `true`                                      | Menú propio                                                                                                        |
| `menu_position`            | `58`                                        | Detrás de Monedero (56) y Cambios de tarifa (57)                                                                   |
| `menu_icon`                | `dashicons-media-document`                  |                                                                                                                    |
| `show_in_nav_menus`        | `false`                                     |                                                                                                                    |
| `show_in_rest`             | `false`                                     | Editor clásico, sin exposición REST                                                                                |
| `has_archive`              | `false`                                     |                                                                                                                    |
| `rewrite` / `query_var`    | `false`                                     |                                                                                                                    |
| `hierarchical`             | `false`                                     |                                                                                                                    |
| `supports`                 | `[ 'title', 'page-attributes' ]`            | Título y orden. Sin editor, extracto ni miniatura.                                                                 |
| `capability_type`          | `[ 'sulayr_document', 'sulayr_documents' ]` | Capacidades propias (6.4)                                                                                          |
| `map_meta_cap`             | `true`                                      |                                                                                                                    |
| `delete_with_user`         | `false`                                     | Borrar un administrador no borra documentos                                                                        |
| `taxonomies`               | `[]`                                        | La taxonomía se asocia al CPT desde su propio registro (`register_taxonomy( …, 'sulayr_document' )`) en la fase D5 |


Etiquetas: las de la clase base están en inglés y dependen de la traducción del `.po`. El CPT sobrescribe `get_labels()` con textos en español. Principales: «Documentos», «Documento», «Añadir documento», «Editar documento», «Buscar documentos», «No hay documentos», «No hay documentos en la papelera», «Orden» en lugar de «Atributos».

### 8.2. Taxonomía `sulayr_doc_category`


| Argumento                                                               | Valor                                                                     |
| ----------------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Etiquetas                                                               | «Categorías», «Categoría»                                                 |
| `hierarchical`                                                          | `true` (gestión como categorías; no se usan subcategorías en el front v1) |
| `public` / `publicly_queryable` / `show_in_nav_menus` / `show_tagcloud` | `false`                                                                   |
| `show_ui` / `show_admin_column`                                         | `true`                                                                    |
| `show_in_rest` / `rewrite` / `query_var`                                | `false`                                                                   |
| `meta_box_cb`                                                           | `false` (el campo ACF la sustituye)                                       |
| Orden de categorías en el front                                         | Por nombre. Orden manual de categorías: v2 (meta de término).             |


La clase base `Taxonomy` no expone `meta_box_cb` y fija `rewrite` como array. La subclase sobrescribe `add_custom_taxonomy()`, igual que los CPT sobrescriben el registro.

### 8.3. Grupo ACF «Datos del documento»

Ubicación: `post_type == sulayr_document`. Posición `acf_after_title`.


| Campo        | Tipo       | Configuración                                                                                                                                                                                                                        |
| ------------ | ---------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Descripción  | `textarea` | Obligatorio, `maxlength 300`, `rows 3`, `new_lines ''`. Instrucciones: «Una o dos frases que expliquen qué es el documento.»                                                                                                         |
| Archivo      | `file`     | Obligatorio, `return_format id`, `library uploadedTo`, `mime_types pdf,jpg,jpeg,png`, `max_size 10`. Instrucciones: «PDF o imagen, máximo 10 MB. Solo podrán abrirlo los deportistas activos a los que vaya dirigido.»               |
| Categoría    | `taxonomy` | `taxonomy sulayr_doc_category`, `field_type radio`, `add_term 0`, `save_terms 1`, `load_terms 1`, `return_format id`, `allow_null 1`. Solo se usa para los documentos generales; en el front, los personales van al grupo «Para ti». |
| Visible para | `radio`    | Obligatorio. `all` → «Todos los deportistas activos», `users` → «Deportistas concretos». Por defecto `all`. `layout horizontal`.                                                                                                     |
| Deportistas  | `user`     | `multiple 1`, `role [ 'athlete' ]`, `return_format id`. Lógica condicional: solo si «Visible para» es `users`. Instrucciones: «Solo estos deportistas verán el documento, y solo mientras estén activos.»                            |


El texto de ayuda de «Visible para» recuerda que «Todos» lo ve cualquier deportista activo. Así se evita publicar a todos un documento personal por despiste. El plugin no puede detectar por sí solo si un documento es personal.

### 8.4. Listado de administración


| Columna      | Contenido                                                                                                                                             |
| ------------ | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| Título       | Nativa                                                                                                                                                |
| Categoría    | Columna automática de la taxonomía                                                                                                                    |
| Visible para | «Todos» o «N deportistas» (con los nombres en el `title` si son pocos). «Sin destinatarios» en rojo si la audiencia es `users` y la lista está vacía. |
| Archivo      | Etiqueta del tipo y tamaño (`PDF · 1,2 MB`), o «Sin archivo» en rojo                                                                                  |
| Orden        | `menu_order`, ordenable                                                                                                                               |
| Fecha        | Nativa (publicado / programado / última modificación)                                                                                                 |


- Orden por defecto: `menu_order ASC`, luego `title ASC`.
- Filtro por categoría: desplegable propio. WordPress solo pinta el nativo para `category`. Usa los parámetros `taxonomy` + `term`, los mismos que los enlaces de la columna Categorías (la taxonomía no tiene query var).
- Se quita la acción de fila «Edit with WPBakery Page Builder»: el documento no tiene editor de contenido.
- Filtro por audiencia: desplegable «Todos / Personales» (`restrict_manage_posts` + `pre_get_posts`).
- Búsqueda: nativa, por título.
- Sin edición rápida de archivo. La edición rápida nativa permite cambiar título, estado, orden y fecha.
- Acción de fila «Ver archivo»: enlace a la URL de descarga controlada.

---



## 9. Frontend



### 9.1. Acceso

- Sección: `/mi-cuenta/documentos/`
- Descarga: `/mi-cuenta/documentos/{ID}/`
- Ítem de menú «Documentos», visible solo para deportistas activos, detrás de «Mis pagos».

Menú resultante: Escritorio, Perfil de deportista, Reservas de piscina, Mi monedero, Mis pagos, **Documentos**, Pedidos, Direcciones, Detalles de cuenta, Cerrar sesión.

### 9.2. Orden y agrupación

- Primer grupo: **«Para ti»**, con los documentos dirigidos al usuario (audiencia `users`), sea cual sea su categoría. Solo aparece si hay alguno.
- Después, los documentos generales agrupados por categoría. Grupos por nombre de categoría; «Otros documentos» al final para los que no tienen categoría.
- Dentro de cada grupo: `menu_order ASC`, después `post_date DESC`.
- Si no hay documentos personales y ningún general tiene categoría, la lista es plana, sin cabeceras.
- Si hay documentos personales y ningún general tiene categoría, los generales van bajo «Documentos del club», para separarlos de «Para ti» (decidido en D4, se mantiene en D5).



### 9.3. Estructura HTML aproximada

```html
<div class="sulayr-documents">
  <section class="sulayr-documents-group sulayr-documents-group--personal" aria-labelledby="doc-personal">
    <h3 class="sulayr-documents-group-title" id="doc-personal">Para ti</h3>
    <ul class="sulayr-documents-list"> … </ul>
  </section>
  <section class="sulayr-documents-group" aria-labelledby="doc-cat-12">
    <h3 class="sulayr-documents-group-title" id="doc-cat-12">Normativa</h3>
    <ul class="sulayr-documents-list">
      <li class="sulayr-document">
        <span class="sulayr-document-type sulayr-document-type--pdf" aria-hidden="true">PDF</span>
        <div class="sulayr-document-body">
          <h4 class="sulayr-document-title">Reglamento interno 2026</h4>
          <p class="sulayr-document-description">Normas de funcionamiento del club aprobadas en asamblea.</p>
          <p class="sulayr-document-meta">PDF · 340 KB · Actualizado el 12/10/2026</p>
        </div>
        <a class="sulayr-document-action button" href="/mi-cuenta/documentos/123/"
           target="_blank" rel="noopener">
          Abrir <span class="screen-reader-text">Reglamento interno 2026 (se abre en una pestaña nueva)</span>
        </a>
      </li>
    </ul>
  </section>
</div>
```



### 9.4. Acciones por tipo

Con D5 y D6 solo hay PDF e imágenes, y todos se abren igual.


| Tipo              | Botón   | Comportamiento                                                     |
| ----------------- | ------- | ------------------------------------------------------------------ |
| PDF               | «Abrir» | `inline`, pestaña nueva. El visor del navegador permite descargar. |
| Imagen (jpg, png) | «Abrir» | `inline`, pestaña nueva                                            |




### 9.5. Estados


| Estado                                           | Contenido                                                                                                         |
| ------------------------------------------------ | ----------------------------------------------------------------------------------------------------------------- |
| Vacío                                            | «Todavía no hay documentos disponibles.» Mismo estilo que `.sulayr-wallet-empty`.                                 |
| Error de descarga (404)                          | Redirección a `/mi-cuenta/documentos/` con un aviso de WooCommerce: «Este documento ya no está disponible.»       |
| Sin permiso (403), incluido deportista no activo | Redirección a Mi cuenta con el aviso «Los documentos del club solo están disponibles para deportistas en activo.» |
| Documento de otro deportista                     | Igual que 404: «Este documento ya no está disponible.»                                                            |


Se usan avisos de WooCommerce (`wc_add_notice`) en lugar de páginas de error sueltas. Es el sistema de avisos que ya muestra Mi cuenta.

### 9.6. Responsive

- Escritorio: fila con etiqueta de tipo, texto y botón a la derecha.
- Menos de 576 px: la etiqueta y el texto arriba, el botón a ancho completo debajo. Área táctil de 44 px como mínimo.
- Sin tablas, para que no haga falta desplazamiento horizontal (a diferencia del monedero).



### 9.7. Listas grandes

Con el volumen esperado (decenas de documentos) basta con la agrupación por categoría, sin paginación. Si se superan unos 50 documentos publicados, el siguiente paso es un buscador en el cliente sobre la lista ya pintada (JS mínimo, sin AJAX), y después un filtro por categoría. No entra en v1.

---



## 10. Evolución futura


| Funcionalidad                                                                                           | Cómo encaja                                                                                                                                                                                                                      | Esfuerzo   | ¿v1?                               |
| ------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- | ---------------------------------- |
| Categorías                                                                                              | Taxonomía incluida                                                                                                                                                                                                               | —          | **Sí** (fase 5, se puede posponer) |
| Orden manual                                                                                            | `menu_order` incluido                                                                                                                                                                                                            | —          | **Sí**                             |
| Fecha de publicación / programación                                                                     | Nativo                                                                                                                                                                                                                           | —          | **Sí**                             |
| Estado activo / inactivo                                                                                | Borrador nativo                                                                                                                                                                                                                  | —          | **Sí**                             |
| Orden manual de categorías                                                                              | Meta de término `sulayr_order`                                                                                                                                                                                                   | Bajo       | No                                 |
| Búsqueda y filtros en el front                                                                          | JS en el cliente sobre la lista                                                                                                                                                                                                  | Bajo       | No                                 |
| Documentos para deportistas concretos                                                                   | Audiencia `users` + filas `_sulayr_document_user`                                                                                                                                                                                | —          | **Sí** (D3)                        |
| Solo deportistas activos                                                                                | Regla en `DocumentService::user_can_view()`                                                                                                                                                                                      | —          | **Sí** (D2)                        |
| Visibilidad por colectivo (tarifa, natación, directiva)                                                 | Nuevo valor de «Visible para» (p. ej. `group`) + regla en `DocumentService::user_can_view()`. Los colectivos salen de campos que ya existen (`sulayr_user_membership_plan`, `sulayr_user_swimming`, `sulayr_user_is_directive`). | Bajo-medio | No                                 |
| Excepción de estado para documentos personales (p. ej. que un deportista de baja reciba su certificado) | Condición en `user_can_view()`: `users` ignora el estado                                                                                                                                                                         | Bajo       | No (D3b lo descarta en v1)         |
| Registro de accesos a documentos personales                                                             | Tabla propia alimentada por `sulayr_document_downloaded`                                                                                                                                                                         | Medio      | No                                 |
| Caducidad                                                                                               | Campo ACF `date_time_picker` + condición en la consulta. Opcional: cron que pase a borrador.                                                                                                                                     | Bajo       | No                                 |
| Versionado                                                                                              | Conservar adjuntos anteriores en un repeater de solo lectura o en meta. Depende de D7.                                                                                                                                           | Medio      | No                                 |
| Documentos obligatorios + confirmación de lectura                                                       | Tabla propia `sulayr_document_reads` (`document_id`, `user_id`, `read_at`) alimentada por `sulayr_document_downloaded`. Una tabla, no postmeta, porque crece con documentos × deportistas.                                       | Medio-alto | No                                 |
| Histórico de descargas                                                                                  | La misma tabla                                                                                                                                                                                                                   | Medio      | No                                 |
| Notificaciones al publicar                                                                              | `transition_post_status` → correo con las plantillas de `templates/email/` y `EmailConfig`                                                                                                                                       | Medio      | No                                 |
| Gestor de documentos no administrador                                                                   | Rol o capacidades sobre otro rol. Ya preparado por el mapa de capacidades (D1).                                                                                                                                                  | Bajo       | No                                 |


Ninguna de estas evoluciones obliga a cambiar el modelo de v1. Todas se apoyan en el ID del documento, la regla de visibilidad centralizada en `DocumentService` o los dos hooks propios.

---



## 11. Propuesta final



### 11.1. Arquitectura elegida

CPT `sulayr_document` con campos ACF locales y taxonomía `sulayr_doc_category`, en el plugin del club. Archivos (PDF e imágenes, máximo 10 MB) en `uploads/sulayr-documentos/`, bloqueado por `.htaccess` en Apache y servido por PHP desde el endpoint `documentos` de Mi cuenta. Cada descarga comprueba la capacidad `sulayr_view_documents`, que el deportista esté activo y que sea destinatario del documento.

### 11.2. Modelo de datos

`post_title` (título), ACF `sulayr_document_description` (descripción), ACF `sulayr_document_file` (ID del adjunto privado), taxonomía `sulayr_doc_category` (una categoría), ACF `sulayr_document_audience` (`all` / `users`), ACF `sulayr_document_users` (destinatarios, para editar) + meta `_sulayr_document_user` (una fila por destinatario, para consultar), `menu_order` (orden), `post_status` (estado), `post_date` / `post_modified` (fechas), meta `_sulayr_document_file_updated` (cambio de archivo), meta del adjunto `_sulayr_private_document`.

### 11.3. Estructura de código

```
src/
├── Infrastructure/CustomFields.php                 (+ constantes DOCUMENT_*)
├── Modules/
│   ├── PostTypes/DocumentPostType.php
│   ├── Taxonomies/DocumentCategoryTaxonomy.php
│   ├── Fields/DocumentFields/DocumentFields.php
│   └── Documents/
│       ├── DocumentCapabilities.php
│       ├── DocumentStorage.php
│       ├── DocumentsAccountEndpoint.php
│       └── DocumentAdminList.php
├── Services/DocumentService.php
└── App/App.php                                     (+ registro)
templates/parts/content-documents.php
public/css/styles.css                               (+ .sulayr-documents-*)
```



### 11.4. Permisos

- Gestión: solo administrador, mediante capacidades `*_sulayr_documents`.
- Lectura: `sulayr_view_documents` para `athlete` y `administrator`, y además deportista en estado activo y destinatario del documento (todos o en la lista).
- Sincronización versionada de capacidades (`sulayr_documents_caps_version`).
- Archivo físico inaccesible por URL directa. Descarga solo por `/mi-cuenta/documentos/{ID}/`.



### 11.5. Flujo de administración

1. Documentos → Añadir documento.
2. Título, descripción, archivo (se sube al directorio privado), categoría y «Visible para» (todos o deportistas concretos, con su selección). Orden opcional.
3. Publicar, guardar como borrador o programar.
4. Para cambiar el archivo, se elimina el actual en el campo y se sube otro. El anterior se borra al guardar.
5. Papelera para retirar. Borrado definitivo para eliminar también el archivo.



### 11.6. Flujo del deportista

1. Inicia sesión y entra en Mi cuenta. Si no está activo, no ve «Documentos».
2. Pulsa «Documentos».
3. Ve primero «Para ti» (si tiene documentos personales) y después los generales por categoría, con descripción, tipo, tamaño y fecha.
4. Pulsa «Abrir»: el PDF o la imagen se abre en una pestaña nueva.
5. El plugin comprueba sesión, capacidad, estado del deportista, estado del documento y destinatario, y envía el archivo.



### 11.7. Fases de implementación

Cada fase se prueba con `wp eval-file` y, donde hay HTTP de por medio, con `curl` usando la cookie de un deportista de prueba. No hay PHPUnit en el proyecto.


| Fase | Contenido                                                                                                                                        | Comprobación                                                                                                                                                                                                                                                                                                                                                                                                                                        |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| D0   | Decisiones D1–D8                                                                                                                                 | Hecha (sección 12)                                                                                                                                                                                                                                                                                                                                                                                                                                  |
| D1   | `DocumentPostType` + `DocumentCapabilities` + constantes                                                                                         | El CPT existe. El administrador ve el menú. Un editor y un deportista no. Capacidades presentes y sincronización idempotente. No hay `flush_rewrite_rules()` en cada carga.                                                                                                                                                                                                                                                                         |
| D2   | `DocumentFields` (incluidos «Visible para» y destinatarios) + `DocumentStorage` (directorio privado, `.htaccess`, marca del adjunto, validación) | Una subida desde el documento termina en `uploads/sulayr-documentos/`. `curl` a la URL física devuelve 403. Elegir un archivo público existente se rechaza. Un tipo distinto de PDF/imagen o más de 10 MB se rechaza. Una subida de otra parte de la web no cambia de directorio. Audiencia `users` sin deportistas no se guarda. Las filas `_sulayr_document_user` coinciden con el campo tras crear, editar, cambiar a `all` y borrar un usuario. |
| D3   | `DocumentService` + descarga controlada en `template_redirect`                                                                                   | Matriz de acceso: sin sesión, cliente, deportista activo destinatario, deportista activo no destinatario, deportista no activo destinatario (`standby`, `baja`, `moroso`), deportista sin estado, administrador × documento general, personal, borrador, papelera, sin archivo. Personal ajeno da 404, no 403. Cabeceras `inline` correctas.                                                                                                        |
| D4   | Sección «Documentos» en Mi cuenta: menú, título, plantilla con grupo «Para ti», estilos, estado vacío, avisos                                    | Menú en la posición prevista, también con el enlace de reservas presente, y oculto para no activos. «Para ti» solo con documentos personales. Un deportista no ve títulos de documentos personales ajenos. Lista con y sin documentos. Vista en móvil. Regla de reescritura regenerada en el despliegue.                                                                                                                                            |
| D5   | `DocumentCategoryTaxonomy` + campo de categoría + agrupación en el front                                                                         | Una sola categoría por documento. Agrupación y grupo «Otros documentos». Lista plana si no hay categorías.                                                                                                                                                                                                                                                                                                                                          |
| D6   | Ciclo de vida del archivo + `DocumentAdminList`                                                                                                  | La sustitución borra el archivo anterior y actualiza la fecha. El borrado definitivo elimina el adjunto. Columnas, orden por defecto y aviso «Sin archivo».                                                                                                                                                                                                                                                                                         |
| D7   | Aceptación y documentación                                                                                                                       | Revisión completa del flujo de administración y del deportista. Breve guía de uso para el administrador. En producción: `.htaccess` efectivo (URL física → 403) y `upload_max_filesize` de 10M o más.                                                                                                                                                                                                                                               |


Las fases D1 a D7 están hechas y en `main`. El modelo no cambia.

---



### 11.8. Estado de las fases


| Fase | Estado                                                | Notas                                                                            |
| ---- | ----------------------------------------------------- | -------------------------------------------------------------------------------- |
| D1   | Hecha. En `main` y en producción (`cfed7450`).        | Ver abajo                                                                        |
| D2   | Hecha. En `main` y en producción (`cfed7450`).        | Ver abajo. El campo de categoría pasa a D5, con la taxonomía.                    |
| D3   | Hecha. En `main` y en producción (`cfed7450`).        | Ver abajo                                                                        |
| D4   | Hecha. En `main` y en producción (`cfed7450`).        | Ver abajo. Primera versión utilizable (MVP).                                     |
| D5   | Hecha. En `main` (`deb5398e`).                        | Ver abajo                                                                        |
| D6   | Hecha. En `main` (`deb5398e`).                        | Ver abajo                                                                        |
| D7   | Hecha. En `main` (`deb5398e`).                        | Ver abajo. Guía: `wp-content/plugins/club-triatlon-sulayr/MANUAL-DOCUMENTOS.md`. El límite de subida en producción sigue sin comprobar. |


**D1.** Archivos: `src/Modules/PostTypes/DocumentPostType.php`, `src/Modules/Documents/DocumentCapabilities.php`, constantes `DOCUMENT_`* en `Infrastructure/CustomFields.php` y registro en `App::load_classes()`.

Comprobado con un script `wp eval-file` y con peticiones HTTP autenticadas:

- El CPT existe en el blog 1 con los argumentos de 8.1: no público, sin REST (`/wp-json/wp/v2/sulayr_document` → 404), sin reescritura, etiquetas en español y `menu_position` 58.
- Las capacidades primitivas son `*_sulayr_documents`. El administrador las tiene todas, además de `sulayr_view_documents`. `athlete` solo tiene `read` y `sulayr_view_documents`. Editor, autor, colaborador, suscriptor, cliente y gestor de tienda no tienen ninguna.
- `edit_post` / `delete_post` sobre un documento: sí para el administrador, no para deportista ni editor.
- Sincronizar dos veces no cambia los roles. Se retira una capacidad de documentos sobrante en `athlete` y no se toca un rol no gestionado. Una capacidad borrada a mano se restaura aunque la versión coincida.
- Un documento sin título que se publica o se programa queda en borrador, con aviso en la pantalla de edición. Con título se publica.
- `rewrite_rules` no cambia entre cargas: el CPT no vacía las reglas en cada petición.
- HTTP: el administrador abre el listado y «Añadir documento» (200, menú y textos en español). Un deportista recibe 403 de WordPress en esas pantallas, igual que ya ocurre con el listado del monedero. `UserService` solo redirige `/wp-admin/` a Mi cuenta.
- Los documentos y el usuario editor de prueba se borraron al terminar.

No probado en esta fase: el aviso de falta de título en la pantalla de edición (redirección con `sulayr_document_notice`). Solo se probó que el documento queda en borrador. Falta guardarlo sin título desde el navegador.

**D2.** Archivos: `src/Modules/Fields/DocumentFields/DocumentFields.php`, `src/Modules/Documents/DocumentStorage.php` y registro en `App::load_classes()`.

Cómo funciona:

- El grupo ACF se registra en `acf/init`, no en `plugins_loaded`. Así sus textos no disparan el aviso de traducción cargada antes de tiempo, que sí dan los grupos existentes.
- La subida se desvía al directorio privado en `acf/upload_prefilter/name=sulayr_document_file`, solo si ACF no ha rechazado el archivo. El desvío termina en el filtro `wp_handle_upload`. El directorio, el `.htaccess` y el `index.html` se crean en la primera subida y se comprueban en cada carga del escritorio de quien gestiona documentos.
- El directorio no tiene subcarpetas por año y mes. Los nombres repetidos los resuelve `wp_unique_filename()`.
- El índice de destinatarios se reescribe en `acf/save_post` (prioridad 20). Al guardar se descartan los usuarios que no existen o no tienen rol `athlete` y los duplicados. Con audiencia `all`, el campo y el índice se vacían.

Comprobado con subidas HTTP reales a `async-upload.php` (el mismo camino que el modal de medios), un script `wp eval-file` y tres envíos reales del formulario de edición:

- PDF y PNG desde el campo: se guardan en `uploads/sulayr-documentos/`, marcados y asociados al documento. La URL directa, el directorio y el `.htaccess` devuelven 403.
- Se rechazan: un `.pdf` que no es PDF (WordPress), un `.docx` (tipo no permitido) y un PDF de 11 MB (tamaño).
- Una subida normal sin el campo sigue yendo a `uploads/AAAA/MM/` y se sirve con 200.
- Validación: archivo público, no numérico o vacío rechazado. Un adjunto marcado a mano fuera del directorio no cuenta como privado. Descripción de 301 caracteres rechazada. Audiencia desconocida o vacía rechazada. Audiencia `users` sin deportistas o solo con un editor rechazada.
- Índice: coincide con el campo, sin duplicados ni usuarios no válidos. `meta_query` con `=` encuentra el documento. Un usuario borrado sale del índice y del campo.
- `.htaccess` e `index.html` borrados a mano se recrean.
- Formulario real: publicar con un deportista escribe campo e índice. Guardar con un archivo de la biblioteca pública no cambia nada. Cambiar a «Todos» vacía campo e índice.
- Los documentos y adjuntos de prueba y los usuarios temporales se borraron. El directorio privado queda creado y protegido.

**Consecuencia para D3 (comportamiento de ACF):** en el servidor, ACF solo valida al publicar. Si la validación falla, no guarda los campos, pero el post queda publicado y WordPress muestra «Documento publicado». En el navegador lo impide la validación JavaScript de ACF. Los borradores y los programados no se validan en el servidor. Por eso la regla de acceso de D3 tiene que **fallar cerrada**:

- Audiencia vacía o desconocida → nadie lo ve (no se trata como `all`).
- Audiencia `users` sin filas en el índice → nadie lo ve.
- Archivo ausente o no privado → no se lista y la descarga da 404.

No probado en esta fase: la subida y la validación en el navegador (modal de medios, mensajes de ACF en pantalla). Las subidas se probaron con las mismas peticiones que hace el modal.

**D3.** Archivos: `src/Services/DocumentService.php`, `src/Modules/Documents/DocumentsAccountEndpoint.php` y registro en `App::load_classes()` (solo con WooCommerce activo).

Cómo funciona:

- `DocumentService::user_can_view()` es la única regla de acceso:
  - Quien tiene `edit_post` sobre el documento lo ve siempre, incluidos borradores y documentos en la papelera.
  - El resto necesita: documento publicado, acceso a la sección (`sulayr_view_documents` + estado activo o vacío), y audiencia `all`, o audiencia `users` y estar en el índice.
  - Cualquier otra audiencia: nadie. La regla pasa al final por el filtro `sulayr_document_user_can_view`.
- `get_file()` exige un adjunto privado (marca + ruta dentro del directorio), legible y con extensión `pdf`, `jpg`, `jpeg` o `png`. Si no, devuelve `null`.
- `get_visible_documents()` hace dos consultas (generales y personales del usuario), ordena por `menu_order ASC, date DESC` y descarta lo que no pase la regla o no tenga archivo válido.
- Endpoint `documentos` registrado con `woocommerce_get_query_vars`. Al desplegar, la opción `sulayr_documents_endpoint_version` marca `sulayr_wc_flush_rewrite_rules`, y el vaciado de reglas que ya hace `WooCommerceIntegration` (init, 999) regenera las reglas una vez. No hace falta guardar los enlaces permanentes a mano.
- Descarga en `template_redirect` (prioridad 2), solo en la página de Mi cuenta y con un ID numérico:
  - Sin sesión: redirección a Mi cuenta (formulario de acceso).
  - Sin acceso a la sección (cliente, no activo): redirección a Mi cuenta con el aviso de 9.5.
  - Documento inexistente, de otro tipo, no publicado, ajeno o sin archivo válido: redirección a la sección con «Este documento ya no está disponible.», la misma respuesta en todos los casos.
  - Si el usuario podía verlo pero falta el archivo, se escribe `[sulayr-documentos] El documento N no tiene un archivo privado válido.` en `debug.log`.
- Envío:
  - Cabeceras: `nocache_headers()`, `Content-Type` del tipo permitido, `Content-Length`, `Content-Disposition: inline` con el título en ASCII (`Reglamento-interno-2026-version-n.pdf`), `X-Content-Type-Options: nosniff` y `X-Robots-Tag: noindex, nofollow`.
  - Se lee por bloques de 1 MB. Una petición `HEAD` recibe solo las cabeceras. Antes de enviar se dispara `sulayr_document_downloaded`.
  - Sin soporte de `Range`: los PDF se abren igual en el navegador, pero no se descargan por partes.



Comprobado:

- Matriz HTTP de 99 casos: sin sesión, cliente, deportistas en `baja`, `standby` y `moroso`, deportista activo destinatario, deportista sin estado, deportista activo no destinatario y administrador. Contra cada uno: documento general (PDF), imagen, personal, borrador, papelera, sin archivo, archivo público, sin audiencia, lista vacía, ID inexistente y una página. Los 99 casos dieron el resultado esperado. En las descargas se comprobaron el `Content-Type` y que el cuerpo es idéntico al archivo.
- Script `wp eval-file`, 18 comprobaciones:
  - Estados: el vacío cuenta como activo; `baja`, `standby` y `moroso` no.
  - Listas por usuario: un deportista de `baja` destinatario no ve nada.
  - Exclusión de borradores, papelera, documentos sin archivo, con archivo público, sin audiencia y con lista vacía.
  - Datos de presentación y nombre de descarga.
  - Fallo cerrado con una audiencia desconocida.
  - Un archivo privado con extensión no permitida no se sirve.
  - El filtro de ampliación se aplica.
- Avisos tras la redirección: comprobados siguiendo la redirección con sesión de WooCommerce. `HEAD` de una imagen: 200 con `Content-Type` y `Content-Length`, sin cuerpo.
- Sin regresiones: `/mi-cuenta/documentos/`, `/mi-cuenta/documentos/abc/`, `mi-monedero` y `perfil-deportista` responden 200.
- Usuarios, documentos y archivos de prueba borrados al terminar. En `debug.log` quedan 8 líneas `[sulayr-documentos]` de las pruebas (documentos 2539 y 2540, ya borrados).

Cambio respecto a 6.5: sin sesión no se usa `auth_redirect()` (llevaría a `wp-login.php`), sino Mi cuenta, que ya muestra el formulario de acceso de WooCommerce. Los 403 y 404 de 6.5 se materializan como redirecciones con aviso (9.5), no como páginas de error.

**D4.** Archivos: `DocumentsAccountEndpoint` (menú, título, protección y contenido de la sección), `templates/parts/content-documents.php`, estilos `.sulayr-documents`* en `public/css/styles.css` y `date_is_update` en `DocumentService::present()`.

Cómo funciona:

- El ítem «Documentos» va en `woocommerce_account_menu_items` (prioridad 15), detrás del primero que exista de `mis-pagos`, `mi-monedero` o `dashboard`.
- Solo lo ven usuarios con rol `athlete` y acceso a la sección. El administrador no ve el ítem, igual que con las demás secciones, pero puede abrir la URL.
- `guard_section()` (`template_redirect`, prioridad 3) redirige a Mi cuenta, con el aviso de 9.5, a quien tiene sesión pero no tiene acceso. Sin sesión, WooCommerce muestra el formulario de acceso.
- Plantilla: grupo «Para ti» si hay documentos personales; después, los generales. Hasta D5 los generales son un único grupo sin título. Si además hay personales, ese grupo se titula **«Documentos del club»**, para separarlo de «Para ti». Este título no estaba en la sección 9 y se revisará en D5.
- Cada documento muestra la etiqueta de tipo (PDF/IMG, sin fuente de iconos), el título, la descripción y la línea «tipo · tamaño · Publicado el / Actualizado el …». La fecha usa el formato de fecha del sitio. Los tamaños por debajo de 1 KB van sin decimales.
- Botón «Abrir» con `target="_blank" rel="noopener"` y texto para lectores de pantalla. Todo lo que se imprime pasa por `esc_html`/`esc_attr`/`esc_url`.
- CSS:
  - Todo bajo `.sulayr-documents`, porque `.entry-content p` y las listas de Astra tienen más especificidad que una clase sola.
  - Usa las variables `--sulayr-profile-*` del plugin, no el Bento del tema.
  - Por debajo de 576 px, el botón ocupa todo el ancho y tiene 44 px de alto como mínimo.

Comprobado:

- HTTP, 21 comprobaciones:
  - Menú: deportista activo y sin estado ven «Documentos» justo detrás de «Mis pagos». `baja`, cliente y administrador no lo ven.
  - Acceso a la sección: `baja` y cliente son redirigidos. Sin sesión se muestra el formulario de acceso.
  - Página del destinatario activo: título «Documentos», «Para ti» antes de «Documentos del club», ningún documento excluido y el orden manual (`menu_order -1` primero).
  - Escapado: un título con `<script>` y una descripción con `<b>` se muestran como texto.
  - Tipo, tamaño y fechas correctos. Enlaces con pestaña nueva y texto accesible.
  - Un activo no destinatario ve la lista plana sin cabecera y sin el documento personal. El administrador ve la sección.
- Menú completo de un deportista real con natación (solo lectura): Escritorio > Perfil de deportista > Reservas de piscina > Mi monedero > Mis pagos > **Documentos** > Pedidos > Direcciones > Detalles de cuenta > Cerrar sesión. Coincide con 9.1.
- Estado vacío: «Todavía no hay documentos disponibles.»
- Capturas con Chromium sin interfaz a 1280 px y 390 px: lista, estado vacío y móvil sin desbordamiento. La primera ronda mostró espacios y sangrías del tema, corregidos con el ámbito `.sulayr-documents`. Con `google-chrome --headless=new` a 390 px el contenido salía cortado, también en «Mi monedero». Era un defecto de la herramienta: con `headless_shell` se ve bien.
- Finales de línea: los archivos del plugin usan CRLF (los 62 que están en git). Los archivos nuevos y los modificados quedan en CRLF, y el diff de `styles.css` solo añade líneas.
- Usuarios, documentos y archivos de prueba borrados. Sin entradas nuevas en `debug.log` relacionadas con documentos.

No probado: la navegación real con un lector de pantalla y los navegadores móviles reales. Las capturas son de Chromium.

**D5.** Archivos: `src/Modules/Taxonomies/DocumentCategoryTaxonomy.php` (nuevo), campo de categoría en `DocumentFields`, `category` y `group_by_category()` en `DocumentService`, agrupación en `DocumentsAccountEndpoint::render_section()` y registro en `App::load_classes()`.

Cómo funciona:

- Taxonomía `sulayr_doc_category`, registrada para `sulayr_document` con los argumentos de 8.2. Sobrescribe `add_custom_taxonomy()` (la base no admite `meta_box_cb` ni `rewrite => false`) y las etiquetas, en español.
- Capacidades de la taxonomía: gestionar, editar y borrar términos con `edit_others_sulayr_documents`; asignar con `edit_sulayr_documents`. Solo el administrador.
- `show_in_quick_edit => false`: la edición rápida y en bloque usaría casillas y permitiría varias categorías.
- Campo ACF «Categoría» (radio, opcional, sin crear términos, `save_terms` y `load_terms`) entre «Archivo» y «Visible para».
- `present()` añade `category` (`id`, `name`) o `null`. Si un documento tuviera varias por error, se usa la primera por nombre.
- `group_by_category()`: grupos por nombre de categoría, sin distinguir mayúsculas ni tildes; «Otros documentos» al final; un único grupo sin título si ninguno tiene categoría. Dentro de cada grupo se mantiene el orden de la consulta (`menu_order`, fecha).

**D6.** Archivos: ciclo de vida en `DocumentStorage`, `src/Modules/Documents/DocumentAdminList.php` (nuevo) y registro en `App::load_classes()` (solo en `is_admin()`).

Cómo funciona:

- `acf/save_post` (prioridad 5, antes de que ACF guarde) anota el archivo actual. En la prioridad 20, si ha cambiado, borra el anterior y, si hay archivo nuevo, escribe `_sulayr_document_file_updated`. Después borra los adjuntos privados subidos a ese documento que no son su archivo actual.
- `before_delete_post`: al borrar definitivamente un documento se borran su archivo y los adjuntos privados subidos a él. La papelera no los toca.
- Un adjunto solo se borra si es privado (marca y ruta) y ningún otro documento, en cualquier estado, lo usa como archivo.
- Si la validación de ACF falla, ACF no guarda y no se borra nada.
- Listado: columnas, avisos, orden por defecto, filtros y «Ver archivo» de 8.4. Además, la columna Visible para muestra el nombre si hay un solo deportista y «Sin definir» si falta la audiencia.

Comprobado:

- Script `wp eval-file`, 34 comprobaciones:
  - Taxonomía: argumentos, capacidades (administrador sí; editor y deportista no), orden de los campos y configuración del campo.
  - El campo escribe y lee el término; cambiar de categoría deja una sola; vaciarlo borra el término.
  - Agrupación: «Árbitros», «calendario», «Normativa» en ese orden, orden manual dentro, «Otros documentos» al final, personales solo en «Para ti», categoría vacía sin grupo, lista plana sin categorías, dos categorías por error.
  - Ciclo de vida: primer archivo sin fecha; guardar sin cambios no toca nada pero borra lo subido y no usado; la sustitución borra el adjunto y el archivo en disco y pone la fecha, que el front muestra como «Actualizado el»; un archivo usado por otro documento en la papelera no se borra; un adjunto público nunca se borra; vaciar el archivo borra el anterior sin fecha; papelera y restaurar conservan el archivo; el borrado definitivo lo elimina; borrar una página no toca sus adjuntos.
- Formulario real: subida por `async-upload.php` con `_acfuploader` (como el modal) y envío de `post.php`. El archivo anterior y una subida descartada desaparecen del disco y de la base de datos, la fecha cambia, y un segundo guardado sin cambios no toca nada.
- HTTP, 40 comprobaciones: columnas y su orden, valores y avisos de cada columna, orden por defecto y por Orden, los dos filtros (incluido un valor no válido y «Todas las categorías»), «Ver archivo» solo con archivo, sin enlace de WPBakery, otras pantallas sin cambios, pantalla de edición sin caja nativa y con el campo, «Documentos → Categorías» (administrador 200, editor 403), sin REST, y la sección del deportista con los grupos en orden y aria-labelledby.
- Capturas del listado y de la sección.
- Datos de prueba borrados. Se borró también un borrador automático de las pruebas de D1.



Hallazgo durante las pruebas (sin cambio de código): el campo «Deportistas» es obligatorio con lógica condicional. Con «Todos», el JavaScript de ACF lo desactiva y no se envía. Un envío sin JavaScript que incluya ese campo vacío no supera la validación y ACF no guarda. Con un navegador normal no ocurre.

**D7.** Archivos: `wp-content/plugins/club-triatlon-sulayr/MANUAL-DOCUMENTOS.md` (guía del administrador).

Comprobado en producción el 22/10/2026, desde fuera y sin sesión:

- `uploads/sulayr-documentos/index.html`, `.htaccess` y un PDF inexistente: 403. Un archivo inexistente en `uploads/2025/08/`: 404. Una imagen pública: 200. El 403 lo pone la regla del directorio.
- `/mi-cuenta/documentos/`: 200, y una ruta inventada de Mi cuenta da 404, así que la regla de reescritura del endpoint está activa. `/mi-cuenta/documentos/999999/` sin sesión redirige a Mi cuenta.

Pendiente: comprobar `upload_max_filesize` y `post_max_size` (10 MB o más) en **Medios → Añadir nuevo** del escritorio de producción. Desde fuera no se puede ver.

No probado: la interfaz del modal de medios, la lógica condicional y los mensajes de ACF en un navegador real con JavaScript. Las pruebas usan las mismas peticiones que hace el navegador.

## 12. Decisiones tomadas


| ID  | Pregunta                                                               | Decisión                                                                                                                                              |
| --- | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------- |
| D1  | ¿Quién gestiona documentos?                                            | Solo administradores                                                                                                                                  |
| D2  | ¿Qué deportistas ven los documentos según su estado?                   | Solo los que están en estado `active` (vacío cuenta como activo). `standby`, `baja` y `moroso` no ven la sección.                                     |
| D3  | ¿Habrá documentos con datos personales o dirigidos a una sola persona? | Sí, desde la v1                                                                                                                                       |
| D3a | Opciones de visibilidad en v1                                          | «Todos los deportistas» o «Deportistas concretos». Los colectivos (tarifa, natación, directiva) quedan para después.                                  |
| D3b | ¿Ve un documento personal un deportista no activo?                     | No. Ningún deportista no activo ve documentos, tampoco los suyos. Consecuencia: este canal no sirve para entregar documentos a un deportista de baja. |
| D3c | Presentación de los documentos personales                              | Grupo «Para ti» al principio de la sección                                                                                                            |
| D4  | Servidor de producción                                                 | Apache con `.htaccess`. Se verifica en la fase D7.                                                                                                    |
| D5  | ¿Abrir en el navegador o descargar?                                    | Abrir en pestaña nueva                                                                                                                                |
| D6  | Tipos y tamaño                                                         | `pdf`, `jpg`, `jpeg`, `png`. Máximo 10 MB.                                                                                                            |
| D7  | Archivo anterior al sustituir                                          | Se borra. Sin versionado en v1.                                                                                                                       |
| D8  | Ubicación del código del endpoint                                      | Clase propia `DocumentsAccountEndpoint`                                                                                                               |




## 13. Hallazgos fuera de alcance

Se han visto durante el análisis. No forman parte de esta funcionalidad y no se corrigen aquí.

- `sulayr_subscription_changes` no se registra porque su nombre supera los 20 caracteres. El histórico de cambios de tarifa no está funcionando.
- `sulayr_wallet` usa las capacidades de `post`: editores y autores pueden crear y editar movimientos de monedero.
- La comprobación del nonce en la activación de suscripción está comentada (`WooCommerceIntegration::handle_subscription_activation_request`).
- Las plantillas usan iconos `bi bi-*`, pero la fuente de Bootstrap Icons no se encola.
- `PostType::add_custom_post_type()` vacía las reglas de reescritura en cada petición si alguna subclase no lo sobrescribe.

