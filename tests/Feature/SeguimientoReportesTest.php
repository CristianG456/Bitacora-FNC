<?php

namespace Tests\Feature;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\DiaNoHabil;
use App\Models\Notificacion;
use App\Models\Rol;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TipoProceso;
use App\Models\User;
use App\Services\SeguimientoReportService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use ZipArchive;

class SeguimientoReportesTest extends TestCase
{
    use RefreshDatabase;

    private TipoProceso $tipo;

    private SubtipoProceso $subtipo;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['Administrador', 'Juridica', 'Consultor', 'Usuario', 'Abogado'] as $rol) {
            Rol::create(['nombre' => $rol]);
        }
        $this->tipo = TipoProceso::create(['nombre' => 'Derecho de petición', 'codigo' => 'DP', 'activo' => true, 'ans_dias' => 5, 'ans_tipo_dias' => 'habiles']);
        $this->subtipo = SubtipoProceso::create(['tipo_id' => $this->tipo->id, 'nombre' => 'General', 'codigo' => 'GEN', 'activo' => true]);
    }

    public function test_multiple_types_combine_with_existing_filters_and_exports(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $consultor = $this->user('Consultor', 'Consultora');
        $responsable = $this->user('Usuario', 'Responsable filtro');
        $tipoDos = TipoProceso::create(['nombre' => 'Contrato', 'codigo' => 'CT', 'activo' => true]);
        $subtipoDos = SubtipoProceso::create(['tipo_id' => $tipoDos->id, 'nombre' => 'Compra', 'codigo' => 'COM', 'activo' => true]);
        $tipoTres = TipoProceso::create(['nombre' => 'Convenio', 'codigo' => 'CV', 'activo' => true]);
        $subtipoTres = SubtipoProceso::create(['tipo_id' => $tipoTres->id, 'nombre' => 'Marco', 'codigo' => 'MAR', 'activo' => true]);

        $uno = $this->caso($admin, 'MULTI-UNO', ['ans_estado' => 'preventivo', 'fecha_solicitud' => '2026-09-10']);
        $dos = $this->caso($admin, 'MULTI-DOS', ['tipo_id' => $tipoDos->id, 'subtipo_id' => $subtipoDos->id, 'ans_estado' => 'preventivo', 'fecha_solicitud' => '2026-09-12']);
        $tres = $this->caso($admin, 'MULTI-TRES', ['tipo_id' => $tipoTres->id, 'subtipo_id' => $subtipoTres->id, 'ans_estado' => 'preventivo', 'fecha_solicitud' => '2026-09-14']);
        foreach ([$uno, $dos, $tres] as $caso) {
            $this->assign($caso, $responsable);
        }

        $filtros = [
            'tipo_ids' => [$this->tipo->id, $tipoDos->id],
            'estado' => 'Pendiente',
            'responsable_id' => $responsable->id,
            'ans_estado' => 'preventivo',
            'desde' => '2026-09-01',
            'hasta' => '2026-09-30',
        ];

        $this->actingAs($consultor)->get(route('seguimiento.reportes', $filtros + ['seccion' => 'detalle']))
            ->assertOk()->assertSee('MULTI-UNO')->assertSee('MULTI-DOS')->assertDontSee('MULTI-TRES')
            ->assertSee('Filtros activos');
        $this->actingAs($consultor)->get(route('seguimiento.reportes', ['tipo_ids' => [$tipoDos->id], 'seccion' => 'detalle']))
            ->assertOk()->assertSee('MULTI-DOS')->assertDontSee('MULTI-UNO');
        $this->actingAs($consultor)->get(route('seguimiento.reportes', $filtros + ['subtipo_id' => $subtipoDos->id, 'seccion' => 'detalle']))
            ->assertOk()->assertSee('MULTI-DOS')->assertDontSee('MULTI-UNO')->assertDontSee('MULTI-TRES');
        $this->actingAs($consultor)->get(route('seguimiento.reportes', ['seccion' => 'detalle']))
            ->assertOk()->assertSee('MULTI-UNO')->assertSee('MULTI-DOS')->assertSee('MULTI-TRES');

        $pdf = $this->actingAs($consultor)->get(route('seguimiento.exportar.pdf', $filtros));
        $pdf->assertOk()->assertSee('MULTI-UNO')->assertSee('MULTI-DOS')->assertDontSee('MULTI-TRES')
            ->assertSee('Derecho de petición, Contrato');
        $xlsx = $this->actingAs($consultor)->get(route('seguimiento.exportar.excel', $filtros));
        $contenido = $xlsx->assertOk()->assertDownload()->streamedContent();
        $this->assertStringContainsString('spreadsheetml.sheet', $xlsx->headers->get('content-type'));
        $hojas = $this->xlsxSheets($contenido);
        $this->assertStringContainsString('Resumen', $hojas['workbook']);
        $this->assertStringContainsString('Detalle de casos', $hojas['workbook']);
        $this->assertStringContainsString('Responsables', $hojas['workbook']);
        $this->assertStringContainsString('MULTI-UNO', $hojas['detalle']);
        $this->assertStringContainsString('MULTI-DOS', $hojas['detalle']);
        $this->assertStringNotContainsString('MULTI-TRES', $hojas['detalle']);
        $this->assertStringContainsString('Derecho de petición, Contrato', $hojas['resumen']);
        $this->assertStringContainsString('<autoFilter', $hojas['detalle']);
        $this->assertStringContainsString('ySplit=', $hojas['detalle']);
    }

    public function test_web_detail_is_paginated_while_exports_keep_all_filtered_cases(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $consultor = $this->user('Consultor', 'Consultora');
        foreach (range(1, 13) as $indice) {
            $this->caso($admin, sprintf('PAG-%02d', $indice));
        }
        $filtros = ['tipo_ids' => [$this->tipo->id]];

        $primera = $this->actingAs($consultor)->get(route('seguimiento.reportes', $filtros + ['seccion' => 'detalle']));
        $primera->assertOk()->assertSee('Mostrando')->assertSee('1-10')->assertSee('de <strong class="text-gray-800">13</strong>', false)
            ->assertSee('PAG-13')->assertDontSee('PAG-01')->assertSee('casos_page=2', false);
        $this->actingAs($consultor)->get(route('seguimiento.reportes', $filtros + ['casos_page' => 2, 'seccion' => 'detalle']))
            ->assertOk()->assertSee('PAG-01')->assertDontSee('PAG-13');

        $pdf = $this->actingAs($consultor)->get(route('seguimiento.exportar.pdf', $filtros));
        $pdf->assertOk()->assertSee('PAG-13')->assertSee('PAG-01');
        $xlsx = $this->actingAs($consultor)->get(route('seguimiento.exportar.excel', $filtros))->streamedContent();
        $hojas = $this->xlsxSheets($xlsx);
        $this->assertStringContainsString('PAG-13', $hojas['detalle']);
        $this->assertStringContainsString('PAG-01', $hojas['detalle']);
    }

    public function test_firma_is_rejected_for_every_non_lawyer_without_side_effects_and_allowed_for_lawyer(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $caso = $this->caso($admin, 'FIRMA-ROL');
        foreach (['Usuario', 'Juridica', 'Consultor'] as $rol) {
            $destino = $this->user($rol, 'Destino '.$rol);
            $this->assign($caso, $destino);
            $tareas = Tarea::count();
            $eventos = Bitacora::count();
            $avisos = Notificacion::count();
            $this->actingAs($admin)->post(route('tareas.guardar', $caso), ['user_id' => $destino->id, 'descripcion' => 'Intento de firma no autorizado', 'tipo_accion' => 'firma'])
                ->assertSessionHasErrors('user_id');
            $this->assertSame($tareas, Tarea::count());
            $this->assertSame($eventos, Bitacora::count());
            $this->assertSame($avisos, Notificacion::count());
        }
        $abogado = $this->user('Abogado', 'Abogada');
        $this->assign($caso, $abogado);
        $this->actingAs($admin)->post(route('tareas.guardar', $caso), ['user_id' => $abogado->id, 'descripcion' => 'Firma válida para abogada', 'tipo_accion' => 'firma'])->assertRedirect();
        $this->assertDatabaseHas('tareas', ['user_id' => $abogado->id, 'tipo_accion' => 'firma']);
        $this->actingAs($admin)->get(route('casos.show', $caso))->assertOk()
            ->assertSee('data-role="Abogado"', false)->assertSee("tipo.value = 'normal'", false)
            ->assertDontSee('<option value="firma">Firma</option>', false);
    }

    public function test_only_consultor_and_administrator_access_reports_and_operational_roles_exclude_admin(): void
    {
        $admin = $this->user('Administrador', 'Administrador excluido');
        $consultor = $this->user('Consultor', 'Jefe consultora');
        $juridica = $this->user('Juridica', 'Juridica incluida');
        $usuario = $this->user('Usuario', 'Usuario incluido');
        $abogado = $this->user('Abogado', 'Abogado incluido');
        $caso = $this->caso($admin, 'MULTI-1');
        foreach ([$admin, $juridica, $usuario, $abogado] as $persona) {
            $this->assign($caso, $persona);
        }

        $this->actingAs($consultor)->get(route('seguimiento.index'))->assertOk()
            ->assertSee('Seguimiento y Reportes')->assertSee('Juridica incluida')
            ->assertSee('Usuario incluido')->assertSee('Abogado incluido')->assertDontSee('Administrador excluido');
        $this->actingAs($admin)->get(route('seguimiento.index'))->assertOk();
        foreach ([$juridica, $usuario, $abogado] as $sinAcceso) {
            $this->actingAs($sinAcceso)->get(route('seguimiento.index'))->assertForbidden();
        }
    }

    public function test_distinct_totals_filters_drill_down_and_exports_use_the_same_cases(): void
    {
        CarbonImmutable::setTestNow('2026-09-25 10:00:00 America/Bogota');
        DiaNoHabil::create(['fecha' => '2026-09-28', 'nombre' => 'Cierre', 'tipo' => 'institucional', 'activo' => true]);
        $admin = $this->user('Administrador', 'Admin');
        $consultor = $this->user('Consultor', 'Consultora');
        $uno = $this->user('Usuario', 'Responsable uno');
        $dos = $this->user('Abogado', 'Responsable dos');
        $caso = $this->caso($admin, 'DP-UNO', ['ans_fecha_limite' => '2026-09-29', 'ans_estado' => 'preventivo']);
        $this->assign($caso, $uno);
        $this->assign($caso, $dos);
        $caso->tareas()->create(['user_id' => $uno->id, 'descripcion' => 'Revisión documental pendiente', 'estado' => 'Pendiente', 'fecha_inicio' => '2026-09-24']);
        Bitacora::registrar('Casos', 'Movimiento', 'Último movimiento real', $caso->id, $caso->id);
        $otroTipo = TipoProceso::create(['nombre' => 'Contrato', 'codigo' => 'CT', 'activo' => true]);
        $otroSub = SubtipoProceso::create(['tipo_id' => $otroTipo->id, 'nombre' => 'Compra', 'codigo' => 'COM', 'activo' => true]);
        $otro = $this->caso($admin, 'CT-DOS', ['tipo_id' => $otroTipo->id, 'subtipo_id' => $otroSub->id, 'fecha_solicitud' => '2026-08-01', 'estado' => 'Finalizado', 'ans_estado' => 'cumplido']);

        $pantalla = $this->actingAs($consultor)->get(route('seguimiento.index', ['seccion' => 'detalle']));
        $pantalla->assertOk()->assertSee('DP-UNO')->assertSee('CT-DOS')->assertSee('1 días restantes');
        $datos = $this->app->make(SeguimientoReportService::class)->datos([]);
        $this->assertSame(2, $datos['resumen']['total']);
        $this->assertSame(1, $datos['responsables']->firstWhere('usuario.id', $uno->id)->casos_asociados);
        $this->assertSame(1, $datos['responsables']->firstWhere('usuario.id', $dos->id)->casos_asociados);
        $this->actingAs($consultor)->get(route('seguimiento.reportes', ['tipo_id' => $this->tipo->id, 'estado' => 'Pendiente', 'seccion' => 'detalle']))
            ->assertOk()->assertSee('DP-UNO')->assertDontSee('CT-DOS');
        foreach ([['responsable_id' => $uno->id], ['rol' => 'Abogado'], ['ans_estado' => 'preventivo'], ['desde' => '2026-09-01'], ['con_tareas_pendientes' => '1']] as $filtro) {
            $this->actingAs($consultor)->get(route('seguimiento.reportes', $filtro + ['seccion' => 'detalle']))
                ->assertOk()->assertSee('DP-UNO')->assertDontSee('CT-DOS');
        }
        $this->actingAs($consultor)->get(route('seguimiento.reportes', ['responsable_id' => $admin->id]))
            ->assertSessionHasErrors('responsable_id');
        $this->actingAs($consultor)->get(route('seguimiento.responsable', $uno))->assertOk()
            ->assertSee('Revisión documental pendiente')->assertSee('Último movimiento');
        $xlsx = $this->actingAs($consultor)->get(route('seguimiento.exportar.excel', ['tipo_id' => $this->tipo->id]));
        $xlsx->assertOk()->assertDownload();
        $hojas = $this->xlsxSheets($xlsx->streamedContent());
        $this->assertStringContainsString('DP-UNO', $hojas['detalle']);
        $this->assertStringNotContainsString('CT-DOS', $hojas['detalle']);
        $pdf = $this->actingAs($consultor)->get(route('seguimiento.exportar.pdf', ['tipo_id' => $this->tipo->id]));
        $pdf->assertOk()->assertSee('Reporte de Seguimiento y Gestión')->assertSee('DP-UNO')->assertDontSee('CT-DOS')
            ->assertSee('Casos que requieren atención')->assertSee('institutional-watermark', false)
            ->assertSee('data:image/png;base64,', false)->assertDontSee('https://', false);
        CarbonImmutable::setTestNow();
    }

    public function test_excel_is_valid_with_special_characters_and_stores_text_as_inline_strings(): void
    {
        $consultor = $this->user('Consultor', 'Consultora');
        $responsable = $this->user('Usuario', 'José Andrés Peña');
        $textoFormula = $this->user('Usuario', '=No es una fórmula');
        $this->tipo->update(['nombre' => 'Contratos & Convenios']);
        $this->subtipo->update(['nombre' => 'Pérez <Gómez>']);
        $caso = $this->caso($consultor, 'XLSX-SEGURA');
        $this->assign($caso, $responsable);
        $this->assign($caso, $textoFormula);
        $caso->tareas()->create([
            'user_id' => $responsable->id,
            'descripcion' => 'Revisión <jurídica> & firma',
            'estado' => 'Pendiente',
            'fecha_inicio' => '2026-09-20',
        ]);

        $contenido = $this->actingAs($consultor)
            ->get(route('seguimiento.exportar.excel'))
            ->assertOk()->assertDownload()->streamedContent();
        $hojas = $this->xlsxSheets($contenido);

        $this->assertXlsxXmlIsValid($hojas);
        $this->assertStringContainsString('Contratos &amp; Convenios', $hojas['detalle']);
        $this->assertStringContainsString('Pérez &lt;Gómez&gt;', $hojas['detalle']);
        $this->assertStringContainsString('José Andrés Peña', $hojas['detalle']);
        $this->assertStringContainsString('=No es una fórmula', $hojas['detalle']);
        $this->assertStringContainsString('Revisión &lt;jurídica&gt; &amp; firma', $hojas['detalle']);
        $this->assertStringContainsString('t=', $hojas['detalle']);
        $this->assertStringNotContainsString('<f>', $hojas['detalle']);
        $this->assertTrue(
            strpos($hojas['detalle'], '<autoFilter') < strpos($hojas['detalle'], '<mergeCells'),
            'OpenXML exige autoFilter antes de mergeCells.'
        );
        $this->assertTrue(
            strpos($hojas['responsables'], '<autoFilter') < strpos($hojas['responsables'], '<mergeCells'),
            'OpenXML exige autoFilter antes de mergeCells.'
        );
    }

    public function test_excel_without_results_keeps_the_three_valid_sheets_and_headers(): void
    {
        $consultor = $this->user('Consultor', 'Consultora');
        $sinCasos = TipoProceso::create(['nombre' => 'Sin casos', 'codigo' => 'VAC', 'activo' => true]);

        $contenido = $this->actingAs($consultor)
            ->get(route('seguimiento.exportar.excel', ['tipo_id' => $sinCasos->id]))
            ->assertOk()->assertDownload()->streamedContent();
        $hojas = $this->xlsxSheets($contenido);

        $this->assertXlsxXmlIsValid($hojas);
        $this->assertStringContainsString('Radicado', $hojas['detalle']);
        $this->assertStringContainsString('Responsable', $hojas['responsables']);
    }

    public function test_internal_sections_preserve_filters_and_offer_safe_back_navigation(): void
    {
        $consultor = $this->user('Consultor', 'Consultora');
        $responsable = $this->user('Usuario', 'Andrés Ruiz');
        $tipoDos = TipoProceso::create(['nombre' => 'Contratos', 'codigo' => 'CTR', 'activo' => true]);
        $subtipoDos = SubtipoProceso::create(['tipo_id' => $tipoDos->id, 'nombre' => 'General', 'codigo' => 'GEN2', 'activo' => true]);
        $uno = $this->caso($consultor, 'UX-UNO', ['ans_estado' => 'critico']);
        $dos = $this->caso($consultor, 'UX-DOS', ['tipo_id' => $tipoDos->id, 'subtipo_id' => $subtipoDos->id, 'ans_estado' => 'critico']);
        $this->assign($uno, $responsable);
        $this->assign($dos, $responsable);
        $filtros = ['tipo_ids' => [$this->tipo->id, $tipoDos->id], 'estado' => 'Pendiente', 'ans_estado' => 'critico', 'responsable_id' => $responsable->id];

        $this->actingAs($consultor)->get(route('seguimiento.index', $filtros))
            ->assertOk()->assertSee('Resumen ejecutivo')->assertSee('Detalle de casos')
            ->assertSee('data-tracking-section', false)
            ->assertSee('tracking-type-picker', false)
            ->assertDontSee('Resultados disponibles')
            ->assertDontSee('tracking-module-grid', false);

        foreach (['resumen' => 'tracking-metrics', 'tipos' => 'tracking-card-grid', 'responsables' => 'tracking-table', 'atencion' => 'tracking-attention-list', 'detalle' => 'tracking-case-grid'] as $seccion => $selector) {
            $respuesta = $this->actingAs($consultor)->get(route('seguimiento.index', $filtros + ['seccion' => $seccion]));
            $respuesta->assertOk()->assertSee($selector, false)->assertSee('Filtros activos')->assertSee('Modificar filtros')
                ->assertSee('responsable_id='.$responsable->id, false)->assertSee('ans_estado=critico', false);
        }

        $this->actingAs($consultor)->get(route('seguimiento.index', $filtros + ['seccion' => 'detalle', 'volver' => 'tipos']))
            ->assertOk()->assertSee('← Volver')->assertSee('seccion=tipos', false);
        $this->actingAs($consultor)->get(route('seguimiento.index', $filtros + ['seccion' => 'detalle']))
            ->assertOk()->assertSee('← Volver')->assertSee('Modificar filtros');
        $this->actingAs($consultor)->get(route('seguimiento.index', $filtros + ['volver' => 'detalle']))
            ->assertOk()->assertSee('← Volver')->assertSee('seccion=detalle', false);
        $this->actingAs($consultor)->get(route('seguimiento.index', $filtros + ['seccion' => 'detalle', 'volver' => 'responsables,resumen']))
            ->assertOk()->assertSee('seccion=responsables', false)->assertSee('volver=resumen', false);
    }

    public function test_global_history_uses_the_reusable_institutional_layout(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $caso = $this->caso($admin, 'HIST-1', ['estado' => 'Finalizado']);
        Bitacora::registrar('Casos', 'Finalizar', 'Evento con trazabilidad', $caso->id, $caso->id, null, ['antes' => 'En proceso', 'despues' => 'Finalizado']);
        $this->actingAs($admin)->get(route('historial.exportar.pdf', ['caso_id' => $caso->id]))
            ->assertOk()->assertSee('Historial Global del Sistema')->assertSee('Evento con trazabilidad')
            ->assertSee('event-card', false)->assertSee('institutional-watermark', false)
            ->assertSee('data:image/png;base64,', false)->assertSee('antes')->assertDontSee('https://', false);
    }

    public function test_institutional_reports_keep_header_watermark_footer_for_long_content(): void
    {
        $admin = $this->user('Administrador', 'Admin');
        $caso = $this->caso($admin, 'HIST-LARGO', ['estado' => 'Finalizado']);
        foreach (range(1, 45) as $indice) {
            Bitacora::registrar('Casos', 'Evento '.$indice, str_repeat('Detalle legible del evento '.$indice.'. ', 8), $caso->id, $caso->id);
        }

        $html = $this->actingAs($admin)->get(route('historial.exportar.pdf', ['caso_id' => $caso->id]))
            ->assertOk()->getContent();

        $this->assertSame(45, substr_count($html, 'class="event-card"'));
        $this->assertStringContainsString('position:fixed', $html);
        $this->assertStringContainsString('opacity:.06', $html);
        $this->assertStringContainsString('institutional-header', $html);
        $this->assertStringContainsString('institutional-footer', $html);
        $this->assertGreaterThanOrEqual(4, substr_count($html, 'data:image/'));
    }

    public function test_tracking_ui_is_consistent_responsive_and_has_one_pagination_summary(): void
    {
        $consultor = $this->user('Consultor', 'Consultora visual');
        $caso = $this->caso($consultor, 'UI-TRACKING');
        $this->assign($caso, $consultor);

        $response = $this->actingAs($consultor)->get(route('seguimiento.index'))
            ->assertOk()
            ->assertSee('css/seguimiento-ui.css', false)
            ->assertSee('tracking-filter-card', false)
            ->assertSee('name="tipo_ids[]"', false)
            ->assertSee('Selección múltiple')
            ->assertSee('data-tracking-parent', false)
            ->assertSee('data-tracking-section', false)
            ->assertSee('Seguimiento por responsable')
            ->assertSee('Casos que requieren atenci', false)
            ->assertDontSee('Resultados disponibles')
            ->assertDontSee('tracking-module-grid', false);

        $detalle = $this->actingAs($consultor)->get(route('seguimiento.index', ['seccion' => 'detalle']));
        $this->assertSame(1, substr_count($detalle->getContent(), 'Las exportaciones incluyen todo el resultado filtrado.'));
        $this->assertLessThan(
            strpos($detalle->getContent(), 'class=\'tracking-submodule-head\''),
            strpos($detalle->getContent(), 'class=\'tracking-content-back\'')
        );
        $this->assertStringContainsString('@media (max-width:639px)', file_get_contents(public_path('css/seguimiento-ui.css')));
    }

    private function user(string $rol, string $nombre): User
    {
        return User::factory()->create(['name' => $nombre, 'rol_id' => Rol::where('nombre', $rol)->value('id'), 'activo' => true, 'force_password_change' => false]);
    }

    private function caso(User $creador, string $radicado, array $extra = []): Caso
    {
        $solicitante = Solicitante::create(['nombre' => 'Solicitante '.$radicado, 'documento' => 'DOC-'.uniqid()]);

        return Caso::create(array_merge(['radicado' => $radicado, 'tipo_id' => $this->tipo->id, 'subtipo_id' => $this->subtipo->id, 'descripcion' => 'Caso de seguimiento', 'solicitante_id' => $solicitante->id, 'fecha_solicitud' => '2026-09-20', 'ans_fecha_inicio' => '2026-09-20', 'ans_dias' => 5, 'ans_tipo_dias' => 'habiles', 'ans_fecha_limite' => '2026-09-29', 'ans_estado' => 'vigente', 'estado' => 'Pendiente', 'fecha_inicio' => '2026-09-20', 'created_by' => $creador->id], $extra));
    }

    private function assign(Caso $caso,User $user): void
    {
        $caso->usuarios()->attach($user->id,['estado' => 'Pendiente', 'activo' => true, 'fecha_asignacion' => '2026-09-20 09:00:00']);
    }

    private function xlsxSheets(string $contenido): array
    {
        $archivo = tempnam(sys_get_temp_dir(), 'seguimiento-xlsx-');
        file_put_contents($archivo, $contenido);
        $zip = new ZipArchive;

        try {
            $this->assertTrue($zip->open($archivo) === true);

            return [
                'workbook' => $zip->getFromName('xl/workbook.xml'),
                'resumen' => $zip->getFromName('xl/worksheets/sheet1.xml'),
                'detalle' => $zip->getFromName('xl/worksheets/sheet2.xml'),
                'responsables' => $zip->getFromName('xl/worksheets/sheet3.xml'),
            ];
        } finally {
            $zip->close();
            @unlink($archivo);
        }
    }

    private function assertXlsxXmlIsValid(array $hojas): void
    {
        foreach ($hojas as $nombre => $xml) {
            $dom = new \DOMDocument;
            $this->assertTrue($dom->loadXML($xml), $nombre.' debe ser XML válido.');
        }
    }
}
