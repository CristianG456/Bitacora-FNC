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

class AuditChronologicalOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_audit_surface_uses_newest_first_with_stable_tie_breaker(): void
    {
        $admin = $this->user('Administrador', 'Admin Cronología');
        $consultor = $this->user('Consultor', 'Consultor Cronología');
        $caso = $this->case($admin);

        $definitions = [
            ['CASO CREADO ORDEN', 'Creación', '2026-10-01 08:00:00', ['event_type' => 'case_created']],
            ['RESPONSABLE ASIGNADO ORDEN', 'Asignación', '2026-10-01 09:00:00', ['event_type' => 'responsible_assigned']],
            ['TAREA ASIGNADA ORDEN', 'Crear', '2026-10-01 10:00:00', ['event_type' => 'task_assigned']],
            ['TAREA COMPLETADA ORDEN', 'Completar', '2026-10-02 15:00:00', ['event_type' => 'task_completed']],
            ['ANOTACIÓN ORDEN', 'Anotación', '2026-10-03 14:00:00', ['event_type' => 'consultant_note', 'anotacion' => 'Snapshot conocido al anotar']],
            ['CASO FINALIZADO ORDEN', 'Finalizar', '2026-10-03 14:00:00', ['event_type' => 'case_finalized', 'resultado' => 'CUMPLIDO']],
        ];

        $events = collect($definitions)->map(fn (array $definition) => Bitacora::create([
            'caso_id' => $caso->id,
            'user_id' => $admin->id,
            'modulo' => 'Casos',
            'accion' => $definition[1],
            'descripcion' => $definition[0],
            'metadata' => $definition[3] + ['actor' => ['nombre' => $admin->name, 'rol' => 'Administrador']],
            'created_at' => $definition[2],
        ]));

        $expected = $events->sortByDesc(fn (Bitacora $event) => sprintf('%s-%010d', $event->created_at->format('YmdHis'), $event->id))
            ->pluck('descripcion')->values()->all();
        $snapshots = $events->mapWithKeys(fn (Bitacora $event) => [$event->id => $event->metadata])->all();

        $this->assertSame($expected, $caso->fresh()->bitacoras->pluck('descripcion')->all());
        $this->assertSame('CASO FINALIZADO ORDEN', $expected[0]);
        $this->assertSame('CASO CREADO ORDEN', $expected[array_key_last($expected)]);

        $this->actingAs($consultor)->get(route('casos.show', $caso))->assertOk()->assertSeeInOrder($expected);
        $this->actingAs($consultor)->get(route('dashboard.casos.bitacora', $caso))->assertOk()->assertSeeInOrder($expected);
        $this->actingAs($consultor)->get(route('historial.show', $caso))->assertOk()->assertSeeInOrder($expected);
        $this->actingAs($consultor)->get(route('historial.exportar.pdf', ['caso_id' => $caso->id]))->assertOk()->assertSeeInOrder($expected);
        $this->actingAs($consultor)->get(route('historial.exportar.pdf'))->assertOk()->assertSeeInOrder($expected);

        $csv = $this->actingAs($consultor)->get(route('historial.exportar.excel', ['caso_id' => $caso->id]))
            ->assertOk()->streamedContent();
        $this->assertTextOrder($csv, $expected);

        $currentSnapshots = Bitacora::whereIn('id', $events->pluck('id'))->get()
            ->mapWithKeys(fn (Bitacora $event) => [$event->id => $event->metadata])->all();
        $this->assertSame($snapshots, $currentSnapshots);
    }

    public function test_first_history_page_contains_newest_events_and_last_page_the_oldest(): void
    {
        $admin = $this->user('Administrador', 'Admin Paginación');
        $caso = $this->case($admin);

        foreach (range(1, 27) as $sequence) {
            Bitacora::create([
                'caso_id' => $caso->id,
                'user_id' => $admin->id,
                'modulo' => 'Casos',
                'accion' => 'Evento',
                'descripcion' => sprintf('EVENTO-ORDEN-%03d', $sequence),
                'metadata' => [],
                'created_at' => sprintf('2026-10-%02d 10:00:00', $sequence),
            ]);
        }

        $this->actingAs($admin)->get(route('historial.show', $caso))
            ->assertOk()->assertSee('EVENTO-ORDEN-027')->assertSee('EVENTO-ORDEN-003')->assertDontSee('EVENTO-ORDEN-001');
        $this->actingAs($admin)->get(route('historial.show', ['caso' => $caso, 'page' => 2]))
            ->assertOk()->assertSeeInOrder(['EVENTO-ORDEN-002', 'EVENTO-ORDEN-001'])->assertDontSee('EVENTO-ORDEN-027');
    }

    private function assertTextOrder(string $content, array $values): void
    {
        $position = -1;
        foreach ($values as $value) {
            $next = strpos($content, $value);
            $this->assertNotFalse($next);
            $this->assertGreaterThan($position, $next);
            $position = $next;
        }
    }

    private function user(string $role, string $name): User
    {
        $roleId = Rol::firstOrCreate(['nombre' => $role])->id;

        return User::factory()->create(['name' => $name, 'rol_id' => $roleId, 'activo' => true, 'force_password_change' => false]);
    }

    private function case(User $creator): Caso
    {
        $type = TipoProceso::create(['nombre' => 'Tipo cronología', 'codigo' => 'CRO', 'activo' => true]);
        $subtype = SubtipoProceso::create(['tipo_id' => $type->id, 'nombre' => 'Subtipo cronología', 'codigo' => 'ORD', 'activo' => true]);
        $applicant = Solicitante::create(['nombre' => 'Solicitante cronología', 'documento' => uniqid('DOC-'), 'tipo_solicitante' => 'persona']);

        return Caso::create([
            'radicado' => uniqid('ORDEN-'), 'tipo_id' => $type->id, 'subtipo_id' => $subtype->id,
            'descripcion' => 'Caso para validar cronología', 'solicitante_id' => $applicant->id,
            'fecha_solicitud' => '2026-10-01', 'estado' => 'Finalizado', 'fecha_inicio' => '2026-10-01',
            'fecha_fin' => '2026-10-04', 'created_by' => $creator->id,
        ]);
    }
}
