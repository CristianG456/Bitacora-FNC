<?php

namespace Tests\Unit;

use App\Services\LocalBackupStorageService;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use Tests\TestCase;

class LocalBackupStorageServiceTest extends TestCase
{
    private string $originalStoragePath;

    private string $temporaryStoragePath;

    private string $backupDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalStoragePath = storage_path();
        $this->temporaryStoragePath = $this->originalStoragePath.DIRECTORY_SEPARATOR.'framework'.DIRECTORY_SEPARATOR.'testing'.DIRECTORY_SEPARATOR.'local-backup-'.uniqid();
        $this->app->useStoragePath($this->temporaryStoragePath);
        $this->backupDirectory = storage_path('app/backups');
        File::ensureDirectoryExists($this->backupDirectory);
    }

    protected function tearDown(): void
    {
        $this->app->useStoragePath($this->originalStoragePath);
        File::deleteDirectory($this->temporaryStoragePath);

        parent::tearDown();
    }

    public function test_upload_exists_read_download_and_delete_stay_inside_backup_directory(): void
    {
        $source = $this->absoluteBackupPath('incoming/source.zip');
        File::ensureDirectoryExists(dirname($source));
        File::put($source, 'verified-backup');
        $service = new LocalBackupStorageService;

        $this->assertTrue($service->upload($source, 'stored/backup.zip'));
        $this->assertTrue($service->exists('stored/backup.zip'));

        $stream = $service->readStream('stored/backup.zip');
        $this->assertIsResource($stream);
        $this->assertSame('verified-backup', stream_get_contents($stream));
        fclose($stream);

        $this->assertTrue($service->download('stored/backup.zip', 'downloads/copy.zip'));
        $this->assertSame('verified-backup', File::get($this->absoluteBackupPath('downloads/copy.zip')));
        $this->assertTrue($service->delete('stored/backup.zip'));
        $this->assertFalse($service->exists('stored/backup.zip'));
    }

    public function test_missing_files_are_handled_without_creating_or_deleting_unrelated_data(): void
    {
        $service = new LocalBackupStorageService;

        $this->assertFalse($service->exists('missing.zip'));
        $this->assertFalse($service->readStream('missing.zip'));
        $this->assertFalse($service->download('missing.zip', 'downloads/missing.zip'));
        $this->assertTrue($service->delete('missing.zip'));
        $this->assertFileDoesNotExist($this->absoluteBackupPath('downloads/missing.zip'));
    }

    public function test_path_traversal_and_absolute_paths_are_rejected_for_all_operations(): void
    {
        $service = new LocalBackupStorageService;
        $outside = $this->temporaryStoragePath.DIRECTORY_SEPARATOR.'outside.zip';
        File::put($outside, 'outside');

        $operations = [
            fn () => $service->exists('../outside.zip'),
            fn () => $service->readStream('nested/../../outside.zip'),
            fn () => $service->delete($outside),
            fn () => $service->download('../outside.zip', 'copy.zip'),
            fn () => $service->download('missing.zip', '../copy.zip'),
            fn () => $service->upload($outside, 'stored/outside.zip'),
        ];

        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('La operación debía rechazar el escape del directorio de respaldos.');
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }

        $this->assertSame('outside', File::get($outside));
    }

    public function test_symbolic_link_that_resolves_outside_backup_directory_is_rejected(): void
    {
        $outside = $this->temporaryStoragePath.DIRECTORY_SEPARATOR.'outside-link-target.zip';
        $link = $this->absoluteBackupPath('escaped-link.zip');
        File::put($outside, 'outside');

        if (! @symlink($outside, $link)) {
            $this->markTestSkipped('El sistema operativo no permite crear enlaces simbólicos en este entorno.');
        }

        $service = new LocalBackupStorageService;

        try {
            $service->readStream('escaped-link.zip');
            $this->fail('Un enlace que escapa del directorio permitido debe rechazarse.');
        } catch (InvalidArgumentException) {
            $this->assertSame('outside', File::get($outside));
        } finally {
            @unlink($link);
        }
    }

    public function test_upload_rejects_missing_source_and_safe_delete_is_idempotent(): void
    {
        $service = new LocalBackupStorageService;

        try {
            $service->upload($this->absoluteBackupPath('missing-source.zip'), 'stored/backup.zip');
            $this->fail('Un archivo fuente inexistente debe rechazarse.');
        } catch (InvalidArgumentException) {
            $this->assertTrue(true);
        }

        $this->assertTrue($service->delete('stored/already-missing.zip'));
    }

    private function absoluteBackupPath(string $relativePath): string
    {
        return $this->backupDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }
}
