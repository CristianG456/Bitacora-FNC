@extends('layouts.app')

@section('title', 'Editar Tarea - ' . $caso->radicado)

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
    <h1>Editar Tarea</h1>
    <p>Caso: <strong style="color:#b11226;">{{ $caso->radicado }}</strong> · Tarea #{{ $tarea->orden ?? $tarea->id }}</p>
</div>

<form method="POST" action="{{ route('tareas.actualizar', [$caso->id, $tarea->id]) }}" id="form-editar">
    @csrf
    @method('PUT')

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        {{-- ── Columna principal ────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            {{-- Datos --}}
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 20px;">
                    Datos de la Tarea
                </h2>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="user_id">Usuario Asignado *</label>
                        <select name="user_id" id="user_id" class="form-select"
                                {{ auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']) ? '' : 'disabled' }}>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}"
                                    {{ (old('user_id', $tarea->user_id) == $u->id) ? 'selected' : '' }}>
                                    {{ $u->name }} — {{ $u->role?->nombre ?? 'Sin rol' }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="descripcion">Descripción *</label>
                        <textarea name="descripcion" id="descripcion"
                                  class="form-textarea"
                                  rows="4"
                                  required minlength="10" maxlength="2000"
                                  {{ auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']) ? '' : 'readonly' }}>{{ old('descripcion', $tarea->descripcion) }}</textarea>
                        <div style="text-align:right;margin-top:4px;">
                            <span id="desc-count" style="font-size:11px;color:#9ca3af;">
                                {{ strlen($tarea->descripcion) }} / 2000
                            </span>
                        </div>
                        @error('descripcion')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Estado --}}
                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="estado">Estado *</label>
                        <select name="estado" id="estado" class="form-select" required>
                            @foreach(['Pendiente', 'En proceso', 'Completada'] as $est)
                                <option value="{{ $est }}"
                                    {{ old('estado', $tarea->estado) === $est ? 'selected' : '' }}>
                                    {{ $est }}
                                </option>
                            @endforeach
                        </select>
                        @error('estado')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="fecha_inicio">Fecha de Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio"
                               class="form-input"
                               value="{{ old('fecha_inicio', $tarea->fecha_inicio?->format('Y-m-d')) }}">
                        @error('fecha_inicio')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="form-label" for="fecha_fin">Fecha Límite</label>
                        <input type="date" name="fecha_fin" id="fecha_fin"
                               class="form-input"
                               value="{{ old('fecha_fin', $tarea->fecha_fin?->format('Y-m-d')) }}">
                        @error('fecha_fin')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Observación de actualización --}}
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px;">
                    Nueva Observación
                </h2>
                <p style="font-size:12.5px;color:#9ca3af;margin:0 0 14px;">
                    <i data-lucide="info" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i>
                    Obligatoria si cambias el estado a <strong>Completada</strong>.
                </p>
                <textarea name="observacion" id="observacion"
                          class="form-textarea"
                          rows="4"
                          placeholder="Registra los avances, cambios o motivo del cierre..."
                          maxlength="2000">{{ old('observacion') }}</textarea>
                <div style="text-align:right;margin-top:4px;">
                    <span id="obs-count" style="font-size:11px;color:#9ca3af;">0 / 2000</span>
                </div>
                @error('observacion')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>

            {{-- Historial de observaciones --}}
            @if($observaciones->isNotEmpty())
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                    Historial de Observaciones ({{ $observaciones->count() }})
                </h2>

                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($observaciones as $obs)
                    <div class="observacion-item">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <span style="font-size:12px;font-weight:600;color:#374151;">
                                {{ $obs->autor?->name ?? 'Usuario' }}
                                <span style="color:#9ca3af;font-weight:400;margin-left:4px;">
                                    ({{ $obs->autor?->role?->nombre }})
                                </span>
                            </span>
                            <span style="font-size:11px;color:#9ca3af;">
                                {{ $obs->created_at->format('d/m/Y H:i') }}
                            </span>
                        </div>
                        <p style="font-size:13px;color:#374151;margin:0;line-height:1.6;">
                            {{ $obs->contenido }}
                        </p>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>

        {{-- ── Panel lateral ────────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:20px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                    Acciones
                </h2>

                <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-bottom:10px;">
                    <i data-lucide="save" style="width:16px;height:16px;"></i>
                    Guardar Cambios
                </button>

                <a href="{{ route('tareas.index', $caso->id) }}"
                   class="btn-secondary" style="width:100%;justify-content:center;">
                    Cancelar
                </a>
            </div>

            {{-- Estado actual --}}
            <div style="background:#f8fafc;border-radius:14px;border:1px solid #e8ecf0;padding:20px;">
                <h2 style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;">
                    Estado Actual
                </h2>
                @php
                    $tc = match($tarea->estado) {
                        'Pendiente'  => 'badge-pendiente',
                        'En proceso' => 'badge-proceso',
                        'Completada' => 'badge-completada',
                        default      => 'badge-pendiente',
                    };
                @endphp
                <span class="badge {{ $tc }}" style="font-size:13px;padding:5px 14px;">{{ $tarea->estado }}</span>

                <div style="margin-top:14px;font-size:12px;color:#6b7280;line-height:1.7;">
                    <p style="margin:0;"><strong>Creada:</strong> {{ $tarea->created_at->format('d/m/Y H:i') }}</p>
                    @if($tarea->fecha_fin)
                    <p style="margin:0;"><strong>Límite:</strong>
                        <span style="{{ $tarea->fecha_fin->isPast() && !$tarea->estaCompletada() ? 'color:#dc2626;font-weight:600;' : '' }}">
                            {{ $tarea->fecha_fin->format('d/m/Y') }}
                            @if($tarea->fecha_fin->isPast() && !$tarea->estaCompletada()) ⚠️ Vencida @endif
                        </span>
                    </p>
                    @endif
                    <p style="margin:0;"><strong>Observaciones:</strong> {{ $observaciones->count() }}</p>
                </div>
            </div>

        </div>

    </div>

</form>

@endsection

@push('scripts')
<script>
    const txtDesc = document.getElementById('descripcion');
    const cntDesc = document.getElementById('desc-count');
    const txtObs  = document.getElementById('observacion');
    const cntObs  = document.getElementById('obs-count');
    const selEst  = document.getElementById('estado');

    function updateCount(t, c) {
        const l = t.value.length;
        c.textContent = `${l} / 2000`;
        c.style.color = l > 1800 ? '#dc2626' : '#9ca3af';
    }

    txtDesc.addEventListener('input', () => updateCount(txtDesc, cntDesc));
    txtObs.addEventListener('input',  () => updateCount(txtObs, cntObs));

    // Aviso visual cuando estado = Completada
    selEst.addEventListener('change', function() {
        const label = document.querySelector('label[for="observacion"]');
        if (!label) return;
        if (this.value === 'Completada') {
            txtObs.setAttribute('required', 'required');
            txtObs.style.borderColor = '#b11226';
        } else {
            txtObs.removeAttribute('required');
            txtObs.style.borderColor = '';
        }
    });

    // Activar aviso si ya viene Completada
    selEst.dispatchEvent(new Event('change'));
</script>
@endpush
