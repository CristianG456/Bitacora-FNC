<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\Tarea;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user  = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica']);

        // ─── Estadísticas de casos ─────────────────────────────────
        $baseQuery = $esAdmin
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q->where('users.id', $user->id)->where('activo', true));

        $totalCasos    = (clone $baseQuery)->count();
        $enProceso     = (clone $baseQuery)->where('estado', 'En proceso')->count();
        $completados   = (clone $baseQuery)->where('estado', 'Completado')->count();
        $finalizados   = (clone $baseQuery)->where('estado', 'Finalizado')->count();
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