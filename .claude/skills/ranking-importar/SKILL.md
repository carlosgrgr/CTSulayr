---
name: ranking-importar
description: Convierte los PDF de clasificaciones oficiales de la federación en el JSON que importa Ranking → Importar resultados (ranking interno del Club Triatlón Sulayr). Úsala cuando haya PDFs de una competición en docs/club/ranking-interno/<prueba>/, o cuando pidan pasar una clasificación a JSON para el ranking.
argument-hint: "<carpeta de la prueba dentro de docs/club/ranking-interno/>"
---

# Importar una competición al ranking interno

Genera `resultados.json` a partir de los PDF de una competición. Después, un gestor lo pega en **wp-admin → Ranking → Importar resultados**. La web empareja a los deportistas con los socios y unifica las clasificaciones. Esta skill solo extrae y comprueba.

Las reglas de negocio están en `docs/club/SPEC-RANKING-INTERNO.md` §14 (R39 y R40).

## Pasos

1. **Localiza los PDF.** El argumento es la carpeta de la prueba: `docs/club/ranking-interno/<argumento>/`. Si no se da, lista `docs/club/ranking-interno/` y pregunta cuál. Usa todos los `.pdf` de la carpeta.

2. **Lee las instrucciones del formato.** Lee `wp-content/plugins/club-triatlon-sulayr/admin/ranking-import-prompt.md`. Es el mismo prompt que la web enseña para claude.ai y es la definición del JSON. Síguelo al pie de la letra.

3. **Lee cada PDF entero** con Read, todas las páginas.
   - Los PDF de más de 10 páginas necesitan `pages`, y eso requiere `poppler-utils`. Si falta, pide al usuario que ejecute `! sudo apt install poppler-utils`.
   - Hay que sacar a todos los deportistas, no solo a los del club. La web necesita al ganador, los primeros de cada categoría y los finalizados.

4. **Escribe el JSON** en `docs/club/ranking-interno/<argumento>/resultados.json`:
   - Una entrada de `classifications` por cada PDF o tabla.
   - En relevos, `team_category` sale del título: MASCULINA → `m`, FEMENINA → `f`, MIXTA → `mixto`. Federado y open van como clasificaciones separadas: la web las unifica.
   - Copia los nombres y los tiempos tal cual. No corrijas nada.

5. **Valídalo** con el mismo código de la web:
   ```bash
   php .claude/skills/ranking-importar/validar.php docs/club/ranking-interno/<argumento>/resultados.json
   ```
   - Si hay errores, corrígelos volviendo al PDF y repite.
   - Si avisa de que el número de finalizados no cuadra, vuelve al PDF: casi siempre falta una página o una fila.

6. **Contrasta con el PDF.** Para cada clasificación, comprueba que:
   - el primero y el último del JSON coinciden con el PDF;
   - el número de deportistas coincide.

   Revisa también la lista «Deportistas de equipos del club» que imprime el validador: nombres, categorías y tiempos.

7. **Informa al usuario.** Dale:
   - la ruta del JSON;
   - el resumen del validador: conjuntos, ganadores, finalizados y deportistas del club;
   - los pasos que faltan: copiar el JSON (en WSL, `clip.exe < <ruta>`), pegarlo en Ranking → Importar resultados, resolver las dudas, «Rellenar la competición», «Guardar», revisar los puntos y «Guardar y publicar».

## No hagas

- No emparejes con socios ni consultes PROD: eso lo hace la web, que tiene la lista de socios.
- No recalcules posiciones ni unifiques clasificaciones en el JSON: copia las del PDF y deja que la web aplique R39.
- No cambies el formato del JSON aquí. Si hay que cambiarlo, cambia a la vez:
  - `admin/ranking-import-prompt.md`;
  - `Calc/ImportParser.php`;
  - la §14 de la spec.
