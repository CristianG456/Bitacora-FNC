<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TipoProceso;
use App\Models\SubtipoProceso;
use App\Models\Caso;

class CasoController extends Controller
{
    public function crear()
    {
        $tipos = TipoProceso::with('subtipos')->get();

        return view('casos.crear', compact('tipos'));
    }
    public function guardar(Request $request)
    {
        $data = $request->validate([
            'tipo_proceso_id' => 'required|exists:tipos_proceso,id',
            'subtipo_proceso_id' => 'required|exists:subtipos_proceso,id',
            'descripcion' => 'required|string|max:1000',
            'nombre_solicitante' => 'required|string|max:255',
            'documento_solicitante' => 'required|string|max:50',
            'enlace_google_drive' => 'nullable|url',
        ]);

        Caso::create($data);

        return redirect()->route('dashboard')
            ->with('success', 'Caso creado correctamente');
    }
}