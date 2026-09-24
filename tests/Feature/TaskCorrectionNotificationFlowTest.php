<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SolicitudCorreccionTarea;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TareaVersion;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TaskCorrectionNotificationFlowTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;
    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario', 'Abogado'] as $nombre) {
            Rol::create(['nombre' => $nombre]);
        }

        $this->tipo = TipoProceso::create([
            'nombre' => 'Proceso correcciones',
            'codigo' => 'PC',
            'activo' => true,
            'ans_dias' => 5,
            'ans_tipo_dias' => 'calendario',
        ]);
        $this->subtipo = SubtipoProceso::create([
            'tipo_id' => $this->tipo->id,
            'nombre' => 'Subtipo correcciones',
            'codigo' => 'SC',
            'activo' => true,
        ]);
    }

    public function test_full_correction_flow_uses_general_bell_same_task_and_preserves_progress(): void
    {
        $juridica = $this->user('Juridica', 'Sara Juridica');
        $usuario = $this->user('Usuario', 'Andres Felipe Ruiz Andrade');
        $caso = $this->caso($juridica, 'CV-SC-20260923-0001');
        $this->assign($caso, $usuario);
        $tarea = $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Revisar documento principal',
            'estado' => 'Pendiente',
            'orden' => 1,
        ]);

        $this->actingAs($usuario)->post(route('tareas.completar', [$caso, $tarea]), [
            'observacion' => 'dvklsadnvjkansd',
        ])->assertRedirect();

        $tarea->refresh();
        $taskCount = Tarea::where('caso_id', $caso->id)->count();
        $completionEvents = Bitacora::where('caso_id', $caso->id)
            ->where('entidad_id', $tarea->id)
            ->where('accion', 'Completar')
            ->count();

        $this->actingAs($usuario)->getJson(route('casos.estado', $caso))
            ->assertOk()
            ->assertJsonPath('progreso', 100)
            ->assertJsonPath('tareas_completadas', 1)
            ->assertJsonPath('tareas_total', 1);

        $this->actingAs($usuario)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), [
            'motivo' => 'Escribi mal mi descripcion.',
        ])->assertRedirect();

        $solicitud = SolicitudCorreccionTarea::where('tarea_id', $tarea->id)->firstOrFail();
        $solicitudAudit = Bitacora::where('accion', 'like', 'Solicitar%')->firstOrFail();
        $this->assertSame('Escribi mal mi descripcion.', $solicitudAudit->metadata['motivo']);

        $alertaJuridica = Notificacion::where('user_id', $juridica->id)
            ->where('tipo', 'correccion_tarea')
            ->firstOrFail();
        $this->assertSame($caso->id, $alertaJuridica->caso_id);
        $this->assertSame($tarea->id, $alertaJuridica->tarea_id);
        $this->assertSame($solicitud->id, $alertaJuridica->solicitud_correccion_id);

        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('sinLeer', 1)
            ->assertJsonPath('mensajesSinLeer', 0)
            ->assertJsonPath('tareasPendientes', 0)
            ->assertJsonPath('notificaciones.0.url', route('casos.show', $caso, false).'#tarea-'.$tarea->id)
            ->assertJsonPath('notificaciones.0.tarea_id', $tarea->id)
            ->assertJsonPath('notificaciones.0.solicitud_correccion_id', $solicitud->id);

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('id="tarea-'.$tarea->id.'"', false)
            ->assertSee('data-correction-request-id="'.$solicitud->id.'"', false)
            ->assertSee('Solicitud de')
            ->assertSee('Escribi mal mi descripcion.')
            ->assertSee('Autorizar')
            ->assertSee('Rechazar');

        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Intento antes de aprobar',
            'fecha_fin' => '2026-09-23 10:30:00',
        ])->assertForbidden();

        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud))->assertRedirect();
        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud))
            ->assertSessionHasErrors('solicitud');

        $this->assertSame(1, Notificacion::where('user_id', $usuario->id)
            ->where('tipo', 'correccion_tarea')
            ->where('solicitud_correccion_id', $solicitud->id)->count());
        $approvalAudit = Bitacora::where('accion', 'like', 'Aprobar%')->firstOrFail();
        $this->assertSame('Andres Felipe Ruiz Andrade', $approvalAudit->metadata['solicitante']);
        $this->assertSame('Escribi mal mi descripcion.', $approvalAudit->metadata['motivo']);
        $this->assertSame('Sara Juridica', $approvalAudit->metadata['autorizada_por']);

        $this->actingAs($usuario)->getJson(route('notificaciones.recientes'))
            ->assertOk()
            ->assertJsonPath('mensajesSinLeer', 0)
            ->assertJsonPath('notificaciones.0.url', route('casos.show', $caso, false).'#tarea-'.$tarea->id);

        $this->actingAs($usuario)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('autorizada')
            ->assertSee('Corregir tarea')
            ->assertSee('una sola vez');

        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Documento revisado correctamente.',
            'fecha_fin' => '2026-09-23 11:00:00',
        ])->assertRedirect();

        $version = TareaVersion::where('tarea_id', $tarea->id)->firstOrFail();
        $correctionAudit = Bitacora::where('accion', 'Corregir tarea')->firstOrFail();
        $this->assertSame('dvklsadnvjkansd', $version->datos_anteriores['observacion']);
        $this->assertSame('Documento revisado correctamente.', $version->datos_nuevos['observacion']);
        $this->assertSame('dvklsadnvjkansd', $correctionAudit->metadata['anterior']['observacion']);
        $this->assertSame('Documento revisado correctamente.', $correctionAudit->metadata['nuevo']['observacion']);
        $this->assertSame('utilizada', $solicitud->refresh()->estado);
        $this->assertFalse((bool) $solicitud->activa);
        $this->assertSame('Completada', $tarea->refresh()->estado);
        $this->assertSame($taskCount, Tarea::where('caso_id', $caso->id)->count());
        $this->assertSame($completionEvents, Bitacora::where('caso_id', $caso->id)
            ->where('entidad_id', $tarea->id)->where('accion', 'Completar')->count());

        $this->actingAs($usuario)->getJson(route('casos.estado', $caso))
            ->assertOk()
            ->assertJsonPath('progreso', 100)
            ->assertJsonPath('tareas_completadas', 1)
            ->assertJsonPath('tareas_total', 1)
            ->assertJsonPath('tareas.0.correccion_key', $solicitud->id.':utilizada:'.$solicitud->updated_at->getTimestamp())
            ->assertJsonPath('tareas.0.estado', 'Completada');

        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Segundo intento prohibido',
            'fecha_fin' => '2026-09-23 12:00:00',
        ])->assertForbidden();
        $this->assertSame(1, TareaVersion::where('tarea_id', $tarea->id)->count());
    }

    public function test_rejection_notifies_user_hides_actions_and_is_idempotent(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Usuario');
        $consultor = $this->user('Consultor', 'Consultor');
        $abogado = $this->user('Abogado', 'Abogado');
        [$caso, $tarea, $solicitud] = $this->completedTaskWithRequest($juridica, $usuario, 'RECHAZO');

        $this->actingAs($consultor)->post(route('tareas.correccion.aprobar', $solicitud))->assertForbidden();
        $this->actingAs($abogado)->post(route('tareas.correccion.rechazar', $solicitud), [
            'motivo_rechazo' => 'Intento no autorizado',
        ])->assertForbidden();
        $this->actingAs($juridica)->post(route('tareas.correccion.rechazar', $solicitud), [])
            ->assertSessionHasErrors('motivo_rechazo');

        $this->actingAs($juridica)->post(route('tareas.correccion.rechazar', $solicitud), [
            'motivo_rechazo' => 'La informacion coincide con el soporte.',
        ])->assertRedirect();
        $this->actingAs($juridica)->post(route('tareas.correccion.rechazar', $solicitud), [
            'motivo_rechazo' => 'Segundo rechazo',
        ])->assertSessionHasErrors('solicitud');

        $this->assertSame('rechazada', $solicitud->refresh()->estado);
        $this->assertFalse((bool) $solicitud->activa);
        $this->assertSame(1, Notificacion::where('user_id', $usuario->id)
            ->where('tipo', 'correccion_tarea')
            ->where('solicitud_correccion_id', $solicitud->id)->count());
        $this->assertSame(1, Bitacora::where('accion', 'like', 'Rechazar%')->count());

        $this->actingAs($usuario)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSee('rechazada')
            ->assertDontSee('autorizada');
        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'No debe guardarse',
            'fecha_fin' => '2026-09-23 12:00:00',
        ])->assertForbidden();
    }

    public function test_authorization_cannot_cross_users_or_tasks(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuarioA = $this->user('Usuario', 'Usuario A');
        $usuarioB = $this->user('Usuario', 'Usuario B');
        $caso = $this->caso($juridica, 'AISLAMIENTO');
        $this->assign($caso, $usuarioA);
        $this->assign($caso, $usuarioB);
        $tareaA = $this->completedTask($caso, $usuarioA, 'Tarea A');
        $tareaB = $this->completedTask($caso, $usuarioB, 'Tarea B');

        $this->actingAs($usuarioA)->post(route('tareas.correccion.solicitar', [$caso, $tareaA]), [
            'motivo' => 'Correccion exclusiva de A',
        ]);
        $solicitud = SolicitudCorreccionTarea::firstOrFail();
        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud));

        $payload = ['observacion' => 'Cambio no autorizado', 'fecha_fin' => '2026-09-23 12:00:00'];
        $this->actingAs($usuarioB)->put(route('tareas.correccion.aplicar', [$caso, $tareaA, $solicitud]), $payload)
            ->assertForbidden();
        $this->actingAs($usuarioB)->put(route('tareas.correccion.aplicar', [$caso, $tareaB, $solicitud]), $payload)
            ->assertForbidden();

        $this->assertSame(0, TareaVersion::count());
        $this->assertSame('aprobada', $solicitud->refresh()->estado);
    }

    public function test_polling_is_read_only_and_exposes_current_card_state(): void
    {
        $juridica = $this->user('Juridica', 'Sara');
        $usuario = $this->user('Usuario', 'Usuario');
        [$caso, $tarea, $solicitud] = $this->completedTaskWithRequest($juridica, $usuario, 'POLLING');
        $notifications = Notificacion::count();
        $audits = Bitacora::count();

        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))->assertOk();
        $this->actingAs($juridica)->getJson(route('notificaciones.recientes'))->assertOk();
        $this->actingAs($juridica)->getJson(route('casos.estado', $caso))
            ->assertOk()
            ->assertJsonPath('tareas.0.correccion_key', $solicitud->id.':pendiente:'.$solicitud->updated_at->getTimestamp())
            ->assertJsonPath('tareas.0.estado', 'Completada')
            ->assertJsonFragment(['id' => $tarea->id]);

        $this->assertSame($notifications, Notificacion::count());
        $this->assertSame($audits, Bitacora::count());
    }

    private function completedTaskWithRequest(User $juridica, User $usuario, string $radicado): array
    {
        $caso = $this->caso($juridica, $radicado);
        $this->assign($caso, $usuario);
        $tarea = $this->completedTask($caso, $usuario, 'Tarea completada '.$radicado);
        $this->actingAs($usuario)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), [
            'motivo' => 'Motivo valido de correccion',
        ]);

        return [$caso, $tarea, SolicitudCorreccionTarea::firstOrFail()];
    }

    private function completedTask(Caso $caso, User $usuario, string $descripcion): Tarea
    {
        $tarea = $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => $descripcion,
            'estado' => 'Completada',
            'fecha_fin' => '2026-09-23 09:00:00',
        ]);
        Observacion::create([
            'tarea_id' => $tarea->id,
            'user_id' => $usuario->id,
            'contenido' => 'Observacion original',
        ]);

        return $tarea;
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

    private function caso(User $creador, string $radicado): Caso
    {
        $solicitante = Solicitante::create([
            'nombre' => 'Solicitante',
            'documento' => 'DOC-'.uniqid(),
            'tipo_solicitante' => 'persona',
        ]);

        return Caso::create([
            'radicado' => $radicado,
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Caso para correcciones',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => '2026-09-23',
            'estado' => 'En proceso',
            'fecha_inicio' => '2026-09-23',
            'created_by' => $creador->id,
        ]);
    }

    private function assign(Caso $caso, User $usuario): void
    {
        $caso->usuarios()->syncWithoutDetaching([
            $usuario->id => ['estado' => 'En proceso', 'activo' => true, 'fecha_asignacion' => now()],
        ]);
    }
}
