<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\User;
use App\Services\CaseAuditService;
use App\Support\LocalDate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(?Request $request = null)
    {
        $request ??= request();
        $user  = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']);

        // ─── Estadísticas de casos ─────────────────────────────────
        $baseQuery = $this->casosVisiblesPara($user);

        $totalCasos    = (clone $baseQuery)->count();
        $enProceso     = (clone $baseQuery)->where('estado', 'En proceso')->count();
        // 'Completado' nunca se alcanza por lógica de la app; se cuenta 'Finalizado'
        // para que la tarjeta muestre los casos efectivamente cerrados.
        $completados   = (clone $baseQuery)->where('estado', 'Finalizado')->count();
        $finalizados   = $completados; // Alias para mantener compatibilidad con la vista
        $pendientes    = (clone $baseQuery)->where('estado', 'Pendiente')->count();

        // ─── Casos recientes ───────────────────────────────────────
        $filtrosDashboard = [
            'todos' => ['estado' => null, 'etiqueta' => 'Casos recientes'],
            'pendientes' => ['estado' => 'Pendiente', 'etiqueta' => 'Casos pendientes recientes'],
            'en_proceso' => ['estado' => 'En proceso', 'etiqueta' => 'Casos en proceso recientes'],
            'completados' => ['estado' => 'Finalizado', 'etiqueta' => 'Casos completados recientes'],
            'finalizados' => ['estado' => 'Finalizado', 'etiqueta' => 'Casos finalizados recientes'],
        ];
        $filtroSolicitado = $request->query('estado_dashboard', 'todos');
        $filtroDashboard = is_string($filtroSolicitado) && array_key_exists($filtroSolicitado, $filtrosDashboard)
            ? $filtroSolicitado
            : 'todos';
        $filtroActivo = $filtrosDashboard[$filtroDashboard];

        $casosRecientes = (clone $baseQuery)
            ->when($filtroActivo['estado'], fn ($query, $estado) => $query->where('estado', $estado))
            ->with(['tipo', 'subtipo'])
            ->latest()
            ->limit(10)
            ->get();
        $tituloListado = $filtroActivo['etiqueta'];



        // ─── Notificaciones sin leer ───────────────────────────────
        $notificacionesSinLeer = $user->notificacionesSinLeer();

        return view('dashboard', compact(
            'user',
            'totalCasos',
            'enProceso',
            'completados',
            'finalizados',
            'pendientes',
            'casosRecientes',
            'filtroDashboard',
            'tituloListado',
            'notificacionesSinLeer'
        ));
    }

    /**
     * Busca únicamente dentro de los casos que el usuario ya puede consultar.
     * La descripción se compara después de descifrarla: no se duplica contenido
     * sensible en una columna auxiliar de texto plano.
     */
    public function buscarCasos(Request $request): JsonResponse
    {
        if (is_string($request->query('q'))) {
            $request->merge(['q' => trim($request->query('q'))]);
        }
        $validated = $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']]);
        $termino = $this->normalizarBusqueda($validated['q']);
        $resultados = [];

        $casos = $this->casosVisiblesPara($request->user())
            ->with(['tipo', 'subtipo', 'solicitante', 'usuarios.role'])
            ->latest('casos.created_at')
            ->lazy(100);

        foreach ($casos as $caso) {
            if (!$this->casoCoincide($caso, $termino)) {
                continue;
            }

            $resultados[] = [
                'id' => $caso->id,
                'radicado' => $caso->radicado,
                'tipo' => $caso->tipo?->nombre ?? 'Sin tipo',
                'subtipo' => $caso->subtipo?->nombre,
                'solicitante' => $caso->solicitanteNombreActual() ?? 'Sin solicitante',
                'estado' => $caso->estado,
                'fecha' => LocalDate::inBogota($caso->created_at)?->format('d/m/Y'),
                'descripcion' => Str::limit((string) $caso->descripcion, 150),
                'responsables' => $caso->usuarios->pluck('name')->values()->all(),
                'bitacora_url' => route('dashboard.casos.bitacora', $caso, false),
            ];

            if (count($resultados) === 12) {
                break;
            }
        }

        return response()->json([
            'data' => $resultados,
            'message' => empty($resultados) ? 'No se encontraron casos con ese criterio.' : null,
        ]);
    }

    /**
     * Carga la bitácora sin ejecutar los efectos secundarios de la vista del caso.
     */
    public function bitacoraCaso(Request $request, int $caso): View
    {
        $caso = $this->casosVisiblesPara($request->user())
            ->with(['tipo', 'subtipo', 'solicitante', 'solicitanteTipoDocumento', 'usuarios.role'])
            ->whereKey($caso)
            ->firstOrFail();

        $eventos = Bitacora::with(['usuario.role'])
            ->where('caso_id', $caso->id)
            ->recientesPrimero()
            ->get();
        $auditSummary = app(CaseAuditService::class)->resumen($caso);

        return view('dashboard.case-audit-modal-content', compact('caso', 'eventos', 'auditSummary'));
    }

    private function casosVisiblesPara(User $user): Builder
    {
        if ($user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado'])) {
            return Caso::query();
        }

        return Caso::whereHas('usuarios', fn ($query) => $query
            ->where('users.id', $user->id)
            ->where('caso_usuario.activo', true));
    }

    private function casoCoincide(Caso $caso, string $termino): bool
    {
        $fechas = collect([$caso->created_at, $caso->fecha_solicitud, $caso->fecha_inicio, $caso->fecha_fin])
            ->filter()
            ->flatMap(fn ($fecha) => [$fecha->format('Y-m-d'), $fecha->format('d/m/Y'), $fecha->format('d-m-Y')]);

        $valores = collect([
            $caso->radicado,
            $caso->solicitanteNombreActual(),
            $caso->solicitanteDocumentoActual(),
            $caso->solicitanteTipoActual(),
            $caso->tipo?->nombre,
            $caso->tipo?->codigo,
            $caso->subtipo?->nombre,
            $caso->subtipo?->codigo,
            $caso->descripcion,
            $caso->estado,
        ])->merge($fechas)->merge($caso->usuarios->flatMap(fn (User $responsable) => [
            $responsable->name,
            $responsable->role?->nombre,
        ]));

        return $valores->filter(fn ($valor) => is_scalar($valor))
            ->contains(fn ($valor) => str_contains($this->normalizarBusqueda((string) $valor), $termino));
    }

    private function normalizarBusqueda(string $valor): string
    {
        return Str::lower(Str::ascii(trim($valor)));
    }
}
