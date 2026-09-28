@extends('layouts.app')
@section('title', 'Seguimiento y Reportes')
@push('styles')
<link rel="stylesheet" href="/css/seguimiento-ui.css?v={{ filemtime(public_path('css/seguimiento-ui.css')) }}">
@endpush
@section('content')
<div class="tracking-shell max-w-7xl mx-auto">
    <header class="tracking-hero">
        <div>
            <p class="tracking-eyebrow">Vista ejecutiva</p>
            <h1 class="tracking-title">Seguimiento y Reportes</h1>
            <p class="tracking-lead">Prioridades, carga operativa y cumplimiento de ANS en una vista ordenada para facilitar decisiones rápidas.</p>
        </div>
        <div class="tracking-actions">
            <a class="btn-secondary" href="{{ route('seguimiento.reportes', request()->except('casos_page')) }}">Reportes</a>
            <a class="btn-secondary" href="{{ route('seguimiento.exportar.excel', request()->except('casos_page')) }}">Excel</a>
            <a class="btn-primary" target="_blank" href="{{ route('seguimiento.exportar.pdf', request()->except('casos_page')) }}">PDF</a>
        </div>
    </header>

    @include('seguimiento.partials.filtros', ['action' => route('seguimiento.index')])
    @include('seguimiento.partials.resumen')
    @include('seguimiento.partials.ans')

    <section aria-labelledby="casos-por-tipo">
        <div class="tracking-section-head">
            <div><h2 id="casos-por-tipo">Casos por tipo</h2><p>Distribución compacta del conjunto filtrado.</p></div>
            <p class="hidden sm:block">Selecciona una tarjeta para filtrar</p>
        </div>
        <div class="tracking-card-grid">
            @forelse($porTipo->filter(fn ($fila) => $fila->total) as $fila)
                <a href="{{ route('seguimiento.index', array_merge(request()->except('casos_page'), ['tipo_ids' => [$fila->tipo->id]])) }}" class="tracking-type-card">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="font-bold text-sm text-gray-900 truncate">{{ $fila->tipo->nombre }}</p><p class="text-[11px] text-gray-400 mt-1">Casos del tipo</p></div>
                        <strong class="text-xl text-[#9f1024]">{{ $fila->total }}</strong>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                        <span class="bg-gray-50 border border-gray-100 rounded-full px-2 py-1">Activos {{ $fila->estados['Pendiente'] + $fila->estados['En proceso'] }}</span>
                        <span class="bg-red-50 border border-red-100 text-red-700 rounded-full px-2 py-1">Vencidos {{ $fila->ans['vencido'] + $fila->ans['incumplido'] }}</span>
                    </div>
                </a>
            @empty
                <div class="tracking-empty md:col-span-2 xl:col-span-3"><span class="tracking-empty-icon" aria-hidden="true">—</span><span>No hay tipos con resultados.</span></div>
            @endforelse
        </div>
    </section>

    @include('seguimiento.partials.responsables')

    <section aria-labelledby="casos-atencion">
        <div class="tracking-section-head"><div><h2 id="casos-atencion">Casos que requieren atención</h2><p>Prioridad por vencimiento, criticidad y antigüedad de tareas.</p></div></div>
        @include('seguimiento.partials.atencion', ['limite' => 8])
    </section>

    <section aria-labelledby="detalle-casos">
        <div class="tracking-section-head"><div><h2 id="detalle-casos">Detalle de casos</h2><p>Información verificable y fácil de escanear del resultado filtrado.</p></div></div>
        @include('seguimiento.partials.casos')
    </section>
</div>
@endsection