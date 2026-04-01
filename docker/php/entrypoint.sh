#!/bin/sh

set -e

echo "Waiting for MySQL..."

until php -r "
new mysqli(
    getenv('MYSQL_HOST'),
    getenv('MYSQL_USER'),
    getenv('MYSQL_PASSWORD'),
    getenv('MYSQL_DATABASE'),
    getenv('MYSQL_PORT')
);
"; do
  echo "MySQL not ready yet..."
  sleep 2
done

echo "MySQL is ready."

cd /var/www/api

echo "Installing composer dependencies..."
composer install --no-interaction

echo "Updating database structure..."
php config/update-structure.php || true

echo "Starting PHP-FPM..."
exec php-fpm -F