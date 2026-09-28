@php
    $tiposActivos = collect($filtros['tipo_ids'] ?? [])->map(fn ($id) => (int) $id);
    $nombresTipos = $opciones['tipos']->whereIn('id', $tiposActivos)->pluck('nombre');
@endphp

<form method="GET" action="{{ $action }}" class="tracking-filter-card" aria-labelledby="tracking-filter-title">
    <div class="tracking-filter-head">
        <div>
            <p class="tracking-eyebrow">Consulta personalizada</p>
            <h2 id="tracking-filter-title" class="font-bold text-gray-900">Filtros del informe</h2>
            <p class="text-xs text-gray-500 mt-1">Combina criterios para concentrarte únicamente en la información que necesitas.</p>
        </div>
        @if($nombresTipos->isNotEmpty())
            <div class="tracking-selected-types" aria-label="Tipos activos">
                @foreach($nombresTipos as $nombre)
                    <span class="tracking-chip">{{ $nombre }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="tracking-filter-body">
        <div class="tracking-filter-grid">
            <div class="tracking-field">
                <label for="filter-responsable">Responsable</label>
                <select id="filter-responsable" name="responsable_id" class="form-select">
                    <option value="">Todos los responsables</option>
                    @foreach($opciones['responsables'] as $v)
                        <option value="{{ $v->id }}" @selected(request('responsable_id') == $v->id)>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tracking-field">
                <label for="filter-rol">Rol</label>
                <select id="filter-rol" name="rol" class="form-select">
                    <option value="">Todos los roles</option>
                    @foreach($opciones['roles'] as $v)<option value="{{ $v }}" @selected(request('rol') === $v)>{{ ucfirst($v) }}</option>@endforeach
                </select>
            </div>
            <div class="tracking-field">
                <label for="filter-estado">Estado del caso</label>
                <select id="filter-estado" name="estado" class="form-select">
                    <option value="">Todos los estados</option>
                    @foreach($opciones['estados'] as $v)<option value="{{ $v }}" @selected(request('estado') === $v)>{{ ucfirst($v) }}</option>@endforeach
                </select>
            </div>
            <div class="tracking-field">
                <label for="filter-ans">Estado ANS</label>
                <select id="filter-ans" name="ans_estado" class="form-select">
                    <option value="">Todos los estados ANS</option>
                    @foreach($opciones['estadosAns'] as $v)<option value="{{ $v }}" @selected(request('ans_estado') === $v)>{{ ucfirst($v) }}</option>@endforeach
                </select>
            </div>

            <div class="tracking-field tracking-field-wide">
                <span class="tracking-field-label">Tipos de proceso</span>
                <details class="tracking-type-picker" @if($tiposActivos->isNotEmpty()) open @endif>
                    <summary>
                        <span>{{ $tiposActivos->isEmpty() ? 'Todos los tipos de proceso' : $tiposActivos->count().' tipo(s) seleccionado(s)' }}</span>
                        <span class="text-xs font-medium text-gray-400">Selección múltiple · abrir</span>
                    </summary>
                    <div class="tracking-type-options">
                        @foreach($opciones['tipos'] as $tipo)
                            <label class="tracking-type-option">
                                <input type="checkbox" name="tipo_ids[]" value="{{ $tipo->id }}" class="mt-0.5 rounded border-gray-300 text-red-700 focus:ring-red-700" @checked($tiposActivos->contains($tipo->id))>
                                <span>{{ $tipo->nombre }}</span>
                            </label>
                        @endforeach
                    </div>
                </details>
            </div>
            <div class="tracking-field tracking-field-wide">
                <label for="filter-subtipo">Subtipo</label>
                <select id="filter-subtipo" name="subtipo_id" class="form-select">
                    <option value="">Todos los subtipos</option>
                    @foreach($opciones['subtipos']->groupBy(fn ($subtipo) => $subtipo->tipo?->nombre ?? 'Otros') as $tipo => $subtipos)
                        <optgroup label="{{ $tipo }}">
                            @foreach($subtipos as $v)<option value="{{ $v->id }}" @selected(($filtros['subtipo_id'] ?? null) == $v->id)>{{ $v->nombre }}</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div class="tracking-field">
                <label for="filter-desde">Fecha desde</label>
                <input id="filter-desde" type="date" name="desde" value="{{ request('desde') }}" class="form-input">
            </div>
            <div class="tracking-field">
                <label for="filter-hasta">Fecha hasta</label>
                <input id="filter-hasta" type="date" name="hasta" value="{{ request('hasta') }}" class="form-input">
            </div>
        </div>

        <div class="tracking-filter-footer">
            <label class="tracking-check">
                <input type="checkbox" name="con_tareas_pendientes" value="1" class="rounded border-gray-300 text-red-700 focus:ring-red-700" @checked(request('con_tareas_pendientes') === '1')>
                <span>Mostrar solo casos con tareas pendientes</span>
            </label>
            <div class="tracking-actions">
                <a class="btn-secondary" href="{{ $action }}">Limpiar</a>
                <button class="btn-primary" type="submit">Aplicar filtros</button>
            </div>
        </div>
    </div>
</form>