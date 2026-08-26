<?php

use App\Models\ConfiguracionRespaldo;
use App\Services\BackupScheduleService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Configuración de Respaldo Automático
try {
    if (Schema::hasTable('backup_settings')) {
        $config = ConfiguracionRespaldo::first();

        if ($config && $config->is_active) {
            app(BackupScheduleService::class)->register($config);
        }
    }
} catch (Exception $e) {
    Log::error('Error al cargar la configuración de respaldos en el scheduler: '.$e->getMessage());
}
