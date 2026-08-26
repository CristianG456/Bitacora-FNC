<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionRespaldo;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
    public function handle(BackupService $backupService)
    {
        try {
            $config = ConfiguracionRespaldo::first();

            if (! $config) {
                $this->error('No se ha configurado el módulo de respaldos.');

                return Command::FAILURE;
            }

            $this->info('Iniciando limpieza de respaldos antiguos...');

            $backupService->cleanOldBackups($config);

            $this->info('Limpieza finalizada con éxito.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error('No fue posible completar la retención. Consulta los logs del módulo.');
            Log::error('El comando backup:clean falló.', [
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }
}
