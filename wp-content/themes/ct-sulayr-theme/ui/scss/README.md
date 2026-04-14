# UI SCSS

El CSS del template UI está en **`style.scss`** (equivalente a `../css/style.css`; todo CSS es SCSS válido).

## Compilar a CSS

Desde la raíz del tema:

```bash
# Con Dart Sass (recomendado)
npx sass ui/scss/style.scss ui/css/style.css --no-source-map

# O con Sass (Ruby)
sass ui/scss/style.scss ui/css/style.css
```

El tema encola `ui/css/style.css` en el front; tras editar `style.scss`, vuelve a compilar para ver los cambios.

## Refactorizar

Puedes ir convirtiendo bloques a SCSS “de verdad”:

- **Variables**: colores, espaciados, breakpoints.
- **Anidación**: agrupar reglas bajo un selector padre (p. ej. `.swiper-container { .swiper-wrapper { … } }`).
- **Partials**: dividir en `_reset.scss`, `_swiper.scss`, `_buttons.scss`, etc. e importarlos en `style.scss`.
