<div class="tracking-case-grid">
@forelse($casos as $caso)
    @php
        $ansClasses = match($caso->ans_estado) {
            'vencido', 'incumplido' => 'bg-red-50 text-red-800 border-red-200',
            'critico' => 'bg-orange-50 text-orange-800 border-orange-200',
            'preventivo' => 'bg-amber-50 text-amber-800 border-amber-200',
            'vigente', 'cumplido' => 'bg-green-50 text-green-800 border-green-200',
            default => 'bg-gray-50 text-gray-700 border-gray-200',
        };
    @endphp
    <article class="tracking-case-card">
        <div class="tracking-case-head">
            <div class="min-w-0">
                <a class="font-bold text-[#9f1024] hover:underline" href="{{ route('casos.show', $caso) }}">{{ $caso->radicado }}</a>
                <p class="text-xs text-gray-500 mt-1 truncate">{{ $caso->tipo?->nombre }} · {{ $caso->subtipo?->nombre }}</p>
            </div>
            <div class="flex flex-wrap gap-1.5">
                <span class="px-2 py-1 rounded-full border text-[11px] font-semibold {{ $ansClasses }}">{{ ucfirst($caso->ans_estado ?? 'Sin ANS') }}</span>
                <span class="px-2 py-1 rounded-full border border-gray-200 bg-gray-50 text-gray-700 text-[11px] font-semibold">{{ $caso->estado }}</span>
            </div>
        </div>
        <div class="tracking-case-body">
            <div>
                <p class="tracking-case-label">Resumen del caso</p>
                <p><b>Solicitud:</b> {{ $caso->fecha_solicitud?->format('d/m/Y') ?? 'Sin fecha' }}</p>
                <p class="mt-1"><b>Límite:</b> {{ $caso->ans_fecha_limite?->format('d/m/Y') ?? 'N/D' }}</p>
                @if($caso->dias_restantes_calculados !== null)
                    <p class="mt-1 {{ $caso->dias_restantes_calculados < 0 ? 'text-red-700 font-bold' : 'text-gray-500' }}">{{ $caso->dias_restantes_calculados < 0 ? $caso->dias_retraso.' días de retraso' : $caso->dias_restantes_calculados.' días restantes' }}</p>
                @endif
            </div>
            <div>
                <p class="tracking-case-label">Gestión</p>
                <p><b>Responsables:</b> {{ $caso->usuarios->pluck('name')->join(', ') ?: 'Sin responsable activo' }}</p>
                <p class="mt-1"><b>Pendientes:</b> {{ $caso->tareas_pendientes_count }}</p>
                <p class="mt-1 text-gray-500"><b>Último movimiento:</b> {{ $caso->ultimo_movimiento?->format('d/m/Y H:i') ?? 'Sin movimientos' }}</p>
            </div>
        </div>
        @if($caso->tareaPendienteMasAntigua)
            <div class="tracking-task-note">
                <b>Tarea pendiente más antigua:</b> {{ $caso->tareaPendienteMasAntigua->descripcion }}
                <span class="block text-gray-500 mt-1">{{ $caso->tareaPendienteMasAntigua->usuario?->name ?? 'Sin responsable' }} · desde {{ ($caso->tareaPendienteMasAntigua->fecha_inicio ?? $caso->tareaPendienteMasAntigua->created_at)?->format('d/m/Y H:i') }}</span>
            </div>
        @endif
    </article>
@empty
    <div class="tracking-empty xl:col-span-2"><span class="tracking-empty-icon" aria-hidden="true">—</span><span>No hay casos que coincidan con los filtros.</span></div>
@endforelse
</div>

@if($casos instanceof \Illuminate\Pagination\LengthAwarePaginator)
    <div class="tracking-pagination">
        <p class="text-sm text-gray-500">Mostrando <strong class="text-gray-800">{{ $casos->firstItem() ?? 0 }}-{{ $casos->lastItem() ?? 0 }}</strong> de <strong class="text-gray-800">{{ $casos->total() }}</strong> casos. <span class="hidden sm:inline">Las exportaciones incluyen todo el resultado filtrado.</span></p>
        @if($casos->hasPages())<div>{{ $casos->onEachSide(1)->links() }}</div>@endif
    </div>
@endif