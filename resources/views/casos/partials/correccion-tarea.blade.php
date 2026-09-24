@php
    $solicitudActiva = $tarea->solicitudesCorreccion->firstWhere('activa', true);
    $ultimaSolicitud = $tarea->solicitudesCorreccion->first();
    $esJuridicaCorreccion = auth()->user()->esJuridica();
    $esPropietarioCorreccion = (int) $tarea->user_id === (int) auth()->id();
@endphp

@if($esJuridicaCorreccion && $ultimaSolicitud)
    <div class="mt-3 rounded-lg border p-3 text-xs {{ $ultimaSolicitud->estado === 'pendiente' ? 'border-amber-200 bg-amber-50' : ($ultimaSolicitud->estado === 'rechazada' ? 'border-red-200 bg-red-50' : 'border-green-200 bg-green-50') }}"
         data-correction-request-id="{{ $ultimaSolicitud->id }}"
         data-correction-state="{{ $ultimaSolicitud->estado }}">
        <h4 class="font-bold uppercase tracking-wide {{ $ultimaSolicitud->estado === 'pendiente' ? 'text-amber-900' : ($ultimaSolicitud->estado === 'rechazada' ? 'text-red-800' : 'text-green-800') }}">
            Solicitud de corrección
        </h4>
        <dl class="mt-2 grid gap-1 text-gray-700">
            <div><dt class="inline font-semibold">Solicitado por:</dt> <dd class="inline">{{ $ultimaSolicitud->solicitante?->name }}</dd></div>
            <div><dt class="inline font-semibold">Motivo:</dt> <dd class="inline">{{ $ultimaSolicitud->motivo }}</dd></div>
            <div><dt class="inline font-semibold">Estado:</dt> <dd class="inline font-bold uppercase">{{ $ultimaSolicitud->estado === 'utilizada' ? 'Corrección realizada' : $ultimaSolicitud->estado }}</dd></div>
            @if($ultimaSolicitud->revisora)
                <div><dt class="inline font-semibold">Revisado por:</dt> <dd class="inline">{{ $ultimaSolicitud->revisora->name }}</dd></div>
            @endif
            @if($ultimaSolicitud->motivo_rechazo)
                <div><dt class="inline font-semibold">Motivo del rechazo:</dt> <dd class="inline">{{ $ultimaSolicitud->motivo_rechazo }}</dd></div>
            @endif
        </dl>

        @if($ultimaSolicitud->estado === 'pendiente' && $ultimaSolicitud->activa)
            <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-end">
                <form method="POST" action="{{ route('tareas.correccion.rechazar', $ultimaSolicitud) }}" class="flex flex-1 flex-col gap-2 sm:flex-row" onsubmit="this.querySelectorAll('button').forEach(button => button.disabled = true)">
                    @csrf
                    <input class="form-input flex-1 text-xs" name="motivo_rechazo" placeholder="Motivo del rechazo" required minlength="5" maxlength="1000">
                    <button class="btn-secondary justify-center text-red-700" type="submit">Rechazar</button>
                </form>
                <form method="POST" action="{{ route('tareas.correccion.aprobar', $ultimaSolicitud) }}" onsubmit="this.querySelector('button').disabled = true">
                    @csrf
                    <button class="btn-secondary w-full justify-center text-green-700" type="submit">Autorizar corrección</button>
                </form>
            </div>
        @endif
    </div>
@elseif($esPropietarioCorreccion && $tarea->estado === 'Completada')
    @if(!$solicitudActiva)
        @if($ultimaSolicitud?->estado === 'rechazada')
            <div class="mt-3 rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                <strong>Solicitud de corrección rechazada.</strong>
                @if($ultimaSolicitud->motivo_rechazo)<span class="mt-1 block">{{ $ultimaSolicitud->motivo_rechazo }}</span>@endif
            </div>
        @elseif($ultimaSolicitud?->estado === 'utilizada')
            <div class="mt-3 rounded-md border border-green-200 bg-green-50 p-3 text-xs text-green-800">
                <strong>Corrección realizada.</strong><span class="mt-1 block">La autorización de un solo uso ya fue consumida.</span>
            </div>
        @endif
        <button type="button" onclick="document.getElementById('modal-solicitar-correccion-{{ $tarea->id }}').showModal()" class="mt-3 btn-secondary border-amber-300 text-amber-700">
            Solicitar corrección
        </button>
        <dialog id="modal-solicitar-correccion-{{ $tarea->id }}" class="w-[min(92vw,520px)] rounded-xl p-0 shadow-2xl backdrop:bg-gray-900/60">
            <div class="bg-white p-5">
                <div class="mb-4 flex justify-between gap-4">
                    <div><h3 class="text-lg font-bold text-gray-900">Solicitar corrección</h3><p class="mt-1 text-sm text-gray-500">{{ $tarea->descripcion }}</p></div>
                    <button type="button" onclick="this.closest('dialog').close()" class="text-2xl text-gray-400" aria-label="Cerrar">×</button>
                </div>
                <form action="{{ route('tareas.correccion.solicitar', [$tarea->caso_id, $tarea]) }}" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                    @csrf
                    <label class="mb-2 block text-sm font-semibold text-gray-800">Motivo de la corrección *</label>
                    <textarea name="motivo" class="form-input w-full text-sm" rows="4" required minlength="5" maxlength="1000" placeholder="Ejemplo: Escribí mal la observación."></textarea>
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="this.closest('dialog').close()" class="btn-secondary">Cancelar</button>
                        <button type="submit" class="btn-secondary border-amber-300 text-amber-700">Enviar solicitud</button>
                    </div>
                </form>
            </div>
        </dialog>
    @elseif($solicitudActiva->estado === 'pendiente')
        <div class="mt-3 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            <strong>Corrección solicitada</strong><span class="mt-1 block">Pendiente de aprobación por Jurídica.</span>
        </div>
    @elseif($solicitudActiva->estado === 'aprobada')
        <div class="mt-3 rounded-md border border-green-200 bg-green-50 p-3 text-xs text-green-800"><strong>Corrección autorizada</strong><span class="mt-1 block">Esta autorización puede utilizarse una sola vez.</span></div>
        <button type="button" onclick="document.getElementById('modal-corregir-tarea-{{ $tarea->id }}').showModal()" class="mt-2 btn-secondary border-green-300 text-green-700">Corregir tarea</button>
        <dialog id="modal-corregir-tarea-{{ $tarea->id }}" class="w-[min(92vw,520px)] rounded-xl p-0 shadow-2xl backdrop:bg-gray-900/60">
            <div class="bg-white p-5">
                <div class="mb-4 flex justify-between gap-4">
                    <div><h3 class="text-lg font-bold text-gray-900">Corregir tarea</h3><p class="mt-1 text-sm text-gray-500">La tarea permanecerá completada.</p></div>
                    <button type="button" onclick="this.closest('dialog').close()" class="text-2xl text-gray-400" aria-label="Cerrar">×</button>
                </div>
                <form action="{{ route('tareas.correccion.aplicar', [$tarea->caso_id, $tarea, $solicitudActiva]) }}" method="POST" onsubmit="this.querySelector('button[type=submit]').disabled=true">
                    @csrf
                    @method('PUT')
                    <p class="mb-2 text-xs text-gray-500"><strong>Observación actual:</strong> {{ $tarea->observacion?->contenido }}</p>
                    <label class="mb-2 block text-sm font-semibold text-gray-800">Nueva observación *</label>
                    <textarea name="observacion" class="form-input w-full text-sm" rows="4" required minlength="5" maxlength="2000">{{ $tarea->observacion?->contenido }}</textarea>
                    <label class="mb-2 mt-3 block text-sm font-semibold text-gray-800">Fecha de finalización *</label>
                    <input type="datetime-local" name="fecha_fin" class="form-input w-full text-sm" required value="{{ $tarea->fecha_fin?->setTimezone('America/Bogota')->format('Y-m-d\TH:i') }}">
                    <div class="mt-4 flex justify-end gap-2">
                        <button type="button" onclick="this.closest('dialog').close()" class="btn-secondary">Cancelar</button>
                        <button type="submit" class="btn-secondary border-green-300 text-green-700">Guardar corrección</button>
                    </div>
                </form>
            </div>
        </dialog>
    @endif
@endif
