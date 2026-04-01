#!/bin/sh

cd /var/www/app

echo "Installing node modules..."
npm install

echo "Building frontend..."
npm run build

echo "Starting nginx..."

exec /docker-entrypoint.sh nginx -g "daemon off;"