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

            // Notificar al usuario asignado
            Notificacion::enviar(
                $tarea->user_id,
                'Nueva tarea asignada',
                "Se te asignó la tarea: \"{$tarea->descripcion}\" en el caso {$caso->radicado}.",
                'tarea'
            );

            // Bitácora
            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Crear',
                descripcion:     "Tarea creada para el usuario ID {$tarea->user_id} en el caso {$caso->radicado}.",
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

            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Eliminar',
                descripcion:     "Tarea ID {$tarea->id} eliminada del caso {$caso->radicado}.",
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
