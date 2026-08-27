<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreConfiguracionRespaldoRequest;
use App\Models\BackupHistory;
use App\Models\ConfiguracionRespaldo;
use App\Services\BackupMailService;
use App\Services\BackupScheduleService;
use App\Services\R2BackupStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command;

class ConfiguracionRespaldoController extends Controller
{
    public function __construct(protected BackupScheduleService $backupScheduleService) {}

    public function index(Request $request)
    {
        $config = ConfiguracionRespaldo::first() ?? new ConfiguracionRespaldo;

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

    public function storeOrUpdate(StoreConfiguracionRespaldoRequest $request)
    {
        $validated = $request->validated();

        $validated['recipient_emails'] = filled($validated['recipient_emails'] ?? null)
            ? array_values(array_filter(array_map('trim', explode(',', $validated['recipient_emails']))))
            : null;
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
            if (! $config) {
                return response()->json(['success' => false, 'message' => 'No hay configuración guardada para probar.']);
            }

            // Test sending a simple mail using the saved config
            $mailService = app(BackupMailService::class);
            $mailService->testConnection($config);

            return response()->json(['success' => true, 'message' => 'Conexión SMTP exitosa. Se ha enviado un correo de prueba.']);
        } catch (\Exception $e) {
            Log::warning('La prueba SMTP de respaldos falló.', [
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible completar la prueba SMTP. Revisa la configuración y los logs.',
            ]);
        }
    }

    public function respaldoManual()
    {
        try {
            $exitCode = Artisan::call('backup:run', ['--manual' => true]);

            if ($exitCode !== Command::SUCCESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'El respaldo no se completó. Revisa el historial y los logs del módulo.',
                ], 500);
            }

            return response()->json(['success' => true, 'message' => 'Respaldo generado correctamente.']);
        } catch (\Exception $e) {
            Log::error('Falló la solicitud de respaldo manual.', [
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'El respaldo no se completó. Revisa el historial y los logs del módulo.',
            ], 500);
        }
    }

    public function probarR2(Request $request)
    {
        try {
            $config = ConfiguracionRespaldo::first();
            if (! $config || ! $config->r2_enabled) {
                return response()->json(['success' => false, 'message' => 'R2 no está configurado o habilitado.']);
            }

            $r2Service = app(R2BackupStorageService::class);
            $testKey = 'healthchecks/test_'.Str::uuid().'.txt';
            File::ensureDirectoryExists(storage_path('app'), 0755, true);
            $testPath = tempnam(storage_path('app'), 'r2_healthcheck_');
            if ($testPath === false) {
                throw new \RuntimeException('No se pudo preparar la prueba local.');
            }

            try {
                File::put($testPath, 'R2 healthcheck '.Str::uuid());

                if (! $r2Service->upload($testPath, $testKey) || ! $r2Service->exists($testKey)) {
                    throw new \RuntimeException('La carga de prueba no pudo verificarse.');
                }

                if (! $r2Service->delete($testKey)) {
                    throw new \RuntimeException('La limpieza remota no pudo verificarse.');
                }
            } finally {
                // La clave es única de este healthcheck. Se intenta limpiar siempre,
                // incluso cuando upload() devolvió false por verificación ambigua.
                if (! $r2Service->delete($testKey)) {
                    Log::warning('No se pudo confirmar la limpieza final del healthcheck R2.', [
                        'key_hash' => hash('sha256', $testKey),
                    ]);
                }

                File::delete($testPath);
            }

            return response()->json(['success' => true, 'message' => 'Conexión a Cloudflare R2 exitosa.']);
        } catch (\Exception $e) {
            Log::warning('La prueba de Cloudflare R2 falló.', [
                'exception' => $e::class,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible validar Cloudflare R2. Revisa credenciales, bucket y conectividad.',
            ]);
        }
    }

    public function download(BackupHistory $history)
    {
        if ($history->file_path && File::exists($history->file_path)) {
            return response()->download($history->file_path);
        }

        if ($history->storage_path) {
            $r2Service = app(R2BackupStorageService::class);
            $stream = $r2Service->readStream($history->storage_path);

            if (! is_resource($stream)) {
                return back()->with('error', 'El archivo de respaldo no se encuentra en Cloudflare R2.');
            }

            return response()->streamDownload(function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            }, $history->file_name, ['Content-Type' => 'application/zip']);
        }

        return back()->with('error', 'El archivo de respaldo no está disponible localmente ni en R2.');
    }

    public function destroy(BackupHistory $history)
    {
        $errors = [];
        $remoteDeleted = false;
        $localDeleted = false;

        if ($history->storage_path) {
            $r2Service = app(R2BackupStorageService::class);
            $remoteDeleted = $r2Service->delete($history->storage_path);

            if (! $remoteDeleted) {
                $errors[] = 'No fue posible eliminar la copia de Cloudflare R2.';
            }
        }

        if ($history->file_path && File::exists($history->file_path)) {
            $localDeleted = File::delete($history->file_path) && ! File::exists($history->file_path);

            if (! $localDeleted) {
                $errors[] = 'No fue posible eliminar la copia local.';
            }
        } else {
            $localDeleted = true;
        }

        if ($errors !== []) {
            if ($remoteDeleted) {
                $history->update([
                    'storage_provider' => 'local',
                    'storage_path' => null,
                    'r2_uploaded_at' => null,
                ]);
            }

            if ($localDeleted) {
                $history->update(['file_path' => null]);
            }

            Log::warning('Un respaldo no pudo eliminarse completamente.', [
                'backup_history_id' => $history->id,
                'failed_operations' => count($errors),
            ]);

            return back()->with('error', implode(' ', $errors).' El historial se conservó para reintentar.');
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
        $latestWarning = BackupHistory::where('status', 'exitoso')
            ->whereNotNull('error_message')
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
        } elseif ($latest?->status === 'exitoso' && filled($latest->error_message)) {
            $status = 'advertencia';
        } elseif ($config->exists && $config->is_active && $latest?->status === 'exitoso') {
            $status = 'operativo';
        }

        return [
            'status' => $status,
            'latest' => $latest,
            'latest_error' => $latestError,
            'latest_warning' => $latestWarning,
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

        return $this->backupScheduleService->nextRun(
            $config->backup_frequency,
            $config->backup_time,
        );
    }
}
