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
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2">
                    <i data-lucide="server" class="text-gray-500"></i> Servidor SMTP
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
            </div>

            <!-- Configuración de Envío y Respaldo -->
            <div>
                <h2 class="text-lg font-bold text-gray-800 mb-4 border-b pb-2 flex items-center gap-2">
                    <i data-lucide="settings" class="text-gray-500"></i> Opciones de Respaldo
                </h2>
                
                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
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
                        <p class="text-xs text-gray-500 mt-1">Separados por comas.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
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
                        <input type="text" name="backup_password" value="{{ old('backup_password', $config->backup_password) }}" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:border-blue-500 outline-none">
                        <p class="text-xs text-gray-500 mt-1">Si se especifica, el archivo ZIP generado estará protegido.</p>
                    </div>

                    <div class="flex items-center pt-2">
                        <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $config->is_active) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="is_active" class="ml-2 block text-sm text-gray-900 font-medium">
                            Activar respaldos automáticos
                        </label>
                    </div>
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
                        Swal.fire('¡Éxito!', data.message, 'success');
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
