<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfiguracionRespaldoRequest;
use App\Http\Requests\UpdateConfiguracionRespaldoRequest;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Http\Request;

class ConfiguracionRespaldoController extends Controller
{
    public function index(Request $request)
    {
        $config = ConfiguracionRespaldo::first() ?? new ConfiguracionRespaldo();
        
        $query = \App\Models\BackupHistory::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $historial = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        return view('respaldos.index', compact('config', 'historial'));
    }

    public function storeOrUpdate(\App\Http\Requests\StoreConfiguracionRespaldoRequest $request)
    {
        $validated = $request->validated();

        // Convert recipient_emails from string (comma separated) to array
        $emails = array_map('trim', explode(',', $validated['recipient_emails']));
        $validated['recipient_emails'] = $emails;
        $validated['is_active'] = $request->has('is_active');
        $validated['r2_enabled'] = $request->has('r2_enabled');

        $config = ConfiguracionRespaldo::first();

        // Si no se proporcionó contraseña pero ya existe configuración, mantenemos la anterior
        if (empty($validated['smtp_password']) && $config) {
            unset($validated['smtp_password']);
        }

        if ($config) {
            $config->update($validated);
        } else {
            ConfiguracionRespaldo::create($validated);
        }

        return redirect()->route('respaldos.index')->with('success', 'Configuración guardada correctamente.');
    }

    public function probarSmtp(Request $request)
    {
        try {
            $config = ConfiguracionRespaldo::first();
            if (!$config) {
                return response()->json(['success' => false, 'message' => 'No hay configuración guardada para probar.']);
            }

            // Test sending a simple mail using the saved config
            $mailService = app(\App\Services\BackupMailService::class);
            $mailService->testConnection($config);

            return response()->json(['success' => true, 'message' => 'Conexión SMTP exitosa. Se ha enviado un correo de prueba.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al probar SMTP: ' . $e->getMessage()]);
        }
    }

    public function respaldoManual()
    {
        try {
            // Llama al comando Artisan
            \Illuminate\Support\Facades\Artisan::call('backup:run', ['--manual' => true]);
            return response()->json(['success' => true, 'message' => 'Respaldo generado y enviado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar respaldo: ' . $e->getMessage()]);
        }
    }
    public function probarR2(Request $request)
    {
        try {
            $config = ConfiguracionRespaldo::first();
            if (!$config || !$config->r2_enabled) {
                return response()->json(['success' => false, 'message' => 'R2 no está configurado o habilitado.']);
            }

            $r2Service = app(\App\Services\R2BackupStorageService::class);
            // Write a small test file
            $testContent = 'Conexión exitosa a R2 - ' . now();
            $testPath = storage_path('app/r2_test.txt');
            file_put_contents($testPath, $testContent);
            
            $r2Service->upload($testPath, 'test_connection.txt');
            $r2Service->delete('test_connection.txt');
            unlink($testPath);

            return response()->json(['success' => true, 'message' => 'Conexión a Cloudflare R2 exitosa.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al probar R2: ' . $e->getMessage()]);
        }
    }

    public function download(\App\Models\BackupHistory $history)
    {
        if ($history->storage_provider === 'r2') {
            $r2Service = app(\App\Services\R2BackupStorageService::class);
            if (!$r2Service->exists($history->storage_path)) {
                return back()->with('error', 'El archivo de respaldo no se encuentra en Cloudflare R2.');
            }
            // Retornar archivo desde R2 creando una URL temporal o descargando y sirviendo
            // Como las URLs temporales pueden fallar dependiendo del provider, mejor lo descargamos temporalmente
            $tempPath = storage_path('app/temp_' . $history->file_name);
            if ($r2Service->download($history->storage_path, $tempPath)) {
                return response()->download($tempPath)->deleteFileAfterSend(true);
            }
            return back()->with('error', 'No se pudo descargar el archivo de R2.');
        }

        // Local provider
        if (!file_exists($history->file_path)) {
            return back()->with('error', 'El archivo de respaldo no se encuentra en el servidor local.');
        }

        return response()->download($history->file_path);
    }

    public function destroy(\App\Models\BackupHistory $history)
    {
        if ($history->storage_provider === 'r2') {
            $r2Service = app(\App\Services\R2BackupStorageService::class);
            $r2Service->delete($history->storage_path);
        } else {
            if (file_exists($history->file_path)) {
                unlink($history->file_path);
            }
        }
        
        $history->delete();

        return back()->with('success', 'Respaldo eliminado correctamente.');
    }
}
