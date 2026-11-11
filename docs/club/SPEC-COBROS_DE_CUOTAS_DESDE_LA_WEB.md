# Spec: Cobro de cuotas desde la web

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr` (web del club)

Oct 28, 2026 · @Carlos · Estado: borrador abierto

Nota del 11 de noviembre de 2026: [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) define socios, servicios con vigencia, histórico y recibos mensuales con control manual de pagos, y elimina Stripe. Este borrador debe apoyarse en ese modelo para el cobro online.

## Contexto y objetivos

El club cobrará las mensualidades desde su propia web WordPress con un TPV virtual, y dejará de usar el Excel de cuotas. La integración la desarrolla el club en su plugin de área de socio, sin coste más allá del tiempo.

**Situación actual**

- Los cobros de mensualidades se hacen con SportEasy. Solo se usa para cobrar.
- El control de cuotas vive en un Excel de Drive con una pestaña por mes. En cada pestaña se ajustan a mano altas, bajas, piscina, saldos a favor, matrículas y prorrateos.
- Un socio pagó por la web con una suscripción de Stripe. La integración se elimina en la parte A de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) (D57).

**Objetivos**

- El cobro mensual se lanza solo, con el importe correcto para cada socio.
- Cada pago queda registrado automáticamente en la web en cuanto el banco lo confirma.
- Un usuario administrador sigue pagos, impagos y cambios desde la web, sin Excel.
- La única tarea manual es revisar el borrador de recibos unos minutos al mes y atender excepciones.

**Fuera de alcance por ahora**

- Contabilidad general del club (gastos, visión anual).



## Medio de pago elegido

TPV virtual del banco con pago por referencia (cobro recurrente con tarjeta guardada). Cuesta lo mismo que las alternativas y es el único que permite automatizar el registro de pagos en la web.


| Opción                        | Coste anual aprox.                               | Automatización | Comentario                                                                                                 |
| ----------------------------- | ------------------------------------------------ | -------------- | ---------------------------------------------------------------------------------------------------------- |
| SportEasy (actual)            | 182,49 € (3,96 €/deportista/año; 46 deportistas) | Solo cobros    | Valorado. La diferencia con el TPV es de unos 20 € al año                                                  |
| TPV virtual                   | 160–196,80 €                                     | Casi total     | 0,70 % tarjetas nacionales, 2 % internacionales, 5 €/mes de mantenimiento                                  |
| Domiciliación SEPA (norma 19) | 181 € + 0,60 €/devolución                        | Parcial        | 0,36 €/recibo en otras entidades, 0 € en la propia; requiere subir el fichero cada mes y mandatos firmados |
| Stripe                        | Más del doble que el TPV                         | Total          | 1,5 % + 0,25 € por cobro con tarjeta europea                                                               |


Valoración de octubre de 2026, el mes con más deportistas del año: 47 deportistas y 1.626,58 € a cobrar.

SportEasy cobra 3,96 € al año por deportista. Con 46 deportistas, este año son 182,49 €. El TPV suma 5 € al mes (60 € al año aunque no se cobre nada) y 0,70 € de cada 100 € cobrados.

Si todos los meses fueran como octubre, el TPV saldría por 196,80 € al año, cerca de 1,2 € al mes más que SportEasy. Redondeando, unos 200 €: unos 18 € más. Como en verano se cobra menos, lo probable es que baje a unos 160 €: unos 20 € menos que SportEasy. La diferencia de dinero es pequeña.

A partir de unos 60 socios el TPV sale claramente más barato: SportEasy cobra lo mismo por cada socio nuevo y en el TPV ese coste baja. El motivo para cambiar es el trabajo. Cada socio guarda la tarjeta una vez, el cobro mensual es automático y los pagos quedan en la web, sin Excel.

**Por qué TPV y no SEPA:** el banco avisa a la web al momento de cada cobro, aprobado o denegado. Con SEPA hay que subir el fichero a mano y las devoluciones llegan días después.

**Cuándo revisar la decisión:** si el banco no ofrece pago por referencia, o si quita o rebaja los 5 € al mes. En ese caso el TPV pasa a ser claramente más barato.

## Cálculo del cobro mensual

La web no guarda un importe fijo por socio: cada mes calcula el recibo con la tarifa, los servicios y los ajustes vigentes. Cualquier cambio se refleja solo en el siguiente recibo.

```latex
\text{Recibo} = \text{cuota base} + \sum \text{servicios activos} + \sum \text{ajustes del mes}
```

**Piezas**

- **Tarifas:** cuota básica, cuota completa, piscina… Cada precio tiene fechas de vigencia, así se cubre que la piscina cueste 25 € en invierno y 15–20 € en verano.
- **Servicios del socio:** su cuota base y los servicios activos, cada uno con fecha de inicio y de fin. Dar de baja la natación es poner fecha de fin, no borrar.
- **Ajustes:** importes positivos o negativos que se aplican una vez o durante un periodo: matrícula, medio mes, saldo a favor, promoción amigo, penalización por retraso, congelación por lesión, cuota subvencionada.

**Reglas** (por confirmar con la junta)

- **Fecha de corte:** día 25. Los cambios hechos hasta el corte cuentan para el mes siguiente; los posteriores, para el otro.
- **Fecha de cobro:** día 1 de cada mes.
- **Alta a mitad de mes:** prorrateo por días, o medio mes si entra después del 15. La matrícula se añade al primer recibo.
- **Saldo a favor mayor que el recibo:** el recibo queda a 0 € y el resto pasa como saldo al mes siguiente.
- **Congelación o subvención:** el recibo se genera a 0 € con el motivo, para que quede constancia.
- **Junta directiva:** mismas reglas; sus particularidades se registran como ajustes.

**Ciclo mensual**

1. Día 25: la web genera el borrador de recibos con el desglose de cada socio.
2. Días 25–30: el administrador revisa y corrige los recibos que haga falta, y aprueba el lote.
3. Día 1: la web cobra cada recibo aprobado con la referencia de la tarjeta.
4. El banco responde y la web marca cada recibo como pagado o fallido.
5. Los fallidos se reintentan y, si siguen fallando, se avisa al socio y al administrador.



## Estructura de tablas

Nueve tablas propias del plugin, con prefijo `{wp_prefix}sulayr_`. Los importes se guardan en céntimos (enteros) para evitar errores de redondeo, y las fechas en formato `DATE`/`DATETIME`.


| Tabla             | Para qué                                                  | Campos principales                                                                                                                                                           |
| ----------------- | --------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `socios`          | Ficha del socio (si el plugin ya tiene una, se reutiliza) | id, userid (WordPress), numsocio, tipo (normal, junta, externo), estado (activo, congelado, baja), fechaalta, fechabaja                                                      |
| `tarifas`         | Catálogo de conceptos cobrables                           | id, codigo, nombre, tipo (cuotabase, servicio, puntual), activa                                                                                                              |
| `tarifa_precios`  | Precio de cada tarifa en el tiempo                        | id, tarifaid, importecent, vigentedesde, vigentehasta                                                                                                                        |
| `socio_servicios` | Qué tiene contratado cada socio y desde cuándo            | id, socioid, tarifaid, fechainicio, fechafin, estado (pendiente, aprobado, rechazado), solicitadopor, aprobadopor                                                            |
| `ajustes`         | Importes puntuales o por periodo                          | id, socioid, tipo (matricula, prorrateo, saldofavor, descuento, promocion, penalizacion, exencion), importecent (±), periododesde, periodohasta, motivo, reciboid, creadopor |
| `recibos`         | Lo que se cobra a un socio en un mes                      | id, socioid, periodo (AAAA-MM), importecent, estado (borrador, aprobado, cobrando, pagado, fallido, exento, anulado), intentos, fechacobro, metodopagoid                     |
| `recibo_lineas`   | Desglose del recibo                                       | id, reciboid, concepto, tarifaid o ajusteid, importecent                                                                                                                     |
| `metodos_pago`    | Referencia de la tarjeta que da Redsys                    | id, socioid, redsysidentifier, coftxnid, tarjetaenmascarada, caducidad (AAMM), estado (activo, caducado, revocado), fechaalta                                                |
| `transacciones`   | Cada operación enviada a Redsys                           | id, reciboid, dsorder, tipo (inicial, recurrente, puntual, devolucion), importecent, dsresponse, codigoautorizacion, estado, respuesta (JSON sin datos sensibles), creado    |


Una décima tabla `log_cambios` (quién, qué, cuándo, valor anterior y nuevo) guarda la auditoría de cambios en servicios, ajustes y recibos.

**Relaciones clave**

- Un socio tiene muchos servicios, ajustes y recibos, y normalmente un método de pago activo.
- Un recibo tiene varias líneas y una o más transacciones (reintentos).
- Un recibo se identifica de forma única por socio + periodo, lo que evita cobrar dos veces el mismo mes.



## Flujo con Redsys

El socio registra su tarjeta una vez en la página del banco; a partir de ahí la web cobra cada mes sin que tenga que hacer nada. Los nombres de parámetros son los habituales de Redsys y hay que confirmarlos con el manual de integración del banco.

[embedded content: cobro mensual · 6 pasos, 1 decisión

Solo los recibos fallidos tras los reintentos requieren atención del socio o de tesorería.

**1. Registro de tarjeta (primer pago)**

1. El socio paga su primer recibo (matrícula + parte proporcional) desde la web.
2. La web redirige a la página segura de Redsys con `DS_MERCHANT_IDENTIFIER=REQUIRED`, `DS_MERCHANT_COF_INI=S` y `DS_MERCHANT_COF_TYPE=R` (pago recurrente). El socio se autentica con su banco (3D Secure).
3. Redsys envía la notificación a la URL de la web con la referencia (`Ds_Merchant_Identifier`), el identificador de la operación inicial (`Ds_Merchant_Cof_Txnid`) y la caducidad (`Ds_ExpiryDate`).
4. La web verifica la firma, marca el recibo como pagado y guarda la referencia en `metodos_pago`.

Para cambiar de tarjeta sin pagar un recibo, preguntar al banco si permite una operación de validación sin importe.

**2. Cobro mensual (sin el socio delante)**

1. El cron del día 1 recorre los recibos aprobados.
2. Para cada uno, llamada a la API REST de Redsys con la referencia, `DS_MERCHANT_DIRECTPAYMENT=true`, `DS_MERCHANT_EXCEP_SCA=MIT`, `DS_MERCHANT_COF_INI=N`, `DS_MERCHANT_COF_TYPE=R` y el `DS_MERCHANT_COF_TXNID` guardado.
3. La respuesta llega en la misma llamada: un `Ds_Response` entre 0000 y 0099 significa autorizado.
4. La web registra la transacción y actualiza el recibo a pagado o fallido.

**3. Detalles técnicos**

- **Número de pedido (**`Ds_Order`**):** de 4 a 12 caracteres, los 4 primeros numéricos, único por intento. Propuesta: periodo AAMM + id del recibo + número de intento.
- **Importe:** en céntimos y moneda 978 (euro), que coincide con cómo se guarda en la base de datos.
- **Firma:** HMAC SHA-256 con la clave del comercio, tanto al enviar como al recibir.
- **Caducidad de tarjeta:** aviso al socio 30 días antes; si caduca, el recibo pasa a pago manual hasta que registre otra.
- **Pago manual:** un recibo fallido se paga desde el área de socio con el mismo flujo del registro de tarjeta, lo que aprovecha para guardar la nueva tarjeta.



## Panel de administración y área de socio

El administrador gestiona excepciones y revisa el lote mensual; el socio gestiona su tarjeta y pide sus cambios.

**Panel de administración** (rol con permiso propio, por ejemplo `sulayr_tesoreria`)

- **Resumen del mes:** total a cobrar, cobrado, pendiente y fallido, con la lista de impagos.
- **Borrador de recibos:** lista de socios con el desglose de cada recibo, edición puntual y botón de aprobar el lote.
- **Ficha de socio:** servicios con sus fechas, ajustes, historial de recibos y estado de la tarjeta.
- **Solicitudes pendientes:** altas y bajas de servicios pedidas por socios, para aprobar o rechazar.
- **Tarifas:** precios y fechas de vigencia.
- **Avisos:** tarjetas que caducan en los próximos 30 días y recibos fallidos tras reintentos.
- **Exportación:** listado mensual en CSV, que sustituye a la pestaña del Excel.

**Área de socio**

- Ver su cuota actual y el importe previsto del próximo recibo.
- Historial de recibos con su estado.
- Pedir alta o baja en natación o cambio de cuota, con aviso de la fecha de corte.
- Añadir o cambiar la tarjeta (redirige a la página segura del banco).
- Pagar a mano un recibo fallido.

**Notificaciones por email**

- Al socio: cobro realizado, cobro fallido, tarjeta a punto de caducar, solicitud aprobada o rechazada.
- Al administrador: borrador listo para revisar, resumen tras el cobro.

**Apunte para la revisión** (28 de octubre de 2026, sin decidir)

Cinco días antes del vencimiento, mandar a cada socio el resumen de lo que se le va a cobrar: la cuantía de su tarifa más los servicios añadidos. Por dos vías:

- Correo.
- Aviso en su perfil, en Comunicaciones o Alertas.

El aviso interno puede llevar directamente al pago. Al revisarlo hay que encajarlo con el cobro automático del día 1: si la tarjeta ya está guardada, el resumen informa de lo que se cobrará; el enlace de pago sirve cuando no hay tarjeta o el cobro anterior falló.

## Seguridad y protección de datos

La web nunca ve ni guarda datos de tarjeta: el socio los introduce en la página del banco y la web solo recibe una referencia. Eso deja el plugin fuera del ámbito de la normativa de seguridad de tarjetas (PCI DSS).

**Pagos**

- Verificar la firma de cada notificación de Redsys antes de cambiar el estado de un recibo.
- Tratar cada notificación como idempotente: si el recibo ya está pagado, no volver a procesarla.
- La clave del comercio fuera de la base de datos (en `wp-config.php` o variable de entorno).
- La URL de notificación solo por HTTPS.
- Un cron que no se ejecute dos veces a la vez (bloqueo) y que nunca cobre un recibo que no esté aprobado.

**Acceso**

- Solo el rol de tesorería ve importes e historial de pagos de otros socios.
- Todas las acciones de administración comprueban permisos y nonce de WordPress.
- Registro de auditoría de cada cambio en servicios, ajustes y recibos.

**Protección de datos**

- Añadir al formulario de alta online la información sobre el tratamiento de datos de pago y la autorización expresa para cargos recurrentes en la tarjeta.
- Definir cuánto tiempo se conservan recibos y transacciones (obligaciones fiscales) y cuándo se borra la referencia de tarjeta tras una baja.
- Incluir al banco como encargado o responsable del tratamiento en el registro de actividades del club.



## Preguntas abiertas y plan

**Al banco**

Enviado el 28 de octubre de 2026. Pendiente de respuesta.

- [ ] ¿Activan pago por referencia con operativa de cobros recurrentes iniciados por el comercio?
- [ ] ¿Hay cuota de alta del TPV virtual?
- [ ] ¿Pueden quitar o rebajar los 5 €/mes de mantenimiento por ser asociación deportiva?
- [ ] ¿Incluye Bizum y con qué comisión?
- [ ] ¿Hay comisión mínima o fija por operación además del 0,70 %?
- [ ] ¿Tienen entorno de pruebas y manual de integración con la API REST?

**A la junta**

- [x] ¿Los 3,96 € de SportEasy cubren todo el servicio o solo los pagos? Solo los pagos. Con los datos de octubre de 2026, la diferencia con el TPV es de unos 20 € al año. El ahorro está en el tiempo de gestión.
- [ ] Confirmar fecha de corte (día 25) y de cobro (día 1).
- [ ] Regla de prorrateo para altas a mitad de mes.
- [ ] Cuántos reintentos tras un cobro fallido y si se aplica penalización por retraso.
- [ ] ¿Los cambios de servicio pedidos por socios se aplican solos o necesitan aprobación?
- [x] ¿Qué pasa con el socio que paga ahora con suscripción de Stripe? Ya no se usa: Stripe se elimina (spec de altas, D57).
- [ ] Recordatorio 5 días antes del vencimiento, con el importe de la tarifa y los toppings, por correo y por aviso en el perfil (Comunicaciones o Alertas), con enlace al pago. Ver el apunte del apartado de notificaciones.

**Plan de implantación**

1. Contratar el TPV y obtener credenciales del entorno de pruebas.
2. Crear tablas, catálogo de tarifas y cargar los socios con sus servicios actuales.
3. Primer pago con guardado de tarjeta y notificación, en pruebas.
4. Generación de borrador, aprobación y cobro recurrente, en pruebas.
5. Panel de administración y área de socio.
6. Piloto real con la junta directiva durante un mes, en paralelo con el Excel.
7. Migración del resto de socios: cada uno registra su tarjeta antes del día de corte.
8. Dejar de cobrar mensualidades por SportEasy.

