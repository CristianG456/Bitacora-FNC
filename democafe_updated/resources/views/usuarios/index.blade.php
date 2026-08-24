@extends('layouts.app')

@section('title', 'Gestión de Usuarios - Sistema Jurídico')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between">
        <div class="mb-3 sm:mb-0">
            <h1 class="text-2xl font-bold text-gray-900">Usuarios del Sistema</h1>
            <p class="text-gray-500 text-sm mt-1">Gestiona los accesos y roles de los usuarios</p>
        </div>
        @if(auth()->user()->tieneRol('Administrador'))
        <a href="{{ route('usuarios.crear') }}" class="btn-primary w-full sm:w-auto justify-center">
            <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
            Crear Usuario
        </a>
        @endif
    </div>
</div>

@if(auth()->user()->tieneRol('Administrador') && isset($solicitudesRecuperacion) && $solicitudesRecuperacion->count() > 0)
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-r-lg">
    <div class="flex items-start">
        <div class="flex-shrink-0">
            <i data-lucide="alert-triangle" class="h-5 w-5 text-yellow-400"></i>
        </div>
        <div class="ml-3">
            <h3 class="text-sm font-medium text-yellow-800">
                Solicitudes de recuperación de contraseña (Últimas 48h)
            </h3>
            <div class="mt-2 text-sm text-yellow-700">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($solicitudesRecuperacion as $solicitud)
                        <li>{{ $solicitud->email }} solicitó restablecer contraseña el {{ \Carbon\Carbon::parse($solicitud->created_at)->format('d/m/Y H:i') }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endif

<!-- FILTROS Y BÚSQUEDA -->
<div class="bg-white p-4 rounded-lg border border-gray-200 mb-6 flex flex-col gap-4">
    <form action="{{ route('usuarios.index') }}" method="GET" class="flex-1 flex flex-col sm:flex-row gap-4">
        
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400" style="width:18px;height:18px;"></i>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Buscar por nombre, correo o área..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md bg-white text-sm focus:border-red-500 outline-none transition">
        </div>
        
        <select name="estado" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:border-red-500 outline-none transition" onchange="this.form.submit()">
            <option value="Todos" {{ request('estado') === 'Todos' ? 'selected' : '' }}>Todos los estados</option>
            <option value="Activos" {{ request('estado') === 'Activos' ? 'selected' : '' }}>Activos</option>
            <option value="Inactivos" {{ request('estado') === 'Inactivos' ? 'selected' : '' }}>Inactivos</option>
        </select>
        
    </form>
</div>

<!-- LISTA DE USUARIOS -->
<div class="bg-white border border-gray-200 rounded-xl overflow-hidden shadow-sm">
    @if($usuarios->isNotEmpty())
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm text-gray-700">
            <thead class="bg-white text-[13px] font-bold text-gray-900 border-b border-gray-200">
                <tr>
                    <th class="px-6 py-4">Nombre</th>
                    <th class="px-6 py-4">Correo</th>
                    <th class="px-6 py-4">Área</th>
                    <th class="px-6 py-4">Rol</th>
                    <th class="px-6 py-4">Estado</th>
                    @if(auth()->user()->tieneRol('Administrador'))
                    <th class="px-6 py-4 text-right">Acciones</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($usuarios as $user)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-6 py-4 font-medium text-gray-900">
                        {{ $user->name }}
                    </td>
                    <td class="px-6 py-4 text-gray-500">
                        {{ $user->email }}
                    </td>
                    <td class="px-6 py-4 text-gray-600 flex items-center gap-2 mt-1">
                        @if($user->area)
                            <i data-lucide="building-2" style="width:16px;height:16px;color:#9ca3af;"></i>
                            {{ $user->area }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-6 py-4">
                        @php
                            $rolNombre = $user->role?->nombre ?? 'Sin Rol';
                            $rolBadgeClass = match($rolNombre) {
                                'Administrador'    => 'bg-red-700 text-white',
                                'Juridica'         => 'bg-blue-400 text-white',
                                'Usuario Principal'=> 'bg-blue-500 text-white',
                                'Usuario'          => 'bg-gray-500 text-white',
                                'Consultor'        => 'bg-orange-500 text-white',
                                default            => 'bg-gray-200 text-gray-700',
                            };
                        @endphp
                        <span class="px-3 py-1 rounded-full text-xs font-semibold tracking-wide {{ $rolBadgeClass }}">
                            {{ $rolNombre }}
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        @if($user->activo)
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-green-500 text-white">
                                activo
                            </span>
                        @else
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-400 text-white">
                                inactivo
                            </span>
                        @endif
                    </td>
                    @if(auth()->user()->tieneRol('Administrador'))
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('usuarios.editar', $user->id) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Editar">
                                <i data-lucide="edit-2" style="width:16px;height:16px;"></i>
                            </a>
                            
                            @if(auth()->id() !== $user->id)
                            <form action="{{ route('usuarios.eliminar', $user->id) }}" method="POST" class="inline-block" onsubmit="confirmarAccion(event, this, '¿Eliminar usuario?', 'Esta acción no se puede deshacer y el usuario perderá acceso al sistema.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition" title="Eliminar">
                                    <i data-lucide="trash-2" style="width:16px;height:16px;"></i>
                                </button>
                            </form>

                            <form action="{{ route('usuarios.estado', $user->id) }}" method="POST" class="inline-block" onsubmit="confirmarAccion(event, this, '¿Cambiar estado del usuario?', 'Si lo desactivas, no podrá acceder al sistema.');">
                                @csrf
                                <button type="submit" class="p-1.5 text-gray-400 {{ $user->activo ? 'hover:text-orange-600 hover:bg-orange-50' : 'hover:text-green-600 hover:bg-green-50' }} rounded transition" title="{{ $user->activo ? 'Desactivar' : 'Activar' }}">
                                    <i data-lucide="power" style="width:16px;height:16px;"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
                    @endif
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="p-12 text-center text-gray-500">
        <i data-lucide="users" class="mx-auto mb-3 text-gray-300" style="width:40px;height:40px;"></i>
        <p class="text-sm">No se encontraron usuarios.</p>
    </div>
    @endif
</div>

<!-- PAGINACIÓN Y TOTALES -->
<div class="mt-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div class="text-sm text-gray-600 flex items-center gap-4">
        <span class="font-semibold">Total de usuarios: {{ \App\Models\User::count() }}</span>
        <span class="hidden md:inline">|</span>
        @if(auth()->user()->tieneRol('Administrador'))
        <span class="hidden md:inline">Administradores: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Administrador'))->count() }}</span>
        <span class="hidden md:inline">|</span>
        @endif
        <span class="hidden md:inline">Jurídica: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Juridica'))->count() }}</span>
        <span class="hidden md:inline">|</span>
        <span class="hidden md:inline">Usuarios: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Usuario'))->count() }}</span>
        <span class="hidden md:inline">|</span>
        <span class="hidden md:inline">Consultores: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Consultor'))->count() }}</span>
    </div>
    <div>
        {{ $usuarios->links() }}
    </div>
</div>

@endsection
