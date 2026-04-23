@extends('layouts.app')

@section('title', 'Todos los Casos - Sistema Jurídico')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Todos los Casos</h1>
            <p class="text-gray-500 text-sm mt-1">Gestiona y revisa todos los casos jurídicos</p>
        </div>
        @if($esAdmin)
        <a href="{{ route('casos.crear') }}" class="btn-primary">
            <i data-lucide="plus-circle" style="width:16px;height:16px;"></i>
            Crear Nuevo Caso
        </a>
        @endif
    </div>
</div>

<!-- FILTROS Y BÚSQUEDA -->
<div class="bg-white p-4 rounded-lg border border-gray-200 mb-6 flex items-center gap-4">
    <form action="{{ route('casos.index') }}" method="GET" class="flex-1 flex gap-4">
        
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400" style="width:18px;height:18px;"></i>
            <input type="text" name="search" value="{{ request('search') }}" 
                   placeholder="Buscar por radicado, descripción, nombre o documento..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
        </div>
        
        <select name="estado" class="w-48 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition" onchange="this.form.submit()">
            <option value="Todos" {{ request('estado') === 'Todos' ? 'selected' : '' }}>Todos</option>
            <option value="Pendiente" {{ request('estado') === 'Pendiente' ? 'selected' : '' }}>Pendiente</option>
            <option value="En proceso" {{ request('estado') === 'En proceso' ? 'selected' : '' }}>En Proceso</option>
            <option value="Completado" {{ request('estado') === 'Completado' ? 'selected' : '' }}>Completado</option>
            <option value="Finalizado" {{ request('estado') === 'Finalizado' ? 'selected' : '' }}>Finalizado</option>
        </select>
        
    </form>
</div>

<!-- LISTA DE CASOS -->
<div class="space-y-4">
    @forelse($casos as $caso)
    <div class="bg-white border border-gray-200 rounded-lg p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 transition hover:border-gray-300 hover:shadow-sm">
        
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <h3 class="text-base font-bold text-gray-900">{{ $caso->radicado }}</h3>
                
                @php
                    $badgeClass = match($caso->estado) {
                        'En proceso'  => 'bg-blue-100 text-blue-700',
                        'Completado'  => 'bg-green-100 text-green-700',
                        'Finalizado'  => 'bg-red-100 text-red-700',
                        default       => 'bg-gray-100 text-gray-700',
                    };
                @endphp
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide {{ $badgeClass }}">
                    {{ $caso->estado }}
                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-3 max-w-4xl truncate">
                {{ $caso->descripcion }}
            </p>
            
            <div class="flex items-center gap-4 text-xs text-gray-500 font-medium">
                <span>Tipo: <span class="text-gray-700">{{ $caso->tipo?->nombre ?? 'N/A' }}</span></span>
                <span class="text-gray-300">•</span>
                <span>Creado: <span class="text-gray-700">{{ $caso->created_at->format('d/m/Y') }}</span></span>
                <span class="text-gray-300">•</span>
                <span><span class="text-gray-700">{{ $caso->usuarios->count() }}</span> usuario(s) asignado(s)</span>
            </div>
        </div>
        
        <div class="flex-shrink-0">
            <a href="{{ route('casos.show', $caso->id) }}" class="btn-secondary">
                Ver Detalles
            </a>
        </div>
        
    </div>
    @empty
    <div class="bg-white border border-gray-200 rounded-lg p-12 text-center text-gray-500">
        <i data-lucide="folder-open" class="mx-auto mb-3 text-gray-300" style="width:40px;height:40px;"></i>
        <p class="text-sm">No se encontraron casos que coincidan con la búsqueda.</p>
    </div>
    @endforelse
</div>

<div class="mt-6">
    {{ $casos->links() }}
</div>

@endsection
