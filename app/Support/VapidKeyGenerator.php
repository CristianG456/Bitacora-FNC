<?php

namespace App\Support;

use Minishlink\WebPush\VAPID;

class VapidKeyGenerator
{
    /** @return array{publicKey: string, privateKey: string} */
    public function generate(): array
    {
        return VAPID::createVapidKeys();
    }
}
