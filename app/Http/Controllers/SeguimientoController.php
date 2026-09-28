<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\SeguimientoReportService;
use App\Support\LocalDate;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeguimientoController extends Controller
{
    public function __construct(private readonly SeguimientoReportService $reportes) {}

    public function index(Request $request)
    {
        return view('seguimiento.index', $this->datosWeb($request, $this->filtros($request)));
    }

    public function responsable(Request $request, User $responsable)
    {
        abort_unless(
            $responsable->activo && in_array($responsable->role?->nombre, SeguimientoReportService::ROLES_OPERATIVOS, true),
            404
        );

        $filtros = $this->filtros($request);
        $filtros['responsable_id'] = $responsable->id;
        $datos = $this->reportes->datos($filtros);
        $seguimiento = $datos['responsables']->firstWhere('usuario.id', $responsable->id);
        abort_unless($seguimiento, 404);

        return view('seguimiento.responsable', $datos + compact('seguimiento'));
    }

    public function reportes(Request $request)
    {
        return view('seguimiento.reportes', $this->datosWeb($request, $this->filtros($request)));
    }

    public function exportarPdf(Request $request)
    {
        return view('seguimiento.pdf', $this->reportes->datos($this->filtros($request)) + [
            'generadoEn' => LocalDate::inBogota(now()),
        ]);
    }

    public function exportarExcel(Request $request)
    {
        $datos = $this->reportes->datos($this->filtros($request));
        $nombre = 'seguimiento_'.now('America/Bogota')->format('Ymd_His').'.csv';

        return Response::streamDownload(function () use ($datos) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, chr(239).chr(187).chr(191));

            fputcsv($salida, ['FILTROS APLICADOS']);
            foreach ($datos['filtrosAplicados'] as $nombre => $valor) {
                fputcsv($salida, [$nombre, $valor]);
            }
            fputcsv($salida, []);
            fputcsv($salida, ['RESUMEN']);
            fputcsv($salida, ['Total casos', 'Casos activos', 'Próximos a vencer', 'ANS vencidos', 'Tareas pendientes']);
            fputcsv($salida, [
                $datos['resumen']['total'],
                $datos['resumen']['activos'],
                $datos['resumen']['proximos'],
                $datos['resumen']['vencidos'],
                $datos['resumen']['tareas_pendientes'],
            ]);
            fputcsv($salida, []);
            fputcsv($salida, ['DETALLE DE CASOS']);
            fputcsv($salida, ['Radicado', 'Tipo', 'Subtipo', 'Estado', 'Fecha solicitud', 'Fecha límite', 'Estado ANS', 'Restante/retraso', 'Responsables', 'Tareas pendientes', 'Tarea pendiente más antigua', 'Responsable tarea', 'Último movimiento']);
            foreach ($datos['casos'] as $caso) {
                $dias = $caso->dias_restantes_calculados;
                fputcsv($salida, [
                    $caso->radicado,
                    $caso->tipo?->nombre,
                    $caso->subtipo?->nombre,
                    $caso->estado,
                    $caso->fecha_solicitud?->format('Y-m-d'),
                    $caso->ans_fecha_limite?->format('Y-m-d'),
                    $caso->ans_estado,
                    $dias === null ? 'Sin ANS' : ($dias >= 0 ? $dias.' restantes' : abs($dias).' de retraso'),
                    $caso->usuarios->pluck('name')->join(', '),
                    $caso->tareas_pendientes_count,
                    $caso->tareaPendienteMasAntigua?->descripcion,
                    $caso->tareaPendienteMasAntigua?->usuario?->name,
                    $caso->ultimo_movimiento?->format('Y-m-d H:i'),
                ]);
            }
            fputcsv($salida, []);
            fputcsv($salida, ['SEGUIMIENTO POR RESPONSABLE']);
            fputcsv($salida, ['Responsable', 'Rol', 'Casos asociados', 'Tareas pendientes', 'Tareas completadas', 'Próximos a vencer', 'ANS vencidos']);
            foreach ($datos['responsables'] as $fila) {
                fputcsv($salida, [
                    $fila->usuario->name,
                    $fila->usuario->role?->nombre,
                    $fila->casos_asociados,
                    $fila->tareas_pendientes,
                    $fila->tareas_completadas,
                    $fila->casos_proximos,
                    $fila->casos_vencidos,
                ]);
            }
            fclose($salida);
        }, $nombre, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    private function filtros(Request $request): array
    {
        $filtros = $request->validate([
            'responsable_id' => ['nullable', 'integer', 'exists:users,id'],
            'rol' => ['nullable', Rule::in(SeguimientoReportService::ROLES_OPERATIVOS)],
            'tipo_ids' => ['nullable', 'array'],
            'tipo_ids.*' => ['integer', 'distinct', 'exists:tipos_proceso,id'],
            'tipo_id' => ['nullable', 'integer', 'exists:tipos_proceso,id'],
            'subtipo_id' => ['nullable', 'integer', 'exists:subtipos_proceso,id'],
            'estado' => ['nullable', Rule::in(SeguimientoReportService::ESTADOS_CASO)],
            'ans_estado' => ['nullable', Rule::in(SeguimientoReportService::ESTADOS_ANS)],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'con_tareas_pendientes' => ['nullable', Rule::in(['0', '1'])],
        ]);

        $filtros['tipo_ids'] = collect($filtros['tipo_ids'] ?? [])
            ->when(
                empty($filtros['tipo_ids']) && ! empty($filtros['tipo_id']),
                fn ($ids) => $ids->push((int) $filtros['tipo_id'])
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        unset($filtros['tipo_id']);

        if (! empty($filtros['responsable_id']) && ! User::query()
            ->whereKey($filtros['responsable_id'])
            ->where('activo', true)
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', SeguimientoReportService::ROLES_OPERATIVOS))
            ->exists()) {
            throw ValidationException::withMessages([
                'responsable_id' => 'El responsable debe tener un rol operativo habilitado.',
            ]);
        }

        return $filtros;
    }

    private function datosWeb(Request $request, array $filtros): array
    {
        $datos = $this->reportes->datos($filtros);
        $todosLosCasos = $datos['casos'];
        $porPagina = 10;
        $pagina = LengthAwarePaginator::resolveCurrentPage('casos_page');

        $datos['casos'] = new LengthAwarePaginator(
            $todosLosCasos->forPage($pagina, $porPagina)->values(),
            $todosLosCasos->count(),
            $porPagina,
            $pagina,
            [
                'path' => $request->url(),
                'pageName' => 'casos_page',
            ]
        );
        $datos['casos']->appends($request->except('casos_page'));

        return $datos;
    }
}
