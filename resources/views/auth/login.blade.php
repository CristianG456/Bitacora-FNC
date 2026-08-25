<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Institucional - Sistema de Bitácoras</title>
    
    <link rel="icon" href="{{ asset('imagenes/federacion cafeteros logo.png') }}" type="image/png">

    <!-- Tailwind -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="bg-white min-h-screen flex">

    <!-- Lado Izquierdo (Rojo) -->
    <div class="hidden lg:flex lg:w-1/2 bg-[#9F1932] relative overflow-hidden flex-col justify-end p-16 text-white shadow-[10px_0_20px_rgba(0,0,0,0.1)] z-10">
        <!-- Círculos decorativos (Simulando la imagen) -->
        <div class="absolute top-[10%] left-[5%] w-[450px] h-[450px] rounded-full bg-white/5 pointer-events-none"></div>
        <div class="absolute bottom-[10%] left-[25%] w-[500px] h-[500px] rounded-full bg-white/5 pointer-events-none"></div>

        <div class="relative z-10 mb-16">
            <span class="inline-block px-4 py-1.5 rounded-full bg-white/20 text-xs font-semibold mb-6 tracking-wide">
                Sistema Institucional
            </span>
            <h1 class="text-5xl font-bold mb-6 tracking-tight leading-tight">Sistema de<br>Bitácoras</h1>
            <p class="text-[15px] text-white/80 max-w-md leading-relaxed font-light">
                Comité Departamental de Cafeteros del Tolima —<br>
                Plataforma de registro y seguimiento de casos y tareas jurídicas.
            </p>
        </div>
    </div>

    <!-- Lado Derecho (Formulario) -->
    <div class="w-full lg:w-1/2 flex flex-col justify-center items-center p-8 bg-white relative">
        <div class="w-full max-w-sm">
            
            <!-- Logo y Entidad -->
            <div class="flex flex-col items-center mb-10">
                <img src="{{ asset('imagenes/federacion cafeteros logo.png') }}" alt="Federación Nacional de Cafeteros de Colombia" class="w-40 mb-3">
                <div class="text-center text-[#7A1525]">
                    <h2 class="text-lg font-bold">Comité de Cafeteros</h2>
                    <h3 class="text-md font-normal">del Tolima</h3>
                </div>
            </div>

            <!-- Título Formulario -->
            <div class="text-center mb-8">
                <h1 class="text-[22px] font-bold text-gray-900 mb-1">Acceso Institucional</h1>
                <p class="text-gray-500 text-sm">Ingresa tus credenciales para continuar</p>
            </div>

            @if(session('success'))
                <div class="bg-green-50 text-green-700 p-3 rounded-lg mb-4 text-sm border border-green-200 text-center">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-50 text-red-700 p-3 rounded-lg mb-4 text-sm border border-red-200 text-center">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" autocomplete="off" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Correo electrónico</label>
                    <input type="email" name="email"
                        class="w-full p-3.5 rounded-lg bg-gray-50 border border-gray-100 focus:bg-white focus:border-[#9F1932] focus:ring-1 focus:ring-[#9F1932] outline-none transition text-sm text-gray-800 placeholder-gray-400"
                        placeholder="nombre@cafedecolombia.com"
                        autocomplete="off" required>
                </div>

                <div id="campoPassword">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Contraseña</label>
                    <input type="password" name="password"
                        class="w-full p-3.5 rounded-lg bg-gray-50 border border-gray-100 focus:bg-white focus:border-[#9F1932] focus:ring-1 focus:ring-[#9F1932] outline-none transition text-sm text-gray-800 placeholder-gray-400"
                        placeholder="••••••••">
                </div>

                <!-- Checkbox Recuperar -->
                <div class="flex items-center pt-1">
                    <input type="checkbox" name="recuperar" id="recuperar" class="w-4 h-4 text-[#9F1932] bg-gray-100 border-gray-300 rounded focus:ring-[#9F1932] cursor-pointer">
                    <label for="recuperar" class="ml-2 text-xs text-gray-600 cursor-pointer hover:text-gray-900 transition">Solicitar recuperación de contraseña</label>
                </div>

                <button id="btnLogin" type="submit"
                    class="w-full bg-[#9F1932] hover:bg-[#821328] text-white py-3.5 rounded-lg text-sm font-semibold transition shadow-sm mt-2">
                    Ingresar al sistema
                </button>
            </form>

            <div class="mt-16 text-center">
                <p class="text-[11px] text-gray-400">Federación Nacional de Cafeteros de Colombia</p>
            </div>
        </div>
    </div>

    <!-- SCRIPT -->
    <script>
        // Al cargar login: limpiar cualquier estado de pestaña anterior
        sessionStorage.removeItem('tab_token');
        document.querySelector('form') && document.querySelector('form').addEventListener('submit', function (event) {
            var token = (typeof crypto !== 'undefined' && crypto.randomUUID)
                ? crypto.randomUUID()
                : 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = Math.random() * 16 | 0;
                    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                });

            sessionStorage.setItem('tab_token', token);
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'tab_token';
            input.value = token;
            event.currentTarget.appendChild(input);
        });

        const checkbox = document.getElementById('recuperar');
        const boton = document.getElementById('btnLogin');
        const campoPassword = document.getElementById('campoPassword');
        const passwordInput = document.querySelector('input[name="password"]');

        checkbox.addEventListener('change', function () {
            if (this.checked) {
                boton.innerText = 'Enviar solicitud';
                boton.classList.remove('bg-[#9F1932]', 'hover:bg-[#821328]');
                boton.classList.add('bg-gray-800', 'hover:bg-gray-900');
                
                campoPassword.style.display = 'none';
                passwordInput.removeAttribute('required');
            } else {
                boton.innerText = 'Ingresar al sistema';
                boton.classList.remove('bg-gray-800', 'hover:bg-gray-900');
                boton.classList.add('bg-[#9F1932]', 'hover:bg-[#821328]');
                
                campoPassword.style.display = 'block';
                passwordInput.setAttribute('required', 'required');
            }
        });
        
        // Ejecutar al cargar por si el navegador recuerda el checkbox
        if(checkbox.checked) {
            checkbox.dispatchEvent(new Event('change'));
        }
    </script>

</body>
</html>
