<?php

namespace Tests\Feature;

use App\Models\BackupHistory;
use App\Models\ConfiguracionRespaldo;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupStatusPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_backup_state_is_reported_without_invented_data(): void
    {
        $response = $this->actingAs($this->administrator())->get('/respaldos');

        $response->assertOk();
        $response->assertSee('Servidor SMTP');
        $response->assertSee('Cloudflare R2 (S3)');
        $response->assertSee('Estado de Respaldos');
        $response->assertSee('ADVERTENCIA');
        $response->assertSee('No existe una configuración de respaldos guardada.');
        $response->assertSee('No existe historial estructurado disponible.');
        $response->assertSee('Configuración avanzada');
    }

    public function test_hybrid_fragment_and_connection_test_endpoints_remain_available(): void
    {
        $user = $this->administrator();

        $fragment = $this->actingAs($user)->get('/respaldos', [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $fragment->assertOk();
        $fragment->assertSee('id="module-fragment"', false);
        $fragment->assertSee('Estado de Respaldos');

        $this->actingAs($user)
            ->postJson('/respaldos/probar')
            ->assertOk()
            ->assertJson(['success' => false]);
        $this->actingAs($user)
            ->postJson('/respaldos/probar-r2')
            ->assertOk()
            ->assertJson(['success' => false]);
    }
    public function test_real_history_is_rendered_without_exposing_backup_password(): void
    {
        ConfiguracionRespaldo::create([
            'sender_email' => 'respaldos@example.test',
            'sender_name' => 'Respaldos',
            'recipient_emails' => ['destino@example.test'],
            'backup_frequency' => 'diario',
            'backup_time' => '23:30',
            'backup_password' => 'clave-zip-super-secreta',
            'max_backups' => 10,
            'retention_days' => 15,
            'r2_retention_days' => 30,
            'r2_enabled' => true,
            'is_active' => true,
        ]);
        BackupHistory::create([
            'file_name' => 'backup_real.zip',
            'file_path' => '/ruta/no-publica/backup_real.zip',
            'file_size' => 2097152,
            'backup_type' => 'automatico',
            'status' => 'exitoso',
            'storage_provider' => 'r2',
            'storage_path' => 'respaldos/backup_real.zip',
        ]);

        $response = $this->actingAs($this->administrator())->get('/respaldos');

        $response->assertOk();
        $response->assertSee('OPERATIVO');
        $response->assertSee('Cloudflare R2');
        $response->assertSee('2.00 MB');
        $response->assertSee('10 copias');
        $response->assertSee('30 días');
        $response->assertDontSee('clave-zip-super-secreta');
        $response->assertDontSee('/ruta/no-publica');
    }

    public function test_recent_error_is_sanitized(): void
    {
        BackupHistory::create([
            'file_name' => 'backup_fallido.zip',
            'file_path' => '/ruta/interna/secreta.zip',
            'backup_type' => 'manual',
            'status' => 'fallido',
            'storage_provider' => 'local',
            'error_message' => 'stack trace con credenciales y /ruta/interna',
        ]);

        $response = $this->actingAs($this->administrator())->get('/respaldos');

        $response->assertOk();
        $response->assertSee('ERROR');
        $response->assertSee('El último intento de respaldo no se completó correctamente.');
        $response->assertDontSee('stack trace con credenciales');
        $response->assertDontSee('/ruta/interna');
    }

    private function administrator(): User
    {
        $role = Rol::firstOrCreate(['nombre' => 'Administrador']);

        return User::factory()->create([
            'rol_id' => $role->id,
            'activo' => true,
            'force_password_change' => false,
        ]);
    }
}