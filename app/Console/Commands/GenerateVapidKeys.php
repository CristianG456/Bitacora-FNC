<?php

namespace App\Console\Commands;

use App\Support\VapidKeyGenerator;
use App\Support\VapidKeyStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid:init
                            {--subject= : URL o mailto: institucional usado como VAPID_SUBJECT}';

    protected $description = 'Inicializa una identidad VAPID persistente una sola vez sin mostrar la clave privada';

    public function handle(VapidKeyStore $store, VapidKeyGenerator $generator): int
    {
        $source = config('webpush.source');
        $configured = config('webpush.vapid');
        $path = config('webpush.key_file');

        if (! is_string($path) || $path === '') {
            $this->error('VAPID_KEY_FILE no tiene una ruta válida.');

            return self::FAILURE;
        }
        if ($source === 'environment_incomplete') {
            $this->error('La configuración de entorno contiene solo una parte del par VAPID. No se generó ni reemplazó ninguna clave.');

            return self::FAILURE;
        }
        if ($source === 'environment') {
            try {
                $store->validate($configured);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            return $this->reportReused();
        }
        if (is_file($path)) {
            try {
                $store->load($path);
            } catch (Throwable $exception) {
                $this->error($exception->getMessage().' No se reemplazó el archivo existente.');

                return self::FAILURE;
            }

            return $this->reportReused();
        }

        try {
            if (! Schema::hasTable('push_subscriptions')) {
                $this->error('No existe la tabla push_subscriptions; no es seguro inicializar VAPID todavía.');

                return self::FAILURE;
            }
            if (DB::table('push_subscriptions')->exists()) {
                $this->error('Existen suscripciones Push y no hay una identidad VAPID recuperable. No se generó un par nuevo.');

                return self::FAILURE;
            }
        } catch (Throwable) {
            $this->error('No fue posible verificar las suscripciones existentes. No se generó un par nuevo.');

            return self::FAILURE;
        }

        try {
            $subject = $this->option('subject') ?: ($configured['subject'] ?? config('app.url'));
            $keys = $generator->generate();
            $created = $store->create($path, [
                'subject' => $subject,
                'public_key' => $keys['publicKey'],
                'private_key' => $keys['privateKey'],
            ]);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! $created) {
            return $this->reportReused();
        }

        $this->info('PUBLIC generada: SI');
        $this->info('PRIVATE generada: SI');
        $this->info('La identidad quedó en almacenamiento persistente privado. Limpia la caché de configuración antes de usarla.');

        return self::SUCCESS;
    }

    private function reportReused(): int
    {
        $this->info('PUBLIC generada: NO');
        $this->info('PRIVATE generada: NO');
        $this->info('Identidad VAPID existente reutilizada.');

        return self::SUCCESS;
    }
}
