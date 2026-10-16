#!/bin/sh
set -e

# Cloud Run inyecta el puerto real en $PORT (no siempre 8080, aunque sea lo usual).
# Apache necesita que se lo configuremos en runtime, no en build time.
PORT="${PORT:-8080}"

sed -ri "s/^Listen 80$/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/:80>/:${PORT}>/g" /etc/apache2/sites-available/*.conf

exec apache2-foreground
