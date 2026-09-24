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
        <span id="case-status-badge" class="px-2.5 py-1 rounded-md text-[10px] sm:text-xs font-bold tracking-wide shrink-0 {{ $badgeClass }}">
            {{ $caso->estado }}
        </span>

        @if($esAdmin && $caso->estado !== 'Finalizado')
            @php
                $todasCompletadas = $caso->puedeFinalizarse();
            @endphp
            <div class="w-full sm:w-auto sm:ml-auto mt-2 sm:mt-0">
                <form id="case-finalize-form" action="{{ route('casos.finalizar', $caso->id) }}" method="POST" onsubmit="if (this.querySelector('button').disabled) { event.preventDefault(); return false; } confirmarAccion(event, this, '¿Finalizar caso?', 'Esta acción notificará a todos los asignados y cambiará el estado permanentemente.');">
                    @csrf
                    <button id="case-finalize-button" type="submit" class="w-full sm:w-auto px-4 py-2 rounded-md font-bold text-sm transition shadow-sm flex items-center justify-center gap-2 {{ $todasCompletadas ? 'bg-[#c8828b] hover:bg-[#b11226] text-white' : 'bg-gray-200 text-gray-500 cursor-not-allowed opacity-75' }}" {{ $todasCompletadas ? '' : 'disabled' }} title="{{ $todasCompletadas ? 'Finalizar Caso' : 'Debe haber usuarios asignados y todas sus tareas deben estar completadas' }}">
                        <i data-lucide="check-circle" class="icon-sm"></i>
                        <span id="case-finalize-label">Finalizar Caso</span>
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
        <div id="mis-tareas" class="bg-white rounded-lg border border-gray-200 shadow-sm p-6 scroll-mt-6">
            <h2 class="text-base font-bold text-gray-900 mb-4">Información General</h2>
            
            <div class="mb-4">
                <span class="block text-xs font-semibold text-gray-500 mb-1">Descripción</span>
                <p class="text-sm text-gray-800 leading-relaxed">{{ $caso->descripcion }}</p>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-4 mb-4">
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Tipo de solicitante</span>
                    <p class="text-sm text-gray-800">
                        {{ match($caso->solicitanteTipoActual()) {
                            'persona' => 'Persona natural',
                            'empresa' => 'Persona jurídica',
                            default => 'No especificado',
                        } }}
                    </p>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Solicitante</span>
                    <p class="text-sm text-gray-800">{{ $caso->solicitanteNombreActual() }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Tipo de documento</span>
                    <p class="text-sm text-gray-800">{{ $caso->solicitanteTipoDocumentoActual()?->codigo ?? 'No especificado' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Número de documento / NIT</span>
                    <p class="text-sm text-gray-800">{{ $caso->solicitanteDocumentoActual() ?: 'Documento pendiente' }}</p>
                </div>
                <div>
                    <span class="block text-xs font-semibold text-gray-500 mb-1">Día de solicitud</span>
                    <p class="text-sm text-gray-800">{{ $caso->fecha_solicitud?->format('d/m/Y') ?? 'No registrada' }}</p>
                </div>
            </div>
            
            @if($caso->ans_fecha_limite)
                @php
                    $diasRestantesAns = app('App\Services\AnsService')->diasRestantes($caso);
                    $ansBadge = match($caso->ans_estado) {
                        'vencido', 'incumplido' => 'bg-red-100 text-red-700',
                        'critico' => 'bg-orange-100 text-orange-700',
                        'preventivo' => 'bg-yellow-100 text-yellow-700',
                        'cumplido' => 'bg-green-100 text-green-700',
                        default => 'bg-blue-100 text-blue-700',
                    };
                @endphp
                <div class="mt-5 rounded-lg border border-gray-200 bg-gray-50 p-4" id="case-ans-panel">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                        <h3 class="text-sm font-bold text-gray-900">Cumplimiento ANS</h3>
                        <span id="case-ans-status" class="px-2.5 py-1 rounded-full text-xs font-bold {{ $ansBadge }}">
                            {{ ucfirst($caso->ans_estado ?? 'vigente') }}
                        </span>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                        <div><span class="block text-xs text-gray-500">Inicio</span>{{ $caso->ans_fecha_inicio?->format('d/m/Y') }}</div>
                        <div><span class="block text-xs text-gray-500">ANS aplicado</span>{{ $caso->ans_dias }} días {{ $caso->ans_tipo_dias }}</div>
                        <div><span class="block text-xs text-gray-500">Fecha límite</span><span id="case-ans-deadline">{{ $caso->ans_fecha_limite->format('d/m/Y') }}</span></div>
                        <div><span class="block text-xs text-gray-500">Tiempo</span><span id="case-ans-remaining">{{ $diasRestantesAns >= 0 ? $diasRestantesAns.' días restantes' : abs($diasRestantesAns).' días de retraso' }}</span></div>
                    </div>
                </div>
            @else
                <p class="mt-4 text-xs text-gray-400">Este caso histórico no tiene ANS asignado.</p>
            @endif

            <div class="flex items-center gap-4 text-sm mt-6 pt-4 border-t border-gray-100">
                <div class="flex items-center gap-2 text-gray-500">
                    <i data-lucide="calendar" class="icon-md"></i>
                    {{ \App\Support\LocalDate::inBogota($caso->created_at)?->locale('es')->translatedFormat('d \d\e F \d\e Y') }}
                </div>
                @if($caso->link_drive)
                <a href="{{ $caso->link_drive }}" target="_blank" class="flex items-center gap-1 text-red-600 hover:text-red-800 font-medium transition">
                    Abrir link de Drive <i data-lucide="external-link" class="icon-sm"></i>
                </a>
                @endif
            </div>

            @if(auth()->user()->esJuridica())
            <div class="mt-5 border-t border-gray-100 pt-4">
                <button type="button" onclick="document.getElementById('modal-corregir-caso').showModal()" class="btn-secondary text-red-700 border-red-200 hover:bg-red-50">
                    <i data-lucide="pencil" class="icon-sm"></i> Corregir información
                </button>
            </div>
            <dialog id="modal-corregir-caso" class="w-[min(94vw,760px)] rounded-xl p-0 shadow-2xl backdrop:bg-gray-900/60">
                <div class="bg-white p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-lg font-bold text-gray-900">Corregir información del caso</h2>
                            <p class="text-sm text-gray-500 mt-1">Los cambios quedarán registrados con valor anterior, valor nuevo y motivo.</p>
                        </div>
                        <button type="button" onclick="document.getElementById('modal-corregir-caso').close()" class="text-gray-400 hover:text-gray-700 text-2xl" aria-label="Cerrar">×</button>
                    </div>
                <form action="{{ route('casos.correccion', $caso) }}" method="POST" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    @csrf
                    @method('PUT')
                    <label class="text-xs font-semibold text-gray-700">Nombre / razón social *
                        <input class="form-input w-full mt-1" name="nombre_solicitante" value="{{ old('nombre_solicitante', $caso->solicitanteNombreActual()) }}" required>
                    </label>
                    <label class="text-xs font-semibold text-gray-700">Tipo de solicitante *
                    <select id="correction-applicant-type" class="form-select w-full mt-1" name="tipo_solicitante" required>
                        <option value="persona" @selected(old('tipo_solicitante', $caso->solicitanteTipoActual()) === 'persona')>Persona natural</option>
                        <option value="empresa" @selected(old('tipo_solicitante', $caso->solicitanteTipoActual()) === 'empresa')>Persona jurídica</option>
                    </select>
                    </label>
                    <label class="text-xs font-semibold text-gray-700">Tipo de documento
                    <select id="correction-document-type" class="form-select w-full mt-1" name="tipo_documento_solicitante_id" data-selected="{{ old('tipo_documento_solicitante_id', $caso->solicitanteTipoDocumentoActual()?->id) }}">
                        <option value="">Sin tipo de documento</option>
                    </select>
                    </label>
                    <label class="text-xs font-semibold text-gray-700">Número de documento / NIT
                        <input class="form-input w-full mt-1" name="documento_solicitante" value="{{ old('documento_solicitante', $caso->solicitanteDocumentoActual()) }}" placeholder="Opcional">
                    </label>
                    <label class="text-xs font-semibold text-gray-700">Fecha de solicitud *
                        <input class="form-input w-full mt-1" type="date" name="fecha_solicitud" value="{{ old('fecha_solicitud', $caso->fecha_solicitud?->toDateString()) }}" required>
                    </label>
                    <select class="form-select" name="tipo_proceso_id" required>
                        @foreach($tiposProceso as $tipoProceso)
                            <option value="{{ $tipoProceso->id }}" @selected((int) old('tipo_proceso_id', $caso->tipo_id) === $tipoProceso->id)>{{ $tipoProceso->nombre }}</option>
                        @endforeach
                    </select>
                    <select class="form-select" name="subtipo_proceso_id" required>
                        @foreach($tiposProceso as $tipoProceso)
                            @foreach($tipoProceso->subtipos as $subtipoProceso)
                                <option value="{{ $subtipoProceso->id }}" @selected((int) old('subtipo_proceso_id', $caso->subtipo_id) === $subtipoProceso->id)>{{ $tipoProceso->nombre }} — {{ $subtipoProceso->nombre }}</option>
                            @endforeach
                        @endforeach
                    </select>
                    <input class="form-input sm:col-span-2" name="descripcion" value="{{ old('descripcion', $caso->descripcion) }}" required placeholder="Descripción">
                    <textarea class="form-input sm:col-span-2" name="observacion_inicial" placeholder="Observación inicial">{{ old('observacion_inicial', $caso->observacion_inicial) }}</textarea>
                    <input class="form-input sm:col-span-2" type="url" name="enlace_google_drive" value="{{ old('enlace_google_drive', $caso->link_drive) }}" placeholder="Enlace asociado">
                    <label class="sm:col-span-2 text-xs font-semibold text-gray-700">Motivo de la corrección *
                        <textarea class="form-input w-full mt-1" name="motivo_correccion" required minlength="5" rows="3" placeholder="Explica por qué es necesario corregir la información">{{ old('motivo_correccion') }}</textarea>
                    </label>
                    <label class="sm:col-span-2 text-xs text-gray-600"><input type="checkbox" name="confirmar_recalculo_ans" value="1"> Confirmo el recálculo del ANS si cambio el tipo de proceso o la fecha de solicitud.</label>
                    <div class="sm:col-span-2 flex justify-end gap-2 pt-2">
                        <button type="button" onclick="document.getElementById('modal-corregir-caso').close()" class="btn-secondary">Cancelar</button>
                        <button class="btn-secondary text-red-700 border-red-200 justify-center" type="submit">Guardar corrección</button>
                    </div>
                </form>
                </div>
            </dialog>
            @endif
        </div>

        @if($esAdmin || $esConsultor)
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
                    <p id="case-progress" class="text-xs text-gray-500 mt-1">Progreso: {{ $progreso }}% ({{ $tareasCompletadas }}/{{ $totalTareas }} completados)</p>
                </div>
                @if($esAdmin && $caso->estado !== 'Finalizado')
                <button type="button" onclick="document.getElementById('modal-agregar-usuario').classList.remove('hidden')" class="btn-secondary text-red-600 border-red-200 hover:bg-red-50 hover:border-red-300">
                    <i data-lucide="user-plus" class="icon-sm"></i> Agregar Usuario
                </button>
                @endif
            </div>

            <div class="bg-blue-50 border border-blue-100 text-blue-700 p-3 rounded-md text-sm flex items-start gap-2 mb-4">
                <i data-lucide="info" class="icon-lg info-icon"></i>
                <p>{{ $esAdmin ? 'Puedes modificar los usuarios asignados en cualquier momento.' : 'Vista de solo lectura de los usuarios asignados.' }}</p>
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
                                Asignado: {{ \App\Support\LocalDate::inBogota($user->pivot->fecha_asignacion)?->locale('es')->translatedFormat('d \d\e M') }} • {{ $completadasUsu }}/{{ $tareasUsuario->count() }} tareas
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
            @php
                $puedeCompletarPropias = $puedeCompletarPropias
                    ?? (!$esConsultor && $caso->usuarios->contains('id', auth()->id()));
                $misTareas = $caso->tareas->where('user_id', auth()->id());
                $tareasAdministrables = $puedeCompletarPropias
                    ? $caso->tareas->where('user_id', '!=', auth()->id())
                    : $caso->tareas;
            @endphp
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-gray-900">
                    {{ $esConsultor ? 'Lista de Tareas' : ($esAdmin ? 'Gestión de Tareas' : 'Mis Tareas') }}
                </h2>
            </div>

            @if($esAdmin || $esConsultor)
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
                    <div class="w-full sm:w-36">
                        <select name="tipo_accion" class="form-select w-full text-sm">
                            <option value="normal">Normal</option>
                            <option value="firma">Firma</option>
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
            @endif

            <div class="space-y-3">
                @forelse($tareasAdministrables as $tarea)
                <div id="tarea-{{ $tarea->id }}" data-task-id="{{ $tarea->id }}" class="scroll-mt-24 border border-gray-200 rounded-lg p-4 flex flex-col sm:flex-row sm:items-center justify-between transition hover:border-gray-300 gap-3">
                    <div class="flex-1">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-bold text-gray-400">#{{ $tarea->orden ?? $loop->iteration }}</span>
                            <h3 class="text-sm font-bold text-gray-900">{{ $tarea->descripcion }}</h3>
                        </div>
                        <p class="text-xs text-gray-500">
                            Asignado a: <strong class="text-gray-700">{{ $tarea->usuario->name ?? 'Sin asignar' }}</strong>
                            | Tipo: <strong>{{ $tarea->tipo_accion === 'firma' ? 'Firma' : 'Normal' }}</strong>
                            | Estado: <span data-task-status>{{ $tarea->estado }}</span>
                        </p>
                        @if($tarea->estado === 'Completada' && $tarea->observacion)
                            <div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded">
                                <strong>Observación:</strong> {{ $tarea->observacion->contenido }}
                            </div>
                        @endif
                        @if(auth()->user()->esJuridica() && $tarea->versiones->isNotEmpty())
                            <details class="mt-2 text-xs text-gray-600">
                                <summary class="cursor-pointer font-semibold">Versiones de corrección ({{ $tarea->versiones->count() }})</summary>
                                @foreach($tarea->versiones as $version)
                                    <p class="mt-1">v{{ $version->version }} · {{ $version->correctora?->name }} · {{ $version->created_at?->format('d/m/Y H:i') }} · Campos: {{ implode(', ', $version->campos_modificados) }}</p>
                                @endforeach
                            </details>
                        @endif
                        @php
                            $solicitudVisible = $tarea->solicitudesCorreccion->first();
                        @endphp
                        <div data-task-correction data-correction-key="{{ $solicitudVisible ? implode(':', [$solicitudVisible->id, $solicitudVisible->estado, $solicitudVisible->updated_at?->getTimestamp()]) : 'none' }}">
                            @include('casos.partials.correccion-tarea', ['tarea' => $tarea])
                        </div>
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

            @endif

            @if($puedeCompletarPropias)
                @php
                    $completadas = $misTareas->where('estado', 'Completada')->count();
                    $total = $misTareas->count();
                @endphp
                @if($esAdmin)
                    <h3 class="text-sm font-bold text-gray-900 mt-6 mb-2 border-t border-gray-100 pt-5">Mis Tareas</h3>
                @endif
                <p class="text-sm text-gray-500 mb-4">{{ $completadas }} de {{ $total }} tareas completadas</p>
                
                <div class="space-y-4">
                    @forelse($misTareas as $tarea)
                        @if($tarea->estado === 'Completada')
                            <div id="tarea-{{ $tarea->id }}" data-task-id="{{ $tarea->id }}" class="scroll-mt-24 border border-green-200 bg-green-50 rounded-lg p-4">
                                <div class="flex items-center gap-2 text-green-700 font-bold text-sm mb-1">
                                    <i data-lucide="check-circle" class="icon-sm"></i>
                                    Tarea {{ $tarea->orden }} - <span data-task-status>Completada</span>
                                </div>
                                <p class="text-sm text-gray-900 mb-2">{{ $tarea->descripcion }}</p>
                                @if($tarea->observacion)
                                <div class="mt-2 text-sm text-gray-700">
                                    <span class="font-semibold text-gray-600 block mb-0.5">Observación:</span>
                                    {{ $tarea->observacion->contenido }}
                                </div>
                                @endif
                                @php
                                    $solicitudVisible = $tarea->solicitudesCorreccion->first();
                                @endphp
                                <div data-task-correction data-correction-key="{{ $solicitudVisible ? implode(':', [$solicitudVisible->id, $solicitudVisible->estado, $solicitudVisible->updated_at?->getTimestamp()]) : 'none' }}">
                                    @include('casos.partials.correccion-tarea', ['tarea' => $tarea])
                                </div>
                            </div>
                        @else
                            <div data-task-id="{{ $tarea->id }}" class="border border-gray-200 rounded-lg p-4 bg-white">
                                <h3 class="font-bold text-sm text-gray-900 mb-1">Tarea {{ $tarea->orden }} - <span data-task-status>{{ $tarea->estado }}</span></h3>
                                <p class="text-sm text-gray-600 mb-4">{{ $tarea->descripcion }}</p>
                                
                                <form data-task-completion-form action="{{ route('tareas.completar', [$caso->id, $tarea->id]) }}" method="POST">
                                    @csrf
                                    @if($tarea->tipo_accion === 'firma' && auth()->user()->esAbogado())
                                    <div class="mb-3 text-sm text-blue-700 bg-blue-50 border border-blue-100 rounded p-3">Esta acción registrará “Firma realizada” en la tarea.</div>
                                    <button type="submit" class="w-full py-2.5 bg-[#c8828b] hover:bg-[#b11226] text-white rounded-md font-bold text-sm transition shadow-sm">
                                        ✓ Firma realizada
                                    </button>
                                    @else
                                    <label class="block text-sm font-bold text-gray-900 mb-2">Observación de la Tarea</label>
                                    <textarea name="observacion" rows="3" placeholder="Describe el trabajo realizado, hallazgos o recomendaciones..." class="w-full bg-gray-50 border border-gray-200 rounded-md p-3 text-sm focus:outline-none focus:border-red-400 mb-3" required></textarea>
                                    <button type="submit" class="w-full py-2.5 bg-[#c8828b] hover:bg-[#b11226] text-white rounded-md font-bold text-sm transition shadow-sm">
                                        Finalizar Tarea
                                    </button>
                                    @endif
                                </form>
                            </div>
                        @endif
                    @empty
                        <p class="text-sm text-gray-500 text-center py-4 bg-gray-50 rounded-lg border border-dashed border-gray-200">No tienes tareas asignadas en este caso.</p>
                    @endforelse
                </div>
            @endif
        </div>

    </div>

    <!-- COLUMNA DERECHA (TABS BITÁCORA / MENSAJES) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden flex flex-col sticky-sidebar">
            
            <!-- TABS -->
            <div class="flex border-b border-gray-200 bg-gray-50 p-2 gap-1">
                @php $verBitacora = auth()->user()->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']); @endphp
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
                <div id="audit-event-{{ $bitacora->id }}" data-audit-action="{{ $bitacora->accion }}" class="relative flex items-start gap-4 z-10 cursor-pointer group" onclick="mostrarDetalleEvento('{{ $bitacora->usuario?->name ?? 'Sistema' }}', '{{ $bitacora->accion }}', '{{ \App\Support\LocalDate::inBogota($bitacora->created_at)?->locale('es')->translatedFormat('d \d\e F \d\e Y \a \l\a\s H:i \h') }}', '{{ addslashes($bitacora->descripcion) }}')">
                    
                    @php
                        $iconData = match(strtolower($bitacora->accion)) {
                            'crear' => ['icon' => 'file', 'color' => 'bg-gray-100 text-gray-500'],
                            'asignacion', 'asignar' => ['icon' => 'user-plus', 'color' => 'bg-blue-100 text-blue-500'],
                            'observación', 'observacion' => ['icon' => 'message-circle', 'color' => 'bg-purple-100 text-purple-500'],
                            'actualizar', 'cambio de estado' => ['icon' => 'refresh-cw', 'color' => 'bg-orange-100 text-orange-500'],
                            default => ['icon' => 'activity', 'color' => 'bg-gray-100 text-gray-500'],
                        };
                        $accionVisible = strtolower($bitacora->accion) === 'corregir tarea'
                            ? 'Tarea corregida'
                            : $bitacora->accion;
                        $detalleCorreccion = \App\Support\TaskCorrectionAuditPresenter::make($bitacora);
                    @endphp

                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 border-2 border-white {{ $iconData['color'] }} group-hover:scale-110 transition-transform">
                        <i data-lucide="{{ $iconData['icon'] }}" class="icon-sm"></i>
                    </div>

                    <div class="flex-1 pt-1 bg-white group-hover:bg-gray-50 rounded transition p-1 -m-1">
                        <div class="flex justify-between items-start mb-0.5">
                            <span class="text-xs font-bold text-gray-900">{{ $bitacora->usuario?->name ?? 'Sistema' }}</span>
                            <span class="text-[10px] text-gray-400 whitespace-nowrap ml-2">{{ \App\Support\LocalDate::inBogota($bitacora->created_at)?->locale('es')->translatedFormat('d M, H:i \h') }}</span>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-wide text-red-700 mb-1">{{ $accionVisible }}</p>
                        <p class="text-xs text-gray-600 {{ $detalleCorreccion ? '' : 'truncate' }}">{{ $bitacora->descripcion }}</p>
                        @include('components.task-correction-audit-details', ['detalle' => $detalleCorreccion, 'compacto' => true])
                        @if(!$detalleCorreccion)
                        @if(!empty($bitacora->metadata) && isset($bitacora->metadata['observacion']))
                            <div class="mt-1.5 p-1.5 bg-yellow-50/50 border border-yellow-100 rounded text-gray-700 italic text-[10px] truncate">
                                <span class="font-semibold text-gray-900 not-italic"><i data-lucide="message-square" style="width:10px;height:10px;display:inline;margin-top:-2px;"></i> Obs:</span> 
                                {{ $bitacora->metadata['observacion'] }}
                            </div>
                        @endif
                        @endif
                        @if(!empty($bitacora->metadata) && isset($bitacora->metadata['motivo']))
                            <div class="mt-2 rounded border border-blue-100 bg-blue-50 p-2 text-[11px] text-gray-700">
                                <p><strong>Motivo:</strong> {{ $bitacora->metadata['motivo'] }}</p>
                                @if(!empty($bitacora->metadata['autorizada_por']))
                                    <p class="mt-1"><strong>Autorizado por:</strong> {{ $bitacora->metadata['autorizada_por'] }}</p>
                                @endif
                            </div>
                        @endif
                        @if(!empty($bitacora->metadata['cambios']))
                            <div class="mt-2 space-y-2">
                                @foreach($bitacora->metadata['cambios'] as $cambio)
                                    <div class="rounded border border-gray-200 bg-gray-50 p-2 text-[11px]">
                                        <p class="font-bold text-gray-800">{{ $cambio['campo'] ?? 'Campo' }}</p>
                                        <p><span class="font-semibold">Anterior:</span> {{ ($cambio['anterior'] ?? null) !== null && ($cambio['anterior'] ?? '') !== '' ? $cambio['anterior'] : 'Sin valor' }}</p>
                                        <p><span class="font-semibold">Nuevo:</span> {{ ($cambio['nuevo'] ?? null) !== null && ($cambio['nuevo'] ?? '') !== '' ? $cambio['nuevo'] : 'Sin valor' }}</p>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(!empty($bitacora->metadata['campos']) && isset($bitacora->metadata['anterior'], $bitacora->metadata['nuevo']))
                            <div class="mt-2 space-y-2">
                                @foreach($bitacora->metadata['campos'] as $campo)
                                    <div class="rounded border border-gray-200 bg-gray-50 p-2 text-[11px]">
                                        <p class="font-bold text-gray-800">{{ ucfirst(str_replace('_', ' ', $campo)) }}</p>
                                        <p><span class="font-semibold">Anterior:</span> {{ $bitacora->metadata['anterior'][$campo] ?? 'Sin valor' }}</p>
                                        <p><span class="font-semibold">Nuevo:</span> {{ $bitacora->metadata['nuevo'][$campo] ?? 'Sin valor' }}</p>
                                    </div>
                                @endforeach
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
            <div id="content-mensajes" class="flex-1 min-h-[430px] flex-col {{ $verBitacora ? 'hidden' : 'flex' }} bg-white relative">
                <div class="flex flex-1 min-h-0 flex-col sm:flex-row">
                    <aside class="sm:w-32 shrink-0 border-b sm:border-b-0 sm:border-r border-gray-200 bg-gray-50 p-2 overflow-x-auto sm:overflow-y-auto">
                        <p class="px-2 pt-1 pb-2 text-[10px] font-bold uppercase tracking-wide text-gray-400">General</p>
                        <button type="button" data-chat-type="general" class="chat-conversation-button w-full flex items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-xs {{ $tipoChat === 'general' ? 'bg-white font-bold text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-white' }}">
                            <span class="truncate">Chat General</span>
                            <span data-chat-badge="general" class="{{ ($conteosChat['general'] ?? 0) > 0 ? '' : 'hidden' }} rounded-full bg-[#b11226] px-1.5 py-0.5 text-[10px] text-white">{{ $conteosChat['general'] ?? 0 }}</span>
                        </button>

                        <p class="px-2 pt-4 pb-2 text-[10px] font-bold uppercase tracking-wide text-gray-400">Directos</p>
                        <div class="flex sm:block gap-1">
                            @forelse($destinatariosChat as $usuarioChat)
                                @php $directosSinLeer = $conteosChat['directos'][$usuarioChat->id] ?? 0; @endphp
                                <button type="button" data-chat-type="directo" data-chat-user="{{ $usuarioChat->id }}" data-chat-name="{{ $usuarioChat->name }}" class="chat-conversation-button min-w-28 sm:min-w-0 w-full flex items-center justify-between gap-2 rounded-md px-2 py-2 text-left text-xs {{ $tipoChat === 'directo' && $interlocutorId === $usuarioChat->id ? 'bg-white font-bold text-gray-900 shadow-sm' : 'text-gray-600 hover:bg-white' }}">
                                    <span class="truncate">{{ $usuarioChat->name }}</span>
                                    <span data-chat-badge="directo-{{ $usuarioChat->id }}" class="{{ $directosSinLeer > 0 ? '' : 'hidden' }} rounded-full bg-[#b11226] px-1.5 py-0.5 text-[10px] text-white">{{ $directosSinLeer }}</span>
                                </button>
                            @empty
                                <p class="px-2 text-[11px] text-gray-400">Sin usuarios disponibles.</p>
                            @endforelse
                        </div>
                    </aside>

                    <div class="flex flex-1 min-w-0 flex-col p-4">
                        <div class="mb-3 border-b border-gray-100 pb-2">
                            <p id="active-chat-label" class="text-xs font-bold text-gray-800">
                                {{ $tipoChat === 'general' ? 'Chat General' : 'Chat directo con '.$destinatariosChat->firstWhere('id', $interlocutorId)?->name }}
                            </p>
                        </div>
                        <div class="flex-1 space-y-4 overflow-y-auto mb-4 pr-2 flex flex-col" id="chat-container">
                    @forelse($mensajesChat as $msg)
                        @php $esMio = $msg->user_id === auth()->id(); @endphp
                        <div class="flex flex-col {{ $esMio ? 'items-end' : 'items-start' }}" data-message-id="{{ $msg->id }}">
                            <div class="{{ $esMio ? 'bg-[#b11226] text-white' : 'bg-gray-100 text-gray-800' }} rounded-xl p-3 max-w-[85%] relative shadow-sm">
                                <span class="block text-[11px] font-bold opacity-90 mb-1 {{ $esMio ? 'text-red-100' : 'text-gray-600' }}">
                                    {{ $esMio ? auth()->user()->name : $msg->autor?->name }}
                                </span>
                                @if($msg->destinatario_id)
                                    <span class="block text-[10px] opacity-80 mb-1">Directo para {{ $msg->destinatario?->name }}</span>
                                @endif
                                <p class="text-[13.5px] leading-relaxed">{{ $msg->mensaje }}</p>
                            </div>
                            <span class="text-[10px] text-gray-400 mt-1 mx-1">{{ \App\Support\LocalDate::inBogota($msg->created_at)?->locale('es')->translatedFormat('d M, H:i \h') }}</span>
                        </div>
                    @empty
                        <div class="h-full flex items-center justify-center text-sm text-gray-400 italic my-auto">Empieza la conversación en este caso.</div>
                    @endforelse
                        </div>

                        <div class="pt-3 border-t border-gray-100 shrink-0">
                    @unless($esConsultor)
                    <form id="form-chat" action="{{ route('casos.mensajes', $caso->id) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="destinatario_id" id="chat-recipient-id" value="{{ $tipoChat === 'directo' ? $interlocutorId : '' }}">
                        <input type="text" name="mensaje" required placeholder="Escribe un mensaje..." class="flex-1 bg-gray-50 border border-gray-200 text-gray-800 text-sm rounded-lg px-4 py-2.5 focus:outline-none focus:border-red-400 focus:bg-white transition">
                        <button type="submit" class="chat-btn-send text-white rounded-lg transition shrink-0 flex items-center justify-center w-11 h-11">
                            <svg class="icon-lg chat-send-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </button>
                    </form>
                    @else
                        <p class="text-xs text-gray-500 text-center">El rol Consultor puede leer mensajes, pero no enviarlos.</p>
                    @endunless
                        </div>
                    </div>
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
    (() => {
        const applicantType = document.getElementById('correction-applicant-type');
        const documentType = document.getElementById('correction-document-type');
        if (!applicantType || !documentType) return;

        const catalog = @json($tiposDocumento);
        const initialSelection = String(documentType.dataset.selected || '');
        const refreshDocumentTypes = (preserveSelection = true) => {
            const appliesTo = applicantType.value === 'empresa' ? 'juridica' : 'natural';
            const selected = preserveSelection ? (documentType.value || initialSelection) : '';
            documentType.innerHTML = '<option value="">Sin tipo de documento</option>';
            catalog
                .filter(item => item.aplica_a === appliesTo || item.aplica_a === 'ambos')
                .forEach(item => {
                    const option = document.createElement('option');
                    option.value = item.id;
                    option.textContent = `${item.codigo} - ${item.nombre}`;
                    option.selected = String(item.id) === String(selected);
                    documentType.appendChild(option);
                });
        };

        applicantType.addEventListener('change', () => refreshDocumentTypes(false));
        refreshDocumentTypes(true);

        @if(old('motivo_correccion'))
            document.getElementById('modal-corregir-caso')?.showModal();
        @endif
    })();

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
            window.caseChat?.markCurrentRead();
        }
    }

    // Auto-open chat if coming from redirect
    @if(session('tab') === 'mensajes' || request()->query('tab') === 'mensajes')
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
    function appendSafeChatMessage(chatContainer, msg) {
        if (chatContainer.querySelector(`[data-message-id="${msg.id}"]`)) return;

        const wrapper = document.createElement('div');
        wrapper.className = `flex flex-col ${msg.esMio ? 'items-end' : 'items-start'}`;
        wrapper.dataset.messageId = msg.id;

        const bubble = document.createElement('div');
        bubble.className = `${msg.esMio ? 'bg-[#b11226] text-white' : 'bg-gray-100 text-gray-800'} rounded-xl p-3 max-w-[85%] relative shadow-sm`;

        const authorElement = document.createElement('span');
        authorElement.className = `block text-[11px] font-bold opacity-90 mb-1 ${msg.esMio ? 'text-red-100' : 'text-gray-600'}`;
        authorElement.textContent = msg.autor ?? '';

        if (msg.esDirecto) {
            const directElement = document.createElement('span');
            directElement.className = 'block text-[10px] opacity-80 mb-1';
            directElement.textContent = `Directo para ${msg.destinatario ?? 'usuario'}`;
            bubble.append(authorElement, directElement);
        } else {
            bubble.append(authorElement);
        }

        const messageElement = document.createElement('p');
        messageElement.className = 'text-[13.5px] leading-relaxed';
        messageElement.textContent = msg.mensaje ?? '';

        const dateElement = document.createElement('span');
        dateElement.className = 'text-[10px] text-gray-400 mt-1 mx-1';
        dateElement.textContent = msg.fecha ?? '';

        bubble.append(messageElement);
        wrapper.append(bubble, dateElement);
        chatContainer.appendChild(wrapper);
    }
    /* legacy-chat-block-start */
    if (false) {
    const formChat = document.getElementById('form-chat');
    if (formChat) {
        formChat.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const input = this.querySelector('input[name="mensaje"]');
            const destinatario = this.querySelector('select[name="destinatario_id"]');
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
                body: JSON.stringify({
                    mensaje: mensaje,
                    destinatario_id: destinatario?.value || null
                })
            })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    const chatContainer = document.getElementById('chat-container');
                    
                    // Quitar mensaje de "Empieza la conversación" si existe
                    const emptyMsg = chatContainer.querySelector('.italic');
                    if(emptyMsg) emptyMsg.remove();
                    appendSafeChatMessage(chatContainer, data.mensaje);
                    window.caseChatPoller?.setLastId(data.mensaje.id);
                    
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
    /* legacy-chat-block-end */

    window.caseChatPoller?.stop();

    (() => {
        const chatContainer = document.getElementById('chat-container');
        const contentMensajes = document.getElementById('content-mensajes');
        if (!chatContainer || !contentMensajes) return;

        let lastId = Math.max(0, ...Array.from(chatContainer.querySelectorAll('[data-message-id]'))
            .map(element => Number(element.dataset.messageId) || 0));
        let timer = null;
        let stopped = false;

        const schedule = () => {
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(poll, 3000);
        };

        const poll = async () => {
            if (stopped || document.visibilityState !== 'visible' || contentMensajes.classList.contains('hidden')) {
                schedule();
                return;
            }

            try {
                const response = await fetch('{{ route('casos.mensajes.json', $caso->id, false) }}?after_id=' + lastId, {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                const data = await response.json();
                const nearBottom = chatContainer.scrollHeight - chatContainer.scrollTop - chatContainer.clientHeight < 80;

                for (const mensaje of data.mensajes ?? []) {
                    chatContainer.querySelector('.italic')?.remove();
                    appendSafeChatMessage(chatContainer, mensaje);
                    lastId = Math.max(lastId, Number(mensaje.id) || 0);
                }

                if (nearBottom && (data.mensajes?.length ?? 0) > 0) {
                    chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Error actualizando mensajes:', error);
            } finally {
                schedule();
            }
        };

        window.caseChatPoller = {
            stop() { stopped = true; clearTimeout(timer); },
            setLastId(id) { lastId = Math.max(lastId, Number(id) || 0); },
            poll,
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') poll();
        }, { once: false });
        window.addEventListener('pagehide', () => window.caseChatPoller?.stop(), { once: true });
        schedule();
    })();

    }

    window.caseChatPoller?.stop();

    (() => {
        const chatContainer = document.getElementById('chat-container');
        const contentMensajes = document.getElementById('content-mensajes');
        const recipientInput = document.getElementById('chat-recipient-id');
        const activeLabel = document.getElementById('active-chat-label');
        if (!chatContainer || !contentMensajes) return;

        let activeType = @json($tipoChat);
        let activeUser = @json($interlocutorId);
        let activeName = @json($tipoChat === 'directo' ? $destinatariosChat->firstWhere('id', $interlocutorId)?->name : null);
        let lastId = Math.max(0, ...Array.from(chatContainer.querySelectorAll('[data-message-id]'))
            .map(element => Number(element.dataset.messageId) || 0));
        let timer = null;
        let stopped = false;
        let inFlight = false;

        const query = (afterId = 0) => {
            const params = new URLSearchParams({ after_id: String(afterId), chat: activeType });
            if (activeType === 'directo' && activeUser) params.set('usuario', String(activeUser));
            return params.toString();
        };

        const updateCounts = (counts = {}) => {
            const general = Number(counts.general ?? 0);
            const generalBadge = document.querySelector('[data-chat-badge="general"]');
            if (generalBadge) {
                generalBadge.textContent = general;
                generalBadge.classList.toggle('hidden', general === 0);
            }
            document.querySelectorAll('[data-chat-badge^="directo-"]').forEach(badge => {
                const userId = badge.dataset.chatBadge.replace('directo-', '');
                const count = Number(counts.directos?.[userId] ?? 0);
                badge.textContent = count;
                badge.classList.toggle('hidden', count === 0);
            });
        };

        const updateActiveUI = () => {
            document.querySelectorAll('.chat-conversation-button').forEach(button => {
                const selected = button.dataset.chatType === activeType
                    && (activeType === 'general' || Number(button.dataset.chatUser) === Number(activeUser));
                button.classList.toggle('bg-white', selected);
                button.classList.toggle('font-bold', selected);
                button.classList.toggle('text-gray-900', selected);
                button.classList.toggle('shadow-sm', selected);
                button.classList.toggle('text-gray-600', !selected);
            });
            if (recipientInput) recipientInput.value = activeType === 'directo' ? activeUser : '';
            if (activeLabel) activeLabel.textContent = activeType === 'general'
                ? 'Chat General'
                : `Chat directo con ${activeName ?? 'usuario'}`;
        };

        const markCurrentRead = async () => {
            if (document.visibilityState !== 'visible' || contentMensajes.classList.contains('hidden')) return;
            const body = new FormData();
            body.append('chat', activeType);
            if (activeType === 'directo' && activeUser) body.append('usuario', activeUser);

            try {
                const response = await fetch('{{ route('casos.mensajes.leidos', $caso->id, false) }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body,
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                updateCounts(data.conteos);
                window.notificationPoller?.poll();
            } catch (error) {
                console.error('Error marcando la conversación como leída:', error);
            }
        };

        const loadConversation = async () => {
            const response = await fetch('{{ route('casos.mensajes.json', $caso->id, false) }}?' + query(0), {
                headers: { 'Accept': 'application/json' },
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const data = await response.json();
            chatContainer.replaceChildren();
            lastId = 0;
            for (const mensaje of data.mensajes ?? []) {
                appendSafeChatMessage(chatContainer, mensaje);
                lastId = Math.max(lastId, Number(mensaje.id) || 0);
            }
            if ((data.mensajes?.length ?? 0) === 0) {
                const empty = document.createElement('div');
                empty.className = 'h-full flex items-center justify-center text-sm text-gray-400 italic my-auto';
                empty.textContent = 'Empieza esta conversación.';
                chatContainer.appendChild(empty);
            }
            updateCounts(data.conteos);
            chatContainer.scrollTop = chatContainer.scrollHeight;
            await markCurrentRead();
        };

        document.querySelectorAll('.chat-conversation-button').forEach(button => {
            button.addEventListener('click', async () => {
                activeType = button.dataset.chatType;
                activeUser = activeType === 'directo' ? Number(button.dataset.chatUser) : null;
                activeName = activeType === 'directo' ? button.dataset.chatName : null;
                updateActiveUI();
                try {
                    await loadConversation();
                } catch (error) {
                    console.error('Error cargando la conversación:', error);
                }
            });
        });

        document.getElementById('form-chat')?.addEventListener('submit', async function (event) {
            event.preventDefault();
            const input = this.querySelector('input[name="mensaje"]');
            const button = this.querySelector('button[type="submit"]');
            const message = input.value.trim();
            if (!message || button.disabled) return;
            button.disabled = true;
            button.style.opacity = '0.5';

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        mensaje: message,
                        destinatario_id: activeType === 'directo' ? activeUser : null,
                    }),
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                chatContainer.querySelector('.italic')?.remove();
                appendSafeChatMessage(chatContainer, data.mensaje);
                lastId = Math.max(lastId, Number(data.mensaje.id) || 0);
                input.value = '';
                chatContainer.scrollTop = chatContainer.scrollHeight;
            } catch (error) {
                console.error('Error enviando mensaje:', error);
                alert('Ocurrió un error al enviar el mensaje.');
            } finally {
                button.disabled = false;
                button.style.opacity = '1';
                input.focus();
            }
        });

        const schedule = () => {
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(poll, 3000);
        };

        const poll = async () => {
            if (stopped || inFlight || document.visibilityState !== 'visible' || contentMensajes.classList.contains('hidden')) {
                schedule();
                return;
            }
            inFlight = true;
            try {
                const response = await fetch('{{ route('casos.mensajes.json', $caso->id, false) }}?' + query(lastId), {
                    headers: { 'Accept': 'application/json' },
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                const nearBottom = chatContainer.scrollHeight - chatContainer.scrollTop - chatContainer.clientHeight < 80;
                for (const mensaje of data.mensajes ?? []) {
                    chatContainer.querySelector('.italic')?.remove();
                    appendSafeChatMessage(chatContainer, mensaje);
                    lastId = Math.max(lastId, Number(mensaje.id) || 0);
                }
                updateCounts(data.conteos);
                if ((data.mensajes?.length ?? 0) > 0) {
                    await markCurrentRead();
                    if (nearBottom) chatContainer.scrollTop = chatContainer.scrollHeight;
                }
            } catch (error) {
                console.error('Error actualizando mensajes:', error);
            } finally {
                inFlight = false;
                schedule();
            }
        };

        window.caseChat = { markCurrentRead };
        window.caseChatPoller = {
            stop() { stopped = true; clearTimeout(timer); },
            setLastId(id) { lastId = Math.max(lastId, Number(id) || 0); },
            poll,
        };

        updateActiveUI();
        if (!contentMensajes.classList.contains('hidden')) markCurrentRead();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                poll();
                markCurrentRead();
            }
        });
        window.addEventListener('pagehide', () => window.caseChatPoller?.stop(), { once: true });
        schedule();
    })();

    window.caseStatePoller?.stop();

    (() => {
        let timer = null;
        let stopped = false;
        let inFlight = false;

        const schedule = () => {
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(poll, 4000);
        };

        const applyState = data => {
            const badge = document.getElementById('case-status-badge');
            const badgeStyles = {
                'En proceso': ['bg-blue-100', 'text-blue-700'],
                'Completado': ['bg-green-100', 'text-green-700'],
                'Finalizado': ['bg-red-100', 'text-red-700'],
                'Pendiente': ['bg-gray-100', 'text-gray-700'],
            };

            if (badge) {
                badge.textContent = data.estado ?? '';
                badge.classList.remove('bg-blue-100', 'text-blue-700', 'bg-green-100', 'text-green-700', 'bg-red-100', 'text-red-700', 'bg-gray-100', 'text-gray-700');
                badge.classList.add(...(badgeStyles[data.estado] ?? badgeStyles.Pendiente));
            }

            const progress = document.getElementById('case-progress');
            if (progress) {
                progress.textContent = `Progreso: ${data.progreso ?? 0}% (${data.tareas_completadas ?? 0}/${data.tareas_total ?? 0} completados)`;
            }

            const ansStatus = document.getElementById('case-ans-status');
            const ansRemaining = document.getElementById('case-ans-remaining');
            if (ansStatus && data.ans) {
                const ansState = data.ans.estado ?? 'vigente';
                const ansStyles = {
                    vigente: ['bg-blue-100', 'text-blue-700'],
                    preventivo: ['bg-yellow-100', 'text-yellow-700'],
                    critico: ['bg-orange-100', 'text-orange-700'],
                    vencido: ['bg-red-100', 'text-red-700'],
                    incumplido: ['bg-red-100', 'text-red-700'],
                    cumplido: ['bg-green-100', 'text-green-700'],
                };
                ansStatus.textContent = ansState.replace(/^./, value => value.toUpperCase());
                ansStatus.classList.remove(
                    'bg-blue-100', 'text-blue-700',
                    'bg-yellow-100', 'text-yellow-700',
                    'bg-orange-100', 'text-orange-700',
                    'bg-red-100', 'text-red-700',
                    'bg-green-100', 'text-green-700',
                );
                ansStatus.classList.add(...(ansStyles[ansState] ?? ansStyles.vigente));
            }
            if (ansRemaining && data.ans?.dias_restantes !== null) {
                const days = Number(data.ans.dias_restantes);
                ansRemaining.textContent = days >= 0
                    ? days + ' días restantes'
                    : Math.abs(days) + ' días de retraso';
            }

            for (const tarea of data.tareas ?? []) {
                const rows = document.querySelectorAll(`[data-task-id="${Number(tarea.id)}"]`);
                rows.forEach(row => {
                    row.querySelectorAll('[data-task-status]').forEach(element => {
                        element.textContent = tarea.estado ?? '';
                    });
                    if (tarea.estado === 'Completada') {
                        row.querySelectorAll('form[data-task-completion-form] button[type="submit"]').forEach(button => {
                            button.disabled = true;
                        });
                    }
                    const correction = row.querySelector('[data-task-correction]');
                    if (correction && typeof tarea.correccion_html === 'string' && correction.dataset.correctionKey !== tarea.correccion_key) {
                        correction.innerHTML = tarea.correccion_html;
                        correction.dataset.correctionKey = tarea.correccion_key;
                    }
                });
            }
            lucide.createIcons();

            const finalizeForm = document.getElementById('case-finalize-form');
            const finalizeButton = document.getElementById('case-finalize-button');
            const finalizeLabel = document.getElementById('case-finalize-label');
            if (finalizeForm && finalizeButton) {
                const canFinalize = Boolean(data.puede_finalizar);
                finalizeForm.classList.toggle('hidden', data.estado === 'Finalizado');
                finalizeButton.disabled = !canFinalize;
                finalizeButton.classList.toggle('bg-[#c8828b]', canFinalize);
                finalizeButton.classList.toggle('hover:bg-[#b11226]', canFinalize);
                finalizeButton.classList.toggle('text-white', canFinalize);
                finalizeButton.classList.toggle('bg-gray-200', !canFinalize);
                finalizeButton.classList.toggle('text-gray-500', !canFinalize);
                finalizeButton.classList.toggle('cursor-not-allowed', !canFinalize);
                finalizeButton.classList.toggle('opacity-75', !canFinalize);
                finalizeButton.title = canFinalize
                    ? 'Finalizar Caso'
                    : 'Debe haber usuarios asignados y todas sus tareas deben estar completadas';
                if (finalizeLabel) finalizeLabel.textContent = 'Finalizar Caso';
            }
        };

        const poll = async () => {
            if (stopped || inFlight || document.visibilityState !== 'visible') {
                schedule();
                return;
            }

            inFlight = true;
            try {
                const response = await fetch('{{ route('casos.estado', $caso->id, false) }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                applyState(await response.json());
            } catch (error) {
                console.error('Error actualizando el estado del caso:', error);
            } finally {
                inFlight = false;
                schedule();
            }
        };

        window.caseStatePoller = {
            stop() { stopped = true; clearTimeout(timer); },
            poll,
        };

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') poll();
        });
        window.addEventListener('pagehide', () => window.caseStatePoller?.stop(), { once: true });
        schedule();
    })();

    const enfocarTareaDesdeHash = () => {
        if (!window.location.hash.startsWith('#tarea-')) return;
        const tarea = document.querySelector(window.location.hash);
        if (!tarea) return;
        tarea.scrollIntoView({ behavior: 'smooth', block: 'center' });
        tarea.classList.add('ring-2', 'ring-amber-400', 'ring-offset-2');
        window.setTimeout(() => tarea.classList.remove('ring-2', 'ring-amber-400', 'ring-offset-2'), 3500);
    };
    window.addEventListener('hashchange', enfocarTareaDesdeHash);
    window.setTimeout(enfocarTareaDesdeHash, 150);
</script>
@endpush

@endsection
