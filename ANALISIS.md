# Análisis — adaptación al código existente

Este documento explica por qué la implementación se aparta de algunos nombres o mecanismos que el SPEC planteaba al principio. Los requisitos funcionales siguen en [SPEC.md](SPEC.md).

No se modifica el plugin del club. La integración se engancha desde `wp-content/plugins/sulayr-reservas/`.

## Sitios

`wp-config.php` define Multisite por subdominio. El blog principal es el `1`, dominio `clubtriatlonsulayr.local.com`.

El sitio de reservas se creó después del análisis inicial. Al cargar WordPress, `get_site(2)` devolvió `cnnazari.clubtriatlonsulayr.local.com`. Esos identificadores se guardan en opciones de red (`sulayr_reservas_club_blog_id` y `sulayr_reservas_bookings_blog_id`) para no repetirlos por el código.

`switch_to_blog()` cambia el contexto aunque el sitio no exista. Por eso `BlogContext` valida ambos blogs antes de cambiar y llama a `restore_current_blog()` en un `finally`.

`COOKIE_DOMAIN` no está en `wp-config.php`. En instalaciones por subdominio, el núcleo lo define como el dominio de la red con un punto delante (`wp-includes/ms-default-constants.php`), así que la sesión puede compartirse. Los roles siguen siendo por sitio.

## Rol `athlete`

El SPEC hablaba del rol `deportista`. En `AthleteRole.php` el slug es `athlete` y la etiqueta visible es «Deportista». No existe el slug `deportista`. Se aprobó usar `athlete` en el club y `nadador` en reservas.

`UserService::assign_athlete_role()` escucha `user_register` y hace `set_role('athlete')` solo si el usuario no tiene roles o es `subscriber`. Un alta que pase `role => nadador` a `wp_insert_user()` no entra en esa rama. Más adelante, asignar `nadador` con `add_role()` evita quitar `gestor` o `administrator` si coincidieran en la misma cuenta.

El mismo servicio redirige a cualquier `athlete` fuera de `wp-admin`. El bloqueo de `nadador` y `gestor` tendrá que limitarse al blog 2 para no interferir con esa redirección en el club.

## Datos personales en tabla local

`wp_usermeta` es global en la red. Escribir `first_name`, `sulayr_user_phone` o `sulayr_user_dni` desde reservas modificaría el perfil ACF del club. El email tampoco es un user meta: es `wp_users.user_email`.

Orígenes verificados en `CustomFields.php`, `UserFields.php` y `UserService::get_user_profile()`:

- Nombre: `first_name`.
- Apellidos: `last_name`.
- Email: `wp_users.user_email`.
- Teléfono: `sulayr_user_phone`.
- DNI: `sulayr_user_dni`.
- Natación: `sulayr_user_swimming`, select, valores `'1'` y `'0'`.
- Estado: `sulayr_user_athlete_status`, select, valores `active`, `standby`, `baja` y `moroso`.

La comparación debe ser estricta con `'1'` y `active`. `HelperService::format_swimming_supplement_to_bool()` acepta variantes (`yes`, `sí`, `true`) que el SPEC no admite.

Por eso la ficha del nadador —incluida la copia de esos cinco datos, el estado activo y el vínculo con el club— está en `sulayr_swim_swimmers`, tabla del blog 2. La sincronización lee el club y escribe solo esa tabla. No llama a `wp_update_user()` ni a `update_user_meta()` sobre las claves del club.

El DNI no va en `sulayr_swim_bookings`. El historial conserva nombre, apellidos, teléfono y email, y `user_id` puede quedar vacío. En InnoDB, varios `NULL` no chocan con la clave única `(session_id, user_id)`.

## Mes generado

La columna no se llama `year_month`. MySQL rechaza ese identificador porque `YEAR_MONTH` es una palabra reservada: el `CREATE TABLE` fallaba y la tabla de meses no llegaba a crearse. El identificador aprobado es `generated_month`, `CHAR(7)`, formato `YYYY-MM`, clave primaria de `sulayr_swim_months`.

## Guardado del perfil en el club

En `wp-admin/user-edit.php`, `edit_user_profile_update` se dispara antes de `edit_user()`. ACF 6.6.1 no guarda ahí. `includes/forms/form-user.php` escucha `profile_update` con prioridad 10 y, dentro, `_acf_do_save_post` persiste las metas.

`AthleteUpdate` puede revertir `sulayr_user_swimming` en `acf/save_post` con prioridad 20 si hay una suscripción activa y no se cumple el margen de cinco días. Enganchar la sincronización antes de esa prioridad leería un valor que luego se deshace.

La sincronización automática irá en `profile_update` con prioridad 30, solo en el blog 1 y solo si quien guarda tiene `manage_options`. No se ejecuta al cargar el perfil.

La importación CSV (`CsvImporterService`) crea usuarios con `wp_create_user()` y `update_field()`, sin `profile_update`. Esos deportistas no se sincronizan solos. Encaja con el SPEC: la primera incorporación es manual, al guardar el perfil o con el botón.

`remove_user_from_blog` y `set_user_role` no pasan por ese formulario. Harán falta para desactivar en reservas cuando el usuario deje el club o pierda el rol `athlete`, que es lo que piden el apartado 5.5 y el criterio 18.2.

## Entorno

- PHP en ejecución: 8.4.17. El plugin del club exige `>= 7.4` (`composer.json` y `WpDependencies.php`). El plugin de reservas declara `Requires PHP: 7.4` y no usa sintaxis exclusiva de PHP 8.
- WordPress 6.8.3. ACF PRO 6.6.1. El sistema de reservas no depende de ACF para funcionar; solo lee sus metas desde el club.
- El sitio de reservas usa Twenty Twenty-Five. Los paneles serán un shortcode con estilos propios, para no depender de ese tema ni del tema del club.

## Esquema ya instalado

Versión de base de datos `1`, opción `sulayr_reservas_db_version` en cada sitio. `dbDelta()` no marca la versión como instalada si falta alguna tabla prevista.

- Blog 2: `sulayr_swim_sessions`, `sulayr_swim_bookings`, `sulayr_swim_months`, `sulayr_swim_swimmers`.
- Blog 1: `sulayr_swim_sync_log`.
