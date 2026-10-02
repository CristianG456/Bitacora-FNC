@extends('layouts.app')
@section('title', 'Seguimiento y Reportes')
@push('styles')
<link rel='stylesheet' href='/css/seguimiento-ui.css?v={{ filemtime(public_path('css/seguimiento-ui.css')) }}'>
@endpush
@section('content')
@php
    $consulta = request()->except(['seccion', 'volver', 'casos_page']);
    $nombresSeccion = [
        'resumen' => 'Resumen ejecutivo',
        'tipos' => 'Casos por tipo',
        'responsables' => 'Seguimiento por responsable',
        'atencion' => 'Casos que requieren atención',
        'detalle' => 'Detalle de casos',
    ];
    $historialVolver = collect(explode(',', (string) request('volver')))
        ->filter(fn ($destino) => $destino === 'filters' || isset($nombresSeccion[$destino]))
        ->values();
    $volver = $historialVolver->shift();
    $parametrosVolver = $consulta;
    if ($volver && $volver !== 'filters') {
        $parametrosVolver['seccion'] = $volver;
    }
    if ($historialVolver->isNotEmpty()) {
        $parametrosVolver['volver'] = $historialVolver->implode(',');
    }
    $enlaceVolver = route('seguimiento.index', $parametrosVolver);
@endphp
<div class='tracking-shell max-w-7xl mx-auto'>
    <a class='tracking-content-back' href='{{ $seccion || $volver ? $enlaceVolver : route('dashboard') }}'>← Volver</a>
    @if(! $seccion)
        <header class='tracking-hero'>
            <div>
                <p class='tracking-eyebrow'>Vista ejecutiva</p>
                <h1 class='tracking-title'>Seguimiento y Reportes</h1>
                <p class='tracking-lead'>Aplica filtros una vez y consulta cada resultado en una vista clara, sin perder el contexto.</p>
            </div>
            <div class='tracking-actions'>
                <a class='btn-secondary' href='{{ route('seguimiento.exportar.excel', $consulta) }}'>Excel</a>
                <a class='btn-primary' target='_blank' href='{{ route('seguimiento.exportar.pdf', $consulta) }}'>PDF</a>
            </div>
        </header>

        @include('seguimiento.partials.filtros', ['action' => route('seguimiento.index')])

    @else
        <header class='tracking-submodule-head'>
            <div>
                <nav class='tracking-breadcrumb' aria-label='Miga de pan'><a href='{{ route('seguimiento.index', $consulta) }}'>Seguimiento y Reportes</a><span aria-hidden='true'>›</span><span>{{ $nombresSeccion[$seccion] }}</span></nav>
                <h1 class='tracking-title'>{{ $nombresSeccion[$seccion] }}</h1>
            </div>
            <div class='tracking-actions'>
                <a class='btn-secondary' href='{{ route('seguimiento.index', array_merge($consulta, ['volver' => $seccion])) }}'>Modificar filtros</a>
                <a class='btn-secondary' href='{{ route('seguimiento.exportar.excel', $consulta) }}'>Excel</a>
                <a class='btn-primary' target='_blank' href='{{ route('seguimiento.exportar.pdf', $consulta) }}'>PDF</a>
            </div>
        </header>

        <section class='tracking-active-filters' aria-label='Filtros activos'>
            <strong>Filtros activos</strong>
            <div>
                @forelse(collect($filtrosAplicados)->reject(fn ($valor) => in_array($valor, ['Todos', 'Todos a Todos'], true)) as $nombre => $valor)
                    <span class='tracking-chip'>{{ $nombre }}: {{ $valor }}</span>
                @empty
                    <span class='text-sm text-gray-500'>Sin filtros adicionales.</span>
                @endforelse
            </div>
        </section>

        @if($seccion === 'resumen')
            @include('seguimiento.partials.resumen')
            @include('seguimiento.partials.ans')
        @elseif($seccion === 'tipos')
            <section aria-labelledby='casos-por-tipo'>
                <div class='tracking-section-head'><div><h2 id='casos-por-tipo'>Casos por tipo</h2><p>Distribución compacta del conjunto filtrado.</p></div></div>
                <div class='tracking-card-grid'>
                    @forelse($porTipo->filter(fn ($fila) => $fila->total) as $fila)
                        <a href='{{ route('seguimiento.index', array_merge($consulta, ['seccion' => 'detalle', 'volver' => 'tipos', 'tipo_ids' => [$fila->tipo->id]])) }}' class='tracking-type-card'>
                            <div class='flex items-start justify-between gap-3'><div class='min-w-0'><p class='font-bold text-sm text-gray-900 truncate'>{{ $fila->tipo->nombre }}</p><p class='text-[11px] text-gray-400 mt-1'>Casos del tipo</p></div><strong class='text-xl text-[#9f1024]'>{{ $fila->total }}</strong></div>
                            <div class='mt-3 flex flex-wrap gap-1.5 text-[11px]'><span class='bg-gray-50 border border-gray-100 rounded-full px-2 py-1'>Activos {{ $fila->estados['Pendiente'] + $fila->estados['En proceso'] }}</span><span class='bg-red-50 border border-red-100 text-red-700 rounded-full px-2 py-1'>Vencidos {{ $fila->ans['vencido'] + $fila->ans['incumplido'] }}</span></div>
                        </a>
                    @empty
                        <div class='tracking-empty md:col-span-2 xl:col-span-3'>No hay tipos con resultados.</div>
                    @endforelse
                </div>
            </section>
        @elseif($seccion === 'responsables')
            @include('seguimiento.partials.responsables')
        @elseif($seccion === 'atencion')
            <section aria-labelledby='casos-atencion'><div class='tracking-section-head'><div><h2 id='casos-atencion'>Casos que requieren atención</h2><p>Prioridad por vencimiento, criticidad y antigüedad de tareas.</p></div></div>@include('seguimiento.partials.atencion', ['limite' => 8])</section>
        @elseif($seccion === 'detalle')
            <section aria-labelledby='detalle-casos'><div class='tracking-section-head'><div><h2 id='detalle-casos'>Detalle de casos</h2><p>Información verificable del resultado filtrado.</p></div></div>@include('seguimiento.partials.casos')</section>
        @endif
    @endif
</div>
@endsection
