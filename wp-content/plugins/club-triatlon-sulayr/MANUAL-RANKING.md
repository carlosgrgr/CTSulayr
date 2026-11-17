# Manual del gestor — Ranking interno

Este manual es para quien mete los resultados del ranking interno del club. Explica cómo dar de alta una competición a partir de las clasificaciones oficiales de la federación, cómo se calculan los puntos y qué ven los socios.

No incluye capturas todavía. Los nombres en negrita son los textos de los botones y de los apartados del menú.

La especificación técnica completa está en `docs/club/SPEC-RANKING-INTERNO.md`.

## 1. Qué es

Después de cada competición, el gestor mete los resultados de los socios que participaron. La web calcula los puntos y publica una clasificación de la temporada (año natural) en **Mi cuenta → Ranking interno**:

- **absoluta**, por sexo;
- **por grupo de edad**, por sexo y categoría de la federación.

Cuentan las 6 mejores pruebas de cada socio. Solo lo ven los socios activos y congelados, con su sesión iniciada. Los deportistas no pueden meter sus tiempos.

## 2. Quién lo gestiona

Hace falta el rol **Gestor de ranking** (o ser administrador). Lo da un administrador: **Usuarios** → ficha del socio → **Roles adicionales del club** → **Gestor de ranking** → **Actualizar usuario**. El gestor sigue usando Mi cuenta como cualquier socio y además ve el menú **Ranking** en wp-admin.

## 3. Antes de la primera competición: Deportistas

**Ranking → Deportistas** lista los socios activos y congelados con su sexo, su fecha de nacimiento y su grupo de edad en la temporada.

- Un socio **sin sexo** no se puede elegir en los resultados. Rellénalo aquí y pulsa **Guardar sexos**.
- Si un socio que ya tiene resultados se queda **sin sexo**, sus resultados dejan de contar en la clasificación y sale un aviso hasta que lo rellenes.
- Un socio **sin fecha de nacimiento** puntúa en la absoluta, pero no en grupo de edad. La fecha la cambia un gestor de socios o un administrador.

El grupo de edad es la categoría que corresponde a la edad que el socio cumple el 31 de diciembre de la temporada. No cambia a mitad de temporada.

## 4. Meter una competición

Lo normal es **importarla** (4.1): la web rellena todo a partir de los PDF de la federación y tú solo revisas. Meterla a mano (4.2) queda para los casos que la importación no cubre, como la contrarreloj por equipos.

### 4.1. Importar los resultados

1. **Descarga los PDF** oficiales de la clasificación:
   - en individual, la general masculina y la femenina;
   - en relevos, todas las que tengan socios (federado y open, masculina, femenina y mixta).
2. **Conviértelos a JSON con Claude**, de una de estas dos formas:
   - **Con la skill `ranking-importar`** (recomendado). En la app de escritorio de Claude o en claude.ai, adjunta los PDF y pide «pasa estos resultados al ranking interno». En Claude Code, escribe `/ranking-importar` y arrastra los PDF o pon su ruta. Claude lee todas las páginas, comprueba el archivo con las mismas reglas que la web y te da `resultados.json` para descargar, con un resumen: ganadores, finalizados y deportistas de equipos del club. Si algo no cuadra, lo corrige antes de dártelo.
     Para tener la skill en la app hay que instalarla una vez: comprime la carpeta `.claude/skills/ranking-importar/` del repositorio en un .zip y súbelo en Ajustes → Capacidades → Skills.
   - **Con claude.ai**: abre **Ranking → Importar resultados**, pulsa **Copiar el prompt**, abre una conversación nueva en claude.ai, adjunta los PDF y pega el prompt. Claude contesta con el JSON. Aquí nadie lo comprueba antes: si tiene errores, te los dirá la web al analizarlo.
3. **Pega el JSON** en la misma pantalla, o sube el archivo `.json`, y pulsa **Analizar**. Si el JSON tiene errores, no se importa nada y la pantalla los lista: pídele a Claude que los corrija.
4. **Revisa** lo que sale:
   - **Conjuntos del ranking**: cada conjunto, con su ganador y sus finalizados. Federado y open van juntos (lo explica el recuadro de abajo).
   - **Dudas** (en amarillo): deportistas que se parecen a algún socio, pero no lo bastante. En cada una, elige el socio que corresponde o **No es socio**. Debajo del desplegable sale por qué es dudoso.
   - **Socios encontrados**: vienen elegidos. Si alguno no es, cámbialo.
   - **De equipos del club, sin socio**: corren en un equipo «Sulayr», pero su nombre no se parece al de ningún socio. Si alguno es socio, elígelo.
   - Si ya hay una competición con la misma fecha, sale un aviso con su enlace, para que no la metas dos veces.
   - **Bonus por interés del club**: si es una prueba a la que el club quiere que vaya más gente, elige el bonus y, si quieres, el motivo (sección 5.1). El JSON no lo trae.
5. Pulsa **Rellenar la competición**. Se abre **Añadir competición** con los datos, los resultados y las referencias rellenos. **Todavía no se ha guardado nada.**
6. Revisa la tabla. Las referencias que no se pudieron rellenar salen en rojo, con el motivo arriba (por ejemplo, un grupo de edad sin código de la federación). Rellénalas a mano.
7. Pulsa **Guardar**, revisa los puntos de cada fila y, si todo está bien, **Guardar y publicar**.

> **Federado y open, juntos.** Para el ranking interno da igual en qué clasificación saliera cada uno. Se juntan todas las de la misma categoría (masculina, femenina o mixta). El ganador es el más rápido de todas, y la «Pos» y la «Pos Cat» se recalculan por tiempo, así que pueden no coincidir con las del PDF. Es lo esperado.

La importación se guarda dos horas. Si cierras la pestaña de «Añadir competición» sin guardar, vuelve a **Importar resultados** y pulsa otra vez **Rellenar la competición**. Para empezar con otra competición, pulsa **Descartar e importar otro JSON**.

### 4.2. Meterla a mano

Necesitas dos documentos de la federación: la **Clasificación general** de cada sexo y la **Clasificación por categorías**.

1. **Ranking → Añadir competición**.
2. **Datos**: nombre, fecha, lugar, modalidad (triatlón, triatlón cros, duatlón, duatlón cros, acuatlón, acuabike), distancia y formato (individual, relevos o contrarreloj por equipos). Copia lo que pone la cabecera de la clasificación. El formato no se puede cambiar si la competición ya tiene resultados: bórralos antes. Si la prueba tiene **bonus por interés del club**, elígelo aquí (sección 5.1).
3. **Resultados**: una fila por socio que participó, con los datos de la clasificación general de su sexo:
   - **Socio**. Debajo aparecen su sexo y su grupo de edad.
   - **Pos**: la posición en la general de su sexo.
   - **Cat**: el código de la columna «Cat» (por ejemplo `V1M`, `SNF`, `ABM`).
   - **Pos Cat**: la posición en su categoría.
   - **Tiempo**: el tiempo final, como `1:35:53`.
   - **Estado**: Finalizado, DNF, DSQ, DNS o NC. Si no terminó, no hace falta tiempo ni posiciones: son 0 puntos.
   - En relevos y equipos, además, la **categoría del equipo** (masculina, femenina o mixta).
   Un socio que ya está en otra fila no aparece en el desplegable, y un socio sin sexo tampoco.
   
   **Añadir fila** añade más filas; **Quitar** borra una.
4. Pulsa **Guardar**. La competición queda en **borrador**: todavía no cuenta ni se ve.
5. **Referencias**: al guardar aparecen las filas que hacen falta. Rellénalas y vuelve a guardar:
   - **Ganador masculino** / **Ganadora femenina**: el tiempo de la primera fila de la clasificación general de cada sexo, y **Finalizados**, de la cabecera («FINALIZADOS»).
   - **Primero de cada categoría** con socios: el tiempo de la primera fila de esa categoría en la clasificación por categorías.
   - Los finalizados de una categoría solo se piden si un socio de esa categoría no tiene tiempo.
6. Revisa los puntos de cada fila y pulsa **Guardar y publicar**. Ya se ve en Mi cuenta.

Si falta algo, la web no publica y marca en rojo lo que falta. Si hay errores, no se guarda nada: arriba sale un resumen por fila y cada error aparece junto a su campo. Si la base de datos falla a mitad de guardar, también se deshace todo y vuelves al formulario con tus datos.

### Casos especiales

- **Un socio terminó pero no tiene tiempo** en la clasificación: pídeselo. Si no lo da, deja el tiempo vacío; sus puntos se calculan por su posición, y entonces hace falta el número de finalizados de su categoría.
- **Categoría AB (ABM / ABF)**: son los primeros de la general y la federación los saca de su categoría por edad. Mete «ABM» en **Cat**. En su grupo de edad cuenta como ganador.
- **El código de la carrera no coincide con el grupo de la web** (por ejemplo, la carrera dice `V2M` y la web calcula Veterano 1): se guarda, con un aviso. Revisa la fecha de nacimiento del socio o las edades de los grupos.
- **Relevos**: cada relevista hace el recorrido completo. Mete su tiempo individual. Las referencias son las de su categoría de relevos (masculina, femenina o mixta), con federado y open juntos: el ganador es el más rápido de los dos.
- **Contrarreloj por equipos**: mete a cada socio con el tiempo de su equipo. La referencia es el tiempo del equipo ganador de su categoría.

## 5. Cómo se calculan los puntos

- Por tiempo: `1000 × (tiempo del ganador / tiempo del socio) × coeficiente + 50`.
- Si no hay tiempo: `1000 × (1 − (posición − 1) / finalizados) × coeficiente + 50`.
- DNF, DSQ, DNS o NC: 0 puntos.
- Coeficientes por defecto: sprint de triatlón ×1,1; estándar ×1,2; media ×1,4; larga ×1,6; acuatlón, acuabike, supersprint y duatlón sprint ×1,0. Cros, igual que carretera.
- En la clasificación suman las 6 mejores. Con los mismos puntos, va delante quien terminó más pruebas y después quien tiene el mejor resultado. Si siguen iguales, comparten puesto.

En la ficha de cada socio, pasando por los puntos (o tocándolos en el móvil), se ve el cálculo.

### 5.1. Bonus por interés del club

Para que vayan más socios a ciertas pruebas (por ejemplo, las del Circuito Granadino o un campeonato de Andalucía), una competición puede dar puntos extra:

- Se elige en la competición, en **Bonus por interés del club**: «Normal (sin bonus)», +50, +100… hasta +300. El **Motivo** es opcional y lo ven los socios (p. ej. «+100 · Circuito Granadino»).
- Se suma igual a todos los socios que la terminan, en la absoluta y en su grupo de edad: `1000 × ratio × coeficiente + 50 + bonus`. Quien no termina sigue con 0 puntos.
- Es una cifra fija y no un multiplicador: premia ir, pero no cambia las diferencias entre los que corren ni hace que una prueba corta valga como una larga.
- Se puede poner o cambiar después de publicar: los puntos se recalculan y el cambio queda en el registro.

Úsalo con moderación: si todas las pruebas tienen bonus, ninguna destaca.

## 6. Corregir y borrar

- Para corregir, abre la competición desde **Ranking → Competiciones**, cambia lo que haga falta y guarda. Los puntos se recalculan solos.
- Si una competición publicada se queda sin referencias o sin resultados al guardarla, vuelve a borrador.
- **Volver a borrador** la quita del ranking sin borrar nada.
- **Borrar competición** la quita con todos sus resultados. Queda una copia en el registro.
- **Recalcular todo** (en Competiciones) vuelve a calcular los puntos de todas las temporadas. No cambia ningún dato. Úsalo si algo no cuadra.

## 7. Ajustes

**Ranking → Ajustes** cambia los puntos base, el bonus, cuántas pruebas cuentan, los coeficientes, los grupos de edad, el bonus por interés del club y el nombre de la pestaña de Mi cuenta.

En **Bonus por interés** están el **salto** (50) y el **máximo** (300) de los valores que se pueden elegir en cada competición. Con máximo 0 no se usa. El máximo tiene que ser múltiplo del salto. Si una competición de la temporada ya tiene un bonus que dejaría de valer (por ejemplo, +300 y quieres bajar el máximo a 250), Ajustes no guarda y te dice cuál es: cámbiale antes el bonus.

Los ajustes son los de la **temporada en curso**. Al guardar se recalcula solo esa temporada; las anteriores conservan su baremo. Los grupos de edad llevan un código (las dos letras de la columna «Cat», sin la M/F) para avisar cuando la carrera clasifica a un socio en otro grupo.

## 8. Registro

**Ranking → Registro** guarda quién cambió qué y cuándo: datos de competiciones, referencias, resultados, puntos recalculados y ajustes. No se puede editar ni borrar. Se puede filtrar por tipo, competición o socio.

## 9. Socios de baja

Un socio que se da de baja desaparece de la clasificación y de las fichas de Mi cuenta, pero sus resultados se guardan. En **Ranking → Clasificación** salen aparte, como «De baja». Si vuelve al club, recupera sus puntos, también los de la misma temporada.
