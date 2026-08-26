<?php

return [
    'timezone' => env('APP_TIMEZONE', 'America/Bogota'),
    'lock_seconds' => (int) env('BACKUP_LOCK_SECONDS', 3600),
    'overlap_minutes' => (int) env('BACKUP_OVERLAP_MINUTES', 120),
];
