<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cambio de Contraseña Obligatorio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen relative overflow-hidden">
    
    <!-- Background Decoration -->
    <div class="absolute top-0 left-0 w-full h-96 bg-[#c84661] -skew-y-6 transform origin-top-left -z-10 shadow-lg"></div>

    <div class="w-full max-w-md px-6">
        <div class="bg-white rounded-xl shadow-xl overflow-hidden">
            <div class="p-8">
                <!-- Logo o Icono -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 bg-[#c84661] text-white rounded-full flex items-center justify-center shadow-md">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                </div>

                <div class="text-center mb-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Cambio de Contraseña</h2>
                    <p class="text-sm text-gray-600 bg-yellow-50 border-l-4 border-yellow-400 p-3 text-left">
                        Por motivos de seguridad, debe cambiar su contraseña temporal antes de continuar utilizando el sistema.
                    </p>
                </div>

                @if ($errors->any())
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md text-sm text-red-700">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('password.change.update') }}" class="space-y-6" id="passwordForm">
                    @csrf

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Nueva Contraseña</label>
                        <div class="relative">
                            <input type="password" id="password" name="password" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#c84661] focus:border-[#c84661] transition bg-gray-50 text-gray-900 pr-10"
                                placeholder="••••••••">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="password">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-gray-500" id="passwordLengthHint">Mínimo 8 caracteres</p>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-gray-700 mb-2">Confirmar Contraseña</label>
                        <div class="relative">
                            <input type="password" id="password_confirmation" name="password_confirmation" required
                                class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#c84661] focus:border-[#c84661] transition bg-gray-50 text-gray-900 pr-10"
                                placeholder="••••••••">
                            <button type="button" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 toggle-password" data-target="password_confirmation">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                            </button>
                        </div>
                        <p class="mt-1 text-xs text-red-500 hidden" id="passwordMatchError">Las contraseñas no coinciden</p>
                    </div>

                    <button type="submit" id="submitBtn" disabled
                        class="w-full bg-[#c84661] hover:bg-[#a6354d] text-white font-bold py-3 px-4 rounded-lg transition shadow-md flex items-center justify-center gap-2 opacity-50 cursor-not-allowed">
                        <span>Guardar y Continuar</span>
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </button>
                </form>

                <div class="mt-6 border-t border-gray-100 pt-6">
                    <form method="POST" action="{{ route('logout') }}" class="text-center">
                        @csrf
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-900 transition">
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmInput = document.getElementById('password_confirmation');
        const submitBtn = document.getElementById('submitBtn');
        const matchError = document.getElementById('passwordMatchError');
        const lengthHint = document.getElementById('passwordLengthHint');

        // Contenedor para indicadores de seguridad (opcional, pero sugerido para UI amigable)
        const rules = [
            { id: 'rule-length', regex: /.{8,}/ },
            { id: 'rule-upper', regex: /[A-Z]/ },
            { id: 'rule-lower', regex: /[a-z]/ },
            { id: 'rule-number', regex: /[0-9]/ },
            { id: 'rule-special', regex: /[^A-Za-z0-9]/ }
        ];

        // Añadir elementos HTML dinámicamente para las reglas
        const hintContainer = document.createElement('div');
        hintContainer.className = 'mt-2 text-xs grid grid-cols-1 md:grid-cols-2 gap-1';
        hintContainer.innerHTML = `
            <div id="rule-length" class="text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Mínimo 8 caracteres</div>
            <div id="rule-upper" class="text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 Mayúscula</div>
            <div id="rule-lower" class="text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 Minúscula</div>
            <div id="rule-number" class="text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 Número</div>
            <div id="rule-special" class="text-gray-500 flex items-center gap-1"><svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> 1 Carácter especial</div>
        `;
        lengthHint.replaceWith(hintContainer);

        function validatePassword() {
            const pass = passwordInput.value;
            const confirm = confirmInput.value;
            
            let isPasswordValid = true;

            // Validar cada regla
            rules.forEach(rule => {
                const el = document.getElementById(rule.id);
                if (rule.regex.test(pass)) {
                    el.classList.remove('text-gray-500', 'text-red-500');
                    el.classList.add('text-green-500');
                } else {
                    el.classList.remove('text-green-500');
                    if (pass.length > 0) {
                        el.classList.add('text-red-500');
                    } else {
                        el.classList.add('text-gray-500');
                        el.classList.remove('text-red-500');
                    }
                    isPasswordValid = false;
                }
            });

            // Validar coincidencia
            let isMatchValid = false;
            if (confirm.length > 0) {
                if (pass !== confirm) {
                    matchError.classList.remove('hidden');
                    confirmInput.classList.add('border-red-500');
                } else {
                    matchError.classList.add('hidden');
                    confirmInput.classList.remove('border-red-500');
                    isMatchValid = true;
                }
            } else {
                matchError.classList.add('hidden');
                confirmInput.classList.remove('border-red-500');
            }

            // Habilitar botón solo si todo es válido
            if (isPasswordValid && isMatchValid) {
                submitBtn.removeAttribute('disabled');
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitBtn.setAttribute('disabled', 'true');
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        passwordInput.addEventListener('input', validatePassword);
        confirmInput.addEventListener('input', validatePassword);

        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', function() {
                const targetId = this.getAttribute('data-target');
                const input = document.getElementById(targetId);
                
                if (input.type === 'password') {
                    input.type = 'text';
                    this.innerHTML = '<svg class="w-5 h-5 text-[#c84661]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path></svg>';
                } else {
                    input.type = 'password';
                    this.innerHTML = '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>';
                }
            });
        });
    </script>
</body>
</html>
