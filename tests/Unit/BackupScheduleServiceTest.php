<?php

namespace Tests\Unit;

use App\Services\BackupScheduleService;
use Carbon\Carbon;
use Tests\TestCase;

class BackupScheduleServiceTest extends TestCase
{
    public function test_daily_schedule_preserves_configured_time(): void
    {
        $service = new BackupScheduleService;

        $this->assertSame('30 14 * * *', $service->cronExpression('diario', '14:30'));
    }

    public function test_weekly_schedule_preserves_configured_time(): void
    {
        $service = new BackupScheduleService;

        $this->assertSame('45 8 * * 0', $service->cronExpression('semanal', '08:45:00'));
    }

    public function test_monthly_schedule_preserves_configured_time(): void
    {
        $service = new BackupScheduleService;

        $this->assertSame('5 23 1 * *', $service->cronExpression('mensual', '23:05'));
        $this->assertSame('America/Bogota', config('backup.timezone'));
    }

    public function test_next_run_uses_backup_timezone_for_all_frequencies(): void
    {
        config(['backup.timezone' => 'America/Bogota']);
        $service = new BackupScheduleService;
        $from = Carbon::create(2026, 8, 26, 10, 0, 0, 'America/Bogota');

        $this->assertSame(
            '2026-08-26 14:30 America/Bogota',
            $service->nextRun('diario', '14:30', $from)->format('Y-m-d H:i e')
        );
        $this->assertSame(
            '2026-08-30 08:45 America/Bogota',
            $service->nextRun('semanal', '08:45', $from)->format('Y-m-d H:i e')
        );
        $this->assertSame(
            '2026-09-01 23:05 America/Bogota',
            $service->nextRun('mensual', '23:05', $from)->format('Y-m-d H:i e')
        );
    }
}
