<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login</title>

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 flex items-center justify-center min-h-screen">

    <div class="w-full max-w-md bg-white p-8 rounded-xl shadow">

        <!-- LOGO -->
        <div class="flex flex-col items-center mb-6">

            <img src="{{ asset('imagenes/federacion cafeteros logo.png') }}" 
                alt="Logo"
                class="w-28 h-28 object-contain bg-gray-50 p-3 rounded-xl shadow-sm mb-4">

            <h1 class="text-xl font-bold text-red-700 text-center leading-tight">
                Sistema de Gestión de Casos Jurídicos
            </h1>

            <p class="text-gray-500 text-sm mt-2 text-center">
                Ingresa tus credenciales para continuar
            </p>

        </div>

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-2 rounded mb-3 text-sm">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-2 rounded mb-3 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- FORMULARIO -->
        <form method="POST" action="{{ route('login.post') }}">
        @csrf

            <!-- Email -->
            <div class="mb-4">
                <label class="block text-sm mb-1">Correo electrónico</label>
                <input type="email" name="email"
                    class="w-full p-3 border rounded-lg bg-gray-100"
                    placeholder="usuario@cafedecolombia.com">
            </div>

            <!-- Password -->
            <div class="mb-4" id="campoPassword">
                <label class="block text-sm mb-1">Contraseña</label>
                <input type="password" name="password"
                    class="w-full p-3 border rounded-lg bg-gray-100">
            </div>

            <!-- Checkbox -->
            <div class="mb-4 flex items-center">
                <input type="checkbox" name="recuperar" id="recuperar" class="mr-2">
                <span class="text-sm">Solicitar recuperación de contraseña</span>
            </div>

            <!-- Botón -->
            <button id="btnLogin"
                class="w-full bg-red-700 text-white py-3 rounded-lg font-semibold transition">
                Iniciar sesión
            </button>

        </form>

    </div>

    <!-- SCRIPT -->
    <script>
        const checkbox = document.getElementById('recuperar');
        const boton = document.getElementById('btnLogin');
        const campoPassword = document.getElementById('campoPassword');

        checkbox.addEventListener('change', function () {

            if (this.checked) {
                boton.innerText = 'Enviar solicitud';

                // cambia color
                boton.classList.remove('bg-red-700');
                boton.classList.add('bg-gray-700');

                // opcional: ocultar contraseña
                campoPassword.style.display = 'none';

            } else {
                boton.innerText = 'Iniciar sesión';

                boton.classList.remove('bg-gray-700');
                boton.classList.add('bg-red-700');

                campoPassword.style.display = 'block';
            }

        });
    </script>

</body>
</html>