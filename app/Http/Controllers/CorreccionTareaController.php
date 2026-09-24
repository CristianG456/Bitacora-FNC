<?php

namespace App\Http\Controllers;

use App\Models\Caso;
use App\Models\SolicitudCorreccionTarea;
use App\Models\Tarea;
use App\Services\CorreccionTareaService;
use Illuminate\Http\Request;

class CorreccionTareaController extends Controller
{
    public function solicitar(Request $request, Caso $caso, Tarea $tarea, CorreccionTareaService $service)
    {
        $this->verificarTarea($caso, $tarea);
        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->solicitar($tarea, $request->user(), $data['motivo']);

        return back()->with('success', 'La solicitud de corrección fue enviada a Jurídica.');
    }

    public function aprobar(Request $request, SolicitudCorreccionTarea $solicitud, CorreccionTareaService $service)
    {
        $service->aprobar($solicitud, $request->user());

        return back()->with('success', 'La corrección fue autorizada para un solo uso.');
    }

    public function rechazar(Request $request, SolicitudCorreccionTarea $solicitud, CorreccionTareaService $service)
    {
        $data = $request->validate(['motivo_rechazo' => ['required', 'string', 'min:5', 'max:1000']]);
        $service->rechazar($solicitud, $request->user(), $data['motivo_rechazo']);

        return back()->with('success', 'La solicitud fue rechazada.');
    }

    public function corregir(
        Request $request,
        Caso $caso,
        Tarea $tarea,
        SolicitudCorreccionTarea $solicitud,
        CorreccionTareaService $service,
    ) {
        $this->verificarTarea($caso, $tarea);
        $data = $request->validate([
            'observacion' => ['required', 'string', 'min:5', 'max:2000'],
            'fecha_fin' => ['required', 'date'],
        ]);
        $service->corregir($solicitud, $tarea, $request->user(), $data);

        return back()->with('success', 'La tarea fue corregida y la autorización quedó consumida.');
    }

    private function verificarTarea(Caso $caso, Tarea $tarea): void
    {
        abort_unless($tarea->caso_id === $caso->id, 404);
    }
}
