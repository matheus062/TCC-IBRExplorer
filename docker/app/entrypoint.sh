#!/bin/sh

set -e

cd /var/www/app

LOCKFILE="package-lock.json"
CHECKSUM_FILE="node_modules/.package-lock.cksum"

mkdir -p node_modules

CURRENT_CHECKSUM=""

if [ -f "$LOCKFILE" ]; then
  CURRENT_CHECKSUM="$(cksum "$LOCKFILE" | awk '{print $1":"$2}')"
fi

SAVED_CHECKSUM=""

if [ -f "$CHECKSUM_FILE" ]; then
  SAVED_CHECKSUM="$(cat "$CHECKSUM_FILE")"
fi

if [ ! -d node_modules/.bin ] || [ "$CURRENT_CHECKSUM" != "$SAVED_CHECKSUM" ]; then
  echo "Installing frontend dependencies..."
  npm ci
  printf '%s' "$CURRENT_CHECKSUM" > "$CHECKSUM_FILE"
else
  echo "Frontend dependencies already installed."
fi

echo "Starting Vite development server..."
exec npm run dev -- --host 0.0.0.0 --port 5173
