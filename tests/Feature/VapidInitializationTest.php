<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\VapidKeyGenerator;
use App\Support\VapidKeyStore;
use Base64Url\Base64Url;
use Brick\Math\BigInteger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Jose\Component\Core\Util\Ecc\NistCurve;
use Jose\Component\Core\Util\Ecc\PrivateKey;
use Tests\TestCase;

class VapidInitializationTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->keyFile = storage_path('framework/testing/vapid-'.bin2hex(random_bytes(8)).'.json');
        config()->set('webpush.key_file', $this->keyFile);
        config()->set('webpush.source', 'unconfigured');
        config()->set('webpush.vapid', [
            'subject' => 'https://juridica.example.test',
            'public_key' => null,
            'private_key' => null,
        ]);
        $this->app->instance(VapidKeyGenerator::class, new class extends VapidKeyGenerator
        {
            public function generate(): array
            {
                return VapidInitializationTest::validKeys();
            }
        });
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        @unlink($this->keyFile.'.lock');

        parent::tearDown();
    }

    public function test_first_initialization_generates_and_second_reuses_the_same_identity(): void
    {
        $this->artisan('webpush:vapid:init')
            ->expectsOutput('PUBLIC generada: SI')
            ->expectsOutput('PRIVATE generada: SI')
            ->assertSuccessful();

        $first = (new VapidKeyStore)->load($this->keyFile);

        $this->artisan('webpush:vapid:init')
            ->expectsOutput('PUBLIC generada: NO')
            ->expectsOutput('PRIVATE generada: NO')
            ->assertSuccessful();

        $this->assertSame($first, (new VapidKeyStore)->load($this->keyFile));
    }

    public function test_persisted_identity_is_recovered_after_a_fresh_configuration_resolution(): void
    {
        $this->artisan('webpush:vapid:init')->assertSuccessful();
        $store = new VapidKeyStore;
        $persisted = $store->load($this->keyFile);

        $resolved = $store->resolve([
            'subject' => null,
            'public_key' => null,
            'private_key' => null,
        ], 'https://fallback.example.test', $this->keyFile);

        $this->assertSame('persistent_file', $resolved['source']);
        $this->assertSame($persisted, $resolved['vapid']);
    }

    public function test_complete_environment_identity_has_priority_and_is_not_persisted_again(): void
    {
        $keys = self::validKeys();
        config()->set('webpush.source', 'environment');
        config()->set('webpush.vapid', [
            'subject' => 'mailto:juridica@example.test',
            'public_key' => $keys['publicKey'],
            'private_key' => $keys['privateKey'],
        ]);

        $this->artisan('webpush:vapid:init')
            ->expectsOutput('PUBLIC generada: NO')
            ->expectsOutput('PRIVATE generada: NO')
            ->assertSuccessful();

        $this->assertFileDoesNotExist($this->keyFile);
    }

    public function test_partial_environment_identity_fails_without_generating_keys(): void
    {
        config()->set('webpush.source', 'environment_incomplete');

        $this->artisan('webpush:vapid:init')->assertFailed();

        $this->assertFileDoesNotExist($this->keyFile);
    }

    public function test_mismatched_environment_pair_fails_without_persisting_it(): void
    {
        $keys = self::validKeys();
        $keys['privateKey'] = Base64Url::encode(str_repeat(chr(3), 32));
        config()->set('webpush.source', 'environment');
        config()->set('webpush.vapid', [
            'subject' => 'https://juridica.example.test',
            'public_key' => $keys['publicKey'],
            'private_key' => $keys['privateKey'],
        ]);

        $this->artisan('webpush:vapid:init')->assertFailed();

        $this->assertFileDoesNotExist($this->keyFile);
    }

    public function test_existing_subscriptions_prevent_generation_when_identity_is_missing(): void
    {
        $user = User::factory()->create(['activo' => true]);
        PushSubscription::create([
            'user_id' => $user->id,
            'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example.test/existing'),
            'endpoint' => 'https://push.example.test/existing',
            'p256dh' => str_repeat('p', 88),
            'auth' => str_repeat('a', 24),
            'content_encoding' => 'aes128gcm',
        ]);

        $this->artisan('webpush:vapid:init')->assertFailed();

        $this->assertFileDoesNotExist($this->keyFile);
    }

    public function test_frontend_receives_only_the_public_key(): void
    {
        $keys = self::validKeys();
        config()->set('webpush.vapid', [
            'subject' => 'https://juridica.example.test',
            'public_key' => $keys['publicKey'],
            'private_key' => $keys['privateKey'],
        ]);
        $user = User::factory()->create(['activo' => true, 'force_password_change' => false]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('name="web-push-vapid-key" content="'.$keys['publicKey'].'"', false)
            ->assertDontSee($keys['privateKey'], false);
    }

    /** @return array{publicKey: string, privateKey: string} */
    public static function validKeys(): array
    {
        static $keys;
        if (is_array($keys)) {
            return $keys;
        }

        $privateBytes = str_repeat(chr(0), 31).chr(2);
        $point = NistCurve::curve256()
            ->createPublicKey(PrivateKey::create(BigInteger::of(2)))
            ->getPoint();
        $x = hex2bin(str_pad($point->getX()->toBase(16), 64, '0', STR_PAD_LEFT));
        $y = hex2bin(str_pad($point->getY()->toBase(16), 64, '0', STR_PAD_LEFT));

        return $keys = [
            'publicKey' => Base64Url::encode(chr(4).$x.$y),
            'privateKey' => Base64Url::encode($privateBytes),
        ];
    }
}
