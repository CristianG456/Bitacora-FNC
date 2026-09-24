<?php

namespace App\Http\Controllers;

use App\Http\Requests\CorregirCasoRequest;
use App\Models\Caso;
use App\Services\CorreccionCasoService;

class CorreccionCasoController extends Controller
{
    public function update(
        CorregirCasoRequest $request,
        Caso $caso,
        CorreccionCasoService $service,
    ) {
        $service->corregir($caso, $request->validated());

        return redirect()->route('casos.show', $caso)
            ->with('success', 'La corrección fue registrada con trazabilidad completa.');
    }
}
