#!/bin/sh
mkdir -p /var/www/html/temp /var/www/html/log
chmod -R 777 /var/www/html/temp /var/www/html/log
exec "$@"
