<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificacionController extends Controller
{
    public function index()
    {
        $notificaciones = Auth::user()->notificaciones()
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('notificaciones.index', compact('notificaciones'));
    }

    public function marcarLeidas()
    {
        Auth::user()->notificaciones()
            ->where('leido', false)
            ->update(['leido' => true]);

        return redirect()->back()->with('success', 'Todas las notificaciones marcadas como leídas.');
    }

    public function getRecientes()
    {
        $notificaciones = Auth::user()->notificaciones()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'titulo' => $notif->titulo,
                    'mensaje' => $notif->mensaje,
                    'leido' => $notif->leido,
                    'fecha' => $notif->created_at->diffForHumans(),
                ];
            });

        return response()->json($notificaciones);
    }
}
