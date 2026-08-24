<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Bloqueado - Sistema de Bitácoras</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="flex items-center justify-center min-h-screen p-4">

    <div class="bg-white rounded-2xl shadow-xl max-w-md w-full p-8 text-center border-t-4 border-[#b11226]">
        
        <div class="mx-auto w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
            <i data-lucide="shield-alert" class="w-8 h-8 text-[#b11226]"></i>
        </div>
        
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Pestaña Protegida</h2>
        
        <p class="text-gray-600 text-sm mb-6 leading-relaxed">
            Por motivos de seguridad, la sesión activa está anclada a la pestaña original. Para utilizar el sistema en esta nueva pestaña, por favor confirma tu identidad.
        </p>

        @if ($errors->any())
            <div class="bg-red-50 text-red-600 text-sm p-3 rounded-lg mb-4 text-left border border-red-100">
                <i data-lucide="alert-circle" class="w-4 h-4 inline-block mr-1 -mt-0.5"></i>
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('desbloquear.pestana') }}" method="POST" class="text-left space-y-4">
            @csrf
            
            <div>
                <label for="password" class="block text-sm font-semibold text-gray-700 mb-1">Contraseña</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i data-lucide="lock" class="h-5 w-5 text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg focus:ring-[#b11226] focus:border-[#b11226] text-sm outline-none transition"
                           placeholder="Ingresa tu contraseña">
                </div>
            </div>

            <button type="submit" class="w-full bg-[#b11226] hover:bg-[#8b0e1e] text-white font-semibold py-2.5 px-4 rounded-lg transition-colors flex justify-center items-center gap-2">
                <i data-lucide="unlock" class="w-4 h-4"></i>
                Desbloquear Pestaña
            </button>
        </form>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
