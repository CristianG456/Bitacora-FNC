#!/bin/sh
# Ejecutar migraciones
php artisan migrate --force

# Crear administrador a partir de variables de entorno
php artisan app:create-admin

# Ajustar permisos para que el servidor web (www-data) pueda escribir
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# Iniciar el proceso principal (php-fpm)
exec php-fpm
