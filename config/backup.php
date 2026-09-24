<?php

return [
    'timezone' => env('APP_TIMEZONE', 'America/Bogota'),
    // 24 horas: evita que un respaldo largo pierda silenciosamente la exclusividad,
    // pero permite recuperación automática tras una caída del proceso.
    'lock_seconds' => (int) env('BACKUP_LOCK_SECONDS', 86400),
    'overlap_minutes' => (int) env('BACKUP_OVERLAP_MINUTES', 1440),
    // Seguro por defecto. Solo el Compose con MySQL en la red interna lo desactiva
    // para aceptar el certificado autofirmado creado por la imagen oficial.
    'mysql_ssl_verify_server_cert' => filter_var(
        env('BACKUP_MYSQL_SSL_VERIFY_SERVER_CERT', true),
        FILTER_VALIDATE_BOOL,
    ),
];
