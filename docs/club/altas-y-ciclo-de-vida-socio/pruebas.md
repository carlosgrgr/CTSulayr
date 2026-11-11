# Pruebas pendientes — partes B (altas) y C (cambios, congelaciones y bajas)

Pruebas manuales de las partes B y C de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](../SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) (secciones 18 y 19). Lo que se probó por HTTP el 11 de noviembre de 2026 está en 18.4 y 19.4. Aquí queda lo que falta: el navegador (formulario, JavaScript, aspecto), los emails reales y PHP 7.4 en PROD.

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

## En PROD, tras subir con FileZilla (spec 18.5)

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

### 10. Al día siguiente

- [ ] Herramientas → Salud del sitio sin avisos de WP-Cron.
- [ ] Si se aprobó un alta con fecha futura, ese día pasa a «Activo».
