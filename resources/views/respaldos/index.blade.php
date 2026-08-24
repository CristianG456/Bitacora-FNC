@extends('layouts.app')

@section('title', 'Configuración de Respaldos - Sistema Jurídico')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between">
        <div class="mb-3 sm:mb-0">
            <h1 class="text-2xl font-bold text-gray-900">Configuración de Respaldos</h1>
            <p class="text-gray-500 text-sm mt-1">Configura las copias de seguridad automáticas de la base de datos</p>
        </div>
        <div class="flex flex-col sm:flex-row gap-2">
            <button type="button" onclick="probarSmtp()" class="btn-secondary w-full sm:w-auto justify-center bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-2 px-4 rounded-md flex items-center gap-2">
                <i data-lucide="mail" style="width:16px;height:16px;"></i>
                Probar SMTP
            </button>
            <button type="button" onclick="respaldoManual()" class="btn-primary w-full sm:w-auto justify-center bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md flex items-center gap-2">
                <i data-lucide="download" style="width:16px;height:16px;"></i>
                Generar Respaldo Manual
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <form action="{{ route('respaldos.store') }}" method="POST">
        @csrf
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <!-- Configuración SMTP -->
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2"><i data-lucide="server" class="text-gray-500"></i> Servidor SMTP</div>
                    <button type="button" onclick="probarSmtp()" class="text-sm bg-gray-100 hover:bg-gray-200 text-gray-800 font-medium py-1 px-3 rounded flex items-center gap-1 transition">
                        <i data-lucide="mail" style="width:14px;height:14px;"></i> Probar SMTP
                    </button>
                </h2>
                
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Host SMTP</label>
                        <input type="text" name="smtp_host" value="{{ old('smtp_host', $config->smtp_host) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                    </div>
                    
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Puerto</label>
                            <input type="number" name="smtp_port" value="{{ old('smtp_port', $config->smtp_port) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Encriptación</label>
                            <input type="text" name="smtp_encryption" value="{{ old('smtp_encryption', $config->smtp_encryption) }}" placeholder="tls, ssl" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Usuario SMTP</label>
                        <input type="text" name="smtp_username" value="{{ old('smtp_username', $config->smtp_username) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña SMTP</label>
                        <input type="password" name="smtp_password" placeholder="{{ $config->smtp_password ? '••••••••' : 'Contraseña' }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                        <p class="text-xs text-gray-500 mt-1">Déjalo en blanco para mantener la contraseña actual.</p>
                    </div>
                </div>

                <h2 class="text-lg font-bold text-gray-800 mb-4 mt-8 border-b pb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2"><i data-lucide="cloud" class="text-blue-500"></i> Cloudflare R2 (S3)</div>
                    <button type="button" onclick="probarR2()" class="text-sm bg-blue-50 hover:bg-blue-100 text-blue-700 font-medium py-1 px-3 rounded flex items-center gap-1 transition">
                        <i data-lucide="check-circle" style="width:14px;height:14px;"></i> Probar R2
                    </button>
                </h2>
                
                <div class="space-y-4">
                    <div class="flex items-center pb-2">
                        <input type="checkbox" id="r2_enabled" name="r2_enabled" value="1" {{ old('r2_enabled', $config->r2_enabled) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="r2_enabled" class="ml-2 block text-sm text-gray-900 font-medium">
                            Habilitar subida a Cloudflare R2
                        </label>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Bucket Name</label>
                        <input type="text" name="r2_bucket" value="{{ old('r2_bucket', $config->r2_bucket) }}" placeholder="mi-bucket-backups" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ruta Destino (Path)</label>
                        <input type="text" name="r2_path" value="{{ old('r2_path', $config->r2_path) }}" placeholder="respaldos/produccion" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                        <p class="text-xs text-gray-500 mt-1">Carpeta dentro del bucket (opcional).</p>
                    </div>

                    <div class="bg-blue-50 text-blue-800 text-xs p-3 rounded-md border border-blue-100">
                        <p><strong>Nota:</strong> Las credenciales (Access Key, Secret Key y Endpoint) deben configurarse en el archivo <code>.env</code> de Docker para mayor seguridad.</p>
                    </div>
                </div>
            </div>

            <!-- Panel informativo de respaldos -->
            <div class="min-w-0">
                @php
                    $estadoClases = [
                        'operativo' => 'bg-green-100 text-green-800 border-green-200',
                        'advertencia' => 'bg-amber-100 text-amber-800 border-amber-200',
                        'error' => 'bg-red-100 text-red-800 border-red-200',
                    ];
                    $estadoEtiquetas = [
                        'operativo' => 'OPERATIVO',
                        'advertencia' => 'ADVERTENCIA',
                        'error' => 'ERROR',
                    ];
                    $estadoActual = $resumenRespaldos['status'];
                    $ultimoRespaldo = $resumenRespaldos['latest'];
                @endphp

                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 flex items-center justify-between gap-3">
                    <span class="flex items-center gap-2">
                        <i data-lucide="activity" class="text-gray-500"></i> Estado de Respaldos
                    </span>
                    <span class="px-3 py-1 rounded-full border text-xs font-bold {{ $estadoClases[$estadoActual] }}">
                        {{ $estadoEtiquetas[$estadoActual] }}
                    </span>
                </h2>

                <div class="space-y-4">
                    @if(!$config->exists)
                        <div class="p-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm flex gap-2">
                            <i data-lucide="triangle-alert" class="shrink-0 mt-0.5" style="width:16px;height:16px;"></i>
                            <span>No existe una configuración de respaldos guardada.</span>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-gray-800 mb-3">
                                <i data-lucide="history" class="text-blue-600" style="width:16px;height:16px;"></i>
                                Último respaldo
                            </div>
                            @if($ultimoRespaldo)
                                <p class="text-sm font-semibold text-gray-900">{{ $ultimoRespaldo->created_at->format('d/m/Y - h:i a') }}</p>
                                <dl class="mt-3 space-y-2 text-xs">
                                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Estado</dt><dd class="font-semibold {{ $ultimoRespaldo->status === 'exitoso' ? 'text-green-700' : 'text-red-700' }}">{{ $ultimoRespaldo->status === 'exitoso' ? 'Completado' : 'Fallido' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Destino</dt><dd class="font-semibold text-gray-800 text-right">{{ $ultimoRespaldo->storage_provider === 'r2' ? ($ultimoRespaldo->file_path && file_exists($ultimoRespaldo->file_path) ? 'Local + Cloudflare R2' : 'Cloudflare R2') : 'Local' }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-gray-500">Tamaño</dt><dd class="font-semibold text-gray-800">{{ $ultimoRespaldo->file_size > 0 ? number_format($ultimoRespaldo->file_size / 1048576, 2) . ' MB' : 'No disponible' }}</dd></div>
                                </dl>
                            @else
                                <p class="text-sm text-gray-500">No disponible</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                            <div class="flex items-center gap-2 text-sm font-bold text-gray-800 mb-3">
                                <i data-lucide="calendar-clock" class="text-blue-600" style="width:16px;height:16px;"></i>
                                Próximo respaldo
                            </div>
                            @if($resumenRespaldos['next_run'])
                                <p class="text-sm font-semibold text-gray-900">{{ $resumenRespaldos['next_run']->format('d/m/Y - h:i a') }}</p>
                                <div class="flex justify-between gap-3 mt-3 text-xs">
                                    <span class="text-gray-500">Frecuencia</span>
                                    <span class="font-semibold text-gray-800">{{ ucfirst($config->backup_frequency) }}</span>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No hay respaldo automático programado</p>
                            @endif
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-lg border border-gray-200 p-4">
                            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2"><i data-lucide="database" style="width:16px;height:16px;"></i> Copias disponibles</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Respaldos locales</dt><dd class="font-bold text-gray-800">{{ $resumenRespaldos['local_copies'] ?? 'No disponible' }}</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Cloudflare R2</dt><dd class="font-bold text-gray-800">{{ $resumenRespaldos['r2_copies'] ?? 'No disponible' }}</dd></div>
                            </dl>
                            @if($resumenRespaldos['r2_copies'] === null && $resumenRespaldos['r2_records'] > 0)
                                <p class="mt-2 text-[11px] text-gray-500">{{ $resumenRespaldos['r2_records'] }} registro(s) R2 en historial, sin verificación remota.</p>
                            @endif
                        </div>

                        <div class="rounded-lg border border-gray-200 p-4">
                            <h3 class="text-sm font-bold text-gray-800 mb-3 flex items-center gap-2"><i data-lucide="shield-check" style="width:16px;height:16px;"></i> Retención</h3>
                            <dl class="space-y-2 text-sm">
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Local</dt><dd class="font-bold text-gray-800 text-right">@if($config->exists){{ ($config->max_backups ?? 0) > 0 ? $config->max_backups . ' copias' : 'Sin límite de copias' }}{{ ($config->retention_days ?? 0) > 0 ? ' / ' . $config->retention_days . ' días' : '' }}@else No disponible @endif</dd></div>
                                <div class="flex justify-between gap-3"><dt class="text-gray-500">Cloudflare R2</dt><dd class="font-bold text-gray-800">@if(!$config->exists) No disponible @elseif(($config->r2_retention_days ?? 0) > 0) {{ $config->r2_retention_days }} días @else Sin límite configurado @endif</dd></div>
                            </dl>
                        </div>
                    </div>

                    <div class="rounded-lg border {{ $resumenRespaldos['latest_error'] ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50' }} p-4">
                        <h3 class="text-sm font-bold {{ $resumenRespaldos['latest_error'] ? 'text-red-800' : 'text-green-800' }} flex items-center gap-2">
                            <i data-lucide="{{ $resumenRespaldos['latest_error'] ? 'circle-alert' : 'circle-check' }}" style="width:16px;height:16px;"></i>
                            Último error
                        </h3>
                        @if($resumenRespaldos['latest_error'])
                            <p class="mt-2 text-xs font-semibold text-red-800">{{ $resumenRespaldos['latest_error']->created_at->format('d/m/Y - h:i a') }}</p>
                            <p class="mt-1 text-sm text-red-700">El último intento de respaldo no se completó correctamente.</p>
                        @else
                            <p class="mt-2 text-sm text-green-700">Sin errores recientes</p>
                        @endif
                    </div>

                    <div class="rounded-lg border border-gray-200 overflow-hidden">
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex items-center gap-2">
                            <i data-lucide="list" class="text-gray-500" style="width:16px;height:16px;"></i>
                            <h3 class="text-sm font-bold text-gray-800">Historial reciente</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($resumenRespaldos['recent'] as $registro)
                                <div class="p-3 grid grid-cols-1 sm:grid-cols-[1.2fr_1fr_auto_auto] gap-2 sm:gap-3 sm:items-center text-xs">
                                    <div><span class="text-gray-500 sm:hidden">Fecha: </span><span class="font-semibold text-gray-800">{{ $registro->created_at->format('d/m/Y - H:i') }}</span></div>
                                    <div><span class="text-gray-500 sm:hidden">Destino: </span><span class="text-gray-700">{{ $registro->storage_provider === 'r2' ? 'Cloudflare R2' : 'Local' }}</span></div>
                                    <div class="text-gray-700"><span class="text-gray-500 sm:hidden">Tamaño: </span>{{ $registro->file_size > 0 ? number_format($registro->file_size / 1048576, 2) . ' MB' : 'No disponible' }}</div>
                                    <div><span class="inline-flex px-2 py-1 rounded-full font-bold {{ $registro->status === 'exitoso' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $registro->status === 'exitoso' ? 'Completado' : 'Fallido' }}</span></div>
                                </div>
                            @empty
                                <p class="p-4 text-sm text-gray-500 text-center">No existe historial estructurado disponible.</p>
                            @endforelse
                        </div>
                    </div>

                    <details class="rounded-lg border border-gray-200 bg-white">
                        <summary class="cursor-pointer select-none px-4 py-3 text-sm font-semibold text-gray-700 hover:bg-gray-50 rounded-lg flex items-center gap-2">
                            <i data-lucide="settings-2" style="width:16px;height:16px;"></i>
                            Configuración avanzada
                        </summary>
                        <div class="p-4 border-t border-gray-200 space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Email Remitente</label>
                                    <input type="email" name="sender_email" value="{{ old('sender_email', $config->sender_email) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre Remitente</label>
                                    <input type="text" name="sender_name" value="{{ old('sender_name', $config->sender_name) }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Correos Destino</label>
                                <textarea name="recipient_emails" required rows="2" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none" placeholder="correo1@ejemplo.com, correo2@ejemplo.com">{{ old('recipient_emails', is_array($config->recipient_emails) ? implode(', ', $config->recipient_emails) : $config->recipient_emails) }}</textarea>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Frecuencia</label>
                                    <select name="backup_frequency" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none bg-white">
                                        <option value="diario" {{ old('backup_frequency', $config->backup_frequency) == 'diario' ? 'selected' : '' }}>Diario</option>
                                        <option value="semanal" {{ old('backup_frequency', $config->backup_frequency) == 'semanal' ? 'selected' : '' }}>Semanal</option>
                                        <option value="mensual" {{ old('backup_frequency', $config->backup_frequency) == 'mensual' ? 'selected' : '' }}>Mensual</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Hora de Ejecución</label>
                                    <input type="time" name="backup_time" value="{{ old('backup_time', $config->backup_time ? \Carbon\Carbon::parse($config->backup_time)->format('H:i') : '00:00') }}" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña del ZIP</label>
                                <input type="password" name="backup_password" value="" placeholder="{{ $config->backup_password ? 'Configurada; dejar vacío para conservar' : 'Sin configurar' }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Máximo backups (Local)</label>
                                    <input type="number" name="max_backups" value="{{ old('max_backups', $config->max_backups ?? 10) }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none bg-white">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Días retención (Local)</label>
                                    <input type="number" name="retention_days" value="{{ old('retention_days', $config->retention_days) }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Días retención (Cloudflare R2)</label>
                                <input type="number" name="r2_retention_days" value="{{ old('r2_retention_days', $config->r2_retention_days) }}" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                            </div>
                            <div class="flex items-center pt-2">
                                <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $config->is_active) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-900 font-medium">Activar respaldos automáticos</label>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </div>

        <div class="mt-8 flex justify-end pt-4 border-t border-gray-200">
            <button type="submit" class="bg-red-700 hover:bg-red-800 text-white font-bold py-2 px-6 rounded-md shadow-sm transition flex items-center gap-2">
                <i data-lucide="save" style="width:18px;height:18px;"></i>
                Guardar Configuración
            </button>
        </div>
    </form>
</div>

<!-- HISTORIAL DE RESPALDOS -->
<div class="mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-6 border-b pb-4">
        <h2 class="text-xl font-bold text-gray-800 flex items-center gap-2">
            <i data-lucide="history" class="text-blue-600"></i> Historial de Respaldos
        </h2>
        
        <form action="{{ route('respaldos.index') }}" method="GET" class="flex flex-col sm:flex-row gap-3 mt-4 sm:mt-0 w-full sm:w-auto">
            <input type="date" name="date" value="{{ request('date') }}" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:border-blue-500 outline-none">
            <select name="status" class="px-3 py-2 border border-gray-300 rounded-md text-sm focus:border-blue-500 outline-none bg-white">
                <option value="">Todos los estados</option>
                <option value="exitoso" {{ request('status') === 'exitoso' ? 'selected' : '' }}>Exitoso</option>
                <option value="fallido" {{ request('status') === 'fallido' ? 'selected' : '' }}>Fallido</option>
            </select>
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-md font-medium text-sm transition flex items-center justify-center gap-2">
                <i data-lucide="filter" style="width:14px;height:14px;"></i> Filtrar
            </button>
            @if(request()->has('date') || request()->has('status'))
                <a href="{{ route('respaldos.index') }}" class="bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 px-4 py-2 rounded-md font-medium text-sm transition flex items-center justify-center">
                    Limpiar
                </a>
            @endif
        </form>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="bg-gray-50 text-[13px] font-bold text-gray-700 uppercase border-b border-gray-200">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Archivo</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Tamaño</th>
                    <th class="px-4 py-3">Tiempo (s)</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($historial as $hist)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-4 py-3 font-medium text-gray-900">
                        {{ $hist->created_at->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        <span title="{{ $hist->file_name }}" class="truncate max-w-[200px] inline-block align-bottom">{{ $hist->file_name }}</span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 bg-gray-100 text-gray-700 rounded-md text-xs font-semibold">{{ ucfirst($hist->backup_type) }}</span>
                        @if($hist->storage_provider === 'r2')
                            <span class="ml-1 px-2 py-1 bg-blue-100 text-blue-800 rounded-md text-xs font-semibold" title="Almacenado en Cloudflare R2"><i data-lucide="cloud" class="inline" style="width:10px;height:10px;"></i> R2</span>
                        @else
                            <span class="ml-1 px-2 py-1 bg-gray-200 text-gray-700 rounded-md text-xs font-semibold" title="Almacenado Localmente"><i data-lucide="hard-drive" class="inline" style="width:10px;height:10px;"></i> Local</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ number_format($hist->file_size / 1048576, 2) }} MB
                    </td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ number_format($hist->execution_time, 2) }}s
                    </td>
                    <td class="px-4 py-3">
                        @if($hist->status === 'exitoso')
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-bold flex items-center w-max gap-1">
                                <i data-lucide="check-circle" style="width:12px;height:12px;"></i> Exitoso
                            </span>
                        @else
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-bold flex items-center w-max gap-1 cursor-pointer" title="El respaldo no se completó correctamente">
                                <i data-lucide="x-circle" style="width:12px;height:12px;"></i> Fallido
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            @if($hist->status === 'exitoso' && file_exists($hist->file_path))
                                <a href="{{ route('respaldos.download', $hist->id) }}" class="p-1.5 text-blue-600 hover:bg-blue-50 rounded transition" title="Descargar">
                                    <i data-lucide="download" style="width:16px;height:16px;"></i>
                                </a>
                            @endif
                            <form action="{{ route('respaldos.destroy', $hist->id) }}" method="POST" class="inline-block" onsubmit="confirmarAccion(event, this, '¿Eliminar respaldo?', 'Se eliminará permanentemente el archivo físico y el registro.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-red-600 hover:bg-red-50 rounded transition" title="Eliminar">
                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                        <div class="flex flex-col items-center justify-center gap-2">
                            <i data-lucide="archive" class="text-gray-300" style="width:32px;height:32px;"></i>
                            <p>No se encontraron respaldos registrados.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">
        {{ $historial->links() }}
    </div>
</div>

@endsection

@push('scripts')
<script>
    function probarSmtp() {
        Swal.fire({
            title: 'Probando conexión...',
            text: 'Por favor espera',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("respaldos.probar") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire('¡Éxito!', data.message, 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Ocurrió un error en la solicitud.', 'error');
        });
    }

    function probarR2() {
        Swal.fire({
            title: 'Probando conexión R2...',
            text: 'Verificando buckets y credenciales',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        fetch('{{ route("respaldos.probar_r2") }}', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                Swal.fire('¡Conectado!', data.message, 'success');
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        })
        .catch(error => {
            Swal.fire('Error', 'Ocurrió un error en la solicitud a Cloudflare.', 'error');
        });
    }

    function respaldoManual() {
        Swal.fire({
            title: '¿Generar respaldo ahora?',
            text: "Esto puede tomar unos minutos dependiendo del tamaño de la base de datos.",
            icon: 'info',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Sí, generar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Generando respaldo...',
                    text: 'Por favor espera',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                fetch('{{ route("respaldos.manual") }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('¡Éxito!', data.message, 'success').then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                })
                .catch(error => {
                    Swal.fire('Error', 'Ocurrió un error en la solicitud.', 'error');
                });
            }
        });
    }
</script>
@endpush
