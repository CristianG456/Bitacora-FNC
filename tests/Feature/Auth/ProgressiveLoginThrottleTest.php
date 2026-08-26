<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ProgressiveLoginThrottleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_third_failure_blocks_only_the_submitted_email_for_one_minute(): void
    {
        $blockedUser = User::factory()->create(['activo' => true]);
        $otherUser = User::factory()->create(['activo' => true]);

        $this->failLogin($blockedUser->email);
        $this->failLogin($blockedUser->email);

        $thirdResponse = $this->failLogin(strtoupper($blockedUser->email));
        $thirdResponse->assertSessionHasErrors([
            'email' => 'Demasiados intentos fallidos. Inténtalo nuevamente en 1 minuto.',
        ]);

        $blockedResponse = $this->post('/login', [
            'email' => $blockedUser->email,
            'password' => 'password',
        ]);
        $this->assertGuest();
        $blockedResponse->assertSessionHasErrors('email');

        $otherResponse = $this->post('/login', [
            'email' => $otherUser->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($otherUser);
        $otherResponse->assertRedirect(route('login', absolute: false));
    }

    public function test_repeated_failure_cycles_increase_the_lock_time(): void
    {
        $user = User::factory()->create(['activo' => true]);

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $firstCycle = $this->failLogin($user->email);
        }
        $firstCycle->assertSessionHasErrors([
            'email' => 'Demasiados intentos fallidos. Inténtalo nuevamente en 1 minuto.',
        ]);

        $this->travel(61)->seconds();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $secondCycle = $this->failLogin($user->email);
        }
        $secondCycle->assertSessionHasErrors([
            'email' => 'Demasiados intentos fallidos. Inténtalo nuevamente en 2 minutos.',
        ]);
    }

    public function test_successful_login_clears_previous_failures(): void
    {
        $user = User::factory()->create(['activo' => true]);

        $this->failLogin($user->email);
        $this->failLogin($user->email);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);
        $this->assertAuthenticatedAs($user);

        $this->post('/logout');
        $response = $this->failLogin($user->email);

        $response->assertSessionHasErrors([
            'email' => 'Credenciales incorrectas',
        ]);
    }

    private function failLogin(string $email)
    {
        return $this->post('/login', [
            'email' => $email,
            'password' => 'wrong-password',
        ]);
    }
}
