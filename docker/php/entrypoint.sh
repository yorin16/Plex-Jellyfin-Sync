#!/bin/sh
set -e

cd /var/www/html

echo "Waiting for MySQL to be ready..."
until php -r "
\$url = parse_url(getenv('DATABASE_URL'));
try {
    new PDO('mysql:host='.\$url['host'].';port='.(\$url['port'] ?? 3306), \$url['user'], \$url['pass']);
    exit(0);
} catch (Exception \$e) {
    exit(1);
}
" > /dev/null 2>&1; do
    sleep 3
done
echo "MySQL ready."

php bin/console cache:clear --no-warmup
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

exec "$@"
