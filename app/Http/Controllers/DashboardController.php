<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(?Request $request = null)
    {
        $request ??= request();
        $user  = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']);

        // ─── Estadísticas de casos ─────────────────────────────────
        $baseQuery = $esAdmin
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q
                ->where('users.id', $user->id)
                ->where('caso_usuario.activo', true));

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
}
