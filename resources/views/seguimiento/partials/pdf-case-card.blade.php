@php
    $ansClass = match($caso->ans_estado) { 'vencido', 'incumplido' => 'badge-danger', 'critico', 'preventivo' => 'badge-warning', 'vigente', 'cumplido' => 'badge-success', default => '' };
@endphp
<article class="case-card">
    <div class="case-card-head"><div><span class="cell-primary">{{ $caso->radicado }}</span><span class="cell-secondary">{{ $caso->tipo?->nombre }} · {{ $caso->subtipo?->nombre }}</span></div><div style="text-align:right"><span class="badge {{ $ansClass }}">{{ ucfirst($caso->ans_estado ?? 'Sin ANS') }}</span> <span class="badge">{{ $caso->estado }}</span></div></div>
    <div class="case-card-body"><div class="info-grid">
        <div class="info-block"><span class="info-label">Fechas y ANS</span>Solicitud: {{ $caso->fecha_solicitud?->format('d/m/Y') ?? 'N/D' }}<br>Límite: {{ $caso->ans_fecha_limite?->format('d/m/Y') ?? 'N/D' }}<span class="cell-secondary">{{ $caso->dias_restantes_calculados === null ? 'Sin cálculo' : ($caso->dias_restantes_calculados < 0 ? $caso->dias_retraso.' días de retraso' : $caso->dias_restantes_calculados.' días restantes') }}</span></div>
        <div class="info-block"><span class="info-label">Responsables y gestión</span>{{ $caso->usuarios->pluck('name')->join(', ') ?: 'Sin responsable activo' }}<span class="cell-secondary">{{ $caso->tareas_pendientes_count }} tarea(s) pendiente(s)</span></div>
        <div class="info-block"><span class="info-label">Pendiente más antiguo</span>{{ $caso->tareaPendienteMasAntigua?->descripcion ?? 'Sin tareas pendientes' }}@if($caso->tareaPendienteMasAntigua?->usuario)<span class="cell-secondary">{{ $caso->tareaPendienteMasAntigua->usuario->name }}</span>@endif</div>
        <div class="info-block"><span class="info-label">Último movimiento</span>{{ $caso->ultimo_movimiento?->format('d/m/Y H:i') ?? 'Sin movimientos' }}<span class="cell-secondary">{{ $caso->dias_sin_movimiento ?? 'N/D' }} días sin movimiento</span></div>
    </div></div>
</article>
