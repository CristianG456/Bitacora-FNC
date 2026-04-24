@extends('layouts.app')

@section('title', 'Historial Global del Sistema')

@section('content')

<style>
    /* Estilos específicos para la línea de tiempo del historial */
    .timeline {
        position: relative;
        padding-left: 2rem;
        margin-top: 2rem;
    }

    .timeline::before {
        content: '';
        position: absolute;
        top: 0;
        bottom: 0;
        left: 11px;
        width: 2px;
        background: #e5e7eb;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
    }

    .timeline-icon {
        position: absolute;
        left: -2rem;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: white;
        border: 2px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }

    .timeline-content {
        background: white;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        transition: box-shadow 0.2s;
    }

    .timeline-content:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }

    /* Colores por tipo de evento (similar a la imagen) */
    .evento-creado .timeline-content {
        border-color: #bbf7d0;
        background: #f0fdf4;
    }
    .evento-creado .timeline-icon { border-color: #22c55e; color: #22c55e; }

    .evento-asignacion .timeline-content {
        border-color: #bfdbfe;
        background: #eff6ff;
    }
    .evento-asignacion .timeline-icon { border-color: #3b82f6; color: #3b82f6; }

    .evento-tarea .timeline-content {
        border-color: #fde68a;
        background: #fefce8;
    }
    .evento-tarea .timeline-icon { border-color: #eab308; color: #eab308; }
    
    .evento-mensaje .timeline-content {
        border-color: #e9d5ff;
        background: #faf5ff;
    }
    .evento-mensaje .timeline-icon { border-color: #a855f7; color: #a855f7; }

    .evento-defecto .timeline-content {
        border-color: #e5e7eb;
    }
    .evento-defecto .timeline-icon { border-color: #9ca3af; color: #9ca3af; }

    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
    }

    .event-title {
        font-weight: 700;
        color: #111827;
        font-size: 15px;
    }

    .event-time {
        font-size: 12px;
        color: #6b7280;
    }

    .event-desc {
        color: #4b5563;
        font-size: 13.5px;
        margin-bottom: 12px;
    }

    .event-meta {
        display: flex;
        gap: 16px;
        font-size: 12px;
        color: #6b7280;
        align-items: center;
    }

    .event-meta i {
        width: 14px;
        height: 14px;
        margin-right: 4px;
    }

    .badge-event {
        font-size: 10px;
        padding: 2px 8px;
        border-radius: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .bg-green-100 { background: #dcfce7; color: #166534; }
    .bg-blue-100 { background: #dbeafe; color: #1d4ed8; }
    .bg-yellow-100 { background: #fef9c3; color: #713f12; }
    .bg-purple-100 { background: #f3e8ff; color: #7e22ce; }
    .bg-gray-100 { background: #f3f4f6; color: #374151; }

    .filter-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    }

    .filter-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 16px;
        align-items: end;
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
    <div>
        <h1>Historial Global del Sistema</h1>
        <p>Visualización completa de la bitácora de casos finalizados</p>
    </div>
    <div style="display: flex; gap: 12px;">
        <a href="{{ route('historial.exportar.pdf', request()->all()) }}" target="_blank" class="btn-secondary" style="color: #b11226; border-color: #fca5a5;">
            <i data-lucide="file-down" style="width:16px;height:16px;"></i>
            Exportar PDF
        </a>
        <a href="{{ route('historial.exportar.excel', request()->all()) }}" class="btn-secondary" style="color: #16a34a; border-color: #86efac;">
            <i data-lucide="sheet" style="width:16px;height:16px;"></i>
            Exportar Excel
        </a>
    </div>
</div>

{{-- Filtros --}}
<div class="filter-card">
    <form method="GET" action="{{ route('historial.index') }}">
        <h3 style="font-size: 14px; font-weight: 600; color: #374151; margin-bottom: 16px;">Filtros</h3>
        
        <div class="filter-grid">
            <div>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="search" class="text-gray-400" style="width: 16px; height: 16px;"></i>
                    </div>
                    <input type="text" name="radicado" value="{{ request('radicado') }}" placeholder="Buscar por radicado o solicitante..." class="form-input" style="padding-left: 36px;">
                </div>
            </div>

            <div>
                <select name="evento" class="form-select">
                    <option value="">Todos los eventos</option>
                    @foreach($acciones as $accion)
                        <option value="{{ $accion }}" {{ request('evento') == $accion ? 'selected' : '' }}>
                            {{ $accion }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="usuario_id" class="form-select">
                    <option value="">Todos los usuarios</option>
                    @foreach($usuarios as $user)
                        <option value="{{ $user->id }}" {{ request('usuario_id') == $user->id ? 'selected' : '' }}>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <select name="tipo_id" class="form-select">
                    <option value="">Todos los tipos</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->id }}" {{ request('tipo_id') == $tipo->id ? 'selected' : '' }}>
                            {{ $tipo->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                @if(request()->hasAny(['radicado', 'evento', 'usuario_id', 'tipo_id']))
                    <a href="{{ route('historial.index') }}" class="btn-secondary w-full justify-center">
                        Limpiar filtros
                    </a>
                @else
                    <button type="submit" class="btn-primary w-full justify-center" style="background: #f3f4f6; color: #374151; border: 1px solid #e5e7eb;">
                        Filtrar
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>

{{-- Lista de eventos --}}
<div style="font-size: 13.5px; color: #6b7280;">
    Mostrando {{ $eventos->count() }} de {{ $eventos->total() }} eventos
</div>

@if($eventos->isEmpty())
    <div style="background: white; border-radius: 12px; padding: 40px; text-align: center; border: 1px solid #e5e7eb; margin-top: 16px;">
        <i data-lucide="inbox" style="width: 48px; height: 48px; color: #9ca3af; margin: 0 auto 16px;"></i>
        <h3 style="font-size: 16px; font-weight: 600; color: #374151;">No hay eventos para mostrar</h3>
        <p style="color: #6b7280; font-size: 14px; margin-top: 8px;">Intenta ajustar los filtros de búsqueda.</p>
    </div>
@else
    <div class="timeline">
        @foreach($eventos as $evento)
            @php
                // Determinar el estilo basado en la acción o módulo
                $claseEvento = 'evento-defecto';
                $icono = 'circle';
                $badgeClass = 'bg-gray-100';

                $accionStr = strtolower($evento->accion);
                $moduloStr = strtolower($evento->modulo);

                if (str_contains($accionStr, 'crear') || str_contains($accionStr, 'nuevo')) {
                    $claseEvento = 'evento-creado';
                    $icono = 'file-plus';
                    $badgeClass = 'bg-green-100';
                } elseif (str_contains($accionStr, 'asignar') || str_contains($accionStr, 'reemplazar')) {
                    $claseEvento = 'evento-asignacion';
                    $icono = 'user-plus';
                    $badgeClass = 'bg-blue-100';
                } elseif ($moduloStr === 'tareas' || str_contains($accionStr, 'tarea')) {
                    $claseEvento = 'evento-tarea';
                    $icono = 'check-square';
                    $badgeClass = 'bg-yellow-100';
                } elseif ($moduloStr === 'chat' || $moduloStr === 'mensajes') {
                    $claseEvento = 'evento-mensaje';
                    $icono = 'message-square';
                    $badgeClass = 'bg-purple-100';
                } elseif (str_contains($accionStr, 'actualizar') || str_contains($accionStr, 'estado')) {
                    $claseEvento = 'evento-defecto';
                    $icono = 'edit-3';
                    $badgeClass = 'bg-gray-100';
                }
            @endphp

            <div class="timeline-item {{ $claseEvento }}">
                <div class="timeline-icon">
                    <i data-lucide="{{ $icono }}" style="width: 14px; height: 14px;"></i>
                </div>
                <div class="timeline-content">
                    <div class="event-header">
                        <div>
                            <span class="badge-event {{ $badgeClass }}">{{ $evento->accion }}</span>
                        </div>
                        <div class="event-time">
                            {{ $evento->created_at->format('d/m/Y - H:i') }}
                        </div>
                    </div>
                    
                    @if($evento->caso)
                        <div class="event-title mb-1">
                            {{ $evento->caso->radicado }}
                        </div>
                    @endif
                    
                    <div class="event-desc">
                        {{ $evento->descripcion }}
                    </div>
                    
                    <div class="event-meta">
                        <div style="display: flex; align-items: center;">
                            <i data-lucide="user"></i>
                            {{ $evento->usuario ? $evento->usuario->name : 'Sistema' }}
                        </div>
                        
                        @if($evento->usuario && $evento->usuario->role)
                            <div style="display: flex; align-items: center;">
                                <i data-lucide="shield"></i>
                                {{ $evento->usuario->role->nombre }}
                            </div>
                        @endif

                        @if($evento->modulo)
                            <div style="display: flex; align-items: center;">
                                <i data-lucide="box"></i>
                                Módulo: {{ $evento->modulo }}
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Paginación --}}
    <div style="margin-top: 24px;">
        {{ $eventos->links() }}
    </div>
@endif

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Enviar el formulario automáticamente al cambiar un select (opcional para mejor UX)
        const selects = document.querySelectorAll('.filter-card select');
        selects.forEach(select => {
            select.addEventListener('change', () => {
                select.closest('form').submit();
            });
        });
    });
</script>
@endpush
