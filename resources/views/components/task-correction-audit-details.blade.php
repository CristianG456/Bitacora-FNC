@if($detalle)
    <div class="mt-2 space-y-1.5 rounded border border-blue-100 bg-blue-50 p-2 text-gray-700 {{ ($compacto ?? false) ? 'text-[11px]' : 'text-xs' }}">
        @if(!empty($detalle['tarea']))
            <p><strong>Tarea:</strong> “{{ $detalle['tarea'] }}”</p>
        @endif
        @if(!empty($detalle['solicitante']))
            <p><strong>Solicitado por:</strong> {{ $detalle['solicitante'] }}</p>
        @endif
        @if(!empty($detalle['motivo']))
            <p><strong>Motivo de la solicitud:</strong> {{ $detalle['motivo'] }}</p>
        @endif
        @if($detalle['accion'] === 'solicitar corrección' && filled($detalle['observacion_original']))
            <p><strong>Observación actual/original:</strong> {{ $detalle['observacion_original'] }}</p>
        @endif
        @if(!empty($detalle['autorizador']))
            <p><strong>{{ $detalle['accion'] === 'rechazar corrección' ? 'Rechazado por:' : 'Autorizado por:' }}</strong> {{ $detalle['autorizador'] }}</p>
        @endif
        @if(!empty($detalle['motivo_rechazo']))
            <p><strong>Motivo del rechazo:</strong> {{ $detalle['motivo_rechazo'] }}</p>
        @endif
        @if(!empty($detalle['resultado']))
            <p><strong>Resultado:</strong> {{ $detalle['resultado'] }}</p>
        @endif
        @if(!empty($detalle['cambios']))
            <div class="space-y-2 pt-1">
                <p class="font-bold text-gray-800">Cambios realizados:</p>
                @foreach($detalle['cambios'] as $cambio)
                    <div class="rounded border border-gray-200 bg-white p-2">
                        <p class="font-bold text-gray-800">{{ $cambio['campo'] }}</p>
                        <p><span class="font-semibold">Anterior:</span> {{ filled($cambio['anterior']) ? $cambio['anterior'] : 'Sin valor' }}</p>
                        <p><span class="font-semibold">Nuevo:</span> {{ filled($cambio['nuevo']) ? $cambio['nuevo'] : 'Sin valor' }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif
