<?php

namespace App\Http\Controllers;

use App\Http\Requests\TareaRequest;
use App\Http\Requests\ObservacionRequest;
use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Notificacion;
use App\Models\Observacion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TareaController extends Controller
{
    // ─── Lista de tareas de un caso ────────────────────────────────

    public function index(Caso $caso)
    {
        $this->autorizarAcceso($caso);

        $tareas = $caso->tareas()
            ->with(['usuario', 'observaciones.autor'])
            ->orderBy('orden')
            ->orderBy('created_at')
            ->get();

        return view('tareas.index', compact('caso', 'tareas'));
    }

    // ─── Formulario de creación ────────────────────────────────────

    public function crear(Caso $caso)
    {
        $this->autorizarCrear();

        // Solo usuarios asignados al caso pueden recibir tareas
        $usuarios = $caso->usuarios()
            ->wherePivot('activo', true)
            ->get();

        // Si no hay usuarios asignados, permitir cualquier usuario activo
        if ($usuarios->isEmpty()) {
            $usuarios = User::where('activo', true)->orderBy('name')->get();
        }

        return view('tareas.crear', compact('caso', 'usuarios'));
    }

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

            // Observación obligatoria al crear
            Observacion::create([
                'tarea_id'   => $tarea->id,
                'user_id'    => Auth::id(),
                'contenido'  => $data['observacion'],
                'created_at' => now(),
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

        return redirect()->route('tareas.index', $caso->id)
            ->with('success', 'Tarea creada correctamente y usuario notificado.');
    }

    // ─── Formulario de edición ─────────────────────────────────────

    public function editar(Caso $caso, Tarea $tarea)
    {
        $this->verificarTareaDeCaso($caso, $tarea);
        $this->autorizarEdicion($tarea);

        $usuarios = $caso->usuarios()
            ->wherePivot('activo', true)
            ->get();

        if ($usuarios->isEmpty()) {
            $usuarios = User::where('activo', true)->orderBy('name')->get();
        }

        $observaciones = $tarea->observaciones()->with('autor')->latest('created_at')->get();

        return view('tareas.editar', compact('caso', 'tarea', 'usuarios', 'observaciones'));
    }

    // ─── Actualizar tarea ──────────────────────────────────────────

    public function actualizar(TareaRequest $request, Caso $caso, Tarea $tarea)
    {
        $this->verificarTareaDeCaso($caso, $tarea);
        $this->autorizarEdicion($tarea);

        $data = $request->validated();

        DB::transaction(function () use ($data, $caso, $tarea) {

            $estadoAnterior = $tarea->estado;

            $tarea->update([
                'user_id'      => $data['user_id'],
                'descripcion'  => $data['descripcion'],
                'estado'       => $data['estado'],
                'orden'        => $data['orden'] ?? $tarea->orden,
                'fecha_inicio' => $data['fecha_inicio'] ?? $tarea->fecha_inicio,
                'fecha_fin'    => $data['fecha_fin']    ?? $tarea->fecha_fin,
            ]);

            // Registrar observación si se proporcionó (obligatoria al completar)
            if (!empty($data['observacion'])) {
                Observacion::create([
                    'tarea_id'   => $tarea->id,
                    'user_id'    => Auth::id(),
                    'contenido'  => $data['observacion'],
                    'created_at' => now(),
                ]);
            }

            // Notificar cambio de estado
            if ($estadoAnterior !== $data['estado']) {
                Notificacion::enviar(
                    $tarea->user_id,
                    'Estado de tarea actualizado',
                    "Tu tarea \"{$tarea->descripcion}\" cambió de \"{$estadoAnterior}\" a \"{$data['estado']}\" en el caso {$caso->radicado}.",
                    'tarea'
                );
            }

            // Bitácora
            Bitacora::registrar(
                modulo:          'Tareas',
                accion:          'Actualizar',
                descripcion:     "Tarea ID {$tarea->id} actualizada en el caso {$caso->radicado}. Estado: {$estadoAnterior} → {$data['estado']}.",
                casoId:          $caso->id,
                entidadId:       $tarea->id,
                usuarioAfectado: $tarea->user_id,
                metadata:        [
                    'estado_anterior' => $estadoAnterior,
                    'estado_nuevo'    => $data['estado'],
                ]
            );
        });

        return redirect()->route('tareas.index', $caso->id)
            ->with('success', 'Tarea actualizada correctamente.');
    }

    // ─── Ver detalle de tarea ──────────────────────────────────────

    public function ver(Caso $caso, Tarea $tarea)
    {
        $this->verificarTareaDeCaso($caso, $tarea);
        $this->autorizarAcceso($caso);

        $observaciones = $tarea->observaciones()->with('autor')->latest('created_at')->get();

        return view('tareas.ver', compact('caso', 'tarea', 'observaciones'));
    }

    // ─── Agregar observación a tarea existente ─────────────────────

    public function agregarObservacion(ObservacionRequest $request, Caso $caso, Tarea $tarea)
    {
        $this->verificarTareaDeCaso($caso, $tarea);
        $this->autorizarAcceso($caso);

        $observacion = Observacion::create([
            'tarea_id'   => $tarea->id,
            'user_id'    => Auth::id(),
            'contenido'  => $request->validated()['contenido'],
            'created_at' => now(),
        ]);

        Bitacora::registrar(
            modulo:      'Tareas',
            accion:      'Observación',
            descripcion: "Observación agregada a tarea ID {$tarea->id} en caso {$caso->radicado}.",
            casoId:      $caso->id,
            entidadId:   $tarea->id,
            metadata:    ['observacion_id' => $observacion->id]
        );

        return redirect()->route('tareas.ver', [$caso->id, $tarea->id])
            ->with('success', 'Observación agregada correctamente.');
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

        return redirect()->route('tareas.index', $caso->id)
            ->with('success', 'Tarea eliminada correctamente.');
    }

    // ─── Cambio rápido de estado (AJAX / form) ─────────────────────

    public function cambiarEstado(Request $request, Caso $caso, Tarea $tarea)
    {
        $this->verificarTareaDeCaso($caso, $tarea);

        $request->validate([
            'estado'      => ['required', 'in:Pendiente,En proceso,Completada'],
            'observacion' => ['required_if:estado,Completada', 'nullable', 'string', 'min:10', 'max:2000'],
        ], [
            'estado.required'           => 'El estado es obligatorio.',
            'estado.in'                 => 'Estado inválido.',
            'observacion.required_if'   => 'Debes agregar una observación para marcar la tarea como Completada.',
            'observacion.min'           => 'La observación debe tener al menos 10 caracteres.',
        ]);

        DB::transaction(function () use ($request, $caso, $tarea) {

            $estadoAnterior = $tarea->estado;
            $tarea->update(['estado' => $request->estado]);

            if (!empty($request->observacion)) {
                Observacion::create([
                    'tarea_id'   => $tarea->id,
                    'user_id'    => Auth::id(),
                    'contenido'  => $request->observacion,
                    'created_at' => now(),
                ]);
            }

            Bitacora::registrar(
                modulo:      'Tareas',
                accion:      'Cambio de Estado',
                descripcion: "Tarea ID {$tarea->id}: Estado cambiado de \"{$estadoAnterior}\" a \"{$request->estado}\".",
                casoId:      $caso->id,
                entidadId:   $tarea->id,
                metadata:    ['estado_anterior' => $estadoAnterior, 'estado_nuevo' => $request->estado]
            );
        });

        return redirect()->route('tareas.index', $caso->id)
            ->with('success', 'Estado de la tarea actualizado.');
    }

    // ─── Métodos privados de autorización ──────────────────────────

    private function autorizarAcceso(Caso $caso): void
    {
        $user = Auth::user();

        // Administradores y Jurídica ven todo
        if ($user->tieneAlgunRol(['Administrador', 'Juridica'])) {
            return;
        }

        // Otros usuarios: solo si están asignados al caso
        $asignado = $caso->usuarios()
            ->where('users.id', $user->id)
            ->wherePivot('activo', true)
            ->exists();

        if (!$asignado) {
            abort(403, 'No tienes acceso a este caso.');
        }
    }

    private function autorizarCrear(): void
    {
        if (!Auth::user()->tieneAlgunRol(['Administrador', 'Juridica'])) {
            abort(403, 'Solo administradores o jurídica pueden crear tareas.');
        }
    }

    private function autorizarEdicion(Tarea $tarea): void
    {
        $user = Auth::user();

        // Admin/Jurídica pueden editar cualquier tarea
        if ($user->tieneAlgunRol(['Administrador', 'Juridica'])) {
            return;
        }

        // El propio usuario asignado puede actualizar el estado
        if ($tarea->user_id === $user->id) {
            return;
        }

        abort(403, 'No tienes permiso para editar esta tarea.');
    }

    private function verificarTareaDeCaso(Caso $caso, Tarea $tarea): void
    {
        if ($tarea->caso_id !== $caso->id) {
            abort(404, 'La tarea no pertenece a este caso.');
        }
    }
}
