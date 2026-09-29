<?php

namespace App\Console\Commands;

use App\Services\ContainerBootstrapService;
use Illuminate\Console\Command;

class BootstrapContainer extends Command
{
    protected $signature = 'app:container-bootstrap';

    protected $description = 'Ejecuta migraciones e inicializa VAPID una sola vez bajo un lock persistente';

    public function handle(ContainerBootstrapService $bootstrap): int
    {
        $this->components->info('Esperando el lock de bootstrap del contenedor...');

        $result = $bootstrap->run(
            storage_path('app/private/container-bootstrap.lock'),
            fn (): int => $this->call('migrate', ['--force' => true, '--no-interaction' => true]),
            fn (): int => $this->call('webpush:vapid:init', ['--no-interaction' => true]),
        );

        if ($result !== self::SUCCESS) {
            $this->components->error('El bootstrap se detuvo sin iniciar la aplicación.');

            return $result;
        }

        $this->components->info('Bootstrap de migraciones y VAPID completado.');

        return self::SUCCESS;
    }
}
