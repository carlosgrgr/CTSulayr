# Prioridades

Nov 17, 2026 · Estado: orden cerrado. Los puntos 1 a 3 están definidos, el punto 4 está valorado y el punto 5 está en definición.

Este documento fija el orden de lo que falta por definir. No es una spec y no cambia requisitos ya escritos. Una fila de aquí no se implementa hasta que tenga su propio documento.

El orden es el de la lista. Definir una tarea no adelanta las siguientes.


| Orden | Tarea                              | Estado                      | Documento cercano                                                                   |
| ----- | ---------------------------------- | --------------------------- | ----------------------------------------------------------------------------------- |
| 1     | Facturación Reservas               | Cerrada, desplegada en PROD | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md)               |
| 2     | Tienda online                      | Cerrada, desplegada en PROD | [SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md)                                 |
| 3     | Pago online                        | Definida, en desarrollo     | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 4     | Rentabilidad frente a SportEasy    | Valorada                    | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 5     | Consentimiento legal (RGPD y LOPD) | En definición               | [SPEC-PROTECCION-DE-DATOS.md](club/SPEC-PROTECCION-DE-DATOS.md)                     |


El documento cercano no es la definición de la tarea. Hay que confirmar si vale o si la tarea es otra cosa.

## 1. Facturación Reservas

[SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) describe el coste mensual del sistema de reservas y la liquidación con CN Nazarí. Sus decisiones (P1 a P4 y D5 a D16) están cerradas en su sección 12. El plugin es `sulayr-reservas`.

Esta prioridad es esa spec. Está cerrada, en `main` y desplegada en PROD el 28 de octubre de 2026. El enlace «Facturación» del menú del panel está visible para quien tenga `sulayr_view_billing`.

## 2. Tienda online

WooCommerce ya está en el sitio del club, con el catálogo de equipación 2025-2026 en PROD. El área del deportista es Mi cuenta.

[SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md) define lo que falta: nombre a estampar en los tritrajes y exportación a CSV de los pedidos seleccionados. Cerrada el 9 de noviembre de 2026: en `main` (`2f9e0503f` y `79ce29b9b`) y desplegada en PROD ese mismo día. Manual en `club-triatlon-sulayr/MANUAL-TIENDA.md` y guía para la junta en `docs/club/guia-tienda-sulayr.html`. El plugin es `club-triatlon-sulayr`.

## 3. Pago online

[SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) define el pago online con el TPV virtual (Redsys) en `club-triatlon-sulayr`. Definida el 17 de noviembre de 2026 (decisiones C1 a C30). Cubre las dos cosas: la tienda con tarjeta (parte A, módulo oficial de Redsys) y el cobro automático de las cuotas con la tarjeta guardada (partes B a D). La parte B está en desarrollo en la rama `pago-online-parte-b`: los pasos B1 (base sin conexión con Redsys) y B2 (pago desde Mis recibos) están hechos y probados en local el 17 de noviembre de 2026, también a mano en el navegador, y el paso B3 (cobro con la tarjeta guardada) el 23 de noviembre de 2026, probado contra el entorno de pruebas de Redsys (spec §11). No está en `main` ni en PROD. La parte C (cobro mensual) está en desarrollo en la rama `pago-online-parte-c`, que sale de `pago-online-parte-b`: pasos C1 y C2 hechos y probados en local el 23 de noviembre de 2026 (spec §12). El banco dio de alta el TPV virtual el 23 de noviembre de 2026; faltan las credenciales de pruebas del club.

Desde el 11 de noviembre de 2026, [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) define los recibos mensuales y el control manual de pagos sobre los que se apoyará el cobro online, y elimina la integración con Stripe. No define el cobro online.

## 4. Rentabilidad frente a SportEasy

Valorada el 28 de octubre de 2026. SportEasy se usa solo para cobrar. La diferencia de dinero con el TPV virtual es pequeña, unos 20 € al año arriba o abajo. El motivo para el TPV es el tiempo de gestión: la tarjeta se guarda una vez, el cobro mensual es automático y los pagos quedan en la web, sin Excel.

Las cifras y la cuenta están en la spec de pago online. El banco respondió el 17 de noviembre de 2026: permite el cobro automático (pagos MIT, sin coste adicional) y no rebaja los 5 € al mes. La diferencia de dinero con SportEasy se queda en unos 20 € al año arriba o abajo.

## 5. Consentimiento legal (RGPD y LOPD)

[SPEC-PROTECCION-DE-DATOS.md](club/SPEC-PROTECCION-DE-DATOS.md), en definición desde el 17 de noviembre de 2026. Alcance decidido con el club ese día (L1 a L9): lo que el pago online exige (autorización de cargos recurrentes, información y cambios en la política de privacidad) y la conservación real (anonimizar a los antiguos socios con un botón del gestor, y plazos de la tienda, el TPV, el registro de cambios y las cuentas sin socio). Los textos legales los redacta Claude como borradores y los aprueba la junta. Los documentos internos (registro de actividades, encargados, brechas, derechos y confidencialidad) se preparan como borradores en Drive. Quedan fuera el sitio de reservas y las herramientas de exportar y borrar de WordPress. Las propuestas L10 a L27 están pendientes de confirmar.

La política de privacidad, la de cookies y los consentimientos del formulario de alta ya existen. Están en PROD desde el 11 y el 12 de noviembre de 2026 (parte B de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md)).
