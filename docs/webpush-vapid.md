# Identidad VAPID persistente

El sistema admite dos fuentes, en este orden:

1. `VAPID_PUBLIC_KEY` y `VAPID_PRIVATE_KEY` completos en secretos o variables del entorno.
2. `storage/app/private/webpush-vapid.json`, ubicado por defecto en el volumen persistente de `storage`.

Una configuración parcial del entorno nunca se combina con el archivo. En ese caso la inicialización falla de forma segura.

## Inicialización por entorno

El comando es explícito: no se ejecuta durante requests, arranques ni recreaciones del contenedor.

```bash
php artisan webpush:vapid:init --subject=mailto:soporte@dominio.example
```

Comportamiento:

- reutiliza un par completo suministrado por el entorno;
- verifica criptográficamente que la clave pública y la privada pertenecen al mismo par;
- reutiliza la identidad privada persistida cuando ya existe;
- genera un único par solamente cuando no existe ninguna identidad y `push_subscriptions` está vacía;
- se niega a reemplazar archivos inválidos, configuraciones parciales o identidades perdidas con suscripciones existentes;
- nunca imprime la clave pública ni la privada.

Después de la primera inicialización debe limpiarse la configuración cacheada:

```bash
php artisan optimize:clear
```

Si el despliegue usa `config:cache`, puede reconstruirse después según el procedimiento normal del servidor.

## Docker de este proyecto

`app` y `scheduler` comparten `bitacora_storage` en `/var/www/storage`, así que ambos leen el mismo archivo aunque se recreen con la misma imagen. No existe un servicio worker de cola en el Compose actual porque `QUEUE_CONNECTION=sync`. Si posteriormente se agrega uno, también debe montar el mismo volumen de solo backend.

Ejecutar la inicialización como el usuario de PHP evita crear un secreto que PHP-FPM no pueda leer:

```bash
docker compose exec --user www-data app php artisan webpush:vapid:init --subject=mailto:soporte@dominio.example
docker compose exec app php artisan optimize:clear
```

No se debe ejecutar el comando hasta que la migración de `push_subscriptions` esté aplicada. El archivo VAPID debe incluirse en el respaldo seguro del volumen, nunca en Git, la imagen, `public/`, JavaScript o logs.

## Despliegue en producción

Antes de desplegar esta estrategia:

1. comprobar si producción ya tiene un par completo en sus secretos; si existe, conservarlo;
2. comprobar si existen suscripciones; si existen pero el par anterior se perdió, detenerse y no generar otro automáticamente;
3. construir y desplegar la imagen que contiene esta versión, sin incluir secretos y conservando el volumen `bitacora_storage`;
4. ejecutar el comando una sola vez como `www-data`;
5. limpiar o reconstruir la caché de configuración;
6. confirmar, sin imprimir valores, que `config('webpush.vapid.public_key')`, `private_key` y `subject` están presentes;
7. verificar que el frontend contiene únicamente la pública y que la suscripción se registra normalmente.

La pérdida del volumen sin un respaldo de este archivo equivale a perder la identidad VAPID del entorno.
