#!/bin/sh
# Ejecutar migraciones
php artisan migrate --force

# Crear administrador a partir de variables de entorno
php artisan app:create-admin

# Asegurar que la estructura de carpetas exista (crucial al usar Bind Mounts vacíos)
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Ajustar permisos para que el servidor web (www-data) pueda escribir
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Iniciar el proceso principal (php-fpm)
exec php-fpm
