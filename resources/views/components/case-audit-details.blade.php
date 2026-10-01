@props(['event', 'compact' => false])

@php
    $meta = is_array($event->metadata) ? $event->metadata : [];
    $tipo = $meta['event_type'] ?? null;
    $fecha = static fn ($value) => $value
        ? \Carbon\Carbon::parse($value)->timezone('America/Bogota')->locale('es')->translatedFormat('d M Y · H:i')
        : 'No registrada';
    $persona = static fn ($value) => ($value['nombre'] ?? 'Sistema').' · '.($value['rol'] ?? 'Sistema');
    $ans = $meta['ans'] ?? null;
@endphp

@if($tipo)
<div class="mt-3 space-y-3 rounded-lg border border-gray-200 bg-white/80 p-3 text-xs text-gray-700" data-audit-detail="{{ $tipo }}">
    @if($tipo === 'case_created')
        <div class="grid grid-cols-2 gap-2">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Creado por</span><b>{{ $persona($meta['actor'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Fecha/hora</span><b>{{ $fecha($meta['fecha_hora'] ?? null) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Radicado</span><b>{{ data_get($meta, 'caso.radicado', 'N/A') }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Solicitud</span><b>{{ data_get($meta, 'caso.fecha_solicitud', 'N/A') }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Tipo / subtipo</span><b>{{ data_get($meta, 'caso.tipo', 'N/A') }} · {{ data_get($meta, 'caso.subtipo', 'N/A') }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Estado inicial</span><b>{{ data_get($meta, 'caso.estado_inicial', 'N/A') }}</b></div>
        </div>
        <div class="rounded-md bg-gray-50 p-2">
            <span class="block text-[10px] font-bold uppercase text-gray-500">Solicitante</span>
            <b>{{ data_get($meta, 'solicitante.nombre', 'N/A') }}</b> · {{ data_get($meta, 'solicitante.tipo', 'N/A') }}
            @if(data_get($meta, 'solicitante.documento'))<div>{{ data_get($meta, 'solicitante.tipo_documento', 'Documento') }}: {{ data_get($meta, 'solicitante.documento') }}</div>@endif
        </div>
        <div>
            <h4 class="text-[10px] font-bold uppercase text-gray-500">Responsables y responsabilidades iniciales</h4>
            <div class="mt-2 space-y-2">
                @forelse($meta['responsables'] ?? [] as $responsable)
                    <div class="rounded-md border border-gray-200 p-2">
                        <div class="flex justify-between gap-2"><b>{{ $persona($responsable) }}</b><span>{{ !empty($responsable['activo']) ? 'Activo' : 'Inactivo' }}</span></div>
                        @forelse($responsable['tareas'] ?? [] as $asignacion)
                            <div class="mt-2 border-l-2 border-red-200 pl-2">
                                <b>→ {{ data_get($asignacion, 'tarea.descripcion') }}</b>
                                <div>Tipo: {{ data_get($asignacion, 'tarea.tipo') }} · Asignada por: {{ $persona($asignacion['actor'] ?? []) }}</div>
                                <div>{{ $fecha($asignacion['fecha_asignacion'] ?? null) }}</div>
                                @if(data_get($asignacion, 'ans.dias_restantes') !== null)<div>ANS restante: <b>{{ data_get($asignacion, 'ans.dias_restantes') }} días {{ data_get($asignacion, 'ans.tipo_dias') }}</b></div>@endif
                            </div>
                        @empty
                            <div class="mt-1 text-gray-500">Sin tareas iniciales.</div>
                        @endforelse
                    </div>
                @empty
                    <div class="rounded-md border border-dashed border-gray-300 p-2 text-gray-500">Responsables iniciales: Ninguno.</div>
                @endforelse
            </div>
        </div>
    @elseif($tipo === 'responsible_assigned')
        <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-3' }}">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Realizado por</span><b>{{ $persona($meta['actor'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsable</span><b>{{ $persona($meta['responsable'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Fecha/hora</span><b>{{ $fecha($meta['fecha_hora'] ?? null) }}</b></div>
        </div>
        <div><span class="rounded-full bg-green-50 px-2 py-1 text-[10px] font-bold text-green-700">ACTIVO</span>
            @if(!empty($meta['autoasignacion'])) <b class="ml-1">Autoasignación</b>@elseif(!empty($meta['reactivacion'])) <b class="ml-1">Reactivación</b>@endif
        </div>
    @elseif($tipo === 'task_assigned')
        <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-2' }}">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Asignada por</span><b>{{ $persona($meta['actor'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsable</span><b>{{ $persona($meta['responsable'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Tarea</span><b>{{ data_get($meta, 'tarea.descripcion') }}</b> · {{ data_get($meta, 'tarea.tipo') }}</div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Asignación</span><b>{{ $fecha($meta['fecha_asignacion'] ?? null) }}</b></div>
        </div>
    @elseif($tipo === 'task_completed')
        <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-3' }}">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Completada por</span><b>{{ $persona($meta['actor'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsable</span><b>{{ $persona($meta['responsable'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Tarea</span><b>{{ data_get($meta, 'tarea.descripcion') }}</b> · {{ data_get($meta, 'tarea.tipo') }}</div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Tiempo de atención</span><b>{{ $meta['tiempo_atencion'] ?? 'No disponible' }}</b> · {{ $meta['dias_habiles_transcurridos'] ?? 0 }} días hábiles</div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Asignada</span><b>{{ $fecha($meta['fecha_asignacion'] ?? null) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Completada</span><b>{{ $fecha($meta['fecha_finalizacion'] ?? null) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Resultado</span><b class="{{ ($meta['resultado'] ?? '') === 'FUERA DE ANS' ? 'text-red-700' : 'text-green-700' }}">{{ $meta['resultado'] ?? 'SIN ANS' }}</b></div>
        </div>
        @if(!empty($meta['observacion']))<div class="rounded-md bg-yellow-50 p-2"><b>Observación:</b> {{ $meta['observacion'] }}</div>@endif
    @elseif($tipo === 'responsible_reassigned')
        <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-3' }}">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsable anterior</span><b>{{ $persona($meta['responsable_anterior'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsable nuevo</span><b>{{ $persona($meta['responsable_nuevo'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Realizado por</span><b>{{ $persona($meta['actor'] ?? []) }}</b><div>{{ $fecha($meta['fecha_hora'] ?? null) }}</div></div>
        </div>
    @elseif($tipo === 'case_finalized')
        <div class="grid gap-2 {{ $compact ? '' : 'sm:grid-cols-3' }}">
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Finalizado por</span><b>{{ $persona($meta['actor'] ?? []) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Fecha finalización</span><b>{{ $fecha($meta['fecha_finalizacion'] ?? null) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Tiempo total</span><b>{{ $meta['tiempo_total'] ?? 'No disponible' }}</b> · {{ $meta['dias_habiles_totales'] ?? 0 }} días hábiles</div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Fecha solicitud</span><b>{{ $meta['fecha_solicitud'] ?? 'N/A' }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Responsables / tareas</span><b>{{ $meta['responsables_relacionados'] ?? 0 }} / {{ $meta['tareas_total'] ?? 0 }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Última tarea completada</span><b>{{ $fecha($meta['ultima_tarea_completada'] ?? null) }}</b></div>
            <div><span class="block text-[10px] font-bold uppercase text-gray-500">Resultado ANS</span><b class="{{ ($meta['resultado'] ?? '') === 'INCUMPLIDO' ? 'text-red-700' : 'text-green-700' }}">{{ $meta['resultado'] ?? 'SIN ANS' }}</b></div>
        </div>
    @elseif($tipo === 'consultant_note')
        <div class="rounded-md border border-blue-200 bg-blue-50 p-2">
            <span class="block text-[10px] font-bold uppercase text-blue-700">Anotación de seguimiento</span>
            <b>{{ $persona($meta['actor'] ?? []) }}</b>
            <p class="mt-1 whitespace-pre-line">{{ $meta['anotacion'] ?? '' }}</p>
        </div>
    @endif

    @if($ans)
        <div class="flex flex-wrap gap-x-4 gap-y-1 rounded-md bg-red-50 p-2 text-[11px]">
            <span><b>ANS:</b> {{ strtoupper($ans['estado'] ?? 'N/A') }}</span>
            @if(isset($ans['dias_configurados']))<span><b>Configurado:</b> {{ $ans['dias_configurados'] }} días {{ $ans['tipo_dias'] ?? '' }}</span>@endif
            <span><b>Inicio:</b> {{ $ans['fecha_inicio'] ?? 'N/A' }}</span>
            <span><b>Límite:</b> {{ $ans['fecha_limite'] ?? 'N/A' }}</span>
            @if(isset($meta['retraso_dias']) && $meta['retraso_dias'] !== null)<span class="font-bold text-red-700">Retraso: {{ $meta['retraso_dias'] }} días {{ $ans['tipo_dias'] ?? '' }}</span>
            @elseif(isset($ans['dias_restantes']))<span><b>Restante:</b> {{ $ans['dias_restantes'] }} días {{ $ans['tipo_dias'] ?? '' }}</span>@endif
        </div>
    @endif
</div>
@endif
