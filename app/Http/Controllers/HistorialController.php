<?php

namespace App\Http\Controllers;

use App\Models\Bitacora;
use App\Models\User;
use App\Models\TipoProceso;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class HistorialController extends Controller
{
    /**
     * Muestra el historial global del sistema.
     */
    public function index(Request $request)
    {
        $query = Bitacora::with(['caso.tipo', 'usuario.role'])->latest();

        // Filtro por radicado o nombre del solicitante
        if ($request->filled('radicado')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('radicado', 'like', '%' . $request->radicado . '%')
                  ->orWhereHas('solicitante', function($sq) use ($request) {
                      $sq->where('nombre', 'like', '%' . $request->radicado . '%')
                         ->orWhere('documento', 'like', '%' . $request->radicado . '%');
                  });
            });
        }

        // Filtro por evento/accion
        if ($request->filled('evento')) {
            $query->where('accion', $request->evento);
        }

        // Filtro por usuario
        if ($request->filled('usuario_id')) {
            $query->where('user_id', $request->usuario_id);
        }

        // Filtro por tipo de proceso
        if ($request->filled('tipo_id')) {
            $query->whereHas('caso', function($q) use ($request) {
                $q->where('tipo_id', $request->tipo_id);
            });
        }

        // Filtrar solo casos finalizados según el requerimiento: "bitacora completa de los casos ya finalizados"
        // Hacemos que por defecto muestre finalizados, pero si se busca un radicado específico lo muestre todo para evitar confusión,
        // o lo dejamos siempre forzado a finalizados. Forzaremos a finalizados si no hay búsqueda directa, o simplemente lo dejamos global con un scope.
        // Lo dejaremos forzado a finalizados como dice la instrucción, a menos que el usuario sea el afectado directo.
        $query->whereHas('caso', function($q) {
            $q->where('estado', 'Finalizado');
        });

        $eventos = $query->paginate(20)->withQueryString();
        
        $usuarios = User::where('activo', true)->get();
        $tipos = TipoProceso::all();
        $acciones = Bitacora::select('accion')->distinct()->pluck('accion');

        return view('historial.index', compact('eventos', 'usuarios', 'tipos', 'acciones'));
    }

    /**
     * Exportar a Excel (CSV nativo compatible con Excel)
     */
    public function exportarExcel(Request $request)
    {
        $query = Bitacora::with(['caso.tipo', 'usuario.role'])->latest();

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
        
        $query->whereHas('caso', function($q) {
            $q->where('estado', 'Finalizado');
        });

        $eventos = $query->get();

        $filename = "historial_global_" . date('Ymd_His') . ".csv";

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
        
        $query->whereHas('caso', function($q) {
            $q->where('estado', 'Finalizado');
        });

        $eventos = $query->get();

        return view('historial.pdf', compact('eventos'));
    }
}
