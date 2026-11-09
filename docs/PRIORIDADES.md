# Prioridades

Oct 28, 2026 · Estado: orden cerrado. El punto 1 está definido y el punto 4 está valorado; el resto sigue sin definir.

Este documento fija el orden de lo que falta por definir. No es una spec y no cambia requisitos ya escritos. Una fila de aquí no se implementa hasta que tenga su propio documento.

El orden es el de la lista. Definir una tarea no adelanta las siguientes.


| Orden | Tarea                              | Estado                      | Documento cercano                                                                   |
| ----- | ---------------------------------- | --------------------------- | ----------------------------------------------------------------------------------- |
| 1     | Facturación Reservas               | Cerrada, desplegada en PROD | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md)               |
| 2     | Tienda online                      | Cerrada, desplegada en PROD | [SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md)                                 |
| 3     | Pago online                        | Pendiente de definir        | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 4     | Rentabilidad frente a SportEasy    | Valorada                    | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 5     | Consentimiento legal (RGPD y LOPD) | Pendiente de definir        | Mención en el borrador de cobros                                                    |


El documento cercano no es la definición de la tarea. Hay que confirmar si vale o si la tarea es otra cosa.

## 1. Facturación Reservas

[SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) describe el coste mensual del sistema de reservas y la liquidación con CN Nazarí. Sus decisiones (P1 a P4 y D5 a D16) están cerradas en su sección 12. El plugin es `sulayr-reservas`.

Esta prioridad es esa spec. Está cerrada, en `main` y desplegada en PROD el 28 de octubre de 2026. El enlace «Facturación» del menú del panel está comentado hasta que se empiece a usar.

## 2. Tienda online

WooCommerce ya está en el sitio del club, con el catálogo de equipación 2025-2026 en PROD. El área del deportista es Mi cuenta.

[SPEC-TIENDA-ONLINE.md](club/SPEC-TIENDA-ONLINE.md) define lo que falta: nombre a estampar en los tritrajes y exportación a CSV de los pedidos seleccionados. Cerrada el 9 de noviembre de 2026: en `main` (`2f9e0503f` y `79ce29b9b`) y desplegada en PROD ese mismo día. Manual en `club-triatlon-sulayr/MANUAL-TIENDA.md` y guía para la junta en `docs/club/guia-tienda-sulayr.html`. El plugin es `club-triatlon-sulayr`.

## 3. Pago online

[SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) es un borrador del cobro de cuotas con TPV virtual en `club-triatlon-sulayr`. Elige el TPV frente a SEPA y Stripe, y deja preguntas al banco y a la junta. Estar en esta lista no aprueba esas elecciones.

Falta decidir si esta prioridad es ese borrador, el pago de la tienda, o los dos.

## 4. Rentabilidad frente a SportEasy

Valorada el 28 de octubre de 2026. SportEasy se usa solo para cobrar. La diferencia de dinero con el TPV virtual es pequeña, unos 20 € al año arriba o abajo. El motivo para el TPV es el tiempo de gestión: la tarjeta se guarda una vez, el cobro mensual es automático y los pagos quedan en la web, sin Excel.

Las cifras y la cuenta están en el borrador de cobros. Sigue pendiente la respuesta del banco sobre la automatización y sobre quitar o rebajar los 5 € al mes. Si los quita, el TPV pasa a ser claramente más barato.

## 5. Consentimiento legal (RGPD y LOPD)

No hay spec. El borrador de cobros pide, solo para los pagos, informar del tratamiento, autorizar cargos recurrentes, fijar cuánto se conservan recibos y referencia de tarjeta, e incluir al banco en el registro de actividades. Este documento no redacta cláusulas.

Falta decidir qué tratamientos cubre, en qué formularios aparece y quién escribe el texto legal.