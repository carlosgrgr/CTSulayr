# SPEC — Facturación

## Resumen mensual del coste del sistema de reservas y liquidación con CN Nazarí

**Proyecto:** Club Triatlón Sulayr / sulayr-reservas  
**Fase:** 12  
**Estado:** Especificada. Sin implementar. Tiene decisiones pendientes (sección 12).  
**Fecha:** octubre 2026

---

# 1. Contexto

CN Nazarí usa el sistema de reservas de natación del Club Triatlón Sulayr. A cambio paga una cuota mensual que depende del número de usuarios activos.

Además, el Sulayr paga a CN Nazarí un importe por cada deportista los días 15 de cada mes. El coste del sistema de reservas se descuenta de ese pago.

Hoy no hay forma de saber cuántos nadadores estaban activos en una fecha pasada. La tabla de nadadores solo guarda el estado actual. Al retirar a un nadador se borra su fila.

Esta fase añade una sección de **Facturación** que hace las cuentas de forma automática. No debe hacer falta ningún cálculo manual.

---

# 2. Objetivos

- Calcular de forma automática el coste mensual del sistema de reservas.
- Calcular el importe neto que el Sulayr transfiere a CN Nazarí el día 15.
- Mostrar el detalle de los usuarios facturados, para que el cálculo se pueda comprobar.
- Mantener sin cambios las liquidaciones de los meses ya cerrados.
- No cambiar el comportamiento actual de reservas, nadadores, sesiones ni sincronización.

---

# 3. Alcance

## 3.1. Incluido

- Registro histórico de los periodos en que cada nadador está activo.
- Tarifa configurable con fecha de inicio de validez.
- Cálculo y cierre mensual del coste del sistema.
- Cálculo de la liquidación con el Sulayr.
- Sección «Facturación» en el panel del site de reservas.
- Exportación CSV del detalle de usuarios de un mes.

## 3.2. Fuera de alcance

- Emitir facturas legales. Lo que se genera es un **resumen**, no una factura: no lleva numeración legal, NIF ni IVA.
- Cobros o transferencias automáticas (Stripe, remesas, etc.).
- Facturar a clientes distintos de CN Nazarí. Sería otra fase.
- Recalcular meses anteriores a la instalación de esta fase.

---

# 4. Tarifa

## 4.1. Tarifa inicial

| Concepto | Valor |
|---|---|
| Cuota base mensual | 9,00 € |
| Usuarios incluidos en la cuota base | 25 |
| Precio por usuario adicional | 0,50 €/mes |
| Importe máximo mensual | 49,00 € |

No se aplica IVA.

## 4.2. Fórmula

```
usuarios_adicionales = max(0, usuarios_facturables − 25)
coste = min(9,00 + usuarios_adicionales × 0,50, 49,00)
```

El tope de 49,00 € se alcanza con 105 usuarios facturables.

**Mes sin sesiones:** si en el mes no se ha celebrado ninguna sesión, el coste es la cuota base (9,00 €), haya los usuarios activos que haya.

Una **sesión celebrada** es una sesión habilitada (`enabled = 1`) con fecha dentro del mes. No importa que tenga reservas o no.

El importe se redondea a céntimos.

## 4.3. Tarifa configurable

- Los cuatro valores de la tabla se pueden configurar.
- Solo el **superadministrador de la red** puede cambiarlos, desde los ajustes de red del plugin. El gestor y el administrador del site de reservas no pueden.
- Cada cambio de tarifa lleva un mes de inicio de validez, que es el mes en curso o uno posterior. Un mes se calcula con la tarifa vigente en ese mes.
- Cambiar la tarifa no modifica los meses ya cerrados.

---

# 5. Usuarios facturables

## 5.1. Quién cuenta

Un usuario es **facturable en un mes** si ha tenido la ficha de nadador **activa al menos un día del mes natural**, en hora de `Europe/Madrid`.

- Cuentan todos los nadadores activos: los vinculados al club Sulayr y los propios de CN Nazarí.
- Un nadador activo cuenta aunque no haya reservado ninguna sesión ese mes.
- Un gestor que también tiene ficha de nadador activa cuenta.
- Los nadadores inactivos no cuentan durante los días en que están inactivos.
- Las cuentas sin ficha de nadador (administradores, gestores que no nadan) no cuentan.

## 5.2. Altas y bajas durante el mes

- Un nadador cuenta **una sola vez** por mes, aunque se active y se desactive varias veces.
- Quien se da de alta el último día del mes cuenta ese mes.
- Quien se da de baja el día 1 cuenta ese mes.
- Un nadador retirado del site sigue contando en los meses en que estuvo activo.
- Pasar de «vinculado al club» a «CN Nazarí» no lo convierte en un usuario nuevo. Sigue contando una sola vez.

## 5.3. Ejemplo

Octubre con sesiones celebradas:

- 30 nadadores activos todo el mes.
- 4 altas el día 20.
- 2 bajas el día 10.

Usuarios facturables: 30 + 4 + 2 = 36.

Coste: 9,00 + (36 − 25) × 0,50 = **14,50 €**.

---

# 6. Liquidación con el Sulayr

## 6.1. Cálculo

```
Pago del Sulayr a CN Nazarí  = deportistas Sulayr × importe por deportista
− Coste del sistema de reservas del mes cerrado
= Importe neto a transferir el día 15
```

- El **importe por deportista** se puede configurar. Solo lo cambia el superadministrador de la red, y cada cambio lleva un mes de inicio de validez, igual que la tarifa.
- El criterio para contar a los deportistas del Sulayr está pendiente de confirmar (sección 12).

## 6.2. Calendario

- El cálculo usa **meses naturales**. Un mes se cierra cuando termina su último día.
- El pago del día 15 del mes M+1 descuenta el coste del mes M, que ya está cerrado. El día de pago no cambia.
- Entre el día 1 y el 15, CN Nazarí puede revisar el resumen y el detalle.

---

# 7. Cierre de mes

- Al cerrarse un mes, el sistema guarda una copia fija del cálculo:
  - el número de usuarios facturables;
  - si hubo sesiones;
  - la tarifa y el importe por deportista aplicados;
  - el coste;
  - la liquidación;
  - la lista de usuarios facturados.
- El mes se cierra la primera vez que se calcula después de terminar, sea por una tarea programada o al abrir la sección de facturación. El cierre no debe depender solo de WP-Cron.
- Un mes cerrado **no cambia**, aunque después se edite, desactive o retire a un nadador.
- La copia del detalle guarda el nombre y el tipo (Sulayr / CN Nazarí) de cada usuario. Así el mes sigue siendo comprobable aunque el nadador se retire, porque la retirada borra su ficha. No se guardan el DNI, el teléfono ni el email.
- El primer mes facturable es el **primer mes natural completo** después de instalar esta fase. Los meses anteriores aparecen como «sin datos».

---

# 8. Sección «Facturación»

## 8.1. Acceso

- Solo la ven los usuarios con rol **gestor** o **administrador** del site de reservas, y el superadministrador de la red.
- Se controla con una capacidad nueva, `sulayr_view_billing`, no por nombre de rol.
- La sección forma parte del panel `[sulayr_reservas]` del frontend. No usa `wp-admin` ni `admin-post.php`.

## 8.2. Contenido

**Mes en curso** (estimación provisional):

- Usuarios facturables hasta hoy.
- Coste estimado.
- Liquidación estimada.

**Meses cerrados** (listado):

- Mes.
- Usuarios facturables.
- Si hubo sesiones.
- Coste del sistema.
- Pago por deportistas.
- Importe neto.

**Detalle de un mes:**

- Lista de usuarios facturados, con nombre, tipo (Sulayr / CN Nazarí) y periodo o periodos activos dentro del mes.
- Exportación a CSV.

En pantalla se ve siempre la tarifa aplicada y la fórmula, para que el cálculo se pueda seguir.

---

# 9. Requisitos técnicos

- **Historial de estado.** Una tabla nueva en el site de reservas guarda periodos de actividad (`user_id`, tipo, inicio, fin). Se rellena en todos los caminos que crean, activan, desactivan, cambian de tipo o retiran a un nadador:
  - el alta;
  - la importación CSV;
  - el alta de un usuario existente;
  - la activación y la desactivación;
  - el paso de «vinculado al club» a CN Nazarí;
  - la retirada;
  - la sincronización con el club.
- **Arranque.** Al instalar, se abre un periodo para cada nadador que en ese momento esté activo.
- **Comprobación diaria.** Una tarea compara el estado actual de los nadadores con los periodos abiertos y registra cualquier diferencia en el registro técnico.
- **Esquema.** Las tablas nuevas se crean con `dbDelta` y se sube `sulayr_reservas_db_version`. No se borran ni se modifican datos existentes.
- **Fechas.** Se usan `wp_timezone()` y `DateTimeImmutable`.
- **Compatibilidad.** El código es compatible con PHP 7.4.

---

# 10. Seguridad

- Comprobar nonce y capacidad en todas las acciones, incluida la exportación CSV.
- El nadador no ve la sección ni los datos de otros nadadores.
- Solo el superadministrador de la red puede cambiar la tarifa y el importe por deportista.

---

# 11. Criterios de aceptación

1. Un nadador activo todo el mes cuenta 1.
2. Un nadador dado de alta y de baja varias veces en el mes cuenta 1.
3. Un nadador retirado a mitad de mes cuenta en ese mes y no en el siguiente.
4. Un nadador inactivo todo el mes no cuenta.
5. Un gestor con ficha de nadador activa cuenta. Un gestor sin ficha no cuenta.
6. Con 25 usuarios o menos, el coste es 9,00 €.
7. Con 36 usuarios, el coste es 14,50 €.
8. Con 105 usuarios o más, el coste es 49,00 €.
9. En un mes sin sesiones celebradas, el coste es 9,00 €, haya los usuarios activos que haya.
10. Un mes cerrado no cambia al desactivar o retirar nadadores después.
11. Un cambio de tarifa no afecta a meses cerrados ni a meses anteriores a su inicio de validez.
12. Un nadador no puede ver la sección ni descargar el CSV.
13. El neto de la liquidación es igual a pago por deportistas − coste del sistema.

---

# 12. Decisiones pendientes

| # | Decisión | Propuesta |
|---|---|---|
| P1 | Importe que paga el Sulayr por deportista | Configurable. Falta el valor inicial. |
| P2 | Criterio para contar a los deportistas del Sulayr en la liquidación | El mismo que en 5.1, limitado a nadadores vinculados al club algún día del mes. Falta confirmar que coincide con el acuerdo actual con CN Nazarí. |
| P3 | Qué pasa si el neto sale negativo | Mostrarlo como «CN Nazarí debe al Sulayr X €» y no arrastrarlo a otros meses. |
| P4 | Si el pago del día 15 corresponde al mes en curso o al anterior | En ambos casos se descuenta el último mes cerrado. Falta dejarlo escrito. |
