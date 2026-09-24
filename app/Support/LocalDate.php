<?php

namespace App\Support;

use Carbon\Carbon;
use DateTimeInterface;

final class LocalDate
{
    public static function inBogota(DateTimeInterface|string|null $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $date = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'UTC');

        return $date->copy()->timezone('America/Bogota');
    }
}
