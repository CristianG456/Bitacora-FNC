@extends('layouts.app')

@section('content')

<h1 class="text-2xl font-bold mb-1">Dashboard</h1>
<p class="text-gray-500 mb-6">Vista general de casos jurídicos</p>

<!-- Cards -->
<div class="grid grid-cols-4 gap-4 mb-6">

    <!-- Total Casos -->
    <div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">
        <div>
            <p class="text-gray-500 text-sm">Total Casos</p>
            <h2 class="text-2xl font-bold text-gray-800">4</h2>
        </div>
        <div class="bg-gray-100 p-3 rounded-full">
            <i data-lucide="briefcase" class="text-gray-600"></i>
        </div>
    </div>

    <!-- En Proceso -->
    <div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">
        <div>
            <p class="text-gray-500 text-sm">En Proceso</p>
            <h2 class="text-2xl font-bold text-blue-500">1</h2>
        </div>
        <div class="bg-blue-100 p-3 rounded-full">
            <i data-lucide="loader" class="text-blue-500"></i>
        </div>
    </div>

    <!-- Completados -->
    <div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">
        <div>
            <p class="text-gray-500 text-sm">Completados</p>
            <h2 class="text-2xl font-bold text-green-500">1</h2>
        </div>
        <div class="bg-green-100 p-3 rounded-full">
            <i data-lucide="check-circle" class="text-green-500"></i>
        </div>
    </div>

    <!-- Finalizados -->
    <div class="bg-white p-4 rounded-xl shadow flex items-center justify-between">
        <div>
            <p class="text-gray-500 text-sm">Finalizados</p>
            <h2 class="text-2xl font-bold text-[#b11226]">1</h2>
        </div>
        <div class="bg-red-100 p-3 rounded-full">
            <i data-lucide="flag" class="text-[#b11226]"></i>
        </div>
    </div>

</div>
<!-- Botón -->
<div class="flex justify-end mb-4">
    <a href="{{ route('casos.crear') }}"
    class="bg-red-700 text-white px-4 py-2 rounded-lg">
        Crear Nuevo Caso
    </a>
</div>

<!-- Tabla -->
<div class="bg-white p-4 rounded-lg shadow">

    <h2 class="font-semibold mb-4">Casos Recientes</h2>

    <table class="w-full text-sm border rounded-lg overflow-hidden">
    <thead class="bg-gray-100">
        <tr class="text-left">
            <th class="p-2">Radicado</th>
            <th class="p-2">Tipo</th>
            <th class="p-2">Descripción</th>
            <th class="p-2">Estado</th>
            <th class="p-2">Fecha</th>
            <th class="p-2">Acción</th>
        </tr>
    </thead>

    <tbody>
        <tr class="border-t hover:bg-gray-50">
            <td class="p-2">JUR-2024-001</td>
            <td class="p-2">Laboral</td>
            <td class="p-2">Revisión contrato...</td>
            <td class="p-2">
                <span class="px-2 py-1 bg-blue-500 text-white rounded-full text-xs">
                    En Proceso
                </span>
            </td>
            <td class="p-2">10/04/2024</td>
            <td class="p-2">
                <button class="bg-gray-200 px-3 py-1 rounded-lg">
                    Ver
                </button>
            </td>
        </tr>
    </tbody>
</table>

</div>
<script src="https://cdn.tailwindcss.com"></script>

@endsection