@extends('layouts.app')

@section('title', 'Dashboard - Sistema Jurídico')

@section('content')

{{-- Encabezado de página --}}
<div class="page-header">
    <h1>Dashboard</h1>
    <p>Vista general de casos jurídicos</p>
</div>

{{-- ══════════════════════════════════════════════════════════════
     TARJETAS DE ESTADÍSTICAS
══════════════════════════════════════════════════════════════ --}}
<div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px;">

    {{-- Total Casos --}}
    <div class="stat-card">
        <div>
            <p style="font-size:12px;color:#6b7280;font-weight:500;margin:0 0 6px;">Total Casos</p>
            <h2 style="font-size:28px;font-weight:700;color:#111827;margin:0;">{{ $totalCasos }}</h2>
        </div>
        <div class="stat-icon" style="background:#f3f4f6;">
            <i data-lucide="folder" style="width:22px;height:22px;color:#6b7280;"></i>
        </div>
    </div>

    {{-- En Proceso --}}
    <div class="stat-card">
        <div>
            <p style="font-size:12px;color:#6b7280;font-weight:500;margin:0 0 6px;">En Proceso</p>
            <h2 style="font-size:28px;font-weight:700;color:#2563eb;margin:0;">{{ $enProceso }}</h2>
        </div>
        <div class="stat-icon" style="background:#dbeafe;">
            <i data-lucide="clock" style="width:22px;height:22px;color:#2563eb;"></i>
        </div>
    </div>

    {{-- Completados --}}
    <div class="stat-card">
        <div>
            <p style="font-size:12px;color:#6b7280;font-weight:500;margin:0 0 6px;">Completados</p>
            <h2 style="font-size:28px;font-weight:700;color:#16a34a;margin:0;">{{ $completados }}</h2>
        </div>
        <div class="stat-icon" style="background:#dcfce7;">
            <i data-lucide="check-circle" style="width:22px;height:22px;color:#16a34a;"></i>
        </div>
    </div>

    {{-- Finalizados --}}
    <div class="stat-card">
        <div>
            <p style="font-size:12px;color:#6b7280;font-weight:500;margin:0 0 6px;">Finalizados</p>
            <h2 style="font-size:28px;font-weight:700;color:#b11226;margin:0;">{{ $finalizados }}</h2>
        </div>
        <div class="stat-icon" style="background:#fce7f3;">
            <i data-lucide="flag" style="width:22px;height:22px;color:#b11226;"></i>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     FILA: TABLA DE CASOS + MIS TAREAS
══════════════════════════════════════════════════════════════ --}}
<div style="display: block;">

    {{-- ── Casos Recientes ────────────────────────────────────── --}}
    <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;overflow:hidden;">

        {{-- Header tabla --}}
        <div style="padding:18px 22px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid #f5f5f5;">
            <h2 style="font-size:14px;font-weight:700;color:#111827;margin:0;">Casos Recientes</h2>
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary" style="font-size:12.5px;padding:7px 14px;">
                <i data-lucide="plus" style="width:15px;height:15px;"></i>
                Crear Nuevo Caso
            </a>
            @endif
        </div>

        {{-- Tabla --}}
        @if($casosRecientes->isNotEmpty())
        <div style="overflow-x:auto;">
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
                        <td style="max-width:260px;">
                            <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:260px;color:#374151;">
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
                        <td style="color:#6b7280;font-size:12.5px;">
                            {{ $caso->created_at->format('d/m/Y') }}
                        </td>
                        <td>
                            <a href="{{ route('tareas.index', $caso->id) }}" class="btn-ver">Ver</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div style="padding:40px;text-align:center;">
            <i data-lucide="folder-open" style="width:40px;height:40px;color:#d1d5db;margin:0 auto 12px;display:block;"></i>
            <p style="color:#9ca3af;font-size:13.5px;margin:0;">No hay casos registrados aún.</p>
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary" style="margin-top:16px;display:inline-flex;">
                Crear el primer caso
            </a>
            @endif
        </div>
        @endif

    </div>



</div>

@endsection