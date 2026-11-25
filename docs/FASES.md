# Índice de trabajo

No hay una rama de trabajo fija. Cada spec se implementa en la rama que corresponda y entra en `main` cuando está hecha.

El orden de lo que aún no tiene spec está en [PRIORIDADES.md](PRIORIDADES.md). Esa lista no se implementa hasta que cada tarea tenga su documento.

Las fases 0 a 11 fueron el plan de entrega de `sulayr-reservas`. Están cerradas y en `main`. Esa numeración no continúa. Las specs abiertas se agrupan abajo por sistema. Si una spec necesita pasos internos, los numera ella (como D0 a D7 en documentos).

Las pruebas que siguen abiertas no reabren las fases de reservas. Están en `wp-content/plugins/sulayr-reservas/DEVELOPMENT.md`, sección «Pruebas pendientes».

## Fases de reservas (cerradas)

El commit `ce681881` («Swimming reservations spec») es el SPEC inicial, anterior a estas fases.

| Fase | Contenido | Estado |
|---|---|---|
| 0 | Análisis y separación de SPEC, informe y registro | En `main` |
| 1 | Esqueleto del plugin, opciones de red y cambio de blog con restauración | En `main` |
| 2 | Esquema `dbDelta`: sesiones, reservas, meses, nadadores y registro técnico | En `main` |
| 3 | Roles `nadador` y `gestor`, capacidades y bloqueo de `wp-admin` en reservas | En `main` |
| 4 | Generación de sesiones y ventana de fechas | En `main` |
| 5 | Reservas, cancelaciones, límite semanal y concurrencia | En `main` |
| 6 | Alta, activación, desactivación y eliminación de nadadores | En `main` |
| 7 | Shortcode y paneles | En `main` |
| 8 | Sincronización, botón y registro técnico | En `main` |
| 9 | Criterios de aceptación del SPEC | En `main` |
| 10 | Límite por nadador, perfil, fotografía, CSV, filtros y Bento del perfil del club | En `main` |
| 11 | Acceso Club ↔ Reservas, cierre de sesión y calendario móvil de un día | En `main` |

## Sistema de reservas

Plugin `sulayr-reservas`. Estas specs llevan el sufijo `-RESERVAS`. `SPEC-RESERVAS.md` es la spec de este sistema. `ANALISIS.md`, `ACEPTACION.md` y `DEVELOPMENT.md` la acompañan.

| Spec | Documento | Estado |
|---|---|---|
| Reservas | [SPEC-RESERVAS.md](reservas/SPEC-RESERVAS.md), [SPEC-MEJORAS-RESERVAS.md](reservas/SPEC-MEJORAS-RESERVAS.md), [SPEC-FASE11-RESERVAS.md](reservas/SPEC-FASE11-RESERVAS.md) | Cerrada. Fases 0 a 11 en `main`. |
| Facturación | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) | Cerrada. Criterios de la sección 11 probados (ver `ACEPTACION.md`). En `main` y desplegada en PROD el 28 de octubre de 2026. El enlace «Facturación» del menú del panel está visible para quien tenga `sulayr_view_billing`. |
| Nadadores apuntados en el detalle de la sesión | [SPEC-ASISTENTES-SESION-RESERVAS.md](reservas/SPEC-ASISTENTES-SESION-RESERVAS.md) | Cerrada. Criterios de la sección 4 probados (ver `ACEPTACION.md`). En `main` (`501f6a185` y `0e56b6346`) y desplegada en PROD el 12 de noviembre de 2026 con FileZilla. |

## Web del club

Plugin `club-triatlon-sulayr`. Estas specs conservan su nombre. No modifican `SPEC-RESERVAS.md` ni el comportamiento de reservas.

| Spec | Documento | Estado |
|---|---|---|
| Documentos | [SPEC-DOCUMENTOS.md](club/SPEC-DOCUMENTOS.md) | Fases D0 a D7 en `main`. D5 a D7 en `deb5398e`. |
| Pago online (cuotas y tienda) | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) | Definida el 17 de noviembre de 2026 (decisiones C1 a C30). Parte B empezada en la rama `pago-online-parte-b`: pasos B1 (base sin conexión con Redsys) y B2 (pago desde Mis recibos) hechos y probados en local el 17 de noviembre de 2026, también a mano en el navegador; paso B3 (cobro con la tarjeta guardada por la API REST) hecho y probado contra el entorno de pruebas de Redsys el 23 de noviembre de 2026 (spec §11). Parte C en la rama `pago-online-parte-c` (sale de `pago-online-parte-b`): pasos C1 (cobro mensual, reintentos, avisos, bajas y caducidad) y C2 (pantalla «Aprobar cobro» y cobro de un recibo concreto) hechos y probados en local el 23 de noviembre de 2026, también contra el entorno de pruebas de Redsys (spec §12). No está en `main` ni en PROD. TPV dado de alta por el banco el 23 de noviembre de 2026. La notificación de Redsys llegando sola a la web se probó con un túnel el 23 de noviembre de 2026 (spec §11.2). Las credenciales de pruebas del club llegaron el 24 de noviembre de 2026; con ellas se repitió la prueba de punta a punta, cobro MIT y venta denegada incluidos (spec §11.2). En staging desde el 25 de noviembre de 2026 ([pago-online/despliegue-staging.md](club/pago-online/despliegue-staging.md)): pago, cobro con la tarjeta guardada y venta denegada correctos; falta allí el cobro mensual. |
| Protección de datos (RGPD) | [SPEC-PROTECCION-DE-DATOS.md](club/SPEC-PROTECCION-DE-DATOS.md) | En definición desde el 17 de noviembre de 2026. Alcance decidido (L1 a L9); propuestas L10 a L27 pendientes de confirmar. Sin implementar. La parte P1 (anonimización) no depende del TPV. |
| Alta y ciclo de vida del socio | [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) | Definida el 11 de noviembre de 2026. Parte A en `main` (`fab85c343` y `892d6fcff`) y desplegada en PROD el 11 de noviembre de 2026; migración terminada en PROD. Partes B, C y D en `main` (merge `21914c88d`) y código subido a PROD el 12 de noviembre de 2026 (pasos 1 a 4 de `club/altas-y-ciclo-de-vida-socio/despliegue-prod.md`). Configurada y comprobada ese día; recibos desde noviembre de 2026. Pruebas manuales hechas; quedan solo las que dependen de una fecha (`club/altas-y-ciclo-de-vida-socio/pruebas.md`). Pestaña «Mis recibos» en Mi cuenta (spec 20.8) en `main` y en PROD el 12 de noviembre de 2026. |
| Tienda online | [SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md) | Cerrada. En `main` (`2f9e0503f` y `79ce29b9b`) y desplegada en PROD el 9 de noviembre de 2026. |
| Ranking interno | [SPEC-RANKING-INTERNO.md](club/SPEC-RANKING-INTERNO.md) | Definida el 16 de noviembre de 2026 (decisiones R1 a R38). Pasos R0 a R6 implementados ese día y probados en local (sección 13 de la spec). En `main` (`7fb03aba1`) y subida a PROD el 16 de noviembre de 2026. Configuración hecha. Los criterios de PROD están sin confirmar. Pendientes: pruebas manuales ([club/ranking-interno/pruebas.md](club/ranking-interno/pruebas.md)) y registro del despliegue R7 ([club/ranking-interno/despliegue-prod.md](club/ranking-interno/despliegue-prod.md)). Ampliación del 16 de noviembre de 2026: importación desde JSON con unificación de federado y open (R39 a R41, sección 14), rama `ranking-importar`, probada en local con Úbeda y mergeada en `main` ese día; skill `ranking-importar` autónoma, instalada en la app de escritorio. Subida a PROD ese día. Ajuste de estilos del ranking en el móvil (spec §8.4) subido a PROD el 17 de noviembre de 2026. Bonus por interés del club (R42 y R43, §15): definido, implementado y subido a PROD el 17 de noviembre de 2026; criterios 15 y 16 comprobados en PROD. |

La identidad visual Sulayr del panel, la cabecera del tema y el acceso no tiene spec: no cambia comportamiento. Está en `main` y en PROD desde el 28 de octubre de 2026. El botón «Hazte socio» de la cabecera va sin subrayado y con el texto en negrita (`style.css` del tema): en `main` y en PROD el 16 de noviembre de 2026. Detalle en `DEVELOPMENT.md`, sección «Identidad visual Sulayr».

Una spec de reservas no modifica el plugin del club: la integración se engancha desde `sulayr-reservas`. El estado vivo de cada spec está en su documento.
