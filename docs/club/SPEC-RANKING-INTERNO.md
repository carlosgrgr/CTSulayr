# SPEC — Ranking interno del club

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr`
**Estado:** Definida el 16 de noviembre de 2026. Decisiones R1 a R38 tomadas con el club ese día (sección 1.3); R26 a R32 salen de los ejemplos de clasificaciones de la Federación Andaluza (`docs/club/ejemplos-clasificaciones/`). No quedan preguntas abiertas (sección 12). Implementada el 16 de noviembre de 2026 en la rama `ranking-interno` (pasos R0 a R6, sección 13): 47 tests PHPUnit y 42 comprobaciones con WordPress en verde. Mergeada en `main` (`7fb03aba1`) y subida a PROD el 16 de noviembre de 2026. Configuración hecha (ajustes, sexos y rol de gestor). Faltan los criterios de PROD. Quedan las pruebas manuales de [ranking-interno/pruebas.md](ranking-interno/pruebas.md) y el registro del despliegue en [ranking-interno/despliegue-prod.md](ranking-interno/despliegue-prod.md). **Ampliación (16 de noviembre de 2026):** importación de resultados desde JSON (decisiones R39 a R41, sección 14), implementada ese día en la rama `ranking-importar`, probada en local con la competición de Úbeda (72 tests PHPUnit) y mergeada en `main` el mismo día. La skill `ranking-importar` es autónoma y está instalada en la app de escritorio de Claude desde ese día. Subida a PROD el 16 de noviembre de 2026; Úbeda se está importando y quedan los criterios 11 a 14 en PROD (ver [ranking-interno/despliegue-prod.md](ranking-interno/despliegue-prod.md), «Ampliación»). El mismo día se corrigieron los estilos del ranking en el móvil (sección 8.4), pendientes de subir.
**Fecha:** 16 de noviembre de 2026
**Resumen para la junta:** [resumen-ranking-interno.html](resumen-ranking-interno.html), sin la parte técnica (16 de noviembre de 2026): puntuación, qué ven los socios y cómo se meten las competiciones (los tiempos se sacan con IA de los PDF de la federación). Si cambia el baremo o el flujo, hay que actualizarlo.

Después de cada competición, un gestor de ranking introduce en la web del club (blog 1) los resultados de los socios que participaron. La web calcula los puntos de cada resultado y publica, en Mi cuenta, una clasificación interna de la temporada: absoluta por sexo y por grupo de edad, con una ficha por deportista y otra por competición.

Esta spec no modifica [SPEC-RESERVAS.md](../reservas/SPEC-RESERVAS.md) ni el plugin `sulayr-reservas`. Amplía la ficha del socio de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) con un dato nuevo, el sexo (sección 2.1). No cambia ninguna decisión de esa spec.

---

## 1. Estado actual del plugin y decisiones de encaje

### 1.1. Lo que hay en el plugin

Comprobado en local el 16 de noviembre de 2026 sobre `main` (`1c03c2634`).

- **Arranque.** `club-triatlon-sulayr.php` define las constantes `SULAYR_*` (`SULAYR_TEXTDOMAIN = 'club-triatlon-sulayr'`, `SULAYR_TEMPLATE_DIR`, `SULAYR_PUBLIC_ASSETS`…) y crea `App\App`. `App` comprueba ACF PRO (`WpDependencies`). En `plugins_loaded` instancia las clases; cada clase registra sus hooks en el constructor. El módulo de socios se agrupa en `App::load_members()`.
- **Organización.** Composer con PSR-4, `ClubTriatlonSulayr\` → `src/`, y `vendor/` en el repositorio (dompdf). `composer.json` fija `config.platform.php` a 7.4.33. El código nuevo está en `src/Modules/<Módulo>/`; el de socios, en `src/Modules/Members/` con subcarpetas `Admin/`, `Database/`, `Join/`, `Lifecycle/`, `Receipts/` y `Catalog/`.
- **Tablas propias.** `Members\Database\MembersSchema`: constantes con el nombre de cada tabla, `table()` con `$wpdb->prefix`, `dbDelta` en `init` (prioridad 5) cuando cambia `VERSION` (opción `sulayr_members_db_version`, ahora 4). Los historiales son filas que no se borran.
- **Registro de cambios.** `Members\MemberLog` (tabla `sulayr_member_log`): solo inserta. Siempre va ligado a un socio (`user_id` obligatorio). Escucha cambios de metas de usuario de una lista fija (`TRACKED_META`) y de roles. Admite una nota común con `with_note()`.
- **CPT y taxonomías.** `sulayr_wallet` (monedero), `sulayr_document` y su taxonomía `sulayr_doc_category`, y `sulayr_service` (catálogo de servicios).
- **Roles y capacidades.** El rol `athlete` existe solo en blog 1. `Members\MemberRoles` añade `sulayr_member_manager` y `sulayr_shop_manager` encima de `athlete`. Las capacidades (`sulayr_manage_members`, `sulayr_view_member_log`, `sulayr_manage_shop`) se sincronizan con `add_cap()`/`remove_cap()` cuando cambia una versión. `Admin\ManagerAccess` pinta las casillas de los roles adicionales en la ficha de usuario (solo para administradores), y `MemberRoles::can_use_admin()` evita que WooCommerce eche de wp-admin a los gestores. Los gestores no tienen `edit_users`: la ficha del socio de «Socios» es de solo lectura y los datos se cambian desde pantallas propias.
- **Pantallas de administración.** Menú propio (`add_menu_page` / `add_submenu_page`) protegido por capacidad. Los formularios envían a `admin-post.php` (`admin_post_<acción>`) con `check_admin_referer()` y `current_user_can()`, y vuelven con un aviso guardado en un transient por usuario (`RequestsPage::set_notice()`). Si hay errores, el estado del formulario se guarda en otro transient (`STATE_TRANSIENT`). Hay un botón «Ejecutar ahora» para tareas que en local se lanzarían con WP-CLI.
- **Ajustes.** `Members\MemberSettings`: una página de opciones ACF (`post_id` `sulayr_members`, opciones `sulayr_members_<campo>`) con pestañas. `get()` devuelve un valor por defecto si el campo está vacío.
- **REST y AJAX.** Hay una clase base `Modules\Endpoints\Endpoint` (espacio de nombres `v1/club-triatlon-sulayr`), pero ninguna ruta la usa. No hay `wp_ajax_*`. Las pantallas se pintan en el servidor.
- **Shortcodes y bloques.** Un shortcode, `[sulayr_solicitud_alta]` (`Join\PublicJoinForm`). No hay bloques.
- **Mi cuenta (WooCommerce).** Las pestañas son endpoints de WooCommerce. Por ejemplo, `Receipts\ReceiptsAccount` (`mis-recibos`) usa `woocommerce_get_query_vars`, `woocommerce_account_menu_items`, `woocommerce_account_<endpoint>_endpoint` y una versión en una opción para pedir que se regeneren los enlaces (`sulayr_wc_flush_rewrite_rules`). El acceso se comprueba con `FeesAccount::is_athlete()` y el estado del socio.
- **Plantillas.** `Services\TemplateService::load()` / `render()` incluyen `templates/<nombre>.php`. El tema no puede sobrescribirlas.
- **Recursos.** `App::load_public_assets()` carga Bootstrap 5.3.8 y `public/css/styles.css` en todo el front. Los estilos de socios del front usan las variables `--sulayr-*` del tema `ct-sulayr-theme`. En wp-admin, `Members\Admin\BrandStyles` aplica la identidad Sulayr dentro de `.sulayr-brand` en las pantallas cuyo `page` empieza por `sulayr-socios`.
- **Traducciones.** Text domain `club-triatlon-sulayr`, con `languages/es_ES.po`. Los módulos recientes escriben los textos directamente en español.
- **Tests.** No hay PHPUnit ni carpeta `tests/`. Se verifica con WP-CLI y `wp eval-file`. En local hay PHP 8.1 a 8.5 y no hay PHP 7.4.

### 1.2. Deportistas, competiciones y multisite

- **Deportista = usuario de WordPress** con rol `athlete` en blog 1. Sus datos son metas de usuario: los campos ACF de `UserFields` (fecha de nacimiento `sulayr_user_birth_date`, foto `sulayr_user_photo`, estado `sulayr_user_athlete_status`: `pendiente`, `active`, `congelado` o `baja`) y los de la «Ficha del socio» (`MemberFields`: número, tipo, fecha de baja, talla, tutor…). El estado tiene un historial con fechas en `sulayr_member_status_periods` (`StatusPeriods`).
- **Sexo:** no existe en ningún sitio (ni en la ficha, ni en el formulario de alta, ni en reservas). Hay que añadirlo (sección 2.1).
- **Fecha de nacimiento:** existe y en local la tienen los 59 deportistas. ACF la guarda como `Ymd`; `MemberDates::acf_to_iso()` la convierte.
- **Categoría o grupo de edad:** no se guarda en ningún sitio. Se calcula (sección 3.2).
- **Competiciones, eventos y resultados:** no hay nada.
- **Multisite.** `club-triatlon-sulayr` está activado solo en blog 1 (no en red); `sulayr-reservas` está activado en red. Las tablas del club usan el prefijo de blog 1 (`wp_`). El plugin del club no llama a `switch_to_blog()`. La única llamada a reservas es `Join\ReservationsSync`.

### 1.3. Decisiones

| Nº | Decisión |
|---|---|
| R1 | Documento en `docs/club/SPEC-RANKING-INTERNO.md`, con una fila en `docs/FASES.md`. |
| R2 | El sexo se guarda en la ficha del socio (meta `sulayr_member_sex`: `m` o `f`) y se pide en el formulario de alta. Cada resultado guarda una copia, que se rehace al recalcular (sección 2.4). |
| R3 | El grupo de edad es la categoría de la federación que corresponde a la edad a 31 de diciembre del año de la temporada (tabla de la sección 3.2, configurable). Un socio no cambia de grupo dentro de una temporada. |
| R4 | En el ranking solo salen socios activos o congelados. Un congelado conserva sus puntos. Un socio de baja desaparece del ranking y de las fichas; si vuelve, recupera sus resultados (R34). |
| R5 | La temporada es el año natural de la fecha de la competición. |
| R6 | Las referencias de tiempo (ganador absoluto por sexo y ganador de cada grupo de edad con socios) son obligatorias para publicar una competición (sección 4.3). |
| R7 | Se guarda la posición dentro del sexo (la «Pos» de la clasificación general, que la federación publica por sexo). El respaldo por posición la usa frente a los **finalizados** de ese sexo (cabecera «FINALIZADOS»), no frente a los inscritos. |
| R8 | El coeficiente sale de una matriz modalidad × distancia editable desde wp-admin, con los valores del baremo por defecto y 1,0 en las combinaciones no previstas (sección 3.1). Triatlón cros y duatlón cros son modalidades propias, con los mismos coeficientes por defecto que en carretera. |
| R9 | Puntos = `1000 × ratio × coeficiente + 50`. El bonus se suma después del coeficiente. |
| R10 | Desempate en la clasificación: más pruebas terminadas, luego mejor resultado individual; si sigue el empate, se comparte puesto. |
| R11 | Un tiempo menor que el del ganador se rechaza al validar. Un tiempo igual solo vale si el socio es el ganador (posición 1). |
| R12 | Relevos y contrarreloj por equipos cuentan. En relevos cada deportista hace el recorrido completo y cuenta su tiempo individual; en la contrarreloj por equipos, el tiempo del equipo (sección 3.5). |
| R13 | Todas las pruebas son puntuables. No hay campo «puntuable». |
| R14 | Capacidad nueva `sulayr_manage_ranking` para el rol nuevo «Gestor de ranking» (`sulayr_ranking_manager`) y para los administradores. Los gestores de socios no la tienen. |
| R15 | Los datos los mete solo un gestor de ranking. Los deportistas solo consultan; no pueden añadir sus tiempos. |
| R16 | El ranking solo se ve con sesión iniciada, en una pestaña de Mi cuenta. No se ocultan datos de menores ni fotos, porque no es público. |
| R17 | La ficha del deportista y la de la competición están dentro de esa pestaña de Mi cuenta. La foto es la de `sulayr_user_photo`. |
| R18 | Shortcodes en esta spec; un bloque queda para más adelante. `TemplateService` se amplía para que el tema pueda sobrescribir solo las plantillas del ranking. |
| R19 | ~~No hay importación por CSV. Se introduce todo a mano, en una tabla editable.~~ Sustituida por R40 el 16 de noviembre de 2026: los resultados se importan desde un JSON (sección 14). La tabla editable sigue para revisar y para meter datos a mano. |
| R20 | No se cargan temporadas anteriores. El ranking empieza con las competiciones que se metan desde la puesta en marcha. |
| R21 | PHPUnit solo para el cálculo, sin cargar WordPress. El resto se prueba con `wp eval-file` y a mano. |
| R22 | PROD no tiene SSH ni WP-CLI. Todo lo que haga falta allí (crear tablas, recalcular, rellenar el sexo) se hace desde wp-admin. |
| R23 | Tablas propias, no CPT (sección 2.2). |
| R24 | Una competición está en borrador o publicada. Solo las publicadas cuentan y se ven en Mi cuenta. Así un gestor puede meter los resultados en varias veces. |
| R25 | Sin REST ni AJAX: formularios a `admin-post.php` en wp-admin y vistas con parámetros GET en el front, como el resto del plugin (sección 6). |
| R26 | No hay «posición general» mezclando sexos: la clasificación general de la federación ya es por sexo. |
| R27 | Estado «NC» (fuera de control / corte), además de DNF, DSQ y DNS. Da 0 puntos, como un DNF. |
| R28 | Cada resultado guarda el código de categoría de la carrera (columna «Cat», p. ej. `V1M`). Si no coincide con el grupo que calcula la web, la pantalla avisa, pero se puede guardar. |
| R29 | Categoría absoluta (`ABM` / `ABF`): la federación saca a los primeros de la general de la clasificación de su categoría por edad (no es acumulable). Un socio que corre en AB puntúa en su grupo de edad como ganador del grupo (ratio 1). |
| R30 | Relevos: un socio se compara con el mejor tiempo individual de su sexo dentro de su clasificación de relevos (masculina, femenina o mixta; federado y open juntos, R39). Puntúa también en su grupo de edad, con su «Pos Cat». |
| R31 | Contrarreloj por equipos: cada socio del equipo recibe el tiempo del equipo, comparado con el del equipo ganador de su categoría de equipo. En el grupo de edad recibe los mismos puntos que en la absoluta. |
| R32 | La competición se identifica por los datos de la cabecera de la clasificación oficial (nombre, localidad, fecha, distancia). Los tiempos de referencia se toman de la clasificación general (ganador de cada sexo) y de la clasificación por categorías (primero de cada categoría). |
| R33 | Relevos y contrarreloj por equipos llevan el coeficiente de la modalidad y distancia de la competición, como una prueba individual. (Antes P2 c.) |
| R34 | Un socio que se da de baja y vuelve recupera sus resultados anteriores, también en la misma temporada: la baja no los borra, solo los oculta. (Antes P3.) |
| R35 | Cambiar los ajustes del baremo solo recalcula la temporada en curso. Cada temporada guarda su baremo, y las anteriores se quedan con el que tenían (sección 2.6). (Antes P4.) |
| R36 | Coeficientes por defecto de la sección 3.1: media ×1,4 y larga ×1,6 en triatlón y duatlón; acuatlón, acuabike y supersprint, ×1,0. Son provisionales: los cambia desde wp-admin («Ranking → Ajustes») un administrador o un gestor de ranking. (Antes P6.) |
| R37 | El ranking solo lo ven los socios activos y congelados. Ningún otro usuario lo ve en el front, aunque sea entrenador. (Antes P7.) |
| R38 | Si un socio terminó y la clasificación no trae su tiempo, el club se lo pide al deportista y el gestor lo mete en la tabla (pasa las mismas validaciones). Si no lo da, sus puntos se calculan por posición; entonces los finalizados de su categoría son obligatorios. (Antes P5.) |
| R39 | Decidida por el club el 16 de noviembre de 2026. Para el ranking interno no se distingue la clasificación de la federación en la que sale un deportista (por ejemplo, **federado** y **open** en relevos): se unifican todas las de la misma categoría de equipo y el mismo sexo. El tiempo de referencia del ganador es el más rápido de todas ellas; el primero de cada categoría, el más rápido de esa categoría en todas ellas; los finalizados, la suma. «Pos» y «Pos Cat» se recalculan por tiempo dentro del conjunto unificado (empate, misma posición). Por eso la categoría de equipo se llama «Masculina», «Femenina» o «Mixta», sin «open». |
| R40 | Decidida por el club el 16 de noviembre de 2026. Los resultados se importan desde un JSON que una IA genera a partir de los PDF oficiales (skill `/ranking-importar` de Claude Code, o claude.ai con el prompt que da la web). La web empareja a los deportistas con los socios por nombre y rellena el formulario de la competición sin guardarlo; el gestor revisa, guarda y publica como siempre. Sustituye a R19. |
| R41 | El emparejamiento es por nombre, porque las clasificaciones no traen licencia ni DNI. Se contrasta con el sexo y el grupo de edad que da la categoría de la carrera. Lo que no es seguro se lista para que el gestor diga a qué socio corresponde o que no es socio (sección 14.4). |

### 1.4. Cómo encaja

Un módulo nuevo `src/Modules/Ranking/`, cargado desde `App::load_ranking()` y con los mismos patrones que el módulo de socios: tablas con `dbDelta` y versión, ajustes en una página de opciones ACF con valores por defecto, registro de cambios que solo inserta, pantallas propias con `admin-post.php`, endpoint de Mi cuenta con WooCommerce, y la identidad Sulayr.

Archivos nuevos:

| Archivo | Responsabilidad |
|---|---|
| `Ranking/Database/RankingSchema.php` | Tablas, `VERSION`, `table()`, `maybe_install()`. |
| `Ranking/RankingSettings.php` | Página de opciones ACF «Ranking → Ajustes», `get()` con valores por defecto, coeficientes y grupos de edad. |
| `Ranking/RankingLog.php` | Registro de cambios: solo inserta. |
| `Ranking/Calc/RaceTime.php` | Convierte entre `h:mm:ss` y segundos. Sin WordPress. |
| `Ranking/Calc/AgeGroups.php` | Grupo de edad de una fecha de nacimiento en una temporada. Sin WordPress. |
| `Ranking/Calc/PointsCalculator.php` | Puntos de un resultado. Sin WordPress. |
| `Ranking/Calc/Standings.php` | Clasificación a partir de los puntos: mejores N, orden, desempates. Sin WordPress. |
| `Ranking/Calc/ResultValidator.php` | Reglas de validación de la sección 5. Sin WordPress. |
| `Ranking/Competitions.php` | Lectura y escritura de competiciones y referencias. |
| `Ranking/Results.php` | Lectura y escritura de resultados. |
| `Ranking/RankingService.php` | Recalcula puntos, construye clasificaciones, caché. |
| `Ranking/RankingRoster.php` | Socios que pueden tener resultados y salir en el ranking; sexo y grupo. |
| `Ranking/Admin/RankingMenu.php` | Menú «Ranking» y botón «Recalcular todo». |
| `Ranking/Admin/CompetitionsPage.php` | Listado de competiciones. |
| `Ranking/Admin/CompetitionEditPage.php` | Alta y edición de una competición: datos, referencias y resultados. |
| `Ranking/Admin/StandingsPage.php` | Clasificación vista desde wp-admin. |
| `Ranking/Admin/AthletesPage.php` | Deportistas del ranking: sexo, grupo de edad y avisos. |
| `Ranking/Admin/LogPage.php` | Registro de cambios. |
| `Ranking/Front/RankingAccount.php` | Pestaña de Mi cuenta. |
| `Ranking/Front/RankingShortcode.php` | Shortcode `[sulayr_ranking]`. |
| `Ranking/Front/RankingView.php` | Lee los parámetros GET, comprueba el acceso y elige la plantilla. Lo usan la pestaña y el shortcode. |
| `templates/ranking/*.php` | Plantillas del front (sección 8.3). |
| `admin/js/ranking-results.js` | Añadir y quitar filas en la tabla de resultados. |
| `tests/` | PHPUnit del cálculo (sección 10). |

Cambios en código que ya existe (son pocos y no cambian el comportamiento actual):

| Archivo | Cambio |
|---|---|
| `App/App.php` | `load_ranking()` desde `load_classes()`. |
| `Infrastructure/CustomFields.php` | `MEMBER_SEX = 'sulayr_member_sex'`, `SEX_MALE = 'm'` y `SEX_FEMALE = 'f'`. |
| `Members/MemberFields.php` | Campo «Sexo» en la ficha del socio. |
| `Members/MemberLog.php` | `MEMBER_SEX` en `TRACKED_META` (área `datos`) y su etiqueta. |
| `Members/Join/JoinForm.php`, `Join/JoinApproval.php` | Campo «Sexo» obligatorio en el formulario de alta y en «Nueva alta», guardado al aprobar. |
| `Members/Admin/MembersMenu.php` | «Sexo» en la ficha de solo lectura. |
| `Members/MemberRoles.php` | Rol `sulayr_ranking_manager` y capacidad `sulayr_manage_ranking`; `VERSION` sube a 2; `can_use_admin()` acepta la capacidad nueva. |
| `Members/Admin/BrandStyles.php` | `is_members_screen()` también reconoce las páginas `sulayr-ranking*`. |
| `Services/TemplateService.php` | `locate()`: para nombres que empiezan por `ranking/`, busca antes en el tema (sección 8.3). |

---

## 2. Modelo de datos

### 2.1. Sexo del socio

- Meta de usuario `sulayr_member_sex` (`CustomFields::MEMBER_SEX`), con los valores `m` (masculino) o `f` (femenino). Es un campo ACF `select` en el grupo «Ficha del socio» (`MemberFields`), así que un administrador lo ve en `user-edit.php`.
- Formulario de alta y «Nueva alta» (`JoinForm`): campo obligatorio «Sexo (para las clasificaciones)», con los valores Masculino y Femenino. `JoinApproval` lo copia a la meta.
- Ficha de solo lectura de «Socios»: una fila «Sexo».
- `MemberLog` lo registra como dato (área `datos`).
- Gestor de ranking: lo rellena o corrige en «Ranking → Deportistas» (sección 4.5), porque no tiene `edit_users`. Esa pantalla escribe la meta con `update_user_meta()`, y `MemberLog` lo registra.
- Mi cuenta: el socio no puede cambiarlo, igual que la fecha de nacimiento (`UserService`).
- Socios que ya existen: el sexo está vacío. Un socio sin sexo no puede tener resultados y aparece con un aviso en «Ranking → Deportistas». En PROD se rellena desde esa pantalla (R22).

### 2.2. Tablas o CPT

Tablas propias en blog 1, por estos motivos:

- La clasificación suma y ordena puntos por temporada, sexo y grupo. Con postmeta serían varios `JOIN` sin índices útiles; con tablas, una consulta con índices.
- Los resultados son filas ligadas a una competición y a un socio, con una clave única `(competición, socio)`. Un CPT no da esa garantía.
- La pantalla de resultados es una tabla editable propia, así que la interfaz de un CPT no aporta nada.
- Es el patrón del módulo de socios (historiales, recibos y registro en tablas, con `dbDelta`).

### 2.3. Esquema

`Ranking\Database\RankingSchema`: `VERSION = 1`, opción `sulayr_ranking_db_version`, `maybe_install()` en `init` con prioridad 5, `dbDelta` y `table()` con `$wpdb->prefix`, igual que `MembersSchema`. Subir `VERSION` cuando cambie una tabla: `dbDelta` conserva los datos. Si una versión necesita rellenar datos, se hace en `install()` según la versión anterior, como hace `MembersSchema` con la 4.

Tiempos en segundos enteros. Puntos en `decimal(8,2)`. Fechas en `Y-m-d`.

**`sulayr_ranking_competitions`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint(20) unsigned` AI | PK |
| `name` | `varchar(190)` | Obligatorio. |
| `race_date` | `date` | Obligatorio. |
| `season` | `smallint(4) unsigned` | Año de `race_date`. Se guarda para el índice. |
| `location` | `varchar(190)` | Puede estar vacío. |
| `modality` | `varchar(20)` | `triatlon`, `triatlon_cros`, `duatlon`, `duatlon_cros`, `acuatlon` o `acuabike`. |
| `distance` | `varchar(20)` | `supersprint`, `sprint`, `estandar`, `media` o `larga`. |
| `format` | `varchar(20)` | `individual`, `relevos` o `equipos` (contrarreloj por equipos). |
| `status` | `varchar(20)` | `borrador` o `publicada`. |
| `notes` | `varchar(255)` | Nota interna. |
| `created_by`, `updated_by` | `bigint(20) unsigned` | |
| `created_at`, `updated_at` | `datetime` | |

Índices: `KEY season_status (season, status)`, `KEY race_date (race_date)`.

**`sulayr_ranking_references`**: tiempos del ganador y participantes. Una fila por referencia que haga falta.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint(20) unsigned` AI | PK |
| `competition_id` | `bigint(20) unsigned` | |
| `scope` | `varchar(10)` | `absoluta` o `grupo`. |
| `team_category` | `varchar(10)` | Vacío en individual. En relevos y equipos, la clasificación del equipo: `m` (masculina), `f` (femenina) o `mixto`. Federado y open van juntos (R39). |
| `sex` | `char(1)` | `m` o `f`. Vacío en equipos: la referencia es el tiempo del equipo ganador. |
| `age_group` | `varchar(32)` | Clave del grupo cuando `scope = grupo`; vacío en `absoluta`. |
| `winner_time` | `int unsigned` | Segundos. |
| `participants` | `int unsigned` NULL | Finalizados (R7). Obligatorio en `absoluta` (respaldo por posición). En `grupo`, solo obligatorio si algún socio del grupo terminó sin tiempo (R38). En equipos, equipos que terminaron. |

Índices: `UNIQUE KEY ref (competition_id, scope, team_category, sex, age_group)`.

**`sulayr_ranking_results`**

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint(20) unsigned` AI | PK |
| `competition_id` | `bigint(20) unsigned` | |
| `user_id` | `bigint(20) unsigned` | Socio. |
| `sex` | `char(1)` | Copia de `sulayr_member_sex` (R2). |
| `age_group` | `varchar(32)` | Copia del grupo de la temporada; vacío si falta la fecha de nacimiento. |
| `status` | `varchar(10)` | `finalizado`, `dnf`, `dsq`, `dns` o `nc`. |
| `time_s` | `int unsigned` NULL | Tiempo final; en relevos, el individual del socio; en equipos, el del equipo. |
| `race_category` | `varchar(8)` | Código «Cat» de la carrera (`V1M`, `SNF`, `ABM`…). Puede estar vacío. |
| `position_sex` | `int unsigned` NULL | «Pos» de la clasificación de su sexo. En relevos, dentro de su clasificación de relevos; en equipos, la del equipo en su categoría. |
| `position_age_group` | `int unsigned` NULL | «Pos Cat». |
| `team_category` | `varchar(10)` | Solo en relevos y equipos: `m`, `f` o `mixto`. |
| `coefficient` | `decimal(4,2)` | Coeficiente aplicado. |
| `points_absolute` | `decimal(8,2)` | |
| `points_age_group` | `decimal(8,2)` NULL | NULL = no puntúa en grupo de edad. |
| `method_absolute`, `method_age_group` | `varchar(10)` | `tiempo`, `posicion`, `absoluta` (R29), `estado` (DNF/DSQ/DNS/NC) o `ninguno`. |
| `calc_note` | `varchar(255)` | Aviso del cálculo, p. ej. «Falta el tiempo del ganador de Veterano 1 F». |
| `created_by`, `updated_by` | `bigint(20) unsigned` | |
| `created_at`, `updated_at` | `datetime` | |

Índices: `UNIQUE KEY competition_user (competition_id, user_id)`, `KEY user_id (user_id)`, `KEY sex_group (sex, age_group)`.

**`sulayr_ranking_log`**: solo inserta.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | `bigint(20) unsigned` AI | PK |
| `entity` | `varchar(20)` | `competicion`, `referencia`, `resultado`, `ajuste` o `recalculo`. |
| `entity_id` | `bigint(20) unsigned` | 0 en los ajustes. |
| `competition_id` | `bigint(20) unsigned` | 0 si no aplica. |
| `user_id` | `bigint(20) unsigned` | Socio afectado; 0 si no aplica. |
| `field` | `varchar(64)` | Campo, o `alta` / `borrado` / `publicar` / `despublicar`. |
| `old_value`, `new_value` | `text` | En un borrado, la fila entera como JSON. |
| `note` | `varchar(255)` | Motivo común, p. ej. «Cambio de ajustes · Temporada 2027» (`RankingLog::with_note()`). |
| `author_id` | `bigint(20) unsigned` | |
| `created_at` | `datetime` | |

Índices: `KEY competition_id (competition_id)`, `KEY user_id (user_id)`, `KEY created_at (created_at)`.

No se reutiliza `sulayr_member_log`: exige un socio por entrada y su `record()` dispara `sulayr_member_history_changed`, que vuelve a comprobar los recibos. Los cambios de competiciones y ajustes no tienen socio y no afectan a los recibos.

### 2.4. Copias en el resultado

`sex` y `age_group` del resultado son copias para poder filtrar con índices. `RankingService::recalculate_user()` las rehace cuando cambia `sulayr_member_sex` o `sulayr_user_birth_date` de un socio (hook `updated_user_meta` / `added_user_meta`). Son datos corregidos, no cambios reales. Cambiar los grupos de edad en los ajustes rehace todas las copias.

### 2.5. Borrados

- **Competición:** solo se borra desde su pantalla, con confirmación. Se borran también sus referencias y resultados. Cada borrado queda en el registro como JSON.
- **Resultado:** se borra quitando su fila en la tabla de resultados. Queda en el registro.
- **Socio:** los resultados no se borran cuando se da de baja (R4); dejan de mostrarse.

### 2.6. Baremo por temporada

Para que un cambio de ajustes no altere temporadas pasadas (R35), cada temporada guarda una copia de su baremo:

- Opción `sulayr_ranking_baremo_<año>` (sin autoload), con puntos base, bonus, N, los treinta coeficientes y los grupos de edad.
- `RankingSettings::for_season( int $year, bool $create = false ): array` devuelve esa copia. Si no existe, devuelve los ajustes actuales sin guardar nada; solo crea la copia cuando `$create` es `true`, que es lo que hacen las escrituras.
- Los ajustes de «Ranking → Ajustes» son siempre los de la temporada en curso. Guardarlos sobrescribe la copia de esa temporada y no toca las demás.
- El cálculo, la clasificación (N) y los grupos de edad de una temporada leen siempre `for_season()`, nunca los ajustes directamente.
- Cuando empieza una temporada nueva, la primera escritura (guardar los ajustes o una competición de esa temporada) congela una copia de los ajustes vigentes. Al guardar los ajustes, antes de escribirlos se congelan las copias de la temporada anterior y de la en curso, con los valores que aún están vigentes. Las lecturas (ficha, clasificación, baremos de temporadas pasadas) nunca crean copias. Así una competición de diciembre metida en enero se calcula con el baremo de su temporada.
- Los baremos de temporadas pasadas no se editan desde wp-admin. Si hiciera falta, se haría con un cambio de código y una entrada en el registro.

---

## 3. Lógica de cálculo

### 3.1. Ajustes del baremo

Todo se lee con `RankingSettings::get()`, que devuelve el valor por defecto si el campo está vacío.

| Ajuste | Clave | Por defecto |
|---|---|---|
| Puntos base | `base_points` | 1000 |
| Bonus por prueba terminada | `finish_bonus` | 50 |
| Pruebas que cuentan (N) | `best_n` | 6 |
| Coeficientes | `coef_<modalidad>_<distancia>` | Tabla de abajo |
| Grupos de edad | `age_groups` | Sección 3.2 |
| Nombre de la pestaña de Mi cuenta | `account_label` | «Ranking interno» |

Coeficientes por defecto. En negrita, los que da el baremo; el resto es 1,0 (R8). «Media» y «larga» se aplican a triatlón y duatlón; acuatlón y acuabike van siempre a ×1,0 (R36). Las modalidades cros llevan por defecto los mismos valores que en carretera. Todos se cambian en «Ranking → Ajustes».

| Modalidad | Supersprint | Sprint | Estándar / olímpico | Media | Larga |
|---|---|---|---|---|---|
| Triatlón | 1,0 | **1,1** | **1,2** | **1,4** | **1,6** |
| Triatlón cros | 1,0 | 1,1 | 1,2 | 1,4 | 1,6 |
| Duatlón | 1,0 | **1,0** | **1,2** | **1,4** | **1,6** |
| Duatlón cros | 1,0 | 1,0 | 1,2 | 1,4 | 1,6 |
| Acuatlón | **1,0** | **1,0** | **1,0** | **1,0** | **1,0** |
| Acuabike | **1,0** | **1,0** | **1,0** | **1,0** | **1,0** |

### 3.2. Grupo de edad

```
edad(fecha_nacimiento, temporada) = temporada − año(fecha_nacimiento)    // edad a 31 de diciembre
grupo = primer grupo de age_groups con edad_min ≤ edad ≤ edad_max (edad_max vacía = sin límite)
sin fecha de nacimiento, o ningún grupo → grupo vacío
```

Grupos por defecto: las categorías de la federación que dio el club el 16 de noviembre de 2026 (R3). Cada grupo tiene una clave estable, un nombre, una edad mínima, una edad máxima y el código de la federación (las dos letras de la columna «Cat», sin la M/F final), que sirve para el aviso de R28. Los códigos sin confirmar en los ejemplos quedan vacíos y el gestor los puede rellenar.

| Clave | Nombre | Edades | Código |
|---|---|---|---|
| `prebenjamin` | Prebenjamín | –7 | |
| `benjamin` | Benjamín | 8–9 | |
| `alevin` | Alevín | 10–11 | |
| `infantil` | Infantil | 12–13 | |
| `cadete` | Cadete | 14–15 | `CD` |
| `juvenil` | Juvenil | 16–17 | `JV` |
| `junior` | Júnior | 18–19 | `JN` |
| `sub23` | Sub-23 | 20–23 | `23` |
| `senior` | Sénior | 24–39 | `SN` |
| `veterano-1` | Veterano 1 | 40–49 | `V1` |
| `veterano-2` | Veterano 2 | 50–59 | `V2` |
| `veterano-3` | Veterano 3 | 60–69 | `V3` |
| `veterano-4` | Veterano 4 | 70–79 | `V4` |
| `veterano-5` | Veterano 5 | 80– | |

La federación también tiene grupos de edad de cinco años (G.E. 30-34, 35-39…). El ranking usa las categorías, no esos grupos, porque son las que publican las clasificaciones de los ejemplos.

Un socio no cambia de grupo a mitad de temporada: la edad depende solo del año (R3). Si un gestor corrige la fecha de nacimiento, se recalcula el grupo de todas las temporadas de ese socio (sección 2.4).

### 3.3. Puntos de un resultado

`PointsCalculator::calculate( array $input ): array`. Es una función pura: los datos entran ya leídos y no se lee nada de WordPress.

```
entrada:
  status, time_s, position_sex, position_age_group
  coef                      // de la matriz
  ref_abs  = {winner_time, participants}  o null
  ref_grp  = {winner_time, participants}  o null   // null si el socio no tiene grupo
  base = 1000, bonus = 50

función puntos(ref, tiempo, posición):
  si ref ≠ null y ref.winner_time > 0 y tiempo > 0:
      ratio = ref.winner_time / tiempo                      // ≤ 1 por la validación (R11)
      devuelve (base × ratio × coef + bonus, 'tiempo')
  si ref ≠ null y ref.participants ≥ 1 y posición ≥ 1:
      ratio = 1 − (posición − 1) / ref.participants        // en [0, 1] por la validación
      devuelve (base × ratio × coef + bonus, 'posicion')
  devuelve (null, 'ninguno')

si status ∈ {dnf, dsq, dns, nc}:
    points_absolute = 0, points_age_group = 0, método = 'estado'      // sin bonus
si no:
    (points_absolute, m_abs) = puntos(ref_abs, time_s, position_sex)
    si points_absolute = null: points_absolute = 0 y aviso «Sin referencia absoluta»
    si race_category empieza por 'AB' y el socio tiene grupo:          // R29
        points_age_group = base × 1 × coef + bonus, m_grp = 'absoluta'
    si no:
        (points_age_group, m_grp) = puntos(ref_grp, time_s, position_age_group)
        si points_age_group = null: aviso «No puntúa en grupo de edad: falta la referencia del grupo»

redondeo: round(x, 2) al final, nunca en pasos intermedios
```

La referencia absoluta es la del sexo del socio (`scope = absoluta`, `sex`). La del grupo es la de su sexo y su grupo (`scope = grupo`, `sex`, `age_group`). En relevos y equipos se usa la sección 3.5.

El grupo del socio es siempre el que calcula la web (sección 3.2), no el código de la carrera. Si no coinciden y el código no es AB, el resultado lleva el aviso «La carrera lo clasifica como V2M y la web como Veterano 1: revisa la fecha de nacimiento o las edades de los grupos» (R28).

### 3.4. Clasificación de la temporada

`Standings::build( array $rows, int $n ): array`. Es una función pura. `$rows` son los resultados de las competiciones publicadas de la temporada, ya filtrados por clasificación (absoluta por sexo, o por sexo y grupo) y solo de socios que salen en el ranking (R4).

```
por cada socio:
    puntos  = lista de points_X de sus resultados (X = absolute o age_group), sin los NULL
    orden   = puntos de mayor a menor
    cuentan = los N primeros de orden                     // si tiene menos de N, todos
    total   = suma(cuentan)                               // suma de valores ya redondeados
    terminadas = nº de resultados con status = finalizado
    mejor   = max(puntos) o 0
    marca como «cuenta» los resultados de `cuentan`; si hay valores iguales en el corte, cuentan los de fecha más antigua

orden de la clasificación:
    total desc, terminadas desc, mejor desc
    mismos tres valores → mismo puesto (1, 2, 2, 4) y orden alfabético por apellidos solo para pintar

salen los socios con al menos un resultado en la temporada (también si todos son DNF: total 0)
```

En la clasificación por grupo de edad, solo entran los resultados con `points_age_group` distinto de NULL. Un socio sin grupo no sale en ninguna clasificación por grupo.

### 3.5. Relevos y equipos

- **Relevos** (ejemplo: «I Duatlón por relevos 3X Úbeda Nazarí», clasificación «Open masculina»): cada deportista hace el recorrido completo. La federación publica, por cada clasificación de relevos (open masculina, open femenina, mixta), su tiempo individual, su «Pos», su «Cat» y su «Pos Cat»; el tiempo del equipo es la suma. Si hay varias clasificaciones de la misma categoría (federado y open), se unifican (R39, sección 14.3).
  - Cada socio tiene una fila con su tiempo individual y su `team_category`.
  - Referencia absoluta: el mejor tiempo individual de su sexo dentro de esa clasificación (`scope = absoluta`, `team_category`, `sex`) (R30). En una clasificación mixta, una socia se compara con la mejor mujer.
  - Respaldo por posición: su posición entre los deportistas de su sexo de esa clasificación, frente a los que terminaron.
  - Grupo de edad: como en una prueba individual, con el primero de su categoría dentro de esa clasificación (`scope = grupo`, `team_category`, `sex`, `age_group`).
- **Contrarreloj por equipos:** cada socio del equipo tiene una fila con el tiempo del equipo. La referencia es el tiempo del equipo ganador de su categoría de equipo (`scope = absoluta`, `team_category`, `sex` vacío). El respaldo usa la posición del equipo frente a los equipos que terminaron. En el grupo de edad, `points_age_group = points_absolute` (R31).
- **Clasificación del club:** el socio entra en la clasificación de su sexo, aunque su equipo sea mixto.
- **Coeficiente:** el de la modalidad y distancia de la competición.

### 3.6. Casos límite

| Caso | Comportamiento |
|---|---|
| Dos socios con el mismo tiempo | Mismos puntos. En la clasificación, desempate R10. |
| El socio es el ganador absoluto | Su tiempo es igual a la referencia y su posición en el sexo es 1: ratio 1, `1000 × coef + 50`. |
| Un único participante de su sexo | `participants = 1`: es el ganador. Por tiempo, ratio 1; por posición, `1 − 0/1 = 1`. |
| Falta el tiempo del ganador | No se puede publicar la competición (R6). En borrador, se calcula por posición si hay participantes; si no, 0 puntos con aviso. |
| Falta el tiempo del socio pero tiene posición | Primero se le pide el tiempo al deportista (R38). Si no lo da, respaldo por posición (sección 3.3), con los finalizados de su sexo y de su categoría obligatorios. |
| No tiene tiempo ni posición, y está «finalizado» | Error de validación: hace falta al menos uno de los dos. |
| Falta la referencia del grupo de edad | No puntúa en grupo (`NULL`) y sale un aviso. No se puede publicar mientras falte (R6). |
| Socio sin fecha de nacimiento | Puntúa en la absoluta; no tiene grupo y no sale en las clasificaciones por grupo. Aviso en «Deportistas». |
| Socio sin sexo | No se puede guardar su resultado. Hay que rellenar el sexo antes (sección 2.1). |
| Cambio de categoría a mitad de temporada | No ocurre: el grupo depende del año (R3). Si se corrige la fecha de nacimiento, se rehace el grupo y se recalcula; si el grupo nuevo no tiene referencia, la competición vuelve a mostrar el aviso. |
| Socio congelado | Sus resultados cuentan. Se le pueden meter resultados de pruebas de mientras estaba congelado. |
| Socio de baja | Sus resultados se conservan en la base de datos, pero no salen en ninguna clasificación ni ficha del front. En wp-admin salen marcados «De baja». Si vuelve al club, sus resultados vuelven a contar, también los de la misma temporada (R34). |
| Socio pendiente de alta | No se le pueden meter resultados. |
| Menos de N pruebas | Cuentan todas. |
| DNF, DSQ, DNS o NC | 0 puntos, sin bonus. No cuentan como terminadas para el desempate. |
| Socio en categoría AB | En la absoluta, normal. En su grupo de edad, puntos de ganador (R29), aunque su tiempo sea menor que el del primero de su categoría. |
| Código de categoría distinto del grupo calculado | Se guarda con un aviso (R28). Puntúa en el grupo calculado. |
| Se cambia N, el bonus, un coeficiente o los grupos | Se recalcula solo la temporada en curso; las anteriores conservan su baremo (R35, sección 3.7). |
| Se corrige una competición de una temporada pasada | Se recalcula con el baremo guardado de esa temporada (sección 2.6). |
| Una competición vuelve a borrador | Deja de contar y de verse. Sus puntos se conservan. |

### 3.7. Recálculo

- **Al guardar una competición** (datos, referencias o resultados): `RankingService::recalculate_competition( $id )` recalcula los puntos de todos sus resultados en la misma petición e invalida la caché.
- **Al guardar los ajustes** (`acf/save_post` con `post_id` `sulayr_ranking`, prioridad 20): se comparan los valores anteriores y los nuevos, se registra cada cambio, se copian al baremo de la temporada en curso (sección 2.6) y se lanza `recalculate_season( año en curso )`. Las temporadas anteriores no se tocan (R35). Con el volumen del club (unos 60 socios y una o dos decenas de pruebas al año) cabe en una petición. Si algún día no cabe, se pasa a lotes con WP-Cron.
- **Al cambiar el sexo o la fecha de nacimiento de un socio:** `recalculate_user( $user_id )`.
- **Al cambiar el estado de un socio** (alta, baja, congelación: hook `sulayr_member_history_changed`, que ya dispara `MemberLog`): solo se invalida la caché, porque los puntos no cambian.
- **Al guardar una competición de una temporada pasada:** se recalcula con el baremo de esa temporada.
- **Botón «Recalcular todo»** en «Ranking → Competiciones» (R22). Recalcula cada temporada con su propio baremo. Es idempotente y deja una entrada `recalculo` en el registro con el número de resultados que cambiaron.
- **Registro:** un recálculo solo anota los resultados cuyos puntos cambian (`points_absolute` y `points_age_group`, valor anterior → nuevo), con la nota del motivo.

---

## 4. Pantallas de administración

Menú «Ranking» (`sulayr-ranking`), protegido con `sulayr_manage_ranking`. Todas las pantallas van dentro de `.sulayr-brand` (R18 y `BrandStyles`), y la acción principal lleva `button-primary sulayr-cta`. Los avisos y los errores siguen el patrón de `RequestsPage` (transient por usuario).

### 4.1. Competiciones (`sulayr-ranking`)

- Filtro por temporada; por defecto, la del año en curso.
- Columnas: fecha, nombre, lugar, modalidad · distancia · formato, estado (Borrador / Publicada), número de resultados y avisos (p. ej. «Faltan 2 referencias»).
- Acciones: «Añadir competición», «Editar» y «Recalcular todo».

### 4.2. Añadir o editar una competición (`sulayr-ranking-competicion&id=`)

Una pantalla con tres bloques y un solo formulario. «Guardar» deja el estado como está; «Guardar y publicar» o «Volver a borrador» lo cambian.

1. **Datos:** nombre, fecha, lugar, modalidad, distancia y formato. Un formato no se puede cambiar si ya hay resultados con categoría de equipo (habría que borrarlos antes).
2. **Resultados:** tabla editable, con una fila por socio.
   - Columnas, en el orden de la clasificación oficial: socio (desplegable con los socios activos y congelados que tienen sexo; los que ya están en la tabla no aparecen), «Pos» (posición en el sexo), «Cat» (código de la carrera), «Pos Cat», tiempo (`h:mm:ss`) y estado. En relevos y equipos se añade la categoría del equipo.
   - El sexo y el grupo de edad del socio se muestran al lado, sin poder editarse.
   - «Añadir fila» y «Quitar» con `admin/js/ranking-results.js`. Sin JavaScript, la tabla trae 5 filas vacías.
   - Después de guardar, cada fila muestra los puntos (absoluta y grupo), el método y el aviso del cálculo.
3. **Referencias:** se generan a partir de los resultados guardados.
   - Individual: «Ganador masculino» y «Ganadora femenina» (tiempo de la primera fila de la clasificación general de cada sexo, y «FINALIZADOS» de su cabecera), y una fila por cada pareja sexo + grupo de edad de los socios que terminaron fuera de AB (tiempo del primero de esa categoría, obligatorio; finalizados de la categoría, que solo se pide, y entonces es obligatorio, si algún socio de esa categoría no tiene tiempo: R38).
   - Relevos: las mismas filas, por cada clasificación de relevos con socios (`team_category`). Equipos: una fila por categoría de equipo (tiempo del equipo ganador y equipos que terminaron).
   - Las que faltan salen marcadas en rojo. Al guardar, un cambio en los resultados puede pedir referencias nuevas: la pantalla vuelve con el aviso «Rellena las referencias de los grupos nuevos».

**Guardar** (`admin_post_sulayr_ranking_save_competition`):

1. `check_admin_referer( 'sulayr_ranking_save_competition_' . $id )` y `current_user_can( 'sulayr_manage_ranking' )`.
2. Lee y limpia todos los campos (sección 6.2) y valida con `ResultValidator` (sección 5).
3. Si hay errores, guarda el estado del formulario en un transient y vuelve a la pantalla con los errores fila por fila, sin guardar nada.
4. Si no hay errores, en una transacción: guarda los datos de la competición, inserta, actualiza o borra las filas de resultados y referencias comparándolas con las de la base de datos, y deja una entrada por campo cambiado en el registro.
5. Recalcula la competición (sección 3.7), invalida la caché y vuelve con el aviso «Competición guardada: N resultados».

**Publicar:** exige al menos un resultado y todas las referencias obligatorias (R6). Si falta algo, la competición se guarda en borrador con el aviso de lo que falta.

**Borrar:** botón con confirmación de JavaScript y un nonce propio (sección 2.5).

### 4.3. Flujo de uso

1. Después de la carrera, el gestor abre «Ranking → Añadir competición», rellena los datos y guarda (borrador).
2. Con la clasificación general de cada sexo, mete una fila por socio que participó («Pos», «Cat», «Pos Cat», tiempo o estado) y guarda.
3. Rellena las referencias que pide la pantalla: ganador de cada sexo y «FINALIZADOS», de la clasificación general; primero de cada categoría con socios, de la clasificación por categorías. Guarda.
4. Revisa los puntos de cada fila y pulsa «Guardar y publicar».
5. La competición y la clasificación se ven ya en Mi cuenta.

Desde el 16 de noviembre de 2026 (R40), los pasos 1 a 3 los hace la importación de la sección 14: el gestor llega al paso 4 con todo relleno. Meterlo a mano sigue siendo posible.

### 4.4. Clasificación (`sulayr-ranking-clasificacion`)

La misma vista que Mi cuenta (sección 8), con los mismos filtros. Además:

- muestra los socios de baja con sus puntos, marcados «De baja» y fuera de la numeración;
- un enlace en cada socio a su ficha de «Socios», si el gestor tiene `sulayr_manage_members`.

### 4.5. Deportistas (`sulayr-ranking-deportistas`)

- Listado de los socios activos y congelados: nombre, número de socio, estado, sexo (desplegable editable), fecha de nacimiento (solo lectura) y grupo de edad de la temporada elegida.
- Arriba, los socios con avisos: sin sexo o sin fecha de nacimiento.
- «Guardar sexos» envía a `admin_post_sulayr_ranking_save_sexes`, con nonce, capacidad y valores limitados a `m`/`f`. Guarda la meta (lo registra `MemberLog`) y recalcula los resultados de esos socios.
- La fecha de nacimiento la cambia un gestor de socios o un administrador, como hasta ahora.

### 4.6. Registro (`sulayr-ranking-registro`)

Lista paginada (50 por página), de la más reciente a la más antigua, con filtros por competición, socio y tipo. Columnas: fecha, autor, qué cambió (entidad y campo), valor anterior → nuevo, y competición y socio enlazados.

### 4.7. Ajustes (`sulayr-ranking-ajustes`)

Página de opciones ACF (`post_id` `sulayr_ranking`, opciones `sulayr_ranking_<campo>`), igual que `MemberSettings`. Pestañas:

- **Baremo:** puntos base, bonus y N.
- **Coeficientes:** treinta campos numéricos (seis modalidades × cinco distancias), mínimo 0,1, con paso 0,05.
- **Grupos de edad:** repetidor con clave, nombre, edad mínima, edad máxima y código de la federación. Se validan las claves únicas y que los tramos no se solapen.
- **Mi cuenta:** nombre de la pestaña.

Debajo del título, un aviso: «Estos ajustes se aplican a la temporada {año en curso}. Al guardar, se recalculan sus puntos. Las temporadas anteriores conservan su baremo.» (R35). Debajo, un enlace a «Ver el baremo de temporadas anteriores», de solo lectura.

La página de opciones exige `sulayr_manage_ranking` (`capability` de `acf_add_options_sub_page`).

---

## 5. Validaciones

Estas reglas valen para el formulario de la sección 4.2, se meta a mano o venga de la importación (sección 14), que solo lo rellena. Viven en `Calc\ResultValidator`, sin WordPress, para poder probarlas.

**Competición**

- Nombre obligatorio (máximo 190 caracteres). Fecha obligatoria, válida y no posterior a hoy.
- Modalidad, distancia y formato, dentro de la lista cerrada.

**Tiempos**

- Formato: `h:mm:ss`, `hh:mm:ss` o `mm:ss`. Minutos y segundos entre 0 y 59. Mayor que 0 y menor de 24 horas. No se admiten décimas.
- `RaceTime::parse()` devuelve los segundos, o null si el formato no vale. `RaceTime::format()` hace lo contrario.

**Referencias**

- Absolutas (individual): tiempo del ganador obligatorio para publicar si hay socios de ese sexo que terminaron. Participantes (finalizados): un entero ≥ 1.
- La de grupo, mayor o igual que la absoluta del mismo sexo.
- Participantes del grupo: obligatorios para publicar si algún socio de ese grupo terminó sin tiempo (R38); si se rellenan, entero ≥ 1 y menor o igual que los del sexo.
- En relevos, lo mismo dentro de cada clasificación de relevos.

**Resultados**

- Socio: existe, tiene rol `athlete`, está activo o congelado, tiene sexo y no está repetido en la competición.
- Estado: dentro de la lista. Si es `dnf`, `dsq`, `dns` o `nc`, el tiempo y las posiciones se ignoran y se guardan vacíos.
- Si es `finalizado`, hace falta un tiempo o una posición en el sexo.
- Código de categoría (si se rellena): dos caracteres y la letra del sexo (`^[A-Z0-9]{2}[MF]$`, en mayúsculas), y la letra final igual al sexo del socio. Si no coincide con el grupo calculado y no es AB, aviso (R28), no error.
- **Tiempo frente al ganador (R11):**
  - tiempo < referencia absoluta → error «El tiempo no puede ser menor que el del ganador (h:mm:ss)»;
  - tiempo = referencia absoluta y posición en el sexo ≠ 1 → error «Solo el ganador puede tener el tiempo del ganador»;
  - lo mismo frente a la referencia del grupo, con la posición en la categoría, salvo si el código de categoría es AB (R29).
- Posiciones: enteros ≥ 1; posición en el sexo ≤ finalizados del sexo; posición en la categoría ≤ posición en el sexo.
- Relevos y equipos: categoría del equipo obligatoria.

Los errores se pintan junto a cada campo, y arriba un resumen: «3 errores en la fila de Ana López».

---

## 6. Endpoints, seguridad y rendimiento

### 6.1. Endpoints

No hay rutas REST ni AJAX (R25).

| Acción | Tipo | Capacidad | Nonce |
|---|---|---|---|
| `sulayr_ranking_save_competition` | `admin-post.php`, POST | `sulayr_manage_ranking` | `sulayr_ranking_save_competition_<id>` |
| `sulayr_ranking_delete_competition` | `admin-post.php`, POST | `sulayr_manage_ranking` | `sulayr_ranking_delete_competition_<id>` |
| `sulayr_ranking_save_sexes` | `admin-post.php`, POST | `sulayr_manage_ranking` | `sulayr_ranking_save_sexes` |
| `sulayr_ranking_recalculate` | `admin-post.php`, POST | `sulayr_manage_ranking` | `sulayr_ranking_recalculate` |
| Ajustes | Página de opciones ACF | `sulayr_manage_ranking` | El de ACF |
| Mi cuenta → `ranking-interno` | GET (`temporada`, `vista`, `sexo`, `grupo`, `deportista`, `competicion`) | Deportista no de baja (sección 8.1) | No hace falta: es solo lectura |

Si más adelante hiciera falta una API, se usaría la clase base `Endpoints\Endpoint` con `permission_callback` sobre las mismas comprobaciones.

### 6.2. Seguridad

- **Capacidades:** cada pantalla se registra con `sulayr_manage_ranking`, y cada manejador vuelve a comprobarla con `current_user_can()` antes de leer nada. Nunca se comprueba un nombre de rol.
- **Nonces:** `wp_nonce_field()` / `check_admin_referer()` en todos los POST.
- **Entrada:**
  - `absint()` para los identificadores y las posiciones;
  - `sanitize_text_field( wp_unslash() )` para los textos;
  - listas blancas (`in_array( …, true )`) para modalidad, distancia, formato, estado, sexo, categoría de equipo y grupo; expresión regular para el código de categoría;
  - expresión regular más `RaceTime::parse()` para los tiempos;
  - el campo `temporada` del front: entero de 4 cifras de entre las temporadas con competiciones publicadas.
- **SQL:** siempre `$wpdb->prepare()`, e `$wpdb->insert()` / `update()` con formatos. Los nombres de tabla salen solo de `RankingSchema::table()`.
- **Salida:** `esc_html()`, `esc_attr()` y `esc_url()` en todas las plantillas. Números con `number_format_i18n()`, fechas con `date_i18n()`.
- **Acceso a datos en el front:**
  - una ficha de deportista solo se abre si ese socio sale en el ranking (activo o congelado); si no, «Deportista no encontrado»;
  - una ficha de competición solo se abre si está publicada;
  - no se muestran DNI, email, teléfono ni la fecha de nacimiento: solo el grupo de edad.
- **Redirecciones:** `wp_safe_redirect()` después de cada POST.

### 6.3. Rendimiento

- **Clasificación:** `RankingService::standings( $season )` construye en una pasada todas las clasificaciones de la temporada (absoluta por sexo y por sexo + grupo). Es una consulta de resultados de competiciones publicadas, unida a la lista de socios que salen en el ranking, y `Standings::build()` en PHP.
- **Caché:** transient `sulayr_ranking_standings_<temporada>_<versión>`, de un día. La versión es la opción `sulayr_ranking_cache_version`, un entero que sube con cada cambio de la sección 3.7 (guardar, recalcular, ajustes, estado, sexo o fecha de nacimiento de un socio). Las claves viejas caducan solas. Subir la versión invalida todo sin buscar claves.
- **Fichas:** la del deportista lee sus resultados (índice `user_id`) y toma su posición de la clasificación en caché. La de la competición lee sus resultados (índice `competition_id`).
- **Lista de socios:** `RankingRoster` lee una vez por petición los socios `athlete` con estado `active` o `congelado` y sus metas (`get_users()` con `fields` y `meta_query`), y la guarda en una propiedad estática.

---

## 7. Multisite

- **Dónde vive:** el ranking vive solo en blog 1, donde está activado `club-triatlon-sulayr`. Las tablas tienen el prefijo de ese blog (`wp_sulayr_ranking_*`) y las opciones son de blog (`get_option`), no de red.
- **Sin cambio de blog:** no hay `switch_to_blog()`. Los usuarios son de red, pero solo se usan los que tienen rol `athlete` en blog 1.
- **Roles:** el rol y la capacidad nuevos se crean en blog 1, desde `MemberRoles::sync()`. El rol de blog 2 (`gestor` de reservas) no cambia.
- **Reservas:** no se toca `sulayr-reservas`, ni se lee nada de blog 2. El sexo nuevo no se sincroniza con reservas.
- **Super administradores:** tienen `sulayr_manage_ranking` por ser super administradores.
- **Si alguien activara el plugin en otro blog:** se crearían allí tablas vacías, como pasa hoy con `MembersSchema`. No se protege con código; queda escrito en `AGENT.md` que el plugin solo se activa en blog 1.

---

## 8. Frontend

### 8.1. Mi cuenta → «Ranking interno»

`Front\RankingAccount`, endpoint `ranking-interno`, con el mismo patrón que `ReceiptsAccount`:

- query var en `woocommerce_get_query_vars`;
- versión en la opción `sulayr_ranking_endpoint_version`, que pide regenerar los enlaces;
- pestaña en `woocommerce_account_menu_items` (prioridad 14, detrás de «Mis recibos»);
- título con `RankingSettings::get( 'account_label' )`.

**Quién la ve:** usuarios con rol `athlete` (`FeesAccount::is_athlete()`) y estado `active` o `congelado`. Los pendientes y los de baja no ven la pestaña, y si abren la URL reciben el aviso «El ranking es para socios del club». Ningún otro usuario lo ve (R37).

**Vistas** (`RankingView`, con parámetros GET sobre la URL de la pestaña):

| Parámetros | Vista |
|---|---|
| (ninguno) | Clasificación absoluta de la temporada en curso, del sexo del socio que mira. Si no hay competiciones publicadas en ella, la última temporada con competiciones. |
| `temporada`, `vista=absoluta\|grupo`, `sexo=m\|f`, `grupo=<clave>` | Clasificación filtrada. `grupo` solo con `vista=grupo`; por defecto, el grupo del socio que mira. |
| `deportista=<user_id>` (+ `temporada`) | Ficha del deportista. |
| `competicion=<id>` | Ficha de la competición. |

**Clasificación.** Un formulario GET con los filtros (temporada, absoluta / grupo de edad, sexo, grupo) y un botón «Ver», que funciona sin JavaScript; con JavaScript, el formulario se envía al cambiar un filtro. Debajo, la tabla:

- columnas: puesto, foto y nombre (enlace a la ficha), grupo (en la vista absoluta), pruebas terminadas y total de puntos;
- la fila del socio que mira va destacada («Tú»);
- debajo, la lista de competiciones publicadas de la temporada, con enlace a cada ficha.

**Ficha del deportista:**

- cabecera con foto (`sulayr_user_photo`; si no hay, un círculo con las iniciales), nombre, sexo, grupo de la temporada, puesto en la absoluta y en su grupo, total y pruebas que cuentan («6 de 8»);
- selector de temporada;
- tabla de sus resultados de la temporada: fecha, competición (enlace), modalidad y distancia, estado, tiempo, posición en el sexo, categoría de la carrera y posición en ella, puntos en la absoluta y en el grupo, y una marca en los que cuentan;
- al pasar por los puntos o tocarlos, el desglose: «1000 × 0,8333 × 1,1 + 50».

**Ficha de la competición:**

- datos (nombre, fecha, lugar, modalidad, distancia, formato) y tiempos de referencia;
- tabla de los socios: primero los que terminaron, por posición en el sexo; después DNF, DSQ, DNS y NC. Columnas: socio (enlace), sexo, grupo, categoría de la carrera, tiempo, posiciones y puntos (absoluta y grupo). En relevos y equipos, agrupados por categoría de equipo.

### 8.2. Shortcode `[sulayr_ranking]`

`Front\RankingShortcode`: pinta lo mismo que la pestaña, con las mismas reglas de acceso, en cualquier página. Los enlaces se construyen sobre el enlace permanente de esa página. A quien no tiene acceso le muestra un aviso con un enlace a Mi cuenta para entrar. No admite atributos en esta spec. El bloque queda para otra spec (R18).

### 8.3. Plantillas sobrescribibles

`TemplateService::locate( $name )`. Si `$name` empieza por `ranking/`, busca primero `locate_template( 'sulayr/' . $name . '.php' )` (tema hijo y luego tema padre) y después `templates/`. Pasa el resultado por el filtro `sulayr_ranking_template` (`$path, $name`). Las demás plantillas del plugin no cambian. `load()` y `render()` llaman a `locate()`.

Plantillas:

- `ranking/standings.php` y `ranking/filters.php` (clasificación);
- `ranking/athlete.php` (ficha del deportista);
- `ranking/competition.php` (ficha de la competición);
- `ranking/parts/avatar.php` y `ranking/parts/points.php` (foto o iniciales; puntos y desglose);
- `ranking/notice.php` (avisos y falta de acceso).

Cada plantilla recibe un array de datos ya preparados y escapa en la salida. No hace consultas.

Ejemplo de sobrescritura: `wp-content/themes/ct-sulayr-theme/sulayr/ranking/standings.php`.

### 8.4. Estilo y diseño adaptable

- Estilos en `public/css/styles.css`, bajo `.sulayr-ranking`. No hace falta cargar nada nuevo: ese archivo ya se carga en todo el front.
- Identidad Sulayr (`docs/club/identidad-sulayr.html`) con las variables del tema:
  - tablas con la cabecera en `--sulayr-noche` y texto `--sulayr-nieve` en `--sulayr-mono`, como `.is-style-sulayr-convocatoria`;
  - filas alternas en `--sulayr-nieve`;
  - cifras (tiempos, puntos, puestos) en `--sulayr-mono`;
  - títulos en `--sulayr-display`;
  - fila propia con un filete `--sulayr-cielo`;
  - podio (puestos 1 a 3) con un filete `--sulayr-sol`;
  - texto siempre en Noche sobre fondo claro.
- Móvil (menos de 600 px): cada fila de la tabla pasa a ser una tarjeta con etiquetas (`data-label` en cada celda), sin scroll horizontal. Los filtros se apilan.
- Pantallas medianas (móvil apaisado, tableta): cada tabla va dentro de `.sulayr-ranking-scroll`, que da el borde y se desplaza en horizontal; la tabla tiene un ancho mínimo de 560 px y el nombre no se parte.
- Foto o iniciales: siempre un círculo de 32 px (72 px en la ficha), con tamaño fijo aunque el tema dé a las imágenes `height: auto` o `max-width: 100%`.
- Filas impares en blanco y pares en Nieve, también cuando el fondo de la página es Nieve.
- Caché: el servidor de PROD cachea CSS y JS siete días. `App::asset_version()` añade a la versión la fecha del archivo, así que cada subida cambia la URL y los navegadores no se quedan con la copia antigua. Se añadió el 16 de noviembre de 2026, cuando un móvil seguía mostrando la clasificación sin estilos.
- Accesibilidad: tablas con `<caption>` y `scope="col"`, la fila propia marcada también con texto («Tú»), contraste AA, filtros con `<label>`.

---

## 9. Internacionalización

- Text domain `club-triatlon-sulayr` con la constante `SULAYR_TEXTDOMAIN` en todos los textos: `__()`, `esc_html__()`, `_n()` para los plurales («1 prueba» / «N pruebas»), `_x()` cuando haga falta contexto («Media» como distancia).
- Los textos se escriben en español, como en el módulo de socios. No hace falta traducción para `es_ES`. Si se regenera `languages/es_ES.po`, entran también los textos nuevos.
- `/* translators: */` en cada `sprintf()` con marcadores.
- Los nombres de modalidad, distancia, formato, estado y categoría de equipo salen de un único método `labels()` por lista. En la base de datos se guardan claves, nunca textos.
- Números: `number_format_i18n( $puntos, 2 )` (1.050,00). Fechas: `date_i18n( 'd/m/Y' )`. Tiempos: `RaceTime::format()` (`h:mm:ss`), que no depende del idioma.

---

## 10. Plan de tests

### 10.1. PHPUnit (cálculo)

- PHPUnit 9.6 (el último que funciona en PHP 7.4) en `wp-content/plugins/club-triatlon-sulayr/tests/`, con su propio `tests/composer.json` y `tests/vendor/` en `.gitignore`. Así el `vendor/` que se sube a PROD no cambia y no hay que acordarse de `--no-dev` (R22).
- `tests/bootstrap.php` carga `../vendor/autoload.php` (PSR-4 del plugin) y no carga WordPress. Por eso las clases de `Ranking/Calc/` no pueden usar funciones de WordPress.
- Para lanzarlo: `cd wp-content/plugins/club-triatlon-sulayr/tests && composer install && vendor/bin/phpunit`.
- En local no hay PHP 7.4: los tests se lanzan con el PHP del sistema (8.x), y la compatibilidad con 7.4 se comprueba revisando el código (sin `match`, tipos unión, `mixed`, `?->`, promoción en constructores ni `enum`).

Casos con números (base 1000, bonus 50). Los puntos se redondean a 2 decimales al final:

| # | Caso | Datos | Resultado esperado |
|---|---|---|---|
| T1 | Triatlón sprint, por tiempo | Ganador M 1:00:00 (3600 s), socio 1:12:00 (4320 s), coef. 1,1 | 1000 × 0,833333 × 1,1 + 50 = **966,67** |
| T2 | Olímpico, por tiempo | Ganadora F 2:05:00 (7500 s), socia 2:30:00 (9000 s), coef. 1,2 | 1000 × 0,833333 × 1,2 + 50 = **1050,00** |
| T3 | Ganador de la prueba | Socio = ganador 1:00:00, posición en el sexo 1, sprint 1,1 | 1000 × 1 × 1,1 + 50 = **1150,00** |
| T4 | Media distancia | Ganador 4:00:00 (14400 s), socio 5:20:00 (19200 s), coef. 1,4 | 1000 × 0,75 × 1,4 + 50 = **1100,00** |
| T5 | Larga distancia | Ganador 8:30:00 (30600 s), socio 12:45:00 (45900 s), coef. 1,6 | 1000 × 0,666667 × 1,6 + 50 = **1116,67** |
| T6 | Respaldo por posición | Duatlón sprint (1,0), sin tiempo, posición 11 de 51 | 1000 × (1 − 10/51) × 1,0 + 50 = **853,92** |
| T7 | Único participante | Sin tiempo, posición 1 de 1, acuatlón 1,0 | 1000 × 1 + 50 = **1050,00** |
| T8 | Último por posición | Posición 51 de 51, coef. 1,0 | 1000 × (1 − 50/51) + 50 = **69,61** |
| T9 | Grupo de edad | Ganador V1 M 1:05:00 (3900 s), socio 1:12:00 (4320 s), sprint 1,1 | Grupo: 1000 × 0,902778 × 1,1 + 50 = **1043,06**; absoluta (ganador 1:00:00) = 966,67 |
| T10 | DNF, DSQ, DNS y NC | Cualquier dato | **0** en las dos, sin bonus, método `estado` |
| T11 | Sin referencia del grupo | Referencia absoluta sí, de grupo no | Absoluta calculada; grupo **NULL** y aviso |
| T12 | Sin referencias ni posición | «Finalizado» con tiempo, sin referencias | **0** y aviso |
| T13 | Mejores 6 de 8 | Puntos 1150; 966,67; 0 (DNF); 853,92; 1050; 1100; 1116,67; 900 | Cuentan 1150 + 1116,67 + 1100 + 1050 + 966,67 + 900 = **6283,34**; terminadas 7 |
| T14 | Menos de N | 3 resultados: 900, 800, 700; N = 6 | **2400,00** |
| T15 | Desempate por terminadas | A: 3000 con 4 terminadas; B: 3000 con 5 | B 1.º, A 2.º |
| T16 | Desempate por mejor resultado | A y B: 3000 y 5; mejor A 1100, B 1050 | A 1.º |
| T17 | Empate total | A y C iguales en los tres criterios, D por detrás | Puestos 1, 1, 3 |
| T18 | Grupo de edad por año | Nacido el 31/12/1986, temporada 2027 → 41 años | **veterano-1** |
| T19 | Límite de grupo | Nacido el 01/01/1988, temporada 2027 → 39 años | **senior** |
| T20 | Sin fecha de nacimiento | Fecha vacía | Grupo vacío |
| T21 | Validación: más rápido que el ganador | Ganador 1:00:00, socio 0:59:59 | Error |
| T22 | Validación: empate con el ganador | Socio 1:00:00 = ganador, posición en el sexo 2 | Error; con posición 1, válido |
| T23 | Validación: posición imposible | Posición 52 con 51 participantes | Error |
| T24 | `RaceTime` | `1:02:03` → 3723; `62:03` → 3723; `1:60:00` → null; 3723 → `1:02:03` | Como se indica |
| T25 | Coeficiente que no está en la tabla | Triatlón supersprint con los valores por defecto | 1,0 |
| T26 | Categoría AB (R29) | Socio `ABM` de 30 años, 1:30:43 = ganador absoluto; primero `SNM` 1:34:35; duatlón cros sprint 1,0 | Absoluta 1000 × 1 × 1,0 + 50 = **1050,00**; grupo Sénior **1050,00** (método `absoluta`), sin error aunque 1:30:43 < 1:34:35 |
| T27 | Relevos (R30) | Mejor hombre de la clasificación 0:19:58 (1198 s), socio 0:21:33 (1293 s), duatlón supersprint 1,0 | 1000 × 0,926527 + 50 = **976,53** |
| T28 | Equipos (R31) | Equipo ganador 1:00:00, equipo del socio 1:06:40 (4000 s), triatlón sprint 1,1 | 1000 × 0,9 × 1,1 + 50 = **1040,00** en la absoluta y en el grupo |
| T29 | Aviso de categoría (R28) | Calculado Veterano 1, código `V2M` | Se guarda, con aviso |
| T30 | Código de categoría inválido | `V1F` para un socio masculino, o `XX` | Error |
| T31 | Grupo de edad nuevo | Nacida el 10/03/2011, temporada 2027 → 16 años | **juvenil** |
| T32 | Baremo por temporada (R35) | Resultado de 2027 con sprint 1,1 = 966,67; en 2028 el sprint pasa a 1,2 | El resultado de 2027 sigue en **966,67**; uno igual de 2028 da 1000 × 0,833333 × 1,2 + 50 = **1050,00** |

### 10.2. Pruebas con WordPress (`wp eval-file`, en local)

Script `tests/wp/check-ranking.php`, que no se sube a PROD:

- **Esquema:** las cuatro tablas existen con sus índices, y `sulayr_ranking_db_version` vale 1. Volver a lanzar `install()` no cambia datos.
- **Roles:** `sulayr_ranking_manager` existe con `read`, `view_admin_dashboard` y `sulayr_manage_ranking`; `administrator` la tiene; `sulayr_member_manager` y `athlete` no.
- **Servicio:** crea una competición con 3 socios de prueba, recalcula, comprueba los puntos de T1 a T3 y la clasificación; pasa un socio a baja y comprueba que desaparece del front y que la caché se invalida.
- **Registro:** cambiar un tiempo deja una entrada con los valores anterior y nuevo; borrar la competición deja la fila en JSON.

### 10.3. Pruebas manuales

En local y en PROD, con un gestor de ranking de prueba. Se apuntan en `docs/club/ranking-interno/pruebas.md`.

**Criterios de aceptación:**

1. Un gestor de ranking ve el menú «Ranking» y ningún menú de «Socios». Un gestor de socios no ve «Ranking».
2. Un socio sin sexo no se puede elegir, y aparece con un aviso en «Deportistas».
3. Tiempos imposibles (T21 a T23) dan error en la fila correcta y no se guarda nada.
4. No se puede publicar una competición con referencias por rellenar.
5. Cambiar un coeficiente recalcula los puntos y deja el cambio en el registro.
6. En Mi cuenta, un socio activo y uno congelado ven la pestaña; uno de baja no la ve, y su URL directa da el aviso.
7. Las fichas de deportista y de competición se abren desde la clasificación. La de un socio de baja da «Deportista no encontrado».
8. En el móvil (360 px), la clasificación y las fichas se leen sin scroll horizontal.
9. El shortcode en una página muestra lo mismo que la pestaña, y a quien no ha entrado le pide entrar.
10. Una plantilla copiada en `ct-sulayr-theme/sulayr/ranking/` se usa en lugar de la del plugin.
11. Importación (sección 14): un JSON con errores no importa nada y lista los errores; uno válido muestra los conjuntos unificados, las dudas y los socios encontrados.
12. Importación: no se puede rellenar la competición sin decidir las dudas, ni con un socio en dos filas.
13. Importación: «Rellenar la competición» abre «Añadir competición» con datos, resultados y referencias rellenos y sin guardar; al guardar pasa las validaciones de siempre.
14. Un gestor de socios no ve «Importar resultados» y su URL da 403.

---

## 11. Plan de implementación

Cada paso es pequeño, se prueba en local y deja `main` estable. Los pasos se numeran R0 a R7, como D0 a D7 en documentos.

| Paso | Contenido | Entregable y comprobación |
|---|---|---|
| R0 | Sexo del socio: `CustomFields`, `MemberFields`, `MemberLog`, `JoinForm`/`JoinApproval` y ficha de «Socios». | Alta de prueba con sexo; aparece en la ficha y en el registro del socio. Antes de cerrar el paso, actualizar SPEC-ALTAS. |
| R1 | Esqueleto: `RankingSchema`, `MemberRoles` (rol, capacidad, versión 2), `BrandStyles`, menú «Ranking» vacío, `RankingSettings` y `RankingLog`. | Tablas creadas solas en `init`; rol visible en la ficha de usuario; ajustes guardados con valores por defecto; registro de los cambios de ajustes. |
| R2 | Cálculo puro: `RaceTime`, `AgeGroups`, `PointsCalculator`, `Standings`, `ResultValidator` y PHPUnit. | T1 a T25 en verde. |
| R3 | Competiciones y resultados: `Competitions`, `Results`, `CompetitionsPage`, `CompetitionEditPage`, `ranking-results.js` y recálculo por competición. | Flujo de la sección 4.3 completo en local; validaciones con errores por fila; registro por campo. |
| R4 | Clasificación: `RankingRoster`, `RankingService::standings()` con caché, `StandingsPage`, `AthletesPage`, «Recalcular todo» y hooks de la sección 3.7. | Clasificación absoluta y por grupo correcta con datos de prueba; un socio de baja desaparece; script `check-ranking.php` en verde. |
| R5 | Front: `RankingAccount`, `RankingView`, `RankingShortcode`, plantillas, `TemplateService::locate()` y CSS. | Criterios 6 a 10 de la sección 10.3 en local, en escritorio y en móvil. |
| R6 | Manual del gestor (`MANUAL-RANKING.md`, como `MANUAL-TIENDA.md`), con capturas, y paso de documentación (`FASES.md`, esta spec, `AGENT.md`). | Manual revisado por el club. |
| R7 | Despliegue en PROD con FileZilla: subir primero los archivos nuevos, después los modificados. Las tablas y el rol se crean solos al cargar wp-admin. Rellenar el sexo de los socios en «Ranking → Deportistas». Dar el rol al gestor desde su ficha de usuario. Comprobar la pestaña de Mi cuenta (enlaces regenerados por la versión del endpoint). | Criterios 1 a 9 comprobados en PROD; estado actualizado en todos los documentos ese día. |

R0 se puede subir a PROD solo, antes que el resto, para que las altas nuevas ya traigan el sexo.

Carpeta de trabajo para el despliegue y las pruebas: `docs/club/ranking-interno/`, como `docs/club/altas-y-ciclo-de-vida-socio/`.

---

## 12. Preguntas abiertas

Ninguna. Cerradas el 16 de noviembre de 2026: P1 (R3), P2 (R30, R31 y R33), P3 (R34), P4 (R35), P5 (R38), P6 (R36) y P7 (R37).

---

## 13. Implementación (rama `ranking-interno`, mergeada en `main` el 16 de noviembre de 2026)

Hecha el 16 de noviembre de 2026 en la rama `ranking-interno`, que sale de `main` (`e21a47f50`), y mergeada en `main` ese mismo día (`7fb03aba1`). Código en `wp-content/plugins/club-triatlon-sulayr/src/Modules/Ranking/`, cargado desde `App::load_ranking()`.

### 13.1. Estado de los pasos

| Paso | Estado |
|---|---|
| R0 | Hecho. Sexo en la ficha, en el formulario de alta y en «Nueva alta» (obligatorio), en la ficha de Socios y en el historial del socio. SPEC-ALTAS §4.1 lo menciona. |
| R1 | Hecho. Esquema versión 1 (cuatro tablas), rol «Gestor de ranking» con `MemberRoles` versión 2, menú «Ranking» con la identidad Sulayr, ajustes y registro. |
| R2 | Hecho. 47 tests PHPUnit (T1 a T32 y casos de validación) en verde con PHPUnit 9.6 sobre PHP 8.5. |
| R3 | Hecho. Alta, edición, publicación y borrado de competiciones; tabla de resultados; referencias; recálculo por competición. |
| R4 | Hecho. Clasificación con caché, Deportistas, Clasificación (con los socios de baja aparte) y «Recalcular todo». |
| R5 | Hecho. Pestaña «Ranking interno» de Mi cuenta, shortcode `[sulayr_ranking]`, plantillas sobrescribibles y estilos. Falta ver el móvil en un navegador (criterio 8). |
| R6 | Hecho, sin capturas: `MANUAL-RANKING.md`. Las capturas quedan pendientes hasta tener datos reales. |
| R7 | Código subido a PROD el 16 de noviembre de 2026 y configuración hecha (ajustes, sexos y rol del gestor). Pendientes: criterios 1 a 9 en PROD. Registro en [ranking-interno/despliegue-prod.md](ranking-interno/despliegue-prod.md). |

### 13.2. Comprobaciones hechas en local

- `cd wp-content/plugins/club-triatlon-sulayr/tests && composer install && vendor/bin/phpunit`: 47 tests, 115 aserciones.
- `wp eval-file wp-content/plugins/club-triatlon-sulayr/tests/wp/check-ranking.php`: 42 comprobaciones (esquema, roles, puntos T1, T3 y T9, categoría AB, borrador frente a publicada, caché, registro, cambio de fecha de nacimiento, baja y vuelta, baremo por temporada, vistas del front y de wp-admin, borrado). Crea sus propios socios y lo borra todo al terminar.
- Los manejadores de los formularios (`handle_save`, `handle_delete`, guardar sexos), con errores por fila, publicación bloqueada por referencias, cambio de formato bloqueado y 403 sin capacidad.
- Por HTTP con sesión: la pestaña de Mi cuenta de un socio, el menú «Ranking» de un gestor de ranking (sin el menú «Socios») y el 403 de un deportista sin rol.
- Ejecutadas el 16 de noviembre de 2026 contra el WordPress local: PHPUnit 47 de 47 y `check-ranking.php` 42 de 42. Las correcciones de la revisión (formato bloqueado con resultados, socio sin sexo fuera del cálculo, ROLLBACK si falla una escritura, baremos de lectura sin copia) todavía no tienen comprobación automática.

### 13.3. Diferencias con el texto de la spec

- **Clases añadidas:** `Calc\Keys` (claves cerradas), `Calc\Baremo` (baremo por defecto, normalización y coeficiente), `Labels` (textos visibles de las claves) y `Admin\Notices` (avisos y estado de formularios). No cambian el comportamiento.
- **El listado de competiciones** está en `Admin\RankingMenu`, no en una clase `CompetitionsPage` aparte.
- **El registro** tiene una columna `note` (sección 2.3) para el motivo de los recálculos.
- **Cambios de sexo o fecha de nacimiento:** se recalculan al final de la petición (`shutdown`), una vez por socio, aunque se cambien varios campos.
- **Desempate por nombre:** se ordena por apellidos sin tildes (`remove_accents()`), porque la comparación de textos no entiende de acentos.
- **Edición de una competición publicada:** si al guardarla faltan referencias o se quedan sin resultados, vuelve a borrador con un aviso, para que no cuente a medias.
- **Tests:** PHPUnit está en `tests/` con su propio `composer.json` y `vendor/` ignorado por git. No se sube a PROD.
- **Correcciones tras la revisión contra esta spec:** formato bloqueado si ya hay resultados (§4.2); socio sin sexo con resultados fuera del cálculo y con aviso hasta rellenar el sexo (§2.1); lectura de baremos sin crear copias (§2.6); cada escritura del formulario comprueba su resultado y, si falla, hace ROLLBACK y no guarda nada (§4.2); 5 filas vacías, resumen de errores por fila y socios ya usados fuera de los desplegables (§4.2); orden DNF, DSQ, DNS y NC al final (§4.2).

---

## 14. Importación desde JSON (R39 a R41)

Añadida el 16 de noviembre de 2026, con la primera competición real (I Duatlón por relevos 3X Úbeda Nazarí, 15 de noviembre de 2026). Rama `ranking-importar`, mergeada en `main` y subida a PROD ese día.

### 14.1. Flujo

1. El gestor descarga los PDF oficiales: en individual, la general de cada sexo; en relevos, todas las clasificaciones con socios (federado y open, masculina, femenina y mixta).
2. Una IA los convierte en un JSON (14.2), de una de estas dos formas:
   - Con la skill `ranking-importar` (`.claude/skills/ranking-importar/`). Es autónoma: sirve en Claude Code (dentro del repositorio, `/ranking-importar`) y en la app de escritorio o claude.ai (se sube como skill en un .zip de esa carpeta). Recibe los PDF adjuntos o con su ruta, extrae todos los deportistas, valida el JSON con `scripts/validar.py` (Python 3 sin dependencias, mismas reglas que `ImportParser` e `ImportMerger`) y entrega `resultados.json` para descargar: en la app, en `/mnt/user-data/outputs/`; en Claude Code, junto a los PDF. No guarda nada en el repositorio.
   - En claude.ai: se adjuntan los PDF y se pega el prompt que enseña la pantalla.
3. **Ranking → Importar resultados**: se pega el JSON o se sube el archivo y se pulsa «Analizar». La pantalla tiene las instrucciones y el prompt con un botón para copiarlo. También hay un botón «Importar resultados» en Competiciones y un enlace en «Añadir competición».
4. Revisión (14.4): conjuntos unificados con su ganador y sus finalizados, dudas, socios encontrados y deportistas de equipos del club sin socio. Avisa si ya hay una competición con esa fecha.
5. «Rellenar la competición» abre «Añadir competición» con datos, resultados y referencias rellenos, **sin guardar** (estado del formulario de `Admin\Notices`). Desde ahí se sigue el flujo de 4.3: «Guardar», revisar puntos, «Guardar y publicar».

La importación en curso se guarda dos horas por usuario (transient `sulayr_ranking_import_<user_id>`). «Descartar e importar otro JSON» la borra. No se guarda nada en las tablas del ranking hasta que el gestor pulsa «Guardar» en la competición.

### 14.2. Formato del JSON

La definición para la IA está en `wp-content/plugins/club-triatlon-sulayr/admin/ranking-import-prompt.md`, que la web enseña tal cual. La skill lleva una copia idéntica (`.claude/skills/ranking-importar/formato.md`) para funcionar fuera del repositorio; `ImportSkillTest` comprueba que son iguales y que `scripts/validar.py` da los mismos errores, ganadores y finalizados que la web. La lee y la valida `Calc\ImportParser` (sin WordPress).

```json
{
  "schema": "sulayr-ranking-import/1",
  "competition": { "name": "…", "race_date": "2026-11-15", "location": "Úbeda", "modality": "duatlon", "distance": "supersprint", "format": "relevos" },
  "classifications": [
    { "title": "OPEN MASCULINA", "team_category": "m", "finishers": null,
      "athletes": [ { "pos": 1, "name": "…", "club": "…", "cat": "JVM", "pos_cat": 1, "time": "0:19:58", "status": "finalizado" } ] }
  ]
}
```

- Lleva **todos** los deportistas de cada clasificación, no solo los socios: hacen falta para el ganador, el primero de cada categoría y los finalizados.
- `format`: `individual` o `relevos`. La contrarreloj por equipos no se importa todavía: se mete a mano.
- `team_category`: en relevos, `m`, `f` o `mixto`; en individual se ignora.
- `finishers`: el «FINALIZADOS» de la cabecera, si lo hay. Si no, se cuentan los deportistas con estado `finalizado`.
- `sex`: sale de la última letra de `cat`. Solo hace falta si no hay categoría.
- `status`: `finalizado`, `dnf`, `dsq`, `dns` o `nc`. Sin terminar, no lleva tiempo ni posiciones.
- Admite el JSON dentro de un bloque de código (```` ```json ````), porque es como suele contestar la IA.

### 14.3. Unificación (R39)

`Calc\ImportMerger::merge()` junta a los deportistas por categoría de equipo y sexo (en individual, solo por sexo), venga cada uno de la clasificación que venga. En cada conjunto:

- `Pos` = 1 + los que tienen un tiempo menor; `Pos Cat` = 1 + los de su misma «Cat» con un tiempo menor. Con un solo PDF coincide con la clasificación oficial, salvo en los empates, que comparten posición.
- Ganador: el tiempo más rápido. Primero de categoría: el más rápido con esa «Cat».
- Finalizados: la suma de los `finishers` de sus clasificaciones si todas los traen y son de un solo sexo; si no, los que hay en el JSON. Si no cuadran, la revisión lo avisa (suele ser que falta una página).
- Si un deportista terminó sin tiempo y su clasificación se ha unido a otra, se queda con la «Pos» del PDF y sale un aviso para revisarla.

`ImportMerger::build_input()` prepara el formulario: las filas de los socios elegidos y las referencias que pide `ResultValidator::required_references()`. La del grupo de edad sale del código de la federación del grupo (Ajustes → Grupos de edad): `V1` + sexo → `V1M`. Si el grupo no tiene código, o nadie de esa categoría tiene tiempo (por ejemplo, la carrera clasifica al socio en otra categoría, R28), esa referencia queda vacía con un aviso y se rellena a mano.

### 14.4. Emparejamiento por nombre (R41)

`Calc\NameMatcher`, sin WordPress. Compara sin tildes, sin mayúsculas, sin palabras de enlace (`de`, `del`, `al`, `la`…) y sin importar el orden. Solo se emparejan socios activos o congelados con sexo, y del mismo sexo que el deportista.

| Resultado | Cuándo |
|---|---|
| **Seguro** (viene elegido) | Un único socio con el nombre idéntico; o, si corre en un equipo del club, un único socio con todos sus apellidos y su nombre de pila en el nombre del PDF (el PDF puede traer un segundo nombre). Además, la «Cat» no contradice su grupo de edad. |
| **Duda** (hay que decidir) | Hay parecido pero no basta: falta un nombre o un apellido, una letra de diferencia (palabras de 5 o más), varios socios posibles, la categoría no cuadra, el mismo socio sale en dos filas, o el parecido es por apellidos y nombre en un equipo de otro club. |
| **Ninguno** | Sin parecido. Si corre en un equipo del club, se lista con «No es socio» elegido; si no, solo se cuenta. |

Un equipo es del club si la columna «Club» contiene «SULAYR» (filtro `sulayr_ranking_import_club_keywords`). En los equipos de otros clubes no se enseñan los parecidos flojos, para no llenar la revisión de tocayos.

En cada fila, el desplegable ofrece los candidatos, «No es socio» y el resto de socios del mismo sexo. Antes de rellenar, la web comprueba que no quedan dudas sin decidir, que ningún socio está en dos filas y que todos los elegidos se pueden usar.

### 14.5. Código

| Archivo | Qué hace |
|---|---|
| `src/Modules/Ranking/Calc/ImportParser.php` | Lee y valida el JSON. |
| `src/Modules/Ranking/Calc/ImportMerger.php` | Unifica clasificaciones y prepara el formulario. |
| `src/Modules/Ranking/Calc/NameMatcher.php` | Emparejamiento por nombre. |
| `src/Modules/Ranking/Admin/ImportPage.php` | Pantalla «Importar resultados» (instrucciones, análisis, revisión y relleno). |
| `admin/ranking-import-prompt.md` | Prompt y formato para la IA. |
| `RankingRoster::info()` | Ahora también da `first` y `last` (nombre y apellidos por separado). |
| `Labels::team_categories()` | «Masculina», «Femenina» y «Mixta» (R39). Las claves guardadas no cambian. |
| `.claude/skills/ranking-importar/` | Skill autónoma (`SKILL.md`, `formato.md` y `scripts/validar.py`) para Claude Code, la app de escritorio y claude.ai. No se sube a PROD. |
| `tests/Unit/ImportSkillTest.php` | Comprueba que la skill y la web no se separan (formato y validación). |

Comprobado en local el 16 de noviembre de 2026:

- 72 tests PHPUnit: 25 nuevos en `ImportTest`, `NameMatcherTest` e `ImportSkillTest`.
- `check-ranking.php` en verde.
- Por HTTP con sesión de administrador, con el JSON de Úbeda (`docs/club/ranking-interno/i-duatlon-por-relevos-3x-ubeda-nazari/resultados.json`, 75 deportistas; esa carpeta, con los PDF, no se versiona): JSON con errores rechazado con la lista; análisis con 21 socios seguros, 2 dudas y 1 deportista del club sin socio; rechazo con dudas sin decidir; formulario relleno con 23 socios y 7 de 8 referencias; guardado en borrador con los puntos calculados.
- La referencia que faltaba es la de Júnior femenina: la carrera clasifica a esa socia como 23F y la web como Júnior, y no había ninguna JNF.
- La competición de prueba se borró después.

