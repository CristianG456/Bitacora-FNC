<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupEncryptionService
{
    /**
     * Comprime y opcionalmente protege con AES-256 un dump SQL.
     */
    public function compressAndEncrypt(string $sourcePath, string $zipPath, ?string $password = null): bool
    {
        if (! File::isFile($sourcePath) || ! is_readable($sourcePath) || File::size($sourcePath) <= 0) {
            throw new RuntimeException('El dump SQL no existe, no es legible o está vacío.');
        }

        $entryName = basename($sourcePath);
        $archive = $this->createArchive();
        $created = false;

        try {
            if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el archivo ZIP.');
            }

            if ($archive->addFile($sourcePath, $entryName) !== true) {
                throw new RuntimeException('No fue posible agregar el dump SQL al ZIP.');
            }

            if (filled($password)) {
                if ($archive->setPassword($password) !== true
                    || $archive->setEncryptionName($entryName, ZipArchive::EM_AES_256) !== true) {
                    throw new RuntimeException('No fue posible proteger el archivo ZIP.');
                }
            }

            if ($archive->close() !== true) {
                throw new RuntimeException('No fue posible finalizar el archivo ZIP.');
            }

            $created = true;
            $this->validateArchive($zipPath, $entryName, $password);

            return true;
        } catch (Throwable $e) {
            if (! $created) {
                try {
                    $archive->close();
                } catch (Throwable) {
                    // El archivo parcial se elimina debajo.
                }
            }

            File::delete($zipPath);

            throw $e;
        }
    }

    public function validateArchive(string $zipPath, string $expectedEntry, ?string $password = null): void
    {
        if (! File::isFile($zipPath) || File::size($zipPath) <= 0) {
            throw new RuntimeException('El archivo ZIP generado no es válido.');
        }

        $archive = $this->createArchive();

        try {
            if ($archive->open($zipPath, ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('El archivo ZIP no puede volver a abrirse.');
            }

            if (filled($password) && $archive->setPassword($password) !== true) {
                throw new RuntimeException('No fue posible validar la contraseña del ZIP.');
            }

            $index = $archive->locateName($expectedEntry, ZipArchive::FL_NOCASE);
            if ($index === false) {
                throw new RuntimeException('El ZIP no contiene el dump SQL esperado.');
            }

            $stat = $archive->statIndex($index);
            if (! is_array($stat) || ($stat['size'] ?? 0) <= 0) {
                throw new RuntimeException('El dump SQL contenido en el ZIP está vacío.');
            }

            $stream = $archive->getStream($expectedEntry);
            if (! is_resource($stream)) {
                throw new RuntimeException('El dump SQL del ZIP no puede leerse.');
            }

            try {
                $firstByte = fread($stream, 1);
                if ($firstByte === false || $firstByte === '') {
                    throw new RuntimeException('El dump SQL del ZIP no contiene datos legibles.');
                }
            } finally {
                fclose($stream);
            }
        } finally {
            try {
                $archive->close();
            } catch (Throwable) {
                // La validación principal ya determinó el resultado.
            }
        }
    }

    protected function createArchive(): ZipArchive
    {
        return new ZipArchive;
    }
}
