<?php

namespace Tests\Feature\Auth;

use App\Models\Caso;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ActiveUserSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_can_authenticate(): void
    {
        $user = User::factory()->create(['activo' => true]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('login', absolute: false));
        $this->get('/')->assertOk();
        $this->get('/casos')->assertOk();
        $this->getJson('/notificaciones/recientes')->assertOk();
    }

    public function test_inactive_user_cannot_authenticate(): void
    {
        $user = User::factory()->create(['activo' => false]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    #[DataProvider('inactiveProtectedRoutes')]
    public function test_authenticated_inactive_user_is_rejected_by_global_web_middleware(
        string $method,
        string $uri,
        array $data,
    ): void {
        $user = User::factory()->create(['activo' => false]);
        if (str_contains($uri, '{caso}')) {
            $uri = str_replace('{caso}', (string) $this->createCaso($user)->id, $uri);
        }

        $response = $this->actingAs($user)->{$method}($uri, $data);

        $this->assertGuest();
        $response->assertRedirect(route('login', absolute: false));
    }

    private function createCaso(User $user): Caso
    {
        $tipo = TipoProceso::create(['nombre' => 'Tipo seguro', 'codigo' => 'SEG', 'activo' => true]);
        $subtipo = SubtipoProceso::create([
            'tipo_id' => $tipo->id,
            'nombre' => 'Subtipo seguro',
            'codigo' => 'SUB',
            'activo' => true,
        ]);
        $solicitante = Solicitante::create(['nombre' => 'Solicitante', 'documento' => 'TEST-SECURITY']);

        return Caso::create([
            'radicado' => 'CASO-SECURITY',
            'tipo_id' => $tipo->id,
            'subtipo_id' => $subtipo->id,
            'solicitante_id' => $solicitante->id,
            'created_by' => $user->id,
            'estado' => 'Pendiente',
        ]);
    }

    public static function inactiveProtectedRoutes(): array
    {
        return [
            'dashboard' => ['get', '/dashboard', []],
            'casos' => ['get', '/casos', []],
            'mensajes' => ['post', '/casos/{caso}/mensajes', ['mensaje' => 'bloqueado']],
            'tareas' => ['post', '/casos/{caso}/tareas', ['nombre' => 'bloqueada']],
            'notificaciones' => ['get', '/notificaciones/recientes', []],
        ];
    }
}