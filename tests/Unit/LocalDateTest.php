<?php

namespace Tests\Unit;

use App\Support\LocalDate;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class LocalDateTest extends TestCase
{
    public function test_utc_timestamp_is_presented_in_bogota_time(): void
    {
        $utc = CarbonImmutable::parse('2026-09-21 14:35:00', 'UTC');

        $this->assertSame(
            '2026-09-21 09:35',
            LocalDate::inBogota($utc)?->format('Y-m-d H:i')
        );
        $this->assertSame('UTC', $utc->timezoneName);
    }
}
