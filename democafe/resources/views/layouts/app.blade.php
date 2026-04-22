<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Sistema Jurídico</title>

    @vite(['resources/css/app.css'])

    <!-- ICONOS -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>

<body class="flex bg-gray-100">

<!-- Sidebar -->
<aside class="w-64 bg-white text-gray-700 min-h-screen p-4 shadow">

    <!-- Logo -->
    <div class="flex flex-col items-center text-center mb-8">

    <img src="{{ asset('imagenes/federacion cafeteros logo.png') }}" 
         class="w-20 h-20 object-contain mb-2">

    <span class="text-sm font-semibold text-gray-800 leading-tight">
        Federación Nacional de Cafeteros
    </span>

</div>

    <!-- Menú -->
    <ul class="space-y-2 text-sm">

        <!-- ACTIVO -->
        <li class="flex items-center gap-2 p-2 rounded bg-[#b11226] text-white cursor-pointer">
            <i data-lucide="layout-dashboard"></i>
            Dashboard
        </li>

        <!-- ITEMS -->
        <li class="flex items-center gap-2 p-2 rounded hover:bg-[#b11226] hover:text-white transition cursor-pointer">
            <i data-lucide="folder"></i>
            Casos
        </li>

        <li class="flex items-center gap-2 p-2 rounded hover:bg-[#b11226] hover:text-white transition cursor-pointer">
            <i data-lucide="plus-circle"></i>
            Crear Caso
        </li>

        <li class="flex items-center gap-2 p-2 rounded hover:bg-[#b11226] hover:text-white transition cursor-pointer">
            <i data-lucide="file-text"></i>
            Tipos de Documento
        </li>

        <li class="flex items-center gap-2 p-2 rounded hover:bg-[#b11226] hover:text-white transition cursor-pointer">
            <i data-lucide="users"></i>
            Usuarios
        </li>

        <li class="flex items-center gap-2 p-2 rounded hover:bg-[#b11226] hover:text-white transition cursor-pointer">
            <i data-lucide="clock"></i>
            Historial Global
        </li>

    </ul>

</aside>
<!-- Contenido -->
<div class="flex-1">

    <!-- Header -->
    <header class="bg-white p-4 flex justify-between items-center shadow">

    <h1 class="font-semibold text-[#b11226]">
        Sistema de Gestión de Casos Jurídicos
    </h1>

    <div class="flex items-center gap-4">

        <span class="text-sm text-gray-600">
            {{ auth()->user()->name ?? 'Usuario' }}
        </span>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="bg-gray-200 px-3 py-1 rounded text-sm">
                Cerrar sesión
            </button>
        </form>

    </div>

</header>

  


    <main class="p-6">
        @yield('content')
    </main>

</div>

<script>
    lucide.createIcons();
</script>

</body>
</html>