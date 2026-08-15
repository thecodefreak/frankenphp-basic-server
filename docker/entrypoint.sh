#!/bin/sh
set -e

mkdir -p "$(dirname "${DB_PATH:-/data/app.sqlite}")" /app/public/storage/images

php /app/bin/migrate.php

cron

exec docker-php-entrypoint "$@"
