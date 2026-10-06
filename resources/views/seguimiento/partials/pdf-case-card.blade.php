@php
    $ansClass = match($caso->ans_estado) { 'vencido', 'incumplido' => 'badge-danger', 'critico', 'preventivo' => 'badge-warning', 'vigente', 'cumplido' => 'badge-success', default => '' };
    $asunto = filled(trim((string) $caso->descripcion)) ? trim((string) $caso->descripcion) : 'Sin asunto registrado';
@endphp
<article class='case-card'>
    <div class='case-card-head'>
        <div class='case-card-identity'>
            <span class='cell-primary'>{{ $caso->radicado }}</span>
            <div class='case-subject'>
                <span class='case-subject-label'>ASUNTO</span>
                <span class='case-subject-value'>{{ $asunto }}</span>
            </div>
            <div class='case-type'>
                <span class='case-type-label'>TIPO / SUBTIPO</span>
                <span class='case-type-value'>{{ $caso->tipo?->nombre }} · {{ $caso->subtipo?->nombre }}</span>
            </div>
        </div>
        <div class='case-card-status'><span class='badge {{ $ansClass }}'>{{ ucfirst($caso->ans_estado ?? 'Sin ANS') }}</span> <span class='badge'>{{ $caso->estado }}</span></div>
    </div>
    <div class='case-card-body'><div class='info-grid'>
        <div class='info-block'><span class='info-label'>Fechas y ANS</span>Solicitud: {{ $caso->fecha_solicitud?->format('d/m/Y') ?? 'N/D' }}<br>Límite: {{ $caso->ans_fecha_limite?->format('d/m/Y') ?? 'N/D' }}<span class='cell-secondary'>{{ $caso->dias_restantes_calculados === null ? 'Sin cálculo' : ($caso->dias_restantes_calculados < 0 ? $caso->dias_retraso.' días de retraso' : $caso->dias_restantes_calculados.' días restantes') }}</span></div>
        <div class='info-block'><span class='info-label'>Responsables y gestión</span>{{ $caso->usuarios->pluck('name')->join(', ') ?: 'Sin responsable activo' }}<span class='cell-secondary'>{{ $caso->tareas_pendientes_count }} tarea(s) pendiente(s)</span></div>
        <div class='info-block'><span class='info-label'>Pendiente más antiguo</span>{{ $caso->tareaPendienteMasAntigua?->descripcion ?? 'Sin tareas pendientes' }}@if($caso->tareaPendienteMasAntigua?->usuario)<span class='cell-secondary'>{{ $caso->tareaPendienteMasAntigua->usuario->name }}</span>@endif</div>
        <div class='info-block'><span class='info-label'>Último movimiento</span>{{ $caso->ultimo_movimiento?->format('d/m/Y H:i') ?? 'Sin movimientos' }}<span class='cell-secondary'>{{ $caso->dias_sin_movimiento ?? 'N/D' }} días sin movimiento</span></div>
    </div></div>
</article>
