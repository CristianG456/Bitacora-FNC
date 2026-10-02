<?php

namespace Tests\Feature\Auth;

use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LoginAndUserCreationUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_exposes_accessible_password_toggle_and_safe_domain_helpers(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();
        $passwordMarkup = 'type='.chr(34).'password'.chr(34).' name='.chr(34).'password'.chr(34);

        $this->assertStringContainsString($passwordMarkup, $html);
        $this->assertStringContainsString('@cafedecolombia.com', $html);
        $this->assertStringContainsString('@cafedecolombia.com.co', $html);
        $this->assertStringContainsString('localPart + option.value', $html);
        $this->assertStringContainsString('localPart.includes', $html);
        $this->assertStringContainsString('emailInput.value.includes', $html);
        $this->assertStringContainsString('loginPasswordInput.type', $html);
        $this->assertStringContainsString('Ver contraseña', $html);
        $this->assertStringContainsString('Ocultar contraseña', $html);
        $this->assertStringNotContainsString('localStorage', $html);
    }

    public function test_admin_user_form_has_independent_toggles_and_keeps_confirmation_validation(): void
    {
        Mail::fake();
        $adminRole = Rol::create(['nombre' => 'Administrador']);
        $userRole = Rol::create(['nombre' => 'Usuario']);
        $admin = User::factory()->create(['rol_id' => $adminRole->id, 'activo' => true, 'force_password_change' => false]);
        $passwordMarkup = 'type='.chr(34).'password'.chr(34);

        $html = $this->actingAs($admin)->get(route('usuarios.crear'))->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, $passwordMarkup));
        $this->assertStringContainsString('input[name=password], input[name=password_confirmation]', $html);
        $this->assertStringContainsString('confirmación de contraseña', $html);
        $this->assertStringContainsString('aria-pressed', $html);

        $datos = [
            'name' => 'Nuevo Usuario',
            'email' => 'nuevo@cafedecolombia.com',
            'password' => 'ClaveSegura123',
            'password_confirmation' => 'NoCoincide123',
            'rol_id' => $userRole->id,
            'activo' => '1',
        ];
        $this->actingAs($admin)->post(route('usuarios.guardar'), $datos)->assertSessionHasErrors('password');
        $this->assertDatabaseMissing('users', ['email' => $datos['email']]);

        $datos['password_confirmation'] = $datos['password'];
        $this->actingAs($admin)->post(route('usuarios.guardar'), $datos)->assertRedirect(route('usuarios.index'));
        $usuario = User::where('email', $datos['email'])->firstOrFail();
        $this->assertTrue(Hash::check($datos['password'], $usuario->password));
    }
}
