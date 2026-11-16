---
name: ranking-importar
description: Convierte los PDF de clasificaciones oficiales de una competición de triatlón (Federación Andaluza u otra) en el archivo resultados.json que se importa en el ranking interno del Club Triatlón Sulayr (wp-admin → Ranking → Importar resultados). Úsala cuando el usuario adjunte esos PDF o dé su ruta y quiera los resultados para el ranking interno, o pida pasar una clasificación a JSON para el ranking.
---

# Clasificaciones en PDF → resultados.json del ranking interno

Genera `resultados.json` a partir de los PDF oficiales de una competición y se lo entrega al usuario para descargarlo. Después, un gestor lo sube o lo pega en **wp-admin → Ranking → Importar resultados**. Allí la web empareja a los deportistas con los socios y unifica las clasificaciones (federado y open van juntos). Esta skill solo extrae y comprueba: no empareja socios ni recalcula posiciones.

Funciona igual en Claude Code que en la app de escritorio o claude.ai. Solo necesita leer PDF y ejecutar Python 3 (biblioteca estándar).

## 1. Reúne los PDF

Los PDF pueden llegar de dos formas, y se pueden mezclar:

- **Adjuntos en la conversación.** En la app de escritorio o claude.ai, suelen estar también en `/mnt/user-data/uploads/`.
- **Rutas** a archivos o a una carpeta. Usa todos los `.pdf` de esa carpeta. En WSL, las descargas de Windows están en `/mnt/c/Users/<usuario>/Downloads/`.

Si no hay ningún PDF, pídelos. Hacen falta todas las clasificaciones con socios del club:

- en una prueba individual, la general masculina y la femenina;
- en relevos, todas (federado y open; masculina, femenina y mixta).

## 2. Lee el formato

Lee `formato.md`, en la carpeta de esta skill. Define el JSON y las reglas de extracción, y es el mismo texto que la web da para claude.ai. Síguelo al pie de la letra.

## 3. Extrae todos los deportistas

- Lee cada PDF entero, todas las páginas. Si un PDF es largo y la lectura se corta, sigue por páginas hasta el final.
- Copia **todos** los deportistas de cada clasificación, no solo los del club. La web los necesita para el ganador, los primeros de cada categoría y los finalizados.
- Usa una entrada de `classifications` por cada PDF o tabla.
- En relevos, `team_category` sale del título: MASCULINA → `m`, FEMENINA → `f`, MIXTA → `mixto`. No juntes federado y open: van como clasificaciones separadas y la web las unifica.
- Copia nombres, clubes, categorías, posiciones y tiempos tal cual. No corrijas nada.

## 4. Escribe y valida el JSON

Escribe el archivo `resultados.json`. Dónde (paso 6) no importa todavía. Después ejecuta:

```bash
python3 <carpeta de esta skill>/scripts/validar.py <ruta>/resultados.json
```

El script aplica las mismas reglas que la web: si lo da por bueno, la web también lo acepta.

- **Errores:** corrígelos volviendo al PDF y repite la validación hasta que salga `OK`.
- **Aviso de finalizados que no cuadran:** casi siempre falta una página o una fila. Revísalo.

## 5. Contrasta con los PDF

Para cada clasificación, comprueba que:

- el número de deportistas coincide con el del PDF;
- el primero y el último del JSON son los del PDF.

Revisa también, uno a uno, los «Deportistas de equipos del club» que lista el validador: nombre, Cat, tiempo y club.

## 6. Entrega el archivo para descargar

- **App de escritorio o claude.ai:** guarda el archivo en `/mnt/user-data/outputs/resultados.json` y enlázalo para que el usuario lo descargue.
- **Claude Code:** guárdalo junto a los PDF, o donde diga el usuario, y da la ruta completa. Si los PDF venían solo adjuntos, guárdalo en el directorio de trabajo. No lo guardes dentro de un repositorio salvo que el usuario lo pida. En WSL se copia al portapapeles con `clip.exe < <ruta>`.

En la respuesta incluye:

- el resumen del validador: conjuntos, ganadores, finalizados y deportistas del club;
- cualquier aviso;
- los pasos que faltan en la web:
  1. Ranking → Importar resultados.
  2. Subir o pegar el archivo y pulsar «Analizar».
  3. Resolver las dudas y pulsar «Rellenar la competición».
  4. Pulsar «Guardar», revisar los puntos y pulsar «Guardar y publicar».

## No hagas

- No emparejes con socios ni consultes la web: eso lo hace la web, que tiene la lista de socios.
- No recalcules posiciones ni unifiques clasificaciones en el JSON.
- No cambies el formato. `formato.md` es una copia de `wp-content/plugins/club-triatlon-sulayr/admin/ranking-import-prompt.md`, y un test del plugin comprueba que son iguales. Si hay que cambiar el formato, cambia a la vez:
  - esos dos archivos;
  - `Calc/ImportParser.php`;
  - `scripts/validar.py`;
  - la §14 de `docs/club/SPEC-RANKING-INTERNO.md`.
