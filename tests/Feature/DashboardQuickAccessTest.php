<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardQuickAccessTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;
    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario', 'Abogado'] as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }

        $this->tipo = TipoProceso::create([
            'nombre' => 'Derecho de petición',
            'codigo' => 'DP',
            'activo' => true,
            'ans_dias' => 15,
            'ans_tipo_dias' => 'habiles',
        ]);
        $this->subtipo = SubtipoProceso::create([
            'tipo_id' => $this->tipo->id,
            'nombre' => 'Petición empresarial',
            'codigo' => 'EMP',
            'activo' => true,
        ]);
    }

    public function test_dashboard_exposes_live_search_and_quick_audit_modal(): void
    {
        $consultor = $this->user('Consultor', 'Consultora de pruebas');
        $caso = $this->caso('DP-EMP-20260905-0001', 'Caficultores Unidos SAS', '900456789-1');

        $response = $this->actingAs($consultor)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Consulta rápida de casos')
            ->assertSee('Buscar por radicado, solicitante, documento, NIT, fecha o responsable')
            ->assertSee('data-dashboard-audit-modal', false)
            ->assertSee(route('dashboard.casos.bitacora', $caso, false), false)
            ->assertSee('Bitácora');

        $contenido = $response->getContent();
        $this->assertTrue(
            strpos($contenido, 'dashboard-stats-grid') < strpos($contenido, 'data-dashboard-search'),
            'La consulta rápida debe aparecer después de las burbujas de resumen.'
        );
    }

    public function test_consultant_can_search_all_supported_case_fields_with_partial_matches(): void
    {
        $consultor = $this->user('Consultor', 'Consultora global');
        $responsable = $this->user('Usuario', 'María Responsable');
        $caso = $this->caso('DP-EMP-20260905-0042', 'Caficultores Unidos SAS', '900456789-1', $responsable);

        foreach (['0042', 'Caficultores', '900456', '05/09/2026', 'María Respon', 'respuesta contractual', 'Petición empresarial'] as $termino) {
            $this->actingAs($consultor)
                ->getJson(route('dashboard.casos.buscar', ['q' => $termino]))
                ->assertOk()
                ->assertJsonPath('data.0.id', $caso->id)
                ->assertJsonPath('data.0.radicado', $caso->radicado)
                ->assertJsonPath('data.0.bitacora_url', route('dashboard.casos.bitacora', $caso, false));
        }

        $this->actingAs($consultor)
            ->getJson(route('dashboard.casos.buscar', ['q' => 'criterio inexistente']))
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('message', 'No se encontraron casos con ese criterio.');

        $this->actingAs($consultor)
            ->getJson(route('dashboard.casos.buscar', ['q' => '   ']))
            ->assertUnprocessable();
    }

    public function test_quick_audit_is_complete_chronological_and_does_not_change_case_state(): void
    {
        $consultor = $this->user('Consultor', 'Consultora global');
        $responsable = $this->user('Usuario', 'María Responsable');
        $caso = $this->caso('DP-EMP-20260905-0043', 'Empresa Auditada SAS', '901111111-2', $responsable);

        $primero = Bitacora::create([
            'caso_id' => $caso->id,
            'user_id' => $responsable->id,
            'modulo' => 'Casos',
            'accion' => 'Crear',
            'descripcion' => 'CASO CREADO con responsables iniciales',
            'metadata' => ['actor' => ['nombre' => $responsable->name, 'rol' => 'Usuario']],
            'created_at' => now()->subHour(),
        ]);
        $ultimo = Bitacora::create([
            'caso_id' => $caso->id,
            'user_id' => $responsable->id,
            'modulo' => 'Tareas',
            'accion' => 'Completar',
            'descripcion' => 'TAREA COMPLETADA dentro del ANS',
            'metadata' => ['actor' => ['nombre' => $responsable->name, 'rol' => 'Usuario']],
            'created_at' => now(),
        ]);

        $estadoInicial = $caso->estado;
        $response = $this->actingAs($consultor)->get(route('dashboard.casos.bitacora', $caso));

        $response->assertOk()
            ->assertSee($caso->radicado)
            ->assertSee('Empresa Auditada SAS')
            ->assertSee('Bitácora completa')
            ->assertSee('Responsables relacionados')
            ->assertSee('Asignadas:')
            ->assertSee('Pendientes:')
            ->assertSee('Fuera de ANS:')
            ->assertDontSee('Promedio atención:')
            ->assertSeeInOrder([$ultimo->descripcion, $primero->descripcion]);
        $this->assertSame($estadoInicial, $caso->fresh()->estado);
    }

    public function test_regular_user_only_searches_and_opens_actively_assigned_cases(): void
    {
        $usuario = $this->user('Usuario', 'Usuario limitado');
        $visible = $this->caso('VISIBLE-2026', 'Solicitante visible', 'DOC-VISIBLE', $usuario);
        $oculto = $this->caso('OCULTO-2026', 'Solicitante oculto', 'DOC-OCULTO');

        $this->actingAs($usuario)->getJson(route('dashboard.casos.buscar', ['q' => 'VISIBLE']))
            ->assertOk()->assertJsonPath('data.0.id', $visible->id);
        $this->actingAs($usuario)->getJson(route('dashboard.casos.buscar', ['q' => 'OCULTO']))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($usuario)->get(route('dashboard.casos.bitacora', $visible))->assertOk();
        $this->actingAs($usuario)->get(route('dashboard.casos.bitacora', $oculto))->assertNotFound();
    }

    public function test_quick_access_endpoints_require_authentication(): void
    {
        $caso = $this->caso('AUTH-2026', 'Solicitante protegido', 'DOC-AUTH');

        $this->get(route('dashboard.casos.buscar', ['q' => 'AUTH']))->assertRedirect(route('login'));
        $this->get(route('dashboard.casos.bitacora', $caso))->assertRedirect(route('login'));
    }

    private function user(string $rol, string $nombre): User
    {
        return User::factory()->create([
            'name' => $nombre,
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
            'force_password_change' => false,
        ]);
    }

    private function caso(string $radicado, string $solicitanteNombre, string $documento, ?User $responsable = null): Caso
    {
        $creador = User::whereHas('role', fn ($query) => $query->where('nombre', 'Juridica'))->first()
            ?? $this->user('Juridica', 'Jurídica creadora '.$radicado);
        $solicitante = Solicitante::create([
            'nombre' => $solicitanteNombre,
            'documento' => $documento,
            'tipo_solicitante' => 'empresa',
        ]);
        $caso = Caso::create([
            'radicado' => $radicado,
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Análisis y respuesta contractual prioritaria',
            'solicitante_id' => $solicitante->id,
            'solicitante_nombre_snapshot' => $solicitanteNombre,
            'solicitante_documento_snapshot' => $documento,
            'solicitante_tipo_snapshot' => 'empresa',
            'fecha_solicitud' => '2026-09-05',
            'estado' => 'Pendiente',
            'created_by' => $creador->id,
        ]);

        if ($responsable) {
            $caso->usuarios()->attach($responsable->id, [
                'estado' => 'Pendiente',
                'activo' => true,
                'fecha_asignacion' => now(),
            ]);
        }

        return $caso;
    }
}
