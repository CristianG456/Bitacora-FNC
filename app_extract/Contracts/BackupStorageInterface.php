<?php

namespace App\Contracts;

interface BackupStorageInterface
{
    /**
     * Sube el archivo de respaldo al proveedor de almacenamiento.
     *
     * @param string $localFilePath Ruta local del archivo a subir.
     * @param string $destinationPath Ruta de destino en el almacenamiento.
     * @return bool
     */
    public function upload(string $localFilePath, string $destinationPath): bool;

    /**
     * Descarga el archivo de respaldo desde el proveedor de almacenamiento.
     *
     * @param string $remoteFilePath Ruta del archivo en el almacenamiento.
     * @param string $localDestinationPath Ruta local donde se descargará el archivo.
     * @return bool
     */
    public function download(string $remoteFilePath, string $localDestinationPath): bool;

    public function delete(string $remoteFilePath): bool;

    /**
     * Verifica si el archivo existe en el almacenamiento.
     *
     * @param string $remoteFilePath
     * @return bool
     */
    public function exists(string $remoteFilePath): bool;

    /**
     * Obtiene una URL temporal (si el driver lo soporta) o URL pública.
     *
     * @param string $remoteFilePath
     * @return string
     */
    public function temporaryUrl(string $remoteFilePath): string;
}
