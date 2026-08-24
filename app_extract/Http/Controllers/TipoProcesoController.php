<?php

namespace App\Http\Controllers;

use App\Models\TipoProceso;
use App\Models\SubtipoProceso;
use App\Models\Bitacora;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TipoProcesoController extends Controller
{
    public function index()
    {
        $tipos = TipoProceso::with('subtipos')->get();
        return view('tipos.index', compact('tipos'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:3|unique:tipos_proceso,codigo',
            'descripcion' => 'nullable|string|max:500',
        ]);

        $tipo = TipoProceso::create([
            'nombre' => $request->nombre,
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
            'activo' => true,
        ]);

        Bitacora::registrar(
            modulo: 'Tipos de Proceso',
            accion: 'Crear',
            descripcion: "El usuario ".Auth::user()->name." creó el tipo de documento '{$tipo->nombre}'.",
            entidadId: $tipo->id
        );

        return redirect()->route('tipos.index')->with('success', 'Tipo de documento creado correctamente.');
    }

    public function update(Request $request, TipoProceso $tipo)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:3|unique:tipos_proceso,codigo,' . $tipo->id,
            'descripcion' => 'nullable|string|max:500',
        ]);

        $tipo->update([
            'nombre' => $request->nombre,
            'codigo' => strtoupper($request->codigo),
            'descripcion' => $request->descripcion,
        ]);

        Bitacora::registrar(
            modulo: 'Tipos de Proceso',
            accion: 'Actualizar',
            descripcion: "El usuario ".Auth::user()->name." actualizó el tipo de documento '{$tipo->nombre}'.",
            entidadId: $tipo->id
        );

        return redirect()->route('tipos.index')->with('success', 'Tipo de documento actualizado correctamente.');
    }

    public function toggleEstado(TipoProceso $tipo)
    {
        $tipo->update(['activo' => !$tipo->activo]);
        
        $estadoStr = $tipo->activo ? 'activó' : 'inactivó';
        Bitacora::registrar(
            modulo: 'Tipos de Proceso',
            accion: 'Cambio de Estado',
            descripcion: "El usuario ".Auth::user()->name." {$estadoStr} el tipo de documento '{$tipo->nombre}'.",
            entidadId: $tipo->id
        );

        return redirect()->route('tipos.index')->with('success', "Tipo de documento {$estadoStr} correctamente.");
    }

    public function storeSubtipo(Request $request, TipoProceso $tipo)
    {
        $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:3',
        ]);

        // Verificar unicidad del código dentro de los subtipos de este tipo
        if ($tipo->subtipos()->where('codigo', strtoupper($request->codigo))->exists()) {
            return redirect()->back()->with('error', 'El código de subtipo ya existe para este tipo de documento.');
        }

        $subtipo = $tipo->subtipos()->create([
            'nombre' => $request->nombre,
            'codigo' => strtoupper($request->codigo),
            'activo' => true,
        ]);

        Bitacora::registrar(
            modulo: 'Subtipos de Proceso',
            accion: 'Crear',
            descripcion: "El usuario ".Auth::user()->name." creó el subtipo '{$subtipo->nombre}' para el tipo '{$tipo->nombre}'.",
            entidadId: $subtipo->id
        );

        return redirect()->route('tipos.index')->with('success', 'Subtipo de documento creado correctamente.');
    }

    public function updateSubtipo(Request $request, TipoProceso $tipo, SubtipoProceso $subtipo)
    {
        if ($subtipo->tipo_id !== $tipo->id) {
            abort(404);
        }

        $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|max:3',
        ]);

        // Verificar unicidad ignorando el actual
        if ($tipo->subtipos()->where('codigo', strtoupper($request->codigo))->where('id', '!=', $subtipo->id)->exists()) {
            return redirect()->back()->with('error', 'El código de subtipo ya existe para este tipo de documento.');
        }

        $subtipo->update([
            'nombre' => $request->nombre,
            'codigo' => strtoupper($request->codigo),
        ]);

        Bitacora::registrar(
            modulo: 'Subtipos de Proceso',
            accion: 'Actualizar',
            descripcion: "El usuario ".Auth::user()->name." actualizó el subtipo '{$subtipo->nombre}'.",
            entidadId: $subtipo->id
        );

        return redirect()->route('tipos.index')->with('success', 'Subtipo de documento actualizado correctamente.');
    }
}
