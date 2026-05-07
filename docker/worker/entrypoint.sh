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

cd /var/www/api

until [ -f vendor/autoload.php ]; do
  echo "Waiting for composer dependencies..."
  sleep 2
done

echo "Starting PCAP worker..."
exec "$@"
