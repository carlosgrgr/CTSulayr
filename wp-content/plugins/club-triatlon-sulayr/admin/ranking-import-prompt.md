Te adjunto las clasificaciones oficiales de una competición de triatlón (PDF de la Federación Andaluza de Triatlón u otra federación). Conviértelas en un JSON para importarlas en el ranking interno del Club Triatlón Sulayr.

REGLAS
1. Copia los datos tal cual están en el PDF. No inventes, no redondees y no corrijas nombres.
2. Incluye TODAS las clasificaciones adjuntas (por ejemplo, federado y open, masculina y femenina) y TODOS los deportistas de cada una, sean o no del club, también los que no terminan (DNF, DSQ, DNS, cortados). Revisa todas las páginas.
3. Una clasificación por cada PDF o tabla. En relevos, salta las filas de equipo (el nombre del equipo en azul y el tiempo total del equipo): cada deportista es una fila, y su «club» es el nombre del equipo que sale en su fila.
4. Responde solo con el JSON, sin texto antes ni después.

FORMATO
{
  "schema": "sulayr-ranking-import/1",
  "competition": {
    "name": "Nombre de la prueba tal como sale en la cabecera, en mayúsculas y minúsculas normales",
    "race_date": "AAAA-MM-DD",
    "location": "Localidad",
    "modality": "triatlon | triatlon_cros | duatlon | duatlon_cros | acuatlon | acuabike",
    "distance": "supersprint | sprint | estandar | media | larga",
    "format": "individual | relevos"
  },
  "classifications": [
    {
      "title": "Título de la clasificación tal como sale en el PDF (p. ej. OPEN MASCULINA)",
      "team_category": "Solo en relevos: m (masculina), f (femenina) o mixto. En individual, cadena vacía.",
      "finishers": "Número de «FINALIZADOS» de la cabecera, si aparece; si no, null.",
      "athletes": [
        {
          "pos": 1,
          "name": "NOMBRE Y APELLIDOS tal como salen",
          "club": "Club o equipo de la fila",
          "cat": "Código de categoría de la columna Cat (p. ej. V1M, SNF, 23M, JVM)",
          "pos_cat": 1,
          "time": "h:mm:ss (columna TIEMPO final del deportista; en relevos, su tiempo individual)",
          "status": "finalizado | dnf | dsq | dns | nc",
          "sex": "m | f (solo si no hay columna Cat)"
        }
      ]
    }
  ]
}

DETALLES
- "pos": la columna «Pos» de la fila (posición del deportista en esa clasificación). "pos_cat": la columna «Pos Cat».
- "time": la columna de tiempo final (en negrita), no los parciales. Formato h:mm:ss, p. ej. 0:20:58 o 1:05:12.
- Si un deportista no termina, "status" es dnf, dsq, dns o nc (corte), y "pos", "pos_cat" y "time" van a null.
- "modality" y "distance": elige el valor de la lista que corresponde a la cabecera (Duatlón → duatlon; Supersprint → supersprint; Olímpico o estándar → estandar).
- "format": relevos si la prueba es por relevos; individual en el resto.
- Si un dato no está en el PDF, pon null. Nunca lo deduzcas.
