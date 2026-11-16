# Despliegue en PROD — Ranking interno

Proceso para subir con FileZilla el ranking interno de [SPEC-RANKING-INTERNO.md](../SPEC-RANKING-INTERNO.md) (paso R7). PROD no tiene SSH ni WP-CLI: todo lo que hace falta se hace desde wp-admin.

Escrito el 16 de noviembre de 2026, antes de subirlo. El código (pasos R0 a R6) está en `main` desde ese día (merge `7fb03aba1`). La lista de abajo es el cambio neto entre el `main` anterior (`e21a47f50`) y el actual, tal como se subió.

Todas las rutas son relativas a `wp-content/plugins/club-triatlon-sulayr/` en el servidor.

**Estado (16 de noviembre de 2026):** código subido a PROD y paso 4 hecho (ajustes, sexos y rol de gestor). Faltan las comprobaciones del paso 5. Ver «Registro del despliegue» al final.

## Antes de empezar

1. **Código cerrado.** Mergear `ranking-interno` en `main` y desplegar desde `main`.
2. **Copia de seguridad.** hPanel → Copias de seguridad (archivos y base de datos), o descargar la carpeta del plugin con FileZilla y exportar la base desde phpMyAdmin.
3. **Hora tranquila.** Mientras se sube, la web puede dar error unos segundos si un archivo llega antes que otro del que depende.
4. **No subir** `tests/` (PHPUnit y el script de comprobación solo sirven en local).

## Paso 1. Subir lo nuevo

Nada de esto se usa hasta el paso 2, así que subirlo no cambia la web.

Carpetas enteras:

- `src/Modules/Ranking/` (con `Admin/`, `Calc/`, `Database/` y `Front/`)
- `templates/ranking/` (con `parts/`)

Archivo suelto:

- `admin/js/ranking-results.js`

## Paso 2. Sobrescribir lo modificado

En este orden (primero lo que no carga nada nuevo):

1. `src/Infrastructure/CustomFields.php`
2. `src/Services/TemplateService.php`
3. `src/Modules/Members/MemberFields.php`
4. `src/Modules/Members/MemberLog.php`
5. `src/Modules/Members/MemberRoles.php`
6. `src/Modules/Members/Admin/BrandStyles.php`
7. `src/Modules/Members/Admin/MembersMenu.php`
8. `src/Modules/Members/Join/JoinForm.php`
9. `src/Modules/Members/Join/JoinApproval.php`
10. `public/css/styles.css`
11. `src/App/App.php` (el último: es el que carga el módulo)

No hay archivos que borrar.

## Paso 3. Primera carga

Entra en wp-admin como administrador. En esa carga:

- se crean las cuatro tablas `wp_sulayr_ranking_*` (opción `sulayr_ranking_db_version` = 1);
- se crea el rol «Gestor de ranking» y los administradores reciben `sulayr_manage_ranking` (opción `sulayr_member_roles_version` = 2);
- aparece el menú **Ranking** con Competiciones, Añadir competición, Clasificación, Deportistas, Registro y Ajustes;
- la primera visita a la web regenera los enlaces de Mi cuenta (pestaña **Ranking interno**).

Comprueba que **Ranking** se abre sin errores y que **Socios** sigue igual.

## Paso 4. Configurar

1. **Ranking → Ajustes**: revisa puntos base (1000), bonus (50), N (6), coeficientes y grupos de edad. Si todo está bien, no hace falta guardar: se usan los valores por defecto.
2. **Ranking → Deportistas**: rellena el sexo de todos los socios y pulsa **Guardar sexos**. Revisa los avisos de socios sin fecha de nacimiento (la fecha la pone un gestor de socios o un administrador).
3. Da el rol al gestor: **Usuarios** → su ficha → **Roles adicionales del club** → marca **Gestor de ranking** → **Actualizar usuario**.

## Paso 5. Comprobar

Con los criterios 1 a 9 de la sección 10.3 de la spec. Lo mínimo:

1. Con la cuenta del gestor de ranking: ve **Ranking** y no ve **Socios**.
2. Mete una competición real en borrador, con sus referencias, y publícala.
3. Con la cuenta de un socio activo: **Mi cuenta → Ranking interno** muestra la clasificación, su ficha y la de la competición. En el móvil se lee sin scroll horizontal.
4. El formulario público de alta pide el sexo.

Si algo falla, desactiva el módulo volviendo a subir el `src/App/App.php` de `main` anterior. Las tablas y los datos se quedan; no molestan.

## Después

Actualiza ese mismo día el estado en `docs/FASES.md`, en la spec (cabecera y sección 13.1), en este documento y en `AGENT.md`.

## Registro del despliegue

- **16 de noviembre de 2026:** código de `main` (`7fb03aba1`) subido a PROD con FileZilla. También el `style.css` del tema, con el botón «Hazte socio» sin subrayado y en negrita. Comprobado en PROD: el CSS nuevo está servido.
- **Corregido: `admin/js/ranking-results.js`.** A las 18:55 del 16 de noviembre de 2026 PROD respondía 404 en esa URL. Subido después, y comprobado: responde 200 y su SHA-1 (`20d145d5…`) es el de `main`.
- **Hecho: paso 4** (16 de noviembre de 2026). Ajustes revisados, sexos rellenados en «Ranking → Deportistas» y rol de gestor dado.
- **Pendiente: paso 5** (criterios 1 a 9). Se marcan en `pruebas.md`, columna PROD, solo cuando estén hechos.
