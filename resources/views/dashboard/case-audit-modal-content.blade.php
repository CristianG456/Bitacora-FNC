<div class='dashboard-audit-content' data-case-radicado='{{ $caso->radicado }}'>
    <section class='dashboard-audit-hero'>
        <div class='dashboard-audit-heading'>
            <div>
                <p class='dashboard-audit-eyebrow'>Consulta rápida</p>
                <h3>{{ $caso->radicado }}</h3>
                <p>{{ $caso->tipo?->nombre ?? 'Sin tipo' }} @if($caso->subtipo) · {{ $caso->subtipo->nombre }} @endif</p>
            </div>
            <span class='dashboard-audit-status'>{{ $caso->estado }}</span>
        </div>

        <div class='dashboard-audit-facts'>
            <div><span>Solicitante</span><strong>{{ $caso->solicitanteNombreActual() ?? 'No registrado' }}</strong></div>
            <div><span>Documento / NIT</span><strong>{{ $caso->solicitanteDocumentoActual() ?? 'No registrado' }}</strong></div>
            <div><span>Creación</span><strong>{{ \App\Support\LocalDate::inBogota($caso->created_at)?->format('d/m/Y · H:i') }}</strong></div>
            <div><span>Eventos</span><strong>{{ $eventos->count() }}</strong></div>
        </div>

        <div class='dashboard-audit-description'>
            <span>Resumen del caso</span>
            <p>{{ $caso->descripcion ?: 'Sin descripción registrada.' }}</p>
        </div>

        <div class='dashboard-audit-responsibles'>
            <span>Responsables relacionados</span>
            <div>
                @forelse($caso->usuarios as $responsable)
                    <span class='dashboard-audit-person'>
                        {{ $responsable->name }} · {{ $responsable->role?->nombre ?? 'Sin rol' }}
                        <small>{{ (bool) $responsable->pivot?->activo ? 'Activo' : 'Inactivo' }}</small>
                    </span>
                @empty
                    <span class='dashboard-audit-muted'>Sin responsables registrados.</span>
                @endforelse
            </div>
        </div>
    </section>

    <x-case-audit-summary :summary='$auditSummary' />

    <section class='dashboard-audit-events' aria-label='Bitácora cronológica completa'>
        <div class='dashboard-audit-section-title'>
            <div>
                <p class='dashboard-audit-eyebrow'>Trazabilidad</p>
                <h3>Bitácora completa</h3>
            </div>
            <span>{{ $eventos->count() }} evento(s)</span>
        </div>

        @forelse($eventos as $evento)
            @php
                $meta = is_array($evento->metadata) ? $evento->metadata : [];
                $actor = $meta['actor'] ?? [];
                $detalleCorreccion = \App\Support\TaskCorrectionAuditPresenter::make($evento);
            @endphp
            <article class='dashboard-audit-event' data-audit-event='{{ $evento->id }}'>
                <div class='dashboard-audit-event-head'>
                    <div>
                        <span class='dashboard-audit-action'>{{ $evento->accion }}</span>
                        @if($evento->modulo)<span class='dashboard-audit-module'>{{ $evento->modulo }}</span>@endif
                    </div>
                    <time>{{ \App\Support\LocalDate::inBogota($evento->created_at)?->format('d/m/Y · H:i') }}</time>
                </div>
                <p class='dashboard-audit-event-description'>{{ $evento->descripcion }}</p>
                <x-case-audit-details :event='$evento' compact />
                @include('components.task-correction-audit-details', ['detalle' => $detalleCorreccion, 'compacto' => true])
                <div class='dashboard-audit-actor'>
                    {{ $actor['nombre'] ?? $evento->usuario?->name ?? 'Sistema' }}
                    · {{ $actor['rol'] ?? $evento->usuario?->role?->nombre ?? 'Sistema' }}
                </div>
            </article>
        @empty
            <div class='dashboard-audit-empty'>No hay eventos registrados para este caso.</div>
        @endforelse
    </section>
</div>
