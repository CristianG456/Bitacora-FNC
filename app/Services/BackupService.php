<?php

namespace App\Services;

use App\Models\ConfiguracionRespaldo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class BackupService
{
    protected BackupEncryptionService $encryptionService;
    protected BackupMailService $mailService;

    public function __construct(BackupEncryptionService $encryptionService, BackupMailService $mailService)
    {
        $this->encryptionService = $encryptionService;
        $this->mailService = $mailService;
    }

    /**
     * Ejecuta el proceso de respaldo: Dump -> Compress/Encrypt -> Send Mail -> Cleanup
     */
    public function runBackup(ConfiguracionRespaldo $config)
    {
        Log::info('Iniciando proceso de respaldo de base de datos...');

        $storagePath = storage_path('app/backups');
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
        }

        $timestamp = now()->format('Y_m_d_Hi');
        $sqlFilename = "backup_{$timestamp}.sql";
        $zipFilename = "backup_{$timestamp}.zip";
        
        $sqlPath = $storagePath . '/' . $sqlFilename;
        $zipPath = $storagePath . '/' . $zipFilename;

        try {
            // 1. Generar Dump MySQL
            $this->generateDump($sqlPath);
            Log::info("Dump generado exitosamente en: {$sqlPath}");

            // 2. Comprimir y encriptar
            $this->encryptionService->compressAndEncrypt($sqlPath, $zipPath, $config->backup_password);
            Log::info("Archivo comprimido y protegido en: {$zipPath}");

            // 3. Enviar correo
            $this->mailService->sendBackup($config, $zipPath);
            Log::info("Respaldo enviado por correo correctamente.");

            // Integración futura R2
            // $this->uploadToR2($zipPath);

        } catch (\Exception $e) {
            Log::error('Error durante el proceso de respaldo: ' . $e->getMessage());
            throw $e;
        } finally {
            // Limpieza de archivos temporales
            if (File::exists($sqlPath)) {
                File::delete($sqlPath);
            }
            if (File::exists($zipPath)) {
                File::delete($zipPath);
            }
            Log::info('Limpieza de archivos temporales completada.');
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

        // Usamos mysqldump
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
