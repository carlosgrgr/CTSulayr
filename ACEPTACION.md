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

## Fase 10

La fila 18.4 «Máximo de 5 reservas por semana natural» describe la comprobación de la fase 9. Desde esta fase el máximo es el de la ficha, con defecto 3. Esa fila no se reabre: el comportamiento nuevo está aquí.

| Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|
| Límite por ficha, defecto 3, solo 1–5 | `WeeklyLimit`, `SwimmerService`, `BookingService` | Alta y guardado con 0, 6, «2a», 1, 2, 3, 4 y 5 | Cumplido y probado | Las fichas ya existentes quedaron en 3 al añadir la columna |
| Tres reservas de la misma semana bloquean la cuarta, también si la hace el gestor | `BookingService` | Sesiones del 2 al 6 de junio de 2036 | Cumplido y probado | Un primer intento con el 1 de junio falló la afirmación porque ese día es domingo y las fechas caían en dos semanas |
| Subir el límite permite otra reserva; bajarlo no borra las hechas y bloquea la siguiente | `BookingService`, `SwimmerService` | Límite 4 y después 1, con 4 reservas | Cumplido y probado | |
| Cancelar sigue borrando la fila | `BookingService` | Cancelación de una de esas reservas | Cumplido y probado | |
| Cambiar capacidad y habilitación de la sesión | `SessionEditor` | Las mismas sesiones de prueba | Cumplido y probado | |
| Nombre y teléfono manuales solo en la ficha local | `SwimmerService` | Actualización y lectura de metas globales | Cumplido y probado | `first_name` y `sulayr_user_phone` no cambiaron. El DNI tampoco |
| Un vinculado no edita nombre, apellidos, teléfono ni DNI | `SwimmerService`, panel | Servicio y, en el navegador, perfil y ficha de gestor | Cumplido y probado | Seguían el correo, la foto y, para el propio usuario, la contraseña |
| El nadador no cambia el límite ni la ficha de otro | `SwimmerService`, `PanelActions` | Llamada sin capacidad y con otro `user_id` | Cumplido y probado | |
| Contraseña solo del usuario actual | `SwimmerService` | Contraseña incorrecta, el hash como si fuera la clave, cambio real y llamada como gestor | Cumplido y probado | En CLI, `wp_set_auth_cookie` avisó de cabeceras ya enviadas |
| Correo nuevo solo tras confirmar, 24 h, un solo uso | `EmailChangeService` | Solicitud, confirmación, segundo uso y caducidad forzada | Cumplido y probado | El mensaje se interceptó. No se vio un buzón real. El enlace no pasa por `wp-admin` |
| La confirmación actualiza la cuenta y la ficha | `EmailChangeService` | Lectura de `user_email` y de la columna local | Cumplido y probado | |
| La sincronización restaura el nombre del club y conserva el límite | `SyncService` | Segunda sincronización con límite 5 y un nombre local distinto | Cumplido y probado | |
| CSV: validar, no tocar cuentas existentes, crear solo las filas válidas | `CsvImporter`, `SwimmerService::createImported` | 11 filas, lugares club/reservas/ambos, tres altas | Cumplido y probado | El aviso de contraseña aumentó al confirmar, no en la validación. No se recorrió el formulario de previsualizar y confirmar en el navegador |
| Filtros de estado y búsqueda | `SwimmerRepository`, panel | Servicio y, en el navegador, activos + «Navegador» | Cumplido y probado | La lista quedó en una sola ficha |
| Exportación de fichas y de reservas | `CsvExporter` | CSV con BOM, inactivo, Nadador y Gestor | Cumplido y probado | La reserva cancelada no salió. No se descargó el archivo desde el botón del navegador |
| Fotografía: tamaño, tipo, sustitución y borrado | `PhotoService` | POST HTTP real con JPEG, PNG, archivo de más de 5 MB y WebP | Cumplido y probado | El JPEG de 2000×800 quedó con el lado largo en 1200 o menos. No se probó un archivo que no fuera imagen con extensión `.jpg`. La subida con rol nadador, solo la propia foto, no se hizo por HTTP |
| Perfil del club en Bento, sin cambiar el formulario | `ct-sulayr-theme/profile-bento.css` | Navegador con el tema activo un momento, a 1280 px, 954 px y 390 px | Cumplido y probado | El sitio del club usa Twenty Twenty-Five. Se restauró al terminar. Información y edición conservan campos y pestañas. No existe perfil público de otro deportista |
| Panel de reservas en 390 px | `panel.css` | Lista filtrada | Cumplido y probado | Cabecera y navegación en columna. No se recorrió una reserva nueva en el navegador |

La regresión de reservar, cancelar, editar una sesión y sincronizar se ejecutó con los usuarios de prueba de esta fase y quedó en el script de repetición, 16 comprobaciones y 0 fallos. Esos usuarios y las sesiones de junio de 2036 se borraron. El usuario de navegador `fase10ui` también se borra al cerrar la fase.

## Fase 11

No reabre las filas del apartado 18. El acceso entre sitios y el calendario móvil están aquí.

| Requisito | Componente | Prueba | Resultado | Observaciones |
|---|---|---|---|---|
| Identidad por la cookie de red, sin contraseña ni `user_id` | `CrossSite`, cookie `wordpress_logged_in_*` | Cookie válida, caducada, con el identificador cambiado y basura | Cumplido y probado | `COOKIE_DOMAIN` es `.clubtriatlonsulayr.local.com`. No hay token propio |
| Elegible entra; sin natación, inactivo o anónimo no | `SyncService::isEligibleAthlete()` | Usuarios temporales en el club | Cumplido y probado | Misma condición que la sincronización. No crea ficha al pulsar el enlace |
| Cerrar sesión con nonce y salida de los dos sitios | `wp_logout_url()` | HTML del nadador, gestor y administrador; anónimo sin el enlace | Cumplido y probado | La cookie es de red. El cierre es de toda la red. Quien llegó del club vuelve a su portada |
| Perfil del club solo del usuario actual | `CrossSite::clubProfileUrlForCurrentUser()` | Atleta con ficha activa, nadador sin rol `athlete`, ficha inactiva y `user_id` ajeno en la petición | Cumplido y probado | La URL no lleva `user_id` |
| Calendario de un día por debajo de 1024 px | `PanelRenderer`, `panel.js`, `panel.css` | 390, 414 y 768 px; anterior, siguiente, swipe, viernes, domingo y cambio de semana | Cumplido y probado | Desde 1024 px sigue la cuadrícula. Hoy es `Europe/Madrid` |
| Reservar, cancelar y límite semanal | `BookingService` | Una reserva futura creada y cancelada; la cuarta de la misma semana rechazada | Cumplido y probado | Los formularios no han cambiado de nonce ni de campos |
