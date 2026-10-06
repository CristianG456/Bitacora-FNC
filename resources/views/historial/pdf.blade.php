@extends('pdf.layouts.institucional', ['tituloPdf' => 'Historial Global del Sistema'])
@section('content')
<div class='report-hero'><div class='report-hero-main'><div class='eyebrow'>Sistema de Gestión de Casos Jurídicos</div><h1>Historial Global del Sistema</h1></div><div class='report-hero-meta'><strong>Bitácora de casos finalizados</strong><br>Generada el {{ \App\Support\LocalDate::inBogota(now())?->format('d/m/Y · H:i') }}<br>America/Bogota</div></div>
<div class='filters'><strong>Filtros aplicados</strong><br><span class='filter-item'><b>Radicado/solicitante:</b> {{ request('radicado', 'Todos') }}</span><span class='filter-item'><b>Evento:</b> {{ request('evento', 'Todos') }}</span><span class='filter-item'><b>Usuario:</b> {{ request('usuario_id', 'Todos') }}</span><span class='filter-item'><b>Tipo:</b> {{ request('tipo_id', 'Todos') }}</span><span class='filter-item'><b>Caso:</b> {{ request('caso_id', 'Todos') }}</span></div>
<section class='section'><div class='section-heading'><h2>Trazabilidad de eventos</h2><span>{{ $eventos->count() }} evento(s)</span></div>
@forelse($eventos as $evento)
<article class={!! chr(34) !!}event-card{!! chr(34) !!}>
    <div class='event-card-head'><div><span class='badge'>{{ $evento->accion }}</span> <span class='cell-primary'>{{ $evento->caso?->radicado ?? 'N/A' }}</span><span class='cell-secondary'>{{ $evento->modulo }}</span></div><div style='text-align:right'><span class='cell-primary'>{{ \App\Support\LocalDate::inBogota($evento->created_at)?->format('d/m/Y') }}</span><span class='cell-secondary'>{{ \App\Support\LocalDate::inBogota($evento->created_at)?->format('H:i') }} · America/Bogota</span></div></div>
    <div class='event-card-body'><div class='event-description'>{{ $evento->descripcion }}</div>
        @include('pdf.partials.audit-human-details', ['evento' => $evento])
    </div>
</article>
@empty<div class='event-card'><div class='empty'>No hay eventos con los filtros seleccionados.</div></div>@endforelse
</section>
@endsection
