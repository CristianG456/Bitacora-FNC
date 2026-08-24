<?php

namespace App\Services;

use ZipArchive;

class BackupEncryptionService
{
    /**
     * Comprime y opcionalmente protege con contraseña un archivo.
     *
     * @param string $sourcePath Ruta absoluta del archivo fuente (ej. dump .sql)
     * @param string $zipPath Ruta absoluta donde guardar el archivo .zip
     * @param string|null $password Contraseña para encriptar
     * @return bool
     * @throws \Exception
     */
    public function compressAndEncrypt(string $sourcePath, string $zipPath, ?string $password = null): bool
    {
        if (!file_exists($sourcePath)) {
            throw new \Exception("El archivo fuente no existe: {$sourcePath}");
        }

        $zip = new ZipArchive();
        
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("No se pudo crear el archivo ZIP: {$zipPath}");
        }

        $filename = basename($sourcePath);
        $zip->addFile($sourcePath, $filename);

        if (!empty($password)) {
            // Requiere PHP con soporte de encriptación Zip (libzip)
            if (!$zip->setPassword($password)) {
                $zip->close();
                throw new \Exception("No se pudo establecer la contraseña para el archivo ZIP.");
            }
            if (!$zip->setEncryptionName($filename, ZipArchive::EM_AES_256)) {
                $zip->close();
                throw new \Exception("No se pudo establecer el algoritmo de encriptación para el archivo ZIP.");
            }
        }

        $result = $zip->close();

        if (!$result) {
            throw new \Exception("Error al guardar el archivo ZIP comprimido.");
        }

        return true;
    }
}
