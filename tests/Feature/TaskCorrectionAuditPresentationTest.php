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
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class TaskCorrectionAuditPresentationTest extends TestCase
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
            'nombre' => 'Proceso auditoria',
            'codigo' => 'PA',
            'activo' => true,
            'ans_dias' => 5,
            'ans_tipo_dias' => 'calendario',
        ]);
        $this->subtipo = SubtipoProceso::create([
            'tipo_id' => $this->tipo->id,
            'nombre' => 'Subtipo auditoria',
            'codigo' => 'SA',
            'activo' => true,
        ]);
    }

    public function test_approval_and_correction_details_are_consistent_in_case_log_and_global_history(): void
    {
        $juridica = $this->user('Juridica', 'Cristian Gonzalez');
        $usuario = $this->user('Usuario', 'Andres Felipe Ruiz Andrade');
        [$caso, $tarea] = $this->completedTask($juridica, $usuario, 'AUD-APROBADA');
        $totalTareas = Tarea::where('caso_id', $caso->id)->count();

        $this->actingAs($usuario)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), [
            'motivo' => 'Escribi mal la observacion.',
        ])->assertRedirect();
        $solicitud = SolicitudCorreccionTarea::firstOrFail();
        $requestEvent = Bitacora::where('accion', 'Solicitar corrección')->firstOrFail();
        $requestBeforeApproval = $this->renderCorrectionDetails($requestEvent);
        $this->assertStringContainsString('Escribi mal la observacion.', $requestBeforeApproval);
        $this->assertStringContainsString('Texto incorrecto original.', $requestBeforeApproval);
        $this->assertStringNotContainsString('Cristian Gonzalez', $requestBeforeApproval);
        $this->assertStringNotContainsString('Documento revisado correctamente.', $requestBeforeApproval);
        $this->assertStringNotContainsString('Cambios realizados:', $requestBeforeApproval);

        $this->actingAs($juridica)->post(route('tareas.correccion.aprobar', $solicitud))->assertRedirect();
        $approvalEvent = Bitacora::where('accion', 'Aprobar corrección')->firstOrFail();
        $approvalBeforeCorrection = $this->renderCorrectionDetails($approvalEvent);
        $this->assertSame($requestBeforeApproval, $this->renderCorrectionDetails($requestEvent->fresh()));
        $this->assertStringContainsString('Escribi mal la observacion.', $approvalBeforeCorrection);
        $this->assertStringContainsString('Cristian Gonzalez', $approvalBeforeCorrection);
        $this->assertStringContainsString('Corrección autorizada para un solo uso.', $approvalBeforeCorrection);
        $this->assertStringNotContainsString('Documento revisado correctamente.', $approvalBeforeCorrection);
        $this->assertStringNotContainsString('Cambios realizados:', $approvalBeforeCorrection);
        $this->assertStringNotContainsString('Anterior:', $approvalBeforeCorrection);

        $this->actingAs($usuario)->put(route('tareas.correccion.aplicar', [$caso, $tarea, $solicitud]), [
            'observacion' => 'Documento revisado correctamente.',
            'fecha_fin' => '2026-09-24 11:00:00',
        ])->assertRedirect();
        $correctionEvent = Bitacora::where('accion', 'Corregir tarea')->firstOrFail();
        $correctionDetails = $this->renderCorrectionDetails($correctionEvent);
        $this->assertSame($requestBeforeApproval, $this->renderCorrectionDetails($requestEvent->fresh()));
        $this->assertSame($approvalBeforeCorrection, $this->renderCorrectionDetails($approvalEvent->fresh()));
        $this->assertStringContainsString('Escribi mal la observacion.', $correctionDetails);
        $this->assertStringContainsString('Cristian Gonzalez', $correctionDetails);
        $this->assertStringContainsString('Cambios realizados:', $correctionDetails);
        $this->assertStringContainsString('Texto incorrecto original.', $correctionDetails);
        $this->assertStringContainsString('Documento revisado correctamente.', $correctionDetails);

        $this->assertSame(3, Bitacora::whereIn('accion', [
            'Solicitar corrección', 'Aprobar corrección', 'Corregir tarea',
        ])->count());
        $this->assertSame($totalTareas, Tarea::where('caso_id', $caso->id)->count());
        $this->assertSame('Completada', $tarea->refresh()->estado);

        $caso->update(['estado' => 'Finalizado']);
        $essentialTexts = [
            'Motivo de la solicitud:',
            'Escribi mal la observacion.',
            'Observación actual/original:',
            'Texto incorrecto original.',
            'Solicitado por:',
            'Andres Felipe Ruiz Andrade',
            'Autorizado por:',
            'Cristian Gonzalez',
            'Corrección autorizada para un solo uso.',
            'Cambios realizados:',
            'Observación',
            'Anterior:',
            'Nuevo:',
            'Documento revisado correctamente.',
        ];

        $caseResponse = $this->actingAs($juridica)->get(route('casos.show', $caso))->assertOk();
        $historyResponse = $this->actingAs($juridica)->get(route('historial.show', $caso))->assertOk();
        foreach ($essentialTexts as $text) {
            $caseResponse->assertSeeText($text);
            $historyResponse->assertSeeText($text);
        }
        foreach ([$requestEvent, $approvalEvent, $correctionEvent] as $event) {
            $caseResponse->assertSee('id="audit-event-'.$event->id.'"', false);
            $historyResponse->assertSee('id="audit-event-'.$event->id.'"', false);
        }

        $requestEvent->update(['metadata' => [
            'solicitud_id' => $solicitud->id,
            'motivo' => 'Evento antiguo con solicitud ya utilizada.',
        ]]);
        $legacyRequestDetails = $this->renderCorrectionDetails($requestEvent->fresh());
        $this->assertStringContainsString('Evento antiguo con solicitud ya utilizada.', $legacyRequestDetails);
        $this->assertStringContainsString('Juntar tareas de auditoria', $legacyRequestDetails);
        $this->assertStringContainsString('Texto incorrecto original.', $legacyRequestDetails);
        $this->assertStringNotContainsString('Cristian Gonzalez', $legacyRequestDetails);
        $this->assertStringNotContainsString('Documento revisado correctamente.', $legacyRequestDetails);
        $this->assertStringNotContainsString('Cambios realizados:', $legacyRequestDetails);

        $approvalEvent->update(['metadata' => ['solicitud_id' => $solicitud->id]]);
        $legacyApprovalDetails = $this->renderCorrectionDetails($approvalEvent->fresh());
        $this->assertStringContainsString('Evento antiguo con solicitud ya utilizada.', $legacyApprovalDetails);
        $this->assertStringContainsString('Andres Felipe Ruiz Andrade', $legacyApprovalDetails);
        $this->assertStringContainsString('Cristian Gonzalez', $legacyApprovalDetails);
        $this->assertStringNotContainsString('Documento revisado correctamente.', $legacyApprovalDetails);
        $this->assertStringNotContainsString('Cambios realizados:', $legacyApprovalDetails);
    }

    public function test_rejection_details_are_consistent_and_do_not_change_the_task(): void
    {
        $juridica = $this->user('Juridica', 'Cristian Gonzalez');
        $usuario = $this->user('Usuario', 'Andres Felipe Ruiz Andrade');
        [$caso, $tarea] = $this->completedTask($juridica, $usuario, 'AUD-RECHAZADA');

        $this->actingAs($usuario)->post(route('tareas.correccion.solicitar', [$caso, $tarea]), [
            'motivo' => 'Escribi mal la observacion.',
        ]);
        $solicitud = SolicitudCorreccionTarea::firstOrFail();
        $requestEvent = Bitacora::where('accion', 'Solicitar corrección')->firstOrFail();
        $requestBeforeRejection = $this->renderCorrectionDetails($requestEvent);
        $this->actingAs($juridica)->post(route('tareas.correccion.rechazar', $solicitud), [
            'motivo_rechazo' => 'La informacion registrada es correcta.',
        ])->assertRedirect();
        $rejectionEvent = Bitacora::where('accion', 'Rechazar corrección')->firstOrFail();
        $rejectionDetails = $this->renderCorrectionDetails($rejectionEvent);
        $this->assertSame($requestBeforeRejection, $this->renderCorrectionDetails($requestEvent->fresh()));
        $this->assertStringContainsString('Escribi mal la observacion.', $rejectionDetails);
        $this->assertStringContainsString('Cristian Gonzalez', $rejectionDetails);
        $this->assertStringContainsString('La informacion registrada es correcta.', $rejectionDetails);
        $this->assertStringContainsString('Corrección no autorizada.', $rejectionDetails);
        $this->assertStringNotContainsString('Cambios realizados:', $rejectionDetails);
        $this->assertStringNotContainsString('Nuevo:', $rejectionDetails);

        $this->assertSame('Texto incorrecto original.', $tarea->observacion()->value('contenido'));
        $this->assertSame('Completada', $tarea->refresh()->estado);
        $this->assertSame(0, $tarea->versiones()->count());
        $this->assertSame(2, Bitacora::whereIn('accion', [
            'Solicitar corrección', 'Rechazar corrección',
        ])->count());

        $caso->update(['estado' => 'Finalizado']);
        $essentialTexts = [
            'Motivo de la solicitud:',
            'Escribi mal la observacion.',
            'Rechazado por:',
            'Cristian Gonzalez',
            'Motivo del rechazo:',
            'La informacion registrada es correcta.',
            'Corrección no autorizada.',
        ];

        $caseResponse = $this->actingAs($juridica)->get(route('casos.show', $caso))->assertOk();
        $historyResponse = $this->actingAs($juridica)->get(route('historial.show', $caso))->assertOk();
        foreach ($essentialTexts as $text) {
            $caseResponse->assertSeeText($text);
            $historyResponse->assertSeeText($text);
        }
        foreach ([$requestEvent, $rejectionEvent] as $event) {
            $caseResponse->assertSee('id="audit-event-'.$event->id.'"', false);
            $historyResponse->assertSee('id="audit-event-'.$event->id.'"', false);
        }
    }

    public function test_legacy_correction_events_without_metadata_or_related_records_render_safely(): void
    {
        $juridica = $this->user('Juridica', 'Juridica Auditora');
        $caso = $this->caso($juridica, 'AUD-LEGACY');
        $caso->update(['estado' => 'Finalizado']);
        Bitacora::create([
            'caso_id' => $caso->id,
            'user_id' => $juridica->id,
            'modulo' => 'Tareas',
            'accion' => 'Corregir tarea',
            'entidad_id' => 999999,
            'descripcion' => 'Evento historico sin detalle disponible.',
            'metadata' => null,
            'created_at' => now(),
        ]);

        $this->actingAs($juridica)->get(route('casos.show', $caso))
            ->assertOk()
            ->assertSeeText('Evento historico sin detalle disponible.');
        $this->actingAs($juridica)->get(route('historial.show', $caso))
            ->assertOk()
            ->assertSeeText('Evento historico sin detalle disponible.');
    }

    private function completedTask(User $creador, User $usuario, string $radicado): array
    {
        $caso = $this->caso($creador, $radicado);
        $caso->usuarios()->attach($usuario->id, [
            'estado' => 'En proceso',
            'activo' => true,
            'fecha_asignacion' => now(),
        ]);
        $tarea = $caso->tareas()->create([
            'user_id' => $usuario->id,
            'descripcion' => 'Juntar tareas de auditoria',
            'estado' => 'Completada',
            'fecha_fin' => '2026-09-24 09:00:00',
        ]);
        Observacion::create([
            'tarea_id' => $tarea->id,
            'user_id' => $usuario->id,
            'contenido' => 'Texto incorrecto original.',
        ]);
        Bitacora::create([
            'caso_id' => $caso->id,
            'user_id' => $usuario->id,
            'modulo' => 'Tareas',
            'accion' => 'Completar',
            'entidad_id' => $tarea->id,
            'usuario_afectado' => $usuario->id,
            'descripcion' => 'El usuario completó la tarea.',
            'metadata' => ['observacion' => 'Texto incorrecto original.'],
            'created_at' => now()->subSecond(),
        ]);

        return [$caso, $tarea];
    }

    private function renderCorrectionDetails(Bitacora $event): string
    {
        return view('components.task-correction-audit-details', [
            'detalle' => \App\Support\TaskCorrectionAuditPresenter::make($event),
            'compacto' => false,
        ])->render();
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
            'nombre' => 'Solicitante auditoria',
            'documento' => 'DOC-'.uniqid(),
            'tipo_solicitante' => 'persona',
        ]);

        return Caso::create([
            'radicado' => $radicado,
            'tipo_id' => $this->tipo->id,
            'subtipo_id' => $this->subtipo->id,
            'descripcion' => 'Caso para auditoria visual',
            'solicitante_id' => $solicitante->id,
            'fecha_solicitud' => '2026-09-24',
            'estado' => 'En proceso',
            'fecha_inicio' => '2026-09-24',
            'created_by' => $creador->id,
        ]);
    }
}
