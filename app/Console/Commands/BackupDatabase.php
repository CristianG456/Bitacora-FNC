<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera y envía por correo electrónico un respaldo de la base de datos basado en la configuración guardada.';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\BackupService $backupService)
    {
        try {
            $config = \App\Models\ConfiguracionRespaldo::first();

            if (!$config) {
                $this->error('No se ha configurado el módulo de respaldos.');
                return Command::FAILURE;
            }

            if (!$config->is_active) {
                // Si el scheduler lo llama y está inactivo, simplemente no debería hacerlo (porque no se programa),
                // pero si se llama manualmente por consola, lo permitimos y mandamos un aviso.
                $this->info('Los respaldos están desactivados en la configuración, pero ejecutando respaldo forzado al ser llamado manualmente.');
            }

            $this->info('Iniciando el proceso de respaldo...');
            
            $backupService->runBackup($config);
            
            $this->info('Respaldo finalizado con éxito.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ocurrió un error al generar el respaldo: ' . $e->getMessage());
            \Illuminate\Support\Facades\Log::error('Error en comando backup:database: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
