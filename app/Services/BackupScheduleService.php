<?php

namespace App\Services;

use App\Models\ConfiguracionRespaldo;
use Carbon\Carbon;
use Cron\CronExpression;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

class BackupScheduleService
{
    public function register(ConfiguracionRespaldo $config): Event
    {
        return Schedule::command('backup:run')
            ->cron($this->cronExpression($config->backup_frequency, $config->backup_time))
            ->timezone(config('backup.timezone', 'America/Bogota'))
            ->withoutOverlapping(config('backup.overlap_minutes', 120));
    }

    public function cronExpression(string $frequency, string $time): string
    {
        $parsed = Carbon::createFromFormat(
            strlen($time) > 5 ? 'H:i:s' : 'H:i',
            $time,
            config('backup.timezone', 'America/Bogota')
        );

        $minute = $parsed->minute;
        $hour = $parsed->hour;

        return match ($frequency) {
            'diario' => "{$minute} {$hour} * * *",
            'semanal' => "{$minute} {$hour} * * 0",
            'mensual' => "{$minute} {$hour} 1 * *",
            default => throw new \InvalidArgumentException('Frecuencia de respaldo no soportada.'),
        };
    }

    public function nextRun(string $frequency, string $time, ?Carbon $from = null): Carbon
    {
        $timezone = config('backup.timezone', 'America/Bogota');
        $reference = ($from ?? Carbon::now($timezone))->copy()->setTimezone($timezone);
        $next = (new CronExpression($this->cronExpression($frequency, $time)))
            ->getNextRunDate($reference->toDateTime(), 0, false, $timezone);

        return Carbon::instance($next)->setTimezone($timezone);
    }
}
