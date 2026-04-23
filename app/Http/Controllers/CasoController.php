<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\Solicitante;
use App\Models\TipoProceso;
use App\Models\SubtipoProceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CasoController extends Controller
{
    public function crear()
    {
        $tipos = TipoProceso::with('subtipos')->get();

        return view('casos.crear', compact('tipos'));
    }

    public function guardar(\App\Http\Requests\StoreCasoRequest $request)
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
                descripcion: "Caso creado con radicado {$radicado}.",
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

        return redirect()->route('tareas.index', $caso->id)
            ->with('success', "Caso {$caso->radicado} creado correctamente.");
    }
}