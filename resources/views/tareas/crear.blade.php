@extends('layouts.app')

@section('title', 'Nueva Tarea - ' . $caso->radicado)

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
    <h1>Nueva Tarea</h1>
    <p>Caso: <strong style="color:#b11226;">{{ $caso->radicado }}</strong> — {{ $caso->tipo?->nombre }}</p>
</div>

<form method="POST" action="{{ route('tareas.guardar', $caso->id) }}" id="form-tarea">
    @csrf

    <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;align-items:start;">

        {{-- ── Columna principal ────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            {{-- Datos de la tarea --}}
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 20px;">
                    Información de la Tarea
                </h2>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">

                    {{-- Usuario asignado --}}
                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="user_id">Usuario Asignado *</label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Selecciona un usuario...</option>
                            @foreach($usuarios as $u)
                                <option value="{{ $u->id }}" {{ old('user_id') == $u->id ? 'selected' : '' }}>
                                    {{ $u->name }} — {{ $u->role?->nombre ?? 'Sin rol' }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Descripción --}}
                    <div style="grid-column:1/-1;">
                        <label class="form-label" for="descripcion">Descripción de la Tarea *</label>
                        <textarea name="descripcion" id="descripcion"
                                  class="form-textarea"
                                  rows="4"
                                  placeholder="Describe detalladamente la tarea a realizar..."
                                  required minlength="10" maxlength="2000">{{ old('descripcion') }}</textarea>
                        <div style="display:flex;justify-content:space-between;margin-top:4px;">
                            @error('descripcion')
                                <p class="form-error">{{ $message }}</p>
                            @else
                                <span></span>
                            @enderror
                            <span id="desc-count" style="font-size:11px;color:#9ca3af;">0 / 2000</span>
                        </div>
                    </div>

                    {{-- Fecha inicio --}}
                    <div>
                        <label class="form-label" for="fecha_inicio">Fecha de Inicio</label>
                        <input type="date" name="fecha_inicio" id="fecha_inicio"
                               class="form-input"
                               value="{{ old('fecha_inicio') }}">
                        @error('fecha_inicio')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Fecha fin --}}
                    <div>
                        <label class="form-label" for="fecha_fin">Fecha Límite</label>
                        <input type="date" name="fecha_fin" id="fecha_fin"
                               class="form-input"
                               value="{{ old('fecha_fin') }}">
                        @error('fecha_fin')
                            <p class="form-error">{{ $message }}</p>
                        @enderror
                    </div>

                </div>
            </div>

            {{-- Observación inicial (obligatoria) --}}
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:24px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 6px;">
                    Observación Inicial
                </h2>
                <p style="font-size:12.5px;color:#9ca3af;margin:0 0 16px;">
                    <i data-lucide="info" style="width:13px;height:13px;display:inline;vertical-align:middle;"></i>
                    Obligatoria. Describe el contexto o instrucciones para el usuario.
                </p>

                <textarea name="observacion" id="observacion"
                          class="form-textarea"
                          rows="5"
                          placeholder="Escribe la observación inicial para esta tarea..."
                          required minlength="10" maxlength="2000">{{ old('observacion') }}</textarea>
                <div style="display:flex;justify-content:space-between;margin-top:4px;">
                    @error('observacion')
                        <p class="form-error">{{ $message }}</p>
                    @else
                        <span></span>
                    @enderror
                    <span id="obs-count" style="font-size:11px;color:#9ca3af;">0 / 2000</span>
                </div>
            </div>

        </div>

        {{-- ── Panel lateral ────────────────────────────────── --}}
        <div style="display:flex;flex-direction:column;gap:20px;">

            {{-- Acciones --}}
            <div style="background:white;border-radius:14px;border:1px solid #f0f0f0;padding:20px;">
                <h2 style="font-size:13px;font-weight:700;color:#374151;text-transform:uppercase;letter-spacing:.06em;margin:0 0 16px;">
                    Acciones
                </h2>

                <button type="submit" class="btn-primary" style="width:100%;justify-content:center;margin-bottom:10px;">
                    <i data-lucide="save" style="width:16px;height:16px;"></i>
                    Crear Tarea
                </button>

                <a href="{{ route('tareas.index', $caso->id) }}"
                   class="btn-secondary" style="width:100%;justify-content:center;">
                    Cancelar
                </a>
            </div>

            {{-- Info del caso --}}
            <div style="background:#f8fafc;border-radius:14px;border:1px solid #e8ecf0;padding:20px;">
                <h2 style="font-size:12px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin:0 0 14px;">
                    Contexto del Caso
                </h2>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div>
                        <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Radicado</p>
                        <p style="font-size:13.5px;font-weight:700;color:#b11226;margin:0;">{{ $caso->radicado }}</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Tipo</p>
                        <p style="font-size:13px;font-weight:500;color:#374151;margin:0;">{{ $caso->tipo?->nombre }}</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Subtipo</p>
                        <p style="font-size:13px;font-weight:500;color:#374151;margin:0;">{{ $caso->subtipo?->nombre }}</p>
                    </div>
                    <div>
                        <p style="font-size:11px;color:#9ca3af;margin:0 0 2px;text-transform:uppercase;letter-spacing:.05em;">Estado</p>
                        @php
                            $bc = match($caso->estado) {
                                'En proceso' => 'badge-proceso',
                                'Completado' => 'badge-completado',
                                'Finalizado' => 'badge-finalizado',
                                default      => 'badge-pendiente',
                            };
                        @endphp
                        <span class="badge {{ $bc }}">{{ $caso->estado }}</span>
                    </div>
                </div>
            </div>

            {{-- Aviso reglas --}}
            <div style="background:#fef9c3;border-radius:14px;border:1px solid #fde68a;padding:16px;">
                <div style="display:flex;align-items:flex-start;gap:8px;">
                    <i data-lucide="alert-triangle" style="width:16px;height:16px;color:#d97706;flex-shrink:0;margin-top:1px;"></i>
                    <div>
                        <p style="font-size:12.5px;font-weight:600;color:#713f12;margin:0 0 4px;">Reglas de negocio</p>
                        <ul style="font-size:12px;color:#713f12;margin:0;padding-left:16px;line-height:1.7;">
                            <li>La observación inicial es <strong>obligatoria</strong>.</li>
                            <li>Para completar la tarea se requiere una observación de cierre.</li>
                            <li>El caso no puede finalizarse con tareas pendientes.</li>
                        </ul>
                    </div>
                </div>
            </div>

        </div>

    </div>

</form>

@endsection

@push('scripts')
<script>
    // Contador de caracteres en textarea
    const txtDesc = document.getElementById('descripcion');
    const cntDesc = document.getElementById('desc-count');
    const txtObs  = document.getElementById('observacion');
    const cntObs  = document.getElementById('obs-count');

    function updateCount(textarea, counter) {
        const len = textarea.value.length;
        counter.textContent = `${len} / 2000`;
        counter.style.color = len > 1800 ? '#dc2626' : '#9ca3af';
    }

    txtDesc.addEventListener('input', () => updateCount(txtDesc, cntDesc));
    txtObs.addEventListener('input',  () => updateCount(txtObs,  cntObs));

    // Validación fecha fin >= fecha inicio
    document.getElementById('form-tarea').addEventListener('submit', function(e) {
        const fi = document.getElementById('fecha_inicio').value;
        const ff = document.getElementById('fecha_fin').value;
        if (fi && ff && ff < fi) {
            e.preventDefault();
            alert('La fecha límite debe ser posterior o igual a la fecha de inicio.');
        }
    });
</script>
@endpush
