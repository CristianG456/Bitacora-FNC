<?php

namespace App\Console\Commands;

use App\Services\AnsService;
use Illuminate\Console\Command;

class VerificarAns extends Command
{
    protected $signature = 'ans:verificar';

    protected $description = 'Genera alertas y bitácora para los casos con seguimiento ANS';

    public function handle(AnsService $ansService): int
    {
        $eventos = $ansService->procesarAlertas();
        $this->info("Seguimiento ANS procesado. Eventos nuevos: {$eventos}.");

        return self::SUCCESS;
    }
}
