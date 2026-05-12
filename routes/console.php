<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Support\Facades\Log;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Configuración de Respaldo Automático
try {
    if (Schema::hasTable('backup_settings')) {
        $config = ConfiguracionRespaldo::first();

        if ($config && $config->is_active) {
            $event = Schedule::command('backup:run')->at($config->backup_time);

            switch ($config->backup_frequency) {
                case 'diario':
                    $event->daily();
                    break;
                case 'semanal':
                    $event->weekly();
                    break;
                case 'mensual':
                    $event->monthly();
                    break;
            }
        }
    }
} catch (\Exception $e) {
    Log::error('Error al cargar la configuración de respaldos en el scheduler: ' . $e->getMessage());
}
