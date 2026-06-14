#!/bin/bash
# Refuerza usuario y permisos en la primera inicialización del volumen MySQL.
set -euo pipefail
export MYSQL_PWD="${MYSQL_ROOT_PASSWORD}"
mysql -uroot --protocol=socket <<-EOSQL
CREATE DATABASE IF NOT EXISTS \`${MYSQL_DATABASE}\`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.* TO '${MYSQL_USER}'@'%';
FLUSH PRIVILEGES;
EOSQL
