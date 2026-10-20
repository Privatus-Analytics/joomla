#!/bin/sh
# Installs the built zip in a real Joomla (Docker) and checks the tag.
# Usage: scripts/verify-docker.sh [joomla-image]
# Default image: joomla:5.4.6-php8.3-apache. Also tested: joomla:4.4.14-php8.2-apache.
set -e
cd "$(dirname "$0")/.."
IMAGE="${1:-joomla:5.4.6-php8.3-apache}"
DB_IMAGE="mariadb:11.4.7"
TAG=$(echo "$IMAGE" | tr -c 'a-zA-Z0-9\n' '-')
NET="pa-joomla-$TAG"
PORT="${PORT:-8085}"
ADMIN_USER="paadmin"
# Throwaway test values for a local container. Never reused anywhere else.
ADMIN_PASS="Pa-$(openssl rand -hex 10)-test"
DB_PASS="$(openssl rand -hex 12)"

cleanup() {
  docker rm -f "$NET-web" "$NET-db" >/dev/null 2>&1 || true
  docker network rm "$NET" >/dev/null 2>&1 || true
}
trap cleanup EXIT
cleanup
./scripts/build.sh >/dev/null
ZIP=$(ls dist/plg_system_privatusanalytics-*.zip | tail -1)

docker network create "$NET" >/dev/null
docker run -d --name "$NET-db" --network "$NET" -e MARIADB_ROOT_PASSWORD="$DB_PASS" -e MARIADB_DATABASE=joomla "$DB_IMAGE" >/dev/null
echo "Waiting for the database..."
for i in $(seq 1 60); do
  docker exec "$NET-db" mariadb -uroot -p"$DB_PASS" -e 'select 1' >/dev/null 2>&1 && break
  sleep 2
done
docker run -d --name "$NET-web" --network "$NET" -p "$PORT:80" \
  -e JOOMLA_DB_HOST="$NET-db" -e JOOMLA_DB_USER=root -e JOOMLA_DB_PASSWORD="$DB_PASS" -e JOOMLA_DB_NAME=joomla \
  -e JOOMLA_SITE_NAME="Verify" -e JOOMLA_ADMIN_USER="Admin" -e JOOMLA_ADMIN_USERNAME="$ADMIN_USER" \
  -e JOOMLA_ADMIN_PASSWORD="$ADMIN_PASS" -e JOOMLA_ADMIN_EMAIL="admin@example.test" \
  -e JOOMLA_AUTO_DEPLOY=true "$IMAGE" >/dev/null
echo "Waiting for Joomla to install itself..."
for i in $(seq 1 120); do
  curl -s -o /dev/null -w '%{http_code}' "http://localhost:$PORT/administrator/" 2>/dev/null | grep -q 200 && \
    docker exec "$NET-web" test -f /var/www/html/configuration.php && break
  sleep 3
done
docker exec "$NET-web" php /var/www/html/cli/joomla.php --version

docker cp "$ZIP" "$NET-web:/tmp/plugin.zip"
docker exec -u www-data "$NET-web" php /var/www/html/cli/joomla.php extension:install --path=/tmp/plugin.zip
docker exec "$NET-db" mariadb -uroot -p"$DB_PASS" joomla -e \
  "select extension_id,name,enabled from $(docker exec "$NET-db" mariadb -uroot -p"$DB_PASS" joomla -N -e "select table_name from information_schema.tables where table_name like '%\_extensions' limit 1") where element='privatusanalytics'"

BASE="http://localhost:$PORT" ADMIN_USER="$ADMIN_USER" ADMIN_PASS="$ADMIN_PASS" \
  DB_CONTAINER="$NET-db" DB_PASS="$DB_PASS" python3 -I scripts/verify_joomla.py
