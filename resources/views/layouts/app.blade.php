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

    <style>
        /* ── Variables de color del sistema ── */
        :root {
            --rojo-fnc:     #b11226;
            --rojo-fnc-dark:#8e0e1e;
            --rojo-fnc-light:#f9e6e9;
            --gris-sidebar: #f8f8f8;
            --sidebar-w:    256px;
        }

        * { font-family: 'Inter', sans-serif; box-sizing: border-box; }

        body { background: #f1f5f9; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: var(--sidebar-w);
            min-height: 100vh;
            background: #ffffff;
            border-right: 1px solid #e8ecf0;
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0; left: 0;
            z-index: 40;
        }

        .sidebar-logo {
            padding: 24px 20px 20px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .sidebar-logo img {
            width: 64px;
            height: 64px;
            object-fit: contain;
        }

        .sidebar-logo .org-name {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            text-align: center;
            line-height: 1.4;
        }

        .sidebar-nav {
            padding: 16px 12px;
            flex: 1;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            color: #4b5563;
            text-decoration: none;
            transition: all 0.18s ease;
            margin-bottom: 2px;
            cursor: pointer;
        }

        .nav-item:hover {
            background: var(--rojo-fnc-light);
            color: var(--rojo-fnc);
        }

        .nav-item.active {
            background: var(--rojo-fnc);
            color: #ffffff;
        }

        .nav-item svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }

        .nav-section-title {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #9ca3af;
            padding: 12px 12px 6px;
        }

        /* ── Contenido principal ── */
        .main-wrapper {
            margin-left: var(--sidebar-w);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        /* ── Header ── */
        .top-header {
            background: #ffffff;
            border-bottom: 1px solid #e8ecf0;
            padding: 0 28px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 30;
        }

        .header-title {
            font-size: 15px;
            font-weight: 700;
            color: var(--rojo-fnc);
            letter-spacing: -0.01em;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        /* ── Campana de notificaciones ── */
        .notif-bell {
            position: relative;
            cursor: pointer;
            color: #6b7280;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: background 0.15s;
        }

        .notif-bell:hover { background: #f3f4f6; }

        .notif-badge {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 16px;
            height: 16px;
            background: var(--rojo-fnc);
            border-radius: 50%;
            font-size: 10px;
            font-weight: 700;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Avatar usuario ── */
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 34px;
            height: 34px;
            background: var(--rojo-fnc);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            color: white;
        }

        .user-name-label {
            font-size: 13px;
            font-weight: 600;
            color: #111827;
        }

        .user-role-label {
            font-size: 11px;
            color: #6b7280;
        }

        .btn-logout {
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #6b7280;
            transition: all 0.15s;
        }

        .btn-logout:hover { background: var(--rojo-fnc); color: white; }

        /* ── Contenido ── */
        .page-content {
            padding: 28px;
            flex: 1;
        }

        /* ── Cards estadísticas ── */
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 1px 4px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.10);
            transform: translateY(-2px);
        }

        .stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Badges de estado ── */
        .badge {
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
            letter-spacing: 0.02em;
        }

        .badge-proceso   { background: #dbeafe; color: #1d4ed8; }
        .badge-completado{ background: #dcfce7; color: #166534; }
        .badge-finalizado{ background: #fce7f3; color: #9d174d; }
        .badge-pendiente { background: #fef9c3; color: #713f12; }
        .badge-pendiente-t { background: #fef9c3; color: #713f12; }
        .badge-completada{ background: #dcfce7; color: #166534; }

        /* ── Tabla ── */
        .tabla-casos {
            width: 100%;
            font-size: 13px;
            border-collapse: collapse;
        }

        .tabla-casos thead tr {
            background: #f9fafb;
            border-bottom: 2px solid #f0f0f0;
        }

        .tabla-casos th {
            padding: 10px 14px;
            text-align: left;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #6b7280;
        }

        .tabla-casos tbody tr {
            border-bottom: 1px solid #f5f5f5;
            transition: background 0.12s;
        }

        .tabla-casos tbody tr:hover { background: #fafafa; }

        .tabla-casos td { padding: 11px 14px; color: #374151; }

        .radicado-link {
            color: var(--rojo-fnc);
            font-weight: 600;
            font-size: 12.5px;
            text-decoration: none;
        }

        .radicado-link:hover { text-decoration: underline; }

        .tipo-link { color: #2563eb; font-weight: 500; font-size: 12.5px; }

        /* ── Botón principal ── */
        .btn-primary {
            background: var(--rojo-fnc);
            color: white;
            padding: 9px 18px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: background 0.18s;
        }

        .btn-primary:hover { background: var(--rojo-fnc-dark); }

        .btn-secondary {
            background: white;
            color: #374151;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            border: 1px solid #e5e7eb;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.18s;
        }

        .btn-secondary:hover { border-color: #d1d5db; background: #f9fafb; }

        .btn-ver {
            background: #f3f4f6;
            color: #374151;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: background 0.15s;
        }

        .btn-ver:hover { background: #e5e7eb; }

        /* ── Alertas flash ── */
        .alert {
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 500;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-success { background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .alert-warning { background: #fef9c3; color: #713f12; border: 1px solid #fde68a; }

        /* ── Cards de tareas ── */
        .tarea-card {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            border: 1px solid #f0f0f0;
            transition: all 0.18s;
        }

        .tarea-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-color: #e5e7eb;
        }

        /* ── Sección de página ── */
        .page-header {
            margin-bottom: 24px;
        }

        .page-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 4px;
        }

        .page-header p {
            font-size: 13.5px;
            color: #6b7280;
            margin: 0;
        }

        /* ── Observaciones ── */
        .observacion-item {
            background: #f8fafc;
            border-left: 3px solid var(--rojo-fnc);
            border-radius: 0 8px 8px 0;
            padding: 12px 14px;
        }

        /* ── Formulario ── */
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
            font-size: 13.5px;
            color: #111827;
            transition: border-color 0.15s, background 0.15s;
            outline: none;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--rojo-fnc);
            background: white;
            box-shadow: 0 0 0 3px rgba(177,18,38,0.08);
        }

        .form-error {
            font-size: 12px;
            color: #dc2626;
            margin-top: 4px;
        }

        /* ── Responsive ── */
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>

<body>

{{-- ══════════════════════════════════════════════════════════════
     SIDEBAR
══════════════════════════════════════════════════════════════ --}}
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

        <span class="nav-section-title">Gestión</span>

        <a href="#" class="nav-item">
            <i data-lucide="file-text" style="width:18px;height:18px;"></i>
            Tipos de Proceso
        </a>

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

{{-- ══════════════════════════════════════════════════════════════
     CONTENIDO PRINCIPAL
══════════════════════════════════════════════════════════════ --}}
<div class="main-wrapper">

    {{-- Header --}}
    <header class="top-header">
        <span class="header-title">Sistema de Gestión de Casos Jurídicos</span>

        <div class="header-right">

            {{-- Notificaciones --}}
            <div class="notif-bell" title="Notificaciones">
                <i data-lucide="bell" style="width:20px;height:20px;"></i>
                @php $sinLeer = auth()->user()?->notificacionesSinLeer() ?? 0; @endphp
                @if($sinLeer > 0)
                    <span class="notif-badge">{{ $sinLeer > 9 ? '9+' : $sinLeer }}</span>
                @endif
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
</script>

@stack('scripts')

</body>
</html>