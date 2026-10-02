# Matriz de aceptación

Revisión de la fase 9 sobre el árbol de trabajo. Los identificadores son los del [SPEC.md](SPEC.md). Una fila solo está «Cumplido y probado» si esa comprobación se ha ejecutado. No hay suite PHPUnit.

La fase 9 queda cerrada. Los casos del apartado 18 tienen una comprobación ejecutada. El correo real de alta no pertenece a ese apartado y queda para una revisión posterior.

## 18.1. Usuarios y permisos

| Referencia | Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|---|
| 18.1 | Un nadador activo accede al panel público | `Front\Shortcode` | `do_shortcode` con un nadador de prueba | Cumplido y probado | El HTML incluyó el mes en curso |
| 18.1 | Ese nadador no accede a `wp-admin` | `Access\AdminAccess` | Petición autenticada a `wp-admin` | Cumplido y probado | Nadador y gestor salen del escritorio hacia el panel |
| 18.1 | Un nadador desactivado no accede ni reserva | `Front\Shortcode`, `BookingService` | Shortcode e intento de reserva | Cumplido y probado | Mensaje de acceso denegado y estado `swimmer_inactive` |
| 18.1 | Un gestor usa el panel y no `wp-admin` | `Front\Shortcode`, `Access\AdminAccess` | Panel y petición autenticada a `wp-admin` | Cumplido y probado | El gestor usa el panel público y la entrada al escritorio lo redirige |
| 18.1 | Un administrador usa el panel y `wp-admin` | `Front\Shortcode`, `Access\AdminAccess` | Panel y entrada real a `wp-admin` | Cumplido y probado | El administrador entra en el escritorio |
| 18.1 | Roles distintos en los dos sitios | `SyncService` | Alta sincronizada | Cumplido y probado | `athlete` en el club y `nadador` en reservas, misma cuenta |

## 18.2. Sincronización

| Referencia | Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|---|
| 18.2 | Deportista elegible creado con el mismo `user_id`, rol `nadador` y activo | `SyncService` | Alta con natación `1` y estado `active` | Cumplido y probado | No cambió la contraseña ni el teléfono global |
| 18.2 | Un usuario ya existente se vincula sin duplicar cuenta ni perder reservas | `SyncService` | Ficha manual previa y una reserva | Cumplido y probado | `linked_to_club` pasó a 1 y la reserva siguió |
| 18.2 | Un cambio personal del club actualiza reservas | `SyncService` | Cambio de teléfono | Cumplido y probado | |
| 18.2 | Un campo vacío del club sobrescribe la copia local | `SyncService` | DNI vacío | Cumplido y probado | |
| 18.2 | Si deja de cumplir, se desactiva y se borran las reservas futuras | `SyncService` | Natación `0` | Cumplido y probado | La reserva pasada conservó `user_id` |
| 18.2 | Si vuelve a cumplir, se reactiva sin recuperar las eliminadas | `SyncService` | Natación otra vez `1` | Cumplido y probado | |
| 18.2 | Una desactivación manual no modifica el club | `SwimmerService`, `SyncService` | Ficha puesta a `active = 0` y sincronización posterior | Cumplido y probado | El teléfono global siguió igual. La sincronización volvió a activar |
| 18.2 | La siguiente sincronización prevalece sobre el estado manual | `SyncService` | La misma prueba | Cumplido y probado | |
| 18.2 | Salir del club desactiva en reservas | `ClubSync` | `remove_user_from_blog` del blog 1 | Cumplido y probado | El gancho obliga la desactivación porque WordPress aún no ha quitado el rol |
| 18.2 | El botón manual usa la misma lógica que guardar el perfil | `ClubSync`, `SyncService` | `sync()` con origen manual y `profile_update` | Parcialmente cumplido | La lógica es la misma. No se envió el formulario HTTP del botón |
| 18.2 | Repetir no duplica usuarios ni reservas | `SyncService` | Segunda sincronización | Cumplido y probado | Acción «sin cambios» y una sola ficha |
| 18.2 | Un error se registra y se muestra, sin reintento automático | `SyncService`, `SyncLog` | Usuario inexistente | Cumplido y probado | Quedó una fila `failed`. No hubo segundo intento. El aviso en pantalla del botón no se vio en el navegador |
| 18.2 | El contexto del sitio se restaura aunque falle | `BlogContext` | Operaciones de sincronización y usuario inexistente | Cumplido y probado | El blog activo volvió al de partida. Un fallo del registro no deshizo el alta |

## 18.3. Sesiones

| Referencia | Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|---|
| 18.3 | La generación crea solo el horario establecido | `Schedule`, `SessionGenerator` | `slotsForMonth` de octubre y generación real de noviembre | Cumplido y probado | Octubre calculado: 39 sesiones, lunes 15:00–16:30. Noviembre persistido: 38 sesiones. Las horas de esas filas no se listaron una a una antes de borrarlas |
| 18.3 | No hay sesiones en viernes ni domingo | `Schedule`, `SessionGenerator` | Recuento del horario y de noviembre | Cumplido y probado | |
| 18.3 | La ventana cambia en las fechas definidas | `GenerationWindow` | 25, 26 y 27 de septiembre de 2026 | Cumplido y probado | |
| 18.3 | No se genera dos veces el mismo mes, tampoco a la vez | `SessionGenerator` | Dos procesos sobre noviembre de 2026 | Cumplido y probado | Resultados `created` y `unchanged`. Noviembre se borró después |
| 18.3 | Una sesión deshabilitada conserva reservas y no admite reservas ordinarias | `SessionEditor`, `BookingService` | Sesión deshabilitada en el panel | Cumplido y probado | Conserva las reservas existentes y no admite una reserva ordinaria nueva |
| 18.3 | Bajar la capacidad no borra reservas y bloquea altas | `SessionEditor`, `BookingService` | Fase 7 | Cumplido y probado | No se ha repetido en esta revisión. El código de esa fase no se ha cambiado |

## 18.4. Reservas y cancelaciones

| Referencia | Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|---|
| 18.4 | No se reserva una sesión completa, deshabilitada o ya iniciada | `BookingService` | Tres sesiones de prueba | Cumplido y probado | |
| 18.4 | No se duplica la reserva del mismo usuario y sesión | `BookingService` | Segunda reserva | Cumplido y probado | |
| 18.4 | Máximo de 5 reservas por semana natural | `BookingService` | Seis sesiones de la misma semana | Cumplido y probado | |
| 18.4 | Reservar para otro cuenta en la semana de ese usuario | `BookingService` | Reserva para otra persona | Cumplido y probado | Cuenta en el límite semanal de la persona reservada |
| 18.4 | La reserva retroactiva cuenta en la semana de la sesión | `BookingService` | Reserva retroactiva | Cumplido y probado | Cuenta en la semana de la sesión |
| 18.4 | El nadador cancela con 60 minutos o más | `BookingService` | Sesión de 2099 | Cumplido y probado | |
| 18.4 | El nadador no cancela por debajo de 60 minutos | `BookingService` | Sesión a 20 minutos | Cumplido y probado | |
| 18.4 | El gestor cancela sin ese plazo | `BookingService` | La misma reserva | Cumplido y probado | |
| 18.4 | Cancelar elimina la reserva y libera la plaza | `BookingService` | Cancelación a tiempo | Cumplido y probado | La fila desaparece. El efecto sobre el límite se vuelve a comprobar al contar reservas que siguen existiendo |

## 18.5. Historial y eliminación

| Referencia | Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|---|
| 18.5 | Las reservas pasadas que siguen registradas aparecen en el historial | `PanelRenderer` | Historial visto con sesión iniciada | Cumplido y probado | |
| 18.5 | Las canceladas no aparecen | `BookingService` | La cancelación borra la fila | Cumplido y probado | Sin fila no hay historial |
| 18.5 | Las retroactivas muestran que las añadió un gestor | `BookingService`, `PanelRenderer` | Detalle de sesión en la fase 7 | Cumplido y probado | El detalle mostró «Añadida por un gestor». El historial se ha vuelto a consultar después |
| 18.5 | Desactivar conserva las pasadas y borra las futuras | `SyncService`, `SwimmerService` | Desactivación por sincronización | Cumplido y probado | La desactivación desde el panel se probó en la fase 7 |
| 18.5 | Retirar del sitio conserva el historial permitido | `SwimmerService` | Retirada desde el panel | Cumplido y probado | |
| 18.5 | Retirar de reservas no borra la cuenta global ni la pertenencia al club | `SwimmerService` | Retirada desde el panel | Cumplido y probado | |

## Fuera de esta matriz

Estos puntos no son criterios del apartado 18:

| Asunto | Resultado |
|---|---|
| Correo real de alta | Pendiente de una revisión posterior. No es un caso del apartado 18. La llamada a `wp_send_new_user_notifications()` sí se comprobó, interceptando el envío |
| PHP 7.4 | Implementado con sintaxis compatible. Ejecutado solo en PHP 8.4.17. No es un caso del apartado 18 |
| Calendario autenticado en el navegador | Cumplido y probado |
| PHPUnit | No lo exige el SPEC. Sigue pendiente una suite del plugin |
| Aviso de WooCommerce al retirar un usuario del blog 2 | Parcialmente cumplido. La retirada sigue. El error de `wp_2_wc_customer_lookup` se volvió a ver al borrar usuarios de prueba |
