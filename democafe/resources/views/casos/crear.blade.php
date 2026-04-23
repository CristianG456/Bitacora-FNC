@extends('layouts.app')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200">
    <div class="flex items-center gap-2 mb-3">
        <a href="{{ route('dashboard') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">Crear Nuevo Caso</h1>
    <p class="text-gray-500 text-sm mt-1">Completa la información del caso jurídico</p>
</div>

<form method="POST" action="{{ route('casos.guardar') }}" class="space-y-6">
    @csrf

    <!-- INFORMACIÓN DEL CASO -->
    <div class="bg-white px-8 py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Información del Caso
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Tipo de Caso</label>
                <select name="tipo_proceso_id" id="tipo_proceso_id" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm">
                    <option value="">Selecciona un tipo</option>
                    @foreach($tipos as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Subtipo</label>
                <select name="subtipo_proceso_id" id="subtipo_proceso_id" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm">
                    <option value="">Selecciona un subtipo</option>
                </select>
            </div>

            <div class="md:col-span-2">
                <label class="block text-xs font-semibold text-gray-900 mb-3">Descripción</label>
                <textarea name="descripcion" required
                    placeholder="Describe brevemente el caso..."
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition resize-none h-24"></textarea>
            </div>

        </div>
    </div>

    <!-- DATOS DEL SOLICITANTE -->
    <div class="bg-white px-8 py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Datos del Solicitante
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Nombre del Solicitante</label>
                <input type="text" name="nombre_solicitante" required
                    placeholder="Nombre completo"
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Documento del Solicitante</label>
                <input type="text" name="documento_solicitante" required
                    placeholder="Número de documento"
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">
            </div>

        </div>
    </div>

    <!-- DOCUMENTO -->
    <div class="bg-white px-8 py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Documento
        </h2>

        <div>
            <label class="block text-xs font-semibold text-gray-900 mb-3">Link de Google Drive</label>
            <input type="url" name="enlace_google_drive"
                placeholder="https://drive.google.com/..."
                class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">

            <p class="text-xs text-gray-400 mt-3">
                Asegúrate de que el enlace tenga los permisos adecuados
            </p>
        </div>
    </div>

    <!-- ASIGNACIÓN DE USUARIOS Y TAREAS -->
    <div class="bg-white px-8 py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Asignación de Usuarios y Tareas
        </h2>

        <!-- BUSCADOR -->
        <div class="mb-8">
            <label class="block text-xs font-semibold text-gray-900 mb-3">Buscar Usuario</label>
            <div class="relative">
                <svg class="absolute left-4 top-3.5 w-5 h-5 text-gray-400 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
                <input type="text" id="buscar_usuario"
                    placeholder="Buscar por nombre o email..."
                    class="w-full pl-12 pr-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">
            </div>
        </div>

        <!-- USUARIOS ASIGNADOS -->
        <div id="usuarios-container" class="border-2 border-dashed border-gray-300 rounded-md p-12 text-center bg-gray-50 min-h-56 flex items-center justify-center">
            <div>
                <p class="text-gray-400 text-sm">No hay usuarios asignados. Usa el buscador para agregar usuarios.</p>
            </div>
        </div>

        <!-- HIDDEN: Contenedor para usuarios asignados  -->
        <div id="usuarios-asignados" class="mt-6 space-y-6"></div>

    </div>

    <!-- BOTONES -->
    <div class="flex justify-end gap-3 pt-8 border-t border-gray-200 mt-8">

        <a href="{{ route('dashboard') }}"
        class="px-6 py-2 bg-gray-100 border border-gray-300 text-gray-700 text-sm font-medium rounded-md hover:bg-gray-200 transition">
            Cancelar
        </a>

        <button type="submit"
            class="px-6 py-2 bg-[#c84661] hover:bg-[#b53a52] text-white text-sm font-medium rounded-md transition">
            Crear Caso
        </button>

    </div>

</form>

<script>
const tipos = @json($tipos);

document.getElementById('tipo_proceso_id').addEventListener('change', function () {

    const tipoId = this.value;
    const subtipoSelect = document.getElementById('subtipo_proceso_id');

    subtipoSelect.innerHTML = '<option value="">Selecciona un subtipo</option>';

    const tipo = tipos.find(t => t.id == tipoId);

    if (tipo) {
        tipo.subtipos.forEach(sub => {
            const option = document.createElement('option');
            option.value = sub.id;
            option.textContent = sub.nombre;
            subtipoSelect.appendChild(option);
        });
    }
});
</script>
@endsection
