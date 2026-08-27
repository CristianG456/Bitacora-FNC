<?php

namespace App\Services;

use App\Contracts\BackupStorageInterface;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;

class LocalBackupStorageService implements BackupStorageInterface
{
    public function upload(string $localFilePath, string $destinationPath): bool
    {
        $source = $this->validatedExistingSource($localFilePath);
        $destination = $this->resolveBackupPath($destinationPath);
        File::ensureDirectoryExists(dirname($destination), 0755, true);
        $this->assertDirectoryInsideBase(dirname($destination));

        if ($source !== $destination) {
            return File::copy($source, $destination);
        }

        return true;
    }

    public function download(string $remoteFilePath, string $localDestinationPath): bool
    {
        $source = $this->resolveBackupPath($remoteFilePath);
        $destination = $this->resolveBackupPath($localDestinationPath);

        if (! File::exists($source)) {
            return false;
        }
        $this->assertExistingPathInsideBase($source);

        File::ensureDirectoryExists(dirname($destination), 0755, true);
        $this->assertDirectoryInsideBase(dirname($destination));

        return File::copy($source, $destination);
    }

    public function readStream(string $remoteFilePath): mixed
    {
        $source = $this->resolveBackupPath($remoteFilePath);
        if (File::exists($source)) {
            $this->assertExistingPathInsideBase($source);
        }

        return File::exists($source) ? fopen($source, 'rb') : false;
    }

    public function delete(string $remoteFilePath): bool
    {
        $file = $this->resolveBackupPath($remoteFilePath);
        if (File::exists($file)) {
            $this->assertExistingPathInsideBase($file);
        }

        return ! File::exists($file) || File::delete($file);
    }

    public function exists(string $remoteFilePath): bool
    {
        $path = $this->resolveBackupPath($remoteFilePath);
        if (File::exists($path)) {
            $this->assertExistingPathInsideBase($path);

            return true;
        }

        return false;
    }

    public function temporaryUrl(string $remoteFilePath): string
    {
        $safePath = $this->resolveBackupPath($remoteFilePath);
        if (File::exists($safePath)) {
            $this->assertExistingPathInsideBase($safePath);
        }

        return url('/respaldos/descargar/'.basename($safePath));
    }

    protected function resolveBackupPath(string $relativePath): string
    {
        $normalized = str_replace('\\', '/', trim($relativePath));

        if ($normalized === ''
            || str_contains($normalized, chr(0))
            || str_starts_with($normalized, '/')
            || preg_match('/^[A-Za-z]:\//', $normalized) === 1) {
            throw new InvalidArgumentException('La ruta local del respaldo no es válida.');
        }

        $segments = explode('/', $normalized);
        if (collect($segments)->contains(fn (string $segment) => $segment === '' || $segment === '.' || $segment === '..')) {
            throw new InvalidArgumentException('La ruta local del respaldo no puede salir del directorio permitido.');
        }

        return $this->baseDirectory().DIRECTORY_SEPARATOR.implode(DIRECTORY_SEPARATOR, $segments);
    }

    protected function baseDirectory(): string
    {
        $base = storage_path('app/backups');
        File::ensureDirectoryExists($base, 0755, true);

        return $base;
    }

    protected function validatedExistingSource(string $path): string
    {
        $realPath = realpath($path);
        if ($realPath === false || ! File::isFile($realPath)) {
            throw new InvalidArgumentException('El archivo fuente del respaldo no es válido.');
        }

        $this->assertExistingPathInsideBase($realPath);

        return $realPath;
    }

    protected function assertExistingPathInsideBase(string $path): void
    {
        $realPath = realpath($path);
        $realBase = realpath($this->baseDirectory());
        if ($realPath === false || $realBase === false
            || ($realPath !== $realBase && ! str_starts_with($realPath, $realBase.DIRECTORY_SEPARATOR))) {
            throw new InvalidArgumentException('La ruta resuelta está fuera del directorio de respaldos.');
        }
    }

    protected function assertDirectoryInsideBase(string $directory): void
    {
        $realDirectory = realpath($directory);
        $realBase = realpath($this->baseDirectory());
        if ($realDirectory === false || $realBase === false
            || ($realDirectory !== $realBase && ! str_starts_with($realDirectory, $realBase.DIRECTORY_SEPARATOR))) {
            throw new InvalidArgumentException('El destino resuelto está fuera del directorio de respaldos.');
        }
    }
}
