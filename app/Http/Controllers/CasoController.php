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
    public function index(Request $request)
    {
        $user = Auth::user();
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica']);

        $query = $esAdmin
            ? Caso::query()
            : Caso::whereHas('usuarios', fn($q) => $q->where('users.id', $user->id)->where('activo', true));

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
        $esAdmin = $user->tieneAlgunRol(['Administrador', 'Juridica']);

        // Autorización
        if (!$esAdmin) {
            $asignado = $caso->usuarios()->where('users.id', $user->id)->wherePivot('activo', true)->exists();
            if (!$asignado) {
                abort(403, 'No tienes acceso a este caso.');
            }
        }

        $caso->load([
            'tipo', 'subtipo', 'solicitante',
            'usuarios' => fn($q) => $q->wherePivot('activo', true),
            'tareas' => fn($q) => $q->with('observaciones.autor'),
            'bitacoras' => fn($q) => $q->with('usuario')->latest(),
            'mensajes' => fn($q) => $q->with('remitente')->oldest()
        ]);

        return view('casos.show', compact('caso', 'esAdmin'));
    }

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

        return redirect()->route('casos.show', $caso->id)
            ->with('success', "Caso {$caso->radicado} creado correctamente.");
    }
}