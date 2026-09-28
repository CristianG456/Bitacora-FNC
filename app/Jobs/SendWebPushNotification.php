<?php

namespace App\Jobs;

use App\Models\Notificacion;
use App\Services\WebPushService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendWebPushNotification implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 3600;

    public function __construct(public readonly int $notificationId) {}

    public function uniqueId(): string
    {
        return (string) $this->notificationId;
    }

    public function handle(WebPushService $webPush): void
    {
        try {
            $notification = Notificacion::find($this->notificationId);
            if ($notification) {
                $webPush->send($notification);
            }
        } catch (Throwable $error) {
            Log::error('Web Push no pudo enviarse.', [
                'notification_id' => $this->notificationId,
                'exception' => $error::class,
                'message' => $error->getMessage(),
            ]);
        }
    }
}
