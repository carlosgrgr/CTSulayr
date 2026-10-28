# Prioridades

Oct 28, 2026 · Estado: orden cerrado. El punto 1 está definido y el punto 4 está valorado; el resto sigue sin definir.

Este documento fija el orden de lo que falta por definir. No es una spec y no cambia requisitos ya escritos. Una fila de aquí no se implementa hasta que tenga su propio documento.

El orden es el de la lista. Definir una tarea no adelanta las siguientes.


| Orden | Tarea                              | Estado               | Documento cercano                                                                   |
| ----- | ---------------------------------- | -------------------- | ----------------------------------------------------------------------------------- |
| 1     | Facturación Reservas               | Definida             | [SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md)               |
| 2     | Tienda online                      | Pendiente de definir | No hay                                                                              |
| 3     | Pago online                        | Pendiente de definir | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 4     | Rentabilidad frente a SportEasy    | Valorada             | [SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) |
| 5     | Consentimiento legal (RGPD y LOPD) | Pendiente de definir | Mención en el borrador de cobros                                                    |


El documento cercano no es la definición de la tarea. Hay que confirmar si vale o si la tarea es otra cosa.

## 1. Facturación Reservas

[SPEC-FACTURACION-RESERVAS.md](reservas/SPEC-FACTURACION-RESERVAS.md) describe el coste mensual del sistema de reservas y la liquidación con CN Nazarí. Está escrita, sin implementar, y sus decisiones (P1 a P4 y D5 a D16) están cerradas en su sección 12. El plugin sería `sulayr-reservas`.

Esta prioridad es esa spec. El siguiente paso es implementarla.

## 2. Tienda online

WooCommerce ya está en el sitio del club y el área del deportista es Mi cuenta. No hay spec de esta tarea.

Falta decidir qué se pide: catálogo, quién compra, y qué relación tiene con las cuotas y con la piscina.

## 3. Pago online

[SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md](club/SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md) es un borrador del cobro de cuotas con TPV virtual en `club-triatlon-sulayr`. Elige el TPV frente a SEPA y Stripe, y deja preguntas al banco y a la junta. Estar en esta lista no aprueba esas elecciones.

Falta decidir si esta prioridad es ese borrador, el pago de la tienda, o los dos.

## 4. Rentabilidad frente a SportEasy

Valorada el 28 de octubre de 2026. SportEasy se usa solo para cobrar. La diferencia de dinero con el TPV virtual es pequeña, unos 20 € al año arriba o abajo. El motivo para el TPV es el tiempo de gestión: la tarjeta se guarda una vez, el cobro mensual es automático y los pagos quedan en la web, sin Excel.

Las cifras y la cuenta están en el borrador de cobros. Sigue pendiente la respuesta del banco sobre la automatización y sobre quitar o rebajar los 5 € al mes. Si los quita, el TPV pasa a ser claramente más barato.

## 5. Consentimiento legal (RGPD y LOPD)

No hay spec. El borrador de cobros pide, solo para los pagos, informar del tratamiento, autorizar cargos recurrentes, fijar cuánto se conservan recibos y referencia de tarjeta, e incluir al banco en el registro de actividades. Este documento no redacta cláusulas.

Falta decidir qué tratamientos cubre, en qué formularios aparece y quién escribe el texto legal.