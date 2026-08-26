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

<body class="bg-white min-h-screen flex lg:h-screen lg:overflow-hidden">

    <!-- Lado Izquierdo (Rojo) -->
    <div class="hidden lg:flex lg:h-screen lg:w-1/2 bg-[#9F1932] relative overflow-hidden flex-col justify-end p-16 text-white shadow-[10px_0_20px_rgba(0,0,0,0.1)] z-10">
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
    <div class="w-full lg:h-screen lg:w-1/2 lg:overflow-hidden flex flex-col justify-center items-center p-8 lg:py-4 bg-white relative">
        <div class="w-full max-w-sm">
            
            <!-- Logo y Entidad -->
            <div class="flex flex-col items-center mb-10 lg:mb-6">
                <img src="{{ asset('imagenes/federacion cafeteros logo.png') }}" alt="Federación Nacional de Cafeteros de Colombia" class="w-40 lg:w-32 mb-3">
                <div class="text-center text-[#7A1525]">
                    <h2 class="text-lg font-bold">Comité de Cafeteros</h2>
                    <h3 class="text-md font-normal">del Tolima</h3>
                </div>
            </div>

            <!-- Título Formulario -->
            <div class="text-center mb-8 lg:mb-6">
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

            <form method="POST" action="{{ route('login.post') }}" autocomplete="off" class="space-y-5 lg:space-y-4">
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

                <!-- Checkbox Recuperar y términos informativos -->
                <div class="pt-1 flex flex-col items-center gap-2">
                    <div class="flex items-center justify-center">
                        <input type="checkbox" name="recuperar" id="recuperar" class="w-4 h-4 text-[#9F1932] bg-gray-100 border-gray-300 rounded focus:ring-[#9F1932] cursor-pointer">
                        <label for="recuperar" class="ml-2 text-xs text-gray-600 cursor-pointer hover:text-gray-900 transition">Solicitar recuperación de contraseña</label>
                    </div>
                    <button id="abrirTerminos" type="button" class="text-xs font-medium text-[#9F1932] hover:text-[#821328] hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9F1932] focus-visible:ring-offset-2 rounded">
                        Términos y condiciones
                    </button>
                </div>

                <button id="btnLogin" type="submit"
                    class="w-full bg-[#9F1932] hover:bg-[#821328] text-white py-3.5 rounded-lg text-sm font-semibold transition shadow-sm mt-2">
                    Ingresar al sistema
                </button>
            </form>

            <div class="mt-16 lg:mt-8 text-center">
                <p class="text-[11px] text-gray-400">Federación Nacional de Cafeteros de Colombia</p>
            </div>
        </div>
    </div>

    <!-- Modal informativo de tratamiento de datos -->
    <div id="modalTerminos" class="hidden fixed inset-0 z-[100] items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="tituloTerminos">
        <div class="relative flex w-full max-w-3xl max-h-[90vh] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4 sm:px-7">
                <div>
                    <h2 id="tituloTerminos" class="text-lg font-bold text-gray-900 sm:text-xl">Tratamiento de Datos Personales</h2>
                    <p class="mt-1 text-xs text-gray-500 sm:text-sm">De conformidad con la Ley 1581 de 2012 y el Decreto 1377 de 2013</p>
                </div>
                <button type="button" data-cerrar-terminos class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xl text-gray-600 transition hover:bg-gray-200 hover:text-gray-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9F1932]" aria-label="Cerrar términos y condiciones">
                    &times;
                </button>
            </div>

            <div class="overflow-y-auto px-5 py-5 text-sm leading-6 text-gray-700 sm:px-7 sm:py-6">
                <p class="mb-5 rounded-lg border border-[#9F1932]/15 bg-[#9F1932]/5 p-4">
                    <strong class="text-gray-900">Aviso de Consentimiento:</strong> Al iniciar sesión e ingresar al aplicativo, usted acepta de forma expresa y libre el tratamiento de sus datos personales bajo las condiciones descritas en este documento.
                </p>

                <p class="mb-5">
                    De acuerdo con la Ley 1581 de 2012 (Ley de Protección de Datos Personales) y el Decreto 1377 de 2013, la Federación Nacional de Cafeteros de Colombia (FNC) y el Comité Departamental de Cafeteros del Tolima informan que los datos personales recolectados a través de esta plataforma de Gestión de Proveedores serán tratados de forma segura y confidencial.
                </p>

                <section class="mb-5">
                    <h3 class="mb-2 font-bold text-gray-900">1. Finalidad del Tratamiento</h3>
                    <p class="mb-2">Los datos recolectados y almacenados en este aplicativo se utilizarán con las siguientes finalidades institucionales:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Registro, control y seguimiento de la información gestionada mediante el Sistema de Gestión de Proveedores.</li>
                        <li>Consolidación de estadísticas, reportes y analítica institucional.</li>
                        <li>Envío de notificaciones y alertas relacionadas con las funciones del sistema.</li>
                        <li>Validación de credenciales de acceso institucional y auditoría de seguridad del sistema.</li>
                    </ul>
                </section>

                <section class="mb-5">
                    <h3 class="mb-2 font-bold text-gray-900">2. Derechos del Titular</h3>
                    <p class="mb-2">Como titular de la información, usted tiene derecho a:</p>
                    <ul class="list-disc space-y-1 pl-5">
                        <li>Conocer, actualizar y rectificar sus datos personales frente a los Responsables del Tratamiento.</li>
                        <li>Solicitar prueba de la autorización otorgada para el tratamiento de sus datos.</li>
                        <li>Ser informado del uso que se le ha dado a sus datos personales.</li>
                        <li>Presentar quejas ante la Superintendencia de Industria y Comercio por infracciones a lo dispuesto en la normatividad vigente.</li>
                        <li>Revocar la autorización y/o solicitar la supresión del dato cuando en el tratamiento no se respeten los principios, derechos y garantías constitucionales y legales.</li>
                    </ul>
                </section>

                <section class="mb-5">
                    <h3 class="mb-2 font-bold text-gray-900">3. Seguridad de la Información</h3>
                    <p>La FNC adopta medidas de seguridad de índole técnica, física y organizativa para evitar la alteración, pérdida, mal uso, divulgación o acceso no autorizado de sus datos personales.</p>
                </section>

                <section>
                    <h3 class="mb-2 font-bold text-gray-900">4. Canales de Atención</h3>
                    <p>Para ejercer sus derechos de consulta, reclamo, actualización y supresión de datos, puede comunicarse a través del correo institucional:</p>
                    <p class="my-3 break-all font-semibold text-[#9F1932]">protecciondedatos@cafedecolombia.com</p>
                    <p>o radicar su solicitud en la sede principal del Comité Departamental de Cafeteros del Tolima.</p>
                </section>
            </div>

            <div class="border-t border-gray-200 px-5 py-4 text-right sm:px-7">
                <button type="button" data-cerrar-terminos class="rounded-lg bg-[#9F1932] px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-[#821328] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#9F1932] focus-visible:ring-offset-2">
                    Cerrar
                </button>
            </div>
        </div>
    </div>
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
        const abrirTerminos = document.getElementById('abrirTerminos');
        const modalTerminos = document.getElementById('modalTerminos');
        let focoAntesDelModal = null;

        function mostrarTerminos() {
            focoAntesDelModal = document.activeElement;
            modalTerminos.classList.remove('hidden');
            modalTerminos.classList.add('flex');
            modalTerminos.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            modalTerminos.querySelector('[data-cerrar-terminos]').focus();
        }

        function ocultarTerminos() {
            modalTerminos.classList.add('hidden');
            modalTerminos.classList.remove('flex');
            modalTerminos.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('overflow-hidden');
            focoAntesDelModal?.focus();
        }

        abrirTerminos.addEventListener('click', mostrarTerminos);
        modalTerminos.querySelectorAll('[data-cerrar-terminos]').forEach(function (button) {
            button.addEventListener('click', ocultarTerminos);
        });
        modalTerminos.addEventListener('click', function (event) {
            if (event.target === modalTerminos) ocultarTerminos();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && modalTerminos.getAttribute('aria-hidden') === 'false') {
                ocultarTerminos();
            }
        });

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
