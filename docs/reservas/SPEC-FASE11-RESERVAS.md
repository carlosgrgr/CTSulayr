# SPEC — Fase 11

## Acceso entre Club y Reservas + rediseño mobile del calendario

**Proyecto:** Club Triatlón Sulayr / sulayr-reservas  
**Fase:** 11  
**Estado:** Implementada y en `main`  
**Fecha:** octubre 2026

---

# 1. Contexto

El proyecto dispone actualmente de dos sites dentro del mismo WordPress Multisite:

- **Site club:** `clubtriatlonsulayr.com`
- **Site reservas:** site de reservas del club

El site de reservas dispone de un panel frontend para nadadores, gestores y administradores.

Actualmente se quiere mejorar la integración entre ambos sites y, especialmente, la experiencia de uso del calendario de reservas en dispositivos móviles.

Esta fase incorpora:

1. Logout desde el site de reservas.
2. Enlace directo a reservas desde el perfil del deportista del site del club.
3. Acceso al site de reservas sin volver a introducir la contraseña cuando el usuario ya está autenticado en el site del club.
4. Enlace desde reservas al perfil del deportista en el site del club cuando el usuario también es `athlete`.
5. Nuevo diseño específico del calendario de reservas para dispositivos móviles.

---

# 2. Objetivos

Los objetivos de esta fase son:

- Facilitar el acceso al sistema de reservas desde el perfil del deportista.
- Facilitar el regreso desde reservas al perfil del deportista en el site del club.
- Evitar pedir nuevamente credenciales cuando el usuario ya está autenticado en el site del club.
- Permitir cerrar sesión desde el site de reservas.
- Mejorar sustancialmente la experiencia del calendario en móvil.
- Mantener intacta la funcionalidad actual de reservas, cancelaciones, gestión de sesiones y permisos.
- Mantener el calendario actual de escritorio salvo que alguna adaptación responsive sea estrictamente necesaria.

---

# 3. Alcance

## 3.1. Incluido

### Site de reservas

- Botón/enlace de cerrar sesión.
- Integración de acceso autenticado procedente del site del club.
- Enlace al perfil del deportista en el site del club para usuarios que también sean `athlete`.
- Nuevo diseño mobile del calendario.
- Navegación entre días.
- Navegación horizontal mediante swipe entre los días de la semana.
- Adaptación responsive del calendario.

### Site del club

- Añadir un enlace/botón desde el perfil del deportista hacia el site de reservas.
- Integración necesaria para permitir el acceso autenticado al site de reservas.

---

## 3.2. Fuera de alcance

No modificar en esta fase:

- Lógica de creación de sesiones.
- Capacidad de sesiones.
- Reservas.
- Cancelaciones.
- Límite semanal.
- Gestión de nadadores.
- Importación/exportación CSV.
- Sincronización club → reservas.
- Perfil del nadador del site de reservas.
- Gestión de sesiones por gestores.
- Roles y capacidades existentes, salvo lo estrictamente necesario para el acceso/logout.
- Diseño desktop del calendario, salvo ajustes responsive imprescindibles.

---

# 4. Arquitectura y requisito previo de análisis

Antes de modificar código, debe analizarse la arquitectura actual de autenticación de ambos sites.

El proyecto utiliza WordPress Multisite y los usuarios pertenecen a la red, pero los dos sites tienen funcionalidades y flujos diferentes.

**NO se debe asumir que compartir directamente la cookie de autenticación de WordPress es la solución correcta.**

Antes de implementar el acceso automático, analizar como mínimo:

- configuración actual de WordPress Multisite;
- `COOKIE_DOMAIN`, si existe;
- `COOKIEPATH`;
- `SITECOOKIEPATH`;
- configuración de HTTPS;
- dominio/subdominio de ambos sites;
- forma actual de autenticación;
- creación y existencia de usuarios en ambos sites;
- roles y capacidades de cada site;
- mecanismo actual de bloqueo/redirección de `wp-admin`;
- funcionamiento de `wp_set_auth_cookie()`;
- funcionamiento de `wp_signon()`;
- existencia de hooks o servicios propios relacionados con login;
- relación entre el usuario del club y el usuario del site de reservas;
- comportamiento actual de las cookies entre ambos sites.

La solución elegida debe permitir:

1. Mantener la seguridad del login.
2. No exponer contraseñas.
3. No transmitir contraseñas entre sites.
4. Evitar tokens reutilizables.
5. Evitar que una URL de acceso pueda ser utilizada indefinidamente.
6. Identificar inequívocamente al usuario.
7. Verificar que el usuario tiene derecho a acceder al site de reservas.
8. Mantener los roles y permisos actuales del site de reservas.

Si se utiliza un mecanismo de token entre sites, debe ser:

- de un solo uso;
- de corta duración;
- impredecible;
- almacenado de forma segura;
- invalidado después de utilizarse;
- transmitido únicamente mediante HTTPS;
- asociado al usuario correcto;
- protegido contra replay.

No se debe enviar la contraseña del usuario del club al site de reservas.

---

# 5. Logout desde el site de reservas

## 5.1. Objetivo

El usuario debe disponer de una acción visible para cerrar sesión desde el site de reservas.

El botón debe estar disponible dentro del panel frontend cuando el usuario esté autenticado.

Ejemplo conceptual:

- Calendario
- Mis reservas
- Mi perfil
- Cerrar sesión

La ubicación exacta debe adaptarse al diseño actual del panel.

---

## 5.2. Comportamiento

Al pulsar "Cerrar sesión":

1. Debe utilizarse el mecanismo de logout seguro de WordPress.
2. La acción debe realizarse mediante una URL/acción segura y no mediante una simple URL GET sin protección si la arquitectura actual permite una alternativa mejor.
3. Debe invalidarse la sesión/autenticación correspondiente.
4. El usuario debe ser redirigido a una página pública apropiada.

La página de destino deberá determinarse durante el análisis de UX.

Preferencia:

- si el usuario ha llegado desde el site del club, volver al site del club;
- en caso contrario, permanecer en una página pública del site de reservas.

---

## 5.3. Consideración Multisite

Debe analizarse cuidadosamente si el logout de WordPress:

- cierra únicamente la sesión del site de reservas;
- o invalida la autenticación compartida de toda la red.

Esto es especialmente importante si se implementa un mecanismo de autenticación común entre el club y reservas.

No se debe implementar un logout que provoque efectos inesperados sobre el site del club sin haberlo analizado y documentado.

El comportamiento final debe quedar documentado en `DEVELOPMENT.md`.

---

# 6. Enlace desde el perfil del deportista del club

## 6.1. Objetivo

Desde el perfil del deportista del site del club debe existir un acceso directo al sistema de reservas.

Ejemplo:

**Reservas de piscina**

o

**Ir a reservas**

El enlace debe ser visible para los deportistas que puedan utilizar el sistema de reservas.

---

## 6.2. Condiciones

El enlace debe respetar la lógica existente del club y del sistema de reservas.

Antes de implementarlo se debe comprobar cómo se determina actualmente si un deportista está habilitado para utilizar reservas.

No se debe duplicar la lógica de sincronización.

Si existe una condición equivalente a:

- usuario deportista;
- `sulayr_user_swimming = 1`;
- `sulayr_user_athlete_status = active`;

debe reutilizarse la lógica existente o una abstracción común si ya existe.

No se debe crear una segunda fuente de verdad.

---

## 6.3. Usuario no autenticado

Si el perfil se puede visualizar sin autenticación, el enlace debe llevar al flujo de acceso correspondiente.

Si el usuario ya está autenticado, debe utilizar el mecanismo de acceso automático definido en esta fase.

---

# 7. Acceso automático Club → Reservas

## 7.1. Objetivo funcional

Cuando un usuario esté autenticado en:

`clubtriatlonsulayr.com`

y acceda al sistema de reservas mediante el enlace correspondiente, **no debe volver a introducir su contraseña**.

El resultado esperado es:

1. Usuario autenticado en Club.
2. Pulsa "Reservas".
3. El sistema verifica su identidad.
4. El usuario queda autenticado en Reservas.
5. Se muestra el panel de reservas.

---

# 8. Reglas del acceso automático

## 8.1. No utilizar contraseñas

La contraseña nunca debe:

- aparecer en una URL;
- enviarse entre sites;
- almacenarse temporalmente para este flujo;
- ser solicitada al site del club para realizar el SSO.

---

## 8.2. Identificación del usuario

El mecanismo debe identificar al usuario de forma inequívoca.

Siempre que sea posible, la relación debe basarse en el `user_id` de WordPress de red y no únicamente en:

- email;
- nombre;
- teléfono.

El análisis debe comprobar cómo se comporta actualmente el proyecto cuando un usuario existe en ambos sites.

---

## 8.3. Autorización

Estar autenticado en el club no implica automáticamente tener permiso para utilizar reservas.

El site de reservas debe seguir comprobando sus propias condiciones de acceso.

Por ejemplo:

- usuario existente;
- usuario dado de alta en el site de reservas;
- estado activo;
- permisos/rol adecuados.

La autenticación y la autorización son conceptos separados.

---

# 9. Protección del mecanismo de acceso

Si se implementa mediante token, deberán cumplirse como mínimo:

- token criptográficamente seguro;
- un solo uso;
- caducidad corta;
- asociación con usuario;
- asociación con intención/acción;
- invalidación después de utilizarse;
- HTTPS obligatorio;
- no almacenar el token en texto plano si se persiste;
- protección frente a replay;
- protección frente a manipulación del `user_id`;
- comprobación de que el usuario sigue siendo válido y activo;
- no permitir autenticarse como otro usuario modificando parámetros de la URL.

El token no debe contener información sensible innecesaria.

---

# 10. Usuarios no elegibles

Si el usuario está autenticado en el club pero no está habilitado para utilizar reservas:

- no debe obtener acceso automático al panel de reservas;
- debe mostrarse un mensaje apropiado;
- no debe crearse una ficha de nadador automáticamente salvo que esa funcionalidad ya exista y forme parte de la lógica actual.

La fase 11 no debe alterar las reglas existentes de alta/sincronización de nadadores.

---

# 11. Acceso desde Reservas → Club

## 11.1. Objetivo

Los usuarios que:

- tengan una ficha activa en el site de reservas;
- y además sean usuarios `athlete` del site del club;

deben disponer de un enlace desde el panel de reservas para volver fácilmente al site del club.

El enlace debe llevar preferentemente al perfil del deportista del propio usuario en el site del club.

Ejemplo de texto:

**Mi perfil en el club**

o

**Volver al perfil del club**

La ubicación concreta debe integrarse con la navegación y el diseño actual del panel.

---

## 11.2. Destino

El enlace debe llevar al perfil del propio deportista en el site del club.

No debe utilizarse un `user_id` proporcionado directamente por el navegador para determinar el destino.

El destino debe construirse a partir del usuario autenticado actualmente.

Antes de implementarlo, se debe localizar en el código del club cómo se genera actualmente la URL del perfil de un deportista y reutilizar esa lógica si existe.

No duplicar la lógica de generación de URLs si ya existe una función, helper o mecanismo equivalente.

---

## 11.3. Usuarios que no son `athlete`

Si el usuario está autenticado en reservas pero no tiene perfil `athlete` en el site del club:

- no debe mostrarse el enlace al perfil del club;
- no debe crearse un perfil automáticamente;
- no debe producirse ningún error.

El resto del panel de reservas debe funcionar normalmente.

---

## 11.4. Usuarios vinculados al club

Para los usuarios cuya ficha de reservas está vinculada al club:

- el enlace debe estar disponible siempre que el usuario siga siendo un `athlete` válido en el site del club;
- debe llevar al perfil del propio deportista.

La existencia del enlace no debe modificar ni sustituir la sincronización club → reservas.

---

## 11.5. Seguridad

El enlace debe utilizar exclusivamente la identidad del usuario autenticado.

No debe confiar en:

- `user_id` enviado por GET;
- parámetros manipulables por el usuario;
- datos introducidos desde el navegador.

El destino debe corresponder siempre al propio usuario.

---

## 11.6. Integración visual

El enlace debe integrarse con la navegación existente del panel.

Debe estudiarse si encaja mejor como:

- elemento del menú principal;
- enlace dentro de la tarjeta de usuario/perfil;
- acción dentro de la ficha propia;
- o combinación de las anteriores.

No duplicar innecesariamente el mismo enlace en múltiples lugares.

La solución final debe ser coherente con el diseño Bento actual y con el nuevo diseño mobile del calendario.

---

## 11.7. Logout

El enlace al club y el logout son funcionalidades independientes.

Pulsar:

**Mi perfil en el club**

no debe cerrar la sesión de reservas.

El usuario debe poder volver al club manteniendo su sesión según el mecanismo de autenticación establecido en esta fase.

---

# 12. Calendario mobile

## 12.1. Objetivo

En dispositivos móviles el calendario actual debe sustituirse por una interfaz orientada a mostrar **un único día cada vez**, permitiendo cambiar rápidamente entre días.

La referencia visual proporcionada para esta fase sirve como inspiración para:

- selector horizontal de días;
- día seleccionado destacado;
- contenido principal grande debajo;
- navegación anterior/siguiente;
- aprovechamiento vertical de la pantalla.

La captura proporcionada no debe copiarse literalmente ni considerarse un diseño final.

---

# 13. Estructura del calendario mobile

La vista mobile tendrá dos zonas principales.

## 13.1. Selector superior de días

En la parte superior aparecerá una franja horizontal con los días de la semana.

Debe mostrar inicialmente los días de la semana actual.

Conceptualmente:

```

```

```
┌──────────────────────────────────────┐
│ lun 13    mar 14    MIÉ 15    jue 16 │
│          ───────── seleccionado ─────│
└──────────────────────────────────────┘
```

El día actual debe aparecer inicialmente seleccionado.

El día seleccionado debe tener un tratamiento visual claramente diferenciado.

---

## 13.2. Navegación horizontal

El selector de días debe permitir desplazamiento horizontal mediante:

-  swipe táctil; 
-  navegación por teclado cuando corresponda; 
-  interacción mediante controles accesibles. 

El objetivo es poder cambiar rápidamente entre los días de la semana.

El movimiento debe sentirse natural y no producir scroll horizontal de toda la página.

---

# 14. Día seleccionado

Debajo del selector se mostrará el día seleccionado en formato grande.

Debe contener:

-  día de la semana; 
-  fecha; 
-  sesiones disponibles de ese día; 
-  estado de cada sesión; 
-  acciones disponibles. 

Ejemplo conceptual:

```

```

```
MIÉRCOLES
15 OCTUBRE

┌──────────────────────────────┐
│ 15:00 – 16:30                │
│                              │
│ Disponible                  │
│ 12 / 24 plazas              │
│                              │
│ [ RESERVAR ]                 │
└──────────────────────────────┘

┌──────────────────────────────┐
│ 21:00 – 22:30                │
│                              │
│ Reservada por ti             │
│                              │
│ [ CANCELAR ]                 │
└──────────────────────────────┘
```

El contenido debe ocupar el espacio vertical disponible de forma natural.

---

# 15. Estados de las sesiones

Debe conservarse la semántica de estados actualmente utilizada por el calendario.

Como mínimo:

-  Disponible. 
-  Reservada por ti. 
-  Completa. 
-  Deshabilitada. 
-  Ya comenzada. 

Los estilos visuales pueden adaptarse al nuevo diseño mobile, pero no debe cambiarse el significado funcional de los estados.

No crear estados nuevos únicamente para el rediseño.

---

# 16. Navegación anterior/siguiente

Debe existir una navegación clara para cambiar de día:

-  día anterior; 
-  día siguiente. 

Puede implementarse mediante botones:

```

```

```
‹                     ›
```

o controles equivalentes.

Los botones deben:

-  ser fácilmente pulsables en móvil; 
-  tener suficiente área táctil; 
-  ser accesibles; 
-  no provocar recarga innecesaria de página si puede evitarse; 
-  mantener el estado de autenticación y la funcionalidad actual. 

---

# 17. Relación entre selector superior y navegación

El selector superior y los botones anterior/siguiente deben permanecer sincronizados.

Ejemplo:

```

```

```
Usuario está en miércoles
        ↓
Pulsa "día siguiente"
        ↓
Se muestra jueves
        ↓
El selector superior pasa a destacar jueves
```

Igualmente:

```

```

```
Usuario hace swipe hasta jueves
        ↓
El contenido principal cambia a jueves
        ↓
Jueves queda seleccionado
```

Debe existir una única fecha/día seleccionado como estado de la interfaz.

---

# 18. Cambio de semana

El comportamiento al llegar al primer o último día de la semana debe definirse durante la implementación siguiendo estas reglas:

-  La navegación nunca debe quedar bloqueada sin una razón funcional. 
-  Si el usuario pulsa siguiente desde domingo, debe poder acceder al siguiente día disponible según el calendario. 
-  Si el usuario pulsa anterior desde lunes, debe poder acceder al día anterior disponible. 
-  Debe respetarse que actualmente no existen sesiones todos los días. 

El selector superior puede pasar a representar la semana correspondiente cuando la navegación cruza de semana.

El día seleccionado deberá seguir siendo coherente con la semana mostrada.

---

# 19. Días sin sesiones

El calendario debe poder mostrar días sin sesiones.

Por ejemplo:

-  viernes; 
-  domingo. 

Estos días no deben desaparecer necesariamente del selector semanal si forman parte de la semana mostrada.

El contenido principal debe indicar claramente que no hay sesiones.

Ejemplo:

```

```

```
DOMINGO
18 OCTUBRE

No hay sesiones programadas.
```

No debe interpretarse como error.

---

# 20. Semana actual y fecha inicial

Al abrir el calendario en móvil:

-  debe mostrarse la semana que corresponde a la fecha actualmente seleccionada por el sistema; 
-  inicialmente debe quedar seleccionado el día actual si pertenece al rango mostrado; 
-  si el día actual no tiene sesiones, debe seguir apareciendo seleccionado y mostrar que no hay sesiones. 

La fecha debe calcularse utilizando la zona horaria configurada por WordPress:

`Europe/Madrid`

No utilizar la zona horaria del navegador como fuente de verdad para determinar el día de reserva.

---

# 21. Integración con reservas

El nuevo calendario debe reutilizar las consultas y servicios existentes.

No debe duplicarse la lógica de:

-  disponibilidad; 
-  capacidad; 
-  reserva; 
-  cancelación; 
-  límite semanal; 
-  estado de sesión; 
-  permisos. 

El frontend solo debe cambiar la presentación y navegación.

Las acciones de:

-  Reservar; 
-  Cancelar; 

deben seguir utilizando los mecanismos actuales del panel.

---

# 22. Desktop y tablet

El nuevo diseño descrito en esta sección es principalmente para móvil.

Antes de modificar el diseño desktop debe analizarse el breakpoint existente.

Objetivo:

-  móvil → nuevo calendario de un día; 
-  escritorio → mantener el calendario Bento/cuadrícula existente; 
-  tablet → adaptar según el espacio disponible sin romper la interfaz. 

No se debe reemplazar el calendario desktop actual si no es necesario.

---

# 23. Responsive

Como mínimo debe comprobarse:

-  390 px; 
-  414 px; 
-  768 px; 
-  1024 px; 
-  1280 px. 

No debe existir:

-  scroll horizontal accidental; 
-  botones fuera de pantalla; 
-  texto cortado; 
-  sesiones que desborden; 
-  selector de días inaccesible; 
-  controles táctiles excesivamente pequeños. 

---

# 24. Accesibilidad

El selector y la navegación deben ser accesibles.

Como mínimo:

-  botones reales cuando corresponda; 
-  labels/aria-label en controles de navegación; 
-  foco visible; 
-  navegación por teclado; 
-  contraste suficiente; 
-  estado seleccionado identificable también para lectores de pantalla; 
-  swipe como mejora adicional, no como única forma de navegación. 

---

# 25. Touch / Swipe

El swipe debe utilizarse únicamente para cambiar de día.

No debe:

-  desplazar horizontalmente toda la página; 
-  interferir con botones; 
-  activarse accidentalmente al hacer scroll vertical; 
-  romper la interacción con enlaces o formularios de reserva. 

Debe existir una alternativa mediante botones.

La implementación debe evitar dependencias JavaScript adicionales si no son necesarias.

---

# 26. Rendimiento

El cambio de día no debería provocar consultas innecesarias al servidor si los datos necesarios ya están disponibles.

Durante el análisis debe determinarse si:

1.  cargar todos los días de la semana inicialmente; 
2.  cargar cada día bajo demanda; 
3.  utilizar la consulta existente y cachear los resultados en frontend. 

Debe elegirse la solución más sencilla compatible con la arquitectura existente.

No introducir una librería de calendario o framework JS pesado para esta funcionalidad.

---

# 27. Compatibilidad con el panel actual

Debe conservarse:

-  navegación actual; 
-  tarjetas/resumen; 
-  perfil; 
-  listado de reservas; 
-  gestión para gestores; 
-  acciones de reserva/cancelación; 
-  leyenda de estados cuando siga siendo necesaria; 
-  permisos actuales. 

El rediseño del calendario no debe modificar la lógica del resto del panel.

---

# 28. Requisitos técnicos de implementación

Antes de implementar:

1.  Leer: 
  - `SPEC-RESERVAS.md` 
  - `SPEC-MEJORAS-RESERVAS.md` 
  - `DEVELOPMENT.md` 
  - `FASES.md` 
  - `ACEPTACION.md` 
  - `MANUAL-USUARIO.md` 
2.  Analizar: 
  -  autenticación actual; 
  -  logout actual; 
  -  relación de usuarios entre ambos sites; 
  -  perfil del deportista del club; 
  -  URL actual del perfil de deportista; 
  -  mecanismo actual para determinar si un usuario es `athlete`; 
  - `PanelRenderer`; 
  - `PanelQuery`; 
  - `PanelActions`; 
  -  CSS actual; 
  -  navegación actual del panel; 
  -  breakpoints existentes. 
3.  Presentar primero un análisis y plan de implementación. 
4.  No modificar código durante el análisis. 

---

# 29. Seguridad

Toda acción nueva debe respetar:

-  nonces cuando exista POST/acción; 
-  capacidades; 
-  comprobación de usuario; 
-  validación de parámetros; 
-  escaping de salida; 
-  sanitización de entrada; 
-  HTTPS; 
-  protección contra CSRF; 
-  protección contra autenticación como otro usuario. 

Especial atención al acceso Club → Reservas.

Nunca se debe confiar únicamente en un parámetro como:

```

```

```
?user_id=123
```

para decidir qué usuario autenticar.

El enlace Reservas → Club debe utilizar igualmente la identidad del usuario autenticado y no un `user_id` proporcionado por el navegador.

---

# 30. Pruebas obligatorias

## 30.1. Logout

Comprobar:

-  usuario nadador; 
-  gestor; 
-  administrador; 
-  usuario no autenticado; 
-  acceso posterior al panel; 
-  comportamiento de la sesión del club si existe autenticación compartida. 

---

## 30.2. Acceso Club → Reservas

Comprobar:

### Usuario elegible

Debe seguir el flujo:

**Login club → pulsar Reservas → acceso automático → panel reservas**

Sin pedir contraseña.

### Usuario no elegible

Debe rechazarse correctamente.

### Usuario no autenticado

Debe seguir el flujo normal de autenticación.

### Intentos de manipulación

Modificar cualquier identificador/token de acceso debe impedir la autenticación como otro usuario.

### Replay

Intentar reutilizar un token ya consumido debe fallar.

### Expiración

Intentar utilizar un token caducado debe fallar.

---

## 30.3. Acceso Reservas → Club

Comprobar:

### Usuario `athlete`

Debe visualizar el enlace y acceder al perfil correspondiente del site del club.

### Usuario que no es `athlete`

No debe visualizar el enlace.

### Usuario vinculado

Debe visualizar el enlace mientras siga siendo un `athlete` válido en el club.

### Manipulación

Modificar parámetros de la URL no debe permitir acceder al perfil de otro deportista.

### Sesión

Pulsar el enlace no debe cerrar la sesión de reservas.

---

## 30.4. Enlace club

Probar:

-  usuario elegible; 
-  usuario no elegible; 
-  usuario autenticado; 
-  usuario no autenticado. 

---

## 30.5. Calendario mobile

Comprobar como mínimo:

-  390 px; 
-  414 px; 
-  768 px. 

Y:

-  apertura en día actual; 
-  selección de cada día; 
-  swipe; 
-  botón anterior; 
-  botón siguiente; 
-  sincronización selector/contenido; 
-  lunes; 
-  martes; 
-  miércoles; 
-  jueves; 
-  viernes sin sesiones; 
-  sábado; 
-  domingo sin sesiones; 
-  cambio de semana; 
-  sesión disponible; 
-  sesión reservada; 
-  sesión completa; 
-  sesión deshabilitada; 
-  sesión ya comenzada; 
-  reservar; 
-  cancelar; 
-  límite semanal; 
-  usuario gestor; 
-  usuario administrador. 

---

# 31. Regresión

Debe comprobarse que siguen funcionando:

-  login; 
-  logout; 
-  acceso Club → Reservas; 
-  acceso Reservas → Club; 
-  calendario; 
-  reservas; 
-  cancelaciones; 
-  límite semanal; 
-  perfil; 
-  gestión de nadadores; 
-  importación CSV; 
-  exportación CSV; 
-  sincronización club → reservas; 
-  gestión de sesiones; 
-  redirección de `wp-admin`. 

No se deben modificar funcionalidades de fases anteriores salvo lo estrictamente necesario para esta fase.

---

# 32. Documentación

Al finalizar se actualizarán:

- `DEVELOPMENT.md` 
- `FASES.md` 
- `ACEPTACION.md` 
- `MANUAL-USUARIO.md` 

`SPEC-RESERVAS.md` no debe modificarse.

`SPEC-MEJORAS-RESERVAS.md` no debe modificarse.

Este documento (`SPEC-FASE11-RESERVAS.md`) debe conservarse como especificación de la fase.

La documentación debe indicar claramente:

-  mecanismo elegido para el acceso Club → Reservas; 
-  comportamiento del logout; 
-  comportamiento cuando el usuario no está habilitado; 
-  comportamiento del enlace Reservas → Club; 
-  condiciones para mostrar el enlace al club; 
-  comportamiento al cruzar de semana; 
-  breakpoints del calendario mobile; 
-  decisiones de UX relevantes. 

---

# 33. Commit

No realizar commit.

No realizar push.

La fase se considera implementada en el árbol de trabajo cuando:

1.  La funcionalidad esté implementada. 
2.  Las pruebas hayan sido ejecutadas. 
3.  Las regresiones hayan sido comprobadas. 
4.  La documentación haya sido actualizada. 
5.  Se entregue un informe final. 
6.  No queden errores conocidos que contradigan los requisitos. 

---

# 34. Informe final esperado

Al terminar, el informe debe incluir:

## Implementado

Lista de funcionalidades implementadas.

## Archivos

Archivos creados y modificados.

## Autenticación

Explicar exactamente cómo funciona el flujo:

`Club → mecanismo de autenticación → Reservas`

incluyendo seguridad, expiración e invalidación.

## Logout

Explicar qué sesión se cierra y qué ocurre en el otro site.

## Navegación entre Club y Reservas

Explicar:

-  Club → Reservas; 
-  Reservas → perfil del Club; 
-  condiciones para mostrar cada enlace; 
-  comportamiento para usuarios no `athlete`. 

## Calendario mobile

Explicar:

-  estructura; 
-  navegación; 
-  swipe; 
-  cambio de semana; 
-  responsive; 
-  estados. 

## Pruebas

Tabla:


| Prueba | Resultado  |
| ------ | ---------- |
| ...    | OK / FALLA |


## Regresión

Indicar qué funcionalidades anteriores se comprobaron.

## Pendientes

Indicar cualquier prueba no realizada o incidencia encontrada.

## Documentación

Enumerar los documentos actualizados.

No realizar commit salvo autorización explícita.

---

# 35. Decisión de implementación

La red es de subdominios y `COOKIE_DOMAIN` es `.clubtriatlonsulayr.local.com`. La cookie de sesión de WordPress ya identifica al usuario en el club y en reservas. No se ha creado un token paralelo: firmar otra vez la misma cookie no separa las sesiones y abriría un segundo camino de acceso.

La autorización sigue en el sitio de reservas. El enlace del club solo se muestra si el deportista cumple la condición de sincronización (`athlete`, natación `1`, estado `active`). Cerrar sesión invalida esa cookie de red, así que sale de los dos sitios. El detalle está en `DEVELOPMENT.md`, sección «Fase 11».