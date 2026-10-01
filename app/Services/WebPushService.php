<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\PushSubscription as StoredSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushService
{
    public function __construct(private readonly ?WebPush $client = null) {}

    public function send(Notificacion $notification): int
    {
        $subscriptions = StoredSubscription::query()
            ->where('user_id', $notification->user_id)
            ->orderBy('id')
            ->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $vapid = config('webpush.vapid');
        if (blank($vapid['public_key']) || blank($vapid['private_key']) || blank($vapid['subject'])) {
            Log::warning('Web Push omitido: VAPID no está configurado.', ['notification_id' => $notification->id]);

            return 0;
        }

        $webPush = $this->client ?? new WebPush(['VAPID' => [
            'subject' => $vapid['subject'],
            'publicKey' => $vapid['public_key'],
            'privateKey' => $vapid['private_key'],
        ]]);
        $webPush->setDefaultOptions(['TTL' => 300, 'urgency' => 'normal']);
        $payload = json_encode($this->payload($notification), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $sent = 0;

        $subscriptions->each(function (StoredSubscription $stored) use ($webPush, $payload, $notification, &$sent) {
            $deliveryKey = 'webpush:sent:'.$notification->id.':'.$stored->endpoint_hash;
            $lock = Cache::lock($deliveryKey.':lock', 30);
            if (! $lock->get()) {
                return;
            }

            try {
                if (Cache::has($deliveryKey)) {
                    return;
                }

                $subscription = Subscription::create([
                    'endpoint' => $stored->endpoint,
                    'publicKey' => $stored->p256dh,
                    'authToken' => $stored->auth,
                    'contentEncoding' => $stored->content_encoding ?: 'aes128gcm',
                ]);

                $report = $webPush->sendOneNotification($subscription, $payload);
                if ($report->isSuccess()) {
                    $stored->forceFill(['last_used_at' => now()])->save();
                    Cache::forever($deliveryKey, true);
                    $sent++;

                    return;
                }

                if ($report->isSubscriptionExpired()) {
                    $stored->delete();

                    return;
                }

                Log::warning('Falló un envío Web Push.', [
                    'notification_id' => $notification->id,
                    'subscription_id' => $stored->id,
                    'status' => $report->getResponse()?->getStatusCode(),
                    'reason' => $report->getReason(),
                ]);
            } finally {
                $lock->release();
            }
        });

        return $sent;
    }

    public function payload(Notificacion $notification): array
    {
        return [
            'title' => $notification->tipo === 'ans' ? 'Alerta de ANS' : 'Sistema Jurídico',
            'body' => match ($notification->tipo) {
                'caso' => 'Tienes un nuevo caso asignado.',
                'tarea' => 'Tienes una nueva tarea.',
                'mensaje' => 'Tienes un nuevo mensaje.',
                'correccion_tarea' => 'Tienes una actualización de corrección.',
                'ans' => 'Un caso requiere tu atención.',
                default => 'Tienes una nueva notificación.',
            },
            'icon' => '/icons/icon-192.png',
            'badge' => '/icons/icon-192.png',
            'tag' => 'notification-'.$notification->id,
            'data' => [
                'notification_id' => $notification->id,
                'tipo' => $notification->tipo,
                'url' => $this->url($notification),
            ],
        ];
    }

    private function url(Notificacion $notification): string
    {
        if (! $notification->caso_id) {
            return '/dashboard';
        }

        $base = '/casos/'.$notification->caso_id;
        if ($notification->tipo === 'mensaje') {
            $message = $notification->mensajeRelacionado()->first();
            if ($message?->destinatario_id) {
                $otherUser = $message->user_id === $notification->user_id
                    ? $message->destinatario_id
                    : $message->user_id;

                return $base.'?'.http_build_query(['tab' => 'mensajes', 'chat' => 'directo', 'usuario' => $otherUser]);
            }

            return $base.'?'.http_build_query(['tab' => 'mensajes', 'chat' => 'general']);
        }

        if (in_array($notification->tipo, ['tarea', 'correccion_tarea'], true)) {
            $query = $notification->solicitud_correccion_id
                ? '?'.http_build_query(['solicitud_correccion' => $notification->solicitud_correccion_id])
                : '';
            $fragment = $notification->tarea_id ? '#tarea-'.$notification->tarea_id : '#mis-tareas';

            return $base.$query.$fragment;
        }

        return $base;
    }
}
