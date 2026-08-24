<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\Caso;
use App\Models\User;
use App\Models\TipoProceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class HistorialController extends Controller
{
    /**
     * Muestra la lista de casos finalizados con filtros.
     */
    public function index(Request $request)
    {
        $query = Caso::where('estado', 'Finalizado')
            ->with(['tipo', 'subtipo', 'solicitante', 'usuarios', 'bitacoras'])
            ->latest('updated_at');

        // Filtro por búsqueda (radicado, solicitante)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('radicado', 'like', '%' . $search . '%')
                  ->orWhere('descripcion', 'like', '%' . $search . '%')
                  ->orWhereHas('solicitante', function($sq) use ($search) {
                      $sq->where('nombre', 'like', '%' . $search . '%')
                         ->orWhere('documento', 'like', '%' . $search . '%');
                  });
            });
        }

        // Filtro por tipo de proceso
        if ($request->filled('tipo_id')) {
            $query->where('tipo_id', $request->tipo_id);
        }

        $casos = $query->paginate(15)->withQueryString();
        $tipos = TipoProceso::all();

        return view('historial.index', compact('casos', 'tipos'));
    }

    /**
     * Muestra la bitácora completa de un caso específico.
     */
    public function show(Request $request, Caso $caso)
    {
        // Solo permitir ver bitácora de casos finalizados
        if ($caso->estado !== 'Finalizado') {
            return redirect()->route('historial.index')
                ->with('error', 'Solo se puede ver la bitácora de casos finalizados.');
        }

        $caso->load(['tipo', 'subtipo', 'solicitante', 'usuarios.role']);

        $query = Bitacora::with(['usuario.role'])
            ->where('caso_id', $caso->id)
            ->latest('created_at');

        // Filtro por evento
        if ($request->filled('evento')) {
            $query->where('accion', $request->evento);
        }

        // Filtro por usuario
        if ($request->filled('usuario_id')) {
            $query->where('user_id', $request->usuario_id);
        }

        $eventos = $query->paginate(25)->withQueryString();

        $acciones = Bitacora::where('caso_id', $caso->id)
            ->select('accion')->distinct()->pluck('accion');

        $usuarios = User::whereIn('id',
            Bitacora::where('caso_id', $caso->id)->select('user_id')->distinct()
        )->get();

        $totalEventos = Bitacora::where('caso_id', $caso->id)->count();

        return view('historial.show', compact('caso', 'eventos', 'acciones', 'usuarios', 'totalEventos'));
    }

    /**
     * Exportar a Excel (CSV nativo compatible con Excel)
     */
    public function exportarExcel(Request $request)
    {
        $query = Bitacora::with(['caso.tipo', 'usuario.role'])->latest();

        if ($request->filled('caso_id')) {
            $query->where('caso_id', $request->caso_id);
        }

        if ($request->filled('radicado')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('radicado', 'like', '%' . $request->radicado . '%')
                  ->orWhereHas('solicitante', function($sq) use ($request) {
                      $sq->where('nombre', 'like', '%' . $request->radicado . '%')
                         ->orWhere('documento', 'like', '%' . $request->radicado . '%');
                  });
            });
        }
        if ($request->filled('evento')) {
            $query->where('accion', $request->evento);
        }
        if ($request->filled('usuario_id')) {
            $query->where('user_id', $request->usuario_id);
        }
        if ($request->filled('tipo_id')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('tipo_id', $request->tipo_id);
            });
        }
        
        if (!$request->filled('caso_id')) {
            $query->whereHas('caso', function($q) {
                $q->where('estado', 'Finalizado');
            });
        }

        $eventos = $query->get();

        $filename = "historial_" . ($request->caso_id ? "caso_{$request->caso_id}_" : "global_") . date('Ymd_His') . ".csv";

        $headers = [
            "Content-type"        => "text/csv",
            "Content-Disposition" => "attachment; filename=$filename",
            "Pragma"              => "no-cache",
            "Cache-Control"       => "must-revalidate, post-check=0, pre-check=0",
            "Expires"             => "0"
        ];

        $callback = function() use($eventos) {
            $file = fopen('php://output', 'w');
            // BOM for UTF-8 compatibility in Excel
            fputs($file, "\xEF\xBB\xBF");
            
            fputcsv($file, ['Fecha', 'Radicado', 'Evento', 'Descripción', 'Usuario', 'Rol', 'Módulo']);

            foreach ($eventos as $evento) {
                fputcsv($file, [
                    $evento->created_at->format('d/m/Y - H:i'),
                    $evento->caso ? $evento->caso->radicado : 'N/A',
                    $evento->accion,
                    $evento->descripcion,
                    $evento->usuario ? $evento->usuario->name : 'Sistema',
                    $evento->usuario && $evento->usuario->role ? $evento->usuario->role->nombre : 'N/A',
                    $evento->modulo
                ]);
            }
            fclose($file);
        };

        return Response::stream($callback, 200, $headers);
    }

    /**
     * Exportar a PDF (Vista de impresión)
     */
    public function exportarPdf(Request $request)
    {
        $query = Bitacora::with(['caso.tipo', 'usuario.role'])->latest();

        if ($request->filled('caso_id')) {
            $query->where('caso_id', $request->caso_id);
        }

        if ($request->filled('radicado')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('radicado', 'like', '%' . $request->radicado . '%')
                  ->orWhereHas('solicitante', function($sq) use ($request) {
                      $sq->where('nombre', 'like', '%' . $request->radicado . '%')
                         ->orWhere('documento', 'like', '%' . $request->radicado . '%');
                  });
            });
        }
        if ($request->filled('evento')) {
            $query->where('accion', $request->evento);
        }
        if ($request->filled('usuario_id')) {
            $query->where('user_id', $request->usuario_id);
        }
        if ($request->filled('tipo_id')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('tipo_id', $request->tipo_id);
            });
        }
        
        if (!$request->filled('caso_id')) {
            $query->whereHas('caso', function($q) {
                $q->where('estado', 'Finalizado');
            });
        }

        $eventos = $query->get();

        return view('historial.pdf', compact('eventos'));
    }
}
