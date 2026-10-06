@php($detalle = \App\Support\AuditMetadataPresenter::make($evento))
<div class='event-details' data-audit-event='{{ $detalle['titulo'] }}'>
    <h3 class='event-title'>{{ mb_strtoupper($detalle['titulo']) }}</h3>
    @if($detalle['caso'])<p><strong>Caso:</strong> {{ $detalle['caso'] }}</p>@endif
    @if($detalle['actor'])<p><strong>{{ $detalle['actor_label'] }}:</strong> {{ $detalle['actor'] }}</p>@endif
    @if($detalle['responsable'])<p><strong>Responsable:</strong> {{ $detalle['responsable'] }}</p>@endif
    @if($detalle['responsable_anterior'])<p><strong>Responsable anterior:</strong> {{ $detalle['responsable_anterior'] }}</p>@endif
    @if($detalle['responsable_nuevo'])<p><strong>Responsable nuevo:</strong> {{ $detalle['responsable_nuevo'] }}</p>@endif
    @if($detalle['tarea'])<p><strong>Tarea:</strong> {{ $detalle['tarea'] }}</p>@endif
    @if($detalle['tipo_tarea'])<p><strong>Tipo:</strong> {{ $detalle['tipo_tarea'] }}</p>@endif
    @if($detalle['estado_tarea'] && $detalle['titulo'] !== 'Tarea completada')<p><strong>Estado:</strong> {{ $detalle['estado_tarea'] }}</p>@endif
    @if($detalle['fecha_hora'])<p><strong>Fecha/hora:</strong> {{ $detalle['fecha_hora'] }}</p>@endif
    @if($detalle['fecha_asignacion'])<p><strong>Asignada:</strong> {{ $detalle['fecha_asignacion'] }}</p>@endif
    @if($detalle['fecha_finalizacion'])<p><strong>Completada:</strong> {{ $detalle['fecha_finalizacion'] }}</p>@endif
    @if($detalle['tiempo_atencion'])<p><strong>Tiempo de atención:</strong> {{ $detalle['tiempo_atencion'] }}</p>@endif
    @if($detalle['dias_habiles_transcurridos'] !== null)<p><strong>Días hábiles transcurridos:</strong> {{ $detalle['dias_habiles_transcurridos'] }}</p>@endif
    @if($detalle['motivo'])<p><strong>Motivo:</strong> {{ $detalle['motivo'] }}</p>@endif
    @if($detalle['autorizador'])<p><strong>Autorizado por:</strong> {{ $detalle['autorizador'] }}</p>@endif
    @if($detalle['motivo_rechazo'])<p><strong>Motivo del rechazo:</strong> {{ $detalle['motivo_rechazo'] }}</p>@endif
    @if($detalle['resultado'])<p><strong>Resultado:</strong> {{ $detalle['resultado'] }}</p>@endif
    @if($detalle['observacion'])<p><strong>Observación:</strong> {{ $detalle['observacion'] }}</p>@endif
    @if($detalle['ans'])
        <div class='audit-ans'>
            <strong>ANS</strong>
            @if($detalle['ans']['estado'])<span>Estado: {{ $detalle['ans']['estado'] }}</span>@endif
            @if($detalle['ans']['configurado'])<span>Configurado: {{ $detalle['ans']['configurado'] }}</span>@endif
            @if($detalle['ans']['inicio'])<span>Inicio: {{ $detalle['ans']['inicio'] }}</span>@endif
            @if($detalle['ans']['limite'])<span>Fecha límite: {{ $detalle['ans']['limite'] }}</span>@endif
            @if($detalle['ans']['restante'])<span>Restante: {{ $detalle['ans']['restante'] }}</span>@endif
        </div>
    @endif
    @foreach($detalle['cambios'] as $cambio)
        <p><strong>{{ $cambio['campo'] }}:</strong> {{ filled($cambio['nuevo']) ? $cambio['nuevo'] : 'Sin valor' }}</p>
    @endforeach
</div>
