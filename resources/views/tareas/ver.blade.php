@extends('layouts.app')

@section('title', 'Detalle de Tarea')

@section('content')

{{-- Encabezado --}}
<div class="page-header">
    <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
        <a href="{{ route('tareas.index', $caso->id) }}"
           style="color:#9ca3af;text-decoration:none;display:flex;align-items:center;gap:4px;font-size:13px;">
            <i data-lucide="arrow-left" style="width:16px;height:16px;"></i>
            Volver a tareas
        </a>
    </div>
    <h1>Detalle de Tarea</h1>
    <p>Caso: <strong style="color:#b11226;">{{ $caso->radicado }}</strong></p>
</div>

<div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

    {{-- ── Columna principal ──────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:20px;">

        {{-- Información de la tarea --}}
        <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px;">
                <h2 style="font-size:14px;font-weight:700;color:#111827;margin:0;">Tarea #{{ $tarea->orden ?? $tarea->id }}</h2>
                @php
                    $tc = match($tarea->estado) {
                        'Pendiente'  => 'badge-pendiente',
                        'En proceso' => 'badge-proceso',
                        'Completada' => 'badge-completada',
                        default      => 'badge-pendiente',
                    };
                @endphp
                <span class="badge {{ $tc }}" style="font-size:13px;padding:5px 14px;">{{ $tarea->estado }}</span>
            </div>

            <p style="font-size:14px;color:#374151;line-height:1.7;margin:0 0 20px;">
                {{ $tarea->descripcion }}
            </p>

            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;padding-top:16px;border-top:1px solid #f5f5f5;">

                <div>
                    <p style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 4px;font-weight:600;">Asignado a</p>
                    <div style="display:flex;align-items:center;gap:8px;">
                        <div style="width:30px;height:30px;background:#b11226;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:white;">
                            {{ strtoupper(substr($tarea->usuario?->name ?? 'U', 0, 1)) }}
                        </div>
                        <div>
                            <p style="font-size:13px;font-weight:600;color:#374151;margin:0;">{{ $tarea->usuario?->name ?? '—' }}</p>
                            <p style="font-size:11px;color:#9ca3af;margin:0;">{{ $tarea->usuario?->role?->nombre }}</p>
                        </div>
                    </div>
                </div>

                <div>
                    <p style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 4px;font-weight:600;">Fecha Inicio</p>
                    <p style="font-size:13.5px;color:#374151;font-weight:500;margin:0;">
                        {{ $tarea->fecha_inicio ? $tarea->fecha_inicio->format('d/m/Y') : '—' }}
                    </p>
                </div>

                <div>
                    <p style="font-size:11px;color:#9ca3af;text-transform:uppercase;letter-spacing:.06em;margin:0 0 4px;font-weight:600;">Fecha Límite</p>
                    @if($tarea->fecha_fin)
                        <p style="font-size:13.5px;margin:0;font-weight:500;
                            color:{{ $tarea->fecha_fin->isPast() && !$tarea->estaCompletada() ? '#dc2626' : '#374151' }};">
                            {{ $tarea->fecha_fin->format('d/m/Y') }}
                            @if($tarea->fecha_fin->isPast() && !$tarea->estaCompletada())
                                <span style="font-size:11px;background:#fee2e2;color:#dc2626;padding:2px 6px;border-radius:4px;margin-left:4px;">Vencida</span>
                            @endif
                        </p>
                    @else
                        <p style="font-size:13.5px;color:#9ca3af;margin:0;">Sin fecha límite</p>
                    @endif
                </div>

            </div>
        </div>

        {{-- Cambio rápido de estado --}}
        @if(!$tarea->estaCompletada())
        <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
            <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                Actualizar Estado
            </h2>

            <form method="POST" action="{{ route('tareas.estado', [$caso->id, $tarea->id]) }}" id="form-estado">
                @csrf

                <div style="margin-bottom:14px;">
                    <label class="form-label" for="estado">Nuevo Estado *</label>
                    <select name="estado" id="estado" class="form-select" required>
                        @foreach(['Pendiente', 'En proceso', 'Completada'] as $est)
                            <option value="{{ $est }}" {{ $tarea->estado === $est ? 'selected' : '' }}>
                                {{ $est }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="obs-container" style="margin-bottom:14px;{{ $tarea->estado !== 'Completada' ? '' : '' }}">
                    <label class="form-label" for="obs_rapida">
                        Observación
                        <span id="obs-req-hint" style="color:#dc2626;display:none;"> *Obligatoria para Completada</span>
                    </label>
                    <textarea name="observacion" id="obs_rapida"
                              class="form-textarea"
                              rows="3"
                              placeholder="Describe el avance o motivo del cambio..."
                              maxlength="2000"></textarea>
                    @error('observacion')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary" style="width:100%;justify-content:center;">
                    <i data-lucide="refresh-cw" style="width:16px;height:16px;"></i>
                    Actualizar Estado
                </button>

            </form>
        </div>
        @endif

        {{-- Historial de observaciones --}}
        <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
            <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                Observaciones ({{ $observaciones->count() }})
            </h2>

            @if($observaciones->isNotEmpty())
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @foreach($observaciones as $obs)
                    <div class="observacion-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-size:12.5px;font-weight:600;color:#374151;">
                                {{ $obs->autor?->name ?? 'Usuario' }}
                                @if($obs->autor?->role?->nombre)
                                    <span style="color:#9ca3af;font-weight:400;font-size:11.5px;">
                                        · {{ $obs->autor->role->nombre }}
                                    </span>
                                @endif
                            </span>
                            <span style="font-size:11px;color:#9ca3af;">
                                {{ $obs->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        <p style="font-size:13.5px;color:#374151;margin:0;line-height:1.6;">
                            {{ $obs->contenido }}
                        </p>
                    </div>
                    @endforeach
                </div>
            @else
                <div style="text-align:center;padding:20px;">
                    <i data-lucide="message-square" style="width:32px;height:32px;color:#d1d5db;margin:0 auto 10px;display:block;"></i>
                    <p style="color:#9ca3af;font-size:13px;margin:0;">Aún no hay observaciones registradas.</p>
                </div>
            @endif
        </div>

        {{-- Agregar observación --}}
        <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
            <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                Agregar Observación
            </h2>

            <form method="POST" action="{{ route('tareas.observacion', [$caso->id, $tarea->id]) }}">
                @csrf

                <div style="margin-bottom:14px;">
                    <label class="form-label" for="contenido">Observación *</label>
                    <textarea name="contenido" id="contenido"
                              class="form-textarea"
                              rows="4"
                              placeholder="Registra un avance, nota o comentario relevante..."
                              required minlength="10" maxlength="2000">{{ old('contenido') }}</textarea>
                    @error('contenido')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="btn-primary">
                    <i data-lucide="message-circle-plus" style="width:16px;height:16px;"></i>
                    Guardar Observación
                </button>

            </form>
        </div>

    </div>

    {{-- ── Panel lateral ────────────────────────────────────────── --}}
    <div style="display:flex;flex-direction:column;gap:20px;">

        {{-- Acciones --}}
        @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']) || auth()->id() === $tarea->user_id)
        <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:20px;">
            <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;">
                Acciones
            </h2>

            <a href="{{ route('tareas.editar', [$caso->id, $tarea->id]) }}"
               class="btn-secondary" style="width:100%;justify-content:center;margin-bottom:10px;">
                <i data-lucide="pencil" style="width:15px;height:15px;"></i>
                Editar Tarea
            </a>

            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <form method="POST" action="{{ route('tareas.eliminar', [$caso->id, $tarea->id]) }}"
                  onsubmit="return confirm('¿Eliminar esta tarea permanentemente?')">
                @csrf
                @method('DELETE')
                <button type="submit"
                    style="width:100%;background:#fee2e2;color:#dc2626;border:none;border-radius:8px;padding:9px;font-size:13px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:background 0.15s;"
                    onmouseover="this.style.background='#fecaca'"
                    onmouseout="this.style.background='#fee2e2'">
                    <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
                    Eliminar Tarea
                </button>
            </form>
            @endif
        </div>
        @endif

        {{-- Metadata --}}
        <div style="background:#f8fafc;border-radius:14px;border:1px solid #e8ecf0;padding:20px;">
            <h2 style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;">
                Información
            </h2>
            <div style="display:flex;flex-direction:column;gap:10px;font-size:12.5px;color:#374151;">
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:2px;">Creada</span>
                    {{ $tarea->created_at->format('d/m/Y H:i') }}
                </div>
                @if($tarea->updated_at && $tarea->updated_at != $tarea->created_at)
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:2px;">Última modificación</span>
                    {{ $tarea->updated_at->format('d/m/Y H:i') }}
                </div>
                @endif
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:2px;">Total observaciones</span>
                    {{ $observaciones->count() }}
                </div>
            </div>
        </div>

        {{-- Contexto del caso --}}
        <div style="background:#f8fafc;border-radius:14px;border:1px solid #e8ecf0;padding:20px;">
            <h2 style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;">
                Caso Relacionado
            </h2>
            <div style="display:flex;flex-direction:column;gap:8px;font-size:12.5px;">
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;display:block;margin-bottom:2px;">Radicado</span>
                    <a href="{{ route('tareas.index', $caso->id) }}"
                       style="color:#b11226;font-weight:700;text-decoration:none;">{{ $caso->radicado }}</a>
                </div>
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;display:block;margin-bottom:2px;">Tipo</span>
                    <span style="color:#374151;font-weight:500;">{{ $caso->tipo?->nombre }}</span>
                </div>
                <div>
                    <span style="color:#9ca3af;font-size:11px;text-transform:uppercase;display:block;margin-bottom:2px;">Estado del Caso</span>
                    @php
                        $bc = match($caso->estado) {
                            'En proceso'  => 'badge-proceso',
                            'Completado'  => 'badge-completado',
                            'Finalizado'  => 'badge-finalizado',
                            default       => 'badge-pendiente',
                        };
                    @endphp
                    <span class="badge {{ $bc }}">{{ $caso->estado }}</span>
                </div>
            </div>
        </div>

    </div>

</div>

@endsection

@push('scripts')
<script>
    const selEst = document.getElementById('estado');
    const obsReq = document.getElementById('obs-req-hint');
    const obsField = document.getElementById('obs_rapida');

    if (selEst) {
        selEst.addEventListener('change', function () {
            if (this.value === 'Completada') {
                obsField.setAttribute('required', 'required');
                obsField.style.borderColor = '#b11226';
                obsReq.style.display = 'inline';
            } else {
                obsField.removeAttribute('required');
                obsField.style.borderColor = '';
                obsReq.style.display = 'none';
            }
        });
    }
</script>
@endpush
