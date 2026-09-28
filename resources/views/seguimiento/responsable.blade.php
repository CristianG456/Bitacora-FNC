@extends('layouts.app')
@section('title', 'Seguimiento por responsable')
@push('styles')
<link rel="stylesheet" href="/css/seguimiento-ui.css?v={{ filemtime(public_path('css/seguimiento-ui.css')) }}">
@endpush
@section('content')
<div class="tracking-shell max-w-7xl mx-auto">
    <header class="tracking-hero">
        <div>
            <p class="tracking-eyebrow">Detalle operativo</p>
            <h1 class="tracking-title">{{ $seguimiento->usuario->name }}</h1>
            <p class="tracking-lead">{{ $seguimiento->usuario->role?->nombre }} · evidencia operativa, sin inferir causas.</p>
        </div>
        <a class="btn-secondary" href="{{ route('seguimiento.index', request()->except('casos_page')) }}">Volver a Seguimiento</a>
    </header>

    <section aria-labelledby="resumen-responsable">
        <div class="tracking-section-head"><div><h2 id="resumen-responsable">Resumen del responsable</h2><p>Carga actual y alertas asociadas.</p></div></div>
        <div class="tracking-metrics">
            @foreach([
                ['Casos activos', $seguimiento->casos_activos, '#2563a6', 'Responsabilidad vigente'],
                ['Tareas pendientes', $seguimiento->tareas_pendientes, '#a70f27', 'Por completar'],
                ['Tareas completadas', $seguimiento->tareas_completadas, '#26734d', 'Gestiones cerradas'],
                ['En riesgo ANS', $seguimiento->casos_proximos, '#a16207', 'Requieren seguimiento'],
                ['ANS vencidos', $seguimiento->casos_vencidos, '#b42318', 'Fuera de plazo']
            ] as [$label, $valor, $accent, $contexto])
                <article class="tracking-metric" style="--metric-accent: {{ $accent }}"><p class="tracking-metric-label">{{ $label }}</p><p class="tracking-metric-value">{{ $valor }}</p><p class="tracking-metric-context">{{ $contexto }}</p></article>
            @endforeach
        </div>
    </section>

    <section aria-labelledby="casos-responsable">
        <div class="tracking-section-head"><div><h2 id="casos-responsable">Casos asociados</h2><p>Detalle verificable de su gestión.</p></div></div>
        @include('seguimiento.partials.casos', ['casos' => $seguimiento->casos])
    </section>
</div>
@endsection