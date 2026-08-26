<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class ProgressiveLoginThrottle
{
    private const MAX_ATTEMPTS = 3;

    private const STATE_TTL_SECONDS = 86400;

    private const MAX_LOCK_MINUTES = 30;

    public function secondsRemaining(string $email): int
    {
        $lockedUntil = (int) Cache::get($this->key($email, 'locked-until'), 0);
        $remaining = $lockedUntil - now()->timestamp;

        if ($remaining <= 0) {
            Cache::forget($this->key($email, 'locked-until'));

            return 0;
        }

        return $remaining;
    }

    public function recordFailure(string $email): ?int
    {
        $attemptsKey = $this->key($email, 'attempts');
        RateLimiter::hit($attemptsKey, self::STATE_TTL_SECONDS);

        if (RateLimiter::attempts($attemptsKey) < self::MAX_ATTEMPTS) {
            return null;
        }

        RateLimiter::clear($attemptsKey);

        $levelKey = $this->key($email, 'level');
        $level = ((int) Cache::get($levelKey, 0)) + 1;
        Cache::put($levelKey, $level, self::STATE_TTL_SECONDS);

        $minutes = min(2 ** ($level - 1), self::MAX_LOCK_MINUTES);
        Cache::put(
            $this->key($email, 'locked-until'),
            now()->addMinutes($minutes)->timestamp,
            $minutes * 60
        );

        return $minutes;
    }

    public function clear(string $email): void
    {
        RateLimiter::clear($this->key($email, 'attempts'));
        Cache::forget($this->key($email, 'level'));
        Cache::forget($this->key($email, 'locked-until'));
    }

    private function key(string $email, string $suffix): string
    {
        $normalizedEmail = mb_strtolower(trim($email));

        return 'login-email:'.hash('sha256', $normalizedEmail).':'.$suffix;
    }
}
