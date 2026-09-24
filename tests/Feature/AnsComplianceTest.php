<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\AnsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnsComplianceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario'] as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_case_creation_copies_each_configured_ans_as_an_immutable_snapshot(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-22 10:00:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Juridica ANS');
        $configuraciones = [
            ['Contratos', 'CT', 2, '2026-09-24'],
            ['Convenios', 'CV', 2, '2026-09-24'],
            ['Derechos de petición', 'DP', 15, '2026-10-07'],
            ['Proceso disciplinario', 'PD', 4, '2026-09-26'],
        ];

        foreach ($configuraciones as [$nombre, $codigo, $dias, $limite]) {
            $tipo = TipoProceso::create([
                'nombre' => $nombre,
                'codigo' => $codigo,
                'activo' => true,
                'ans_dias' => $dias,
                'ans_tipo_dias' => 'calendario',
            ]);
            $subtipo = SubtipoProceso::create([
                'tipo_id' => $tipo->id,
                'nombre' => 'General',
                'codigo' => 'GEN',
                'activo' => true,
            ]);

            $this->actingAs($juridica)->post(route('casos.guardar'), [
                'tipo_proceso_id' => $tipo->id,
                'subtipo_proceso_id' => $subtipo->id,
                'descripcion' => 'Caso de validación ANS '.$codigo,
                'tipo_solicitante' => 'persona',
                'nombre_solicitante' => 'Solicitante '.$codigo,
                'documento_solicitante' => null,
                'fecha_solicitud' => '2026-09-22',
            ])->assertRedirect();

            $caso = Caso::where('tipo_id', $tipo->id)->latest('id')->firstOrFail();
            $this->assertSame($dias, $caso->ans_dias);
            $this->assertSame('calendario', $caso->ans_tipo_dias);
            $this->assertSame('2026-09-22', $caso->ans_fecha_inicio->toDateString());
            $this->assertSame($limite, $caso->ans_fecha_limite->toDateString());
            $this->assertSame('vigente', $caso->ans_estado);
            $this->assertDatabaseHas('bitacoras', [
                'caso_id' => $caso->id,
                'modulo' => 'ANS',
                'accion' => 'Asignar',
            ]);
        }
    }

    public function test_changing_type_configuration_does_not_modify_existing_case_snapshot(): void
    {
        $admin = $this->user('Administrador', 'Administrador ANS');
        [$tipo, $subtipo] = $this->tipoConSubtipo('Contratos', 'CT', 2);
        $caso = $this->caso($admin, $tipo, $subtipo, '2026-09-22', '2026-09-24');

        $this->actingAs($admin)->put(route('tipos.update', $tipo), [
            'nombre' => $tipo->nombre,
            'codigo' => $tipo->codigo,
            'descripcion' => null,
            'ans_dias' => 5,
            'ans_tipo_dias' => 'calendario',
        ])->assertRedirect(route('tipos.index'));

        $this->assertSame(5, $tipo->refresh()->ans_dias);
        $this->assertSame(2, $caso->refresh()->ans_dias);
        $this->assertSame('2026-09-24', $caso->ans_fecha_limite->toDateString());
    }

    public function test_alerts_are_generated_for_each_threshold_and_are_idempotent(): void
    {
        $juridica = $this->user('Juridica', 'Juridica');
        $asignado = $this->user('Usuario', 'Asignado');
        [$tipo, $subtipo] = $this->tipoConSubtipo('Solicitudes generales', 'SG', 4);
        $hoy = CarbonImmutable::parse('2026-10-02', 'America/Bogota');

        $preventivo = $this->caso($juridica, $tipo, $subtipo, '2026-10-02', '2026-10-07', $asignado);
        $critico = $this->caso($juridica, $tipo, $subtipo, '2026-10-02', '2026-10-03', $asignado);
        $vencido = $this->caso($juridica, $tipo, $subtipo, '2026-09-20', '2026-09-29', $asignado);

        $service = app(AnsService::class);
        $this->assertSame(4, $service->procesarAlertas($hoy));

        $this->assertSame('preventivo', $preventivo->refresh()->ans_estado);
        $this->assertSame('critico', $critico->refresh()->ans_estado);
        $this->assertSame('vencido', $vencido->refresh()->ans_estado);
        $this->assertSame(3, Notificacion::where('user_id', $asignado->id)->where('tipo', 'ans')->count());
        $this->assertDatabaseHas('bitacoras', ['caso_id' => $preventivo->id, 'accion' => 'Alerta preventiva']);
        $this->assertDatabaseHas('bitacoras', ['caso_id' => $critico->id, 'accion' => 'Alerta crítica']);
        $this->assertDatabaseHas('bitacoras', ['caso_id' => $vencido->id, 'accion' => 'Fecha límite alcanzada']);
        $this->assertDatabaseHas('bitacoras', ['caso_id' => $vencido->id, 'accion' => 'Incumplimiento']);

        $notificaciones = Notificacion::count();
        $bitacoras = Bitacora::count();
        $this->assertSame(0, $service->procesarAlertas($hoy));
        $this->assertSame($notificaciones, Notificacion::count());
        $this->assertSame($bitacoras, Bitacora::count());
    }

    public function test_finalized_case_stops_alerts_while_active_case_continues(): void
    {
        $juridica = $this->user('Juridica', 'Juridica');
        $asignado = $this->user('Usuario', 'Asignado');
        [$tipo, $subtipo] = $this->tipoConSubtipo('Tutelas', 'TT', 4);
        $finalizado = $this->caso($juridica, $tipo, $subtipo, '2026-09-20', '2026-09-25', $asignado, 'Finalizado');
        $activo = $this->caso($juridica, $tipo, $subtipo, '2026-09-20', '2026-09-25', $asignado);

        $eventos = app(AnsService::class)->procesarAlertas(
            CarbonImmutable::parse('2026-09-28', 'America/Bogota')
        );

        $this->assertSame(2, $eventos);
        $this->assertFalse(Bitacora::where('caso_id', $finalizado->id)->where('modulo', 'ANS')->exists());
        $this->assertTrue(Bitacora::where('caso_id', $activo->id)->where('accion', 'Incumplimiento')->exists());
    }

    public function test_business_day_mode_is_ready_without_changing_calendar_day_defaults(): void
    {
        $tipo = TipoProceso::create([
            'nombre' => 'Proceso hábil',
            'codigo' => 'PH',
            'activo' => true,
            'ans_dias' => 2,
            'ans_tipo_dias' => 'habiles',
        ]);

        $snapshot = app(AnsService::class)->snapshot(
            $tipo,
            CarbonImmutable::parse('2026-09-25', 'America/Bogota')
        );

        $this->assertSame('2026-09-29', $snapshot['ans_fecha_limite']);
    }

    public function test_finalizing_a_case_closes_ans_tracking_and_consultor_cannot_change_configuration(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-22 10:00:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Juridica');
        $consultor = $this->user('Consultor', 'Consultor');
        [$tipo, $subtipo] = $this->tipoConSubtipo('Convenios', 'CV', 2);
        $caso = $this->caso($juridica, $tipo, $subtipo, '2026-09-22', '2026-09-24', $juridica);
        $caso->tareas()->create([
            'user_id' => $juridica->id,
            'descripcion' => 'Tarea ANS completada',
            'estado' => 'Completada',
            'fecha_fin' => '2026-09-22',
        ]);

        $this->actingAs($juridica)
            ->post(route('casos.finalizar', $caso))
            ->assertRedirect();

        $this->assertSame('Finalizado', $caso->refresh()->estado);
        $this->assertSame('cumplido', $caso->ans_estado);
        $this->assertDatabaseHas('bitacoras', [
            'caso_id' => $caso->id,
            'modulo' => 'ANS',
            'accion' => 'Cerrar seguimiento',
        ]);

        $this->actingAs($consultor)->put(route('tipos.update', $tipo), [
            'nombre' => $tipo->nombre,
            'codigo' => $tipo->codigo,
            'ans_dias' => 30,
            'ans_tipo_dias' => 'calendario',
        ])->assertForbidden();
        $this->assertSame(2, $tipo->refresh()->ans_dias);
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

    private function tipoConSubtipo(string $nombre, string $codigo, int $dias): array
    {
        $tipo = TipoProceso::create([
            'nombre' => $nombre,
            'codigo' => $codigo,
            'activo' => true,
            'ans_dias' => $dias,
            'ans_tipo_dias' => 'calendario',
        ]);
        $subtipo = SubtipoProceso::create([
            'tipo_id' => $tipo->id,
            'nombre' => 'General',
            'codigo' => 'GEN',
            'activo' => true,
        ]);

        return [$tipo, $subtipo];
    }

    private function caso(
        User $creador,
        TipoProceso $tipo,
        SubtipoProceso $subtipo,
        string $inicio,
        string $limite,
        ?User $asignado = null,
        string $estado = 'Pendiente',
    ): Caso {
        $solicitante = Solicitante::create([
            'nombre' => 'Solicitante ANS',
            'documento' => null,
            'tipo_solicitante' => 'persona',
        ]);
        $caso = Caso::create([
            'radicado' => $tipo->codigo.'-GEN-'.uniqid(),
            'tipo_id' => $tipo->id,
            'subtipo_id' => $subtipo->id,
            'descripcion' => 'Caso ANS',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => $inicio,
            'ans_fecha_inicio' => $inicio,
            'ans_dias' => $tipo->ans_dias,
            'ans_tipo_dias' => $tipo->ans_tipo_dias,
            'ans_fecha_limite' => $limite,
            'ans_estado' => 'vigente',
            'estado' => $estado,
            'fecha_inicio' => $inicio,
            'created_by' => $creador->id,
        ]);

        if ($asignado) {
            $caso->usuarios()->attach($asignado->id, [
                'estado' => 'Pendiente',
                'activo' => true,
                'fecha_asignacion' => now(),
            ]);
        }

        return $caso;
    }
}
