<?php

namespace App\Http\Controllers;

use App\Http\Requests\TareaRequest;
use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Observacion;

class TareaController extends Controller
{


    // ─── Guardar nueva tarea ───────────────────────────────────────

    public function guardar(TareaRequest $request, Caso $caso)
    {
        $this->autorizarCrear();

        DB::transaction(function () use ($request, $caso) {

            $data = $request->validated();

            // Determinar orden: último + 1
            $ultimoOrden = $caso->tareas()->max('orden') ?? 0;
            $data['orden'] = $ultimoOrden + 1;

            $tarea = $caso->tareas()->create([
                'user_id'     => $data['user_id'],
                'descripcion' => $data['descripcion'],
                'estado'      => 'Pendiente',
                'orden'       => $data['orden'],
                'fecha_inicio' => $data['fecha_inicio'] ?? null,
                'fecha_fin'    => $data['fecha_fin']    ?? null,
            ]);

            // Actualizar el estado del usuario a 'En proceso' ya que se le añadió una nueva tarea
            $caso->usuarios()->updateExistingPivot($data['user_id'], ['estado' => 'En proceso']);

            // Notificar al usuario asignado
            Notificacion::enviar(
                $tarea->user_id,
                'Nueva tarea asignada',
                "Se te asignó la tarea: \"{$tarea->descripcion}\" en el caso {$caso->radicado}.",
                'tarea'
            );

            // Obtener el nombre del usuario asignado
            $usuarioAsignado = User::find($tarea->user_id);
            $nombreAsignado = $usuarioAsignado ? $usuarioAsignado->name : 'desconocido';

            // Bitácora
            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Crear',
                descripcion:     "El usuario ".Auth::user()->name." asignó la tarea '{$tarea->descripcion}' a {$nombreAsignado}.",
                casoId:          $caso->id,
                entidadId:       $tarea->id,
                usuarioAfectado: $tarea->user_id,
                metadata:        ['descripcion' => $tarea->descripcion, 'estado' => $tarea->estado]
            );
        });

        return redirect()->route('casos.show', $caso->id)
            ->with('success', 'Tarea creada correctamente y usuario notificado.');
    }



    // ─── Eliminar tarea (soft delete) ──────────────────────────────

    public function eliminar(Caso $caso, Tarea $tarea)
    {
        // Solo administradores pueden eliminar
        if (!Auth::user()->tieneAlgunRol(['Administrador', 'Juridica'])) {
            abort(403, 'No tienes permiso para eliminar tareas.');
        }

        $this->verificarTareaDeCaso($caso, $tarea);

        DB::transaction(function () use ($caso, $tarea) {

            $nombreAsignado = $tarea->usuario ? $tarea->usuario->name : 'nadie';

            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Eliminar',
                descripcion:     "El usuario ".Auth::user()->name." eliminó la tarea '{$tarea->descripcion}' que estaba asignada a {$nombreAsignado}.",
                casoId:          $caso->id,
                entidadId:       $tarea->id,
                usuarioAfectado: $tarea->user_id,
                metadata:        ['descripcion' => $tarea->descripcion, 'estado' => $tarea->estado]
            );

            $tarea->delete();
        });

        return redirect()->route('casos.show', $caso->id)
            ->with('success', 'Tarea eliminada correctamente.');
    }



    // ─── Completar tarea (con observación) ─────────────────────────

    public function completar(Request $request, Caso $caso, Tarea $tarea)
    {
        $request->validate([
            'observacion' => 'required|string|min:5|max:2000'
        ], [
            'observacion.required' => 'La observación es obligatoria para finalizar la tarea.',
            'observacion.min' => 'La observación debe tener al menos 5 caracteres.'
        ]);

        $this->verificarTareaDeCaso($caso, $tarea);

        if ($tarea->user_id !== Auth::id() && !Auth::user()->tieneAlgunRol(['Administrador', 'Juridica'])) {
            abort(403, 'Solo el usuario asignado puede completar esta tarea.');
        }

        DB::transaction(function () use ($request, $caso, $tarea) {
            $tarea->update([
                'estado' => 'Completada',
                'fecha_fin' => now(),
            ]);

            Observacion::create([
                'tarea_id' => $tarea->id,
                'user_id' => Auth::id(),
                'contenido' => $request->input('observacion'),
            ]);

            // Actualizar estado del usuario en el caso
            $totalUsuario = $caso->tareas()->where('user_id', $tarea->user_id)->count();
            $completadasUsuario = $caso->tareas()->where('user_id', $tarea->user_id)->where('estado', 'Completada')->count();

            if ($totalUsuario > 0) {
                if ($completadasUsuario === $totalUsuario) {
                    $caso->usuarios()->updateExistingPivot($tarea->user_id, ['estado' => 'Finalizado']);
                } else {
                    $caso->usuarios()->updateExistingPivot($tarea->user_id, ['estado' => 'En proceso']);
                }
            }

            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Completar',
                descripcion:     "El usuario ".Auth::user()->name." completó la tarea '{$tarea->descripcion}'.",
                casoId:          $caso->id,
                entidadId:       $tarea->id,
                usuarioAfectado: Auth::id(),
                metadata:        ['observacion' => $request->input('observacion')]
            );
        });

        return redirect()->route('casos.show', $caso->id)
            ->with('success', 'Tarea completada exitosamente.');
    }

    // ─── Métodos privados de autorización ──────────────────────────



    private function autorizarCrear(): void
    {
        if (!Auth::user()->tieneAlgunRol(['Administrador', 'Juridica'])) {
            abort(403, 'Solo administradores o jurídica pueden crear tareas.');
        }
    }



    private function verificarTareaDeCaso(Caso $caso, Tarea $tarea): void
    {
        if ($tarea->caso_id !== $caso->id) {
            abort(404, 'La tarea no pertenece a este caso.');
        }
    }
}
