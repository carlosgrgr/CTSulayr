# Pruebas manuales — Ranking interno

Criterios de aceptación de [SPEC-RANKING-INTERNO.md](../SPEC-RANKING-INTERNO.md), sección 10.3. Se marcan solo cuando se han hecho de verdad.

Lo automático ya está en verde en local el 16 de noviembre de 2026: 47 tests PHPUnit (`tests/`) y 42 comprobaciones de `tests/wp/check-ranking.php` (sección 13.2 de la spec). Esas comprobaciones cubren por código los criterios 2, 3, 4, 5, 6, 7 y 10; aquí se repiten a mano, en el navegador. Con la importación (sección 14 de la spec, en `main` desde el 16 de noviembre de 2026) son 72 tests PHPUnit (con `ImportSkillTest`, que compara la skill con la web), también en verde el 16 de noviembre de 2026. Con el bonus por interés del club (sección 15 de la spec) son 85 tests PHPUnit y 51 comprobaciones con WordPress, en verde el 17 de noviembre de 2026.

| Nº | Criterio | Local | PROD |
|---|---|---|---|
| 1 | Un gestor de ranking ve el menú «Ranking» y no ve «Socios». Un gestor de socios no ve «Ranking». | Comprobado por HTTP el 16 de noviembre de 2026 (gestor de ranking: menú Ranking, 403 en Socios). Falta verlo en el navegador. | Pendiente |
| 2 | Un socio sin sexo no se puede elegir y sale con aviso en «Deportistas». | Pendiente | Pendiente |
| 3 | Tiempos imposibles (más rápido que el ganador, empate sin ser 1.º, posición mayor que los finalizados) dan error en la fila correcta y no se guarda nada. | Pendiente | Pendiente |
| 4 | No se puede publicar con referencias por rellenar. | Pendiente | Pendiente |
| 5 | Cambiar un coeficiente recalcula la temporada en curso y deja el cambio en el registro. | Pendiente | Pendiente |
| 6 | En Mi cuenta, un socio activo y uno congelado ven la pestaña; uno de baja no la ve y su URL da el aviso. | Pendiente | Pendiente |
| 7 | Las fichas de deportista y de competición se abren desde la clasificación; la de un socio de baja da «Deportista no encontrado». | Pendiente | Pendiente |
| 8 | En el móvil (360 px), la clasificación y las fichas se leen sin scroll horizontal. | Comprobado el 16 de noviembre de 2026 en Chrome sin interfaz (390 px). | Comprobado el 17 de noviembre de 2026. |
| 9 | El shortcode `[sulayr_ranking]` en una página muestra lo mismo que la pestaña y pide entrar a quien no ha entrado. | Pendiente | Pendiente |
| 10 | Una plantilla copiada en `ct-sulayr-theme/sulayr/ranking/` se usa en lugar de la del plugin. | Pendiente | — |
| 11 | Importación: un JSON con errores no importa nada y lista los errores; uno válido muestra los conjuntos unificados, las dudas y los socios encontrados. | Comprobado por HTTP el 16 de noviembre de 2026 con el JSON de Úbeda. Falta verlo en el navegador. | Pendiente |
| 12 | Importación: no deja rellenar con dudas sin decidir ni con un socio en dos filas. | Dudas sin decidir comprobado por HTTP el 16 de noviembre de 2026. Falta el socio repetido. | Pendiente |
| 13 | Importación: «Rellenar la competición» abre «Añadir competición» relleno y sin guardar; al guardar pasa las validaciones de siempre. | Comprobado por HTTP el 16 de noviembre de 2026 (23 socios, 7 de 8 referencias, guardada en borrador con puntos y borrada después). Falta verlo en el navegador. | Pendiente |
| 14 | Un gestor de socios no ve «Importar resultados» y su URL da 403. | Pendiente | Pendiente |
| 15 | Bonus por interés: al poner +100 a una competición publicada, los puntos de sus socios que terminaron suben 100 (los DNF siguen a 0), la clasificación se actualiza y el cambio sale en el registro. | Comprobado por código el 17 de noviembre de 2026 (`check-ranking.php`). | Comprobado el 17 de noviembre de 2026 con Úbeda (+50 y vuelta a normal). |
| 16 | La etiqueta del bonus («+100 · Motivo») se ve en Mi cuenta (lista de competiciones, ficha de la competición y del deportista, con la línea del desglose), también en el móvil. | Comprobado en el HTML el 17 de noviembre de 2026. | Comprobado el 17 de noviembre de 2026, también en el móvil. |

## Datos para probar

Importación (criterios 11 a 13): `i-duatlon-por-relevos-3x-ubeda-nazari/resultados.json` (solo en local: esa carpeta no se versiona), generado el 16 de noviembre de 2026 a partir de los 4 PDF de esa carpeta y comprobado fila a fila contra el texto de los PDF (75 de 75) (75 deportistas; federado y open se unifican). Con el JSON de Úbeda, la referencia de Júnior femenina queda vacía: la carrera clasifica a una socia como 23F y la web como Júnior. Revisa su fecha de nacimiento antes de publicar.

Las clasificaciones de ejemplo de la federación están en `docs/club/ejemplos-clasificaciones/`. El duatlón cros de Guadix (8 de noviembre de 2026) sirve para una prueba individual con categoría AB; el duatlón por relevos de Úbeda (15 de noviembre de 2026), para relevos con varios equipos del club.
