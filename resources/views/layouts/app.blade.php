<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
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

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    
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

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']))
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

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor']))
        <a href="{{ route('historial.index') }}" class="nav-item {{ request()->routeIs('historial.*') ? 'active' : '' }}">
            <i data-lucide="clock" style="width:18px;height:18px;"></i>
            Historial Global
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
            <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-900 focus:outline-none">
                <i data-lucide="menu" style="width:24px;height:24px;"></i>
            </button>
            <span class="header-title hidden sm:block">Sistema de Gestión de Casos Jurídicos</span>
            <span class="header-title sm:hidden text-sm">SGCJ</span>
        </div>

        <div class="header-right">

            {{-- Notificaciones --}}
            <div class="relative" id="notif-container">
                <div class="notif-bell cursor-pointer" title="Notificaciones" id="notif-btn" onclick="toggleNotificaciones()">
                    <i data-lucide="bell" style="width:20px;height:20px;"></i>
                    @php $sinLeer = auth()->user()?->notificacionesSinLeer() ?? 0; @endphp
                    @if($sinLeer > 0)
                        <span class="notif-badge" id="notif-badge-count">{{ $sinLeer > 9 ? '9+' : $sinLeer }}</span>
                    @endif
                </div>

                <!-- Menú desplegable -->
                <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-sm text-gray-800">Notificaciones</h3>
                        @if($sinLeer > 0)
                        <form action="{{ route('notificaciones.marcar_leidas') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Marcar leídas</button>
                        </form>
                        @endif
                    </div>
                    <div class="max-h-[300px] overflow-y-auto" id="notif-list">
                        <!-- Las notificaciones se cargan por JS -->
                        <div class="p-4 text-center text-sm text-gray-500">Cargando...</div>
                    </div>
                </div>
            </div>

            {{-- Usuario --}}
            <div class="user-info">
                <div class="user-avatar">
                    {{ strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1)) }}
                </div>
                <div>
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
    <main class="page-content">
        @yield('content')
    </main>

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

    function toggleNotificaciones() {
        const dropdown = document.getElementById('notif-dropdown');
        dropdown.classList.toggle('hidden');
        
        if (!dropdown.classList.contains('hidden')) {
            cargarNotificaciones();
        }
    }

    function cargarNotificaciones() {
        fetch('{{ route("notificaciones.recientes") }}')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('notif-list');
                list.innerHTML = '';
                
                if (data.length === 0) {
                    list.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No tienes notificaciones.</div>';
                    return;
                }

                data.forEach(n => {
                    const bg = n.leido ? 'bg-white' : 'bg-blue-50';
                    const icon = n.tipo === 'success' ? '<i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>' : '<i data-lucide="info" class="w-4 h-4 text-blue-500"></i>';
                    
                    list.innerHTML += `
                        <div class="p-3 border-b border-gray-50 flex gap-3 hover:bg-gray-50 transition ${bg}">
                            <div class="mt-0.5 shrink-0">${icon}</div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 mb-0.5">${n.titulo}</h4>
                                <p class="text-xs text-gray-600 leading-snug">${n.mensaje}</p>
                                <span class="text-[10px] text-gray-400 mt-1 block">${n.fecha}</span>
                            </div>
                        </div>
                    `;
                });
                lucide.createIcons();
            });
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(event) {
        const container = document.getElementById('notif-container');
        const dropdown = document.getElementById('notif-dropdown');
        if (container && dropdown && !container.contains(event.target)) {
            dropdown.classList.add('hidden');
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
                form.submit();
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

@stack('scripts')

</body>
</html>