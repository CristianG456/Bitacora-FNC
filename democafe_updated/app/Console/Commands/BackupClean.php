<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupClean extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:clean';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Elimina los respaldos antiguos según la configuración de retención.';

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

            $this->info('Iniciando limpieza de respaldos antiguos...');
            
            $backupService->cleanOldBackups($config);
            
            $this->info('Limpieza finalizada con éxito.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('Ocurrió un error al limpiar los respaldos: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
