<?php

namespace App\Services;

use RuntimeException;
use Throwable;

class ContainerBootstrapService
{
    /**
     * Execute the database and VAPID bootstrap under one persistent exclusive lock.
     *
     * @param  callable(): int  $migrate
     * @param  callable(): int  $initializeVapid
     */
    public function run(string $lockPath, callable $migrate, callable $initializeVapid): int
    {
        $directory = dirname($lockPath);
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('No fue posible crear el directorio privado del bootstrap.');
        }

        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new RuntimeException('No fue posible abrir el lock persistente del bootstrap.');
        }

        @chmod($lockPath, 0600);
        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('No fue posible adquirir el lock persistente del bootstrap.');
            }

            $migrationResult = $migrate();
            if ($migrationResult !== 0) {
                return $migrationResult;
            }

            return $initializeVapid();
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
