<?php

namespace App\Services;

use App\Contracts\BackupStorageInterface;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class R2BackupStorageService implements BackupStorageInterface
{
    protected ?string $resolvedDiskSignature = null;

    protected function disk(ConfiguracionRespaldo $config): Filesystem
    {
        if ($config->r2_bucket) {
            Config::set('filesystems.disks.r2.bucket', $config->r2_bucket);
        }

        $signature = hash('sha256', json_encode([
            config('filesystems.disks.r2.key'),
            config('filesystems.disks.r2.secret'),
            config('filesystems.disks.r2.bucket'),
            config('filesystems.disks.r2.endpoint'),
            config('filesystems.disks.r2.region'),
            config('filesystems.disks.r2.use_path_style_endpoint'),
        ], JSON_THROW_ON_ERROR));

        if ($this->resolvedDiskSignature !== null && $this->resolvedDiskSignature !== $signature) {
            Storage::forgetDisk('r2');
        }

        $this->resolvedDiskSignature = $signature;

        return $this->resolveDisk();
    }

    protected function resolveDisk(): Filesystem
    {
        return Storage::disk('r2');
    }

    public function upload(string $localFilePath, string $destinationPath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config?->r2_enabled || ! is_readable($localFilePath)) {
            return false;
        }

        $stream = null;
        $disk = null;
        $putAttempted = false;
        $verified = false;

        try {
            $stream = fopen($localFilePath, 'rb');
            if (! is_resource($stream)) {
                return false;
            }

            $disk = $this->disk($config);
            if ($disk->exists($destinationPath)) {
                $this->logAmbiguousUpload('destination_already_exists', $destinationPath);

                return false;
            }

            $putAttempted = true;
            if ($disk->put($destinationPath, $stream) !== true) {
                return false;
            }

            $verified = $disk->exists($destinationPath);

            return $verified;
        } catch (Throwable $e) {
            $this->logFailure('upload', $e);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }

            if ($putAttempted && ! $verified && $disk instanceof Filesystem) {
                $this->compensateAmbiguousUpload($disk, $destinationPath);
            }
        }
    }

    protected function compensateAmbiguousUpload(Filesystem $disk, string $destinationPath): bool
    {
        try {
            $deleteResult = $disk->delete($destinationPath);
            $absent = ! $disk->exists($destinationPath);

            if (! $absent) {
                $this->logAmbiguousUpload('compensating_delete_unconfirmed', $destinationPath);
            } elseif ($deleteResult !== true) {
                Log::notice('La limpieza compensatoria R2 confirmó que la clave está ausente.', [
                    'key_hash' => hash('sha256', $destinationPath),
                ]);
            }

            return $absent;
        } catch (Throwable $e) {
            Log::error('No se pudo confirmar la limpieza compensatoria R2.', [
                'operation' => 'compensating_delete',
                'key_hash' => hash('sha256', $destinationPath),
                'exception' => $e::class,
            ]);

            return false;
        }
    }

    public function download(string $remoteFilePath, string $localDestinationPath): bool
    {
        $input = $this->readStream($remoteFilePath);
        if (! is_resource($input)) {
            return false;
        }

        $output = null;
        $completed = false;

        try {
            $output = fopen($localDestinationPath, 'wb');
            if (! is_resource($output)) {
                return false;
            }

            $completed = stream_copy_to_stream($input, $output) !== false;

            return $completed;
        } catch (Throwable $e) {
            $this->logFailure('download', $e);

            return false;
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
            }
            if (! $completed) {
                File::delete($localDestinationPath);
            }
        }
    }

    public function readStream(string $remoteFilePath): mixed
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config) {
            return false;
        }

        try {
            $disk = $this->disk($config);
            if (! $disk->exists($remoteFilePath)) {
                return false;
            }

            $stream = $disk->readStream($remoteFilePath);

            return is_resource($stream) ? $stream : false;
        } catch (Throwable $e) {
            $this->logFailure('read_stream', $e);

            return false;
        }
    }

    public function delete(string $remoteFilePath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config) {
            return false;
        }

        try {
            $disk = $this->disk($config);
            if (! $disk->exists($remoteFilePath)) {
                return true;
            }

            return $disk->delete($remoteFilePath) === true
                && ! $disk->exists($remoteFilePath);
        } catch (Throwable $e) {
            $this->logFailure('delete', $e);

            return false;
        }
    }

    public function exists(string $remoteFilePath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config) {
            return false;
        }

        try {
            return $this->disk($config)->exists($remoteFilePath);
        } catch (Throwable $e) {
            $this->logFailure('exists', $e);

            return false;
        }
    }

    public function temporaryUrl(string $remoteFilePath): string
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config) {
            return '';
        }

        try {
            return $this->disk($config)->temporaryUrl($remoteFilePath, now()->addMinutes(30));
        } catch (Throwable $e) {
            $this->logFailure('temporary_url', $e);

            return '';
        }
    }

    protected function logFailure(string $operation, Throwable $e): void
    {
        Log::warning('Operación de almacenamiento R2 fallida.', [
            'operation' => $operation,
            'exception' => $e::class,
        ]);
    }

    protected function logAmbiguousUpload(string $reason, string $destinationPath): void
    {
        Log::warning('La subida R2 no pudo confirmarse como exitosa.', [
            'reason' => $reason,
            'key_hash' => hash('sha256', $destinationPath),
        ]);
    }
}
