<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'Sistema de Gestión de Casos Jurídicos'); ?></title>
    <meta name="description" content="Sistema de Gestión de Casos Jurídicos - Federación Nacional de Cafeteros">
    
    <link rel="icon" href="<?php echo e(asset('imagenes/federacion cafeteros logo.png')); ?>" type="image/png">

    
    <script src="https://cdn.tailwindcss.com"></script>

    
    <script src="https://unpkg.com/lucide@latest"></script>

    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo e(asset('css/app.css')); ?>">
    
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <?php echo $__env->yieldPushContent('styles'); ?>
</head>

<body>


<aside class="sidebar transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out z-40 fixed">

    
    <div class="sidebar-logo">
        <img src="<?php echo e(asset('imagenes/federacion cafeteros logo.png')); ?>"
             alt="Federación Nacional de Cafeteros"
             onerror="this.style.display='none'">
        <span class="org-name">Federación Nacional<br>de Cafeteros</span>
    </div>

    
    <nav class="sidebar-nav">

        <span class="nav-section-title">Principal</span>

        <a href="<?php echo e(route('dashboard')); ?>"
           class="nav-item <?php echo e(request()->routeIs('dashboard') ? 'active' : ''); ?>">
            <i data-lucide="layout-dashboard" style="width:18px;height:18px;"></i>
            Dashboard
        </a>

        <a href="<?php echo e(route('casos.index')); ?>" class="nav-item <?php echo e(request()->routeIs('casos.*') && !request()->routeIs('casos.crear') ? 'active' : ''); ?>">
            <i data-lucide="folder-open" style="width:18px;height:18px;"></i>
            Casos
        </a>

        <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('create', App\Models\Caso::class)): ?>
        <?php endif; ?>
        <?php if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
        <a href="<?php echo e(route('casos.crear')); ?>"
           class="nav-item <?php echo e(request()->routeIs('casos.crear') ? 'active' : ''); ?>">
            <i data-lucide="plus-circle" style="width:18px;height:18px;"></i>
            Crear Caso
        </a>
        <?php endif; ?>

        <?php if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor'])): ?>
        <span class="nav-section-title">Gestión</span>
        <?php endif; ?>

        <?php if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
        <a href="<?php echo e(route('tipos.index')); ?>" class="nav-item <?php echo e(request()->routeIs('tipos.*') ? 'active' : ''); ?>">
            <i data-lucide="file-text" style="width:18px;height:18px;"></i>
            Tipos de Documento
        </a>
        <?php endif; ?>

        <?php if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
        <a href="<?php echo e(route('usuarios.index')); ?>" class="nav-item <?php echo e(request()->routeIs('usuarios.*') ? 'active' : ''); ?>">
            <i data-lucide="users" style="width:18px;height:18px;"></i>
            Usuarios
        </a>
        <?php endif; ?>

        <?php if(auth()->user()?->tieneAlgunRol(['Administrador', 'Juridica', 'Consultor'])): ?>
        <a href="<?php echo e(route('historial.index')); ?>" class="nav-item <?php echo e(request()->routeIs('historial.*') ? 'active' : ''); ?>">
            <i data-lucide="clock" style="width:18px;height:18px;"></i>
            Historial Global
        </a>
        <?php endif; ?>

        <?php if(auth()->user()?->tieneAlgunRol(['Administrador'])): ?>
        <a href="<?php echo e(route('respaldos.index')); ?>" class="nav-item <?php echo e(request()->routeIs('respaldos.*') ? 'active' : ''); ?>">
            <i data-lucide="database" style="width:18px;height:18px;"></i>
            Respaldos
        </a>
        <?php endif; ?>

    </nav>

</aside>


<div class="main-wrapper">

    
    <div id="sidebar-overlay" class="hidden fixed inset-0 bg-black bg-opacity-50 z-30 md:hidden" onclick="toggleSidebar()"></div>

    
    <header class="top-header flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button onclick="toggleSidebar()" class="md:hidden text-gray-600 hover:text-gray-900 focus:outline-none">
                <i data-lucide="menu" style="width:24px;height:24px;"></i>
            </button>
            <span class="header-title hidden sm:block">Sistema de Gestión de Casos Jurídicos</span>
            <span class="header-title sm:hidden text-sm">SGCJ</span>
        </div>

        <div class="header-right">

            
            <div class="relative" id="notif-container">
                <div class="notif-bell cursor-pointer" title="Notificaciones" id="notif-btn" onclick="toggleNotificaciones()">
                    <i data-lucide="bell" style="width:20px;height:20px;"></i>
                    <?php $sinLeer = auth()->user()?->notificacionesSinLeer() ?? 0; ?>
                    <?php if($sinLeer > 0): ?>
                        <span class="notif-badge" id="notif-badge-count"><?php echo e($sinLeer > 9 ? '9+' : $sinLeer); ?></span>
                    <?php endif; ?>
                </div>

                <!-- Menú desplegable -->
                <div id="notif-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                        <h3 class="font-bold text-sm text-gray-800">Notificaciones</h3>
                        <?php if($sinLeer > 0): ?>
                        <form action="<?php echo e(route('notificaciones.marcar_leidas')); ?>" method="POST">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Marcar leídas</button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <div class="max-h-[300px] overflow-y-auto" id="notif-list">
                        <!-- Las notificaciones se cargan por JS -->
                        <div class="p-4 text-center text-sm text-gray-500">Cargando...</div>
                    </div>
                </div>
            </div>

            
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo e(strtoupper(substr(auth()->user()?->name ?? 'U', 0, 1))); ?>

                </div>
                <div>
                    <div class="user-name-label"><?php echo e(auth()->user()?->name ?? 'Usuario'); ?></div>
                    <div class="user-role-label"><?php echo e(auth()->user()?->role?->nombre ?? 'Sin rol'); ?></div>
                </div>
            </div>

            
            <form method="POST" action="<?php echo e(route('logout')); ?>">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn-logout" title="Cerrar sesión">
                    <i data-lucide="log-out" style="width:16px;height:16px;"></i>
                </button>
            </form>

        </div>
    </header>

    

    
    <main class="page-content">
        <?php echo $__env->yieldContent('content'); ?>
    </main>

</div>

<script>
    lucide.createIcons();

    function toggleSidebar() {
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebar-overlay');
        
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
        
        if (overlay) {
            overlay.classList.toggle('hidden');
        }
    }

    function toggleNotificaciones() {
        const dropdown = document.getElementById('notif-dropdown');
        dropdown.classList.toggle('hidden');
        
        if (!dropdown.classList.contains('hidden')) {
            cargarNotificaciones();
        }
    }

    function cargarNotificaciones() {
        fetch('<?php echo e(route("notificaciones.recientes")); ?>')
            .then(response => response.json())
            .then(data => {
                const list = document.getElementById('notif-list');
                list.innerHTML = '';
                
                if (data.length === 0) {
                    list.innerHTML = '<div class="p-4 text-center text-sm text-gray-500">No tienes notificaciones.</div>';
                    return;
                }

                data.forEach(n => {
                    const bg = n.leido ? 'bg-white' : 'bg-blue-50';
                    const icon = n.tipo === 'success' ? '<i data-lucide="check-circle" class="w-4 h-4 text-green-500"></i>' : '<i data-lucide="info" class="w-4 h-4 text-blue-500"></i>';
                    
                    list.innerHTML += `
                        <div class="p-3 border-b border-gray-50 flex gap-3 hover:bg-gray-50 transition ${bg}">
                            <div class="mt-0.5 shrink-0">${icon}</div>
                            <div>
                                <h4 class="text-xs font-bold text-gray-800 mb-0.5">${n.titulo}</h4>
                                <p class="text-xs text-gray-600 leading-snug">${n.mensaje}</p>
                                <span class="text-[10px] text-gray-400 mt-1 block">${n.fecha}</span>
                            </div>
                        </div>
                    `;
                });
                lucide.createIcons();
            });
    }

    // Cerrar al hacer clic fuera
    document.addEventListener('click', function(event) {
        const container = document.getElementById('notif-container');
        const dropdown = document.getElementById('notif-dropdown');
        if (container && dropdown && !container.contains(event.target)) {
            dropdown.classList.add('hidden');
        }
    });

    // ─── SWEETALERT 2 PARA CONFIRMACIONES Y ALERTAS FLASH ───
    
    // Función global para confirmaciones de formularios
    function confirmarAccion(event, form, titulo, texto) {
        event.preventDefault();
        Swal.fire({
            title: titulo,
            text: texto || 'Esta acción podría afectar los registros.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#b11226',
            cancelButtonColor: '#6b7280',
            confirmButtonText: 'Aceptar',
            cancelButtonText: 'Cancelar',
            customClass: {
                popup: 'rounded-xl',
                confirmButton: 'rounded-lg px-4 py-2 font-semibold',
                cancelButton: 'rounded-lg px-4 py-2 font-semibold'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    // Configuración de Toasts para Alertas Flash
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        customClass: {
            popup: 'rounded-xl shadow-lg border border-gray-100',
        },
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer)
            toast.addEventListener('mouseleave', Swal.resumeTimer)
        }
    });

    <?php if(session('success')): ?>
        Toast.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: '<?php echo e(session("success")); ?>'
        });
    <?php endif; ?>

    <?php if(session('error')): ?>
        Toast.fire({
            icon: 'error',
            title: 'Atención',
            text: '<?php echo e(session("error")); ?>'
        });
    <?php endif; ?>

    <?php if($errors->any()): ?>
        Toast.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo e($errors->first()); ?>'
        });
    <?php endif; ?>
</script>

<?php echo $__env->yieldPushContent('scripts'); ?>

</body>
</html><?php /**PATH /var/www/resources/views/layouts/app.blade.php ENDPATH**/ ?>