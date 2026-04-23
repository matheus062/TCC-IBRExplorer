#!/bin/sh

set -e

echo "Waiting for PostgreSQL..."

until php -r "
new PDO(
    'pgsql:host=' . getenv('POSTGRES_HOST') . ';port=' . getenv('POSTGRES_PORT') . ';dbname=' . getenv('POSTGRES_DATABASE'),
    getenv('POSTGRES_USER'),
    getenv('POSTGRES_PASSWORD')
);
"; do
  echo "PostgreSQL not ready yet..."
  sleep 2
done

echo "PostgreSQL is ready."

cd /var/www/api

LOCKFILE="composer.lock"
CHECKSUM_FILE="vendor/.composer.lock.cksum"

mkdir -p vendor

CURRENT_CHECKSUM=""

if [ -f "$LOCKFILE" ]; then
  CURRENT_CHECKSUM="$(cksum "$LOCKFILE" | awk '{print $1":"$2}')"
fi

SAVED_CHECKSUM=""

if [ -f "$CHECKSUM_FILE" ]; then
  SAVED_CHECKSUM="$(cat "$CHECKSUM_FILE")"
fi

if [ ! -f vendor/autoload.php ] || [ "$CURRENT_CHECKSUM" != "$SAVED_CHECKSUM" ]; then
  echo "Installing composer dependencies..."
  composer install --no-interaction --prefer-dist
  printf '%s' "$CURRENT_CHECKSUM" > "$CHECKSUM_FILE"
else
  echo "Composer dependencies already installed."
fi

echo "Updating database structure..."
php config/update-structure.php

echo "Starting PHP-FPM..."
exec php-fpm -F
