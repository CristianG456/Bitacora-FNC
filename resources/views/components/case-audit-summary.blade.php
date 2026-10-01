@props(['summary', 'compact' => false])

<section class="rounded-xl border border-gray-200 bg-white p-4 {{ $compact ? 'text-xs' : '' }}" data-audit-summary>
    <div class="flex items-center justify-between gap-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-gray-900">Resumen del caso</h2>
        @if(!empty($summary['ans_estado']))
            <span class="rounded-full bg-red-50 px-2.5 py-1 text-[10px] font-bold uppercase text-red-700">ANS {{ $summary['ans_estado'] }}</span>
        @endif
    </div>
    <div class="mt-3 grid grid-cols-2 gap-3 {{ $compact ? '' : 'sm:grid-cols-4 lg:grid-cols-7' }}">
        @foreach([
            'Fecha límite' => $summary['fecha_limite'] ? \Carbon\Carbon::parse($summary['fecha_limite'])->format('d/m/Y') : 'Sin ANS',
            'Responsables' => $summary['responsables'],
            'Tareas' => $summary['tareas_total'],
            'Completadas' => $summary['completadas'],
            'Pendientes' => $summary['pendientes'],
            'A tiempo' => $summary['a_tiempo'],
            'Fuera de ANS' => $summary['fuera_ans'],
        ] as $label => $value)
            <div class="rounded-lg bg-gray-50 p-2.5">
                <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</div>
                <div class="mt-1 font-bold text-gray-900">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <details class="mt-3 border-t border-gray-100 pt-3">
        <summary class="cursor-pointer text-xs font-bold text-gray-700">Responsables relacionados</summary>
        <div class="mt-3 grid gap-3 {{ $compact ? '' : 'md:grid-cols-2 xl:grid-cols-3' }}">
            @forelse($summary['por_responsable'] as $responsable)
                <div class="rounded-lg border border-gray-200 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div><div class="font-bold text-gray-900">{{ $responsable['nombre'] }}</div><div class="text-[11px] text-gray-500">{{ $responsable['rol'] }}</div></div>
                        <span class="rounded-full px-2 py-0.5 text-[9px] font-bold uppercase {{ $responsable['activo'] ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $responsable['activo'] ? 'Activo' : 'Inactivo' }}</span>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] text-gray-600">
                        <span>Asignadas: <b>{{ $responsable['asignadas'] }}</b></span>
                        <span>Pendientes: <b>{{ $responsable['pendientes'] }}</b></span>
                        <span>Fuera de ANS: <b>{{ $responsable['fuera_ans'] }}</b></span>
                        @if($responsable['tiempo_promedio'])<span class="col-span-2">Promedio atención: <b>{{ $responsable['tiempo_promedio'] }}</b></span>@endif
                    </div>
                </div>
            @empty
                <p class="text-xs text-gray-500">Ninguno.</p>
            @endforelse
        </div>
    </details>
</section>
