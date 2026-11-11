# Pruebas pendientes — parte B (altas)

Pruebas manuales de la parte B de [SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md](../SPEC-ALTAS-Y-CICLO-DE-VIDA-SOCIO.md) (sección 18). Lo que se probó por HTTP el 11 de noviembre de 2026 está en 18.4. Aquí queda lo que falta: el navegador (formulario, JavaScript, aspecto), los emails reales y PHP 7.4 en PROD.

Marca cada casilla al hacerla y apunta la fecha. Si algo falla, anótalo debajo de la prueba.

## Antes de empezar

- **Los emails salen de verdad**, también en local (WP Mail SMTP). Usa siempre un email propio o uno `@example.com`.
- Estado de la base local preparado el 11 de noviembre de 2026:
  - Catálogo recreado con sus IDs (2655 cuota base, 2656 entrenamiento genérico, 2657 natación).
  - Socios → Ajustes rellenos con textos «[PRUEBA]». El email del club y el del entrenador son `@example.com`.
  - Páginas «Hazte socio» (`/hazte-socio/`, con `[sulayr_solicitud_alta]`) y «[PRUEBA] Política de privacidad».
  - En local salen en el menú porque no hay menú asignado y el tema lista todas las páginas.
- No hay usuarios de prueba. Crea los que necesites y bórralos al terminar.

## En local

### 1. Formulario público

En http://clubtriatlonsulayr.local.com/hazte-socio/, sin sesión, en ordenador y en móvil (o modo responsive).

- [ ] En móvil los campos van en una columna y no hay scroll horizontal.
- [ ] La cuota base está marcada y no se puede desmarcar.
- [ ] Al marcar «Entrenamiento genérico» aparece el desplegable Sprint/Olímpico y es obligatorio. Al desmarcarlo, desaparece.
- [ ] Con una fecha de nacimiento de menor (p. ej. 2012) aparece el bloque del tutor. Con un adulto, desaparece.
- [ ] El enlace «política de privacidad» del consentimiento abre la página de prueba.
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

- [ ] Perfil de deportista → Editar: estado, DNI y fecha de nacimiento bloqueados; el teléfono se cambia y se guarda.
- [ ] «Uso de mi imagen»: al marcar o desmarcar y guardar sale «Preferencia guardada» con la fecha. En la ficha de Socios aparece la fila nueva de consentimiento.
- [ ] Guardar el perfil no cambia sus servicios en la ficha de Socios.

### 6. Puente transitorio

Como administrador.

- [ ] En Usuarios → editar un socio, quita «Suplemento natación» y guarda. En su ficha de Socios solo se cierra la natación.

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

### 9. Al día siguiente

- [ ] Herramientas → Salud del sitio sin avisos de WP-Cron.
- [ ] Si se aprobó un alta con fecha futura, ese día pasa a «Activo».
