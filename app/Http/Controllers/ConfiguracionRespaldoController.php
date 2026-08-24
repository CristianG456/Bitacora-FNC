<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfiguracionRespaldoRequest;
use App\Http\Requests\UpdateConfiguracionRespaldoRequest;
use App\Models\BackupHistory;
use App\Models\ConfiguracionRespaldo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class ConfiguracionRespaldoController extends Controller
{
    public function index(Request $request)
    {
        $config = ConfiguracionRespaldo::first() ?? new ConfiguracionRespaldo();
        
        $query = BackupHistory::query();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date')) {
            $query->whereDate('created_at', $request->date);
        }

        $historial = $query->orderBy('created_at', 'desc')->paginate(10)->withQueryString();

        $resumenRespaldos = $this->buildBackupSummary($config);

        return view('respaldos.index', compact('config', 'historial', 'resumenRespaldos'));
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

        if (empty($validated['backup_password']) && $config) {
            unset($validated['backup_password']);
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

    private function buildBackupSummary(ConfiguracionRespaldo $config): array
    {
        $latest = BackupHistory::latest()->first();
        $latestError = BackupHistory::where('status', 'fallido')
            ->where('created_at', '>=', now()->subDays(30))
            ->latest()
            ->first();
        $recent = BackupHistory::latest()->limit(5)->get();

        $localCopies = null;
        try {
            $directory = storage_path('app/backups');
            $localCopies = File::isDirectory($directory)
                ? collect(File::files($directory))->filter(
                    fn ($file) => strtolower($file->getExtension()) === 'zip'
                )->count()
                : 0;
        } catch (\Throwable) {
            // La vista mostrara "No disponible" si no puede consultar el almacenamiento local.
        }

        $status = 'advertencia';
        if ($latest?->status === 'fallido') {
            $status = 'error';
        } elseif ($config->exists && $config->is_active && $latest?->status === 'exitoso') {
            $status = 'operativo';
        }

        return [
            'status' => $status,
            'latest' => $latest,
            'latest_error' => $latestError,
            'recent' => $recent,
            'next_run' => $this->nextScheduledRun($config),
            'local_copies' => $localCopies,
            // R2 no ofrece actualmente una operacion segura de listado; no se inventa disponibilidad.
            'r2_copies' => null,
            'r2_records' => BackupHistory::where('status', 'exitoso')
                ->where('storage_provider', 'r2')
                ->count(),
        ];
    }

    private function nextScheduledRun(ConfiguracionRespaldo $config): ?Carbon
    {
        if (! $config->exists || ! $config->is_active || ! $config->backup_time) {
            return null;
        }

        $time = Carbon::parse($config->backup_time);
        $next = now()->setTime($time->hour, $time->minute, 0);

        if ($config->backup_frequency === 'diario') {
            return $next->isFuture() ? $next : $next->addDay();
        }

        if ($config->backup_frequency === 'semanal') {
            $next = $next->nextOrSame(Carbon::SUNDAY);
            return $next->isFuture() ? $next : $next->addWeek();
        }

        if ($config->backup_frequency === 'mensual') {
            $next = $next->startOfMonth();
            return $next->isFuture() ? $next : $next->addMonthNoOverflow();
        }

        return null;
    }
}
