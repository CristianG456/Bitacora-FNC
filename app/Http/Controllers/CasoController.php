<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCasoRequest;
use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Solicitante;
use App\Models\SubtipoProceso;
use App\Models\Tarea;
use App\Models\TipoProceso;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CasoController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']);

        $query = $esAdmin
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q->where('users.id', $user->id));

        // Búsqueda
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('radicado', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhereHas('solicitante', function ($q2) use ($search) {
                      $q2->where('nombre', 'like', "%{$search}%")
                         ->orWhere('documento', 'like', "%{$search}%");
                  });
            });
        }

        // Filtro por estado
        $estadoFiltro = $request->input('estado', 'Todos');
        if ($estadoFiltro !== 'Todos') {
            $query->where('estado', $estadoFiltro);
        }

        $casos = $query->with(['tipo', 'usuarios'])->latest()->paginate(10);

        return view('casos.index', compact('casos', 'search', 'estadoFiltro', 'esAdmin'));
    }

    public function show(Caso $caso)
    {
        $user = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']);

        // Obtener la asignación del usuario actual
        $usuarioAsignado = $caso->usuarios()->where('users.id', $user->id)->first();

        // Autorización
        if (!$esAdmin) {
            if (!$usuarioAsignado || !$usuarioAsignado->pivot->activo) {
                abort(403, 'No tienes acceso a este caso.');
            }
        }

        // Si el usuario está asignado al caso (sea admin o no)
        if ($usuarioAsignado && $usuarioAsignado->pivot->activo) {
            
            // 1. Cambiar estado del usuario en el caso (Pivot)
            if ($usuarioAsignado->pivot->estado === 'Pendiente') {
                $caso->usuarios()->updateExistingPivot($user->id, ['estado' => 'En proceso']);
            }

            // 2. Cambio automático de estado a En proceso del CASO
            if ($caso->estado === 'Pendiente') {
                $caso->update(['estado' => 'En proceso']);
                
                Bitacora::registrar(
                    modulo: 'Casos',
                    accion: 'Cambio de Estado',
                    descripcion: "El caso pasó automáticamente a En proceso tras la revisión del usuario asignado ({$user->name}).",
                    casoId: $caso->id,
                    entidadId: $caso->id
                );
            }
        }

        $caso->load([
            'tipo', 'subtipo', 'solicitante',
            'usuarios' => fn($q) => $q->wherePivot('activo', true),
            'tareas',
            'bitacoras' => fn($q) => $q->with('usuario')->latest(),
            'mensajes' => fn($q) => $q->with('autor')->oldest()
        ]);

        return view('casos.show', compact('caso', 'esAdmin'));
    }

    public function crear()
    {
        $tipos = TipoProceso::with('subtipos')->get();

        return view('casos.crear', compact('tipos'));
    }

    public function guardar(StoreCasoRequest $request)
    {
        $data = $request->validated();

        $caso = DB::transaction(function () use ($data) {

            $tipo    = TipoProceso::findOrFail($data['tipo_proceso_id']);
            $subtipo = SubtipoProceso::findOrFail($data['subtipo_proceso_id']);

            // Crear o recuperar solicitante
            $solicitante = Solicitante::firstOrCreate(
                ['documento' => $data['documento_solicitante']],
                ['nombre'    => $data['nombre_solicitante']]
            );

            // Generar radicado
            $radicado = Caso::generarRadicado($tipo, $subtipo);

            // Crear el caso
            $caso = Caso::create([
                'radicado'           => $radicado,
                'tipo_id'            => $tipo->id,
                'subtipo_id'         => $subtipo->id,
                'descripcion'        => $data['descripcion'],
                'observacion_inicial'=> $data['observacion_inicial'] ?? null,
                'link_drive'         => $data['enlace_google_drive'] ?? null,
                'solicitante_id'     => $solicitante->id,
                'estado'             => 'Pendiente',
                'fecha_inicio'       => now()->toDateString(),
                'created_by'         => Auth::id(),
            ]);

            // Asignar Usuarios y Crear Tareas
            if (!empty($data['usuarios'])) {
                foreach ($data['usuarios'] as $userId) {
                    // Vincular el usuario al caso
                    $caso->usuarios()->attach($userId, [
                        'fecha_asignacion' => now(),
                        'estado'           => 'Pendiente',
                        'activo'           => true,
                    ]);

                    Notificacion::enviar(
                        $userId,
                        'Nuevo caso asignado',
                        "Se te ha asignado el caso radicado {$radicado}.",
                        'caso'
                    );

                    // Crear las tareas de este usuario
                    if (isset($data['tareas'][$userId])) {
                        foreach ($data['tareas'][$userId] as $descTarea) {
                            $caso->tareas()->create([
                                'user_id'     => $userId,
                                'descripcion' => $descTarea,
                                'estado'      => 'Pendiente',
                            ]);
                        }
                    }
                }
            }

            // Bitácora
            Bitacora::registrar(
                modulo:      'Casos',
                accion:      'Crear',
                descripcion: "El caso con radicado {$radicado} fue creado por ".Auth::user()->name.".",
                casoId:      $caso->id,
                entidadId:   $caso->id,
                metadata:    [
                    'radicado'    => $radicado,
                    'tipo'        => $tipo->nombre,
                    'subtipo'     => $subtipo->nombre,
                    'solicitante' => $solicitante->nombre,
                ]
            );

            return $caso;
        });

        return redirect()->route('casos.show', $caso->id)
            ->with('success', "Caso {$caso->radicado} creado correctamente.");
    }

    public function asignarUsuario(Request $request, Caso $caso)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        $userId = $request->input('user_id');

        if ($caso->usuarios()->where('users.id', $userId)->wherePivot('activo', true)->exists()) {
            return redirect()->back()->with('error', 'El usuario ya está asignado a este caso.');
        }

        // Check if user was previously assigned and deactivated
        $existente = $caso->usuarios()->where('users.id', $userId)->first();
        if ($existente) {
            $caso->usuarios()->updateExistingPivot($userId, ['activo' => true]);
        } else {
            $caso->usuarios()->attach($userId, [
                'fecha_asignacion' => now(),
                'estado'           => 'Pendiente',
                'activo'           => true,
            ]);
        }

        Notificacion::enviar(
            $userId,
            'Nuevo caso asignado',
            "Se te ha asignado el caso radicado {$caso->radicado}.",
            'caso'
        );

        $usuario = User::find($userId);

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Asignacion',
            descripcion: "El usuario ".Auth::user()->name." asignó a {$usuario->name} a este caso.",
            casoId: $caso->id,
            entidadId: $usuario->id,
            usuarioAfectado: $usuario->id
        );

        return redirect()->back()->with('success', 'Usuario asignado correctamente.');
    }

    public function removerUsuario(Caso $caso, User $usuario)
    {
        // En lugar de hacer detach, cambiamos activo a false para no perder el historial de tareas
        $caso->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Remover',
            descripcion: "El usuario ".Auth::user()->name." desvinculó a {$usuario->name} de este caso.",
            casoId: $caso->id,
            entidadId: $usuario->id,
            usuarioAfectado: $usuario->id
        );

        return redirect()->back()->with('success', 'Usuario removido del caso correctamente.');
    }

    public function reemplazarUsuario(Request $request, Caso $caso, User $usuario)
    {
        $request->validate([
            'nuevo_user_id' => 'required|exists:users,id|different:'.$usuario->id
        ]);

        $nuevoUsuarioId = $request->input('nuevo_user_id');
        $nuevoUsuario = User::find($nuevoUsuarioId);

        DB::transaction(function () use ($caso, $usuario, $nuevoUsuarioId, $nuevoUsuario) {
            // 1. Asignar nuevo usuario si no existe o reactivarlo
            $existente = $caso->usuarios()->where('users.id', $nuevoUsuarioId)->first();
            if ($existente) {
                $caso->usuarios()->updateExistingPivot($nuevoUsuarioId, ['activo' => true]);
            } else {
                $caso->usuarios()->attach($nuevoUsuarioId, [
                    'fecha_asignacion' => now(),
                    'estado'           => 'Pendiente',
                    'activo'           => true,
                ]);
            }

            Notificacion::enviar(
                $nuevoUsuarioId,
                'Reasignación de caso',
                "Se te ha reasignado el caso radicado {$caso->radicado}.",
                'caso'
            );

            // 2. Transferir todas las tareas del caso del usuario viejo al nuevo
            Tarea::where('caso_id', $caso->id)
                ->where('user_id', $usuario->id)
                ->update(['user_id' => $nuevoUsuarioId]);

            // 3. Desactivar el usuario viejo
            $caso->usuarios()->updateExistingPivot($usuario->id, ['activo' => false]);

            // 4. Registrar en bitácora
            Bitacora::registrar(
                modulo: 'Casos',
                accion: 'Reemplazar',
                descripcion: "El usuario ".Auth::user()->name." reemplazó a {$usuario->name} por {$nuevoUsuario->name} y le transfirió sus tareas.",
                casoId: $caso->id,
                entidadId: $nuevoUsuarioId,
                usuarioAfectado: $usuario->id
            );
        });

        return redirect()->back()->with('success', "{$usuario->name} ha sido reemplazado por {$nuevoUsuario->name} correctamente.");
    }

    public function enviarMensaje(Request $request, Caso $caso)
    {
        $request->validate([
            'mensaje' => 'required|string|max:1000'
        ]);

        $mensaje = $caso->mensajes()->create([
            'user_id' => Auth::id(),
            'mensaje' => $request->input('mensaje'),
            'created_at' => now()
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'mensaje' => $mensaje->mensaje,
                'fecha'   => $mensaje->created_at->format('d M, H:i \h')
            ]);
        }

        return redirect()->route('casos.show', $caso->id)
            ->with('tab', 'mensajes') // Para abrir la tab correcta al recargar
            ->with('success', 'Mensaje enviado.');
    }

    public function finalizar(Request $request, Caso $caso)
    {
        // Validar que todas las tareas estén completadas
        $totalTareas = $caso->tareas()->count();
        $tareasCompletadas = $caso->tareas()->where('estado', 'Completada')->count();

        if ($totalTareas === 0 || $totalTareas !== $tareasCompletadas) {
            return redirect()->back()->with('error', 'No se puede finalizar el caso porque tiene tareas pendientes o no tiene tareas asignadas.');
        }

        if ($caso->estado === 'Finalizado') {
            return redirect()->back()->with('error', 'El caso ya se encuentra finalizado.');
        }

        $caso->update([
            'estado' => 'Finalizado'
        ]);

        Bitacora::registrar(
            modulo: 'Casos',
            accion: 'Cambio de Estado',
            descripcion: "El caso fue marcado como Finalizado por ".Auth::user()->name.".",
            casoId: $caso->id,
            entidadId: $caso->id
        );

        // Notificar a todos los usuarios asignados
        foreach ($caso->usuarios as $usuario) {
            Notificacion::enviar(
                $usuario->id,
                'Caso Finalizado',
                "El caso radicado {$caso->radicado} en el que estabas asignado ha sido finalizado.",
                'success'
            );
        }

        return redirect()->back()->with('success', 'El caso ha sido finalizado exitosamente.');
    }
}