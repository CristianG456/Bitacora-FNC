@extends('layouts.app')
@section('title', 'Reportes de gestión')
@push('styles')
<link rel="stylesheet" href="/css/seguimiento-ui.css?v={{ filemtime(public_path('css/seguimiento-ui.css')) }}">
@endpush
@section('content')
<div class="tracking-shell max-w-7xl mx-auto">
    <header class="tracking-hero">
        <div>
            <p class="tracking-eyebrow">Informe consolidado</p>
            <h1 class="tracking-title">Reportes de gestión</h1>
            <p class="tracking-lead">Lectura ejecutiva con detalle verificable. PDF y Excel incluyen todo el resultado filtrado.</p>
        </div>
        <div class="tracking-actions">
            <a class="btn-secondary" href="{{ route('seguimiento.index', request()->except('casos_page')) }}">Seguimiento</a>
            <a class="btn-secondary" href="{{ route('seguimiento.exportar.excel', request()->except('casos_page')) }}">Excel</a>
            <a class="btn-primary" target="_blank" href="{{ route('seguimiento.exportar.pdf', request()->except('casos_page')) }}">PDF</a>
        </div>
    </header>

    @include('seguimiento.partials.filtros', ['action' => route('seguimiento.reportes')])
    @include('seguimiento.partials.resumen')
    @include('seguimiento.partials.ans')

    <section aria-labelledby="resultados-tipo">
        <div class="tracking-section-head"><div><h2 id="resultados-tipo">Resultados por tipo</h2><p>Estados principales y consolidado ANS por proceso.</p></div></div>
        <div class="tracking-card-grid">
            @forelse($porTipo->filter(fn ($fila) => $fila->total) as $fila)
                <article class="tracking-type-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><h3 class="font-bold text-sm text-gray-900 truncate">{{ $fila->tipo->nombre }}</h3><p class="text-[11px] text-gray-400 mt-1">Distribución del estado</p></div>
                        <a class="text-xl font-bold text-[#9f1024] hover:underline" href="{{ route('seguimiento.reportes', array_merge(request()->except('casos_page'), ['tipo_ids' => [$fila->tipo->id]])) }}">{{ $fila->total }}</a>
                    </div>
                    <div class="grid grid-cols-2 gap-1.5 mt-3 text-[11px]">
                        @foreach($fila->estados as $estado => $cantidad)
                            <a href="{{ route('seguimiento.reportes', array_merge(request()->except('casos_page'), ['tipo_ids' => [$fila->tipo->id], 'estado' => $estado])) }}" class="rounded-md border border-gray-100 bg-gray-50 px-2.5 py-1.5 flex justify-between hover:bg-red-50 hover:border-red-100"><span>{{ $estado }}</span><strong>{{ $cantidad }}</strong></a>
                        @endforeach
                    </div>
                    <div class="mt-3 pt-3 border-t border-gray-100 flex flex-wrap gap-1.5 text-[10px]">
                        <span class="rounded-full bg-amber-50 text-amber-800 px-2 py-1">En riesgo {{ $fila->ans['preventivo'] + $fila->ans['critico'] }}</span>
                        <span class="rounded-full bg-red-50 text-red-800 px-2 py-1">Vencidos {{ $fila->ans['vencido'] + $fila->ans['incumplido'] }}</span>
                        <span class="rounded-full bg-green-50 text-green-800 px-2 py-1">Cumplidos {{ $fila->cumplidos }}</span>
                    </div>
                </article>
            @empty
                <div class="tracking-empty md:col-span-2 xl:col-span-3"><span class="tracking-empty-icon" aria-hidden="true">—</span><span>No hay resultados para los filtros aplicados.</span></div>
            @endforelse
        </div>
    </section>

    @include('seguimiento.partials.responsables')

    <section aria-labelledby="casos-atencion">
        <div class="tracking-section-head"><div><h2 id="casos-atencion">Casos que requieren atención</h2><p>Prioridades operativas del conjunto filtrado.</p></div></div>
        @include('seguimiento.partials.atencion', ['limite' => 8])
    </section>

    <section aria-labelledby="detalle-casos">
        <div class="tracking-section-head"><div><h2 id="detalle-casos">Detalle de casos</h2><p>{{ $resumen['total'] }} caso(s) en total; se muestran 10 por página.</p></div></div>
        @include('seguimiento.partials.casos')
    </section>
</div>
@endsection