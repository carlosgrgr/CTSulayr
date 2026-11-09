# SPEC — Tienda online: nombre en los tritrajes y exportación de pedidos

**Proyecto:** Club Triatlón Sulayr / plugin `club-triatlon-sulayr`
**Estado:** Decisiones D1 a D12 (sección 2). T1 a T3 en `main` (`2f9e0503f`) y en PROD desde el 9 de noviembre de 2026. El cambio T4 (D12, columnas propias para las opciones que no son talla) está implementado y probado en local el 9 de noviembre de 2026 en la rama `tienda-columnas-atributos`, sin commit ni despliegue (sección 9).
**Fecha:** 9 de noviembre de 2026

La tienda ya funciona con WooCommerce en el sitio del club (blog 1): catálogo de equipación 2025-2026, carrito y compra. Esta spec cubre lo que falta: que el cliente pueda indicar un nombre para estampar en los tritrajes, y que el club pueda exportar los pedidos seleccionados a CSV para enviarlos al fabricante (Inverse).

---

## 1. Situación actual

- **Productos.** 15 prendas variables por talla (XS, S, M, L, XL), además de calcetines Wabiks y las 4 cuotas (productos simples, virtuales y ocultos). Los tritrajes son:

  | ID   | Producto                                 | Precio   |
  | ---- | ---------------------------------------- | -------- |
  | 2670 | Tritraje MASC manga corta                | 116,00 € |
  | 2676 | Tritraje MASC tirantes                   | 99,00 €  |
  | 2682 | Tritraje FEM tirantes espalda abierta    | 99,00 €  |
  | 2688 | Tritraje FEM manga corta espalda abierta | 116,00 € |

  Fuente: exportación de productos de PROD del 9 de noviembre de 2026 (`docs/club/products/wc-product-export-9-11-2026-1794246993880.csv`). El 9 de noviembre de 2026 se importó en local, sin imágenes; el importador de WooCommerce asigna IDs nuevos, así que en local los tritrajes son 2566, 2572, 2578 y 2584. Los IDs de esta tabla son los de PROD.
- **Carrito y compra.** Las páginas «Carrito» y «Finalizar compra» usan los shortcodes clásicos `[woocommerce_cart]` y `[woocommerce_checkout]`, no los bloques. La ficha de producto es la plantilla clásica de WooCommerce con Astra. Los hooks clásicos de WooCommerce funcionan en todo el recorrido.
- **Pedidos.** En local, WooCommerce guarda los pedidos en las tablas HPOS (`woocommerce_custom_orders_table_enabled = yes`). La pantalla de pedidos es `admin.php?page=wc-orders`. El código debe funcionar también con la pantalla clásica `edit.php?post_type=shop_order`, por si PROD no tuviera HPOS activo.
- **Código.** La integración con WooCommerce del plugin está en `Modules/WooCommerce/WooCommerceIntegration.php` (área del deportista en Mi cuenta). Las clases se registran en `App::load_classes()`.

## 2. Decisiones del club

| #  | Decisión |
| -- | -------- |
| D1 | El nombre solo se ofrece en los 4 tritrajes. |
| D2 | Es opcional: se puede comprar un tritraje sin nombre. |
| D3 | Máximo 12 caracteres. Se admiten letras con tilde, ñ y números. No se admiten guiones. Se guarda en mayúsculas. |
| D4 | El precio no cambia con el nombre. |
| D5 | Una sola caja de texto. |
| D6 | Exportación a CSV de los pedidos **seleccionados** en la lista de pedidos, no de todos, para no arrastrar pedidos antiguos. |
| D7 | No se añade aviso sobre devoluciones de productos personalizados. |
| D8 | Se admiten espacios entre palabras (p. ej. «DE LA TORRE») y cuentan para los 12 caracteres. |
| D9 | Letras admitidas: A–Z, Á, É, Í, Ó, Ú, Ü y Ñ. Se rechazan la cedilla y los acentos que no son del español (À, Ç, Ø…). |
| D10 | El CSV incluye todas las prendas de los pedidos seleccionados, tengan nombre o no. |
| D11 | Cada pedido exportado recibe una nota privada con la fecha de exportación. |
| D12 | En el CSV, la columna Talla solo lleva tallas. Cada opción de prenda que no es talla (hoy, «Cremallera» de la sudadera) tiene su propia columna. Decidido por el club el 9 de noviembre de 2026, tras el despliegue. |

## 3. Especificación funcional

### 3.1. Configuración del producto (administración)

- En la edición del producto, pestaña «General», aparece la casilla **«Permitir nombre a estampar»**. Por defecto está desmarcada.
- Se marca a mano en los 4 tritrajes (D1). Al desplegar en PROD hay que marcarla en los IDs 2670, 2676, 2682 y 2688 (sección 8).
- La casilla vale para cualquier producto: si en el futuro se quiere el nombre en otra prenda, basta con marcarla.

### 3.2. Ficha del producto (cliente)

En los productos marcados, encima del botón «Añadir al carrito» y debajo del selector de talla:

```
Nombre a estampar (opcional)
[                    ]
Hasta 12 caracteres: letras, números y espacios. Se estampará en mayúsculas.
```

- Campo de texto con `maxlength="12"` y la clase CSS que lo muestra en mayúsculas mientras se escribe.
- La validación definitiva se hace en el servidor al añadir al carrito (3.3). Si falla, WooCommerce muestra el error encima de la ficha y no añade el producto.

### 3.3. Reglas del texto

Al añadir al carrito, el servidor:

1. Elimina los espacios del principio y del final y sustituye varios espacios seguidos por uno. Recompone las letras escritas como letra + tilde combinada (algunos teclados de Mac e iOS): con la extensión `intl` de PHP, en general; sin ella, las del español (á, é, í, ó, ú, ü, ñ). En local no hay `intl`.
2. Si queda vacío: se añade el producto sin nombre (D2).
3. Lo pasa a mayúsculas con `mb_strtoupper()`, de modo que «ñ» y las vocales con tilde quedan bien.
4. Comprueba los caracteres: solo `A-Z`, `Á É Í Ó Ú Ü Ñ`, `0-9` y el espacio (D8, D9). Cualquier otro (guion, punto, apóstrofo, emoji…) da el error «El nombre solo puede llevar letras, números y espacios.»
5. Comprueba la longitud con `mb_strlen()`: más de 12 caracteres, espacios incluidos, da el error «El nombre puede tener como máximo 12 caracteres.»

Si el producto no tiene la casilla marcada, cualquier valor recibido se ignora.

### 3.4. Carrito, compra, pedido y correos

- El nombre forma parte de la línea del carrito. Dos tritrajes iguales con nombres distintos son dos líneas; el mismo tritraje y nombre suman cantidad en una sola línea.
- Se muestra como «Nombre a estampar: GARCÍA» bajo el producto en:
  - el carrito, la página de finalizar compra y la de pedido recibido;
  - los correos de WooCommerce al cliente y al administrador;
  - «Mi cuenta → Pedidos → Ver pedido»;
  - la edición del pedido en la administración.
- Las líneas sin nombre no muestran nada.
- El cliente no puede cambiar el nombre después de comprar. El administrador sí puede corregirlo en la edición del pedido (los metadatos de la línea son editables en WooCommerce).
- «Pedir de nuevo» desde Mi cuenta conserva el nombre.

### 3.5. Exportación de pedidos a CSV

- En la lista de pedidos de WooCommerce aparece la acción en lote **«Exportar equipación a CSV»**.
- El administrador marca los pedidos que quiere, elige la acción y pulsa «Aplicar». El navegador descarga el archivo. Si no se marca ninguno, WooCommerce no ejecuta la acción.
- Solo pueden exportar los usuarios con la capacidad `edit_shop_orders` (administrador y gestor de la tienda).
- Archivo `equipacion-pedidos-AAAAMMDD-HHMM.csv`, en UTF-8 con BOM y separado por `;`, para que Excel en español lo abra bien.
- Una fila por línea de pedido con producto físico. Las líneas de productos virtuales (cuotas) no se exportan. Las prendas sin nombre se exportan con la columna del nombre vacía (D10).
- Columnas:

  | Columna           | Contenido                                                        |
  | ----------------- | ---------------------------------------------------------------- |
  | Pedido            | Número de pedido                                                 |
  | Fecha             | Fecha del pedido, `dd/mm/aaaa`, hora de Madrid                   |
  | Estado            | Estado del pedido en español («Procesando», «Completado»…)       |
  | Cliente           | Nombre y apellidos de facturación                                |
  | Email             | Email de facturación                                             |
  | Teléfono          | Teléfono de facturación                                          |
  | Producto          | Nombre del producto padre, sin la talla                          |
  | Talla             | Valor de las opciones cuyo nombre empieza por «Talla» («Talla», «Talla Wabiks»…). Si hubiera varias, se unen con « / » |
  | Una por opción    | Una columna por cada opción de variación que no es talla, con su nombre visible como cabecera (hoy solo «Cremallera»). Vacía en las prendas que no tienen esa opción |
  | Cantidad          | Cantidad de la línea                                             |
  | Nombre a estampar | Texto guardado, o vacío                                          |

- Orden: por número de pedido ascendente y, dentro de cada pedido, en el orden de las líneas.
- Las columnas de opciones salen de los productos variables publicados, no de los pedidos marcados, para que el CSV tenga siempre las mismas columnas. Van entre Talla y Cantidad, en el orden en que aparecen en el catálogo. Si un pedido trae una opción que ya no está en el catálogo (producto retirado), su columna se añade al final de las opciones (D12).
- Los valores que empiezan por `=`, `+`, `-` o `@` llevan delante un apóstrofo, para que Excel no los interprete como fórmulas. Excepción: los que solo tienen cifras, espacios, paréntesis y puntos tras el signo, como los teléfonos («+34 600 000 000»), se dejan igual.
- Cada pedido exportado recibe una nota privada «Incluido en la exportación de equipación del dd/mm/aaaa hh:mm.» Así se sabe qué pedidos ya se han enviado al fabricante (D11).

## 4. Modelo de datos

| Dónde                   | Clave                          | Valor                                   |
| ----------------------- | ------------------------------ | --------------------------------------- |
| Meta del producto       | `_sulayr_print_name_enabled`   | `yes` o ausente                         |
| Línea del carrito       | `sulayr_print_name`            | Texto ya normalizado (3.3)              |
| Meta de línea de pedido | `sulayr_print_name`            | Texto ya normalizado. Sin guion bajo inicial, para que WooCommerce lo muestre y permita editarlo. |

La etiqueta visible «Nombre a estampar» se pone con el filtro `woocommerce_order_item_display_meta_key`. No hay tablas nuevas ni cambios en pedidos existentes.

## 5. Arquitectura del código

Clases nuevas en `src/Modules/WooCommerce/`, registradas en `App::load_classes()`:

| Clase                    | Responsabilidad |
| ------------------------ | --------------- |
| `PrintNameField`         | Casilla del producto, campo en la ficha, validación y normalización, datos de la línea del carrito, meta de la línea de pedido, etiqueta visible y «Pedir de nuevo». La normalización y validación van en métodos estáticos puros para poder probarlas sin WordPress. |
| `OrdersCsvExport`        | Acción en lote en las dos pantallas de pedidos, comprobación de capacidad y nonce, generación del CSV y notas en los pedidos. |

Hooks principales:

| Hook | Uso |
| ---- | --- |
| `woocommerce_product_options_general_product_data`, `woocommerce_admin_process_product_object` | Casilla del producto |
| `woocommerce_before_add_to_cart_button` | Campo en la ficha |
| `woocommerce_add_to_cart_validation` | Validación (3.3) |
| `woocommerce_add_cart_item_data` | Guardar el nombre en la línea del carrito |
| `woocommerce_get_item_data` | Mostrar el nombre en carrito y compra |
| `woocommerce_checkout_create_order_line_item` | Copiar el nombre a la línea del pedido |
| `woocommerce_order_item_display_meta_key` | Etiqueta «Nombre a estampar» |
| `woocommerce_order_again_cart_item_data` | Conservar el nombre en «Pedir de nuevo» |
| `bulk_actions-woocommerce_page_wc-orders`, `handle_bulk_actions-woocommerce_page_wc-orders` | Exportación con HPOS |
| `bulk_actions-edit-shop_order`, `handle_bulk_actions-edit-shop_order` | Exportación con la pantalla clásica |

Convenciones del plugin del club: espacios, arrays cortos `[]`, condiciones Yoda, compatible con PHP 7.4 y textos en español con el dominio `SULAYR_TEXTDOMAIN`. No se toca `sulayr-reservas`.

## 6. Fases

| Fase | Contenido |
| ---- | --------- |
| T1   | `PrintNameField`: casilla, campo, validación, carrito, pedido, correos, Mi cuenta y «Pedir de nuevo». |
| T2   | `OrdersCsvExport`: acción en lote, CSV y notas. |
| T3   | Pruebas (sección 7), documentación y despliegue (sección 8). |
| T4   | D12: columnas propias para las opciones que no son talla. |

## 7. Criterios de aceptación

Se prueban en local con los productos de la exportación de PROD importados (sin imágenes) y con scripts `wp eval-file` y peticiones HTTP.

| #     | Criterio |
| ----- | -------- |
| 7.1   | La casilla se guarda y se lee en un producto variable. |
| 7.2   | El campo aparece en un tritraje marcado y no aparece en una prenda sin marcar. |
| 7.3   | «garcía» se guarda como «GARCÍA»; «  de la  torre » como «DE LA TORRE». |
| 7.4   | «NUÑEZ 7» se acepta. «GARCÍA-LÓPEZ», «O'NEIL» y «J.L.» se rechazan con el mensaje de caracteres. |
| 7.5   | 12 caracteres se aceptan; 13 se rechazan con el mensaje de longitud. |
| 7.6   | Sin nombre, el tritraje se añade y la línea no muestra nada. |
| 7.7   | El mismo tritraje con dos nombres da dos líneas; con el mismo nombre, una línea con cantidad 2. |
| 7.8   | El nombre aparece en el carrito, la compra, el pedido recibido, Mi cuenta, la edición del pedido y los correos. |
| 7.9   | El precio de la línea es el mismo con y sin nombre. |
| 7.10  | Un valor enviado a un producto sin la casilla se ignora. |
| 7.11  | La exportación con dos pedidos seleccionados devuelve solo sus líneas físicas, con las columnas y el formato de 3.5. |
| 7.12  | Un usuario sin `edit_shop_orders` no puede exportar; una petición sin nonce válido tampoco. |
| 7.13  | Cada pedido exportado tiene la nota con la fecha. |
| 7.14  | La exportación funciona en la pantalla HPOS y en la clásica. |
| 7.15  | Las pruebas generales de la web siguen pasando: páginas, compra de cuota y área del deportista. |

## 8. Despliegue

1. Desplegar el código del plugin del club.
2. Marcar «Permitir nombre a estampar» en los productos 2670, 2676, 2682 y 2688, a mano o con:
   `wp post meta update <ID> _sulayr_print_name_enabled yes`
3. Comprobar en PROD que el campo aparece en un tritraje y no en otra prenda, sin completar una compra.
4. Actualizar el estado de esta spec, `docs/FASES.md` y `docs/PRIORIDADES.md` con la fecha del despliegue.

## 9. Estado de las fases

| Fase | Estado |
| ---- | ------ |
| T1   | Cerrada (`PrintNameField`). En `main` (`2f9e0503f`) y en PROD el 9 de noviembre de 2026. |
| T2   | Cerrada (`OrdersCsvExport`). En `main` (`2f9e0503f`) y en PROD el 9 de noviembre de 2026. |
| T3   | Cerrada. Pruebas en local, documentación (esta spec, `docs/FASES.md`, `docs/PRIORIDADES.md` y `club-triatlon-sulayr/MANUAL-TIENDA.md`) y despliegue en PROD el 9 de noviembre de 2026. |
| T4   | Implementada y probada en local el 9 de noviembre de 2026 en la rama `tienda-columnas-atributos` (9.3). Sin commit ni despliegue. |

### 9.1. Pruebas en local (9 de noviembre de 2026)

Con los productos de PROD importados, scripts `wp eval-file` y peticiones HTTP con sesión de administrador y de deportista. Ningún correo salió: se bloqueó `wp_mail` y los correos se comprobaron generando su HTML. Los pedidos de prueba se borraron al terminar.

| Criterio | Resultado |
| -------- | --------- |
| 7.1 | Cumplido y probado. La casilla se guarda, se borra al desmarcarla y la variación la hereda del padre. Se ve en la pestaña General de un producto variable. |
| 7.2 | Cumplido y probado. El campo aparece en el tritraje y no en la sudadera. |
| 7.3 a 7.7, 7.9, 7.10 | Cumplidos y probados añadiendo al carrito por HTTP desde la ficha (18 casos, incluidos «FRANÇOIS», «ÀLEX» y un emoji, rechazados). |
| 7.8 | Cumplido y probado: carrito, finalizar compra, línea del pedido, edición del pedido (HPOS), Mi cuenta → ver pedido y correos «Pedido en proceso» (cliente) y «Nuevo pedido» (administrador). «Pedir de nuevo» conserva el nombre. |
| 7.11 | Cumplido y probado. Exportación real desde `admin.php?page=wc-orders` con dos pedidos de tres: BOM, cabecera, 4 filas, la cuota fuera, «M / Con cremallera» y cantidad 2. |
| 7.12 | Cumplido y probado. Sin nonce: 403 «El enlace que has seguido ha caducado». Un deportista no ve la acción y WordPress le corta antes de llegar a ella (la pantalla de pedidos le devuelve 500 con o sin la acción; es el comportamiento previo de WordPress). |
| 7.13 | Cumplido y probado. Nota en los pedidos exportados y no en el otro. |
| 7.14 | Probado en HPOS. En la pantalla clásica solo se comprobó que la acción está registrada y aparece en el desplegable: en local HPOS está activo y no se cambió para la prueba. |
| 7.15 | Cumplido y probado. Las 66 páginas de la prueba general y las 47 comprobaciones funcionales de los dos sitios dan el mismo resultado que antes. |

### 9.2. Comprobación en PROD (9 de noviembre de 2026)

Sin sesión y solo con peticiones de lectura:

- La caja «Nombre a estampar (opcional)» aparece en los 4 tritrajes (2670, 2676, 2682 y 2688) y no en la sudadera ni en el maillot de manga corta: la casilla está marcada donde toca.
- La prueba general del frontal (53 páginas de los sitemaps y rutas clave, y 150 recursos) da el mismo resultado que antes del despliegue: sin errores PHP visibles ni recursos rotos.

No se ha comprobado en PROD, porque requiere sesión de administrador o crear pedidos: el aviso de validación al añadir al carrito, el nombre en un pedido real y la acción «Exportar equipación a CSV» en la lista de pedidos (sección 8, paso 3, y `MANUAL-TIENDA.md`, apartado 8).

### 9.3. T4: columnas de opciones (9 de noviembre de 2026)

Implementada en `OrdersCsvExport` y probada en local en la rama `tienda-columnas-atributos`. Sin commit ni despliegue.

- Cabecera: `Pedido;Fecha;Estado;Cliente;Email;Teléfono;Producto;Talla;Cremallera;Cantidad;"Nombre a estampar"`. La sudadera sale con «M» en Talla y «Con cremallera» en Cremallera; los tritrajes, con Cremallera vacía. Comprobado con la exportación real desde la lista de pedidos.
- «Talla» y «Talla Wabiks» cuentan como tallas; «Cremallera», no.
- Una opción que solo está en un pedido añade su columna al final de las opciones.
- Con el producto borrado después del pedido, la opción sigue en su columna («cremallera» de la línea se lee como «Cremallera»).
- Las 38 comprobaciones de pedidos, correos y CSV y la prueba general de la web dan el mismo resultado que antes, sin errores PHP nuevos.

Al desplegar no hay que marcar nada: basta con el código.

### 9.4. Hallazgos durante la implementación

- El 9 de noviembre de 2026, a las 18:57, se borraron en local desde la administración las 4 cuotas (2425 a 2428) y los calcetines Wabiks (2390). `SubscriptionProducts::ensure_products()` volvió a crear las cuotas (2520 a 2529) y dejó publicadas dos veces «Suplemento Natación» y «Natación para directiva»: dos peticiones simultáneas las crearon a la vez. No afecta a esta spec y no se ha corregido. Los calcetines no existen en local.


## 10. Fuera de alcance

- Cambios en el catálogo, precios, envíos o métodos de pago.
- Cobro de cuotas desde la web: tiene su propia spec, `SPEC-COBROS_DE_CUOTAS_DESDE_LA_WEB.md`.
- Envío automático del CSV al fabricante.
