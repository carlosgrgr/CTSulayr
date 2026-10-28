# SPEC — Facturación

## Resumen mensual del coste del sistema de reservas y liquidación con CN Nazarí

**Proyecto:** Club Triatlón Sulayr / sulayr-reservas  
**Estado:** Cerrada. Implementada, probada y desplegada en PROD el 28 de octubre de 2026. El enlace «Facturación» del menú del panel está comentado hasta que se empiece a usar. Decisiones cerradas (sección 12). Pruebas en `ACEPTACION.md`.  
**Fecha:** octubre 2026

---

# 1. Contexto

CN Nazarí usa el sistema de reservas de natación del Club Triatlón Sulayr. A cambio paga una cuota mensual que depende del número de usuarios activos.

Además, el Sulayr paga a CN Nazarí un importe por cada deportista. El coste del sistema de reservas se descuenta de ese pago. El pago lo hace el Sulayr a mano; este sistema solo calcula y muestra el dato.

La tabla de nadadores solo guarda el estado actual, y al retirar a un nadador se borra su fila. Por eso cada mes se guarda una **foto** de los nadadores activos en el momento de generar el resumen.

Esta fase añade un **resumen mensual** que hace las cuentas de forma automática y guarda un histórico. No debe hacer falta ningún cálculo manual.

---

# 2. Objetivos

- Calcular el coste mensual del sistema de reservas cuando se genere el resumen del mes.
- Calcular el importe neto que el Sulayr transfiere a CN Nazarí.
- Mostrar el detalle de los usuarios facturados, para que el cálculo se pueda comprobar.
- Mostrar un histórico de los resúmenes de meses ya terminados.
- Mantener sin cambios los resúmenes de los meses ya terminados.
- No cambiar el comportamiento actual de reservas, nadadores, sesiones ni sincronización.

---

# 3. Alcance

## 3.1. Incluido

- Generación del resumen del mes en curso a petición, desde el `wp-admin` del site de reservas.
- Generación automática el día 20 si el mes no se ha generado a mano.
- Un único resumen por mes, guardado en base de datos como histórico.
- Tarifa e importe por deportista configurables, con registro de cambios.
- Página de ajustes de facturación en el `wp-admin` del site de reservas.
- Sección «Facturación» de consulta en el panel del site de reservas.
- Exportación CSV del detalle de usuarios de un mes.

## 3.2. Fuera de alcance

- Emitir facturas legales. Lo que se genera es un **resumen**, no una factura: no lleva numeración legal, NIF ni IVA.
- Cobros o transferencias automáticas (Stripe, remesas, etc.).
- Controlar la fecha o el estado del pago. El pago es manual.
- Facturar a clientes distintos de CN Nazarí. Sería otra fase.
- Generar meses anteriores a la instalación de esta fase o meses ya terminados.
- Corregir un mes terminado desde el sistema. Si hay un error, se ajusta fuera del sistema, en la transferencia.
- Guardar el historial día a día del estado de cada nadador.

---

# 4. Tarifa

## 4.1. Tarifa inicial


| Concepto                            | Valor      |
| ----------------------------------- | ---------- |
| Cuota base mensual                  | 9,00 €     |
| Usuarios incluidos en la cuota base | 25         |
| Precio por usuario adicional        | 0,50 €/mes |
| Importe máximo mensual              | 49,00 €    |


No se aplica IVA.

Al instalar, la tarifa se crea con estos valores. La entrada queda en el registro de cambios (4.4) como «valor inicial (sistema)».

## 4.2. Fórmula

```
usuarios_adicionales = max(0, usuarios_facturables − usuarios_incluidos)
coste = min(cuota_base + usuarios_adicionales × precio_adicional, importe_maximo)
```

Con la tarifa inicial, el tope de 49,00 € se alcanza con 105 usuarios facturables.

**Mes sin sesiones:** si el mes no tiene ninguna sesión habilitada, el coste es la cuota base (9,00 € con la tarifa inicial), haya los usuarios activos que haya.

Un mes **tiene sesiones** si hay al menos una sesión habilitada (`enabled = 1`) con fecha dentro del mes. Cuentan también las sesiones previstas para días posteriores a la generación. No importa que tengan reservas o no.

El importe se redondea a céntimos.

## 4.3. Ajustes de facturación

- Los cuatro valores de la tarifa y el importe por deportista (6.1) se configuran en una **página de ajustes del site de reservas** (CN Nazarí), dentro de su `wp-admin`. No están en los ajustes de red.
- Pueden cambiarlos el **superadministrador de la red** y el **administrador del site de reservas**. El **gestor no puede**.
- El permiso se controla con la capacidad `sulayr_manage_billing` (sección 9).
- Un cambio se aplica **en cuanto se guarda**. No hay mes de inicio de validez. Afecta al resumen del mes en curso la próxima vez que se genere.
- Un cambio no modifica los resúmenes de meses ya terminados.

**Validación al guardar:**

- Los importes son números ≥ 0 con dos decimales como máximo.
- Los usuarios incluidos son un número entero ≥ 0.
- El importe máximo mensual es ≥ la cuota base.

## 4.4. Registro de cambios

Cada cambio de un valor de la tarifa o del importe por deportista guarda:

- el usuario que lo hizo;
- la fecha y hora;
- el valor anterior y el nuevo.

El registro se ve solo en la página de ajustes, para el administrador del site de reservas y el superadministrador. No se muestra en la sección «Facturación». Sirve para que el Sulayr compruebe cualquier cambio.

---

# 5. Usuarios facturables

## 5.1. Quién cuenta

Un usuario es **facturable en un mes** si tiene la ficha de nadador **activa en el momento en que se genera el resumen** de ese mes. Es una foto del momento, no un historial.

- Cuentan todos los nadadores activos: los vinculados al club Sulayr y los propios de CN Nazarí.
- Un nadador activo cuenta aunque no haya reservado ninguna sesión ese mes.
- Un gestor que también tiene ficha de nadador activa cuenta.
- Los nadadores inactivos no cuentan.
- Las cuentas sin ficha de nadador (administradores, gestores que no nadan) no cuentan.
- Cada nadador cuenta **una sola vez**, porque tiene una única ficha.

## 5.2. Consecuencias de la foto

- Quien se da de alta después de la última generación del mes no cuenta ese mes. Si se vuelve a generar antes de que termine el mes, sí cuenta.
- Quien se da de baja, se desactiva o se retira antes de generar no cuenta ese mes, aunque estuviera activo unos días.
- Un nadador activado por error a principios de mes deja de contar si se desactiva y se vuelve a generar.
- El tipo (Sulayr / CN Nazarí) es el que tiene la ficha en el momento de generar.

## 5.3. Ejemplo

Octubre, con sesiones habilitadas:

- El día 10 se dan de baja 2 nadadores.
- El día 20 se genera el resumen con 30 nadadores activos. Coste: 9,00 + (30 − 25) × 0,50 = **11,50 €**.
- El día 25 se dan de alta 4 nadadores.
- Si el administrador vuelve a generar el día 28, hay 34 activos. Coste: 9,00 + (34 − 25) × 0,50 = **13,50 €**. Este resumen sustituye al del día 20.
- Si no se vuelve a generar, octubre queda con 11,50 €.

---

# 6. Liquidación con el Sulayr

## 6.1. Cálculo

```
Pago del Sulayr a CN Nazarí  = deportistas Sulayr del mes × importe por deportista
− Coste del sistema de reservas del mes
= Importe neto a transferir
```

Todos los datos salen del mismo resumen mensual.

**Deportistas Sulayr.** Son los nadadores con ficha **activa y vinculada al club** en el momento de generar el resumen.

- Cada uno cuenta una vez, con el **importe completo**.
- Un nadador que ya pasó de «vinculado al club» a «CN Nazarí» cuando se genera no cuenta como deportista Sulayr.
- Los socios del club que no tienen ficha activa en el sistema de reservas no cuentan.

**Importe por deportista.** Se configura en los ajustes de facturación (4.3) y queda en el registro de cambios (4.4). Al instalar empieza **vacío**.

**Sin importe configurado.** Mientras no haya importe por deportista:

- la sección «Facturación» y la página de ajustes muestran el aviso «Importe por deportista sin configurar»;
- el resumen se genera igualmente, con el coste del sistema calculado y la liquidación como «pendiente»;
- cuando se configura el importe, basta con volver a generar el mes en curso para completar su liquidación.

Un mes que termina con la liquidación pendiente se queda así, porque los meses terminados no se regeneran (7.4).

**Neto negativo.** Si el coste del sistema supera el pago por deportistas, el neto se muestra como «CN Nazarí debe al Sulayr X €». No se arrastra a otros meses.

## 6.2. Pago

- El sistema no controla cuándo se paga. Solo muestra el dato.
- El resumen del mes en curso se marca como **provisional**, porque se puede regenerar hasta que termine el mes. Si se paga antes de esa fecha, la cifra todavía puede cambiar.
- Cuando el mes termina, su resumen pasa a **definitivo**.

---

# 7. Generación del resumen mensual

## 7.1. Generación manual

- En la página de ajustes de facturación (4.3) hay un botón **«Generar resumen del mes en curso»**.
- Lo pueden usar el superadministrador de la red y el administrador del site de reservas (`sulayr_manage_billing`). El gestor no.
- Solo se genera el **mes en curso**, en hora de `Europe/Madrid`.
- Se puede generar todas las veces que haga falta mientras el mes no termine. Cada generación **sustituye** al resumen anterior del mismo mes. Hay **un único resumen por mes**.

## 7.2. Generación automática

- Si el día 20 el mes en curso no tiene resumen, el sistema lo genera solo. Queda registrado como generado por «sistema».
- Si el mes ya se generó a mano antes del día 20, la generación automática no se ejecuta.
- Una generación manual posterior sustituye a la automática.
- WP-Cron solo se ejecuta cuando alguien visita la web, así que no basta con él. Si la tarea no se ha ejecutado, el sistema también genera el mes la primera vez que alguien abra la sección «Facturación» o la página de ajustes, a partir del día 20 y antes de que termine el mes.
- El día 20 es fijo, no configurable.

## 7.3. Qué se guarda

Cada resumen guarda:

- el mes;
- el número de usuarios facturables;
- el número de deportistas Sulayr;
- si el mes tiene sesiones;
- la tarifa y el importe por deportista vigentes **en el momento de generar**;
- el coste;
- la liquidación, o «pendiente» si no había importe;
- la lista de usuarios facturados, con nombre y tipo (Sulayr / CN Nazarí);
- quién lo generó (usuario o «sistema») y la fecha y hora.

No se guardan el DNI, el teléfono ni el email. La lista guarda el nombre para que el mes siga siendo comprobable aunque el nadador se retire, porque la retirada borra su ficha.

## 7.4. Estado de un mes

- **Provisional:** el mes en curso, si ya tiene resumen. Se puede regenerar.
- **Definitivo:** un mes terminado. Su último resumen queda fijo y **no cambia**, aunque después se edite, desactive o retire a un nadador, o se cambie la tarifa o el importe por deportista. No se puede regenerar.
- **Sin generar:** el mes en curso sin ningún resumen todavía, o un mes terminado que nunca llegó a generarse.
- **Sin datos:** los meses anteriores a la instalación.

El primer mes que se puede generar es el **mes de instalación** de esta fase.

---

# 8. Sección «Facturación»

## 8.1. Acceso

- Solo la ven los usuarios con rol **gestor** o **administrador** del site de reservas, y el superadministrador de la red.
- Se controla con una capacidad nueva, `sulayr_view_billing`, no por nombre de rol.
- La sección forma parte del panel `[sulayr_reservas]` del frontend. No usa `wp-admin` ni `admin-post.php`.
- La sección es de consulta. El resumen se genera y los valores económicos se cambian en la página de ajustes (4.3, 7.1), no desde aquí.

## 8.2. Contenido

**Mes en curso:**

- El último resumen generado, marcado como «provisional».
- Si no hay ninguno, «sin generar».
- No hay una estimación en vivo aparte. Quien necesite la cifra al día genera el resumen.

**Histórico** (meses terminados):

- Mes.
- Estado (definitivo / sin generar).
- Usuarios facturables.
- Deportistas Sulayr.
- Si tuvo sesiones.
- Coste del sistema.
- Pago por deportistas.
- Importe neto, «CN Nazarí debe al Sulayr X €» si es negativo, o «pendiente» si no había importe.
- Fecha de generación y quién lo generó.

**Detalle de un mes:**

- Lista de usuarios facturados, con nombre y tipo (Sulayr / CN Nazarí).
- Exportación a CSV.

Cada resumen (mes en curso y detalle de un mes) muestra:

- Datos: estado, nadadores (usuarios facturables), deportistas CT Sulayr, nadadores CN Nazarí y tarifa aplicada.
- Cálculo, destacado: precio base, usuarios adicionales, coste total, pago de CT Sulayr y cálculo final (importe neto).

La fórmula completa, paso a paso, y quién generó el resumen del mes en curso se ven en la página de ajustes. Si no hay importe por deportista, se muestra el aviso de 6.1.

---

# 9. Requisitos técnicos

- **Resúmenes mensuales.** Una tabla nueva en el site de reservas guarda un resumen por mes, con clave única por mes. Otra tabla, o un campo serializado, guarda la lista de usuarios de cada resumen. Regenerar sustituye el resumen y su lista dentro de una transacción, para que nunca quede un resumen a medias. Si dos generaciones del mismo mes coinciden, gana la última.
- **Registro de cambios de valores.** Una tabla nueva en el site de reservas guarda cada cambio de la tarifa y del importe por deportista (4.4).
- **Arranque.** Al instalar se registra la tarifa inicial (4.1). No hace falta ningún dato previo de nadadores.
- **Tarea programada.** Una tarea diaria comprueba si es día 20 o posterior y el mes en curso no tiene resumen; en ese caso lo genera. La misma comprobación se ejecuta al abrir la sección «Facturación» o la página de ajustes (7.2).
- **Capacidades.** Dos capacidades nuevas, creadas dentro de `BlogContext::onBookings()` para que no lleguen al site del club:
  - `sulayr_view_billing`: roles `gestor` y `administrator` del site de reservas.
  - `sulayr_manage_billing`: solo rol `administrator` del site de reservas. Nunca se da a `gestor`.
  - El superadministrador de la red tiene las dos automáticamente.
- **Página de ajustes.** Se registra en el menú de `wp-admin` del site de reservas y exige `sulayr_manage_billing`. Contiene los valores económicos, el botón de generar y el registro de cambios.
- **Esquema.** Las tablas nuevas se crean con `dbDelta` y se sube `sulayr_reservas_db_version`. No se borran ni se modifican datos existentes.
- **Sin cambios en nadadores.** Esta fase no añade enganches en los caminos de alta, baja, activación, retirada ni sincronización. Solo lee la tabla de nadadores al generar.
- **Fechas.** Se usan `wp_timezone()` y `DateTimeImmutable`.
- **Compatibilidad.** El código es compatible con PHP 7.4.

---

# 10. Seguridad

- Comprobar nonce y capacidad en todas las acciones: generar el resumen, guardar los ajustes y exportar el CSV.
- El nadador no ve la sección ni los datos de otros nadadores.
- Solo el superadministrador de la red y el administrador del site de reservas pueden generar el resumen y cambiar la tarifa y el importe por deportista. El gestor no puede.
- Todo cambio de esos valores queda en el registro de cambios (4.4), y cada resumen guarda quién lo generó.

---

# 11. Criterios de aceptación

1. Un nadador activo al generar cuenta 1.
2. Un nadador inactivo al generar no cuenta, aunque estuviera activo antes ese mes.
3. Un nadador dado de alta después de la última generación del mes no cuenta ese mes. Si se regenera antes de fin de mes, cuenta.
4. Un nadador retirado antes de generar no cuenta.
5. Un gestor con ficha de nadador activa cuenta. Un gestor sin ficha no cuenta.
6. Con 25 usuarios o menos, el coste es 9,00 €.
7. Con 36 usuarios, el coste es 14,50 €.
8. Con 105 usuarios o más, el coste es 49,00 €.
9. En un mes sin ninguna sesión habilitada, el coste es 9,00 €, haya los usuarios activos que haya. Si hay al menos una sesión habilitada en el mes, aunque sea posterior a la generación, se aplica la fórmula normal.
10. Generar dos veces el mismo mes deja un único resumen, el último.
11. Solo se puede generar el mes en curso. Un mes terminado no se puede regenerar y no cambia al desactivar o retirar nadadores ni al cambiar la tarifa o el importe.
12. El resumen usa la tarifa y el importe por deportista vigentes al generarlo.
13. Si el día 20 el mes no tiene resumen, se genera automáticamente como «sistema». Si ya se había generado a mano, no.
14. Si la tarea del día 20 no se ha ejecutado, abrir la sección «Facturación» o la página de ajustes a partir del día 20 genera el resumen.
15. Un nadador no puede ver la sección ni descargar el CSV.
16. Un gestor no puede generar el resumen, ni abrir la página de ajustes, ni guardar valores. Un administrador del site de reservas sí.
17. El neto de la liquidación es igual a pago por deportistas − coste del sistema.
18. Cuenta como deportista Sulayr solo el nadador activo y vinculado al club al generar. Un nadador de CN Nazarí no cuenta.
19. Si el neto es negativo, se muestra «CN Nazarí debe al Sulayr X €» y el mes siguiente no lo arrastra.
20. Sin importe por deportista, la liquidación aparece como «pendiente». Tras configurar el importe y regenerar el mes en curso, se completa.
21. Cada cambio de valores queda en el registro con usuario, fecha y hora, valor anterior y nuevo.
22. La página de ajustes rechaza importes negativos, más de dos decimales, usuarios incluidos no enteros y un tope menor que la cuota base.
23. El resumen del mes en curso se muestra como «provisional» y el de un mes terminado como «definitivo». Un mes sin resumen se muestra como «sin generar».
24. Cada resumen muestra quién lo generó y cuándo en la página de ajustes y en el histórico.

---

# 12. Decisiones tomadas


| #   | Decisión                                          | Resultado                                                                                                                                                                                 |
| --- | ------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| P1  | Importe que paga el Sulayr por deportista         | Configurable en los ajustes de facturación del site de reservas. Empieza vacío. Sin importe, la liquidación queda «pendiente»; se completa regenerando el mes en curso (6.1).             |
| P2  | Criterio para contar a los deportistas del Sulayr | Nadadores con ficha activa y vinculada al club en el momento de generar. Cuentan una vez y con el importe completo (6.1).                                                                 |
| P3  | Neto negativo                                     | Se muestra «CN Nazarí debe al Sulayr X €» y no se arrastra a otros meses (6.1).                                                                                                           |
| P4  | Día de pago                                       | El sistema no lo controla; el pago es manual. El resumen se marca «provisional» mientras el mes no termina y «definitivo» después (6.2).                                                  |
| D5  | Quién cambia la tarifa y el importe, y dónde      | El superadministrador de la red y el administrador del site de reservas, desde una página de ajustes del `wp-admin` del site de reservas. El gestor no puede (4.3).                       |
| D6  | Validez de los cambios                            | Sin mes de inicio de validez. Un cambio se aplica en cuanto se guarda y afecta a la siguiente generación (4.3).                                                                           |
| D7  | Registro de cambios                               | Se guarda y se muestra cada cambio con usuario, fecha y hora, valor anterior y nuevo (4.4).                                                                                               |
| D8  | Valores usados en un resumen                      | Los vigentes en el momento de generar (7.3).                                                                                                                                              |
| D9  | Tarifa inicial y validación                       | Se crea al instalar con los valores de 4.1. Validación de los valores al guardar (4.3).                                                                                                   |
| D10 | Corrección de meses terminados                    | No se pueden corregir desde el sistema. Los ajustes se hacen fuera, en la transferencia (3.2).                                                                                            |
| D11 | Cómo se genera el resumen                         | A petición, con un botón en la página de ajustes, por quien tenga `sulayr_manage_billing`. Solo el mes en curso. Un único resumen por mes; cada generación sustituye a la anterior (7.1). |
| D12 | Generación automática                             | El día 20, si el mes no tiene resumen manual. Si WP-Cron falla, se genera al abrir la sección o los ajustes a partir del día 20 (7.2).                                                    |
| D13 | Qué cuenta como usuario facturable                | Foto del momento: nadadores activos al generar. Se elimina el historial de periodos de actividad y la comprobación diaria (5.1, 9).                                                       |
| D14 | Mes sin sesiones                                  | Solo si el mes no tiene ninguna sesión habilitada, incluidas las previstas después de generar (4.2).                                                                                      |
| D15 | Mes en curso en la sección «Facturación»          | Se muestra el último resumen generado, sin estimación en vivo aparte (8.2).                                                                                                               |
| D16 | Primer mes                                        | El mes de instalación (7.4).                                                                                                                                                              |


