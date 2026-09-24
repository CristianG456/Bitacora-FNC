<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;
use ZipArchive;

class BackupEncryptionService
{
    private const DATABASE_ENTRY = 'database.sql';

    private const CHECKSUM_ENTRY = 'checksum.sha256';

    private const METADATA_ENTRY = 'metadata.json';

    /**
     * Comprime y opcionalmente protege con AES-256 un dump SQL.
     */
    public function compressAndEncrypt(string $sourcePath, string $zipPath, ?string $password = null): bool
    {
        if (! File::isFile($sourcePath) || ! is_readable($sourcePath) || File::size($sourcePath) <= 0) {
            throw new RuntimeException('El dump SQL no existe, no es legible o está vacío.');
        }

        $entryName = self::DATABASE_ENTRY;
        $sqlChecksum = hash_file('sha256', $sourcePath);
        if (! is_string($sqlChecksum) || strlen($sqlChecksum) !== 64) {
            throw new RuntimeException('No fue posible calcular la integridad del dump SQL.');
        }
        $checksumContents = $sqlChecksum.'  '.$entryName."\n";
        $metadataContents = json_encode([
            'format_version' => 1,
            'created_at_utc' => gmdate(DATE_ATOM),
            'database_file' => $entryName,
            'checksum_file' => self::CHECKSUM_ENTRY,
            'checksum_algorithm' => 'sha256',
            'database_sha256' => $sqlChecksum,
            'database_size' => File::size($sourcePath),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $archive = $this->createArchive();
        $created = false;

        try {
            if ($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('No fue posible crear el archivo ZIP.');
            }

            if ($archive->addFile($sourcePath, $entryName) !== true) {
                throw new RuntimeException('No fue posible agregar el dump SQL al ZIP.');
            }
            if ($archive->addFromString(self::CHECKSUM_ENTRY, $checksumContents) !== true
                || $archive->addFromString(self::METADATA_ENTRY, $metadataContents) !== true) {
                throw new RuntimeException('No fue posible agregar la integridad y metadata al ZIP.');
            }

            if (filled($password)) {
                if ($archive->setPassword($password) !== true) {
                    throw new RuntimeException('No fue posible proteger el archivo ZIP.');
                }
                foreach ([self::DATABASE_ENTRY, self::CHECKSUM_ENTRY, self::METADATA_ENTRY] as $protectedEntry) {
                    if ($archive->setEncryptionName($protectedEntry, ZipArchive::EM_AES_256) !== true) {
                        throw new RuntimeException('No fue posible proteger el archivo ZIP.');
                    }
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
                $hashContext = hash_init('sha256');
                $hasContents = false;
                while (! feof($stream)) {
                    $chunk = fread($stream, 8192);
                    if ($chunk === false) {
                        throw new RuntimeException('El dump SQL del ZIP no puede leerse.');
                    }
                    if ($chunk !== '') {
                        $hasContents = true;
                        hash_update($hashContext, $chunk);
                    }
                }
                if (! $hasContents) {
                    throw new RuntimeException('El dump SQL del ZIP no contiene datos legibles.');
                }
                $databaseChecksum = hash_final($hashContext);
            } finally {
                fclose($stream);
            }

            $checksumContents = $archive->getFromName(self::CHECKSUM_ENTRY);
            if (! is_string($checksumContents)
                || ! preg_match('/\A([a-f0-9]{64})\s{2}database\.sql\s*\z/i', $checksumContents, $matches)
                || ! hash_equals($databaseChecksum, strtolower($matches[1]))) {
                throw new RuntimeException('El checksum interno del respaldo no es valido.');
            }

            $metadataContents = $archive->getFromName(self::METADATA_ENTRY);
            if (! is_string($metadataContents)) {
                throw new RuntimeException('El ZIP no contiene metadata valida.');
            }
            $metadata = json_decode($metadataContents, true, 512, JSON_THROW_ON_ERROR);
            if (($metadata['format_version'] ?? null) !== 1
                || ($metadata['database_file'] ?? null) !== self::DATABASE_ENTRY
                || ($metadata['checksum_file'] ?? null) !== self::CHECKSUM_ENTRY
                || ($metadata['checksum_algorithm'] ?? null) !== 'sha256'
                || ! hash_equals($databaseChecksum, (string) ($metadata['database_sha256'] ?? ''))
                || (int) ($metadata['database_size'] ?? 0) !== (int) $stat['size']) {
                throw new RuntimeException('La metadata interna del respaldo no coincide con el dump.');
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
