<div class="event-details">
    @if(!empty($detalle['tarea']))<p><strong>Tarea:</strong> “{{ $detalle['tarea'] }}”</p>@endif
    @if(!empty($detalle['solicitante']))<p><strong>Solicitado por:</strong> {{ $detalle['solicitante'] }}</p>@endif
    @if(!empty($detalle['motivo']))<p><strong>Motivo:</strong> {{ $detalle['motivo'] }}</p>@endif
    @if($detalle['accion'] === 'solicitar corrección' && filled($detalle['observacion_original']))<p><strong>Observación original:</strong> {{ $detalle['observacion_original'] }}</p>@endif
    @if(!empty($detalle['autorizador']))<p><strong>{{ $detalle['accion'] === 'rechazar corrección' ? 'Rechazado por:' : 'Autorizado por:' }}</strong> {{ $detalle['autorizador'] }}</p>@endif
    @if(!empty($detalle['motivo_rechazo']))<p><strong>Motivo del rechazo:</strong> {{ $detalle['motivo_rechazo'] }}</p>@endif
    @if(!empty($detalle['resultado']))<p><strong>Resultado:</strong> {{ $detalle['resultado'] }}</p>@endif
    @foreach($detalle['cambios'] ?? [] as $cambio)
        <div class="change-card"><strong>{{ $cambio['campo'] }}</strong><br>Anterior: {{ filled($cambio['anterior']) ? $cambio['anterior'] : 'Sin valor' }}<br>Nuevo: {{ filled($cambio['nuevo']) ? $cambio['nuevo'] : 'Sin valor' }}</div>
    @endforeach
</div>
