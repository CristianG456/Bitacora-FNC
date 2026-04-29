@extends('layouts.app')

@section('title', 'Historial Global del Sistema')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/historial.css') }}">
@endpush

@section('content')


<!-- HEADER -->
<div class="page-header flex flex-col sm:flex-row sm:justify-between sm:items-start gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Historial Global</h1>
        <p class="text-sm text-gray-500">Selecciona un caso finalizado para ver su bitácora completa</p>
    </div>
</div>

<!-- FILTROS -->
<div class="filter-card">
    <form method="GET" action="{{ route('historial.index') }}" class="flex flex-col sm:flex-row gap-4">
        <div class="relative flex-1">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <i data-lucide="search" class="text-gray-400" style="width: 16px; height: 16px;"></i>
            </div>
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por radicado, descripción o solicitante..." class="form-input w-full" style="padding-left: 36px;">
        </div>

        <select name="tipo_id" class="form-select w-full sm:w-48" onchange="this.form.submit()">
            <option value="">Todos los tipos</option>
            @foreach($tipos as $tipo)
                <option value="{{ $tipo->id }}" {{ request('tipo_id') == $tipo->id ? 'selected' : '' }}>
                    {{ $tipo->nombre }}
                </option>
            @endforeach
        </select>

        @if(request()->hasAny(['search', 'tipo_id']))
            <a href="{{ route('historial.index') }}" class="btn-secondary justify-center shrink-0">
                <i data-lucide="x" style="width:14px;height:14px;"></i>
                Limpiar
            </a>
        @endif
    </form>
</div>

<!-- CONTADOR -->
<div class="text-sm text-gray-500 mb-4">
    {{ $casos->total() }} caso(s) finalizado(s) encontrado(s)
</div>

<!-- LISTA DE CASOS -->
@if($casos->isEmpty())
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
        <i data-lucide="inbox" class="mx-auto mb-3 text-gray-300" style="width:48px;height:48px;"></i>
        <h3 class="text-base font-semibold text-gray-600">No hay casos finalizados</h3>
        <p class="text-sm text-gray-400 mt-2">Los casos finalizados aparecerán aquí con su bitácora completa.</p>
    </div>
@else
    <div class="space-y-4">
        @foreach($casos as $caso)
        <a href="{{ route('historial.show', $caso->id) }}" class="caso-card">
            <div class="caso-card-header">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="caso-radicado">{{ $caso->radicado }}</span>
                    <span class="caso-tipo-badge">{{ $caso->tipo?->nombre ?? 'Sin tipo' }}</span>
                    <span class="eventos-count">
                        <i data-lucide="scroll-text" style="width:12px;height:12px;"></i>
                        {{ $caso->bitacoras->count() }} eventos
                    </span>
                </div>
                <div class="shrink-0 text-gray-400">
                    <i data-lucide="chevron-right" style="width:20px;height:20px;"></i>
                </div>
            </div>
            
            <p class="caso-desc">{{ $caso->descripcion }}</p>
            
            <div class="caso-meta">
                <div class="caso-meta-item">
                    <i data-lucide="user"></i>
                    {{ $caso->solicitante?->nombre ?? 'N/A' }}
                </div>
                <div class="caso-meta-item">
                    <i data-lucide="file-text"></i>
                    {{ $caso->subtipo?->nombre ?? 'N/A' }}
                </div>
                <div class="caso-meta-item">
                    <i data-lucide="users"></i>
                    {{ $caso->usuarios->count() }} usuario(s)
                </div>
                <div class="caso-meta-item">
                    <i data-lucide="calendar"></i>
                    Finalizado: {{ $caso->updated_at->format('d/m/Y') }}
                </div>
            </div>
        </a>
        @endforeach
    </div>

    <div class="mt-6">
        {{ $casos->links() }}
    </div>
@endif

@endsection
