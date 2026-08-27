<?php

namespace App\Services;

use App\Models\BackupHistory;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class BackupService
{
    public function __construct(
        protected BackupEncryptionService $encryptionService,
        protected BackupMailService $mailService,
        protected R2BackupStorageService $r2StorageService,
    ) {}

    protected function getLogger()
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/backups.log'),
        ]);
    }

    /**
     * Ejecuta un respaldo exclusivamente de base de datos.
     */
    public function runBackup(ConfiguracionRespaldo $config, string $type = 'automatico'): void
    {
        $logger = $this->getLogger();
        $lock = Cache::lock('backups:run', config('backup.lock_seconds', 3600));

        if (! $lock->get()) {
            $logger->warning('Se omitió el respaldo porque ya existe otro proceso en ejecución.');
            throw new RuntimeException('Ya existe otro respaldo en ejecución.');
        }

        try {
            $this->executeBackup($config, $type, $logger);
        } finally {
            $lock->release();
        }
    }

    protected function executeBackup(ConfiguracionRespaldo $config, string $type, $logger): void
    {
        $logger->info('Iniciando respaldo exclusivamente de base de datos.', ['type' => $type]);
        $startTime = microtime(true);

        $storagePath = storage_path('app/backups');
        File::ensureDirectoryExists($storagePath, 0755, true);

        $identifier = now()->format('Y_m_d_His').'_'.Str::lower(Str::random(8));
        $sqlFilename = "backup_{$identifier}.sql";
        $zipFilename = "backup_{$identifier}.zip";
        $sqlPath = $storagePath.DIRECTORY_SEPARATOR.$sqlFilename;
        $zipPath = $storagePath.DIRECTORY_SEPARATOR.$zipFilename;

        $history = BackupHistory::create([
            'file_name' => $zipFilename,
            'file_path' => $zipPath,
            'backup_type' => $type,
            'status' => 'fallido',
            'storage_provider' => 'local',
            'sent_to' => is_array($config->recipient_emails)
                ? implode(', ', $config->recipient_emails)
                : $config->recipient_emails,
        ]);
        $zipValidated = false;
        $stage = 'dump';

        try {
            $this->generateDump($sqlPath);
            $logger->info('Dump MySQL completado.', ['backup_history_id' => $history->id]);
            $stage = 'zip';

            $this->encryptionService->compressAndEncrypt(
                $sqlPath,
                $zipPath,
                $config->backup_password,
            );

            if (! File::exists($zipPath) || File::size($zipPath) === 0) {
                throw new RuntimeException('No fue posible verificar el archivo ZIP generado.');
            }

            $fileSize = File::size($zipPath);
            $checksum = hash_file('sha256', $zipPath);
            if (! is_string($checksum) || strlen($checksum) !== 64) {
                throw new RuntimeException('No fue posible calcular la integridad del archivo ZIP.');
            }

            $zipValidated = true;
            $history->update([
                'file_size' => $fileSize,
                'checksum_sha256' => $checksum,
            ]);
            $logger->info('ZIP de respaldo generado y validado.', ['backup_history_id' => $history->id]);

            if ($config->r2_enabled) {
                $stage = 'r2';
                $r2Path = $this->buildR2Path((string) $config->r2_path, $zipFilename);

                if (! $this->r2StorageService->upload($zipPath, $r2Path)) {
                    throw new RuntimeException('No fue posible completar la copia remota requerida.');
                }

                $history->update([
                    'storage_provider' => 'r2',
                    'storage_path' => $r2Path,
                    'r2_uploaded_at' => now(),
                ]);
                $logger->info('Copia R2 verificada.', ['backup_history_id' => $history->id]);
            }

            $stage = 'email';
            $emailWarning = null;
            if ($this->mailService->isEnabled($config)) {
                try {
                    $recipientCount = $this->mailService->sendBackup($config, $zipPath);
                    $logger->info('Envío de correo completado.', [
                        'backup_history_id' => $history->id,
                        'recipient_count' => $recipientCount,
                    ]);
                } catch (Throwable $emailError) {
                    $emailWarning = 'El respaldo se creó correctamente, pero el correo no pudo enviarse.';
                    $logger->warning('El respaldo se conservó, pero falló el envío SMTP.', [
                        'backup_history_id' => $history->id,
                        'exception' => $emailError::class,
                    ]);
                }
            }

            $history->update([
                'status' => 'exitoso',
                'file_size' => $fileSize,
                'checksum_sha256' => $checksum,
                'execution_time' => microtime(true) - $startTime,
                'error_message' => $emailWarning,
            ]);

            try {
                $this->cleanOldBackups($config, $logger);
            } catch (Throwable $cleanupError) {
                $logger->warning('El respaldo terminó, pero la retención requiere revisión.', [
                    'backup_history_id' => $history->id,
                    'exception' => $cleanupError::class,
                ]);
            }

            $logger->info('Respaldo completado.', ['backup_history_id' => $history->id]);
        } catch (Throwable $e) {
            $logger->error('Error durante el proceso de respaldo.', [
                'backup_history_id' => $history->id,
                'stage' => $stage,
                'exception' => $e::class,
            ]);

            $failureData = [
                'status' => 'fallido',
                'error_message' => match ($stage) {
                    'r2' => 'La copia local es válida, pero no fue posible completar la réplica R2.',
                    'zip' => 'El dump se generó, pero no fue posible producir un ZIP válido.',
                    default => 'No fue posible generar el dump de la base de datos.',
                },
                'execution_time' => microtime(true) - $startTime,
            ];

            if (! $zipValidated) {
                File::delete($zipPath);
                $failureData['file_path'] = null;
            }

            $history->update($failureData);

            throw $e;
        } finally {
            File::delete($sqlPath);
            $logger->info('Limpieza del SQL temporal completada.', [
                'backup_history_id' => $history->id,
            ]);
        }
    }

    public function cleanOldBackups(ConfiguracionRespaldo $config, $logger = null): void
    {
        $logger = $logger ?? $this->getLogger();
        $maxBackups = $config->max_backups ?? 10;
        $retentionDays = $config->retention_days;
        $r2RetentionDays = $config->r2_retention_days;

        $localHistories = BackupHistory::orderBy('created_at', 'desc')
            ->get()
            ->filter(
                fn (BackupHistory $history) => filled($history->file_path)
                    && $history->file_size > 0
                    && filled($history->checksum_sha256)
                    && File::exists($history->file_path)
            )->values();
        $localToRemove = collect();

        if ($maxBackups > 0) {
            $localToRemove = $localToRemove->merge($localHistories->slice($maxBackups));
        }

        if ($retentionDays > 0) {
            $expiredDate = now()->subDays($retentionDays);
            $localToRemove = $localToRemove->merge(
                $localHistories->filter(fn (BackupHistory $history) => $history->created_at->lt($expiredDate))
            );
        }

        foreach ($localToRemove->unique('id') as $history) {
            if (! File::delete($history->file_path) || File::exists($history->file_path)) {
                $logger->warning('No fue posible aplicar la retención local.', [
                    'backup_history_id' => $history->id,
                ]);

                continue;
            }

            $history->file_path = null;
            filled($history->storage_path) ? $history->save() : $history->delete();
            $logger->info('Retención local aplicada.', ['backup_history_id' => $history->id]);
        }

        if ($r2RetentionDays > 0) {
            $remoteExpired = BackupHistory::whereNotNull('storage_path')
                ->whereNotNull('r2_uploaded_at')
                ->where('r2_uploaded_at', '<', now()->subDays($r2RetentionDays))
                ->get();

            foreach ($remoteExpired as $history) {
                if (! $this->r2StorageService->delete($history->storage_path)) {
                    $logger->warning('No fue posible aplicar la retención R2; se conserva el historial.', [
                        'backup_history_id' => $history->id,
                    ]);

                    continue;
                }

                $history->storage_path = null;
                $history->r2_uploaded_at = null;
                $history->storage_provider = 'local';

                if (filled($history->file_path) && File::exists($history->file_path)) {
                    $history->save();
                } else {
                    $history->delete();
                }

                $logger->info('Retención R2 aplicada.', ['backup_history_id' => $history->id]);
            }
        }
    }

    protected function generateDump(string $outputPath): void
    {
        $connectionName = config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (($connection['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException('El respaldo requiere una conexión MySQL.');
        }

        $host = $connection['host'] ?? '127.0.0.1';
        $port = $connection['port'] ?? '3306';
        $database = $connection['database'] ?? '';
        $username = $connection['username'] ?? 'root';
        $password = $connection['password'] ?? '';
        $cnfPath = null;
        $errorPath = null;

        try {
            $cnfPath = tempnam(sys_get_temp_dir(), 'mysql_cnf_');
            $errorPath = tempnam(sys_get_temp_dir(), 'mysql_error_');

            if ($cnfPath === false || $errorPath === false) {
                throw new RuntimeException('No fue posible preparar los archivos temporales del dump.');
            }

            $cnfContent = "[client]\n"
                .'user='.$this->mysqlOptionValue((string) $username)."\n"
                .(filled($password)
                    ? 'password='.$this->mysqlOptionValue((string) $password)."\n"
                    : '');

            if (File::put($cnfPath, $cnfContent) !== strlen($cnfContent)) {
                throw new RuntimeException('No fue posible escribir el archivo temporal de MySQL.');
            }
            if (! $this->secureCredentialFile($cnfPath)) {
                throw new RuntimeException('No fue posible proteger el archivo temporal de MySQL.');
            }

            try {
                $command = sprintf(
                    'mysqldump --defaults-extra-file=%s --single-transaction --quick --skip-lock-tables -h %s -P %s %s > %s 2> %s',
                    escapeshellarg($cnfPath),
                    escapeshellarg((string) $host),
                    escapeshellarg((string) $port),
                    escapeshellarg((string) $database),
                    escapeshellarg($outputPath),
                    escapeshellarg($errorPath),
                );

                exec($command, $ignoredOutput, $resultCode);

                if ($resultCode !== 0 || ! File::exists($outputPath) || File::size($outputPath) === 0) {
                    throw new RuntimeException('mysqldump no pudo completar el respaldo.');
                }
            } finally {
                File::delete([$cnfPath, $errorPath]);
            }
        } finally {
            File::delete(array_values(array_filter(
                [$cnfPath, $errorPath],
                fn ($path) => is_string($path) && $path !== ''
            )));
        }
    }

    protected function secureCredentialFile(string $path): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            return true;
        }

        if (! chmod($path, 0600)) {
            return false;
        }

        clearstatcache(true, $path);

        return (fileperms($path) & 0777) === 0600;
    }

    protected function mysqlOptionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    protected function buildR2Path(string $prefix, string $fileName): string
    {
        $normalized = trim(str_replace('\\', '/', $prefix), '/');
        if ($normalized !== '' && collect(explode('/', $normalized))->contains(
            fn (string $segment) => $segment === '' || $segment === '.' || $segment === '..'
        )) {
            throw new RuntimeException('La ruta configurada para R2 no es válida.');
        }

        return ($normalized !== '' ? $normalized.'/' : '').$fileName;
    }
}
