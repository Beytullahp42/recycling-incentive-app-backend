#!/bin/sh
set -e

cd /var/www/html

if [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist --no-progress
fi

if [ -z "${APP_KEY:-}" ]; then
    export APP_KEY="$(php artisan key:generate --show --no-interaction)"
    echo "Generated an ephemeral APP_KEY for this container. Set BACKEND_APP_KEY for a stable key."
fi

if [ "${WAIT_FOR_DB:-true}" = "true" ] && [ "${DB_CONNECTION:-pgsql}" = "pgsql" ]; then
    php <<'PHP'
<?php
$host = getenv('DB_HOST') ?: 'database';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: 'recycling_incentive_backend';
$username = getenv('DB_USERNAME') ?: 'recycling_user';
$password = getenv('DB_PASSWORD') ?: '';
$dsn = sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database);

for ($attempt = 1; $attempt <= 60; $attempt++) {
    try {
        new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 2,
        ]);
        exit(0);
    } catch (Throwable $exception) {
        fwrite(STDERR, sprintf("Waiting for PostgreSQL at %s:%s (%d/60)...\n", $host, $port, $attempt));
        sleep(1);
    }
}

fwrite(STDERR, "PostgreSQL did not become available in time.\n");
exit(1);
PHP
fi

php artisan storage:link --quiet || true

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

if [ "${RUN_SEEDERS:-false}" = "true" ]; then
    php artisan db:seed --force
fi

exec "$@"
