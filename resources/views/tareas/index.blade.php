@extends('layouts.app')

@section('title', 'Tareas del Caso - ' . $caso->radicado)

@section('content')

{{-- Encabezado --}}
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;">
    <div>
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <a href="{{ route('dashboard') }}"
               style="color:#9ca3af;text-decoration:none;display:flex;align-items:center;">
                <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
            </a>
            <span style="font-size:12px;color:#9ca3af;">Dashboard</span>
        </div>
        <h1>Tareas del Caso</h1>
        <p>
            <span style="font-weight:700;color:#b11226;">{{ $caso->radicado }}</span>
            &nbsp;·&nbsp;
            {{ $caso->tipo?->nombre }} / {{ $caso->subtipo?->nombre }}
            &nbsp;·&nbsp;
            @php
                $badgeClass = match($caso->estado) {
                    'En proceso'  => 'badge-proceso',
                    'Completado'  => 'badge-completado',
                    'Finalizado'  => 'badge-finalizado',
                    default       => 'badge-pendiente',
                };
            @endphp
            <span class="badge {{ $badgeClass }}">{{ $caso->estado }}</span>
        </p>
    </div>

    @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
    <a href="{{ route('tareas.crear', $caso->id) }}" class="btn-primary">
        <i data-lucide="plus" style="width:16px;height:16px;"></i>
        Nueva Tarea
    </a>
    @endif
</div>

{{-- ══════════════════════════════════════════════════════════════
     RESUMEN DE TAREAS
══════════════════════════════════════════════════════════════ --}}
@php
    $totalT      = $tareas->count();
    $pendientesT = $tareas->where('estado', 'Pendiente')->count();
    $enProcesoT  = $tareas->where('estado', 'En proceso')->count();
    $completadasT= $tareas->where('estado', 'Completada')->count();
@endphp

<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px;">

    <div class="stat-card" style="padding:14px 18px;">
        <div>
            <p style="font-size:11px;color:#6b7280;font-weight:600;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">Total</p>
            <h3 style="font-size:22px;font-weight:700;color:#111827;margin:0;">{{ $totalT }}</h3>
        </div>
        <div class="stat-icon" style="background:#f3f4f6;width:36px;height:36px;">
            <i data-lucide="list-checks" style="width:18px;height:18px;color:#6b7280;"></i>
        </div>
    </div>

    <div class="stat-card" style="padding:14px 18px;">
        <div>
            <p style="font-size:11px;color:#6b7280;font-weight:600;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">Pendientes</p>
            <h3 style="font-size:22px;font-weight:700;color:#d97706;margin:0;">{{ $pendientesT }}</h3>
        </div>
        <div class="stat-icon" style="background:#fef9c3;width:36px;height:36px;">
            <i data-lucide="clock-4" style="width:18px;height:18px;color:#d97706;"></i>
        </div>
    </div>

    <div class="stat-card" style="padding:14px 18px;">
        <div>
            <p style="font-size:11px;color:#6b7280;font-weight:600;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">En Proceso</p>
            <h3 style="font-size:22px;font-weight:700;color:#2563eb;margin:0;">{{ $enProcesoT }}</h3>
        </div>
        <div class="stat-icon" style="background:#dbeafe;width:36px;height:36px;">
            <i data-lucide="loader" style="width:18px;height:18px;color:#2563eb;"></i>
        </div>
    </div>

    <div class="stat-card" style="padding:14px 18px;">
        <div>
            <p style="font-size:11px;color:#6b7280;font-weight:600;margin:0 0 4px;text-transform:uppercase;letter-spacing:.05em;">Completadas</p>
            <h3 style="font-size:22px;font-weight:700;color:#16a34a;margin:0;">{{ $completadasT }}</h3>
        </div>
        <div class="stat-icon" style="background:#dcfce7;width:36px;height:36px;">
            <i data-lucide="check-circle-2" style="width:18px;height:18px;color:#16a34a;"></i>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     LISTA DE TAREAS
══════════════════════════════════════════════════════════════ --}}
@if($tareas->isNotEmpty())

<div style="background:white;border-radius:14px;border:1px solid #f0f0f0;overflow:hidden;">

    <div style="padding:18px 22px;border-bottom:1px solid #f5f5f5;">
        <h2 style="font-size:14px;font-weight:700;color:#111827;margin:0;">Lista de Tareas</h2>
    </div>

    <table class="tabla-casos" style="width:100%;">
        <thead>
            <tr>
                <th>#</th>
                <th>Descripción</th>
                <th>Asignado a</th>
                <th>Estado</th>
                <th>Fecha Límite</th>
                <th>Observaciones</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($tareas as $tarea)
            <tr>
                <td style="color:#9ca3af;font-weight:600;">{{ $tarea->orden ?? $loop->iteration }}</td>

                <td style="max-width:280px;">
                    <p style="margin:0;font-weight:500;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:280px;">
                        {{ $tarea->descripcion }}
                    </p>
                </td>

                <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:28px;height:28px;background:#b11226;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:white;flex-shrink:0;">
                            {{ strtoupper(substr($tarea->usuario?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p style="margin:0;font-size:12.5px;font-weight:600;color:#374151;">
                                {{ $tarea->usuario?->name ?? '—' }}
                            </p>
                            <p style="margin:0;font-size:11px;color:#9ca3af;">
                                {{ $tarea->usuario?->role?->nombre ?? '' }}
                            </p>
                        </div>
                    </div>
                </td>

                <td>
                    @php
                        $tc = match($tarea->estado) {
                            'Pendiente'  => 'badge-pendiente',
                            'En proceso' => 'badge-proceso',
                            'Completada' => 'badge-completada',
                            default      => 'badge-pendiente',
                        };
                    @endphp
                    <span class="badge {{ $tc }}">{{ $tarea->estado }}</span>
                </td>

                <td style="color:#6b7280;font-size:12px;">
                    @if($tarea->fecha_fin)
                        {{ $tarea->fecha_fin->format('d/m/Y') }}
                        @if($tarea->fecha_fin->isPast() && !$tarea->estaCompletada())
                            <span style="color:#dc2626;font-weight:600;display:block;font-size:10.5px;">Vencida</span>
                        @endif
                    @else
                        <span style="color:#d1d5db;">—</span>
                    @endif
                </td>

                <td style="text-align:center;">
                    <span style="background:#f3f4f6;padding:4px 10px;border-radius:20px;font-size:12px;font-weight:600;color:#374151;">
                        {{ $tarea->observaciones->count() }}
                    </span>
                </td>

                <td>
                    <div style="display:flex;gap:6px;">
                        <a href="{{ route('tareas.ver', [$caso->id, $tarea->id]) }}"
                           class="btn-ver">Ver</a>

                        @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']) || auth()->id() === $tarea->user_id)
                        <a href="{{ route('tareas.editar', [$caso->id, $tarea->id]) }}"
                           style="background:#f3f4f6;color:#374151;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;text-decoration:none;transition:background 0.15s;"
                           onmouseover="this.style.background='#e5e7eb'"
                           onmouseout="this.style.background='#f3f4f6'">
                           Editar
                        </a>
                        @endif

                        @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
                        <form method="POST" action="{{ route('tareas.eliminar', [$caso->id, $tarea->id]) }}"
                              style="display:inline;"
                              onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta tarea?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                style="background:#fee2e2;color:#dc2626;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:500;border:none;cursor:pointer;transition:background 0.15s;">
                                Eliminar
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
<div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:60px;text-align:center;">
    <i data-lucide="clipboard-list" style="width:48px;height:48px;color:#d1d5db;margin:0 auto 16px;display:block;"></i>
    <h3 style="font-size:16px;font-weight:600;color:#374151;margin:0 0 6px;">Sin tareas asignadas</h3>
    <p style="color:#9ca3af;font-size:13.5px;margin:0 0 20px;">Este caso no tiene tareas asignadas todavía.</p>
    @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
    <a href="{{ route('tareas.crear', $caso->id) }}" class="btn-primary">
        <i data-lucide="plus" style="width:16px;height:16px;"></i>
        Crear Primera Tarea
    </a>
    @endif
</div>
@endif

@endsection
