<?php

namespace App\Http\Controllers;

use App\Models\Notificacion;
use App\Models\Tarea;
use App\Support\LocalDate;
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

    public function marcarLeidas(Request $request)
    {
        $data = $request->validate([
            'categoria' => ['nullable', 'in:general,mensaje,todas'],
            'ids' => ['nullable', 'array', 'max:50'],
            'ids.*' => ['integer'],
        ]);
        $categoria = $data['categoria'] ?? 'todas';

        $query = Auth::user()->notificaciones()->where('leido', false);

        if (!empty($data['ids'])) {
            $query->whereIn('id', $data['ids']);
        }

        if ($categoria === 'mensaje') {
            $query->where('tipo', 'mensaje');
        } elseif ($categoria === 'general') {
            $query->where(fn ($notificaciones) => $notificaciones
                ->whereNull('tipo')
                ->orWhere('tipo', '!=', 'mensaje'));
        }

        $query->update(['leido' => true]);

        if ($request->wantsJson()) {
            $generalesSinLeer = Auth::user()->notificaciones()
                ->where('leido', false)
                ->where(fn ($notificaciones) => $notificaciones
                    ->whereNull('tipo')
                    ->orWhere('tipo', '!=', 'mensaje'))
                ->count();
            $mensajesSinLeer = Auth::user()->notificaciones()
                ->where('leido', false)
                ->where('tipo', 'mensaje')
                ->count();

            return response()->json([
                'success' => true,
                'categoria' => $categoria,
                'sinLeer' => $generalesSinLeer,
                'mensajesSinLeer' => $mensajesSinLeer,
            ]);
        }

        return redirect()->back()->with('success', 'Notificaciones actualizadas.');
    }

    public function getRecientes()
    {
        $user = Auth::user();
        $generalesQuery = $user->notificaciones()
            ->where(fn ($notificaciones) => $notificaciones
                ->whereNull('tipo')
                ->orWhere('tipo', '!=', 'mensaje'));
        $mensajesQuery = $user->notificaciones()->where('tipo', 'mensaje');

        $notificaciones = (clone $generalesQuery)
            ->with(['caso:id', 'mensajeRelacionado:id,user_id,destinatario_id'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($notif) => $this->serializar($notif));

        $mensajes = (clone $mensajesQuery)
            ->with(['caso:id', 'mensajeRelacionado:id,user_id,destinatario_id'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($notif) => $this->serializar($notif));

        $tareasQuery = Tarea::query()
            ->with('caso:id,radicado')
            ->where('user_id', $user->id)
            ->where('estado', '!=', 'Completada')
            ->whereHas('caso.usuarios', fn ($usuarios) => $usuarios
                ->where('users.id', $user->id)
                ->where('caso_usuario.activo', true));

        $tareasPendientes = $user->esConsultor() ? 0 : (clone $tareasQuery)->count();
        $tareas = $user->esConsultor()
            ? collect()
            : $tareasQuery->latest('id')->limit(10)->get()->map(fn ($tarea) => [
                'id' => $tarea->id,
                'descripcion' => $tarea->descripcion,
                'estado' => $tarea->estado,
                'caso' => $tarea->caso?->radicado,
                'url' => $tarea->caso ? route('casos.show', $tarea->caso, false) : null,
            ]);

        $sinLeer = (clone $generalesQuery)->where('leido', false)->count();
        $mensajesSinLeer = (clone $mensajesQuery)->where('leido', false)->count();

        return response()->json([
            'notificaciones' => $notificaciones,
            'sinLeer' => $sinLeer,
            'mensajes' => $mensajes,
            'mensajesSinLeer' => $mensajesSinLeer,
            'tareas' => $tareas,
            'tareasPendientes' => $tareasPendientes,
        ]);
    }

    private function serializar(Notificacion $notificacion): array
    {
        $url = null;

        if ($notificacion->caso_id) {
            if ($notificacion->tipo === 'mensaje') {
                $mensaje = $notificacion->mensajeRelacionado;
                $parametros = ['tab' => 'mensajes', 'chat' => 'general'];

                if ($mensaje?->destinatario_id) {
                    $interlocutorId = $mensaje->user_id === $notificacion->user_id
                        ? $mensaje->destinatario_id
                        : $mensaje->user_id;
                    $parametros = [
                        'tab' => 'mensajes',
                        'chat' => 'directo',
                        'usuario' => $interlocutorId,
                    ];
                }

                $url = route('casos.show', $notificacion->caso_id, false).'?'.http_build_query($parametros);
            } elseif (in_array($notificacion->tipo, ['tarea', 'correccion_tarea'], true)) {
                $fragmento = $notificacion->tarea_id
                    ? '#tarea-'.$notificacion->tarea_id
                    : '#mis-tareas';
                $url = route('casos.show', $notificacion->caso_id, false).$fragmento;
            } else {
                $url = route('casos.show', $notificacion->caso_id, false);
            }
        }

        return [
            'id' => $notificacion->id,
            'tipo' => $notificacion->tipo,
            'titulo' => $notificacion->titulo,
            'mensaje' => $notificacion->mensaje,
            'leido' => $notificacion->leido,
            'fecha' => LocalDate::inBogota($notificacion->created_at)?->locale('es')->diffForHumans(),
            'url' => $url,
            'caso_id' => $notificacion->caso_id,
            'tarea_id' => $notificacion->tarea_id,
            'solicitud_correccion_id' => $notificacion->solicitud_correccion_id,
        ];
    }
}
