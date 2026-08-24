@extends('layouts.app')

@section('title', 'Detalles del Caso - ' . $caso->radicado)

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/casos.css') }}">
@endpush

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex flex-wrap sm:flex-nowrap items-center gap-3 mb-2">
        <a href="{{ route('casos.index') }}" class="text-gray-400 hover:text-gray-700 transition shrink-0">
            <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
        </a>
        <h1 class="text-xl sm:text-2xl font-bold text-gray-900 truncate">{{ $caso->radicado }}</h1>
        @php
            $badgeClass = match($caso->estado) {
                'En proceso'  => 'bg-blue-100 text-blue-700',
                'Completado'  => 'bg-green-100 text-green-700',
                'Finalizado'  => 'bg-red-100 text-red-700',
                default       => 'bg-gray-100 text-gray-700',
            };
        @endphp
        <span class="px-2.5 py-1 rounded-md text-[10px] sm:text-xs font-bold tracking-wide shrink-0 {{ $badgeClass }}">
            {{ $caso->estado }}
        </span>

        @if($esAdmin && $caso->estado !== 'Finalizado')
            @php
                $totalTareas = $caso->tareas->count();
                $completadas = $caso->tareas->where('estado', 'Completada')->count();
                $todasCompletadas = ($totalTareas > 0 && $totalTareas === $completadas);
            @endphp
            <div class="w-full sm:w-auto sm:ml-auto mt-2 sm:mt-0">
                <form action="{{ route('casos.finalizar', $caso->id) }}" method="POST" @if($todasCompletadas) onsubmit="confirmarAccion(event, this, '¿Finalizar caso?', 'Esta acción notificará a todos los asignados y cambiará el estado permanentemente.');" @else onsubmit="event.preventDefault();" @endif>
                    @csrf
                    <button type="submit" class="w-full sm:w-auto px-4 py-2 rounded-md font-bold text-sm transition shadow-sm flex items-center justify-center gap-2 {{ $todasCompletadas ? 'bg-[#c8828b] hover:bg-[#b11226] text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed opacity-75' }}" {{ $todasCompletadas ? '' : 'disabled' }} title="{{ $todasCompletadas ? 'Finalizar Caso' : 'Todas las tareas deben estar completadas para finalizar' }}">
                        <i data-lucide="check-circle" class="icon-sm"></i>
                        Finalizar Caso
                    </button>
                </form>
            </div>
        @endif
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
                    <i data-lucide="calendar" class="icon-md"></i>
                    {{ \Carbon\Carbon::parse($caso->created_at)->locale('es')->translatedFormat('d \d\e F \d\e Y') }}
                </div>
                @if($caso->link_drive)
                <a href="{{ $caso->link_drive }}" target="_blank" class="flex items-center gap-1 text-red-600 hover:text-red-800 font-medium transition">
                    Abrir link de Drive <i data-lucide="external-link" class="icon-sm"></i>
                </a>
                @endif
            </div>
        </div>

        @if($esAdmin)
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
                @if($esAdmin && $caso->estado !== 'Finalizado')
                <button type="button" onclick="document.getElementById('modal-agregar-usuario').classList.remove('hidden')" class="btn-secondary text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300">
                    <i data-lucide="user-plus" class="icon-sm"></i> Agregar Usuario
                </button>
                @endif
            </div>

            <div class="bg-blue-50 border border-blue-100 text-blue-700 p-3 rounded-md text-sm flex items-start gap-2 mb-4">
                <i data-lucide="info" class="icon-lg info-icon"></i>
                <p>Puedes modificar los usuarios asignados en cualquier momento. El sistema se ajusta automáticamente sin afectar el proceso.</p>
            </div>

            <div class="space-y-3">
                @forelse($caso->usuarios as $user)
                <div class="border border-gray-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center text-gray-500 shrink-0">
                            <i data-lucide="user" style="width:20px;height:20px;"></i>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-gray-900">{{ $user->name }}</h3>
                            @php
                                $tareasUsuario = $caso->tareas->where('user_id', $user->id);
                                $completadasUsu = $tareasUsuario->where('estado', 'Completada')->count();
                            @endphp
                            <p class="text-xs text-gray-500">
                                Asignado: {{ \Carbon\Carbon::parse($user->pivot->fecha_asignacion)->locale('es')->translatedFormat('d \d\e M') }} • {{ $completadasUsu }}/{{ $tareasUsuario->count() }} tareas
                            </p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-3 sm:ml-4">
                        @php
                            $tareasUsuario = $caso->tareas->where('user_id', $user->id);
                            $totalUsr = $tareasUsuario->count();
                            $completadasUsr = $tareasUsuario->where('estado', 'Completada')->count();
                            
                            // Determinación dinámica del estado en caso de desincronización de la base de datos
                            if ($totalUsr > 0 && $completadasUsr === $totalUsr) {
                                $estadoUsu = 'Finalizado';
                            } elseif ($totalUsr > 0 && $completadasUsr > 0) {
                                $estadoUsu = 'En proceso';
                            } else {
                                $estadoUsu = $user->pivot->estado;
                            }

                            $euClass = match($estadoUsu) {
                                'En proceso' => 'bg-blue-600 text-white',
                                'Finalizado' => 'bg-green-600 text-white',
                                default      => 'bg-gray-500 text-white',
                            };
                        @endphp
                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold tracking-wide {{ $euClass }}">
                            {{ $estadoUsu }}
                        </span>
                        @if($esAdmin && $caso->estado !== 'Finalizado')
                        <button type="button" onclick="abrirModalReemplazo({{ $user->id }}, '{{ addslashes($user->name) }}')" class="text-gray-400 hover:text-blue-600 p-1 bg-gray-50 hover:bg-blue-50 rounded" title="Reemplazar Usuario">
                            <i data-lucide="refresh-cw" class="icon-md"></i>
                        </button>
                        <form action="{{ route('casos.usuarios.remover', [$caso->id, $user->id]) }}" method="POST" class="inline" onsubmit="confirmarAccion(event, this, '¿Desvincular usuario?', 'El usuario dejará de tener acceso a este caso, pero sus tareas pasadas se conservarán.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-400 hover:text-red-600 p-1 bg-red-50 rounded" title="Remover Usuario">
                                <i data-lucide="trash-2" class="icon-md"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4">No hay usuarios asignados a este caso.</p>
                @endforelse
            </div>
        </div>
        @endif

        <!-- Lista de Tareas -->
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900">
                    {{ $esAdmin ? 'Lista de Tareas' : 'Mis Tareas' }}
                </h2>
            </div>

            @if($esAdmin && $caso->estado !== 'Finalizado')
            <div class="mb-5 bg-gray-50 p-3 rounded-lg border border-gray-200">
                <form action="{{ route('tareas.guardar', $caso->id) }}" method="POST" class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    @csrf
                    <div class="flex-1 w-full">
                        <input type="text" name="descripcion" placeholder="Descripción de la nueva tarea (mín. 10 caracteres)..." class="form-input w-full text-sm" required minlength="10" maxlength="2000">
                    </div>
                    <div class="w-full sm:w-56">
                        <select name="user_id" class="form-select w-full text-sm" required>
                            <option value="">Asignar a...</option>
                            @php
                                $usuariosAsignados = $caso->usuarios()->wherePivot('activo', true)->get();
                                $listaUsuarios = $usuariosAsignados->isEmpty() ? \App\Models\User::where('activo', true)->orderBy('name')->get() : $usuariosAsignados;
                            @endphp
                            @foreach($listaUsuarios as $u)
                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-secondary w-full sm:w-auto text-xs text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300 py-2 px-4 whitespace-nowrap justify-center">
                        <i data-lucide="plus" class="icon-sm"></i> Agregar
                    </button>
                </form>
                @if($errors->has('descripcion') || $errors->has('user_id'))
                <div class="mt-2">
                    @error('descripcion') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                    @error('user_id') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                @endif
            </div>
            
            <div class="space-y-3">
                @forelse($caso->tareas as $tarea)
                <div class="border border-gray-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center justify-between transition hover:border-gray-300 gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-400">#{{ $tarea->orden ?? $loop->iteration }}</span>
                            <h3 class="text-sm font-bold text-gray-900">{{ $tarea->descripcion }}</h3>
                        </div>
                        <p class="text-xs text-gray-500">
                            Asignado a: <strong class="text-gray-700">{{ $tarea->usuario->name ?? 'Sin asignar' }}</strong>
                            | Estado: {{ $tarea->estado }}
                        </p>
                        @if($tarea->estado === 'Completada' && $tarea->observacion)
                            <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                <strong>Observación:</strong> {{ $tarea->observacion->contenido }}
                            </div>
                        @endif
                    </div>
                    
                    <div class="flex items-center gap-2 sm:ml-4">
                        @if($esAdmin && $caso->estado !== 'Finalizado')
                        <form action="{{ route('tareas.eliminar', [$caso->id, $tarea->id]) }}" method="POST" class="inline" onsubmit="confirmarAccion(event, this, '¿Eliminar tarea?', 'Esta acción borrará la tarea de forma permanente.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-gray-400 hover:text-red-600 p-1.5 bg-gray-50 hover:bg-red-50 rounded transition" title="Eliminar Tarea">
                                <i data-lucide="trash-2" class="icon-md"></i>
                            </button>
                        </form>
                        @endif
                    </div>
                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">No hay tareas creadas para este caso.</p>
                @endforelse
            </div>

            @else
                @php
                    $misTareas = $caso->tareas->where('user_id', auth()->id());
                    $completadas = $misTareas->where('estado', 'Completada')->count();
                    $total = $misTareas->count();
                @endphp
                <p class="text-sm text-gray-500 mb-4">{{ $completadas }} de {{ $total }} tareas completadas</p>
                
                <div class="space-y-4">
                    @foreach($misTareas as $tarea)
                        @if($tarea->estado === 'Completada')
                            <div class="border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex items-center gap-2 text-green-700 font-bold text-sm mb-1">
                                    <i data-lucide="check-circle" class="icon-sm"></i>
                                    Tarea {{ $tarea->orden }} - Completada
                                </div>
                                <p class="text-sm text-gray-900 mb-2">{{ $tarea->descripcion }}</p>
                                @if($tarea->observacion)
                                <div class="mt-2 text-sm text-gray-700">
                                    <span class="font-semibold text-gray-600 block mb-0.5">Observación:</span>
                                    {{ $tarea->observacion->contenido }}
                                </div>
                                @endif
                            </div>
                        @else
                            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                                <h3 class="font-bold text-sm text-gray-900 mb-1">Tarea {{ $tarea->orden }}</h3>
                                <p class="text-sm text-gray-600 mb-4">{{ $tarea->descripcion }}</p>
                                
                                <form action="{{ route('tareas.completar', [$caso->id, $tarea->id]) }}" method="POST">
                                    @csrf
                                    <label class="block text-sm font-bold text-gray-900 mb-2">Observación de la Tarea</label>
                                    <textarea name="observacion" rows="3" placeholder="Describe el trabajo realizado, hallazgos o recomendaciones..." class="w-full bg-gray-50 border border-gray-200 rounded-md p-3 text-sm focus:outline-none focus:border-red-400 mb-3" required></textarea>
                                    <button type="submit" class="w-full py-2.5 bg-[#c8828b] hover:bg-[#b11226] text-white rounded-md font-bold text-sm transition shadow-sm">
                                        Finalizar Tarea
                                    </button>
                                </form>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

    </div>

    <!-- COLUMNA DERECHA (TABS BITÁCORA / MENSAJES) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col sticky-sidebar">
            
            <!-- TABS -->
            <div class="flex border-b border-gray-200 bg-gray-50 p-2 gap-1">
                @php $verBitacora = auth()->user()->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']); @endphp
                @if($verBitacora)
                <button onclick="switchTab('bitacora')" id="btn-tab-bitacora" class="flex-1 py-2 text-sm font-bold text-gray-900 bg-white shadow-sm rounded-md flex items-center justify-center gap-2 transition">
                    <i data-lucide="file-text" class="icon-md"></i> Bitácora
                </button>
                <button onclick="switchTab('mensajes')" id="btn-tab-mensajes" class="flex-1 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center justify-center gap-2 transition">
                    <i data-lucide="message-square" class="icon-md"></i> Mensajes
                </button>
                @else
                <div class="flex-1 py-2 text-sm font-bold text-gray-900 bg-white shadow-sm rounded-md flex items-center justify-center gap-2">
                    <i data-lucide="message-square" class="icon-md"></i> Mensajes
                </div>
                @endif
            </div>

            <!-- CONTENIDO BITÁCORA -->
            @if($verBitacora)
            <div id="content-bitacora" class="flex-1 overflow-y-auto p-4 space-y-6 relative block">
                <div class="absolute left-8 top-0 bottom-0 w-px bg-gray-200"></div>

                @forelse($caso->bitacoras as $bitacora)
                <div class="relative flex items-start gap-4 z-10 cursor-pointer group" onclick="mostrarDetalleEvento('{{ $bitacora->usuario?->name ?? 'Sistema' }}', '{{ $bitacora->accion }}', '{{ \Carbon\Carbon::parse($bitacora->created_at)->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s H:i \h') }}', '{{ addslashes($bitacora->descripcion) }}')">
                    
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
                        <i data-lucide="{{ $iconData['icon'] }}" class="icon-sm"></i>
                    </div>

                    <div class="flex-1 pt-1 bg-white group-hover:bg-gray-50 rounded transition p-1 -m-1">
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="text-xs font-bold text-gray-900">{{ $bitacora->usuario?->name ?? 'Sistema' }}</span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">{{ $bitacora->created_at->format('d M, H:i \h') }}</span>
                        </div>
                        <p class="text-xs text-gray-600 truncate">{{ $bitacora->descripcion }}</p>
                        @if(!empty($bitacora->metadata) && isset($bitacora->metadata['observacion']))
                            <div class="mt-1.5 p-1.5 bg-yellow-50/50 border border-yellow-100 rounded text-gray-700 italic text-[10px] truncate">
                                <span class="font-semibold text-gray-900 not-italic"><i data-lucide="message-square" style="width:10px;height:10px;display:inline;margin-top:-2px;"></i> Obs:</span> 
                                {{ $bitacora->metadata['observacion'] }}
                            </div>
                        @endif
                    </div>

                </div>
                @empty
                <p class="text-sm text-gray-500 text-center py-4">No hay eventos en la bitácora.</p>
                @endforelse

            </div>
            @endif

            <!-- CONTENIDO MENSAJES -->
            <div id="content-mensajes" class="flex-1 overflow-y-auto p-4 flex-col {{ $verBitacora ? 'hidden' : 'flex' }} bg-white relative">
                <div class="flex-1 space-y-4 overflow-y-auto mb-4 pr-2 flex flex-col" id="chat-container">
                    @forelse($caso->mensajes as $msg)
                        @php $esMio = $msg->user_id === auth()->id(); @endphp
                        <div class="flex flex-col {{ $esMio ? 'items-end' : 'items-start' }}">
                            <div class="{{ $esMio ? 'bg-[#b11226] text-white' : 'bg-gray-100 text-gray-800' }} rounded-xl p-3 max-w-[85%] relative shadow-sm">
                                <span class="block text-[11px] font-bold opacity-90 mb-1 {{ $esMio ? 'text-red-100' : 'text-gray-600' }}">
                                    {{ $esMio ? auth()->user()->name : $msg->autor?->name }}
                                </span>
                                <p class="text-[13.5px] leading-relaxed">{{ $msg->mensaje }}</p>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 mx-1">{{ $msg->created_at->format('d M, H:i \h') }}</span>
                        </div>
                    @empty
                        <div class="h-full flex items-center justify-center text-sm text-gray-400 italic my-auto">Empieza la conversación en este caso.</div>
                    @endforelse
                </div>

                <div class="pt-3 border-t border-gray-100 shrink-0">
                    <form id="form-chat" action="{{ route('casos.mensajes', $caso->id) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="mensaje" required placeholder="Escribe un mensaje..." class="flex-1 bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg px-4 py-2.5 focus:outline-none focus:border-red-400 focus:bg-white transition">
                        <button type="submit" class="chat-btn-send text-white rounded-lg transition shrink-0 flex items-center justify-center w-11 h-11">
                            <svg class="icon-lg chat-send-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </form>
                </div>
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

@if($esAdmin)
<!-- MODAL AGREGAR USUARIO -->
<div id="modal-agregar-usuario" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="document.getElementById('modal-agregar-usuario').classList.add('hidden')"></div>
    
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 relative z-10 overflow-hidden transform transition-all">
        <form action="{{ route('casos.usuarios.asignar', $caso->id) }}" method="POST">
            @csrf
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Agregar Usuario al Caso</h3>
                <button type="button" onclick="document.getElementById('modal-agregar-usuario').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition p-1 border border-gray-200 rounded-md">
                    <i data-lucide="x" class="icon-md"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-900 mb-2">Seleccionar Usuario</label>
                    <div class="relative mb-2">
                        <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400 icon-sm"></i>
                        <input type="text" placeholder="Buscar por nombre o rol..." onkeyup="filtrarUsuarios(this, 'select-agregar')" class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:outline-none focus:border-red-400">
                    </div>
                    <select id="select-agregar" name="user_id" required size="6" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                        @php
                            $usuariosAsignados = $caso->usuarios->pluck('id')->toArray();
                            $usuariosDisponibles = \App\Models\User::where('activo', true)
                                ->whereNotIn('id', $usuariosAsignados)
                                ->orderBy('name')
                                ->get();
                        @endphp
                        @foreach($usuariosDisponibles as $ud)
                            <option value="{{ $ud->id }}" class="py-1.5 px-2 border-b border-gray-100 last:border-0 hover:bg-gray-100 cursor-pointer rounded">{{ $ud->name }} ({{ $ud->role?->nombre }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                <button type="button" onclick="document.getElementById('modal-agregar-usuario').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition">Cancelar</button>
                <button type="submit" class="btn-primary">Asignar</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL REEMPLAZAR USUARIO -->
<div id="modal-reemplazar-usuario" class="fixed inset-0 z-50 hidden flex items-center justify-center">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" onclick="document.getElementById('modal-reemplazar-usuario').classList.add('hidden')"></div>
    
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md mx-4 relative z-10 overflow-hidden transform transition-all">
        <form id="form-reemplazar-usuario" method="POST">
            @csrf
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-lg font-bold text-gray-900">Reemplazar Usuario</h3>
                <button type="button" onclick="document.getElementById('modal-reemplazar-usuario').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 transition p-1 border border-gray-200 rounded-md">
                    <i data-lucide="x" class="icon-md"></i>
                </button>
            </div>
            
            <div class="p-6 space-y-4">
                <p class="text-sm text-gray-600 mb-2">Vas a reemplazar a <strong id="nombre-reemplazo"></strong>. Todas sus tareas en este caso serán transferidas al nuevo usuario.</p>
                <div>
                    <label class="block text-xs font-semibold text-gray-900 mb-2">Seleccionar Nuevo Usuario</label>
                    <div class="relative mb-2">
                        <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400 icon-sm"></i>
                        <input type="text" placeholder="Buscar por nombre o rol..." onkeyup="filtrarUsuarios(this, 'select-reemplazo')" class="w-full pl-9 pr-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:outline-none focus:border-blue-400">
                    </div>
                    <select id="select-reemplazo" name="nuevo_user_id" required size="6" class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-blue-500 outline-none transition">
                        @foreach($usuariosDisponibles as $ud)
                            <option value="{{ $ud->id }}" class="py-1.5 px-2 border-b border-gray-100 last:border-0 hover:bg-gray-100 cursor-pointer rounded">{{ $ud->name }} ({{ $ud->role?->nombre }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 bg-gray-50">
                <button type="button" onclick="document.getElementById('modal-reemplazar-usuario').classList.add('hidden')" class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-100 transition">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm font-bold text-white bg-blue-600 rounded-md hover:bg-blue-700 transition">Confirmar Reemplazo</button>
            </div>
        </form>
    </div>
</div>
@endif

@push('scripts')
<script>
    function abrirModalReemplazo(usuarioId, nombre) {
        document.getElementById('nombre-reemplazo').textContent = nombre;
        document.getElementById('form-reemplazar-usuario').action = `/casos/{{ $caso->id }}/usuarios/${usuarioId}/reemplazar`;
        document.getElementById('modal-reemplazar-usuario').classList.remove('hidden');
    }
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

    function switchTab(tab) {
        const btnBitacora = document.getElementById('btn-tab-bitacora');
        if (!btnBitacora) return;
        
        const btnMensajes = document.getElementById('btn-tab-mensajes');
        const contentBitacora = document.getElementById('content-bitacora');
        const contentMensajes = document.getElementById('content-mensajes');

        if (tab === 'bitacora') {
            // Activar botón bitacora
            btnBitacora.className = 'flex-1 py-2 text-sm font-bold text-gray-900 bg-white shadow-sm rounded-md flex items-center justify-center gap-2 transition';
            btnMensajes.className = 'flex-1 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center justify-center gap-2 transition';
            
            // Mostrar contenido
            contentBitacora.classList.remove('hidden');
            contentBitacora.classList.add('block');
            contentMensajes.classList.add('hidden');
            contentMensajes.classList.remove('flex');
        } else {
            // Activar botón mensajes
            btnMensajes.className = 'flex-1 py-2 text-sm font-bold text-gray-900 bg-white shadow-sm rounded-md flex items-center justify-center gap-2 transition';
            btnBitacora.className = 'flex-1 py-2 text-sm font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-md flex items-center justify-center gap-2 transition';
            
            // Mostrar contenido
            contentBitacora.classList.add('hidden');
            contentBitacora.classList.remove('block');
            contentMensajes.classList.remove('hidden');
            contentMensajes.classList.add('flex');

            // Scroll down
            const chatContainer = document.getElementById('chat-container');
            if(chatContainer) {
                chatContainer.scrollTop = chatContainer.scrollHeight;
            }
        }
    }

    // Auto-open chat if coming from redirect
    @if(session('tab') === 'mensajes')
        switchTab('mensajes');
    @endif

    // Filtrar usuarios en los select de los modales
    function filtrarUsuarios(input, selectId) {
        const filter = input.value.toLowerCase();
        const select = document.getElementById(selectId);
        const options = select.getElementsByTagName('option');
        
        let hasVisibleOptions = false;

        for (let i = 0; i < options.length; i++) {
            if (options[i].value === "") continue;
            
            const txtValue = options[i].textContent || options[i].innerText;
            if (txtValue.toLowerCase().indexOf(filter) > -1) {
                options[i].style.display = "";
                hasVisibleOptions = true;
            } else {
                options[i].style.display = "none";
            }
        }
    }

    // Enviar mensaje por AJAX
    const formChat = document.getElementById('form-chat');
    if (formChat) {
        formChat.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = this.querySelector('input[name="mensaje"]');
            const mensaje = input.value.trim();
            const btn = this.querySelector('button[type="submit"]');
            
            if (!mensaje) return;
            
            // Deshabilitar botón
            btn.disabled = true;
            btn.style.opacity = '0.5';

            fetch(this.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ mensaje: mensaje })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const chatContainer = document.getElementById('chat-container');
                    
                    // Quitar mensaje de "Empieza la conversación" si existe
                    const emptyMsg = chatContainer.querySelector('.italic');
                    if(emptyMsg) emptyMsg.remove();

                    // Crear y agregar nueva burbuja
                    const div = document.createElement('div');
                    div.className = 'flex flex-col items-end';
                    div.innerHTML = `
                        <div class="bg-[#b11226] text-white rounded-xl p-3 max-w-[85%] relative shadow-sm">
                            <span class="block text-[11px] font-bold opacity-90 mb-1 text-red-100">
                                Tú
                            </span>
                            <p class="text-[13.5px] leading-relaxed">${data.mensaje}</p>
                        </div>
                        <span class="text-[10px] text-gray-400 mt-1 mx-1">${data.fecha}</span>
                    `;
                    chatContainer.appendChild(div);
                    
                    // Limpiar input y bajar scroll
                    input.value = '';
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            })
            .catch(error => {
                console.error('Error enviando mensaje:', error);
                alert('Ocurrió un error al enviar el mensaje.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.style.opacity = '1';
                input.focus();
            });
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        if (window.Echo) {
            window.Echo.private('caso.{{ $caso->id }}')
                .listen('.NuevoMensajeCaso', (e) => {
                    if (e.mensaje.user_id != window.userId) {
                        const chatContainer = document.getElementById('chat-container');
                        if(chatContainer) {
                            const emptyMsg = chatContainer.querySelector('.italic');
                            if(emptyMsg) emptyMsg.remove();

                            const div = document.createElement('div');
                            div.className = 'flex flex-col items-start';
                            div.innerHTML = `
                                <div class="bg-gray-100 text-gray-800 rounded-xl p-3 max-w-[85%] relative shadow-sm">
                                    <span class="block text-[11px] font-bold opacity-90 mb-1 text-gray-600">
                                        ${e.mensaje.user_name}
                                    </span>
                                    <p class="text-[13.5px] leading-relaxed">${e.mensaje.mensaje}</p>
                                </div>
                                <span class="text-[10px] text-gray-400 mt-1 mx-1">${e.mensaje.fecha}</span>
                            `;
                            chatContainer.appendChild(div);
                            chatContainer.scrollTop = chatContainer.scrollHeight;
                        }
                    }
                });
        }
    });
</script>
@endpush

@endsection
