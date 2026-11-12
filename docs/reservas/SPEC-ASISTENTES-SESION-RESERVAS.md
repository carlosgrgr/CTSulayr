# Nadadores apuntados en el detalle de la sesión

**Estado:** Definida el 12 de noviembre de 2026 con el club. Implementada en la rama `feature/asistentes-sesion`. Pendiente de entrar en `main` y de desplegar en PROD.

**Plugin:** `sulayr-reservas`.

**Modifica:** [SPEC-RESERVAS.md](SPEC-RESERVAS.md), sección 3.1, solo en este punto. El nadador sigue sin poder consultar ni modificar los demás datos de otros nadadores.

## 1. Objetivo

Un nadador que abre el detalle de una sesión ve quién ha reservado plaza en ella. El gestor sigue viendo su listado con los datos de contacto y, además, la foto de cada nadador.

## 2. Decisiones

| # | Decisión |
|---|---|
| A1 | El nadador ve el listado en el detalle de cualquier sesión que pueda abrir: las del mes en curso, pasadas o futuras, tenga reserva en ellas o no. |
| A2 | De cada nadador apuntado solo se muestran nombre, apellidos y fotografía de reservas. Nunca correo, teléfono, DNI, límite semanal ni la marca «Añadida por un gestor». |
| A3 | Sin fotografía se muestra la inicial en el círculo, como en el perfil. También para quien ya no tiene ficha en reservas pero conserva reservas antiguas. |
| A4 | El listado se ordena por nombre y después por apellidos. El gestor usa el mismo orden. |
| A5 | Título «Nadadores apuntados (N)». Sin reservas: «Nadie ha reservado esta sesión todavía». |
| A6 | Para el nadador, fichas en rejilla: una columna en móvil, dos desde 640 px y tres desde 1024 px. Avatar pequeño y nombre. Sin enlaces ni ampliación de la foto. |
| A7 | La ficha propia no lleva la palabra «Tú». Ocupa toda la fila, con fondo Nieve y filete Menta a la izquierda, y el botón «Cancelar mi reserva» con la regla de siempre (hasta 60 minutos antes). El botón de arriba del detalle se mantiene. |
| A8 | Para el gestor, la tabla de marca del panel sin ancho mínimo: NADADOR (avatar y nombre), EMAIL, ORIGEN («Añadida por un gestor» o vacío) y ACCIÓN («Cancelar reserva»). Su propia fila, si está, va destacada igual. Sin enlace nuevo a la ficha. |
| A9 | Nadie puede ocultarse del listado. El club debe avisar a los nadadores de que su nombre y su foto de reservas los ven los demás nadadores en el detalle de las sesiones. Queda ligado a la fila 5 de [PRIORIDADES.md](../PRIORIDADES.md) (RGPD y LOPD). |

## 3. Permisos

Capacidad nueva `sulayr_view_session_attendees` para `nadador`, `gestor` y el administrador del blog 2. Se comprueba en el servidor, en la consulta que lee el listado. Esa consulta devuelve solo usuario, nombre, apellidos y foto.

`sulayr_view_all_bookings` sigue siendo la única que da acceso a los datos de contacto de las reservas.

## 4. Criterios de aceptación

| # | Criterio |
|---|---|
| C1 | Un nadador ve en el detalle el título con el número y a todos los apuntados, ordenados por nombre. |
| C2 | La lectura del nadador no trae correo, teléfono, DNI ni la marca manual. |
| C3 | Un usuario sin la capacidad no recibe ningún nombre. |
| C4 | La ficha propia sale destacada y con «Cancelar mi reserva» solo dentro del plazo. |
| C5 | El gestor ve la tabla con avatar, correo, origen y cancelación, ordenada por nombre. |
| C6 | Sin reservas sale el texto vacío. |
| C7 | Los roles existentes reciben la capacidad sin reactivar el plugin. |
