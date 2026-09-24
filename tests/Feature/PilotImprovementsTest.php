<?php

namespace Tests\Feature;

use App\Models\Caso;
use App\Models\Mensaje;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PilotImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;
    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario'] as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }

        $this->tipo = TipoProceso::create([
            'nombre' => 'Derecho de petición',
            'codigo' => 'DP',
            'activo' => true,
        ]);
        $this->subtipo = SubtipoProceso::create([
            'tipo_id' => $this->tipo->id,
            'nombre' => 'General',
            'codigo' => 'GEN',
            'activo' => true,
        ]);
    }

    private function user(string $rol, string $name): User
    {
        return User::factory()->create([
            'name' => $name,
            'rol_id' => Rol::where('nombre', $rol)->value('id'),
            'activo' => true,
            'force_password_change' => false,
        ]);
    }

    private function caso(User $creador, array $asignados = [], ?string $fecha = '2026-09-20'): Caso
    {
        $solicitante = Solicitante::create([
            'nombre' => 'Solicitante Prueba',
            'documento' => 'DOC-'.uniqid(),
        ]);

        $caso = Caso::create([
            'radicado' => 'DP-GEN-'.uniqid(),
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Caso de prueba piloto',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => $fecha,
            'estado' => 'Pendiente',
            'fecha_inicio' => '2026-09-20',
            'created_by' => $creador->id,
        ]);

        foreach ($asignados as $asignado) {
            $caso->usuarios()->attach($asignado->id, [
                'estado' => 'Pendiente',
                'activo' => true,
                'fecha_asignacion' => now(),
            ]);
        }

        return $caso;
    }

    public function test_consultor_can_read_but_mutating_case_endpoints_are_rejected(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $consultor = $this->user('Consultor', 'Consultor');
        $asignado = $this->user('Usuario', 'Asignado');
        $caso = $this->caso($juridica, [$asignado]);
        $tarea = $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Tarea pendiente suficientemente larga',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($consultor)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertDontSee('Agregar Usuario')
            ->assertDontSee('id="case-finalize-button"', false)
            ->assertDontSee('Finalizar Tarea');

        $this->actingAs($consultor)->post(route('casos.usuarios.asignar', $caso), ['user_id' => $consultor->id])->assertForbidden();
        $this->actingAs($consultor)->post(route('tareas.completar', [$caso, $tarea]), ['observacion' => 'Intento inválido'])->assertForbidden();
        $this->actingAs($consultor)->delete(route('tareas.eliminar', [$caso, $tarea]))->assertForbidden();
        $this->actingAs($consultor)->patch('/casos/'.$caso->id, ['estado' => 'Finalizado'])->assertStatus(405);
    }

    public function test_juridica_cannot_finalize_case_without_assigned_users(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $caso = $this->caso($juridica);

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame('Pendiente', $caso->refresh()->estado);
    }

    public function test_case_with_assigned_users_keeps_existing_task_completion_rule(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'Asignado');
        $caso = $this->caso($juridica, [$asignado]);
        $tarea = $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Tarea requerida para finalizar',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame('Pendiente', $caso->refresh()->estado);

        $tarea->update(['estado' => 'Completada']);
        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $this->assertSame('Finalizado', $caso->refresh()->estado);
    }

    public function test_juridica_self_assignment_uses_the_normal_task_flow(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $caso = $this->caso($juridica, [$juridica]);
        $tarea = $caso->tareas()->create([
            'user_id' => $juridica->id,
            'descripcion' => 'Tarea propia pendiente para Sara',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('Mis Tareas')
            ->assertSee('Tarea propia pendiente para Sara')
            ->assertSee('Finalizar Tarea');

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))
            ->assertSessionHas('error');

        $this->actingAs($juridica)->post(route('tareas.completar', [$caso, $tarea]), [
            'observacion' => 'Trabajo jurídico completado',
        ])->assertRedirect();

        $this->assertSame('Completado', $caso->refresh()->estado);
        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $this->assertSame('Finalizado', $caso->refresh()->estado);
    }

    public function test_juridica_without_active_assignment_does_not_see_personal_task_controls(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$asignado]);
        $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Tarea exclusiva de Davit',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('Gestión de Tareas')
            ->assertDontSee('Mis Tareas')
            ->assertDontSee('Finalizar Tarea');
    }

    public function test_juridica_and_users_cannot_complete_tasks_assigned_to_someone_else(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $cristian = $this->user('Usuario', 'Cristian');
        $davit = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$juridica, $cristian, $davit]);
        $tareaDavit = $caso->tareas()->create([
            'user_id' => $davit->id,
            'descripcion' => 'Tarea exclusiva de Davit',
            'estado' => 'Pendiente',
        ]);

        $payload = ['observacion' => 'Intento sobre una tarea ajena'];
        $this->actingAs($juridica)
            ->post(route('tareas.completar', [$caso, $tareaDavit]), $payload)
            ->assertForbidden();
        $this->actingAs($cristian)
            ->post(route('tareas.completar', [$caso, $tareaDavit]), $payload)
            ->assertForbidden();

        $this->assertSame('Pendiente', $tareaDavit->refresh()->estado);
    }

    public function test_all_active_users_must_have_completed_tasks_before_finalizing(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $maria = $this->user('Usuario', 'María');
        $carlos = $this->user('Usuario', 'Carlos');
        $caso = $this->caso($juridica, [$maria, $carlos]);
        $caso->tareas()->create(['user_id' => $maria->id, 'descripcion' => 'Tarea de María', 'estado' => 'Completada']);
        $pendiente = $caso->tareas()->create(['user_id' => $carlos->id, 'descripcion' => 'Tarea de Carlos', 'estado' => 'Pendiente']);

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertSessionHas('error');
        $this->assertNotSame('Finalizado', $caso->refresh()->estado);

        $pendiente->update(['estado' => 'Completada']);
        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $this->assertSame('Finalizado', $caso->refresh()->estado);
    }

    public function test_consultor_and_normal_user_cannot_finalize_cases(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $consultor = $this->user('Consultor', 'Consultor');
        $normal = $this->user('Usuario', 'Usuario');
        $caso = $this->caso($juridica, [$normal]);

        $this->actingAs($consultor)->post(route('casos.finalizar', $caso))->assertForbidden();
        $this->actingAs($normal)->post(route('casos.finalizar', $caso))->assertForbidden();
        $this->assertSame('Pendiente', $caso->refresh()->estado);
    }

    public function test_fecha_solicitud_is_required_and_saved_for_new_cases(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $payload = [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Descripción válida del caso',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Persona Solicitante',
        ];

        $this->actingAs($juridica)->post(route('casos.guardar'), $payload)
            ->assertRedirect(route('casos.crear'))
            ->assertSessionHasErrors('fecha_solicitud')
            ->assertSessionHasInput('nombre_solicitante', 'Persona Solicitante')
            ->assertSessionHasInput('descripcion', 'Descripción válida del caso');

        $this->actingAs($juridica)->get(route('casos.crear'))
            ->assertOk()
            ->assertSee('value="Persona Solicitante"', false)
            ->assertSee('Descripción válida del caso');

        $this->actingAs($juridica)->post(route('casos.guardar'), $payload + [
            'fecha_solicitud' => '2026-09-18',
        ])->assertRedirect();

        $this->assertSame('2026-09-18', Caso::latest('id')->first()->fecha_solicitud->toDateString());
        $this->assertNull(Caso::latest('id')->first()->solicitante->documento);

        $this->actingAs($juridica)->get(route('casos.show', Caso::latest('id')->first()))
            ->assertOk()
            ->assertSee('Documento pendiente');
    }

    public function test_real_applicant_document_is_saved_and_displayed(): void
    {
        $juridica = $this->user('Juridica', 'Sara');

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Descripción válida del caso',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Persona Solicitante',
            'documento_solicitante' => '123456789',
            'fecha_solicitud' => '2026-09-18',
        ])->assertRedirect();

        $caso = Caso::latest('id')->first();
        $this->assertSame('123456789', $caso->solicitante->documento);
        $this->actingAs($juridica)->get(route('casos.show', $caso))->assertSee('123456789');
    }

    public function test_same_document_reuses_one_applicant_and_allows_multiple_cases(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $payload = [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Solicitud repetida permitida',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Adriana Gomez',
            'documento_solicitante' => '157845616',
            'fecha_solicitud' => '2026-09-21',
        ];

        foreach (range(1, 3) as $iteration) {
            $this->actingAs($juridica)->post(route('casos.guardar'), $payload)
                ->assertRedirect()
                ->assertSessionHasNoErrors();
        }

        $solicitante = Solicitante::where('documento', '157845616')->firstOrFail();
        $this->assertSame('persona', $solicitante->tipo_solicitante);
        $this->assertSame(3, $solicitante->casos()->count());
        $this->assertSame(1, Solicitante::where('documento', '157845616')->count());

        $this->actingAs($juridica)->post(route('casos.guardar'), $payload + [
            'nombre_solicitante' => 'Nombre Diferente',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Adriana Gomez', $solicitante->fresh()->nombre);
    }

    public function test_company_nit_with_hyphen_is_stored_as_string_and_reused(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $payload = [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Caso para empresa',
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'Empresa ABC S.A.S.',
            'documento_solicitante' => '0900123456-7',
            'fecha_solicitud' => '2026-09-21',
        ];

        $this->actingAs($juridica)->post(route('casos.guardar'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($juridica)->post(route('casos.guardar'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        $empresa = Solicitante::where('documento', '0900123456-7')->firstOrFail();
        $this->assertSame('0900123456-7', $empresa->documento);
        $this->assertSame('empresa', $empresa->tipo_solicitante);
        $this->assertSame(2, $empresa->casos()->count());
    }

    public function test_person_and_company_without_identification_are_allowed_and_not_merged(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $base = [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Caso sin identificación',
            'fecha_solicitud' => '2026-09-21',
            'documento_solicitante' => '   ',
        ];

        $this->actingAs($juridica)->post(route('casos.guardar'), $base + [
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Persona Sin Documento',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->actingAs($juridica)->post(route('casos.guardar'), $base + [
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'Empresa Sin NIT SAS',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(2, Solicitante::whereNull('documento')->count());
        $this->assertSame(['empresa', 'persona'], Solicitante::whereNull('documento')
            ->pluck('tipo_solicitante')->sort()->values()->all());
    }

    public function test_creation_failure_rolls_back_applicant_case_tasks_notifications_and_audit(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'Davit');
        $before = [
            Caso::count(),
            Solicitante::count(),
            Tarea::count(),
            Notificacion::count(),
            \App\Models\Bitacora::count(),
        ];

        $originalDispatcher = \App\Models\Bitacora::getEventDispatcher();
        $isolatedDispatcher = new \Illuminate\Events\Dispatcher($this->app);
        \App\Models\Bitacora::setEventDispatcher($isolatedDispatcher);
        \App\Models\Bitacora::creating(function () {
            throw new \RuntimeException('Fallo controlado después de operaciones intermedias.');
        });

        try {
            $this->actingAs($juridica)->post(route('casos.guardar'), [
                'tipo_proceso_id' => $this->tipo->id,
                'subtipo_proceso_id' => $this->subtipo->id,
                'descripcion' => 'Caso que debe revertirse',
                'tipo_solicitante' => 'persona',
                'nombre_solicitante' => 'Rollback Solicitante',
                'documento_solicitante' => 'ROLLBACK-001',
                'fecha_solicitud' => '2026-09-21',
                'usuarios' => [$asignado->id],
                'tareas' => [$asignado->id => ['Tarea que debe revertirse']],
            ])->assertRedirect(route('casos.crear'))->assertSessionHas('error');
        } finally {
            \App\Models\Bitacora::setEventDispatcher($originalDispatcher);
        }

        $this->assertSame($before, [
            Caso::count(),
            Solicitante::count(),
            Tarea::count(),
            Notificacion::count(),
            \App\Models\Bitacora::count(),
        ]);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_successful_creation_generates_each_database_notification_once(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'Davit');

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $this->subtipo->id,
            'descripcion' => 'Caso exitoso con notificaciones',
            'tipo_solicitante' => 'empresa',
            'nombre_solicitante' => 'Empresa Notificada SAS',
            'documento_solicitante' => '900123456-7',
            'fecha_solicitud' => '2026-09-21',
            'usuarios' => [$asignado->id],
            'tareas' => [$asignado->id => ['Primera tarea válida', 'Segunda tarea válida']],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(3, Notificacion::where('user_id', $asignado->id)->count());
        $this->assertSame(1, Notificacion::where('user_id', $asignado->id)->where('tipo', 'caso')->count());
        $this->assertSame(2, Notificacion::where('user_id', $asignado->id)->where('tipo', 'tarea')->count());
        Mail::assertQueued(\App\Mail\CaseAssignedMail::class, 1);
    }

    public function test_create_form_protects_against_double_submit_and_keeps_validation_details(): void
    {
        $juridica = $this->user('Juridica', 'Sara');

        $this->actingAs($juridica)->get(route('casos.crear'))
            ->assertOk()
            ->assertSee('id="create-case-form"', false)
            ->assertSee('id="create-case-button"', false)
            ->assertSee('button.disabled = true', false)
            ->assertSee('Tipo de solicitante')
            ->assertSee('NIT opcional');

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_solicitante' => 'empresa',
        ])->assertSessionHasErrors([
            'tipo_proceso_id',
            'subtipo_proceso_id',
            'descripcion',
            'nombre_solicitante',
            'fecha_solicitud',
        ]);
    }

    public function test_historical_case_without_fecha_solicitud_still_renders(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $caso = $this->caso($juridica, [], null);

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('No registrada');
    }

    public function test_general_messages_remain_visible_to_case_participants(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'María');
        $otroAsignado = $this->user('Usuario', 'Carlos');
        $externo = $this->user('Usuario', 'Externo');
        $caso = $this->caso($juridica, [$asignado, $otroAsignado]);

        $response = $this->actingAs($asignado)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje general de prueba',
        ])->assertOk();

        $id = $response->json('mensaje.id');
        $this->assertDatabaseHas('mensajes', ['id' => $id, 'destinatario_id' => null]);

        $this->actingAs($asignado)->getJson(route('casos.mensajes.json', [$caso, 'after_id' => 0]))
            ->assertOk()
            ->assertJsonPath('mensajes.0.id', $id)
            ->assertJsonPath('mensajes.0.esDirecto', false);

        $this->actingAs($asignado)->getJson(route('casos.mensajes.json', [$caso, 'after_id' => $id]))
            ->assertOk()
            ->assertJsonCount(0, 'mensajes');

        $this->assertDatabaseHas('notificaciones', ['user_id' => $juridica->id, 'tipo' => 'mensaje']);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $otroAsignado->id, 'tipo' => 'mensaje']);
        $this->assertDatabaseMissing('notificaciones', ['user_id' => $asignado->id, 'tipo' => 'mensaje']);
        $this->assertDatabaseMissing('notificaciones', ['user_id' => $externo->id, 'tipo' => 'mensaje']);
    }

    public function test_direct_message_is_private_and_notifies_only_recipient(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $maria = $this->user('Usuario', 'María');
        $carlos = $this->user('Usuario', 'Carlos');
        $caso = $this->caso($juridica, [$maria, $carlos]);

        $response = $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje privado de prueba',
            'destinatario_id' => $maria->id,
        ])->assertOk();

        $id = $response->json('mensaje.id');
        $this->assertDatabaseHas('mensajes', [
            'id' => $id,
            'user_id' => $juridica->id,
            'destinatario_id' => $maria->id,
        ]);
        $this->assertDatabaseHas('notificaciones', ['user_id' => $maria->id, 'tipo' => 'mensaje']);
        $this->assertDatabaseMissing('notificaciones', ['user_id' => $juridica->id, 'tipo' => 'mensaje']);
        $this->assertDatabaseMissing('notificaciones', ['user_id' => $carlos->id, 'tipo' => 'mensaje']);

        $this->actingAs($juridica)->getJson(route('casos.mensajes.json', $caso))
            ->assertJsonPath('mensajes.0.id', $id);
        $this->actingAs($maria)->getJson(route('casos.mensajes.json', $caso))
            ->assertJsonPath('mensajes.0.id', $id);
        $this->actingAs($carlos)->getJson(route('casos.mensajes.json', $caso))
            ->assertJsonCount(0, 'mensajes');

        $respuesta = $this->actingAs($maria)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Respuesta privada',
            'destinatario_id' => $juridica->id,
        ])->assertOk();

        $this->actingAs($juridica)->getJson(route('casos.mensajes.json', [$caso, 'after_id' => $id]))
            ->assertJsonPath('mensajes.0.id', $respuesta->json('mensaje.id'));
        $this->actingAs($carlos)->getJson(route('casos.mensajes.json', $caso))
            ->assertJsonCount(0, 'mensajes');
    }

    public function test_invalid_or_unrelated_direct_message_access_is_rejected(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'María');
        $externo = $this->user('Usuario', 'Externo');
        $consultor = $this->user('Consultor', 'Consultor');
        $caso = $this->caso($juridica, [$asignado]);

        $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'No debe guardarse',
            'destinatario_id' => $externo->id,
        ])->assertForbidden();

        $this->actingAs($externo)->getJson(route('casos.mensajes.json', $caso))->assertForbidden();
        $this->actingAs($consultor)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Consultor no escribe',
        ])->assertForbidden();
        $this->assertDatabaseCount('mensajes', 0);
    }

    public function test_notifications_endpoint_updates_counter_and_mark_read_clears_it(): void
    {
        $usuario = $this->user('Usuario', 'María');
        Notificacion::enviar($usuario->id, 'Nueva tarea', 'Tienes una tarea', 'tarea');

        $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('sinLeer', 1)
            ->assertJsonCount(1, 'notificaciones');

        $this->actingAs($usuario)->postJson(route('notificaciones.marcar_leidas'))
            ->assertOk()
            ->assertJsonPath('sinLeer', 0);
        $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('sinLeer', 0);
    }

    public function test_header_reports_live_pending_tasks_for_user_and_juridica_but_not_consultor(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Davit');
        $consultor = $this->user('Consultor', 'Consulta');
        $caso = $this->caso($juridica, [$juridica, $usuario, $consultor]);

        $tareaJuridica = $caso->tareas()->create([
            'user_id' => $juridica->id,
            'descripcion' => 'Revisar antecedentes jurídicos',
            'estado' => 'Pendiente',
        ]);
        $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Preparar respuesta al solicitante',
            'estado' => 'Pendiente',
        ]);
        $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Tarea ya terminada',
            'estado' => 'Completada',
        ]);
        $caso->tareas()->create([
            'user_id' => $consultor->id,
            'descripcion' => 'Dato heredado que no debe ser accionable',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('tareasPendientes', 1)
            ->assertJsonPath('tareas.0.id', $tareaJuridica->id);

        $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('tareasPendientes', 1)
            ->assertJsonCount(1, 'tareas');

        $this->actingAs($consultor)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('tareasPendientes', 0)
            ->assertJsonCount(0, 'tareas');

        $this->actingAs($juridica)->post(route('tareas.completar', [$caso, $tareaJuridica]), [
            'observacion' => 'Antecedentes revisados correctamente',
        ])->assertRedirect();

        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 0);
    }

    public function test_message_badge_counts_only_notifications_visible_to_each_recipient(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $davit = $this->user('Usuario', 'Davit');
        $cristian = $this->user('Usuario', 'Cristian');
        $externo = $this->user('Usuario', 'Externo');
        $caso = $this->caso($juridica, [$davit, $cristian]);

        $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje general para el equipo',
        ])->assertOk();

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 1);
        $this->actingAs($cristian)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 1);
        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 0);
        $this->actingAs($externo)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 0);

        $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje directo solo para Davit',
            'destinatario_id' => $davit->id,
        ])->assertOk();

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 2)
            ->assertJsonCount(2, 'mensajes');
        $this->actingAs($cristian)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 1)
            ->assertJsonCount(1, 'mensajes');
        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('mensajesSinLeer', 0);
    }

    public function test_marking_message_notifications_does_not_clear_general_bell(): void
    {
        $usuario = $this->user('Usuario', 'Davit');
        Notificacion::enviar($usuario->id, 'Evento general', 'Cambio relevante', 'info');
        Notificacion::enviar($usuario->id, 'Mensaje nuevo', 'Mensaje del caso', 'mensaje');

        $this->actingAs($usuario)->postJson(route('notificaciones.marcar_leidas'), [
            'categoria' => 'mensaje',
        ])->assertOk();

        $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('sinLeer', 1)
            ->assertJsonPath('mensajesSinLeer', 0);
    }

    public function test_header_indicators_are_authenticated_and_render_together(): void
    {
        $usuario = $this->user('Usuario', 'Davit');

        $this->getJson(route('notificaciones.recientes'))->assertUnauthorized();
        $this->postJson(route('notificaciones.marcar_leidas'))->assertUnauthorized();

        $this->actingAs($usuario)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('id="notif-btn"', false)
            ->assertSee('id="message-indicator-btn"', false)
            ->assertSee('id="task-indicator-btn"', false)
            ->assertSee('setTimeout(poll, 5000)', false)
            ->assertSee("document.visibilityState === 'visible'", false);
    }

    public function test_task_assignment_notifies_the_assignee_once_even_when_self_assigned(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $caso = $this->caso($juridica, [$juridica]);

        $this->actingAs($juridica)->post(route('tareas.guardar', $caso), [
            'user_id' => $juridica->id,
            'descripcion' => 'Revisar documentos de la solicitud',
        ])->assertRedirect();

        $this->assertDatabaseCount('tareas', 1);
        $this->assertDatabaseHas('notificaciones', [
            'user_id' => $juridica->id,
            'tipo' => 'tarea',
            'titulo' => 'Nueva tarea asignada',
        ]);
        $this->assertSame(1, Notificacion::where('user_id', $juridica->id)->where('tipo', 'tarea')->count());

        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))->assertOk();
        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))->assertOk();
        $this->assertSame(1, Notificacion::where('user_id', $juridica->id)->where('tipo', 'tarea')->count());
    }

    public function test_task_cannot_be_assigned_to_user_outside_the_case(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $externo = $this->user('Usuario', 'Externo');
        $caso = $this->caso($juridica);

        $this->actingAs($juridica)->post(route('tareas.guardar', $caso), [
            'user_id' => $externo->id,
            'descripcion' => 'Esta tarea no debe ser creada',
        ])->assertForbidden();

        $this->assertDatabaseCount('tareas', 0);
        $this->assertDatabaseCount('notificaciones', 0);
    }

    public function test_case_state_endpoint_tracks_progress_and_finalization(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $asignado = $this->user('Usuario', 'María');
        $externo = $this->user('Usuario', 'Externo');
        $caso = $this->caso($juridica, [$asignado]);
        $tarea = $caso->tareas()->create([
            'user_id' => $asignado->id,
            'descripcion' => 'Completar análisis del caso',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($juridica)->getJson(route('casos.estado', $caso))
            ->assertOk()
            ->assertJsonPath('progreso', 0)
            ->assertJsonPath('puede_finalizar', false)
            ->assertJsonPath('tareas.0.estado', 'Pendiente');

        $this->actingAs($externo)->getJson(route('casos.estado', $caso))->assertForbidden();

        $this->actingAs($asignado)->post(route('tareas.completar', [$caso, $tarea]), [
            'observacion' => 'Análisis completado correctamente',
        ])->assertRedirect();

        $this->actingAs($juridica)->getJson(route('casos.estado', $caso))
            ->assertOk()
            ->assertJsonPath('estado', 'Completado')
            ->assertJsonPath('progreso', 100)
            ->assertJsonPath('puede_finalizar', true)
            ->assertJsonPath('tareas.0.estado', 'Completada');

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('window.caseStatePoller', false)
            ->assertSee('setTimeout(poll, 4000)', false);

        $this->actingAs($juridica)->post(route('casos.finalizar', $caso))->assertRedirect();
        $this->actingAs($juridica)->getJson(route('casos.estado', $caso))
            ->assertJsonPath('estado', 'Finalizado')
            ->assertJsonPath('puede_finalizar', false);
    }

    public function test_general_alert_read_state_is_selective_and_scoped_to_authenticated_user(): void
    {
        $davit = $this->user('Usuario', 'Davit');
        $cristian = $this->user('Usuario', 'Cristian');
        $first = Notificacion::enviar($davit->id, 'Primera alerta', 'Cambio uno', 'info');
        $second = Notificacion::enviar($davit->id, 'Segunda alerta', 'Cambio dos', 'caso');
        $message = Notificacion::enviar($davit->id, 'Mensaje', 'Mensaje pendiente', 'mensaje');
        $foreign = Notificacion::enviar($cristian->id, 'Alerta ajena', 'No debe tocarse', 'info');

        $this->actingAs($davit)->postJson(route('notificaciones.marcar_leidas'), [
            'categoria' => 'general',
            'ids' => [$first->id, $foreign->id],
        ])->assertOk()
            ->assertJsonPath('sinLeer', 1)
            ->assertJsonPath('mensajesSinLeer', 1);

        $this->assertTrue($first->refresh()->leido);
        $this->assertFalse($second->refresh()->leido);
        $this->assertFalse($message->refresh()->leido);
        $this->assertFalse($foreign->refresh()->leido);

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('sinLeer', 1)
            ->assertJsonPath('mensajesSinLeer', 1);
    }

    public function test_chat_filters_conversations_and_marks_only_active_conversation_as_read(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $davit = $this->user('Usuario', 'Davit');
        $cristian = $this->user('Usuario', 'Cristian');
        $caso = $this->caso($juridica, [$davit, $cristian]);

        $generalId = $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'General visible para todos',
        ])->assertOk()->json('mensaje.id');
        $directId = $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Directo solo para Davit',
            'destinatario_id' => $davit->id,
        ])->assertOk()->json('mensaje.id');
        $otherDirectId = $this->actingAs($cristian)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Directo de Cristian para Davit',
            'destinatario_id' => $davit->id,
        ])->assertOk()->json('mensaje.id');

        $this->actingAs($davit)->getJson(route('casos.mensajes.json', [
            'caso' => $caso,
            'chat' => 'general',
        ]))->assertOk()
            ->assertJsonPath('mensajes.0.id', $generalId)
            ->assertJsonCount(1, 'mensajes');

        $this->actingAs($davit)->getJson(route('casos.mensajes.json', [
            'caso' => $caso,
            'chat' => 'directo',
            'usuario' => $juridica->id,
        ]))->assertOk()
            ->assertJsonPath('mensajes.0.id', $directId)
            ->assertJsonCount(1, 'mensajes');

        $this->actingAs($cristian)->getJson(route('casos.mensajes.json', [
            'caso' => $caso,
            'chat' => 'directo',
            'usuario' => $juridica->id,
        ]))->assertJsonCount(0, 'mensajes');

        $this->actingAs($davit)->postJson(route('casos.mensajes.leidos', $caso), [
            'chat' => 'general',
        ])->assertOk()
            ->assertJsonPath('mensajesSinLeer', 2);

        $this->assertTrue(Notificacion::where('user_id', $davit->id)->where('mensaje_id', $generalId)->firstOrFail()->leido);
        $this->assertFalse(Notificacion::where('user_id', $davit->id)->where('mensaje_id', $directId)->firstOrFail()->leido);
        $this->assertFalse(Notificacion::where('user_id', $davit->id)->where('mensaje_id', $otherDirectId)->firstOrFail()->leido);

        $this->actingAs($davit)->postJson(route('casos.mensajes.leidos', $caso), [
            'chat' => 'directo',
            'usuario' => $juridica->id,
        ])->assertOk()
            ->assertJsonPath('mensajesSinLeer', 1);

        $this->assertTrue(Notificacion::where('user_id', $davit->id)->where('mensaje_id', $directId)->firstOrFail()->leido);
        $this->assertFalse(Notificacion::where('user_id', $davit->id)->where('mensaje_id', $otherDirectId)->firstOrFail()->leido);
    }

    public function test_notification_payload_contains_case_task_and_conversation_destinations(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $davit = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$davit]);
        Notificacion::enviar($davit->id, 'Caso asignado', 'Caso relacionado', 'caso', $caso->id);
        Notificacion::enviar($davit->id, 'Tarea asignada', 'Tarea relacionada', 'tarea', $caso->id);
        $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje general enlazado',
        ])->assertOk();
        $this->actingAs($juridica)->postJson(route('casos.mensajes', $caso), [
            'mensaje' => 'Mensaje directo enlazado',
            'destinatario_id' => $davit->id,
        ])->assertOk();

        $response = $this->actingAs($davit)->getJson(route('notificaciones.recientes'))->assertOk();
        $generalUrls = collect($response->json('notificaciones'))->pluck('url');
        $messageUrls = collect($response->json('mensajes'))->pluck('url');

        $this->assertTrue($generalUrls->contains(route('casos.show', $caso, false)));
        $this->assertTrue($generalUrls->contains(route('casos.show', $caso, false).'#mis-tareas'));
        $this->assertTrue($messageUrls->contains(route('casos.show', $caso, false).'?tab=mensajes&chat=general'));
        $this->assertTrue($messageUrls->contains(route('casos.show', $caso, false).'?tab=mensajes&chat=directo&usuario='.$juridica->id));
    }

    public function test_case_view_exposes_split_chat_and_task_counter_does_not_drop_when_opened(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $davit = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$davit]);
        $caso->tareas()->create([
            'user_id' => $davit->id,
            'descripcion' => 'Tarea que sigue pendiente al abrir el panel',
            'estado' => 'Pendiente',
        ]);

        $this->actingAs($davit)->get(route('casos.show', [
            'caso' => $caso,
            'tab' => 'mensajes',
            'chat' => 'directo',
            'usuario' => $juridica->id,
        ]))->assertOk()
            ->assertSee('Chat General')
            ->assertSee('data-chat-type="directo"', false)
            ->assertSee('id="chat-recipient-id"', false)
            ->assertSee('setTimeout(poll, 3000)', false);

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 1);
        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 1);
    }

    public function test_pending_task_badge_moves_from_two_to_zero_and_stays_zero_after_polling_and_relogin(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $davit = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$davit]);
        $primera = $caso->tareas()->create([
            'user_id' => $davit->id,
            'descripcion' => 'Primera tarea pendiente de prueba',
            'estado' => 'Pendiente',
        ]);
        $segunda = $caso->tareas()->create([
            'user_id' => $davit->id,
            'descripcion' => 'Segunda tarea pendiente de prueba',
            'estado' => 'Pendiente',
        ]);
        Notificacion::enviar($davit->id, 'Nueva tarea asignada', 'Notificación histórica uno', 'tarea', $caso->id);
        Notificacion::enviar($davit->id, 'Nueva tarea asignada', 'Notificación histórica dos', 'tarea', $caso->id);

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 2)
            ->assertJsonCount(2, 'tareas');

        $this->actingAs($davit)->post(route('tareas.completar', [$caso, $primera]), [
            'observacion' => 'Primera tarea completada',
        ])->assertRedirect();

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 1)
            ->assertJsonPath('tareas.0.id', $segunda->id)
            ->assertJsonCount(1, 'tareas');

        // Dos consultas consecutivas representan ciclos de polling: la completada no revive.
        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 1)
            ->assertJsonCount(1, 'tareas');

        $this->actingAs($davit)->post(route('tareas.completar', [$caso, $segunda]), [
            'observacion' => 'Segunda tarea completada',
        ])->assertRedirect();

        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 0)
            ->assertJsonCount(0, 'tareas')
            ->assertJsonCount(2, 'notificaciones');
        $this->actingAs($davit)->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 0)
            ->assertJsonCount(0, 'tareas');

        $this->post('/logout')->assertRedirect('/');
        $this->actingAs($davit->fresh())->getJson(route('notificaciones.recientes'))
            ->assertJsonPath('tareasPendientes', 0)
            ->assertJsonCount(0, 'tareas');
    }

    public function test_case_creation_rejects_a_subtype_from_a_different_process_type(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $otroTipo = TipoProceso::create([
            'nombre' => 'Tipo diferente',
            'codigo' => 'OT',
            'activo' => true,
        ]);
        $otroSubtipo = SubtipoProceso::create([
            'tipo_id' => $otroTipo->id,
            'nombre' => 'Subtipo ajeno',
            'codigo' => 'AJ',
            'activo' => true,
        ]);

        $this->actingAs($juridica)->post(route('casos.guardar'), [
            'tipo_proceso_id' => $this->tipo->id,
            'subtipo_proceso_id' => $otroSubtipo->id,
            'descripcion' => 'Esta combinación no debe aceptarse',
            'tipo_solicitante' => 'persona',
            'nombre_solicitante' => 'Solicitante combinación inválida',
            'fecha_solicitud' => '2026-09-22',
        ])->assertRedirect(route('casos.crear'))
            ->assertSessionHasErrors('subtipo_proceso_id');

        $this->assertDatabaseCount('casos', 0);
        $this->assertDatabaseCount('solicitantes', 0);
    }

    public function test_removed_user_no_longer_sees_case_in_index_or_dashboard(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$usuario]);
        $caso->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

        $this->actingAs($usuario)->get(route('casos.index'))
            ->assertOk()
            ->assertDontSee($caso->radicado);
        $this->actingAs($usuario)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalCasos', 0);
        $this->actingAs($usuario)->get(route('casos.show', $caso))->assertForbidden();
    }

    public function test_completing_an_already_completed_task_does_not_duplicate_business_events(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$usuario]);
        $tarea = $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Tarea para comprobar idempotencia',
            'estado' => 'Pendiente',
        ]);

        $payload = ['observacion' => 'Trabajo completado una sola vez'];
        $this->actingAs($usuario)->post(route('tareas.completar', [$caso, $tarea]), $payload)->assertRedirect();
        $this->actingAs($usuario)->post(route('tareas.completar', [$caso, $tarea]), $payload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, \App\Models\Observacion::where('tarea_id', $tarea->id)->count());
        $this->assertSame(1, \App\Models\Bitacora::where('caso_id', $caso->id)
            ->where('accion', 'Completar')->count());
    }

    public function test_polling_endpoints_are_read_only(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Davit');
        $caso = $this->caso($juridica, [$usuario]);
        $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Tarea que no debe alterarse por polling',
            'estado' => 'Pendiente',
        ]);
        $caso->mensajes()->create([
            'user_id' => $juridica->id,
            'mensaje' => 'Mensaje persistente durante polling',
            'created_at' => now(),
        ]);
        Notificacion::enviar($usuario->id, 'Evento persistente', 'No debe mutar', 'info', $caso->id);
        $before = [
            \App\Models\Bitacora::count(),
            Mensaje::count(),
            Notificacion::count(),
            Tarea::count(),
        ];

        foreach (range(1, 3) as $iteration) {
            $this->actingAs($usuario)->getJson(route('casos.estado', $caso))->assertOk();
            $this->actingAs($usuario)->getJson(route('casos.mensajes.json', [
                'caso' => $caso,
                'chat' => 'general',
            ]))->assertOk();
            $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))->assertOk();
        }

        $this->assertSame($before, [
            \App\Models\Bitacora::count(),
            Mensaje::count(),
            Notificacion::count(),
            Tarea::count(),
        ]);
    }
}
