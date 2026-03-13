#!/bin/sh
set -eu

PORT_VALUE="${PORT:-10000}"

# Align Apache to Render port
sed -ri "s/^Listen\s+[0-9]+/Listen ${PORT_VALUE}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT_VALUE}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
