<div class="tracking-attention-list">
@forelse($casosAtencion->take($limite ?? 8) as $caso)
    @php
        $nivel = match($caso->ans_estado) {
            'vencido', 'incumplido' => ['Vencido','bg-red-50 text-red-800 border-red-200'],
            'critico' => ['Crítico','bg-orange-50 text-orange-800 border-orange-200'],
            'preventivo' => ['Preventivo','bg-amber-50 text-amber-800 border-amber-200'],
            default => ['Tarea pendiente','bg-gray-50 text-gray-700 border-gray-200']
        };
    @endphp
    <article class="tracking-attention-card">
        <div>
            <span class="inline-flex px-2 py-1 rounded-full border text-[11px] font-bold {{ $nivel[1] }}">{{ $nivel[0] }}</span>
            <a href="{{ route('casos.show', $caso) }}" class="block mt-2 font-bold text-[#9f1024] hover:underline">{{ $caso->radicado }}</a>
            <p class="text-xs text-gray-500 mt-1 truncate">{{ $caso->tipo?->nombre }}</p>
        </div>
        <div class="grid sm:grid-cols-3 gap-3 text-xs">
            <div><p class="tracking-case-label">Responsable</p><p class="font-semibold text-gray-700">{{ $caso->tareaPendienteMasAntigua?->usuario?->name ?? $caso->usuarios->pluck('name')->join(', ') ?: 'Sin responsable' }}</p></div>
            <div><p class="tracking-case-label">Fecha límite</p><p class="font-semibold text-gray-700">{{ $caso->ans_fecha_limite?->format('d/m/Y') ?? 'N/D' }}</p><p class="mt-1 {{ $caso->dias_restantes_calculados < 0 ? 'text-red-700 font-bold' : 'text-gray-500' }}">{{ $caso->dias_restantes_calculados === null ? 'Sin cálculo' : ($caso->dias_restantes_calculados < 0 ? $caso->dias_retraso.' días de retraso' : $caso->dias_restantes_calculados.' días restantes') }}</p></div>
            <div><p class="tracking-case-label">Último movimiento</p><p class="font-semibold text-gray-700">{{ $caso->ultimo_movimiento?->format('d/m/Y H:i') ?? 'Sin movimientos' }}</p><p class="mt-1 text-gray-500">{{ $caso->dias_sin_movimiento ?? 'N/D' }} días sin movimiento</p></div>
        </div>
        <div class="tracking-attention-secondary text-xs">
            <p class="tracking-case-label">Pendiente más antiguo</p>
            <p class="leading-5 text-gray-700">{{ $caso->tareaPendienteMasAntigua?->descripcion ?? 'Sin tarea pendiente' }}</p>
        </div>
    </article>
@empty
    <div class="tracking-empty" role="status">
        <span class="tracking-empty-icon" aria-hidden="true">✓</span>
        <span><strong>Sin alertas inmediatas.</strong> No hay casos críticos o vencidos para los filtros aplicados.</span>
    </div>
@endforelse
</div>