FROM php:8.2-fpm

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    netcat-openbsd \
 && rm -rf /var/lib/apt/lists/*

# Extensiones PHP necesarias
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# NOTA DE SEGURIDAD: el archivo .env está excluido en .dockerignore y NO se
# copia dentro de la imagen. Las credenciales se inyectan en runtime via
# variables de entorno del host o docker-compose env_file.
COPY . .

# Instalar dependencias sin paquetes de desarrollo para imagen de producción
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Respaldar la carpeta public para evitar que se pierda con el Bind Mount
RUN cp -r public /tmp/public_stash

# Script de inicio
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]