@extends('pdf.layouts.institucional', ['tituloPdf' => 'Historial Global del Sistema'])
@section('content')
<div class="report-hero"><div class="report-hero-main"><div class="eyebrow">Sistema de Gestión de Casos Jurídicos</div><h1>Historial Global del Sistema</h1></div><div class="report-hero-meta"><strong>Bitácora de casos finalizados</strong><br>Generada el {{ \App\Support\LocalDate::inBogota(now())?->format('d/m/Y · H:i') }}<br>America/Bogota</div></div>
<div class="filters"><strong>Filtros aplicados</strong><br><span class="filter-item"><b>Radicado/solicitante:</b> {{ request('radicado', 'Todos') }}</span><span class="filter-item"><b>Evento:</b> {{ request('evento', 'Todos') }}</span><span class="filter-item"><b>Usuario:</b> {{ request('usuario_id', 'Todos') }}</span><span class="filter-item"><b>Tipo:</b> {{ request('tipo_id', 'Todos') }}</span><span class="filter-item"><b>Caso:</b> {{ request('caso_id', 'Todos') }}</span></div>
<section class="section"><div class="section-heading"><h2>Trazabilidad de eventos</h2><span>{{ $eventos->count() }} evento(s)</span></div>
@forelse($eventos as $evento)
@php($detalleCorreccion = \App\Support\TaskCorrectionAuditPresenter::make($evento))
<article class="event-card">
    <div class="event-card-head"><div><span class="badge">{{ $evento->accion }}</span> <span class="cell-primary">{{ $evento->caso?->radicado ?? 'N/A' }}</span><span class="cell-secondary">{{ $evento->modulo }}</span></div><div style="text-align:right"><span class="cell-primary">{{ \App\Support\LocalDate::inBogota($evento->created_at)?->format('d/m/Y') }}</span><span class="cell-secondary">{{ \App\Support\LocalDate::inBogota($evento->created_at)?->format('H:i') }} · America/Bogota</span></div></div>
    <div class="event-card-body"><div class="event-description">{{ $evento->descripcion }}</div>
    @if($detalleCorreccion) @include('pdf.partials.audit-details', ['detalle' => $detalleCorreccion])
    @elseif(is_array($evento->metadata) && count($evento->metadata))<div class="metadata">@foreach($evento->metadata as $clave => $valor)<span class="meta-item"><span class="meta-label">{{ $clave }}:</span> {{ is_array($valor) ? json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $valor }}</span>@endforeach</div>@endif
    <div class="info-grid" style="margin-top:7px"><div class="info-block"><span class="info-label">Actor</span><span class="cell-primary">{{ $evento->usuario?->name ?? 'Sistema' }}</span></div><div class="info-block"><span class="info-label">Rol</span><span class="badge">{{ $evento->usuario?->role?->nombre ?? 'N/A' }}</span></div></div></div>
</article>
@empty<div class="event-card"><div class="empty">No hay eventos con los filtros seleccionados.</div></div>@endforelse
</section>
@endsection
