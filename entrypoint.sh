#!/bin/sh
set -eu

echo "=== Iniciando contenedor de la aplicacion ==="
: "${DB_HOST:?DB_HOST es obligatorio}"
: "${DB_PORT:=3306}"
: "${DB_DATABASE:?DB_DATABASE es obligatorio}"
: "${DB_USERNAME:?DB_USERNAME es obligatorio}"
: "${DB_PASSWORD:?DB_PASSWORD es obligatorio}"
: "${ADMIN_EMAIL:?ADMIN_EMAIL es obligatorio}"
: "${ADMIN_PASSWORD:?ADMIN_PASSWORD es obligatorio}"

echo "Esperando disponibilidad de MySQL..."
until MYSQL_PWD="$DB_PASSWORD" mysqladmin ping -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USERNAME" --silent; do
    sleep 3
done

echo "Aplicando migraciones pendientes..."
php artisan migrate --force

table_count() {
    MYSQL_PWD="$DB_PASSWORD" mysql -h "$DB_HOST" -P "$DB_PORT" \
        -u "$DB_USERNAME" "$DB_DATABASE" --batch --skip-column-names \
        -e "SELECT COUNT(*) FROM $1;"
}

# Estos seeders usan INSERT: solo se ejecutan para tablas completamente vacias.
if [ "$(table_count tipos_proceso)" -eq 0 ]; then
    php artisan db:seed --class=TipoProcesoSeeder --force
fi
if [ "$(table_count subtipos_proceso)" -eq 0 ]; then
    php artisan db:seed --class=SubtipoProcesoSeeder --force
fi
# RolesSeeder usa updateOrInsert: completa faltantes sin duplicar ni borrar.
php artisan db:seed --class=RolesSeeder --force
# No reemplaza un administrador ya existente.
php artisan app:create-admin

mkdir -p /var/www/storage/framework/cache/data \
    /var/www/storage/framework/sessions /var/www/storage/framework/views \
    /var/www/storage/logs /var/www/bootstrap/cache
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "=== Iniciando php-fpm ==="
exec php-fpm
