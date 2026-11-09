# Manual del administrador — Tienda: nombre en los tritrajes y exportación de pedidos

Este manual es para quien gestiona la tienda del club. Explica cómo se activa el nombre a estampar en una prenda, qué ve el cliente y cómo exportar los pedidos a CSV para enviarlos al fabricante.

No incluye capturas. Los nombres en negrita son los textos de los botones y los apartados del menú.

La especificación técnica completa está en `docs/club/SPEC-TIENDA-ONLINE.md`.

## 1. Qué es

Los tritrajes se pueden personalizar con un nombre o apellido. En la ficha de esas prendas aparece la caja **Nombre a estampar (opcional)**, debajo de la talla. El nombre no cambia el precio.

El nombre viaja con el pedido: se ve en el carrito, en la compra, en los correos, en **Mi cuenta → Pedidos** y en la edición del pedido.

## 2. Activar el nombre en una prenda

1. **Productos** → abre la prenda.
2. En **Datos del producto**, pestaña **General**, marca **Permitir nombre a estampar**.
3. Pulsa **Actualizar**.

Está activado en los 4 tritrajes. Para quitarlo, desmarca la casilla y actualiza. Los pedidos ya hechos conservan su nombre.

## 3. Qué puede escribir el cliente

- Es opcional: si deja la caja vacía, compra la prenda sin nombre.
- Máximo **12 caracteres**, contando los espacios.
- Letras (también con tilde, **Ü** y **Ñ**), números y espacios. No se admiten guiones, puntos, apóstrofos, la **Ç** ni acentos que no sean del español. Si escribe alguno, la web le avisa y no añade la prenda al carrito.
- Se guarda siempre en **MAYÚSCULAS**, y los espacios repetidos se quedan en uno.

Si compra dos tritrajes con nombres distintos, aparecen como dos líneas en el carrito y en el pedido.

## 4. Corregir un nombre en un pedido

Si el cliente pide un cambio antes de enviar el pedido al fabricante:

1. **WooCommerce → Pedidos** → abre el pedido.
2. En la línea del tritraje, pulsa el lápiz (**Editar**).
3. Cambia el valor del campo que se llama `sulayr_print_name`: es el nombre interno de **Nombre a estampar** y solo se ve así al editar. Escríbelo en mayúsculas y con las mismas reglas del apartado 3: aquí la web no lo comprueba.
4. Pulsa **Guardar** y después **Actualizar**.

## 5. Exportar pedidos a CSV

1. **WooCommerce → Pedidos**.
2. Marca las casillas de los pedidos que quieres enviar al fabricante. Solo se exportan los marcados.
3. En **Acciones en lote**, elige **Exportar equipación a CSV** y pulsa **Aplicar**.
4. Se descarga `equipacion-pedidos-AAAAMMDD-HHMM.csv`. Se abre directamente con Excel.

El archivo tiene una fila por prenda, con estas columnas: **Pedido**, **Fecha**, **Estado**, **Cliente**, **Email**, **Teléfono**, **Producto**, **Talla**, **Cantidad** y **Nombre a estampar**.

- Salen todas las prendas de los pedidos marcados, lleven nombre o no. Las que no llevan nombre tienen esa columna vacía.
- Las cuotas no salen: no son prendas.
- Si una prenda tiene más de una opción (por ejemplo, la sudadera), la columna **Talla** muestra las dos: «M / Con cremallera».

## 6. Saber qué pedidos ya se han exportado

Cada pedido exportado recibe una nota privada: «Incluido en la exportación de equipación del dd/mm/aaaa hh:mm.» La ves en la columna derecha de la edición del pedido. El cliente no la ve.

Exportar otra vez el mismo pedido no da error: añade otra nota con la nueva fecha.

## 7. Quién puede exportar

Los administradores y los gestores de la tienda. Los deportistas y los clientes no tienen acceso a la lista de pedidos.

## 8. Comprobación técnica tras desplegar

1. Abre un tritraje en la tienda: debe aparecer la caja **Nombre a estampar (opcional)**. En otra prenda, como la sudadera, no debe aparecer.
2. En **WooCommerce → Pedidos**, el desplegable **Acciones en lote** debe incluir **Exportar equipación a CSV**.
