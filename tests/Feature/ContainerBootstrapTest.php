<?php

namespace Tests\Feature;

use App\Services\ContainerBootstrapService;
use Mockery;
use Tests\TestCase;

class ContainerBootstrapTest extends TestCase
{
    private string $lockPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lockPath = storage_path('framework/testing/bootstrap-'.bin2hex(random_bytes(8)).'.lock');
    }

    protected function tearDown(): void
    {
        @unlink($this->lockPath);
        parent::tearDown();
    }

    public function test_bootstrap_runs_migrations_before_vapid_under_a_persistent_lock(): void
    {
        $order = [];
        $result = (new ContainerBootstrapService)->run(
            $this->lockPath,
            function () use (&$order): int {
                $order[] = 'migrate';

                return 0;
            },
            function () use (&$order): int {
                $order[] = 'vapid';

                return 0;
            },
        );

        $this->assertSame(0, $result);
        $this->assertSame(['migrate', 'vapid'], $order);
        $this->assertFileExists($this->lockPath);
    }

    public function test_bootstrap_stops_before_vapid_when_migration_fails(): void
    {
        $vapidCalled = false;
        $result = (new ContainerBootstrapService)->run(
            $this->lockPath,
            fn (): int => 2,
            function () use (&$vapidCalled): int {
                $vapidCalled = true;

                return 0;
            },
        );

        $this->assertSame(2, $result);
        $this->assertFalse($vapidCalled);
    }

    public function test_artisan_command_delegates_to_the_locked_bootstrap(): void
    {
        $service = Mockery::mock(ContainerBootstrapService::class);
        $service->shouldReceive('run')
            ->once()
            ->withArgs(fn ($path, $migrate, $vapid) => str_ends_with($path, 'container-bootstrap.lock')
                && is_callable($migrate)
                && is_callable($vapid))
            ->andReturn(0);
        $this->app->instance(ContainerBootstrapService::class, $service);

        $this->artisan('app:container-bootstrap')->assertSuccessful();
    }

    public function test_only_app_entrypoint_bootstraps_and_dependants_share_storage(): void
    {
        $entrypoint = file_get_contents(base_path('entrypoint.sh'));
        $compose = file_get_contents(base_path('docker-compose.yml'));

        $this->assertSame(1, substr_count($entrypoint, 'php artisan app:container-bootstrap'));
        $this->assertStringNotContainsString('php artisan migrate --force', $entrypoint);
        $this->assertLessThan(strpos($entrypoint, 'php artisan app:container-bootstrap'), strpos($entrypoint, 'php artisan config:clear'));
        $this->assertLessThan(strpos($entrypoint, 'php artisan config:cache'), strpos($entrypoint, 'php artisan app:container-bootstrap'));
        $this->assertLessThan(strpos($entrypoint, 'exec php-fpm'), strpos($entrypoint, 'php artisan config:cache'));

        preg_match('/\n  scheduler:\n(.*?)\n  worker:/s', $compose, $scheduler);
        preg_match('/\n  worker:\n(.*?)\n  webserver:/s', $compose, $worker);
        foreach ([$scheduler[1] ?? '', $worker[1] ?? ''] as $dependent) {
            $this->assertStringContainsString('entrypoint: []', $dependent);
            $this->assertStringContainsString('app:', $dependent);
            $this->assertStringContainsString('condition: service_healthy', $dependent);
            $this->assertStringContainsString('bitacora_storage:/var/www/storage', $dependent);
            $this->assertStringNotContainsString('container-bootstrap', $dependent);
            $this->assertStringNotContainsString('migrate', $dependent);
        }
    }
}
