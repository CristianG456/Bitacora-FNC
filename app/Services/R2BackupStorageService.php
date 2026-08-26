<?php

namespace App\Services;

use App\Contracts\BackupStorageInterface;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class R2BackupStorageService implements BackupStorageInterface
{
    protected function configureDisk(ConfiguracionRespaldo $config): void
    {
        if ($config->r2_bucket) {
            Config::set('filesystems.disks.r2.bucket', $config->r2_bucket);
        }
    }

    protected function disk(ConfiguracionRespaldo $config): Filesystem
    {
        $this->configureDisk($config);

        return Storage::disk('r2');
    }

    public function upload(string $localFilePath, string $destinationPath): bool
    {
        $config = ConfiguracionRespaldo::first();
        if (! $config?->r2_enabled || ! is_readable($localFilePath)) {
            return false;
        }

        $stream = null;

        try {
            $stream = fopen($localFilePath, 'rb');
            if (! is_resource($stream)) {
                return false;
            }

            $disk = $this->disk($config);
            if ($disk->put($destinationPath, $stream) !== true) {
                return false;
            }

            return $disk->exists($destinationPath);
        } catch (Throwable $e) {
            $this->logFailure('upload', $e);

            return false;
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function download(string $remoteFilePath, string $localDestinationPath): bool
    {
        $input = $this->readStream($remoteFilePath);
        if (! is_resource($input)) {
            return false;
        }

        $output = null;

        try {
            $output = fopen($localDestinationPath, 'wb');
            if (! is_resource($output)) {
                return false;
            }

            return stream_copy_to_stream($input, $output) !== false;
        } catch (Throwable $e) {
            $this->logFailure('download', $e);

            return false;
        } finally {
            fclose($input);
            if (is_resource($output)) {
                fclose($output);
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
}
