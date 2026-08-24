FROM node:22-alpine AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN rm -rf /build/public/build && npm run build

FROM php:8.2-fpm
RUN apt-get update && apt-get install -y --no-install-recommends \
    default-mysql-client git curl nginx unzip \
    libpng-dev libonig-dev libxml2-dev libzip-dev netcat-openbsd \
 && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
 && rm -rf /var/lib/apt/lists/* \
 && rm -f /etc/nginx/sites-enabled/default /etc/nginx/conf.d/default.conf
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
# .env esta excluido por .dockerignore; los secretos se inyectan en runtime.
COPY . .
RUN rm -rf /var/www/public/build
# El build de Vite siempre corresponde al mismo codigo de esta imagen.
COPY --from=frontend /build/public/build /var/www/public/build
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
 && chmod -R 775 /var/www/storage /var/www/bootstrap/cache
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
