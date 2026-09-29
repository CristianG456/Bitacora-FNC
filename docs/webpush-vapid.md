# Identidad VAPID persistente y bootstrap automÃ¡tico

El sistema admite dos fuentes, en este orden:

1. `VAPID_PUBLIC_KEY` y `VAPID_PRIVATE_KEY` completos en secretos o variables del entorno.
2. `storage/app/private/webpush-vapid.json`, ubicado por defecto en el volumen persistente `bitacora_storage`.

Una configuraciÃ³n parcial del entorno nunca se combina con el archivo. La clave privada no se guarda en Git, la imagen, `public/`, JavaScript ni logs.

## Arranque del contenedor app

El entrypoint de `app` ejecuta automÃ¡ticamente:

1. `php artisan config:clear`;
2. `php artisan app:container-bootstrap`;
3. seeders idempotentes y creaciÃ³n protegida del administrador;
4. `php artisan config:cache`;
5. PHP-FPM.

`app:container-bootstrap` adquiere `storage/app/private/container-bootstrap.lock`, ejecuta `migrate --force` y despuÃ©s `webpush:vapid:init`. El lock estÃ¡ en el volumen persistente, por lo que dos rÃ©plicas de app no pueden migrar o inicializar VAPID simultÃ¡neamente.

`webpush:vapid:init` mantiene ademÃ¡s su propio lock atÃ³mico y:

- reutiliza un par completo suministrado por el entorno;
- verifica criptogrÃ¡ficamente que PUBLIC y PRIVATE pertenecen al mismo par;
- reutiliza la identidad privada persistida cuando ya existe;
- genera un Ãºnico par si no existe ninguna identidad y `push_subscriptions` estÃ¡ vacÃ­a;
- se niega a generar si existen suscripciones pero la identidad anterior se perdiÃ³;
- nunca imprime claves.

El comando continÃºa disponible para diagnÃ³stico, pero un despliegue normal no necesita ejecutarlo manualmente.

## App, scheduler y worker

`app` es el Ãºnico servicio que ejecuta el bootstrap. `scheduler` tiene el entrypoint desactivado, espera a que `app` estÃ© saludable y monta el mismo `bitacora_storage`.

Mientras `QUEUE_CONNECTION=sync` no hace falta un worker. El Compose incluye un worker opcional bajo el perfil `queue`; tambiÃ©n desactiva el entrypoint, espera la salud de `app` y monta el mismo volumen. Cuando se use una cola asÃ­ncrona se activa mediante el procedimiento del entorno, sin permitirle migrar ni generar VAPID.

## Despliegue

El despliegue normal queda limitado a actualizar y recrear los servicios con la nueva imagen:

```bash
docker compose pull
docker compose up -d
```

No deben borrarse `bitacora_storage` ni el volumen de MySQL. El archivo VAPID debe incluirse en el respaldo seguro del volumen. Perder el volumen sin respaldo equivale a perder la identidad asociada a las suscripciones existentes; en ese caso el bootstrap se detendrÃ¡ en lugar de generar un par incompatible.

DespuÃ©s del despliegue se recomienda comprobar, sin imprimir valores, que las tres claves efectivas estÃ¡n presentes, que el Service Worker se registra y que el POST de suscripciÃ³n responde correctamente.
