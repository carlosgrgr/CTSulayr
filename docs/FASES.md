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
| Facturación | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) | Especificada, sin implementar. Decisiones cerradas en su sección 12. |

## Web del club

Plugin `club-triatlon-sulayr`. Estas specs conservan su nombre. No modifican `SPEC-RESERVAS.md` ni el comportamiento de reservas.

| Spec | Documento | Estado |
|---|---|---|
| Documentos | [SPEC-DOCUMENTOS.md](club/SPEC-DOCUMENTOS.md) | Fases D0 a D7 en `main`. D5 a D7 en `deb5398e`. |
| Cobros de cuotas | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) | Borrador abierto. Sin implementar. |

Una spec de reservas no modifica el plugin del club: la integración se engancha desde `sulayr-reservas`. El estado vivo de cada spec está en su documento.
