<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sistema de Gestión de Casos Jurídicos')</title>
    <meta name="description" content="Sistema de Gestión de Casos Jurídicos - Federación Nacional de Cafeteros">

    {{-- Tailwind CDN (ya está en el proyecto) --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- Lucide Icons --}}
    <script src="https://unpkg.com/lucide@latest"></script>

    {{-- Google Fonts --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @stack('styles')
</head>

<body>

{{-- SIDEBAR --}}
<aside class="sidebar">

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

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <span class="nav-section-title">Gestión</span>

        <a href="{{ route('tipos.index') }}" class="nav-item {{ request()->routeIs('tipos.*') ? 'active' : '' }}">
            <i data-lucide="file-text" style="width:18px;height:18px;"></i>
            Tipos de Documento
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador']))
        <a href="{{ route('usuarios.index') }}" class="nav-item {{ request()->routeIs('usuarios.*') ? 'active' : '' }}">
            <i data-lucide="users" style="width:18px;height:18px;"></i>
            Usuarios
        </a>
        @endif

        @if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica']))
        <a href="{{ route('historial.index') }}" class="nav-item {{ request()->routeIs('historial.*') ? 'active' : '' }}">
            <i data-lucide="clock" style="width:18px;height:18px;"></i>
            Historial Global
        </a>
        @endif

    </nav>

</aside>

{{-- CONTENIDO PRINCIPAL --}}
<div class="main-wrapper">

    {{-- Header --}}
    <header class="top-header">
        <span class="header-title">Sistema de Gestión de Casos Jurídicos</span>

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

    {{-- Flash messages --}}
    <div style="padding: 0 28px; padding-top: 20px;">
        @if(session('success'))
            <div class="alert alert-success">
                <i data-lucide="check-circle-2" style="width:16px;height:16px;flex-shrink:0;"></i>
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-error">
                <i data-lucide="alert-circle" style="width:16px;height:16px;flex-shrink:0;"></i>
                {{ session('error') }}
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">
                <i data-lucide="alert-triangle" style="width:16px;height:16px;flex-shrink:0;"></i>
                <div>
                    @foreach($errors->all() as $err)
                        <div>{{ $err }}</div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Contenido de página --}}
    <main class="page-content">
        @yield('content')
    </main>

</div>

<script>
    lucide.createIcons();

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
</script>

@stack('scripts')

</body>
</html>