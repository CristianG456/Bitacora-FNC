<?php

namespace App\Services;

use App\Contracts\BackupStorageInterface;
use Illuminate\Support\Facades\File;

class LocalBackupStorageService implements BackupStorageInterface
{
    public function upload(string $localFilePath, string $destinationPath): bool
    {
        // En local, el archivo ya está en el destino o solo se mueve
        $destination = storage_path('app/backups/' . $destinationPath);
        
        if ($localFilePath !== $destination) {
            return File::copy($localFilePath, $destination);
        }
        
        return true;
    }

    public function download(string $remoteFilePath, string $localDestinationPath): bool
    {
        $source = storage_path('app/backups/' . $remoteFilePath);
        if (!File::exists($source)) {
            return false;
        }
        return File::copy($source, $localDestinationPath);
    }

    public function delete(string $remoteFilePath): bool
    {
        $file = storage_path('app/backups/' . $remoteFilePath);
        if (File::exists($file)) {
            return File::delete($file);
        }
        return true;
    }

    public function exists(string $remoteFilePath): bool
    {
        return File::exists(storage_path('app/backups/' . $remoteFilePath));
    }

    public function temporaryUrl(string $remoteFilePath): string
    {
        // En local no hay temporary URL, retorna una URL directa o path
        return url('/respaldos/descargar/' . basename($remoteFilePath));
    }
}
