# Pruebas manuales — Ranking interno

Criterios de aceptación de [SPEC-RANKING-INTERNO.md](../SPEC-RANKING-INTERNO.md), sección 10.3. Se marcan solo cuando se han hecho de verdad.

Lo automático ya está en verde en local el 16 de noviembre de 2026: 47 tests PHPUnit (`tests/`) y 42 comprobaciones de `tests/wp/check-ranking.php` (sección 13.2 de la spec). Esas comprobaciones cubren por código los criterios 2, 3, 4, 5, 6, 7 y 10; aquí se repiten a mano, en el navegador.

| Nº | Criterio | Local | PROD |
|---|---|---|---|
| 1 | Un gestor de ranking ve el menú «Ranking» y no ve «Socios». Un gestor de socios no ve «Ranking». | Comprobado por HTTP el 16 de noviembre de 2026 (gestor de ranking: menú Ranking, 403 en Socios). Falta verlo en el navegador. | Pendiente |
| 2 | Un socio sin sexo no se puede elegir y sale con aviso en «Deportistas». | Pendiente | Pendiente |
| 3 | Tiempos imposibles (más rápido que el ganador, empate sin ser 1.º, posición mayor que los finalizados) dan error en la fila correcta y no se guarda nada. | Pendiente | Pendiente |
| 4 | No se puede publicar con referencias por rellenar. | Pendiente | Pendiente |
| 5 | Cambiar un coeficiente recalcula la temporada en curso y deja el cambio en el registro. | Pendiente | Pendiente |
| 6 | En Mi cuenta, un socio activo y uno congelado ven la pestaña; uno de baja no la ve y su URL da el aviso. | Pendiente | Pendiente |
| 7 | Las fichas de deportista y de competición se abren desde la clasificación; la de un socio de baja da «Deportista no encontrado». | Pendiente | Pendiente |
| 8 | En el móvil (360 px), la clasificación y las fichas se leen sin scroll horizontal. | Pendiente | Pendiente |
| 9 | El shortcode `[sulayr_ranking]` en una página muestra lo mismo que la pestaña y pide entrar a quien no ha entrado. | Pendiente | Pendiente |
| 10 | Una plantilla copiada en `ct-sulayr-theme/sulayr/ranking/` se usa en lugar de la del plugin. | Pendiente | — |

## Datos para probar

Las clasificaciones de ejemplo de la federación están en `docs/club/ejemplos-clasificaciones/`. El duatlón cros de Guadix (8 de noviembre de 2026) sirve para una prueba individual con categoría AB; el duatlón por relevos de Úbeda (15 de noviembre de 2026), para relevos con varios equipos del club.
