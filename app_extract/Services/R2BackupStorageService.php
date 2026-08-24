<?php

namespace App\Services;

use App\Contracts\BackupStorageInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Config;
use App\Models\ConfiguracionRespaldo;

class R2BackupStorageService implements BackupStorageInterface
{
    /**
     * Configura el disco R2 en tiempo de ejecución basado en la BD
     */
    protected function configureDisk(ConfiguracionRespaldo $config)
    {
        // En un entorno de producción real, las keys sensibles suelen venir del .env,
        // pero la interfaz pedía soporte desde el panel y el requerimiento era
        // usar el Storage facade y driver s3. Para mantener seguridad y flexibilidad,
        // las llaves maestras están en el .env, y el bucket/path pueden sobreescribirse.
        
        if ($config->r2_bucket) {
            Config::set('filesystems.disks.r2.bucket', $config->r2_bucket);
        }
    }

    public function upload(string $localFilePath, string $destinationPath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (!$config || !$config->r2_enabled) return false;

        $this->configureDisk($config);

        $fileStream = fopen($localFilePath, 'r');
        $result = Storage::disk('r2')->put($destinationPath, $fileStream);
        if (is_resource($fileStream)) {
            fclose($fileStream);
        }

        return $result;
    }

    public function download(string $remoteFilePath, string $localDestinationPath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (!$config) return false;

        $this->configureDisk($config);

        if (!$this->exists($remoteFilePath)) {
            return false;
        }

        $content = Storage::disk('r2')->get($remoteFilePath);
        return file_put_contents($localDestinationPath, $content) !== false;
    }

    public function delete(string $remoteFilePath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (!$config) return false;

        $this->configureDisk($config);

        if ($this->exists($remoteFilePath)) {
            return Storage::disk('r2')->delete($remoteFilePath);
        }
        return true;
    }

    public function exists(string $remoteFilePath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (!$config) return false;

        $this->configureDisk($config);
        
        return Storage::disk('r2')->exists($remoteFilePath);
    }

    public function temporaryUrl(string $remoteFilePath): string
    {
        $config = ConfiguracionRespaldo::first();
        if (!$config) return '';

        $this->configureDisk($config);

        return Storage::disk('r2')->temporaryUrl(
            $remoteFilePath, now()->addMinutes(30)
        );
    }
}
