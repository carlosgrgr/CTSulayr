# Pruebas pendientes — partes B (altas), C (cambios, congelaciones y bajas) y D (recibos y listados)

Pruebas manuales de las partes B, C y D de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](../SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) (secciones 18, 19 y 20). Lo que se probó por HTTP el 11 de noviembre de 2026 está en 18.4, 19.4 y 20.5. Aquí queda lo que falta: el navegador (formulario, JavaScript, aspecto), los emails reales y PHP 7.4 en PROD.

Marca cada casilla al hacerla y apunta la fecha. Si algo falla, anótalo debajo de la prueba.

## Antes de empezar

- **Los emails salen de verdad**, también en local (WP Mail SMTP). Usa siempre un email propio o uno `@example.com`.
- Estado de la base local (copia de PROD importada y preparada el 11 de noviembre de 2026):
  - Catálogo y Socios → Ajustes de PROD (IDs del catálogo 2828 cuota base, 2829 entrenamiento genérico, 2830 natación).
  - Cambiado solo en local: el email del club (`avisos-club-prueba@example.com`) y el del entrenador (`entrenador-prueba@example.com`), para que las pruebas no escriban al Gmail del club.
  - Ajustes nuevos de la parte B con textos «[PRUEBA]»: texto encima del formulario, mensaje tras enviar y límite de 3 por hora.
  - Página «Hazte socio» (`/hazte-socio/`, con `[sulayr_solicitud_alta]`). El consentimiento de privacidad es el texto de PROD, sin enlace; la política de privacidad de la web está en `/politica-de-privacidad/`.
- **La base tiene los emails reales de los socios.** Aprobar, rechazar o dar de baja a un socio real le enviaría el email. Prueba solo con cuentas y emails propios o `@example.com`.
- No hay usuarios de prueba. Crea los que necesites y bórralos al terminar.

## En local

### 1. Formulario público

En http://clubtriatlonsulayr.local.com/hazte-socio/, sin sesión, en ordenador y en móvil (o modo responsive).

- [ ] En móvil los campos van en una columna y no hay scroll horizontal.
- [ ] La cuota base está marcada y no se puede desmarcar.
- [ ] Al marcar «Entrenamiento genérico» aparece el desplegable Sprint/Olímpico y es obligatorio. Al desmarcarlo, desaparece.
- [ ] Con una fecha de nacimiento de menor (p. ej. 2012) aparece el bloque del tutor. Con un adulto, desaparece.
- [ ] Los textos de los consentimientos son los de Socios → Ajustes (en PROD aún no llevan enlace a la política de privacidad).
- [ ] Un DNI con letra mala muestra el error junto al campo y conserva lo escrito.
- [ ] Al enviar uno válido aparece el mensaje «[PRUEBA] ¡Gracias!…».
- [ ] Recargar la página tras enviar no crea una segunda solicitud.
- [ ] Llega el aviso al club. Para verlo, pon temporalmente tu email en Socios → Ajustes → Datos del club → «Email del club para avisos».

### 2. Aprobar desde wp-admin

En Socios → Solicitudes, como administrador.

- [ ] El menú muestra el globo con las pendientes.
- [ ] La solicitud muestra los datos, las coincidencias (o «Ninguna cuenta…»), los consentimientos y la matrícula propuesta.
- [ ] Cambia un dato, pulsa «Guardar cambios» y comprueba que se guarda.
- [ ] Al aprobar, lleva a la ficha del socio con pasos, consentimientos y registro.
- [ ] Llega un solo email de bienvenida, con buen aspecto, el enlace de WhatsApp y el texto de sus servicios.
- [ ] El enlace «Crear mi contraseña» abre el formulario de nueva contraseña. Con ella se entra en Mi cuenta.
- [ ] Si tiene natación, el enlace a reservas sale en su perfil y puede reservar.

### 3. Con un gestor de socios

- [ ] Marca «Gestor de socios» a un deportista de prueba y entra con él: ve el menú Socios y puede aprobar.
- [ ] Si el socio aprobado tiene natación, aparece en reservas sin que intervenga un administrador.

### 4. Otros casos en wp-admin

- [ ] **Reingreso:** en Nueva alta, con el email de un socio de baja, propone «Vincular… reingreso», 10 € y conserva su número.
- [ ] **Socio activo:** con el email de uno activo sale el aviso rojo y no aparece «Aprobar».
- [ ] **Rechazar** con motivo: el solicitante recibe el email con ese motivo.
- [ ] **Nueva alta → «Crear y aprobar»** con un email nuevo: aprueba en un paso.
- [ ] **Fecha de alta futura:** queda «Pendiente». Al día siguiente de la fecha, la ficha está «Activo». La activación con fecha simulada ya se probó el 11 de noviembre de 2026.
- [ ] **«Marcar hecho»** en un paso de la ficha guarda fecha y autor.
- [ ] **Socios → Ajustes:** la pestaña «Formulario de alta» se ve y guarda.

### 5. Mi cuenta

Como el socio recién creado.

- [ ] Perfil de deportista → Editar: DNI y fecha de nacimiento bloqueados; tarifa, natación y estado ya no salen; el teléfono se cambia y se guarda. En Información, «Servicios» lista los servicios actuales.
- [ ] «Uso de mi imagen»: al marcar o desmarcar y guardar sale «Preferencia guardada» con la fecha. En la ficha de Socios aparece la fila nueva de consentimiento.
- [ ] Guardar el perfil no cambia sus servicios en la ficha de Socios.

### 6. Parte C en local

Con un socio de prueba propio (créalo con «Nueva alta» y un email `@example.com`), no con socios reales: la base tiene sus emails.

- [ ] **Mi cuenta → Mis cuotas** se ve bien en ordenador y en móvil: estado, tipo, servicios con precio y los dos formularios.
- [ ] En «Cambiar mis servicios», al marcar Entrenamiento genérico sale la opción; los servicios incompatibles se bloquean.
- [ ] Pide un cambio: sale «Tu solicitud pendiente» con la fecha calculada y «Cancelar solicitud». Cancélala y pide otra.
- [ ] **Socios → Solicitudes** muestra la petición en «Cambios y bajas pedidos por socios»; el enlace lleva a la ficha.
- [ ] En la ficha, «Cambios, congelación y baja»: apruébala desde un mes. Llega al socio el email «Resultado de solicitud» con buen aspecto.
- [ ] Rechaza otra con motivo: llega el email con el motivo.
- [ ] **Cambiar servicios o tipo** desde la ficha, «Desde el 1 de» un mes: sale en la tabla de programados. Pulsa «Anular».
- [ ] **Al momento:** sin marcar la confirmación da error; marcándola y con motivo se aplica hoy.
- [ ] **Congelar** con «Congelación inmediata»: queda programada para el 1 del mes siguiente y el socio sigue con reservas. «Adelantar o alargar la vuelta» cambia la fecha.
- [ ] **Dar de baja** desde un mes: llega «Confirmación de baja». El socio sigue activo hasta ese día.
- [ ] **Socios → «Ejecutar ahora»**: muestra el resumen; pulsado dos veces no aplica nada la segunda vez.
- [ ] **Usuarios → editar** al socio: no salen tarifa, modalidad, natación, directiva ni estado; sale el recuadro «Socio: …» con el botón a la ficha. El listado de Usuarios muestra «Servicios», «Natación» y «Tipo de socio».
- [ ] **Sulayr Info** (por tarifa, por estado y nadadores) muestra a los socios en su grupo.
- [ ] Un socio de baja entra en Mi cuenta: no ve «Perfil de deportista» ni «Documentos»; sí «Mis cuotas», monedero y pedidos.

### 6 bis. Parte D en local

Con socios de prueba propios (`@example.com`). En Socios → Ajustes → Recibos pon un «Primer mes con recibos» para poder generar. Al generar un mes se crean recibos de todos los socios activos de la copia: no actives el aviso por email del recibo mensual con socios reales.

- [ ] **Socios → Recibos**: generar el mes; la tabla y los totales se ven bien. Abrir un recibo: líneas, «Ver recibo (PDF)», línea manual, apuntar pago (Bizum y monedero) y borrar un pago con motivo.
- [ ] El PDF se ve bien en el ordenador y en el móvil (logo, datos del club y del socio, líneas, total y estado).
- [ ] **Exportar a Excel (CSV)** se abre en Excel con columnas separadas, tildes y euros con coma.
- [ ] **Ficha del socio**: bloques «Recibos», «Subvenciones», «Retribución mensual de la junta» y «Welcome pack». Crear y terminar una subvención; el recibo del mes sale «desfasado» y se regenera.
- [ ] **Mi cuenta → Mis cuotas** con el socio de prueba, en ordenador y móvil: «Tus recibos» se despliega, se ven líneas y pagos, y «Ver recibo» abre el PDF. Con la URL de un recibo de otro socio sale «No puedes ver este recibo».
- [ ] **Tareas pendientes**: filtrar por paso y por alta/cambio/baja y «Marcar hecho».
- [ ] **Entrenador**: «Copiar texto» copia al portapapeles y se pega bien en WhatsApp. «Enviar por email» (con tu email en Ajustes) llega con buen aspecto.
- [ ] **Welcome pack**: registrar una entrega desde la ficha; el socio desaparece de «sin ninguna entrega».
- [ ] **Identidad Sulayr** en Recibos, Tareas pendientes, Entrenador, Welcome pack y los bloques de la ficha: título en Archivo con la franja tricolor, tablas con cabecera Noche, cifras en mono, acción principal en Sol. Nada de texto claro sobre fondo claro. Ver también a 390 px de ancho.
- [ ] **Mis cuotas → Tus recibos** con la marca: mes en Archivo, importes en mono, estado en menta (pagado), cielo (parcial) o sol (impagado).
- [ ] **Identidad en las partes B y C**: Socios, la ficha (con «Cambios, congelación y baja» como tarjetas desplegables), Solicitudes, Nueva alta, el formulario `/hazte-socio/` (grupos, errores en Noche con filete Sol, «Enviar solicitud» en Sol) y Mis cuotas, en ordenador y a 390 px.
- [ ] **Emails con la marca** (con tu email en una solicitud de prueba): bienvenida con el escudo visible sin «Mostrar imágenes», titular, botón «Crear mi contraseña» en Sol y firma con filete menta. Revisa también rechazo, resultado de solicitud y confirmación de baja.

## En PROD, tras subir con FileZilla

El proceso completo de B, C y D juntas está en [despliegue-prod.md](despliegue-prod.md). Estas casillas son las comprobaciones que hay que hacer después.

### 7. Puesta en marcha

- [ ] wp-admin carga y salen Socios → Solicitudes y Nueva alta. Esto confirma que funciona con el PHP de PROD.
- [ ] Socios → Ajustes con los textos reales: WhatsApp, email del club, consentimientos con su versión y emails.
- [ ] Página con `[sulayr_solicitud_alta]` creada y enlazada.

### 8. Prueba completa con un email propio

- [ ] Envía una solicitud: llega el aviso al Gmail del club.
- [ ] Envía otra desde otra conexión (p. ej. el móvil con datos). En Socios → Solicitudes, las IP son distintas. Si son iguales, el límite de 3 por hora es común para todos: súbelo en Ajustes.
- [ ] Aprueba y revisa la bienvenida en el correo, también en el móvil.
- [ ] Después, da de baja al socio de prueba o rechaza la solicitud.

### 9. Parte C en PROD (spec 19.5)

- [ ] Tras subir y borrar los archivos, «Mis cuotas» abre en Mi cuenta (si da 404, guardar Ajustes → Enlaces permanentes).
- [ ] La ficha de Diego Lardón muestra la baja programada para el 1 de enero de 2027.
- [ ] «Ejecutar ahora» en Socios no aplica nada inesperado.
- [ ] El 1 de enero de 2027, Diego Lardón está de baja.

### 9 bis. Parte D en PROD (spec 20.6)

- [ ] Tras subir, salen Socios → Recibos, Tareas pendientes, Entrenador y Welcome pack. Herramientas → Salud del sitio no avisa de que falten DOM, mbstring o GD.
- [ ] Ajustes → Recibos revisados y retribución de la junta puesta en cada ficha. «Primer mes con recibos» decidido (ver Diego Lardón en 20.7).
- [ ] Generar el primer mes y revisar varios recibos y su PDF, en especial uno de la junta y uno con natación de la junta.
- [ ] El día 1 siguiente, sin pulsar nada: los recibos del mes se generaron solos y la retribución de la junta está en el monedero una sola vez.
- [ ] El día de penalización, «Aplicar penalización a impagados» solo añade la línea a los pendientes.
- [ ] Con tu email en «Emails del entrenador», «Enviar por email» del listado llega con la banda Noche, el escudo y la franja tricolor; revísalo en Gmail y en el móvil. Si activas un momento el aviso de recibo mensual con un socio de prueba, lo mismo con ese email.

### 10. Al día siguiente

- [ ] Herramientas → Salud del sitio sin avisos de WP-Cron.
- [ ] Si se aprobó un alta con fecha futura, ese día pasa a «Activo».
