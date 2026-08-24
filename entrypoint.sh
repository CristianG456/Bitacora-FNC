#!/bin/sh
set -e

echo "=== Iniciando contenedor de la aplicación ==="

# Si se pasa un comando explícito (CMD), ejecutarlo directamente.
# Esto permite servicios como reverb (php artisan reverb:start) sin forzar
# php-fpm. El servicio 'app' no pasa CMD, por lo que sigue el flujo normal.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

# ── Migraciones ────────────────────────────────────────────────────────────
# Siempre ejecutamos migrate --force. En una BD nueva NO existe la tabla de
# migraciones y `migrate:status` falla contando 0 pendientes, lo que antes
# evitaba migrar y provocaba el error "Base table or view not found: 1146".
# `migrate --force` es idempotente: omite las migraciones ya aplicadas.
echo "Verificando/aplicando migraciones..."
php artisan migrate --force

# Crear administrador a partir de variables de entorno (idempotente)
php artisan app:create-admin

# Restaurar la carpeta public si el volumen la dejó vacía
if [ -z "$(ls -A /var/www/public)" ]; then
    echo "Carpeta public vacía detectada. Restaurando archivos desde respaldo..."
    cp -r /tmp/public_stash/* /var/www/public/
fi

# Asegurar que la estructura de carpetas exista (crucial al usar volúmenes)
mkdir -p /var/www/storage/framework/cache/data
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/logs
mkdir -p /var/www/bootstrap/cache

# Ajustar permisos para que el servidor web (www-data) pueda escribir
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

echo "=== Iniciando php-fpm ==="
# Iniciar el proceso principal (php-fpm)
exec php-fpm
