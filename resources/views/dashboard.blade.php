@extends('layouts.app')

@section('title', 'Dashboard - Sistema Jurídico')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}">
@endpush

@section('content')

{{-- Encabezado de página --}}
<div class="page-header">
    <h1>Dashboard</h1>
    <p>Vista general de casos jurídicos</p>
</div>

{{-- TARJETAS DE ESTADÍSTICAS --}}
<div class="dashboard-stats-grid">

    {{-- Total Casos --}}
    <div class="stat-card">
        <div>
            <p class="stat-label">Total Casos</p>
            <h2 class="stat-value total">{{ $totalCasos }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper">
            <i data-lucide="folder" class="stat-icon-svg total"></i>
        </div>
    </div>

    {{-- En Proceso --}}
    <div class="stat-card">
        <div>
            <p class="stat-label">En Proceso</p>
            <h2 class="stat-value proceso">{{ $enProceso }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper proceso">
            <i data-lucide="clock" class="stat-icon-svg proceso"></i>
        </div>
    </div>

    {{-- Completados --}}
    <div class="stat-card">
        <div>
            <p class="stat-label">Completados</p>
            <h2 class="stat-value completado">{{ $completados }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper completado">
            <i data-lucide="check-circle" class="stat-icon-svg completado"></i>
        </div>
    </div>

    {{-- Finalizados --}}
    <div class="stat-card">
        <div>
            <p class="stat-label">Finalizados</p>
            <h2 class="stat-value finalizado">{{ $finalizados }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper finalizado">
            <i data-lucide="flag" class="stat-icon-svg finalizado"></i>
        </div>
    </div>

</div>

{{-- FILA: TABLA DE CASOS + MIS TAREAS --}}
<div style="display: block;">

        {{-- ── Casos Recientes ────────────────────────────────────── --}}
    <div class="recent-cases-wrapper">

        {{-- Header tabla --}}
        <div class="recent-cases-header">
            <h2 class="recent-cases-title">Casos Recientes</h2>
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary btn-create-sm">
                <i data-lucide="plus" class="btn-create-icon"></i>
                Crear Nuevo Caso
            </a>
            @endif
        </div>

        {{-- Tabla --}}
        @if($casosRecientes->isNotEmpty())
        <div class="table-responsive">
            <table class="tabla-casos">
                <thead>
                    <tr>
                        <th>Radicado</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Estado</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($casosRecientes as $caso)
                    <tr>
                        <td>
                            <span class="radicado-link">{{ $caso->radicado }}</span>
                        </td>
                        <td>
                            <span class="tipo-link">{{ $caso->tipo?->nombre ?? '—' }}</span>
                        </td>
                        <td class="td-desc">
                            <span class="desc-truncate">
                                {{ $caso->descripcion }}
                            </span>
                        </td>
                        <td>
                            @php
                                $badgeClass = match($caso->estado) {
                                    'En proceso'  => 'badge-proceso',
                                    'Completado'  => 'badge-completado',
                                    'Finalizado'  => 'badge-finalizado',
                                    default       => 'badge-pendiente',
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $caso->estado }}</span>
                        </td>
                        <td class="td-date">
                            {{ $caso->created_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <a href="{{ route('casos.show', $caso->id) }}" class="btn-ver">Ver</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="empty-state">
            <i data-lucide="folder-open" class="empty-state-icon"></i>
            <p class="empty-state-text">No hay casos registrados aún.</p>
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary empty-state-btn">
                Crear el primer caso
            </a>
            @endif
        </div>
        @endif

    </div>



</div>

@endsection