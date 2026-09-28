<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotification;
use App\Models\Notificacion;
use App\Models\PushSubscription;
use App\Models\User;
use App\Services\WebPushService;
use GuzzleHttp\Psr7\Request as PsrRequest;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;
use Mockery;
use Tests\TestCase;

class WebPushTest extends TestCase
{
    use RefreshDatabase;

    private function subscription(string $suffix = 'one'): array
    {
        return [
            'endpoint' => "https://push.example.test/{$suffix}",
            'keys' => ['p256dh' => str_repeat('p', 88), 'auth' => str_repeat('a', 24)],
            'contentEncoding' => 'aes128gcm',
            'device_name' => 'Equipo de prueba',
        ];
    }

    public function test_authenticated_user_registers_and_guest_cannot_register(): void
    {
        $user = User::factory()->create(['activo' => true]);

        $this->postJson(route('push-subscriptions.store'), $this->subscription())->assertUnauthorized();
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->subscription())
            ->assertOk()->assertJsonPath('registered', true);

        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $user->id, 'device_name' => 'Equipo de prueba']);
    }

    public function test_duplicate_endpoint_is_updated_and_each_device_is_preserved(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->subscription('one'))->assertOk();
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->subscription('one'))->assertOk();
        $this->actingAs($user)->postJson(route('push-subscriptions.store'), $this->subscription('two'))->assertOk();

        $this->assertSame(2, PushSubscription::where('user_id', $user->id)->count());
    }

    public function test_user_deletes_only_own_subscription_and_logout_removes_current_device(): void
    {
        $owner = User::factory()->create(['activo' => true]);
        $other = User::factory()->create(['activo' => true]);
        $own = $this->subscription('own');
        $foreign = $this->subscription('foreign');
        $this->actingAs($owner)->postJson(route('push-subscriptions.store'), $own)->assertOk();
        $this->actingAs($other)->postJson(route('push-subscriptions.store'), $foreign)->assertOk();

        $this->actingAs($owner)->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => $foreign['endpoint']])->assertNoContent();
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $other->id]);

        $this->actingAs($owner)->post(route('logout'), ['push_endpoint' => $own['endpoint']])->assertRedirect(route('login'));
        $this->assertDatabaseMissing('push_subscriptions', ['user_id' => $owner->id]);
        $this->assertDatabaseHas('push_subscriptions', ['user_id' => $other->id]);
    }

    public function test_push_is_dispatched_only_after_commit(): void
    {
        Queue::fake();
        $user = User::factory()->create(['activo' => true]);

        DB::beginTransaction();
        $notification = Notificacion::enviar($user->id, 'Nueva tarea secreta', 'Contenido jurídico sensible', 'tarea');
        Queue::assertNothingPushed();
        DB::commit();

        Queue::assertPushed(SendWebPushNotification::class, fn ($job) => $job->notificationId === $notification->id);
    }

    public function test_visible_payload_is_generic_and_deep_link_remains_internal(): void
    {
        $user = User::factory()->create(['activo' => true]);
        $notification = Notificacion::create([
            'user_id' => $user->id,
            'tipo' => 'tarea',
            'titulo' => 'Expediente de Persona Reservada',
            'mensaje' => 'Documento 123456 con observaciones confidenciales',
            'leido' => false,
            'created_at' => now(),
        ]);

        $payload = app(WebPushService::class)->payload($notification);

        $this->assertSame('Sistema Jurídico', $payload['title']);
        $this->assertSame('Tienes una nueva tarea.', $payload['body']);
        $this->assertSame('/dashboard', $payload['data']['url']);
        $this->assertStringNotContainsString('123456', json_encode($payload));
        $this->assertStringNotContainsString('Persona Reservada', json_encode($payload));
    }

    public function test_expired_endpoint_is_removed_without_throwing(): void
    {
        config()->set('webpush.vapid', ['subject' => 'https://example.test', 'public_key' => 'public', 'private_key' => 'private']);
        $user = User::factory()->create(['activo' => true]);
        $stored = PushSubscription::create([
            'user_id' => $user->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.test/expired'),
            'endpoint' => 'https://push.example.test/expired',
            'p256dh' => str_repeat('p', 88),
            'auth' => str_repeat('a', 24),
            'content_encoding' => 'aes128gcm',
        ]);
        $notification = Notificacion::create([
            'user_id' => $user->id, 'tipo' => 'mensaje', 'titulo' => 'Privado',
            'mensaje' => 'Contenido privado', 'leido' => false, 'created_at' => now(),
        ]);
        $report = new MessageSentReport(
            new PsrRequest('POST', $stored->endpoint),
            new PsrResponse(410),
            false,
            'Gone',
        );
        $client = Mockery::mock(WebPush::class);
        $client->shouldReceive('setDefaultOptions')->once()->andReturnSelf();
        $client->shouldReceive('sendOneNotification')->once()->andReturn($report);

        $this->assertSame(0, (new WebPushService($client))->send($notification));
        $this->assertDatabaseMissing('push_subscriptions', ['id' => $stored->id]);
    }
}
