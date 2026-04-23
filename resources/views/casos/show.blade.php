@extends('layouts.app')

@section('title', 'Detalles del Caso - ' . $caso->radicado)

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex items-center gap-3 mb-2">
        <a href="{{ route('casos.index') }}" class="text-gray-400 hover:text-gray-700 transition">
            <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
        </a>
        <h1 class="text-2xl font-bold text-gray-900">{{ $caso->radicado }}</h1>
        @php
            $badgeClass = match($caso->estado) {
                'En proceso'  => 'bg-blue-100 text-blue-700',
                'Completado'  => 'bg-green-100 text-green-700',
                'Finalizado'  => 'bg-red-100 text-red-700',
                default       => 'bg-gray-100 text-gray-700',
            };
        @endphp
        <span class="px-2.5 py-1 rounded-md text-xs font-bold tracking-wide {{ $badgeClass }}">
            {{ $caso->estado }}
        </span>
    </div>
    <div class="ml-8 text-sm text-gray-500">
        {{ $caso->tipo?->nombre }} • {{ $caso->subtipo?->nombre }}
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- COLUMNA IZQUIERDA -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Información General -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 mb-4">Información General</h2>
            
            <div class="mb-4">
                <span class="block text-xs font-semibold text-gray-500 mb-1">Descripción</span>
                <p class="text-sm text-gray-800 leading-relaxed">{{ $caso->descripcion }}</p>
            </div>
            
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Solicitante</span>
                    <p class="text-sm text-gray-800">{{ $caso->solicitante?->nombre }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Documento</span>
                    <p class="text-sm text-gray-800">{{ $caso->solicitante?->documento }}</p>
                </div>
            </div>
            
            <div class="flex items-center gap-4 text-sm mt-6 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2 text-gray-500">
                    <i data-lucide="calendar" style="width:16px;height:16px;"></i>
                    {{ $caso->created_at->translatedFormat('d de F de Y') }}
                </div>
                @if($caso->link_drive)
                <a href="{{ $caso->link_drive }}" target="_blank" class="flex items-center gap-1 text-red-600 hover:text-red-800 font-medium transition">
                    Ver documento <i data-lucide="external-link" style="width:14px;height:14px;"></i>
                </a>
                @endif
            </div>
        </div>

        <!-- Usuarios Asignados -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Usuarios Asignados</h2>
                    @php
                        $totalTareas = $caso->tareas->count();
                        $tareasCompletadas = $caso->tareas->where('estado', 'Completada')->count();
                        $progreso = $totalTareas > 0 ? round(($tareasCompletadas / $totalTareas) * 100) : 0;
                    @endphp
                    <p class="text-xs text-gray-500 mt-1">Progreso: {{ $progreso }}% ({{ $tareasCompletadas }}/{{ $totalTareas }} completados)</p>
                </div>
                @if($esAdmin)
                <button type="button" class="btn-secondary text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300">
                    <i data-lucide="user-plus" style="width:14px;height:14px;"></i> Agregar Usuario
                </button>
                @endif
            </div>

            <div class="bg-blue-50 border border-blue-100 text-blue-700 p-3 rounded-md text-sm flex items-start gap-2 mb-4">
                <i data-lucide="info" style="width:18px;height:18px;flex-shrink:0;margin-top:2px;"></i>
                <p>Puedes modificar los usuarios asignados en cualquier momento. El sistema se ajusta automáticamente sin afectar el proceso.</p>
            </div>

            <div class="space-y-3">
                @forelse($caso->usuarios as $user)
                <div class="border border-gray-200 rounded-lg p-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500">
                            <i data-lucide="user" style="width:20px;height:20px;"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">{{ $user->name }}</h3>
                            @php
                                $tareasUsuario = $caso->tareas->where('user_id', $user->id);
                                $completadasUsu = $tareasUsuario->where('estado', 'Completada')->count();
                            @endphp
                            <p class="text-xs text-gray-500">
                                Asignado: {{ \Carbon\Carbon::parse($user->pivot->fecha_asignacion)->format('d de M') }} • {{ $completadasUsu }}/{{ $tareasUsuario->count() }} tareas
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3">
                        @php
                            $estadoUsu = $user->pivot->estado;
                            $euClass = match($estadoUsu) {
                                'En proceso' => 'bg-blue-600 text-white',
                                'Completado' => 'bg-green-600 text-white',
                                default      => 'bg-gray-500 text-white',
                            };
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide {{ $euClass }}">
                            {{ $estadoUsu }}
                        </span>
                        @if($esAdmin)
                        <button type="button" class="text-gray-400 hover:text-gray-600 p-1">
                            <i data-lucide="refresh-cw" style="width:16px;height:16px;"></i>
                        </button>
                        <button type="button" class="text-red-400 hover:text-red-600 p-1 bg-red-50 rounded">
                            <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                        </button>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4">No hay usuarios asignados a este caso.</p>
                @endforelse
            </div>
        </div>

        <!-- Observaciones / Tareas (simplificado) -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <h2 class="text-base font-bold text-gray-900 mb-4">Observaciones</h2>
            
            <div class="space-y-4">
                @php
                    // Recopilar todas las observaciones de las tareas
                    $todasObservaciones = collect();
                    foreach($caso->tareas as $tarea) {
                        foreach($tarea->observaciones as $obs) {
                            $todasObservaciones->push($obs);
                        }
                    }
                    $todasObservaciones = $todasObservaciones->sortByDesc('created_at');
                @endphp

                @forelse($todasObservaciones as $obs)
                <div class="border-b border-gray-100 pb-4 last:border-0 last:pb-0">
                    <div class="flex items-center justify-between mb-1">
                        <span class="text-xs font-bold text-gray-900">{{ $obs->autor->name }}</span>
                        <span class="text-[11px] text-gray-500">{{ $obs->created_at->format('d M, H:i \h') }}</span>
                    </div>
                    <p class="text-sm text-gray-600">{{ $obs->contenido }}</p>
                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4">No hay observaciones registradas.</p>
                @endforelse
            </div>
        </div>

    </div>

    <!-- COLUMNA DERECHA (TABS BITÁCORA / MENSAJES) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col" style="height: calc(100vh - 140px); position: sticky; top: 80px;">
            
            <!-- TABS -->
            <div class="flex border-b border-gray-200 bg-gray-50 p-2 gap-1">
                <button class="flex-1 py-2 text-sm font-bold text-gray-900 bg-white shadow-sm rounded-md flex items-center justify-center gap-2">
                    <i data-lucide="file-text" style="width:16px;height:16px;"></i> Bitácora
                </button>
                <button class="flex-1 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center justify-center gap-2 transition">
                    <i data-lucide="message-square" style="width:16px;height:16px;"></i> Mensajes
                </button>
            </div>

            <!-- CONTENIDO BITÁCORA -->
            <div class="flex-1 overflow-y-auto p-4 space-y-6 relative">
                <div class="absolute left-8 top-0 bottom-0 w-px bg-gray-200"></div>

                @forelse($caso->bitacoras as $bitacora)
                <div class="relative flex items-start gap-4 z-10 cursor-pointer group" onclick="mostrarDetalleEvento('{{ $bitacora->usuario?->name ?? 'Sistema' }}', '{{ $bitacora->accion }}', '{{ $bitacora->created_at->translatedFormat('d de F de Y \a \l\a\s H:i \h') }}', '{{ addslashes($bitacora->descripcion) }}')">
                    
                    @php
                        $iconData = match(strtolower($bitacora->accion)) {
                            'crear' => ['icon' => 'file', 'color' => 'bg-gray-100 text-gray-500'],
                            'asignacion', 'asignar' => ['icon' => 'user-plus', 'color' => 'bg-blue-100 text-blue-500'],
                            'observación', 'observacion' => ['icon' => 'message-circle', 'color' => 'bg-purple-100 text-purple-500'],
                            'actualizar', 'cambio de estado' => ['icon' => 'refresh-cw', 'color' => 'bg-orange-100 text-orange-500'],
                            default => ['icon' => 'activity', 'color' => 'bg-gray-100 text-gray-500'],
                        };
                    @endphp

                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border-2 border-white {{ $iconData['color'] }} group-hover:scale-110 transition-transform">
                        <i data-lucide="{{ $iconData['icon'] }}" style="width:14px;height:14px;"></i>
                    </div>

                    <div class="flex-1 pt-1 bg-white group-hover:bg-gray-50 rounded transition p-1 -m-1">
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="text-xs font-bold text-gray-900">{{ $bitacora->usuario?->name ?? 'Sistema' }}</span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">{{ $bitacora->created_at->format('d M, H:i \h') }}</span>
                        </div>
                        <p class="text-xs text-gray-600 truncate">{{ $bitacora->descripcion }}</p>
                    </div>

                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4">No hay eventos en la bitácora.</p>
                @endforelse

            </div>

        </div>
    </div>

</div>

<!-- MODAL DETALLE DE EVENTO -->
<div id="modal-evento" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="cerrarModal()"></div>
    
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 relative z-10 overflow-hidden transform transition-all">
        
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Detalle del Evento</h3>
            <button onclick="cerrarModal()" class="text-gray-400 hover:text-gray-600 transition p-1 border border-gray-200 rounded-md">
                <i data-lucide="x" style="width:16px;height:16px;"></i>
            </button>
        </div>
        
        <div class="p-6 space-y-4">
            <div>
                <span class="block text-xs text-gray-500 mb-1">Usuario</span>
                <p class="text-sm font-semibold text-gray-900" id="modal-user"></p>
            </div>
            
            <div>
                <span class="block text-xs text-gray-500 mb-1">Tipo de evento</span>
                <p class="text-sm font-medium text-gray-900" id="modal-tipo"></p>
            </div>
            
            <div>
                <span class="block text-xs text-gray-500 mb-1">Fecha</span>
                <p class="text-sm font-medium text-gray-900" id="modal-fecha"></p>
            </div>
            
            <div>
                <span class="block text-xs text-gray-500 mb-1">Descripción</span>
                <p class="text-sm font-medium text-gray-900 bg-gray-50 p-3 rounded-lg border border-gray-100" id="modal-desc"></p>
            </div>
        </div>
        
    </div>
</div>

@push('scripts')
<script>
    function mostrarDetalleEvento(usuario, tipo, fecha, desc) {
        document.getElementById('modal-user').textContent = usuario;
        document.getElementById('modal-tipo').textContent = tipo;
        document.getElementById('modal-fecha').textContent = fecha;
        document.getElementById('modal-desc').textContent = desc;
        
        const modal = document.getElementById('modal-evento');
        modal.classList.remove('hidden');
    }
    
    function cerrarModal() {
        const modal = document.getElementById('modal-evento');
        modal.classList.add('hidden');
    }
</script>
@endpush

@endsection
