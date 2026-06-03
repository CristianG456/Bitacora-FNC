<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']);

        // ─── Estadísticas de casos ─────────────────────────────────
        $baseQuery = $esAdmin
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q->where('users.id', $user->id));

        $totalCasos    = (clone $baseQuery)->count();
        $enProceso     = (clone $baseQuery)->where('estado', 'En proceso')->count();
        // 'Completado' nunca se alcanza por lógica de la app; se cuenta 'Finalizado'
        // para que la tarjeta muestre los casos efectivamente cerrados.
        $completados   = (clone $baseQuery)->where('estado', 'Finalizado')->count();
        $finalizados   = $completados; // Alias para mantener compatibilidad con la vista
        $pendientes    = (clone $baseQuery)->where('estado', 'Pendiente')->count();

        // ─── Casos recientes ───────────────────────────────────────
        $casosRecientes = (clone $baseQuery)
            ->with(['tipo', 'subtipo'])
            ->latest()
            ->limit(10)
            ->get();



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
            'notificacionesSinLeer'
        ));
    }
}