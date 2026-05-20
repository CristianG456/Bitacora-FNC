#!/bin/sh
# Ejecutar migraciones
php artisan migrate --force

# Crear administrador a partir de variables de entorno
php artisan app:create-admin

# Iniciar el proceso principal (php-fpm)
exec php-fpm
