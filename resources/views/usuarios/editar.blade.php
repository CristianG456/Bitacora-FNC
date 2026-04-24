@extends('layouts.app')

@section('title', 'Editar Usuario - Sistema Jurídico')

@section('content')

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex items-center gap-3">
        <a href="{{ route('usuarios.index') }}" class="text-gray-400 hover:text-gray-700 transition">
            <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Editar Usuario: {{ $usuario->name }}</h1>
            <p class="text-gray-500 text-sm mt-1">Modifica los datos y accesos del usuario</p>
        </div>
    </div>
</div>

<div class="max-w-3xl bg-white rounded-lg border border-gray-200 shadow-sm p-6">
    <form action="{{ route('usuarios.actualizar', $usuario->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Nombre -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Nombre Completo *</label>
                <input type="text" name="name" value="{{ old('name', $usuario->name) }}" required
                       pattern="[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+" title="Solo se permiten letras y espacios"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\sñÑáéíóúÁÉÍÓÚ]/g, '')"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                @error('name') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Correo Electrónico *</label>
                <input type="email" name="email" value="{{ old('email', $usuario->email) }}" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                @error('email') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Nueva Contraseña <span class="text-gray-400 font-normal">(Opcional)</span></label>
                <input type="password" name="password" minlength="8"
                       placeholder="Dejar en blanco para no cambiarla"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                @error('password') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Confirmar Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Confirmar Nueva Contraseña</label>
                <input type="password" name="password_confirmation" minlength="8"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
            </div>

            <!-- Rol -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Rol del Sistema *</label>
                <select name="role_id" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                    <option value="">Seleccione un rol...</option>
                    @foreach($roles as $rol)
                        <option value="{{ $rol->id }}" {{ old('role_id', $usuario->role_id) == $rol->id ? 'selected' : '' }}>
                            {{ $rol->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('role_id') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>

            <!-- Área -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Área / Departamento</label>
                <input type="text" name="area" value="{{ old('area', $usuario->area) }}"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                @error('area') <span class="text-red-500 text-xs mt-1 block">{{ $message }}</span> @enderror
            </div>
        </div>

        <!-- Estado -->
        <div class="mb-8 p-4 bg-gray-50 rounded-md border border-gray-200">
            <label class="flex items-center gap-3 cursor-pointer {{ auth()->id() === $usuario->id ? 'opacity-50' : '' }}">
                <input type="checkbox" name="activo" value="1" {{ old('activo', $usuario->activo) ? 'checked' : '' }}
                       {{ auth()->id() === $usuario->id ? 'disabled' : '' }}
                       class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                <span class="text-sm font-semibold text-gray-900">Usuario Activo</span>
            </label>
            <p class="text-xs text-gray-500 mt-1 ml-7">
                @if(auth()->id() === $usuario->id)
                    No puedes cambiar tu propio estado de actividad.
                @else
                    Si se desmarca, el usuario no podrá ingresar al sistema.
                @endif
            </p>
            @if(auth()->id() === $usuario->id)
                <input type="hidden" name="activo" value="1">
            @endif
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="{{ route('usuarios.index') }}" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                Cancelar
            </a>
            <button type="submit" class="btn-primary">
                Actualizar Usuario
            </button>
        </div>
    </form>
</div>

@endsection
