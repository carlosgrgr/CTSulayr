# Prioridades

Nov 17, 2026 · Estado: orden cerrado. Los puntos 1 a 3 están definidos y el punto 4 está valorado; el punto 5 sigue sin definir.

Este documento fija el orden de lo que falta por definir. No es una spec y no cambia requisitos ya escritos. Una fila de aquí no se implementa hasta que tenga su propio documento.

El orden es el de la lista. Definir una tarea no adelanta las siguientes.


| Orden | Tarea                              | Estado                      | Documento cercano                                                                   |
| ----- | ---------------------------------- | --------------------------- | ----------------------------------------------------------------------------------- |
| 1     | Facturación Reservas               | Cerrada, desplegada en PROD | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md)               |
| 2     | Tienda online                      | Cerrada, desplegada en PROD | [SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md)                                 |
| 3     | Pago online                        | Definida, sin implementar   | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 4     | Rentabilidad frente a SportEasy    | Valorada                    | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 5     | Consentimiento legal (RGPD y LOPD) | Pendiente de definir        | Mención en la spec de pago online                                                   |


El documento cercano no es la definición de la tarea. Hay que confirmar si vale o si la tarea es otra cosa.

## 1. Facturación Reservas

[SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) describe el coste mensual del sistema de reservas y la liquidación con CN Nazarí. Sus decisiones (P1 a P4 y D5 a D16) están cerradas en su sección 12. El plugin es `sulayr-reservas`.

Esta prioridad es esa spec. Está cerrada, en `main` y desplegada en PROD el 28 de octubre de 2026. El enlace «Facturación» del menú del panel está visible para quien tenga `sulayr_view_billing`.

## 2. Tienda online

WooCommerce ya está en el sitio del club, con el catálogo de equipación 2025-2026 en PROD. El área del deportista es Mi cuenta.

[SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md) define lo que falta: nombre a estampar en los tritrajes y exportación a CSV de los pedidos seleccionados. Cerrada el 9 de noviembre de 2026: en `main` (`2f9e0503f` y `79ce29b9b`) y desplegada en PROD ese mismo día. Manual en `club-triatlon-sulayr/MANUAL-TIENDA.md` y guía para la junta en `docs/club/guia-tienda-sulayr.html`. El plugin es `club-triatlon-sulayr`.

## 3. Pago online

[SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) define el pago online con el TPV virtual (Redsys) en `club-triatlon-sulayr`. Definida el 17 de noviembre de 2026 (decisiones C1 a C30), sin implementar. Cubre las dos cosas: la tienda con tarjeta (parte A, módulo oficial de Redsys) y el cobro automático de las cuotas con la tarjeta guardada (partes B a D). Espera a la firma del contrato del TPV y a las credenciales de pruebas.

Desde el 11 de noviembre de 2026, [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) define los recibos mensuales y el control manual de pagos sobre los que se apoyará el cobro online, y elimina la integración con Stripe. No define el cobro online.

## 4. Rentabilidad frente a SportEasy

Valorada el 28 de octubre de 2026. SportEasy se usa solo para cobrar. La diferencia de dinero con el TPV virtual es pequeña, unos 20 € al año arriba o abajo. El motivo para el TPV es el tiempo de gestión: la tarjeta se guarda una vez, el cobro mensual es automático y los pagos quedan en la web, sin Excel.

Las cifras y la cuenta están en la spec de pago online. El banco respondió el 17 de noviembre de 2026: permite el cobro automático (pagos MIT, sin coste adicional) y no rebaja los 5 € al mes. La diferencia de dinero con SportEasy se queda en unos 20 € al año arriba o abajo.

## 5. Consentimiento legal (RGPD y LOPD)

No hay spec. La spec de pago online pide, solo para los pagos, informar del tratamiento, autorizar cargos recurrentes, fijar cuánto se conservan recibos y referencia de tarjeta, e incluir al banco en el registro de actividades. Este documento no redacta cláusulas.

Falta decidir qué tratamientos cubre, en qué formularios aparece y quién escribe el texto legal.

[SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](club/SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) (11 de noviembre de 2026) cubre solo el formulario de alta: casillas de privacidad, normas del club e imagen, con versión y fecha, y plazos de conservación. El texto legal lo redacta el club. Implementado en la parte B de esa spec (commit en la rama `altas-socio-parte-b`, probada en local el 11 de noviembre de 2026, sin mergear; no está en `main` ni en PROD).