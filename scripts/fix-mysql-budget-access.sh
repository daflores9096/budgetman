#!/bin/sh
# Repara usuario/contraseña/permisos MySQL para budgetman en NAS o Docker.
# Uso (SSH en el NAS, carpeta del proyecto):
#   sh scripts/fix-mysql-budget-access.sh
#
# Variables opcionales:
#   DB_CONTAINER=budgetman-db-1

set -e

DB_CONTAINER="${DB_CONTAINER:-budgetman-db-1}"

MYSQL_USER="$(docker exec "$DB_CONTAINER" printenv MYSQL_USER)"
MYSQL_DATABASE="$(docker exec "$DB_CONTAINER" printenv MYSQL_DATABASE)"
MYSQL_PASSWORD="$(docker exec "$DB_CONTAINER" printenv MYSQL_PASSWORD)"
MYSQL_ROOT_PASSWORD="$(docker exec "$DB_CONTAINER" printenv MYSQL_ROOT_PASSWORD)"

if [ -z "$MYSQL_USER" ] || [ -z "$MYSQL_DATABASE" ] || [ -z "$MYSQL_PASSWORD" ] || [ -z "$MYSQL_ROOT_PASSWORD" ]; then
  echo "No se leyeron MYSQL_* del contenedor $DB_CONTAINER"
  exit 1
fi

echo "Reparando acceso de '$MYSQL_USER' a '$MYSQL_DATABASE' en $DB_CONTAINER …"

docker exec -e "MYSQL_PWD=${MYSQL_ROOT_PASSWORD}" "$DB_CONTAINER" mysql -uroot <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
ALTER USER '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL

docker exec -e "MYSQL_PWD=${MYSQL_PASSWORD}" "$DB_CONTAINER" \
  mysql -u"${MYSQL_USER}" "${MYSQL_DATABASE}" -e "SELECT 'ok' AS connection_test;"

echo "Listo. Reinicia la API:"
echo "  docker compose up -d --force-recreate api"
