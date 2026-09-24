<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Observacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SolicitudCorreccionTarea;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TareaVersion;
use App\Models\TipoDocumentoSolicitante;
use App\Models\TipoProceso;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Database\Seeders\TipoDocumentoSolicitanteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ControlledCorrectionsAndLawyerTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;
    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario', 'Abogado'] as $nombre) {
            Rol::firstOrCreate(['nombre' => $nombre]);
        }

        $this->tipo = TipoProceso::create([
            'nombre' => 'Proceso base',
            'codigo' => 'PB',
            'activo' => true,
            'ans_dias' => 5,
            'ans_tipo_dias' => 'calendario',
        ]);
        $this->subtipo = SubtipoProceso::create([
            'tipo_id' => $this->tipo->id,
            'nombre' => 'Subtipo base',
            'codigo' => 'SB',
            'activo' => true,
        ]);
    }

    public function test_catalog_and_lawyer_seeders_are_idempotent(): void
    {
        $this->seed(TipoDocumentoSolicitanteSeeder::class);
        $this->seed(TipoDocumentoSolicitanteSeeder::class);
        $this->seed(RolesSeeder::class);
        $this->seed(RolesSeeder::class);

        $this->assertSame(8, TipoDocumentoSolicitante::count());
        $this->assertSame(1, Rol::where('nombre', 'Abogado')->count());
        $this->assertTrue(TipoDocumentoSolicitante::where('codigo', 'NIT')->where('aplica_a', 'juridica')->exists());
    }

    public function test_case_creation_accepts_optional_catalog_document_and_preserves_snapshot(): void
    {
        $this->seed(TipoDocumentoSolicitanteSeeder::class);
        $juridica = $this->user('Juridica', 'Jurídica');
        $nit = TipoDocumentoSolicitante::where('codigo', 'NIT')->firstOrFail();

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Caso para persona jurídica',
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'Empresa Uno SAS',
            'tipo_documento_solicitante_id' => $nit->id,
            'documento_solicitante' => '900123456-7',
            'fecha_solicitud' => '2026-09-22',
        ])->assertRedirect();

        $caso = Caso::latest('id')->firstOrFail();
        $this->assertSame('empresa', $caso->solicitante_tipo_snapshot);
        $this->assertSame('900123456-7', $caso->solicitante_documento_snapshot);
        $this->assertSame($nit->id, $caso->solicitante_tipo_documento_id);

        $this->actingAs($juridica)->get(route('casos.crear'))
            ->assertOk()
            ->assertSee('Persona natural')
            ->assertSee('Persona jurídica')
            ->assertSee('id="applicant-document-type"', false)
            ->assertSee('"codigo":"CC"', false)
            ->assertSee('"codigo":"NIT"', false);
    }

    public function test_juridica_corrects_only_one_shared_applicant_case_with_auditable_ans_change(): void
    {
        $this->seed(TipoDocumentoSolicitanteSeeder::class);
        $juridica = $this->user('Juridica', 'Jurídica');
        $usuario = $this->user('Usuario', 'Usuario');
        $solicitante = Solicitante::create([
            'nombre' => 'Nombre original',
            'documento' => 'DOC-COMPARTIDO',
            'tipo_solicitante' => 'persona',
        ]);
        $casoUno = $this->caso($juridica, $solicitante, 'CASO-UNO');
        $casoDos = $this->caso($juridica, $solicitante, 'CASO-DOS');
        $cc = TipoDocumentoSolicitante::where('codigo', 'CC')->firstOrFail();

        $payload = [
            'motivo_correccion' => 'Corrección solicitada por Jurídica',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Nombre corregido',
            'tipo_documento_solicitante_id' => $cc->id,
            'documento_solicitante' => '123456789',
            'fecha_solicitud' => '2026-09-23',
            'descripcion' => 'Descripción corregida',
            'observacion_inicial' => 'Observación corregida',
            'enlace_google_drive' => null,
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
        ];

        $this->actingAs($juridica)->put(route('casos.correccion', $casoUno), $payload)
            ->assertSessionHasErrors('confirmar_recalculo_ans');

        $this->actingAs($usuario)->put(route('casos.correccion', $casoUno), $payload + ['confirmar_recalculo_ans' => '1'])
            ->assertForbidden();

        $this->actingAs($juridica)->put(route('casos.correccion', $casoUno), $payload + ['confirmar_recalculo_ans' => '1'])
            ->assertRedirect(route('casos.show', $casoUno));

        $this->assertSame('Nombre corregido', $casoUno->refresh()->solicitanteNombreActual());
        $this->assertSame('Nombre original', $casoDos->refresh()->solicitanteNombreActual());
        $this->assertSame('Nombre original', $solicitante->refresh()->nombre);
        $this->assertSame('2026-09-28', $casoUno->ans_fecha_limite?->toDateString());

        $evento = Bitacora::where('caso_id', $casoUno->id)->where('accion', 'Corregir')->latest('id')->firstOrFail();
        $this->assertSame('Corrección solicitada por Jurídica', $evento->metadata['motivo']);
        $this->assertNotEmpty($evento->metadata['cambios']);
        $this->assertNotNull($evento->metadata['ans_corregido']);

        $this->actingAs($juridica)->get(route('casos.show', $casoUno))
            ->assertOk()
            ->assertSee('Corregir información')
            ->assertSee('Tipo de documento')
            ->assertSee('CC')
            ->assertSee('Motivo:')
            ->assertSee('Anterior:')
            ->assertSee('Nuevo:');
    }

    public function test_completed_task_correction_requires_approval_is_one_use_and_keeps_task_closed(): void
    {
        $juridica = $this->user('Juridica', 'Jurídica');
        $asignado = $this->user('Usuario', 'Asignado');
        $caso = $this->caso($juridica, null, 'TAREA-CORR');
        $this->assign($caso, $asignado);
        $tarea = $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Tarea completada que requiere corrección',
            'estado' => 'Completada',
            'fecha_fin' => '2026-09-22 10:00:00',
        ]);
        Observacion::create(['tarea_id' => $tarea->id, 'user_id' => $asignado->id, 'contenido' => 'Texto original']);

        $this->actingAs($asignado)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('Solicitar corrección');

        $this->actingAs($asignado)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), [
            'motivo' => 'Debo corregir el resultado',
        ])->assertRedirect();
        $solicitud = SolicitudCorreccionTarea::firstOrFail();

        $this->actingAs($asignado)->get(route('casos.show', $caso))
            ->assertSee('Pendiente de aprobación por Jurídica');

        $this->actingAs($asignado)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Resultado corregido',
            'fecha_fin' => '2026-09-22 11:00:00',
        ])->assertForbidden();

        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud))->assertRedirect();
        $this->actingAs($asignado)->get(route('casos.show', $caso))
            ->assertSee('Corrección autorizada')
            ->assertSee('Corregir tarea');
        $this->actingAs($asignado)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Resultado corregido',
            'fecha_fin' => '2026-09-22 11:00:00',
        ])->assertRedirect();

        $this->assertSame('Completada', $tarea->refresh()->estado);
        $this->assertSame('Resultado corregido', $tarea->observacion->contenido);
        $this->assertSame('utilizada', $solicitud->refresh()->estado);
        $this->assertFalse((bool) $solicitud->activa);
        $this->assertSame(1, TareaVersion::where('tarea_id', $tarea->id)->count());
        $this->actingAs($asignado)->get(route('casos.show', $caso))
            ->assertDontSee('Corrección autorizada');
        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertSee('Tarea corregida')
            ->assertSee('Resultado corregido');

        $this->actingAs($asignado)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Segundo uso prohibido',
            'fecha_fin' => '2026-09-22 12:00:00',
        ])->assertForbidden();
    }

    public function test_juridica_can_reject_task_correction_and_assignee_cannot_reuse_it(): void
    {
        $juridica = $this->user('Juridica', 'Jurídica');
        $asignado = $this->user('Usuario', 'Asignado');
        $caso = $this->caso($juridica, null, 'TAREA-RECH');
        $this->assign($caso, $asignado);
        $tarea = $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Tarea completada para rechazo',
            'estado' => 'Completada',
            'fecha_fin' => now(),
        ]);
        Observacion::create(['tarea_id' => $tarea->id, 'user_id' => $asignado->id, 'contenido' => 'Texto original']);
        $this->actingAs($asignado)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), ['motivo' => 'Quiero cambiar el texto']);
        $solicitud = SolicitudCorreccionTarea::firstOrFail();

        $this->actingAs($juridica)->post(route('tareas.correccion.rechazar', $solicitud), [
            'motivo_rechazo' => 'No existe soporte suficiente',
        ])->assertRedirect();

        $this->assertSame('rechazada', $solicitud->refresh()->estado);
        $this->assertSame('No existe soporte suficiente', $solicitud->motivo_rechazo);
        $this->assertFalse((bool) $solicitud->activa);
    }

    public function test_lawyer_has_global_read_but_only_assigned_task_actions_and_signature_is_idempotent(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $abogado = $this->user('Abogado', 'Abogada');
        $usuario = $this->user('Usuario', 'Usuario');
        $caso = $this->caso($admin, null, 'CASO-ABOGADO');
        $firma = $caso->tareas()->create([
            'user_id' => $abogado->id,
            'descripcion' => 'Realizar firma del documento',
            'tipo_accion' => 'firma',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($abogado)->get(route('casos.show', $caso))->assertOk()->assertDontSee('Firma realizada');
        $this->actingAs($abogado)->post(route('tareas.completar', [$caso, $firma]))->assertForbidden();

        $this->assign($caso, $abogado);
        $this->actingAs($abogado)->get(route('casos.show', $caso))->assertOk()->assertSee('Firma realizada');
        $this->actingAs($abogado)->post(route('tareas.completar', [$caso, $firma]))->assertRedirect();
        $this->actingAs($abogado)->post(route('tareas.completar', [$caso, $firma]))->assertRedirect();

        $this->assertSame('Completada', $firma->refresh()->estado);
        $this->assertSame('Firma realizada', $firma->observacion->contenido);
        $this->assertSame(1, Observacion::where('tarea_id', $firma->id)->count());

        $this->assign($caso, $usuario);
        $this->actingAs($admin)->post(route('tareas.guardar', $caso), [
            'user_id' => $usuario->id,
            'descripcion' => 'Firma indebidamente asignada',
            'tipo_accion' => 'firma',
        ])->assertSessionHasErrors('user_id');
    }

    public function test_controlled_multiuser_flow_reaches_normal_finalization_without_real_data(): void
    {
        $this->seed(TipoDocumentoSolicitanteSeeder::class);
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Usuario de prueba');
        $abogado = $this->user('Abogado', 'Abogado de prueba');
        $nit = TipoDocumentoSolicitante::where('codigo', 'NIT')->firstOrFail();

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'PRUEBA CONTROLADA MULTIUSUARIO',
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'PRUEBA PERSONA JURÍDICA SAS',
            'tipo_documento_solicitante_id' => $nit->id,
            'documento_solicitante' => 'NIT-PRUEBA-001',
            'fecha_solicitud' => '2026-09-23',
            'usuarios' => [$usuario->id, $abogado->id],
            'tareas' => [
                $usuario->id => ['Completar análisis jurídico de prueba'],
                $abogado->id => ['Firmar documento jurídico de prueba'],
            ],
            'tipos_tarea' => [
                $usuario->id => ['normal'],
                $abogado->id => ['firma'],
            ],
        ])->assertRedirect();

        $caso = Caso::latest('id')->firstOrFail();
        $tareaUsuario = $caso->tareas()->where('user_id', $usuario->id)->firstOrFail();
        $tareaFirma = $caso->tareas()->where('user_id', $abogado->id)->firstOrFail();

        $this->actingAs($usuario)->post(route('tareas.completar', [$caso, $tareaUsuario]), [
            'observacion' => 'Resultado original de la tarea',
        ])->assertRedirect();
        $this->actingAs($usuario)->post(route('tareas.correccion.solicitar', [$caso, $tareaUsuario]), [
            'motivo' => 'Se detectó un dato por corregir',
        ])->assertRedirect();
        $solicitud = SolicitudCorreccionTarea::where('tarea_id', $tareaUsuario->id)->firstOrFail();
        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud))->assertRedirect();
        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tareaUsuario, $solicitud]), [
            'observacion' => 'Resultado definitivo corregido',
            'fecha_fin' => '2026-09-23 11:00:00',
        ])->assertRedirect();
        $this->actingAs($abogado)->post(route('tareas.completar', [$caso, $tareaFirma]))->assertRedirect();

        $this->assertSame('Completado', $caso->refresh()->estado);
        $this->assertSame('Firma realizada', $tareaFirma->refresh()->observacion->contenido);
        $this->assertSame(1, TareaVersion::where('tarea_id', $tareaUsuario->id)->count());
        $this->assertTrue($juridica->notificaciones()->where('tipo', 'correccion_tarea')->exists());
        $this->assertTrue(Bitacora::where('caso_id', $caso->id)->where('accion', 'Corregir tarea')->exists());
        $this->assertTrue(Bitacora::where('caso_id', $caso->id)->where('accion', 'Completar')->where('entidad_id', $tareaFirma->id)->exists());

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $this->assertSame('Finalizado', $caso->refresh()->estado);
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

    private function caso(User $creador, ?Solicitante $solicitante, string $radicado): Caso
    {
        $solicitante ??= Solicitante::create([
            'nombre' => 'Solicitante',
            'documento' => 'DOC-'.uniqid(),
            'tipo_solicitante' => 'persona',
        ]);

        return Caso::create([
            'radicado' => $radicado,
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Descripción inicial del caso',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => '2026-09-20',
            'ans_fecha_inicio' => '2026-09-20',
            'ans_dias' => 5,
            'ans_tipo_dias' => 'calendario',
            'ans_fecha_limite' => '2026-09-25',
            'ans_estado' => 'vigente',
            'estado' => 'Pendiente',
            'fecha_inicio' => '2026-09-20',
            'created_by' => $creador->id,
        ]);
    }

    private function assign(Caso $caso, User $user): void
    {
        $caso->usuarios()->syncWithoutDetaching([
            $user->id => ['estado' => 'Pendiente', 'activo' => true, 'fecha_asignacion' => now()],
        ]);
    }
}
