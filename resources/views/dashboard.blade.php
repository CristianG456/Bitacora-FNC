@extends('layouts.app')

@section('title', 'Dashboard - Sistema Jurídico')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
@endpush

@section('content')

{{-- Encabezado de página --}}
<div class="page-header">
    @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
        <h1>Dashboard</h1>
        <p>Vista general de casos jurídicos</p>
    @else
        <h1>Mis Casos Asignados</h1>
        <p>Lista de casos que requieren tu atención</p>
    @endif
</div>

{{-- TARJETAS DE ESTADÍSTICAS --}}
@if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
<div class="dashboard-stats-grid">

    {{-- Total Casos --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'todos']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'todos' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'todos' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">Total Casos</p>
            <h2 class="stat-value total">{{ $totalCasos }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper">
            <i data-lucide="folder" class="stat-icon-svg total"></i>
        </div>
    </a>

    {{-- En Proceso --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'en_proceso']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'en_proceso' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'en_proceso' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">En Proceso</p>
            <h2 class="stat-value proceso">{{ $enProceso }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper proceso">
            <i data-lucide="clock" class="stat-icon-svg proceso"></i>
        </div>
    </a>

    {{-- Completados --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'completados']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'completados' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'completados' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">Completados</p>
            <h2 class="stat-value completado">{{ $completados }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper completado">
            <i data-lucide="check-circle" class="stat-icon-svg completado"></i>
        </div>
    </a>

    {{-- Finalizados --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'finalizados']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'finalizados' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'finalizados' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">Finalizados</p>
            <h2 class="stat-value finalizado">{{ $finalizados }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper finalizado">
            <i data-lucide="flag" class="stat-icon-svg finalizado"></i>
        </div>
    </a>

</div>
@else
<div class="dashboard-stats-grid user-stats">

    {{-- Total Asignados --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'todos']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'todos' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'todos' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">Total Asignados</p>
            <h2 class="stat-value total">{{ $totalCasos }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#6b7280; padding:0;">
            <i data-lucide="folder" style="width:20px; height:20px;"></i>
        </div>
    </a>

    {{-- Pendientes --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'pendientes']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'pendientes' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'pendientes' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">Pendientes</p>
            <h2 class="stat-value">{{ $pendientes }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#6b7280; padding:0;">
            <i data-lucide="clock" style="width:20px; height:20px;"></i>
        </div>
    </a>

    {{-- En Proceso --}}
    <a href="{{ route('dashboard', ['estado_dashboard' => 'en_proceso']) }}" class="stat-card dashboard-filter-card {{ $filtroDashboard === 'en_proceso' ? 'is-active' : '' }}" aria-pressed="{{ $filtroDashboard === 'en_proceso' ? 'true' : 'false' }}">
        <div>
            <p class="stat-label">En Proceso</p>
            <h2 class="stat-value">{{ $enProceso }}</h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#3b82f6; padding:0;">
            <i data-lucide="info" style="width:20px; height:20px;"></i>
        </div>
    </a>

</div>
@endif

<section class='dashboard-search' data-dashboard-search data-search-url='{{ route('dashboard.casos.buscar', [], false) }}'>
    <div class='dashboard-search-copy'>
        <div class='dashboard-search-icon'><i data-lucide='search'></i></div>
        <div>
            <h2>Consulta rápida de casos</h2>
            <p>Encuentra un caso y abre su bitácora completa sin salir del Dashboard.</p>
        </div>
    </div>
    <label class='dashboard-search-field'>
        <span class='sr-only'>Buscar casos</span>
        <i data-lucide='search' aria-hidden='true'></i>
        <input type='search' autocomplete='off' spellcheck='false' maxlength='100'
               placeholder='Buscar por radicado, solicitante, documento, NIT, fecha o responsable'
               data-dashboard-search-input>
        <span class='dashboard-search-spinner' data-dashboard-search-spinner hidden aria-label='Buscando'></span>
    </label>
    <p class='dashboard-search-hint' data-dashboard-search-hint>Escribe al menos 2 caracteres.</p>
    <div class='dashboard-search-results' data-dashboard-search-results hidden aria-live='polite'></div>
</section>

{{-- FILA: TABLA DE CASOS + MIS TAREAS --}}
<div style="display: block;">

        {{-- ── Casos Recientes ────────────────────────────────────── --}}
    <div class="recent-cases-wrapper">

        {{-- Header tabla --}}
        <div class="recent-cases-header">
            <div><h2 class="recent-cases-title">{{ $tituloListado }}</h2><p class="dashboard-filter-caption">{{ $casosRecientes->count() }} resultado(s) reciente(s)</p></div>
            <div class="flex items-center gap-2">
            @if($filtroDashboard !== 'todos')<a href="{{ route('dashboard') }}" class="dashboard-clear-filter">Quitar filtro</a>@endif
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary btn-create-sm">
                <i data-lucide="plus" class="btn-create-icon"></i>
                Crear Nuevo Caso
            </a>
            @endif
            </div>
        </div>

        @if($casosRecientes->isNotEmpty())
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <div class="table-responsive">
                <table class="tabla-casos">
                    <thead>
                        <tr>
                            <th>Radicado</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($casosRecientes as $caso)
                        <tr>
                            <td>
                                <span class="radicado-link">{{ $caso->radicado }}</span>
                            </td>
                            <td>
                                <span class="tipo-link">{{ $caso->tipo?->nombre ?? '—' }}</span>
                            </td>
                            <td class="td-desc">
                                <span class="desc-truncate">
                                    {{ $caso->descripcion }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $badgeClass = match($caso->estado) {
                                        'En proceso'  => 'badge-proceso',
                                        'Completado'  => 'badge-completado',
                                        'Finalizado'  => 'badge-finalizado',
                                        default       => 'badge-pendiente',
                                    };
                                @endphp
                                <span class="badge {{ $badgeClass }}">{{ $caso->estado }}</span>
                            </td>
                            <td class="td-date">
                                {{ \App\Support\LocalDate::inBogota($caso->created_at)?->format('d/m/Y') }}
                            </td>
                            <td>
                                <div class='dashboard-case-actions'>
                                    <button type='button' class='btn-audit-quick js-open-case-audit' data-audit-url='{{ route('dashboard.casos.bitacora', $caso, false) }}'>Bitácora</button>
                                    <a href="{{ route('casos.show', $caso->id) }}" class="btn-ver">Ver</a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="user-cases-list">
                @foreach($casosRecientes as $caso)
                @php
                    $badgeClass = match($caso->estado) {
                        'En proceso'  => 'badge-proceso',
                        'Completado'  => 'badge-completado',
                        'Finalizado'  => 'badge-finalizado',
                        default       => 'badge-pendiente',
                    };
                @endphp
                <div class="user-case-card">
                    <div class="case-card-header">
                        <div class="case-card-title">
                            <strong>{{ $caso->radicado }}</strong>
                            <span class="badge {{ $badgeClass }}">{{ $caso->estado }}</span>
                        </div>
                        <div class='dashboard-case-actions'>
                            <button type='button' class='btn-audit-quick js-open-case-audit' data-audit-url='{{ route('dashboard.casos.bitacora', $caso, false) }}'>Bitácora</button>
                            <a href="{{ route('casos.show', $caso->id) }}" class="btn-ver">Ver Caso</a>
                        </div>
                    </div>
                    <div class="case-card-body">
                        <p class="case-description">{{ $caso->descripcion }}</p>
                        <div class="case-meta">
                            <span class="meta-item">Tipo: {{ $caso->tipo?->nombre ?? '—' }}</span>
                            <span class="meta-separator">•</span>
                            <span class="meta-item">Asignado: {{ \App\Support\LocalDate::inBogota($caso->pivot?->fecha_asignacion ?? $caso->created_at)?->format('j/n/Y') }}</span>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif
        @else
        <div class="empty-state">
            <i data-lucide="folder-open" class="empty-state-icon"></i>
            <p class="empty-state-text">No hay casos para el filtro seleccionado.</p>
            @if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']))
            <a href="{{ route('casos.crear') }}" class="btn-primary empty-state-btn">
                Crear el primer caso
            </a>
            @endif
        </div>
        @endif

    </div>



</div>

<div class='dashboard-audit-modal' data-dashboard-audit-modal hidden>
    <div class='dashboard-audit-backdrop' data-dashboard-audit-close></div>
    <section class='dashboard-audit-panel' role='dialog' aria-modal='true' aria-labelledby='dashboard-audit-title' tabindex='-1'>
        <header class='dashboard-audit-modal-header'>
            <div>
                <p>Consulta del caso</p>
                <h2 id='dashboard-audit-title'>Bitácora completa</h2>
            </div>
            <button type='button' class='dashboard-audit-close' data-dashboard-audit-close aria-label='Cerrar bitácora'>
                <i data-lucide='x'></i>
            </button>
        </header>
        <div class='dashboard-audit-body' data-dashboard-audit-body>
            <div class='dashboard-audit-loading'>Cargando bitácora…</div>
        </div>
    </section>
</div>

@push('scripts')
<script type="module">
    window.__dashboardQuickAccessCleanup?.();

    const searchRoot = document.querySelector('[data-dashboard-search]');
    const auditModal = document.querySelector('[data-dashboard-audit-modal]');
    const cleanupCallbacks = [];

    if (searchRoot && auditModal) {
        const input = searchRoot.querySelector('[data-dashboard-search-input]');
        const results = searchRoot.querySelector('[data-dashboard-search-results]');
        const hint = searchRoot.querySelector('[data-dashboard-search-hint]');
        const spinner = searchRoot.querySelector('[data-dashboard-search-spinner]');
        const panel = auditModal.querySelector('.dashboard-audit-panel');
        const body = auditModal.querySelector('[data-dashboard-audit-body]');
        let debounceTimer;
        let searchController;
        let auditController;
        let lastFocused;

        const badgeClass = (estado) => ({
            'En proceso': 'is-process',
            'Completado': 'is-complete',
            'Finalizado': 'is-final',
        }[estado] || 'is-pending');

        const closeModal = () => {
            auditController?.abort();
            auditModal.hidden = true;
            document.body.classList.remove('dashboard-modal-open');
            body.innerHTML = '<div class=\'dashboard-audit-loading\'>Cargando bitácora…</div>';
            lastFocused?.focus?.();
        };

        const openModal = async (url, trigger) => {
            lastFocused = trigger || document.activeElement;
            auditController?.abort();
            auditController = new AbortController();
            auditModal.hidden = false;
            document.body.classList.add('dashboard-modal-open');
            body.innerHTML = '<div class=\'dashboard-audit-loading\'>Cargando bitácora…</div>';
            panel.focus();

            try {
                const response = await fetch(url, {
                    headers: { Accept: 'text/html', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: auditController.signal,
                });
                if (!response.ok) {
                    throw new Error(response.status === 404 || response.status === 403
                        ? 'No tienes permiso para consultar este caso.'
                        : 'No fue posible cargar la bitácora.');
                }
                body.innerHTML = await response.text();
                window.lucide?.createIcons();
            } catch (error) {
                if (error.name !== 'AbortError') {
                    const errorBox = document.createElement('div');
                    errorBox.className = 'dashboard-audit-error';
                    errorBox.textContent = error.message;
                    body.replaceChildren(errorBox);
                }
            }
        };

        const renderResults = (items, message) => {
            results.replaceChildren();
            results.hidden = false;

            if (!items.length) {
                const empty = document.createElement('p');
                empty.className = 'dashboard-search-empty';
                empty.textContent = message || 'No se encontraron casos con ese criterio.';
                results.appendChild(empty);
                return;
            }

            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'dashboard-search-result';

                const top = document.createElement('span');
                top.className = 'dashboard-search-result-top';
                const radicado = document.createElement('strong');
                radicado.textContent = item.radicado;
                const status = document.createElement('span');
                status.className = `dashboard-result-status ${badgeClass(item.estado)}`;
                status.textContent = item.estado;
                top.append(radicado, status);

                const subject = document.createElement('span');
                subject.className = 'dashboard-search-result-subject';
                subject.textContent = `${item.solicitante} · ${item.tipo}${item.subtipo ? ` / ${item.subtipo}` : ''}`;

                const meta = document.createElement('span');
                meta.className = 'dashboard-search-result-meta';
                const responsibleText = item.responsables.length ? item.responsables.join(', ') : 'Sin responsables';
                meta.textContent = `${item.fecha} · ${responsibleText}`;

                const description = document.createElement('span');
                description.className = 'dashboard-search-result-description';
                description.textContent = item.descripcion || 'Sin descripción';

                button.append(top, subject, meta, description);
                button.addEventListener('click', () => openModal(item.bitacora_url, button));
                results.appendChild(button);
            });
        };

        const search = async () => {
            const query = input.value.trim();
            searchController?.abort();

            if (query.length < 2) {
                results.hidden = true;
                results.replaceChildren();
                hint.textContent = 'Escribe al menos 2 caracteres.';
                spinner.hidden = true;
                return;
            }

            searchController = new AbortController();
            spinner.hidden = false;
            hint.textContent = 'Buscando…';

            try {
                const url = new URL(searchRoot.dataset.searchUrl, window.location.origin);
                url.searchParams.set('q', query);
                const response = await fetch(url, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    signal: searchController.signal,
                });
                if (!response.ok) throw new Error();
                const payload = await response.json();
                renderResults(payload.data || [], payload.message);
                hint.textContent = `${payload.data?.length || 0} resultado(s). Selecciona un caso para ver la bitácora.`;
            } catch (error) {
                if (error.name !== 'AbortError') {
                    renderResults([], 'No fue posible realizar la búsqueda. Intenta nuevamente.');
                    hint.textContent = 'La búsqueda no pudo completarse.';
                }
            } finally {
                spinner.hidden = true;
            }
        };

        const onInput = () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(search, 320);
        };
        input.addEventListener('input', onInput);
        cleanupCallbacks.push(() => input.removeEventListener('input', onInput));

        document.querySelectorAll('.js-open-case-audit').forEach((button) => {
            const onOpen = () => openModal(button.dataset.auditUrl, button);
            button.addEventListener('click', onOpen);
            cleanupCallbacks.push(() => button.removeEventListener('click', onOpen));
        });

        auditModal.querySelectorAll('[data-dashboard-audit-close]').forEach((element) => {
            element.addEventListener('click', closeModal);
            cleanupCallbacks.push(() => element.removeEventListener('click', closeModal));
        });

        const onKeydown = (event) => {
            if (event.key === 'Escape' && !auditModal.hidden) closeModal();
        };
        document.addEventListener('keydown', onKeydown);
        cleanupCallbacks.push(() => document.removeEventListener('keydown', onKeydown));
    }

    window.__dashboardQuickAccessCleanup = () => {
        cleanupCallbacks.forEach((cleanup) => cleanup());
        document.body.classList.remove('dashboard-modal-open');
        delete window.__dashboardQuickAccessCleanup;
    };

    document.addEventListener('nueva-notificacion-recibida', (e) => {
        const notif = e.detail;

        // Solo actualizar la tabla si es una notificación de nuevo caso (puedes ajustar esta lógica según el tipo)
        if (notif.tipo === 'caso' || (notif.titulo && notif.titulo.toLowerCase().includes('nuevo caso'))) {
            const tbody = document.querySelector('.tabla-casos tbody');
            if (tbody) {
                const tr = document.createElement('tr');

                // Extraer el número de radicado del mensaje si es posible (ya que la notificación genérica envía un texto)
                // O si tienes el objeto caso incrustado en metadata, úsalo:
                const radicadoMatch = notif.mensaje.match(/CASO-[0-9\-]+/);
                const radicado = radicadoMatch ? radicadoMatch[0] : 'Nuevo';

                const radicadoCell = document.createElement('td');
                const radicadoElement = document.createElement('span');
                radicadoElement.className = 'radicado-link';
                radicadoElement.textContent = radicado;
                radicadoCell.appendChild(radicadoElement);

                const tipoCell = document.createElement('td');
                const tipoElement = document.createElement('span');
                tipoElement.className = 'tipo-link';
                tipoElement.textContent = '—';
                tipoCell.appendChild(tipoElement);

                const descriptionCell = document.createElement('td');
                descriptionCell.className = 'td-desc';
                const description = document.createElement('span');
                description.className = 'desc-truncate';
                description.textContent = notif.mensaje ?? '';
                descriptionCell.appendChild(description);

                const statusCell = document.createElement('td');
                const status = document.createElement('span');
                status.className = 'badge badge-pendiente';
                status.textContent = 'En Proceso';
                statusCell.appendChild(status);

                const dateCell = document.createElement('td');
                dateCell.className = 'td-date';
                dateCell.textContent = new Date().toLocaleDateString();

                const actionCell = document.createElement('td');
                const link = document.createElement('a');
                link.href = '/casos';
                link.className = 'btn-ver';
                link.textContent = 'Ver';
                actionCell.appendChild(link);

                tr.append(radicadoCell, tipoCell, descriptionCell, statusCell, dateCell, actionCell);
                tbody.insertBefore(tr, tbody.firstChild);

                // Update total count
                const totalEl = document.querySelector('.stat-value.total');
                if (totalEl) totalEl.innerText = parseInt(totalEl.innerText) + 1;
            }
        }
    });
</script>
@endpush

@endsection
