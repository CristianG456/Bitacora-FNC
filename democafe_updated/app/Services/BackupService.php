<?php

namespace App\Services;

use App\Models\ConfiguracionRespaldo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class BackupService
{
    protected BackupEncryptionService $encryptionService;
    protected BackupMailService $mailService;
    protected R2BackupStorageService $r2StorageService;

    public function __construct(BackupEncryptionService $encryptionService, BackupMailService $mailService, R2BackupStorageService $r2StorageService)
    {
        $this->encryptionService = $encryptionService;
        $this->mailService = $mailService;
        $this->r2StorageService = $r2StorageService;
    }

    /**
     * Get a custom logger for backups
     */
    protected function getLogger()
    {
        return Log::build([
            'driver' => 'single',
            'path' => storage_path('logs/backups.log'),
        ]);
    }

    /**
     * Ejecuta el proceso de respaldo: Dump -> Compress/Encrypt -> Send Mail -> R2 Upload -> Cleanup
     */
    public function runBackup(ConfiguracionRespaldo $config, string $type = 'automatico')
    {
        $logger = $this->getLogger();
        $logger->info("Iniciando proceso de respaldo de base de datos ({$type})...");
        
        $startTime = microtime(true);

        $storagePath = storage_path('app/backups');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $timestamp = now()->format('Y_m_d_Hi');
        $sqlFilename = "backup_{$timestamp}.sql";
        $zipFilename = "backup_{$timestamp}.zip";
        
        $sqlPath = $storagePath . '/' . $sqlFilename;
        $zipPath = $storagePath . '/' . $zipFilename;

        $history = \App\Models\BackupHistory::create([
            'file_name' => $zipFilename,
            'file_path' => $zipPath,
            'backup_type' => $type,
            'status' => 'fallido',
            'storage_provider' => 'local',
            'sent_to' => is_array($config->recipient_emails) ? implode(', ', $config->recipient_emails) : $config->recipient_emails,
        ]);

        try {
            // 1. Generar Dump MySQL
            $this->generateDump($sqlPath);
            $logger->info("Dump generado exitosamente en: {$sqlPath}");

            // 2. Comprimir y encriptar
            $this->encryptionService->compressAndEncrypt($sqlPath, $zipPath, $config->backup_password);
            $logger->info("Archivo comprimido y protegido en: {$zipPath}");

            // Obtener el tamaño y checksum del ZIP
            $fileSize = filesize($zipPath);
            $checksum = hash_file('sha256', $zipPath);

            // 3. Subir a Cloudflare R2 si está habilitado
            $r2Uploaded = false;
            if ($config->r2_enabled) {
                $logger->info("Iniciando subida a Cloudflare R2...");
                $r2Path = ($config->r2_path ? rtrim($config->r2_path, '/') . '/' : '') . $zipFilename;
                
                if ($this->r2StorageService->upload($zipPath, $r2Path)) {
                    $logger->info("Respaldo subido a R2 exitosamente: {$r2Path}");
                    $r2Uploaded = true;
                    
                    $history->update([
                        'storage_provider' => 'r2',
                        'storage_path' => $r2Path,
                        'r2_uploaded_at' => now(),
                    ]);
                } else {
                    $logger->error("Fallo al subir el respaldo a R2.");
                }
            }

            // 4. Enviar correo (Opcional, se mantiene funcionalidad original)
            if (!empty($config->smtp_host) && !empty($config->sender_email)) {
                $this->mailService->sendBackup($config, $zipPath);
                $logger->info("Respaldo enviado por correo a: {$history->sent_to}");
            }

            // 5. Actualizar historial con éxito
            $history->update([
                'status' => 'exitoso',
                'file_size' => $fileSize,
                'checksum_sha256' => $checksum,
                'execution_time' => microtime(true) - $startTime
            ]);

            // 6. Limpieza de backups antiguos
            $this->cleanOldBackups($config, $logger);

            // Eliminar archivo temporal local si se subió a R2 y así se desea (opcional)
            // Por ahora, lo mantenemos si no se requiere estrictamente borrar la copia local,
            // pero el requerimiento dice: "Eliminar archivo temporal local".
            if ($r2Uploaded) {
                if (File::exists($zipPath)) {
                    File::delete($zipPath);
                    $logger->info('Archivo ZIP temporal local eliminado porque fue subido a R2.');
                }
            }

        } catch (\Exception $e) {
            $logger->error('Error durante el proceso de respaldo: ' . $e->getMessage());
            $history->update([
                'status' => 'fallido',
                'error_message' => $e->getMessage(),
                'execution_time' => microtime(true) - $startTime
            ]);
            throw $e;
        } finally {
            // Limpieza de archivos temporales (.sql)
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }
            $logger->info('Limpieza del archivo SQL temporal completada.');
            $logger->info('Tiempo total de ejecución: ' . (microtime(true) - $startTime) . ' segundos.');
        }
    }

    /**
     * Limpia backups antiguos basados en la configuración
     */
    public function cleanOldBackups(ConfiguracionRespaldo $config, $logger = null)
    {
        $logger = $logger ?? $this->getLogger();
        
        $maxBackups = $config->max_backups ?? 10;
        $retentionDays = $config->retention_days;

        $historiesQuery = \App\Models\BackupHistory::where('status', 'exitoso')->orderBy('created_at', 'desc');
        
        // Mantener solo los últimos $maxBackups
        if ($maxBackups > 0) {
            $oldHistories = $historiesQuery->skip($maxBackups)->take(100)->get();
            
            foreach ($oldHistories as $old) {
                if (File::exists($old->file_path)) {
                    File::delete($old->file_path);
                    $logger->info("Backup antiguo eliminado (límite cantidad): {$old->file_name}");
                }
                $old->delete();
            }
        }

        // Eliminar por días de retención
        if ($retentionDays > 0) {
            $expiredDate = now()->subDays($retentionDays);
            $expiredHistories = \App\Models\BackupHistory::where('status', 'exitoso')
                ->where('created_at', '<', $expiredDate)
                ->get();

            foreach ($expiredHistories as $old) {
                if (File::exists($old->file_path)) {
                    File::delete($old->file_path);
                    $logger->info("Backup antiguo eliminado (límite días): {$old->file_name}");
                }
                $old->delete();
            }
        }
    }

    /**
     * Genera el dump de la base de datos usando mysqldump.
     */
    protected function generateDump(string $outputPath)
    {
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $database = env('DB_DATABASE', 'laravel');
        $username = env('DB_USERNAME', 'root');
        $password = env('DB_PASSWORD', '');

        $passwordOption = !empty($password) ? "-p\"{$password}\"" : "";
        $command = "mysqldump -h {$host} -P {$port} -u {$username} {$passwordOption} {$database} > \"{$outputPath}\"";

        $output = null;
        $resultCode = null;
        
        exec($command . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            $error = implode("\n", $output);
            throw new \Exception("mysqldump falló: {$error}");
        }
    }
}
