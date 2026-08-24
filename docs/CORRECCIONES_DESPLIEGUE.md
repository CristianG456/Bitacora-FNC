# Correcciones de despliegue — Bitacora-FNC

Documentación de los bugs encontrados y corregidos durante el despliegue en
producción (Ubuntu Server `192.168.1.93`, stack Portainer/infra-net), y del
procedimiento de recuperación aplicado.

> Rama: `fix/correcciones-despliegue` (basada en `develop`, sin tocar `main`).

## Estado final verificado

| Contenedor        | Imagen                              | Estado    |
|-------------------|-------------------------------------|-----------|
| `bitacora-app`    | cristian123456/bitacora-fnc:v1.5.18 | ✅ healthy |
| `bitacora-db`     | mysql:8                             | ✅ healthy |
| `bitacora-nginx`  | nginx:alpine                        | ✅ healthy |

App respondiendo en `http://192.168.1.93:8002` → **HTTP 200**
(login "Acceso Institucional - Sistema de Bitácoras").

---

## Bug 1 — El entrypoint no migraba una base de datos nueva (CRÍTICO)

**Síntoma:** el contenedor `bitacora-app` entraba en crash loop y el log mostraba:

```
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'laravel.roles' ...
```

**Causa:** en `entrypoint.sh` las migraciones solo se ejecutaban si
`migrate:status` reportaba migraciones "Pending":

```sh
PENDING=$(php artisan migrate:status 2>/dev/null | grep -c "Pending" || true)
if [ "$PENDING" -gt 0 ]; then
    php artisan migrate --force
fi
```

En una BD fresca la tabla de migraciones no existe, `migrate:status` falla y
`grep -c "Pending"` devuelve `0`, por lo que **nunca se migraba**. Luego
`php artisan app:create-admin` consultaba `roles` (inexistente) y moría.

**Corrección (`entrypoint.sh`):** ejecutar siempre `php artisan migrate --force`,
que es idempotente (omite migraciones ya aplicadas):

```sh
php artisan migrate --force
```

Además se agregó soporte para CMD explícito (`exec "$@"`) para que servicios
como `reverb` puedan ejecutar su propio comando en lugar de forzar `php-fpm`.

---

## Bug 2 — Servicio `reverb` en crash loop (OPCIONAL, deshabilitado)

**Síntoma:** `bitacora-reverb` reiniciaba sin parar (RestartCount creciente).

**Causa doble:**
1. El compose montaba `/var/www/storage` como **`:ro`** y el entrypoint hace
   `chown -R www-data:www-data /var/www/storage`, lo que falla con
   `Read-only file system` y `set -e` mata el contenedor.
2. El `entrypoint.sh` **ignora el `CMD`** (siempre hace `exec php-fpm`), así que
   el comando `php artisan reverb:start` nunca se ejecutaba.

**Corrección (`docker-compose.portainer.yml`):** el servicio `reverb` fue
**eliminado** del stack Portainer (es opcional y no necesario para la app web).
Para re-habilitarlo en el futuro tras corregir el entrypoint (soporte de CMD),
el volumen `storage` debe montarse en modo lectura-escritura.

---

## Bug 3 — Healthcheck de nginx daba "unhealthy" (falso positivo)

**Síntoma:** `bitacora-nginx` aparecía `unhealthy` aunque la app respondía 200.

**Causa:** el healthcheck usaba `wget -q --spider http://localhost/up`.
Dentro del contenedor nginx, `localhost` resuelve a IPv6 (`::1`) y nginx solo
escucha IPv4, por lo que `wget --spider` (petición HEAD) era rechazado.

**Corrección (`docker-compose.portainer.yml`):** cambiado a una petición GET
explícita sobre IPv4:

```yaml
test: ["CMD-SHELL", "wget -q -O /dev/null http://127.0.0.1/up || exit 1"]
```

---

## Procedimiento de recuperación (si el app no arranca en BD nueva)

Si alguna vez se despliega con una BD vacía y el entrypoint viejo, basta con
ejecutar manualmente una sola vez (la imagen ya tiene el código):

```bash
cd /home/jhonathan/bitacora-fnc
docker run --rm --entrypoint sh \
  --network bitacora-fnc_bitacora-net --env-file ./.env \
  cristian123456/bitacora-fnc:v1.5.18 -c \
  "php artisan migrate --force; php artisan app:create-admin; php artisan db:seed --force"
```

Tras esto, `docker compose up -d` levanta el stack sin errores.

---

## Notas de seguridad

- El `.env` de producción usa `APP_ENV=production`, `APP_DEBUG=false` y una
  contraseña de BD y admin definidas (no los valores por defecto).
- MySQL solo expone `127.0.0.1:3310` (no accesible desde fuera del host).
- Pendiente recomendado: exponer vía **Nginx Proxy Manager** + **Cloudflare
  Tunnel** (el stack ya está en la red `infra-net`) para acceso por dominio con
  SSL, en lugar de puerto IP:8002.
