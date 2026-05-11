<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfiguracionRespaldoRequest;
use App\Http\Requests\UpdateConfiguracionRespaldoRequest;
use App\Models\ConfiguracionRespaldo;
use Illuminate\Http\Request;

class ConfiguracionRespaldoController extends Controller
{
    public function index()
    {
        $config = ConfiguracionRespaldo::first() ?? new ConfiguracionRespaldo();
        return view('respaldos.index', compact('config'));
    }

    public function storeOrUpdate(\App\Http\Requests\StoreConfiguracionRespaldoRequest $request)
    {
        $validated = $request->validated();

        // Convert recipient_emails from string (comma separated) to array
        $emails = array_map('trim', explode(',', $validated['recipient_emails']));
        $validated['recipient_emails'] = $emails;
        $validated['is_active'] = $request->has('is_active');

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
            \Illuminate\Support\Facades\Artisan::call('backup:database');
            return response()->json(['success' => true, 'message' => 'Respaldo generado y enviado correctamente.']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Error al generar respaldo: ' . $e->getMessage()]);
        }
    }
}
