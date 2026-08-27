<?php

namespace Tests\Unit;

use App\Services\BackupEncryptionService;
use Illuminate\Support\Facades\File;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use ZipArchive;

class BackupEncryptionServiceTest extends TestCase
{
    private string $temporaryDirectory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->temporaryDirectory = storage_path('framework/testing/backup-encryption-'.uniqid());
        File::ensureDirectoryExists($this->temporaryDirectory);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->temporaryDirectory);

        parent::tearDown();
    }

    public function test_protected_zip_contains_the_expected_readable_sql_and_can_be_reopened(): void
    {
        $sqlPath = $this->path('database.sql');
        $zipPath = $this->path('database.zip');
        $sql = "CREATE TABLE backups (id BIGINT);\nINSERT INTO backups VALUES (1);\n";
        File::put($sqlPath, $sql);

        $this->assertTrue((new BackupEncryptionService)->compressAndEncrypt($sqlPath, $zipPath, 'safe-password'));
        $this->assertFileExists($zipPath);
        $this->assertGreaterThan(0, File::size($zipPath));

        $archive = new ZipArchive;
        $this->assertTrue($archive->open($zipPath, ZipArchive::RDONLY));
        $this->assertTrue($archive->setPassword('safe-password'));
        $index = $archive->locateName('database.sql');
        $this->assertNotFalse($index);
        $this->assertSame(strlen($sql), $archive->statIndex($index)['size']);

        $stream = $archive->getStream('database.sql');
        $this->assertIsResource($stream);
        $this->assertSame($sql, stream_get_contents($stream));
        fclose($stream);
        $this->assertTrue($archive->close());
    }

    public function test_add_file_failure_aborts_and_removes_the_partial_zip(): void
    {
        $sqlPath = $this->path('database.sql');
        $zipPath = $this->path('partial.zip');
        File::put($sqlPath, 'SELECT 1;');
        File::put($zipPath, 'partial');

        $archive = Mockery::mock(ZipArchive::class);
        $archive->shouldReceive('open')->once()->andReturnTrue();
        $archive->shouldReceive('addFile')->once()->with($sqlPath, 'database.sql')->andReturnFalse();
        $archive->shouldReceive('close')->once()->andReturnTrue();

        $service = Mockery::mock(BackupEncryptionService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('createArchive')->once()->andReturn($archive);

        try {
            $service->compressAndEncrypt($sqlPath, $zipPath, 'safe-password');
            $this->fail('Un addFile fallido no debe producir un respaldo válido.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($zipPath);
        }
    }

    public function test_empty_sql_source_is_rejected(): void
    {
        $sqlPath = $this->path('empty.sql');
        $zipPath = $this->path('empty.zip');
        File::put($sqlPath, '');

        $this->expectException(RuntimeException::class);
        (new BackupEncryptionService)->compressAndEncrypt($sqlPath, $zipPath);
    }

    public function test_zip_without_the_expected_sql_entry_is_rejected(): void
    {
        $zipPath = $this->path('wrong-entry.zip');
        $archive = new ZipArchive;
        $this->assertTrue($archive->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE));
        $this->assertTrue($archive->addFromString('other.sql', 'SELECT 1;'));
        $this->assertTrue($archive->close());

        $this->expectException(RuntimeException::class);
        (new BackupEncryptionService)->validateArchive($zipPath, 'database.sql');
    }

    public function test_empty_and_corrupt_zip_files_are_rejected(): void
    {
        $service = new BackupEncryptionService;
        $emptyZip = $this->path('empty.zip');
        $corruptZip = $this->path('corrupt.zip');
        File::put($emptyZip, '');
        File::put($corruptZip, 'this is not a zip archive');

        foreach ([$emptyZip, $corruptZip] as $invalidZip) {
            try {
                $service->validateArchive($invalidZip, 'database.sql');
                $this->fail('Un ZIP vacío o corrupto debe rechazarse.');
            } catch (RuntimeException) {
                $this->assertFileExists($invalidZip);
            }
        }
    }

    public function test_validation_exception_removes_the_generated_zip(): void
    {
        $sqlPath = $this->path('database.sql');
        $zipPath = $this->path('invalid-after-create.zip');
        File::put($sqlPath, 'SELECT 1;');

        $service = Mockery::mock(BackupEncryptionService::class)->makePartial();
        $service->shouldReceive('validateArchive')
            ->once()
            ->with($zipPath, 'database.sql', 'safe-password')
            ->andThrow(new RuntimeException('validation failed'));

        try {
            $service->compressAndEncrypt($sqlPath, $zipPath, 'safe-password');
            $this->fail('La excepción de validación debía propagarse.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($zipPath);
            $this->assertFileExists($sqlPath);
        }
    }

    public function test_missing_sql_source_is_rejected_without_leaving_a_zip(): void
    {
        $zipPath = $this->path('missing.zip');

        try {
            (new BackupEncryptionService)->compressAndEncrypt($this->path('missing.sql'), $zipPath);
            $this->fail('Un SQL inexistente debe rechazarse.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($zipPath);
        }
    }

    private function path(string $file): string
    {
        return $this->temporaryDirectory.DIRECTORY_SEPARATOR.$file;
    }
}
