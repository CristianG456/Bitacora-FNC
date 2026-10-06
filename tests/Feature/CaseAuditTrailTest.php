<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CaseAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;
    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario', 'Abogado'] as $rol) {
            Rol::create(['nombre' => $rol]);
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
            'nombre' => 'General',
            'codigo' => 'GEN',
            'activo' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_new_case_records_complete_immutable_snapshot_and_individual_events(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:20:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Sara Cano');
        $usuario = $this->user('Usuario', 'Andrés Ruiz');
        $abogado = $this->user('Abogado', 'Yobani Granada');

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Caso con auditoría completa',
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'Empresa Auditada SAS',
            'documento_solicitante' => '900123456-7',
            'fecha_solicitud' => '2026-10-01',
            'usuarios' => [$usuario->id, $abogado->id],
            'tareas' => [
                $usuario->id => ['Elaborar respuesta jurídica'],
                $abogado->id => ['Revisión y firma'],
            ],
            'tipos_tarea' => [
                $usuario->id => ['normal'],
                $abogado->id => ['firma'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $caso = Caso::latest('id')->firstOrFail();
        $creacion = Bitacora::where('caso_id', $caso->id)->get()
            ->first(fn (Bitacora $evento) => ($evento->metadata['event_type'] ?? null) === 'case_created');

        $this->assertNotNull($creacion);
        $this->assertSame('Sara Cano', $creacion->metadata['actor']['nombre']);
        $this->assertSame('Juridica', $creacion->metadata['actor']['rol']);
        $this->assertSame($caso->radicado, $creacion->metadata['caso']['radicado']);
        $this->assertSame('Derecho de petición', $creacion->metadata['caso']['tipo']);
        $this->assertSame('General', $creacion->metadata['caso']['subtipo']);
        $this->assertSame('Empresa Auditada SAS', $creacion->metadata['solicitante']['nombre']);
        $this->assertSame(15, $creacion->metadata['ans']['dias_configurados']);
        $this->assertSame('habiles', $creacion->metadata['ans']['tipo_dias']);
        $this->assertCount(2, $creacion->metadata['responsables']);
        $this->assertSame('Elaborar respuesta jurídica', $creacion->metadata['responsables'][0]['tareas'][0]['tarea']['descripcion']);
        $this->assertSame('Revisión y firma', $creacion->metadata['responsables'][1]['tareas'][0]['tarea']['descripcion']);
        $this->assertSame('Firma', $creacion->metadata['responsables'][1]['tareas'][0]['tarea']['tipo']);

        $eventos = Bitacora::where('caso_id', $caso->id)->get();
        $this->assertSame(2, $eventos->filter(fn ($e) => ($e->metadata['event_type'] ?? null) === 'responsible_assigned')->count());
        $this->assertSame(2, $eventos->filter(fn ($e) => ($e->metadata['event_type'] ?? null) === 'task_assigned')->count());

        $usuario->update(['name' => 'Nombre cambiado']);
        $this->assertSame('Andrés Ruiz', $creacion->fresh()->metadata['responsables'][0]['nombre']);

        $cantidad = Bitacora::where('caso_id', $caso->id)->count();
        $this->actingAs($juridica)->get(route('casos.show', $caso))->assertOk()
            ->assertSee('Resumen del caso')
            ->assertSee('Responsables y responsabilidades iniciales')
            ->assertSee('Elaborar respuesta jurídica')
            ->assertSee('ANS restante');
        $this->actingAs($juridica)->get(route('casos.show', $caso))->assertOk();
        $this->assertSame($cantidad, Bitacora::where('caso_id', $caso->id)->count());
    }

    public function test_history_pdf_presents_audit_metadata_without_technical_json(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $actor = $this->user('Juridica', 'Sara Cano');
        $responsable = $this->user('Usuario', 'Andrés Ruiz');
        $caso = $this->caso($admin, '2026-10-06');

        Bitacora::registrar('Tareas', 'Crear', 'Asignación registrada', $caso->id, 27, $responsable->id, [
            'audit_version' => 1,
            'event_type' => 'task_assigned',
            'actor' => ['id' => $actor->id, 'nombre' => $actor->name, 'rol' => 'Juridica'],
            'responsable' => ['id' => $responsable->id, 'nombre' => $responsable->name, 'rol' => 'Usuario'],
            'tarea' => ['id' => 27, 'descripcion' => 'Revisión jurídica', 'tipo' => 'Normal', 'estado' => 'Pendiente'],
            'caso' => ['radicado' => $caso->radicado],
            'ans' => ['estado' => 'critico', 'dias_configurados' => 4, 'tipo_dias' => 'calendario', 'fecha_limite' => '2026-10-06', 'dias_restantes' => 1],
        ]);

        $this->actingAs($admin)->get(route('historial.exportar.pdf', ['caso_id' => $caso->id]))
            ->assertOk()->assertSee('ASIGNACIÓN DE TAREA')
            ->assertSee('Sara Cano · Juridica')->assertSee('Andrés Ruiz · Usuario')
            ->assertSee('Revisión jurídica')->assertSee('Crítico')
            ->assertDontSee('audit_version')->assertDontSee('event_type')->assertDontSee('task_assigned')
            ->assertDontSee('{id')->assertDontSee('https://', false);
    }

    public function test_self_assignment_and_runtime_task_assignment_distinguish_actor_and_responsible(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 10:00:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Sara Jurídica');
        $caso = $this->caso($juridica, '2026-10-16');

        $this->actingAs($juridica)->post(route('casos.usuarios.asignar', $caso), [
            'user_id' => $juridica->id,
        ])->assertRedirect();

        $asignacion = Bitacora::where('caso_id', $caso->id)->where('accion', 'Asignacion')->firstOrFail();
        $this->assertTrue($asignacion->metadata['autoasignacion']);
        $this->assertSame('Sara Jurídica', $asignacion->metadata['responsable']['nombre']);
        $this->assertStringContainsString('se autoasignó', $asignacion->descripcion);

        $this->actingAs($juridica)->post(route('tareas.guardar', $caso), [
            'user_id' => $juridica->id,
            'descripcion' => 'Revisión del caso histórico',
            'tipo_accion' => 'normal',
        ])->assertRedirect();

        $tarea = Bitacora::where('caso_id', $caso->id)->where('accion', 'Crear')->firstOrFail();
        $this->assertSame('task_assigned', $tarea->metadata['event_type']);
        $this->assertSame('Sara Jurídica', $tarea->metadata['actor']['nombre']);
        $this->assertSame('Sara Jurídica', $tarea->metadata['responsable']['nombre']);
        $this->assertSame('Revisión del caso histórico', $tarea->metadata['tarea']['descripcion']);
        $this->assertNotNull($tarea->metadata['ans']['dias_restantes']);
    }

    public function test_task_completion_records_business_duration_and_case_ans_result(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 09:20:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Andrés');
        $caso = $this->caso($juridica, '2026-10-15');
        $caso->usuarios()->attach($usuario->id, ['estado' => 'Pendiente', 'activo' => true, 'fecha_asignacion' => now()]);

        $this->actingAs($juridica)->post(route('tareas.guardar', $caso), [
            'user_id' => $usuario->id,
            'descripcion' => 'Preparar respuesta',
            'tipo_accion' => 'normal',
        ])->assertRedirect();
        $tarea = $caso->tareas()->firstOrFail();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:10:00', 'America/Bogota'));
        $this->actingAs($usuario)->post(route('tareas.completar', [$caso, $tarea]), [
            'observacion' => 'Respuesta elaborada correctamente',
        ])->assertRedirect();

        $evento = Bitacora::where('caso_id', $caso->id)->where('accion', 'Completar')->firstOrFail();
        $this->assertSame('task_completed', $evento->metadata['event_type']);
        $this->assertSame('Andrés', $evento->metadata['responsable']['nombre']);
        $this->assertSame('A TIEMPO', $evento->metadata['resultado']);
        $this->assertGreaterThan(0, $evento->metadata['dias_habiles_transcurridos']);
        $this->assertStringContainsString('días hábiles', $evento->metadata['tiempo_atencion']);
        $this->assertGreaterThanOrEqual(0, $evento->metadata['ans_restante']);
        $this->assertNull($evento->metadata['retraso_dias']);

        $this->actingAs($juridica)->get(route('historial.exportar.pdf', ['caso_id' => $caso->id]))
            ->assertOk()->assertSee('TAREA COMPLETADA')->assertSee('Preparar respuesta')
            ->assertSee('Asignada:')->assertSee('Completada:')
            ->assertSee('Tiempo de atención:')->assertSee('Resultado:')
            ->assertSee('Observación:')->assertSee('Respuesta elaborada correctamente')
            ->assertDontSee('event_type')->assertDontSee('task_completed');
    }

    public function test_late_signature_and_finalization_record_delay_and_complete_snapshots(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-01 08:00:00', 'America/Bogota'));
        $juridica = $this->user('Juridica', 'Sara');
        $abogado = $this->user('Abogado', 'Yobani');
        $caso = $this->caso($juridica, '2026-10-02');
        $caso->usuarios()->attach($abogado->id, ['estado' => 'Pendiente', 'activo' => true, 'fecha_asignacion' => now()]);

        $this->actingAs($juridica)->post(route('tareas.guardar', $caso), [
            'user_id' => $abogado->id,
            'descripcion' => 'Revisión y firma',
            'tipo_accion' => 'firma',
        ])->assertRedirect();
        $firma = $caso->tareas()->firstOrFail();

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-06 15:40:00', 'America/Bogota'));
        $this->actingAs($abogado)->post(route('tareas.completar', [$caso, $firma]))->assertRedirect();
        $completada = Bitacora::where('caso_id', $caso->id)->where('accion', 'Completar')->firstOrFail();
        $this->assertSame('Firma', $completada->metadata['tarea']['tipo']);
        $this->assertSame('FUERA DE ANS', $completada->metadata['resultado']);
        $this->assertGreaterThan(0, $completada->metadata['retraso_dias']);

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $cierre = Bitacora::where('caso_id', $caso->id)->get()
            ->first(fn (Bitacora $evento) => ($evento->metadata['event_type'] ?? null) === 'case_finalized');
        $this->assertNotNull($cierre);
        $this->assertSame('INCUMPLIDO', $cierre->metadata['resultado']);
        $this->assertGreaterThan(0, $cierre->metadata['retraso_dias']);
        $this->assertNotEmpty($cierre->metadata['tiempo_total']);
        $this->assertSame(1, $cierre->metadata['responsables_relacionados']);
        $this->assertSame(1, $cierre->metadata['tareas_total']);
        $this->assertNotEmpty($cierre->metadata['ultima_tarea_completada']);

        $this->actingAs($juridica)->get(route('historial.show', $caso->fresh()))->assertOk()
            ->assertSee('Resumen del caso')
            ->assertSee('Fuera de ANS')
            ->assertSee('Resultado ANS')
            ->assertSee('INCUMPLIDO');
    }

    public function test_historical_case_summary_is_derived_without_fabricating_events(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Histórico');
        $caso = $this->caso($juridica, null);
        $caso->usuarios()->attach($usuario->id, ['estado' => 'Pendiente', 'activo' => true, 'fecha_asignacion' => now()]);
        $caso->tareas()->create(['user_id' => $usuario->id, 'descripcion' => 'Dato histórico verificable', 'estado' => 'Pendiente']);

        $this->assertSame(0, Bitacora::count());
        $this->actingAs($juridica)->get(route('casos.show', $caso))->assertOk()
            ->assertSee('Resumen del caso')
            ->assertSee('Dato histórico verificable');
        $this->assertSame(0, Bitacora::count());
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

    private function caso(User $creador, ?string $limite): Caso
    {
        $solicitante = Solicitante::create([
            'nombre' => 'Solicitante histórico',
            'documento' => 'DOC-'.uniqid(),
            'tipo_solicitante' => 'persona',
        ]);

        return Caso::create([
            'radicado' => 'AUD-'.uniqid(),
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Caso de auditoría',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => '2026-10-01',
            'ans_fecha_inicio' => $limite ? '2026-10-01' : null,
            'ans_dias' => $limite ? 15 : null,
            'ans_tipo_dias' => $limite ? 'habiles' : null,
            'ans_fecha_limite' => $limite,
            'ans_estado' => $limite ? 'vigente' : null,
            'estado' => 'Pendiente',
            'fecha_inicio' => '2026-10-01',
            'created_by' => $creador->id,
        ]);
    }
}
