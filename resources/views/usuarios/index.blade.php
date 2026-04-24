@extends('layouts.app')

@section('title', 'Gestión de Usuarios - Sistema Jurídico')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Usuarios del Sistema</h1>
            <p class="text-gray-500 text-sm mt-1">Gestiona los accesos y roles de los usuarios</p>
        </div>
        <a href="{{ route('usuarios.crear') }}" class="btn-primary">
            <i data-lucide="user-plus" style="width:16px;height:16px;"></i>
            Crear Usuario
        </a>
    </div>
</div>

<!-- FILTROS Y BÚSQUEDA -->
<div class="bg-white p-4 rounded-lg border border-gray-200 mb-6 flex items-center gap-4">
    <form action="{{ route('usuarios.index') }}" method="GET" class="flex-1 flex gap-4">
        
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400" style="width:18px;height:18px;"></i>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Buscar por nombre, correo o área..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md bg-white text-sm focus:border-red-500 outline-none transition">
        </div>
        
        <select name="estado" class="w-48 px-3 py-2 border border-gray-300 rounded-md bg-white text-sm focus:border-red-500 outline-none transition" onchange="this.form.submit()">
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
                    <th class="px-6 py-4 text-right">Acciones</th>
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
                                'Juridica'         => 'bg-gray-200 text-gray-700',
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
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('usuarios.editar', $user->id) }}" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded transition" title="Editar">
                                <i data-lucide="edit-2" style="width:16px;height:16px;"></i>
                            </a>
                            
                            @if(auth()->id() !== $user->id)
                            <form action="{{ route('usuarios.estado', $user->id) }}" method="POST" class="inline-block" onsubmit="return confirm('¿Seguro que deseas cambiar el estado de este usuario?');">
                                @csrf
                                <button type="submit" class="p-1.5 text-gray-400 {{ $user->activo ? 'hover:text-red-600 hover:bg-red-50' : 'hover:text-green-600 hover:bg-green-50' }} rounded transition" title="{{ $user->activo ? 'Desactivar' : 'Activar' }}">
                                    <i data-lucide="power" style="width:16px;height:16px;"></i>
                                </button>
                            </form>
                            @endif
                        </div>
                    </td>
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
        <span class="hidden md:inline">Administradores: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Administrador'))->count() }}</span>
        <span class="hidden md:inline">|</span>
        <span class="hidden md:inline">Usuarios Principales: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Usuario Principal'))->count() }}</span>
        <span class="hidden md:inline">|</span>
        <span class="hidden md:inline">Usuarios: {{ \App\Models\User::whereHas('role', fn($q) => $q->where('nombre', 'Usuario'))->count() }}</span>
    </div>
    <div>
        {{ $usuarios->links() }}
    </div>
</div>

@endsection
