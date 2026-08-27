<?php

return [
    'timezone' => env('APP_TIMEZONE', 'America/Bogota'),
    // 24 horas: evita que un respaldo largo pierda silenciosamente la exclusividad,
    // pero permite recuperación automática tras una caída del proceso.
    'lock_seconds' => (int) env('BACKUP_LOCK_SECONDS', 86400),
    'overlap_minutes' => (int) env('BACKUP_OVERLAP_MINUTES', 1440),
];
