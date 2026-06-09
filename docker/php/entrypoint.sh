#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for MySQL to be ready..."
until php bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; do
    sleep 3
done
echo "MySQL ready."

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
