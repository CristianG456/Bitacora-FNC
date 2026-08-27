<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Models\ConfiguracionRespaldo;
use App\Models\Rol;
use App\Models\User;
use App\Services\BackupEncryptionService;
use App\Services\BackupMailService;
use App\Services\BackupService;
use App\Services\R2BackupStorageService;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Tests\TestCase;

class BackupReliabilityTest extends TestCase
{
    use RefreshDatabase;

    private string $testStoragePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testStoragePath = storage_path('framework/testing/backup-module-'.uniqid());
        File::ensureDirectoryExists($this->testStoragePath.'/logs');
        File::ensureDirectoryExists($this->testStoragePath.'/framework/cache/data');
        $this->app->useStoragePath($this->testStoragePath);
        config(['cache.default' => 'array']);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        File::deleteDirectory($this->testStoragePath);
        parent::tearDown();
    }

    public function test_backup_uses_unique_name_and_keeps_local_copy_after_r2_upload(): void
    {
        Carbon::setTestNow('2026-08-26 10:15:30');
        $config = $this->configuration(['r2_enabled' => true, 'r2_path' => 'backups']);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('upload')->twice()->andReturnTrue();
        $service = $this->serviceWithFakeDump($r2);

        $service->runBackup($config, 'manual');
        $service->runBackup($config, 'manual');

        $histories = BackupHistory::orderBy('id')->get();
        $this->assertCount(2, $histories);
        $this->assertNotSame($histories[0]->file_name, $histories[1]->file_name);
        $this->assertMatchesRegularExpression('/^backup_2026_08_26_101530_[a-z0-9]{8}\.zip$/', $histories[0]->file_name);
        $this->assertFileExists($histories[0]->file_path);
        $this->assertFileExists($histories[1]->file_path);
        $this->assertSame('r2', $histories[0]->storage_provider);
        $this->assertSame(hash_file('sha256', $histories[0]->file_path), $histories[0]->checksum_sha256);
    }

    public function test_backup_lock_prevents_concurrent_execution(): void
    {
        $config = $this->configuration();
        $lock = Cache::lock('backups:run', 60);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('Ya existe otro respaldo en ejecución.');
            $this->serviceWithFakeDump()->runBackup($config, 'manual');
        } finally {
            $lock->release();
        }
    }

    public function test_backup_lock_is_released_after_success_and_exception(): void
    {
        $config = $this->configuration();
        $this->serviceWithFakeDump()->runBackup($config, 'manual');
        $lock = Cache::lock('backups:run', 60);
        $this->assertTrue($lock->get());
        $lock->release();

        $encryption = Mockery::mock(BackupEncryptionService::class);
        $encryption->shouldReceive('compressAndEncrypt')->once()->andThrow(new RuntimeException('zip failed'));
        $mail = Mockery::mock(BackupMailService::class);
        $mail->shouldNotReceive('isEnabled');
        $service = Mockery::mock(BackupService::class, [
            $encryption,
            $mail,
            Mockery::mock(R2BackupStorageService::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('generateDump')
            ->andReturnUsing(fn (string $path) => File::put($path, 'SQL DUMP'));

        try {
            $service->runBackup($config, 'manual');
        } catch (RuntimeException) {
            $afterFailure = Cache::lock('backups:run', 60);
            $this->assertTrue($afterFailure->get());
            $afterFailure->release();
        }
    }

    public function test_r2_failure_marks_history_failed_and_preserves_local_zip(): void
    {
        $config = $this->configuration(['r2_enabled' => true]);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('upload')->once()->andReturnFalse();

        try {
            $this->serviceWithFakeDump($r2)->runBackup($config, 'manual');
            $this->fail('El respaldo debía fallar cuando R2 era obligatorio.');
        } catch (RuntimeException) {
            $history = BackupHistory::firstOrFail();
            $this->assertSame('fallido', $history->status);
            $this->assertFileExists($history->file_path);
            $this->assertStringContainsString('réplica R2', $history->error_message);
            $this->assertStringNotContainsString('secret', (string) $history->error_message);
        }
    }

    public function test_local_retention_removes_only_expired_local_copy(): void
    {
        $config = $this->configuration(['retention_days' => 7, 'max_backups' => 0]);
        $old = $this->historyWithLocalFile('old.zip', now()->subDays(10));
        $recent = $this->historyWithLocalFile('recent.zip', now()->subDay());
        $service = new BackupService(
            Mockery::mock(BackupEncryptionService::class),
            Mockery::mock(BackupMailService::class),
            Mockery::mock(R2BackupStorageService::class),
        );

        $service->cleanOldBackups($config);

        $this->assertDatabaseMissing('backup_histories', ['id' => $old->id]);
        $this->assertDatabaseHas('backup_histories', ['id' => $recent->id]);
        $this->assertFileDoesNotExist($old->file_path);
        $this->assertFileExists($recent->file_path);
    }

    public function test_local_retention_manages_verified_copy_from_failed_r2_backup(): void
    {
        $config = $this->configuration(['retention_days' => 7, 'max_backups' => 0]);
        $history = $this->historyWithLocalFile('failed-r2.zip', now()->subDays(10));
        $history->update(['status' => 'fallido']);
        $path = $history->file_path;
        (new BackupService(
            Mockery::mock(BackupEncryptionService::class),
            Mockery::mock(BackupMailService::class),
            Mockery::mock(R2BackupStorageService::class),
        ))->cleanOldBackups($config);

        $this->assertFileDoesNotExist($path);
        $this->assertDatabaseMissing('backup_histories', ['id' => $history->id]);
    }

    public function test_smtp_failure_becomes_warning_without_invalidating_verified_backup(): void
    {
        $config = $this->configuration([
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_username' => 'mailer@example.test',
            'sender_email' => 'sender@example.test',
            'sender_name' => 'Backups',
            'recipient_emails' => ['recipient@example.test'],
        ]);
        $encryption = Mockery::mock(BackupEncryptionService::class);
        $encryption->shouldReceive('compressAndEncrypt')->once()
            ->andReturnUsing(function (string $source, string $zip): bool {
                File::put($zip, 'zip:'.File::get($source));

                return true;
            });
        $mail = Mockery::mock(BackupMailService::class);
        $mail->shouldReceive('isEnabled')->once()->andReturnTrue();
        $mail->shouldReceive('sendBackup')->once()->andThrow(new RuntimeException('smtp failed'));
        $service = Mockery::mock(BackupService::class, [
            $encryption,
            $mail,
            Mockery::mock(R2BackupStorageService::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('generateDump')
            ->andReturnUsing(fn (string $path) => File::put($path, 'SQL DUMP'));

        $service->runBackup($config, 'manual');
        $history = BackupHistory::firstOrFail();
        $this->assertSame('exitoso', $history->status);
        $this->assertNotNull($history->error_message);
        $this->assertFileExists($history->file_path);
        $this->actingAs($this->administrator())
            ->get('/respaldos')
            ->assertOk()
            ->assertSee('El respaldo es válido, pero el correo SMTP no pudo enviarse.')
            ->assertSee('Exitoso con advertencia');
    }

    public function test_r2_retention_deletes_remote_before_clearing_history(): void
    {
        $config = $this->configuration(['r2_retention_days' => 7, 'max_backups' => 0]);
        $localPath = $this->testStoragePath.'/app/backups/remote.zip';
        File::ensureDirectoryExists(dirname($localPath));
        File::put($localPath, 'zip');
        $history = BackupHistory::create([
            'file_name' => 'remote.zip',
            'file_path' => $localPath,
            'status' => 'exitoso',
            'backup_type' => 'automatico',
            'storage_provider' => 'r2',
            'storage_path' => 'backups/remote.zip',
            'r2_uploaded_at' => now()->subDays(10),
        ]);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('delete')->once()->with('backups/remote.zip')->andReturnTrue();

        (new BackupService(
            Mockery::mock(BackupEncryptionService::class),
            Mockery::mock(BackupMailService::class),
            $r2,
        ))->cleanOldBackups($config);

        $history->refresh();
        $this->assertNull($history->storage_path);
        $this->assertSame('local', $history->storage_provider);
        $this->assertFileExists($localPath);
    }

    public function test_r2_retention_keeps_history_when_remote_delete_fails(): void
    {
        $config = $this->configuration(['r2_retention_days' => 7, 'max_backups' => 0]);
        $history = BackupHistory::create([
            'file_name' => 'remote.zip',
            'status' => 'exitoso',
            'backup_type' => 'automatico',
            'storage_provider' => 'r2',
            'storage_path' => 'backups/remote.zip',
            'r2_uploaded_at' => now()->subDays(10),
        ]);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('delete')->once()->andReturnFalse();

        (new BackupService(
            Mockery::mock(BackupEncryptionService::class),
            Mockery::mock(BackupMailService::class),
            $r2,
        ))->cleanOldBackups($config);

        $this->assertDatabaseHas('backup_histories', [
            'id' => $history->id,
            'storage_path' => 'backups/remote.zip',
        ]);
    }

    public function test_r2_service_upload_stream_and_delete_with_fake_disk(): void
    {
        $this->configuration(['r2_enabled' => true]);
        Storage::fake('r2');
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $service = new R2BackupStorageService;

        $this->assertTrue($service->upload($local, 'backups/upload.zip'));
        $this->assertTrue($service->exists('backups/upload.zip'));
        $stream = $service->readStream('backups/upload.zip');
        $this->assertIsResource($stream);
        $this->assertSame('backup-content', stream_get_contents($stream));
        fclose($stream);
        $this->assertTrue($service->delete('backups/upload.zip'));
        $this->assertFalse($service->exists('backups/upload.zip'));
    }

    public function test_r2_service_does_not_turn_false_results_into_success(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->twice()->andReturnFalse();
        $disk->shouldReceive('put')->once()->andReturnFalse();
        $disk->shouldReceive('delete')->once()->andReturnFalse();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('disk')->andReturn($disk);

        $this->assertFalse($service->upload($local, 'backups/upload.zip'));

        $deleteDisk = Mockery::mock(Filesystem::class);
        $deleteDisk->shouldReceive('exists')->once()->andReturnTrue();
        $deleteDisk->shouldReceive('delete')->once()->andReturnFalse();
        $deleteService = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $deleteService->shouldReceive('disk')->andReturn($deleteDisk);
        $this->assertFalse($deleteService->delete('backups/upload.zip'));
    }

    public function test_r2_ambiguous_upload_runs_compensating_delete(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->times(3)->andReturn(false, false, false);
        $disk->shouldReceive('put')->once()->andReturnTrue();
        $disk->shouldReceive('delete')->once()->with('backups/upload.zip')->andReturnTrue();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('disk')->andReturn($disk);

        $this->assertFalse($service->upload($local, 'backups/upload.zip'));
    }

    public function test_r2_exists_exception_after_put_still_runs_compensation(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $calls = 0;
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->times(3)->andReturnUsing(function () use (&$calls): bool {
            $calls++;
            if ($calls === 2) {
                throw new RuntimeException('ambiguous exists');
            }

            return false;
        });
        $disk->shouldReceive('put')->once()->andReturnTrue();
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('disk')->andReturn($disk);

        $this->assertFalse($service->upload($local, 'backups/upload.zip'));
    }

    public function test_r2_failed_compensation_never_turns_upload_into_success(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->times(3)->andReturn(false, false, true);
        $disk->shouldReceive('put')->once()->andReturnTrue();
        $disk->shouldReceive('delete')->once()->andReturnFalse();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('disk')->andReturn($disk);

        $this->assertFalse($service->upload($local, 'backups/upload.zip'));
    }

    public function test_r2_put_exception_is_compensated_and_reported_as_failure(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->twice()->andReturnFalse();
        $disk->shouldReceive('put')->once()->andThrow(new RuntimeException('put failed'));
        $disk->shouldReceive('delete')->once()->andReturnTrue();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('disk')->andReturn($disk);

        $this->assertFalse($service->upload($local, 'backups/upload.zip'));
    }

    public function test_r2_disk_is_refreshed_when_bucket_changes(): void
    {
        $config = $this->configuration(['r2_enabled' => true, 'r2_bucket' => 'bucket-a']);
        $local = $this->testStoragePath.'/upload.zip';
        File::put($local, 'backup-content');
        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->times(4)->andReturn(false, true, false, true);
        $disk->shouldReceive('put')->twice()->andReturnTrue();
        $service = Mockery::mock(R2BackupStorageService::class)
            ->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('resolveDisk')->twice()->andReturn($disk);
        Storage::shouldReceive('forgetDisk')->once()->with('r2');

        $this->assertTrue($service->upload($local, 'backups/a.zip'));
        $config->update(['r2_bucket' => 'bucket-b']);
        $this->assertTrue($service->upload($local, 'backups/b.zip'));
    }

    public function test_r2_healthcheck_uses_unique_key_and_cleans_up(): void
    {
        $this->configuration(['r2_enabled' => true]);
        Storage::fake('r2');
        Storage::disk('r2')->put('test_connection.txt', 'existing-object');

        $response = $this->actingAs($this->administrator())->postJson('/respaldos/probar-r2');

        $response->assertOk()->assertJson(['success' => true]);
        Storage::disk('r2')->assertExists('test_connection.txt');
        $this->assertSame([], Storage::disk('r2')->allFiles('healthchecks'));
    }

    public function test_r2_healthcheck_always_attempts_cleanup_after_ambiguous_upload(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('upload')->once()->andReturnFalse();
        $r2->shouldReceive('delete')->once()->with(Mockery::pattern('/^healthchecks\/test_.+\.txt$/'))->andReturnTrue();
        $this->app->instance(R2BackupStorageService::class, $r2);

        $this->actingAs($this->administrator())
            ->postJson('/respaldos/probar-r2')
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_r2_healthcheck_does_not_report_success_when_delete_fails(): void
    {
        $this->configuration(['r2_enabled' => true]);
        $r2 = Mockery::mock(R2BackupStorageService::class);
        $r2->shouldReceive('upload')->once()->andReturnTrue();
        $r2->shouldReceive('exists')->once()->andReturnTrue();
        $r2->shouldReceive('delete')->twice()->andReturnFalse();
        $this->app->instance(R2BackupStorageService::class, $r2);

        $this->actingAs($this->administrator())
            ->postJson('/respaldos/probar-r2')
            ->assertOk()
            ->assertJson(['success' => false]);
    }

    public function test_smtp_is_optional_for_local_only_configuration(): void
    {
        $response = $this->actingAs($this->administrator())->post('/respaldos', [
            'backup_frequency' => 'diario',
            'backup_time' => '09:30',
            'max_backups' => 10,
            'retention_days' => 30,
        ]);

        $response->assertRedirect('/respaldos');
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('backup_settings', [
            'backup_frequency' => 'diario',
            'smtp_host' => null,
            'r2_enabled' => false,
        ]);
    }

    public function test_invalid_recipient_is_rejected_and_multiple_valid_recipients_are_accepted(): void
    {
        $base = [
            'smtp_host' => 'smtp.example.test',
            'smtp_port' => 587,
            'smtp_username' => 'mailer@example.test',
            'sender_email' => 'sender@example.test',
            'sender_name' => 'Backups',
            'backup_frequency' => 'diario',
            'backup_time' => '09:30',
        ];

        $this->actingAs($this->administrator())
            ->post('/respaldos', $base + ['recipient_emails' => 'valid@example.test, invalid'])
            ->assertSessionHasErrors('recipient_emails');

        $this->actingAs($this->administrator())
            ->post('/respaldos', $base + ['recipient_emails' => 'one@example.test, two@example.test'])
            ->assertSessionHasNoErrors();
        $this->assertSame(
            ['one@example.test', 'two@example.test'],
            ConfiguracionRespaldo::firstOrFail()->recipient_emails
        );
    }

    public function test_mysql_credential_temporaries_are_cleaned_when_permission_setup_fails(): void
    {
        $originalConnection = config('database.default');
        config([
            'database.default' => 'backup_test',
            'database.connections.backup_test' => [
                'driver' => 'mysql',
                'host' => 'db',
                'port' => 3306,
                'database' => 'legal',
                'username' => 'root',
                'password' => 'not-logged',
            ],
        ]);
        $beforeCnf = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'mysql_cnf_*') ?: [];
        $beforeError = glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'mysql_error_*') ?: [];
        $service = new class(Mockery::mock(BackupEncryptionService::class), Mockery::mock(BackupMailService::class), Mockery::mock(R2BackupStorageService::class)) extends BackupService
        {
            public function invokeGenerateDump(string $path): void
            {
                $this->generateDump($path);
            }

            protected function secureCredentialFile(string $path): bool
            {
                return false;
            }
        };

        $failedSafely = false;
        try {
            $service->invokeGenerateDump($this->testStoragePath.'/dump.sql');
            $this->fail('El fallo de permisos debía abortar.');
        } catch (RuntimeException) {
            $failedSafely = true;
        } finally {
            config(['database.default' => $originalConnection]);
        }

        $this->assertTrue($failedSafely);
        $this->assertSame($beforeCnf, glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'mysql_cnf_*') ?: []);
        $this->assertSame($beforeError, glob(sys_get_temp_dir().DIRECTORY_SEPARATOR.'mysql_error_*') ?: []);
    }

    public function test_non_administrator_cannot_access_backups(): void
    {
        $role = Rol::firstOrCreate(['nombre' => 'Usuario']);
        $user = User::factory()->create([
            'rol_id' => $role->id,
            'activo' => true,
            'force_password_change' => false,
        ]);

        $this->actingAs($user)->get('/respaldos')->assertForbidden();
    }

    public function test_backup_password_is_encrypted_and_legacy_plaintext_remains_readable(): void
    {
        $config = $this->configuration(['backup_password' => 'new-secret']);
        $this->assertNotSame('new-secret', $config->getRawOriginal('backup_password'));
        $this->assertSame('new-secret', $config->backup_password);

        \DB::table('backup_settings')->where('id', $config->id)->update([
            'backup_password' => 'legacy-plain-text',
        ]);

        $this->assertSame('legacy-plain-text', $config->fresh()->backup_password);
    }

    public function test_manual_endpoint_reports_artisan_failure(): void
    {
        Artisan::shouldReceive('call')->once()->andReturn(Command::FAILURE);

        $this->actingAs($this->administrator())
            ->postJson('/respaldos/manual')
            ->assertStatus(500)
            ->assertJson(['success' => false]);
    }

    private function serviceWithFakeDump(?R2BackupStorageService $r2 = null): BackupService
    {
        $encryption = Mockery::mock(BackupEncryptionService::class);
        $encryption->shouldReceive('compressAndEncrypt')
            ->andReturnUsing(function (string $source, string $zip): bool {
                File::put($zip, 'zip:'.File::get($source));

                return true;
            });
        $mail = Mockery::mock(BackupMailService::class);
        $mail->shouldReceive('isEnabled')->andReturnFalse();
        $mail->shouldNotReceive('sendBackup');
        $service = Mockery::mock(BackupService::class, [
            $encryption,
            $mail,
            $r2 ?? Mockery::mock(R2BackupStorageService::class),
        ])->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('generateDump')
            ->andReturnUsing(fn (string $path) => File::put($path, 'SQL DUMP'));

        return $service;
    }

    private function configuration(array $overrides = []): ConfiguracionRespaldo
    {
        return ConfiguracionRespaldo::create(array_merge([
            'recipient_emails' => null,
            'backup_frequency' => 'diario',
            'backup_time' => '10:00',
            'max_backups' => 10,
            'retention_days' => 30,
            'r2_enabled' => false,
            'r2_bucket' => 'test-bucket',
            'r2_path' => 'backups',
            'r2_retention_days' => 30,
            'is_active' => true,
        ], $overrides));
    }

    private function historyWithLocalFile(string $name, Carbon $createdAt): BackupHistory
    {
        $path = $this->testStoragePath.'/app/backups/'.$name;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, 'zip');

        $history = BackupHistory::create([
            'file_name' => $name,
            'file_path' => $path,
            'status' => 'exitoso',
            'backup_type' => 'automatico',
            'storage_provider' => 'local',
            'file_size' => 3,
            'checksum_sha256' => hash('sha256', 'zip'),
        ]);
        $history->timestamps = false;
        $history->created_at = $createdAt;
        $history->updated_at = $createdAt;
        $history->save();

        return $history;
    }

    private function administrator(): User
    {
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);

        return User::factory()->create([
            'rol_id' => $role->id,
            'activo' => true,
            'force_password_change' => false,
        ]);
    }
}
