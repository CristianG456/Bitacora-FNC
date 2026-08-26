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

        try {
            $this->generateDump($sqlPath);
            $logger->info('Dump MySQL completado.', ['backup_history_id' => $history->id]);

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
            $logger->info('ZIP de respaldo generado.', ['backup_history_id' => $history->id]);

            if ($config->r2_enabled) {
                $r2Path = ($config->r2_path ? rtrim($config->r2_path, '/').'/' : '').$zipFilename;

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

            if ($this->mailIsConfigured($config)) {
                $this->mailService->sendBackup($config, $zipPath);
                $logger->info('Envío de correo completado.', [
                    'backup_history_id' => $history->id,
                    'recipient_count' => count($config->recipient_emails),
                ]);
            }

            $history->update([
                'status' => 'exitoso',
                'file_size' => $fileSize,
                'checksum_sha256' => $checksum,
                'execution_time' => microtime(true) - $startTime,
                'error_message' => null,
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
                'exception' => $e::class,
            ]);

            $history->update([
                'status' => 'fallido',
                'error_message' => 'El respaldo no se completó. Consulta los logs técnicos del módulo.',
                'execution_time' => microtime(true) - $startTime,
            ]);

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

        $successful = BackupHistory::where('status', 'exitoso')
            ->orderBy('created_at', 'desc')
            ->get();
        $localHistories = $successful->filter(
            fn (BackupHistory $history) => filled($history->file_path)
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
            $remoteExpired = BackupHistory::where('status', 'exitoso')
                ->whereNotNull('storage_path')
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

        File::put($cnfPath, $cnfContent);
        chmod($cnfPath, 0600);

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
    }

    protected function mysqlOptionValue(string $value): string
    {
        return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
    }

    protected function mailIsConfigured(ConfiguracionRespaldo $config): bool
    {
        return filled($config->smtp_host)
            && filled($config->smtp_port)
            && filled($config->sender_email)
            && is_array($config->recipient_emails)
            && $config->recipient_emails !== [];
    }
}
