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

---

# Ampliación: importación desde JSON (rama `ranking-importar`)

Escrito el 16 de noviembre de 2026. Es la sección 14 de la spec (R39 a R41). **Estado:** subida a PROD el 16 de noviembre de 2026. Paso C en curso (importando Úbeda). No cambia el esquema de la base de datos ni los ajustes.

Rutas relativas a `wp-content/plugins/club-triatlon-sulayr/`.

## Paso A. Subir lo nuevo

Nada de esto se carga hasta el paso B.

- `src/Modules/Ranking/Calc/ImportParser.php`
- `src/Modules/Ranking/Calc/ImportMerger.php`
- `src/Modules/Ranking/Calc/NameMatcher.php`
- `src/Modules/Ranking/Admin/ImportPage.php`
- `admin/ranking-import-prompt.md`

## Paso B. Sobrescribir lo modificado

En este orden:

1. `src/Modules/Ranking/Calc/Keys.php` (solo un comentario)
2. `src/Modules/Ranking/Labels.php` («Masculina», «Femenina», «Mixta»)
3. `src/Modules/Ranking/RankingRoster.php` (nombre y apellidos por separado)
4. `src/Modules/Ranking/Admin/CompetitionEditPage.php` (enlace a la importación)
5. `src/Modules/Ranking/Admin/RankingMenu.php` (el último: es el que carga la pantalla nueva)

No hay archivos que borrar. No subas `tests/` ni `.claude/`.

## Paso C. Comprobar

1. **Ranking → Importar resultados** se abre, con las instrucciones y el prompt. En «Competiciones» está el botón «Importar resultados».
2. Pega el `resultados.json` de Úbeda (está en local, en `docs/club/ranking-interno/i-duatlon-por-relevos-3x-ubeda-nazari/`, que no se versiona), «Analizar», revisa las dudas y «Rellenar la competición». Debe abrirse «Añadir competición» relleno.
3. Revisa los resultados y las referencias. Si todo cuadra, guarda; si no, no guardes nada.
4. Con la cuenta del gestor de socios: no ve «Importar resultados».
5. Marca los criterios 11 a 14 en `pruebas.md`, columna PROD.

Si algo falla, vuelve a subir el `RankingMenu.php` de `main`: la pantalla deja de cargarse y lo demás sigue igual.

---

# Ajuste: estilos del ranking en el móvil

Escrito el 16 de noviembre de 2026. Sección 8.4 de la spec. **Estado:** en `main`, sin subir a PROD.

En un móvil la clasificación se veía sin estilos: iniciales sin círculo, fotos enormes, sin cabecera ni filas alternas. El CSS estaba en PROD, pero el móvil usaba la copia antigua de `styles.css?ver=0.3.0` (el servidor la cachea siete días). Ahora la versión lleva la fecha del archivo. También se añade scroll horizontal a las tablas en pantallas medianas y el avatar tiene un tamaño fijo.

Rutas relativas a `wp-content/plugins/club-triatlon-sulayr/`. Sobrescribe, en este orden:

1. `public/css/styles.css`
2. `templates/ranking/standings.php`
3. `templates/ranking/athlete.php`
4. `templates/ranking/competition.php`
5. `src/App/App.php` (el último: es el que cambia la versión de los archivos)

Comprobar:

1. En el código fuente de la web, `styles.css?ver=` lleva un número largo detrás de `0.3.0`.
2. En el móvil, en vertical: tarjetas; en apaisado: tabla con cabecera Noche, filas alternas y scroll horizontal si no cabe. Iniciales y fotos en un círculo pequeño.
3. Criterio 8 de `pruebas.md`.

# Ampliación: bonus por interés del club

Escrito el 17 de noviembre de 2026. Sección 15 de la spec (R42 y R43). **Estado:** en local, sin subir a PROD.

Sube la versión del esquema a 2: la primera petición a la web añade tres columnas (`interest_bonus` e `interest_label` en competiciones, `interest_bonus` en resultados). No hay datos que migrar: las competiciones que ya hay quedan sin bonus y sus puntos no cambian.

Si aún no has subido el «Ajuste: estilos del ranking en el móvil», súbelo a la vez: comparte `styles.css` y las plantillas, y estas versiones ya lo incluyen.

Rutas relativas a `wp-content/plugins/club-triatlon-sulayr/`. Sobrescribe, en este orden:

1. `src/Modules/Ranking/Database/RankingSchema.php`. Después, abre cualquier página de wp-admin para que se añadan las columnas.
2. `src/Modules/Ranking/Calc/Baremo.php`
3. `src/Modules/Ranking/Calc/PointsCalculator.php`
4. `src/Modules/Ranking/Calc/ResultValidator.php`
5. `src/Modules/Ranking/Calc/ImportMerger.php`
6. `src/Modules/Ranking/Competitions.php`
7. `src/Modules/Ranking/Results.php`
8. `src/Modules/Ranking/RankingService.php`
9. `src/Modules/Ranking/RankingSettings.php`
10. `src/Modules/Ranking/Labels.php`
11. `src/Modules/Ranking/Admin/CompetitionEditPage.php`
12. `src/Modules/Ranking/Admin/ImportPage.php`
13. `src/Modules/Ranking/Admin/RankingMenu.php`
14. `src/Modules/Members/Admin/BrandStyles.php`
15. `src/Modules/Ranking/Front/RankingView.php`
16. `templates/ranking/standings.php`
17. `templates/ranking/athlete.php`
18. `templates/ranking/competition.php`
19. `public/css/styles.css`
20. `src/App/App.php` (solo si no subiste el ajuste de estilos)

Comprobar:

1. **Ranking → Ajustes → Bonus por interés**: salto 50 y máximo 300. Pulsa «Guardar ajustes» una vez para que la temporada guarde esos valores.
2. **Ranking → Competiciones**: Úbeda sigue con los mismos puntos y sin etiqueta.
3. Abre una competición: debajo de «Formato» salen «Bonus por interés del club» y «Motivo del bonus».
4. Criterios 15 y 16 de `pruebas.md`. Para el 15 se puede usar Úbeda: pon +50, comprueba que los puntos suben 50 y vuelve a dejarla en «Normal (sin bonus)».
