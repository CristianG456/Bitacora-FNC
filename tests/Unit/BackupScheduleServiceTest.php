<?php

namespace Tests\Unit;

use App\Services\BackupScheduleService;
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
}
