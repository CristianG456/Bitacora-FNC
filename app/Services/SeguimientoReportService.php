<?php

namespace App\Services;

use App\Models\Caso;
use App\Models\SubtipoProceso;
use App\Models\TipoProceso;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SeguimientoReportService
{
    public const ROLES_OPERATIVOS = ['Juridica', 'Usuario', 'Abogado'];

    public const ESTADOS_CASO = ['Pendiente', 'En proceso', 'Completado', 'Finalizado'];

    public const ESTADOS_ANS = ['vigente', 'preventivo', 'critico', 'vencido', 'cumplido', 'incumplido'];

    public function __construct(private readonly AnsService $ansService) {}

    public function datos(array $filtros): array
    {
        $casos = $this->casos($filtros);
        $opciones = $this->opciones();

        return [
            'casos' => $casos,
            'resumen' => $this->resumen($casos),
            'responsables' => $this->responsables($casos, $filtros),
            'porTipo' => $this->porTipo($casos, $filtros),
            'casosAtencion' => $this->casosAtencion($casos),
            'opciones' => $opciones,
            'filtros' => $filtros,
            'filtrosAplicados' => $this->filtrosAplicados($filtros, $opciones),
        ];
    }

    public function casos(array $filtros): Collection
    {
        $query = Caso::query()
            ->with([
                'tipo',
                'subtipo',
                'usuarios' => fn ($q) => $q->wherePivot('activo', true)
                    ->whereHas('role', fn ($roles) => $roles->whereIn('nombre', self::ROLES_OPERATIVOS))
                    ->with('role'),
                'tareas.usuario.role',
            ])
            ->withMax('bitacoras', 'created_at')
            ->orderByDesc('fecha_solicitud')
            ->orderByDesc('id');

        $this->aplicarFiltros($query, $filtros);

        return $query->get()->map(fn (Caso $caso) => $this->decorarCaso($caso));
    }

    public function aplicarFiltros(Builder $query, array $filtros): Builder
    {
        $query
            ->when($filtros['responsable_id'] ?? null, fn ($q, $id) => $q->whereHas(
                'usuarios',
                fn ($usuarios) => $usuarios->where('users.id', $id)->where('caso_usuario.activo', true)
            ))
            ->when($filtros['rol'] ?? null, fn ($q, $rol) => $q->whereHas(
                'usuarios',
                fn ($usuarios) => $usuarios->where('caso_usuario.activo', true)
                    ->whereHas('role', fn ($roles) => $roles->where('nombre', $rol))
            ))
            ->when($filtros['tipo_ids'] ?? [], fn ($q, $ids) => $q->whereIn('tipo_id', $ids))
            ->when($filtros['subtipo_id'] ?? null, fn ($q, $id) => $q->where('subtipo_id', $id))
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('estado', $estado))
            ->when($filtros['ans_estado'] ?? null, fn ($q, $estado) => $q->where('ans_estado', $estado))
            ->when($filtros['desde'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha_solicitud', '>=', $fecha))
            ->when($filtros['hasta'] ?? null, fn ($q, $fecha) => $q->whereDate('fecha_solicitud', '<=', $fecha))
            ->when(($filtros['con_tareas_pendientes'] ?? null) === '1', fn ($q) => $q->whereHas(
                'tareas', fn ($tareas) => $tareas->where('estado', '!=', 'Completada')
            ));

        return $query;
    }

    public function resumen(Collection $casos): array
    {
        return [
            'total' => $casos->count(),
            'activos' => $casos->where('estado', '!=', 'Finalizado')->count(),
            'proximos' => $casos->whereIn('ans_estado', ['preventivo', 'critico'])->count(),
            'vencidos' => $casos->whereIn('ans_estado', ['vencido', 'incumplido'])->count(),
            'tareas_pendientes' => $casos->sum('tareas_pendientes_count'),
            'por_estado' => collect(self::ESTADOS_CASO)->mapWithKeys(
                fn ($estado) => [$estado => $casos->where('estado', $estado)->count()]
            )->all(),
            'por_ans' => collect(self::ESTADOS_ANS)->mapWithKeys(
                fn ($estado) => [$estado => $casos->where('ans_estado', $estado)->count()]
            )->all(),
        ];
    }

    public function responsables(Collection $casos, array $filtros = []): Collection
    {
        $usuarios = User::query()
            ->with('role')
            ->where('activo', true)
            ->whereHas('role', fn ($q) => $q->whereIn('nombre', self::ROLES_OPERATIVOS))
            ->when($filtros['rol'] ?? null, fn ($q, $rol) => $q->whereHas('role', fn ($r) => $r->where('nombre', $rol)))
            ->when($filtros['responsable_id'] ?? null, fn ($q, $id) => $q->whereKey($id))
            ->orderBy('name')
            ->get();

        return $usuarios->map(function (User $usuario) use ($casos) {
            $asociados = $casos->filter(fn (Caso $caso) => $caso->usuarios->contains('id', $usuario->id));
            $tareas = $asociados->flatMap(fn (Caso $caso) => $caso->tareas->where('user_id', $usuario->id));

            return (object) [
                'usuario' => $usuario,
                'casos' => $asociados->values(),
                'casos_asociados' => $asociados->count(),
                'casos_activos' => $asociados->where('estado', '!=', 'Finalizado')->count(),
                'tareas_pendientes' => $tareas->where('estado', '!=', 'Completada')->count(),
                'tareas_completadas' => $tareas->where('estado', 'Completada')->count(),
                'casos_proximos' => $asociados->whereIn('ans_estado', ['preventivo', 'critico'])->count(),
                'casos_vencidos' => $asociados->whereIn('ans_estado', ['vencido', 'incumplido'])->count(),
            ];
        });
    }

    public function porTipo(Collection $casos, array $filtros = []): Collection
    {
        return TipoProceso::query()
            ->where('activo', true)
            ->when($filtros['tipo_ids'] ?? [], fn ($q, $ids) => $q->whereKey($ids))
            ->orderBy('nombre')
            ->get()
            ->map(function (TipoProceso $tipo) use ($casos) {
                $delTipo = $casos->where('tipo_id', $tipo->id);

                return (object) [
                    'tipo' => $tipo,
                    'total' => $delTipo->count(),
                    'estados' => collect(self::ESTADOS_CASO)->mapWithKeys(
                        fn ($estado) => [$estado => $delTipo->where('estado', $estado)->count()]
                    )->all(),
                    'ans' => collect(self::ESTADOS_ANS)->mapWithKeys(
                        fn ($estado) => [$estado => $delTipo->where('ans_estado', $estado)->count()]
                    )->all(),
                    'cumplidos' => $delTipo->where('ans_estado', 'cumplido')->count(),
                    'incumplidos' => $delTipo->where('ans_estado', 'incumplido')->count(),
                ];
            });
    }

    public function casosAtencion(Collection $casos): Collection
    {
        return $casos
            ->filter(fn (Caso $caso) => in_array($caso->ans_estado, ['vencido', 'incumplido', 'critico', 'preventivo'], true)
                || $caso->tareas_pendientes_count > 0)
            ->sortBy(function (Caso $caso) {
                $prioridad = match ($caso->ans_estado) {
                    'vencido', 'incumplido' => 1,
                    'critico' => 2,
                    'preventivo' => 3,
                    default => 4,
                };
                $fechaTarea = $caso->tareaPendienteMasAntigua?->fecha_inicio
                    ?? $caso->tareaPendienteMasAntigua?->created_at;

                return sprintf('%02d-%012d-%010d', $prioridad, $caso->dias_restantes_calculados ?? 999999, $fechaTarea?->getTimestamp() ?? PHP_INT_MAX);
            })
            ->values();
    }

    public function opciones(): array
    {
        return [
            'responsables' => User::query()->with('role')->where('activo', true)
                ->whereHas('role', fn ($q) => $q->whereIn('nombre', self::ROLES_OPERATIVOS))
                ->orderBy('name')->get(),
            'tipos' => TipoProceso::query()->where('activo', true)->orderBy('nombre')->get(),
            'subtipos' => SubtipoProceso::query()->with('tipo')->where('activo', true)->orderBy('nombre')->get(),
            'roles' => self::ROLES_OPERATIVOS,
            'estados' => self::ESTADOS_CASO,
            'estadosAns' => self::ESTADOS_ANS,
        ];
    }

    public function filtrosAplicados(array $filtros, ?array $opciones = null): array
    {
        $opciones ??= $this->opciones();
        $tiposSeleccionados = collect($filtros['tipo_ids'] ?? [])
            ->map(fn ($id) => $opciones['tipos']->firstWhere('id', (int) $id)?->nombre)
            ->filter()
            ->values();

        return [
            'Periodo' => ($filtros['desde'] ?? 'Todos').' a '.($filtros['hasta'] ?? 'Todos'),
            'Tipos' => $tiposSeleccionados->isEmpty() ? 'Todos' : $tiposSeleccionados->join(', '),
            'Subtipo' => $opciones['subtipos']->firstWhere('id', (int) ($filtros['subtipo_id'] ?? 0))?->nombre ?? 'Todos',
            'Estado' => $filtros['estado'] ?? 'Todos',
            'ANS' => $filtros['ans_estado'] ?? 'Todos',
            'Responsable' => $opciones['responsables']->firstWhere('id', (int) ($filtros['responsable_id'] ?? 0))?->name ?? 'Todos',
            'Rol' => $filtros['rol'] ?? 'Todos',
            'Tareas pendientes' => ($filtros['con_tareas_pendientes'] ?? null) === '1' ? 'Sí' : 'Todos',
        ];
    }

    private function decorarCaso(Caso $caso): Caso
    {
        $referencia = $caso->estado === 'Finalizado' && $caso->fecha_fin
            ? CarbonImmutable::parse($caso->fecha_fin->toDateString(), 'America/Bogota')
            : now('America/Bogota');
        $diasRestantes = $this->ansService->diasRestantes($caso, $referencia);
        $pendientes = $caso->tareas->where('estado', '!=', 'Completada')
            ->sortBy(fn ($tarea) => $tarea->fecha_inicio ?? $tarea->created_at);
        $ultimaActividad = $caso->bitacoras_max_created_at
            ? CarbonImmutable::parse($caso->bitacoras_max_created_at)->timezone('America/Bogota')
            : null;
        $inicio = $caso->fecha_solicitud ?? $caso->created_at;

        $caso->setAttribute('dias_restantes_calculados', $diasRestantes);
        $caso->setAttribute('dias_retraso', $diasRestantes !== null && $diasRestantes < 0 ? abs($diasRestantes) : 0);
        $caso->setAttribute('tareas_pendientes_count', $pendientes->count());
        $caso->setRelation('tareaPendienteMasAntigua', $pendientes->first());
        $caso->setAttribute('ultimo_movimiento', $ultimaActividad);
        $caso->setAttribute('dias_sin_movimiento', $ultimaActividad ? $ultimaActividad->startOfDay()->diffInDays(now('America/Bogota')->startOfDay()) : null);
        $caso->setAttribute('antiguedad_dias', $inicio ? CarbonImmutable::parse($inicio)->startOfDay()->diffInDays(now('America/Bogota')->startOfDay()) : null);

        return $caso;
    }
}
