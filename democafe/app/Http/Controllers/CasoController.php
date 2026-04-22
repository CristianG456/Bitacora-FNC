<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TipoDocumento;
use App\Models\SubtipoDocumento;
use App\Models\Caso;

class CasoController extends Controller
{
    /**
     * Mostrar formulario
     */
    public function crear()
    {
        $tipos = TipoDocumento::with('subtipos')->get();

        return view('casos.crear', compact('tipos'));
    }

    /**
     * Guardar caso (este es el que usa tu form)
     */
    public function guardar(Request $request)
    {
        $request->validate([
            'tipo_documento_id' => 'required|exists:tipo_documento,id',
            'subtipo_documento_id' => 'required|exists:subtipo_documento,id',
            'descripcion' => 'required|string|max:1000',
            'nombre_solicitante' => 'required|string|max:255',
            'documento_solicitante' => 'required|string|max:50',
            'enlace_google_drive' => 'nullable|url',
        ]);

        Caso::create([
            'tipo_documento_id' => $request->tipo_documento_id,
            'subtipo_documento_id' => $request->subtipo_documento_id,
            'descripcion' => $request->descripcion,
            'nombre_solicitante' => $request->nombre_solicitante,
            'documento_solicitante' => $request->documento_solicitante,
            'enlace_google_drive' => $request->enlace_google_drive,
        ]);

        return redirect()
            ->route('dashboard')
            ->with('success', 'Caso creado correctamente');
    }
}