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

echo "Installing composer dependencies..."
composer install --no-interaction

echo "Updating database structure..."
php config/update-structure.php || true

echo "Starting PHP-FPM..."
exec php-fpm -F