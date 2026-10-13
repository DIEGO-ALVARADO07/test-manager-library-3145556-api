#!/bin/sh
set -e

# Apply the schema (owned by the migrations, ADR-001) and create the initial admin.
until php artisan migrate --force; do
    echo "Waiting for the database..."
    sleep 2
done
php artisan library:bootstrap-admin

exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
