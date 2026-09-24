@if(request()->ajax())
<div id="module-fragment" data-module-title="@yield('title')">
    @yield('content')
</div>
<div id="module-styles">
    @stack('styles')
</div>
<div id="module-scripts">
    @stack('scripts')
</div>
<div id="module-flash"
     data-success="{{ session('success') }}"
     data-error="{{ session('error') ?: ($errors->any() ? $errors->first() : '') }}"></div>
@else
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>window.userId = @json(auth()->id());</script>
    @vite(['resources/js/app.js'])
    <title>@yield('title', 'Sistema de Gestión de Casos Jurídicos')</title>
    <meta name="description" content="Sistema de Gestión de Casos Jurídicos - Federación Nacional de Cafeteros">
    
    <link rel="icon" href="{{ asset('imagenes/federacion cafeteros logo.png') }}" type="image/png">

    {{-- Tailwind CDN (ya está en el proyecto) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Lucide Icons --}}
    <script src="https://unpkg.com/lucide@latest"></script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    
    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    @stack('styles')
</head>

<body>

{{-- SIDEBAR --}}
<aside class="sidebar transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-40 fixed">

    {{-- Logo --}}
    <div class="sidebar-logo">
        <img src="{{ asset('imagenes/federacion cafeteros logo.png') }}"
             alt="Federación Nacional de Cafeteros"
             onerror="this.style.display='none'">
        <span class="org-name">Federación Nacional<br>de Cafeteros</span>
    </div>

    {{-- Navegación --}}
    <nav class="sidebar-nav">

        <span class="nav-section-title">Principal</span>

        <a href="{{ route('dashboard') }}"
           class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" style="width:18px;height:18px;"></i>
            Dashboard
        </a>

        <a href="{{ route('casos.index') }}" class="nav-item {{ request()->routeIs('casos.*') && !request()->routeIs('casos.crear') ? 'active' : '' }}">
            <i data-lucide="folder-open" style="width:18px;height:18px;"></i>
            Casos
        </a>

        @can('create', App\Models\Caso::class)
        @endcan
        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <a href="{{ route('casos.crear') }}"
           class="nav-item {{ request()->routeIs('casos.crear') ? 'active' : '' }}">
            <i data-lucide="plus-circle" style="width:18px;height:18px;"></i>
            Crear Caso
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']))
        <span class="nav-section-title">Gestión</span>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <a href="{{ route('tipos.index') }}" class="nav-item {{ request()->routeIs('tipos.*') ? 'active' : '' }}">
            <i data-lucide="file-text" style="width:18px;height:18px;"></i>
            Tipos de Documento
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <a href="{{ route('usuarios.index') }}" class="nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
            <i data-lucide="users" style="width:18px;height:18px;"></i>
            Usuarios
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']))
        <a href="{{ route('historial.index') }}" class="nav-item {{ request()->routeIs('historial.*') ? 'active' : '' }}">
            <i data-lucide="clock" style="width:18px;height:18px;"></i>
            Historial Global
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador']))
        <a href="{{ route('respaldos.index') }}" class="nav-item {{ request()->routeIs('respaldos.*') ? 'active' : '' }}">
            <i data-lucide="database" style="width:18px;height:18px;"></i>
            Respaldos
        </a>
        @endif

    </nav>

</aside>

{{-- CONTENIDO PRINCIPAL --}}
<div class="main-wrapper">

    {{-- Overlay para móvil --}}
    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden" onclick="toggleSidebar()"></div>

    {{-- Header --}}
    <header class="top-header flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="btn-hamburger md:hidden text-gray-600 hover:text-gray-900 focus:outline-none">
                <i data-lucide="menu" style="width:24px;height:24px;"></i>
            </button>
            <span class="header-title hidden sm:block">Sistema de Gestión de Casos Jurídicos</span>
            <span class="header-title sm:hidden text-sm">SGCJ</span>
        </div>

        <div class="header-right">

            @auth
            @php
                $sinLeer = auth()->user()->notificaciones()
                    ->where('leido', false)
                    ->where(fn ($query) => $query->whereNull('tipo')->orWhere('tipo', '!=', 'mensaje'))
                    ->count();
                $mensajesSinLeer = auth()->user()->notificaciones()
                    ->where('tipo', 'mensaje')
                    ->where('leido', false)
                    ->count();
                $tareasPendientes = auth()->user()->esConsultor() ? 0 : auth()->user()->tareas()
                    ->where('estado', '!=', 'Completada')
                    ->whereHas('caso.usuarios', fn ($usuarios) => $usuarios
                        ->where('users.id', auth()->id())
                        ->where('caso_usuario.activo', true))
                    ->count();
            @endphp
            {{-- Notificaciones --}}
            <div class="relative header-indicator-container" id="notif-container">
                <div class="notif-bell cursor-pointer" title="Notificaciones" id="notif-btn" onclick="toggleNotificaciones()">
                    <i data-lucide="bell" style="width:20px;height:20px;"></i>
                    @if($sinLeer > 0)
                        <span class="notif-badge" id="notif-badge-count">{{ $sinLeer > 9 ? '9+' : $sinLeer }}</span>
                    @endif
                </div>

                <!-- Menú desplegable -->
                <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 max-w-[90vw] md:max-w-sm bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-sm text-gray-800">Notificaciones</h3>
                        <form id="notif-mark-read-form" data-mark-read-form action="{{ route('notificaciones.marcar_leidas') }}" method="POST" class="{{ $sinLeer > 0 ? '' : 'hidden' }}">
                            @csrf
                            <input type="hidden" name="categoria" value="general">
                            <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Marcar leídas</button>
                        </form>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto" id="notif-list">
                        <!-- Las notificaciones se cargan por JS -->
                        <div class="p-4 text-center text-sm text-gray-500">Cargando...</div>
                    </div>
                </div>
            </div>

            {{-- Mensajes --}}
            <div class="relative header-indicator-container" id="message-indicator-container">
                <div class="notif-bell cursor-pointer" title="Mensajes" id="message-indicator-btn" onclick="toggleMensajesHeader()">
                    <i data-lucide="message-square" style="width:20px;height:20px;"></i>
                    @if($mensajesSinLeer > 0)
                        <span class="notif-badge" id="message-badge-count">{{ $mensajesSinLeer > 9 ? '9+' : $mensajesSinLeer }}</span>
                    @endif
                </div>
                <div id="message-dropdown" class="header-indicator-dropdown hidden absolute right-0 mt-2 w-80 max-w-[90vw] md:max-w-sm bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-sm text-gray-800">Mensajes</h3>
                        <form id="message-mark-read-form" data-mark-read-form action="{{ route('notificaciones.marcar_leidas') }}" method="POST" class="{{ $mensajesSinLeer > 0 ? '' : 'hidden' }}">
                            @csrf
                            <input type="hidden" name="categoria" value="mensaje">
                            <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Marcar leídos</button>
                        </form>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto" id="message-list">
                        <div class="p-4 text-center text-sm text-gray-500">Cargando...</div>
                    </div>
                </div>
            </div>

            {{-- Mis tareas pendientes --}}
            <div class="relative header-indicator-container" id="task-indicator-container">
                <div class="notif-bell cursor-pointer" title="Mis tareas pendientes" id="task-indicator-btn" onclick="toggleTareasHeader()">
                    <i data-lucide="list-checks" style="width:20px;height:20px;"></i>
                    @if($tareasPendientes > 0)
                        <span class="notif-badge" id="task-badge-count">{{ $tareasPendientes > 9 ? '9+' : $tareasPendientes }}</span>
                    @endif
                </div>
                <div id="task-dropdown" class="header-indicator-dropdown hidden absolute right-0 mt-2 w-80 max-w-[90vw] md:max-w-sm bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 bg-gray-50">
                        <h3 class="font-bold text-sm text-gray-800">Tareas pendientes</h3>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto" id="task-list">
                        <div class="p-4 text-center text-sm text-gray-500">Cargando...</div>
                    </div>
                </div>
            </div>
            @endauth

            {{-- Usuario --}}
            <div class="user-info">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </div>
                <div class="hidden sm:block">
                    <div class="user-name-label">{{ auth()->user()?->name ?? 'Usuario' }}</div>
                    <div class="user-role-label">{{ auth()->user()?->role?->nombre ?? 'Sin rol' }}</div>
                </div>
            </div>

            {{-- Cerrar sesión --}}
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="btn-logout" title="Cerrar sesión">
                    <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                </button>
            </form>

        </div>
    </header>

    {{-- Flash messages are handled by SweetAlert at the bottom of the file --}}

    {{-- Contenido de página --}}
    <main id="app-content" class="page-content">
        @yield('content')
    </main>

    {{-- Navegación inferior móvil recuperada del responsive anterior --}}
    <nav class="bottom-nav" aria-label="Navegación principal móvil">
        <a href="{{ route('dashboard') }}" class="bottom-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i data-lucide="layout-dashboard" aria-hidden="true"></i>
            <span>Inicio</span>
        </a>
        <a href="{{ route('casos.index') }}" class="bottom-nav-item {{ request()->routeIs('casos.*') && !request()->routeIs('casos.crear') ? 'active' : '' }}">
            <i data-lucide="folder-open" aria-hidden="true"></i>
            <span>Casos</span>
        </a>

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <a href="{{ route('casos.crear') }}" class="bottom-nav-item {{ request()->routeIs('casos.crear') ? 'active' : '' }}">
            <i data-lucide="plus-circle" aria-hidden="true"></i>
            <span>Crear</span>
        </a>
        @endif

        <button type="button" class="bottom-nav-item" data-mobile-more aria-controls="mobile-drawer" aria-expanded="false" onclick="toggleMobileDrawer()">
            <i data-lucide="menu" aria-hidden="true"></i>
            <span>Más</span>
        </button>
    </nav>

    {{-- Menú secundario móvil "Más" --}}
    <div id="mobile-drawer" class="mobile-drawer" aria-hidden="true">
        <div class="mobile-drawer-header">
            <h2 class="mobile-drawer-title">Más Opciones</h2>
            <button type="button" class="mobile-drawer-close" aria-label="Cerrar menú" onclick="closeMobileDrawer()">
                <i data-lucide="x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="mobile-drawer-content">
            @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
            <span class="nav-section-title">Gestión</span>
            <a href="{{ route('tipos.index') }}" class="drawer-nav-item {{ request()->routeIs('tipos.*') ? 'active' : '' }}">
                <i data-lucide="file-text" aria-hidden="true"></i>
                Tipos de Documento
            </a>
            <a href="{{ route('usuarios.index') }}" class="drawer-nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
                <i data-lucide="users" aria-hidden="true"></i>
                Usuarios
            </a>
            @endif

            @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor', 'Abogado']))
            <span class="nav-section-title">Reportes</span>
            <a href="{{ route('historial.index') }}" class="drawer-nav-item {{ request()->routeIs('historial.*') ? 'active' : '' }}">
                <i data-lucide="clock" aria-hidden="true"></i>
                Historial Global
            </a>
            @endif

            @if(auth()->user()?->tieneAlgunRol(['Administrador']))
            <span class="nav-section-title">Sistema</span>
            <a href="{{ route('respaldos.index') }}" class="drawer-nav-item {{ request()->routeIs('respaldos.*') ? 'active' : '' }}">
                <i data-lucide="database" aria-hidden="true"></i>
                Respaldos
            </a>
            @endif
        </div>
    </div>

</div>

<script>
    lucide.createIcons();

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
        
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }

    function setMobileDrawer(open) {
        const drawer = document.getElementById('mobile-drawer');
        const trigger = document.querySelector('[data-mobile-more]');
        if (!drawer) return;

        drawer.classList.toggle('open', open);
        drawer.setAttribute('aria-hidden', open ? 'false' : 'true');
        trigger?.setAttribute('aria-expanded', open ? 'true' : 'false');
        document.body.classList.toggle('mobile-drawer-open', open);
    }

    function toggleMobileDrawer() {
        const drawer = document.getElementById('mobile-drawer');
        setMobileDrawer(!drawer?.classList.contains('open'));
    }

    function closeMobileDrawer() {
        setMobileDrawer(false);
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeMobileDrawer();
    });

    function toggleNotificaciones() {
        toggleHeaderDropdown('notif-dropdown', true);
    }

    function toggleMensajesHeader() {
        toggleHeaderDropdown('message-dropdown');
    }

    function toggleTareasHeader() {
        toggleHeaderDropdown('task-dropdown');
    }

    async function toggleHeaderDropdown(dropdownId, marcarGenerales = false) {
        const dropdown = document.getElementById(dropdownId);
        if (!dropdown) return;
        const shouldOpen = dropdown.classList.contains('hidden');
        document.querySelectorAll('.header-indicator-dropdown').forEach(item => item.classList.add('hidden'));
        dropdown.classList.toggle('hidden', !shouldOpen);
        if (shouldOpen) {
            const data = await cargarNotificaciones();
            if (marcarGenerales) {
                const ids = (data?.notificaciones ?? []).filter(item => !item.leido).map(item => item.id);
                if (ids.length > 0) await marcarNotificacionesLeidas('general', ids);
            }
        }
    }

    function actualizarBadge(buttonId, badgeId, cantidad) {
        const button = document.getElementById(buttonId);
        let badge = document.getElementById(badgeId);

        if (cantidad > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.id = badgeId;
                badge.className = 'notif-badge';
                button?.appendChild(badge);
            }
            badge.textContent = cantidad > 9 ? '9+' : String(cantidad);
        } else {
            badge?.remove();
        }
    }

    function renderizarNotificaciones(listId, elementos, mensajeVacio) {
        const list = document.getElementById(listId);
        if (!list) return;
        list.replaceChildren();

        if (elementos.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'p-4 text-center text-sm text-gray-500';
            empty.textContent = mensajeVacio;
            list.appendChild(empty);
            return;
        }

        elementos.forEach(n => {
            const row = document.createElement(n.url ? 'a' : 'div');
            if (n.url) {
                row.href = n.url;
                row.addEventListener('click', async event => {
                    if (n.leido) return;
                    event.preventDefault();
                    try {
                        await marcarNotificacionesLeidas(n.tipo === 'mensaje' ? 'mensaje' : 'general', [n.id]);
                    } catch (error) {
                        console.error('Error marcando la alerta seleccionada como leída:', error);
                    } finally {
                        window.location.href = n.url;
                    }
                });
            }
            row.dataset.notificationId = n.id;
            row.className = `p-3 border-b border-gray-50 flex gap-3 hover:bg-gray-50 transition ${n.leido ? 'bg-white' : 'bg-blue-50'}`;

            const iconWrapper = document.createElement('div');
            iconWrapper.className = 'mt-0.5 shrink-0';
            const icon = document.createElement('i');
            icon.dataset.lucide = n.tipo === 'mensaje' ? 'message-square' : (n.tipo === 'success' ? 'check-circle' : 'info');
            icon.className = n.tipo === 'success' ? 'w-4 h-4 text-green-500' : 'w-4 h-4 text-blue-500';
            iconWrapper.appendChild(icon);

            const details = document.createElement('div');
            const title = document.createElement('h4');
            title.className = 'text-xs font-bold text-gray-800 mb-0.5';
            title.textContent = n.titulo ?? '';
            const message = document.createElement('p');
            message.className = 'text-xs text-gray-600 leading-snug';
            message.textContent = n.mensaje ?? '';
            const date = document.createElement('span');
            date.className = 'text-[10px] text-gray-400 mt-1 block';
            date.textContent = n.fecha ?? '';

            details.append(title, message, date);
            row.append(iconWrapper, details);
            list.appendChild(row);
        });
    }

    function renderizarTareas(tareas) {
        const list = document.getElementById('task-list');
        if (!list) return;
        list.replaceChildren();

        if (tareas.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'p-4 text-center text-sm text-gray-500';
            empty.textContent = 'No tienes tareas pendientes.';
            list.appendChild(empty);
            return;
        }

        tareas.forEach(tarea => {
            const row = document.createElement(tarea.url ? 'a' : 'div');
            if (tarea.url) row.href = tarea.url;
            row.className = 'block p-3 border-b border-gray-50 hover:bg-gray-50 transition';
            const caso = document.createElement('div');
            caso.className = 'text-xs font-bold text-gray-800 mb-1';
            caso.textContent = tarea.caso ?? 'Caso';
            const descripcion = document.createElement('p');
            descripcion.className = 'text-xs text-gray-600 leading-snug';
            descripcion.textContent = tarea.descripcion ?? '';
            row.append(caso, descripcion);
            list.appendChild(row);
        });
    }

    function cargarNotificaciones() {
        return fetch('{{ route("notificaciones.recientes", [], false) }}', { headers: { 'Accept': 'application/json' } })
            .then(response => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                return response.json();
            })
            .then(data => {
                const notificaciones = data.notificaciones ?? [];
                const sinLeer = Number(data.sinLeer ?? 0);
                const mensajes = data.mensajes ?? [];
                const mensajesSinLeer = Number(data.mensajesSinLeer ?? 0);
                const tareas = data.tareas ?? [];
                const tareasPendientes = Number(data.tareasPendientes ?? 0);
                const markReadForm = document.getElementById('notif-mark-read-form');
                const messageMarkReadForm = document.getElementById('message-mark-read-form');

                actualizarBadge('notif-btn', 'notif-badge-count', sinLeer);
                actualizarBadge('message-indicator-btn', 'message-badge-count', mensajesSinLeer);
                actualizarBadge('task-indicator-btn', 'task-badge-count', tareasPendientes);
                markReadForm?.classList.toggle('hidden', sinLeer === 0);
                messageMarkReadForm?.classList.toggle('hidden', mensajesSinLeer === 0);
                renderizarNotificaciones('notif-list', notificaciones, 'No tienes notificaciones.');
                renderizarNotificaciones('message-list', mensajes, 'No tienes mensajes nuevos.');
                renderizarTareas(tareas);
                lucide.createIcons();
                return data;
            })
            .catch(error => console.error('Error actualizando notificaciones:', error));
    }

    window.notificationPoller?.stop();
    (() => {
        let timer = null;
        let stopped = false;

        const schedule = () => {
            clearTimeout(timer);
            if (!stopped) timer = setTimeout(poll, 5000);
        };
        const poll = async () => {
            if (document.visibilityState === 'visible') {
                await cargarNotificaciones();
            }
            schedule();
        };

        window.notificationPoller = {
            stop() { stopped = true; clearTimeout(timer); },
            poll,
        };

        cargarNotificaciones();
        schedule();
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') poll();
        });
    })();

    async function marcarNotificacionesLeidas(categoria, ids = []) {
        const body = new FormData();
        body.append('categoria', categoria);
        ids.forEach(id => body.append('ids[]', id));

        const response = await fetch('{{ route("notificaciones.marcar_leidas", [], false) }}', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body,
        });
        if (!response.ok) throw new Error(`HTTP ${response.status}`);
        await cargarNotificaciones();
    }

    document.querySelectorAll('[data-mark-read-form]').forEach(form => {
        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            try {
                const response = await fetch(this.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: new FormData(this),
                });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                await cargarNotificaciones();
            } catch (error) {
                console.error('Error marcando notificaciones como leídas:', error);
            }
        });
    });

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(event) {
        if (!event.target.closest('.header-indicator-container')) {
            document.querySelectorAll('.header-indicator-dropdown').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        }
    });

    // ─── SWEETALERT 2 PARA CONFIRMACIONES Y ALERTAS FLASH ───
    
    // Función global para confirmaciones de formularios
    function confirmarAccion(event, form, titulo, texto) {
        event.preventDefault();
        Swal.fire({
            title: titulo,
            text: texto || 'Esta acción podría afectar los registros.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b11226',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'rounded-xl',
                confirmButton: 'rounded-lg px-4 py-2 font-semibold',
                cancelButton: 'rounded-lg px-4 py-2 font-semibold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (window.ShellNavigation) {
                    window.ShellNavigation.submitForm(form);
                } else {
                    form.submit();
                }
            }
        });
    }

    // Configuración de Toasts para Alertas Flash
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: 'rounded-xl shadow-lg border border-gray-100',
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    @if(session('success'))
        Toast.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '{{ session("success") }}'
        });
    @endif

    @if(session('error'))
        Toast.fire({
            icon: 'error',
            title: 'Atención',
            text: '{{ session("error") }}'
        });
    @endif

    @if($errors->any())
        Toast.fire({
            icon: 'error',
            title: 'Error',
            text: '{{ $errors->first() }}'
        });
    @endif
</script>

<template id="initial-module-scripts">
    @stack('scripts')
</template>

@include('layouts.shell-navigation')

</body>
</html>
@endif
