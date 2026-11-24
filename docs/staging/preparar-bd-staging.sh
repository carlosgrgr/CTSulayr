#!/usr/bin/env bash
#
# Convierte un volcado de la BD de PROD en un volcado listo para STAGING.
# Se ejecuta EN LOCAL (PROD no tiene WP-CLI), desde la raíz del repo, y no toca
# la BD local de trabajo: usa una BD temporal aparte.
#
#   ./docs/staging/preparar-bd-staging.sh prod.sql staging.sql
#
# Requisitos (ver docs/STAGING.md, «Preparación única en local»):
#   - WP-CLI en local.
#   - El wp-config.php LOCAL lee DB_NAME, DOMAIN_CURRENT_SITE y DISABLE_WP_CRON
#     de variables de entorno cuando existen.
#
# Variables opcionales:
#   TMP_DB=ctsulayr_staging_tmp      BD temporal (se crea; se vacía si ya existe)
#   ANONIMIZAR=1                     cambia el email de los usuarios por uno falso
#   CONSERVAR_EMAILS="a@x.com,b@y.com"   emails que NO se anonimizan (los vuestros)
#   BORRAR_TMP=1                     borra la BD temporal al terminar
#
set -euo pipefail

PROD_DOMAIN="clubtriatlonsulayr.com"
STAGING_DOMAIN="staging.clubtriatlonsulayr.com"
RES_SUB="cnnazari"   # subdominio del sitio de reservas (blog 2)

IN="${1:-}"
OUT="${2:-staging.sql}"
TMP_DB="${TMP_DB:-ctsulayr_staging_tmp}"

die() { echo "ERROR: $*" >&2; exit 1; }

[ -n "$IN" ] || die "uso: $0 <volcado-prod.sql> [salida-staging.sql]"
[ -f "$IN" ] || die "no existe $IN"
[ -f wp-config.php ] || die "ejecútalo desde la raíz del repo (donde está wp-config.php)"
command -v wp >/dev/null || die "falta WP-CLI"
[[ "$TMP_DB" =~ ^[A-Za-z0-9_]+$ ]] || die "TMP_DB solo admite letras, números y _"

# Un volcado con USE o CREATE DATABASE escribiría en otra BD (quizá la local).
if grep -m1 -qiE '^(USE |CREATE DATABASE)' "$IN"; then
	die "$IN contiene USE o CREATE DATABASE. Expórtalo desde phpMyAdmin sin esas sentencias."
fi

# El wp-config local lee estas variables (ver docs/STAGING.md).
export SULAYR_DB_NAME="$TMP_DB"
export SULAYR_DOMAIN="$PROD_DOMAIN"
WP=(wp --url="$PROD_DOMAIN" --skip-plugins --skip-themes)

# Devuelve el nombre de la BD a la que conecta WP-CLI, o vacío si no existe.
current_db() { wp db query "SELECT DATABASE()" --skip-column-names 2>/dev/null || true; }

# ── Seguridad: nunca tocar una BD que no sea la temporal ──
db="$(current_db)"
if [ -n "$db" ] && [ "$db" != "$TMP_DB" ]; then
	die "WP-CLI conecta con '$db', no con '$TMP_DB'. Falta el cambio de DB_NAME en el wp-config local."
fi
if [ "$db" = "$TMP_DB" ]; then
	echo "· Vaciando la BD temporal $TMP_DB"
	wp db reset --yes
else
	echo "· Creando la BD temporal $TMP_DB"
	wp db create
fi
[ "$(current_db)" = "$TMP_DB" ] || die "no se pudo confirmar la conexión con $TMP_DB"

echo "· Importando $IN"
wp db import "$IN"

PREFIX="$(wp config get table_prefix)"
[[ "$PREFIX" =~ ^[A-Za-z0-9_]+$ ]] || die "prefijo de tablas raro: $PREFIX"

# ── Reemplazo de URLs (respeta datos serializados) ──
# Se reemplaza «//dominio» y no «dominio» a secas, para no cambiar emails
# como info@clubtriatlonsulayr.com. Las pasadas no se pisan: «//cnnazari.…» no
# contiene «//clubtriatlonsulayr.com». También la versión escapada en JSON (\/\/).
replace() {
	echo "· $1  →  $2"
	"${WP[@]}" search-replace "$1" "$2" \
		--all-tables-with-prefix --precise --skip-columns=guid --report-changed-only
}
replace "//${RES_SUB}.${PROD_DOMAIN}"     "//${RES_SUB}.${STAGING_DOMAIN}"
replace "//www.${PROD_DOMAIN}"            "//${STAGING_DOMAIN}"
replace "//${PROD_DOMAIN}"                "//${STAGING_DOMAIN}"
replace "\\/\\/${RES_SUB}.${PROD_DOMAIN}" "\\/\\/${RES_SUB}.${STAGING_DOMAIN}"
replace "\\/\\/www.${PROD_DOMAIN}"        "\\/\\/${STAGING_DOMAIN}"
replace "\\/\\/${PROD_DOMAIN}"            "\\/\\/${STAGING_DOMAIN}"

# ── Ajustes de staging en SQL (sin cargar WordPress) ──
BLOG_IDS="$(wp db query "SELECT blog_id FROM ${PREFIX}blogs" --skip-column-names)"
for id in $BLOG_IDS; do
	if [ "$id" = "1" ]; then t="${PREFIX}options"; else t="${PREFIX}${id}_options"; fi
	echo "· Blog $id: sin indexar y sin códigos de seguimiento ($t)"
	wp db query "UPDATE $t SET option_value='0' WHERE option_name='blog_public';"
	wp db query "DELETE FROM $t WHERE option_name='sulayr_scripts_manager_codes';"
done

if [ "${ANONIMIZAR:-0}" = "1" ]; then
	keep="''"
	if [ -n "${CONSERVAR_EMAILS:-}" ]; then
		[[ "$CONSERVAR_EMAILS" =~ ^[A-Za-z0-9@._+,-]+$ ]] || die "CONSERVAR_EMAILS tiene caracteres no válidos"
		keep="'${CONSERVAR_EMAILS//,/\',\'}'"
	fi
	echo "· Anonimizando emails de ${PREFIX}users (salvo: ${CONSERVAR_EMAILS:-ninguno})"
	wp db query "UPDATE ${PREFIX}users SET user_email=CONCAT('usuario', ID, '@staging.invalid') WHERE user_email NOT IN ($keep);"
fi

# Dominios de la red: columnas sin «//», por eso van al final y por SQL.
# Desde aquí WP-CLI ya no arranca con el dominio de PROD contra esta BD.
echo "· Dominios de la red → $STAGING_DOMAIN"
wp db query "UPDATE ${PREFIX}site SET domain='${STAGING_DOMAIN}' WHERE domain IN ('${PROD_DOMAIN}','www.${PROD_DOMAIN}');"
wp db query "UPDATE ${PREFIX}blogs SET domain='${STAGING_DOMAIN}' WHERE domain IN ('${PROD_DOMAIN}','www.${PROD_DOMAIN}');"
wp db query "UPDATE ${PREFIX}blogs SET domain='${RES_SUB}.${STAGING_DOMAIN}' WHERE domain='${RES_SUB}.${PROD_DOMAIN}';"

# ── Comprobación: no debe quedar ninguna URL de PROD ──
echo "· Comprobando restos del dominio de PROD"
left=0
for t in $(wp db query "SHOW TABLES LIKE '${PREFIX}%'" --skip-column-names); do
	cols="$(wp db query "SELECT GROUP_CONCAT(CONCAT('\`',COLUMN_NAME,'\`')) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='$t' AND DATA_TYPE IN ('char','varchar','text','tinytext','mediumtext','longtext') AND COLUMN_NAME<>'guid'" --skip-column-names)"
	[ -z "$cols" ] || [ "$cols" = "NULL" ] && continue
	n="$(wp db query "SELECT COUNT(*) FROM \`$t\` WHERE CONCAT_WS(' ',$cols) REGEXP '//(www\\\\.|${RES_SUB}\\\\.)?clubtriatlonsulayr\\\\.com'" --skip-column-names)"
	if [ "$n" != "0" ]; then echo "  ! $t: $n filas con URL de PROD"; left=1; fi
done
[ "$left" = "0" ] && echo "  OK, ninguna."

echo "· Exportando $OUT"
wp db export "$OUT"

if [ "${BORRAR_TMP:-0}" = "1" ]; then
	[ "$(current_db)" = "$TMP_DB" ] && wp db drop --yes
fi

echo
echo "Hecho: $OUT"
[ "$left" = "0" ] || echo "Revisa las filas marcadas con ! antes de importar en staging."
