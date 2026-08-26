<?php

namespace App\Console\Commands;

use App\Models\ConfiguracionRespaldo;
use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class BackupDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'backup:run {--manual}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Genera un respaldo de base de datos local y opcionalmente lo replica a R2/SMTP.';

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

            if (! $config->is_active) {
                // Si el scheduler lo llama y está inactivo, simplemente no debería hacerlo (porque no se programa),
                // pero si se llama manualmente por consola, lo permitimos y mandamos un aviso.
                $this->info('Los respaldos están desactivados en la configuración, pero ejecutando respaldo forzado al ser llamado manualmente.');
            }

            $this->info('Iniciando el proceso de respaldo...');

            $type = $this->option('manual') ? 'manual' : 'automatico';
            $backupService->runBackup($config, $type);

            $this->info('Respaldo finalizado con éxito.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $message = $e->getMessage() === 'Ya existe otro respaldo en ejecución.'
                ? $e->getMessage()
                : 'El respaldo no se completó. Consulta los logs del módulo.';
            $this->error($message);
            Log::error('El comando backup:run falló.', [
                'exception' => $e::class,
            ]);

            return Command::FAILURE;
        }
    }
}
