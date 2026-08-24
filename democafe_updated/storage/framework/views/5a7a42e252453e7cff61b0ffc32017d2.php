<?php $__env->startSection('content'); ?>

<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200">
    <div class="flex items-center gap-2 mb-3">
        <a href="<?php echo e(route('dashboard')); ?>" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
    </div>
    <h1 class="text-2xl font-bold text-gray-900">Crear Nuevo Caso</h1>
    <p class="text-gray-500 text-sm mt-1">Completa la información del caso jurídico</p>
</div>

<form method="POST" action="<?php echo e(route('casos.guardar')); ?>" class="space-y-6">
    <?php echo csrf_field(); ?>

    <?php if($errors->any()): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded-md">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-sm font-medium text-red-800">
                        Por favor, corrige los siguientes errores:
                    </h3>
                    <ul class="mt-2 text-sm text-red-700 list-disc list-inside">
                        <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <li><?php echo e($error); ?></li>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </ul>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- INFORMACIÓN DEL CASO -->
    <div class="bg-white px-4 sm:px-8 py-6 sm:py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Información del Caso
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Tipo de Caso</label>
                <select name="tipo_proceso_id" id="tipo_proceso_id" required
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm">
                    <option value="">Selecciona un tipo</option>
                    <?php $__currentLoopData = $tipos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tipo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($tipo->id); ?>"><?php echo e($tipo->nombre); ?></option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
    <div class="bg-white px-4 sm:px-8 py-6 sm:py-8 rounded-lg shadow-sm border border-gray-200">

        <h2 class="text-base font-bold text-gray-900 mb-6 uppercase tracking-wide">
            Datos del Solicitante
        </h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Nombre del Solicitante</label>
                <input type="text" name="nombre_solicitante" required
                    placeholder="Nombre completo"
                    pattern="[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+" title="Solo se permiten letras y espacios"
                    oninput="this.value = this.value.replace(/[^a-zA-Z\sñÑáéíóúÁÉÍÓÚ]/g, '')"
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-3">Documento del Solicitante</label>
                <input type="text" name="documento_solicitante" required
                    placeholder="Número de documento"
                    pattern="[0-9]+" title="Solo se permiten números"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="w-full px-4 py-3 border border-gray-300 rounded-md bg-gray-50 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:bg-white focus:border-gray-300 transition">
            </div>

        </div>
    </div>

    <!-- DOCUMENTO -->
    <div class="bg-white px-4 sm:px-8 py-6 sm:py-8 rounded-lg shadow-sm border border-gray-200">

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
    <div class="bg-white px-4 sm:px-8 py-6 sm:py-8 rounded-lg shadow-sm border border-gray-200">

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

        <a href="<?php echo e(route('dashboard')); ?>"
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
const tipos = <?php echo json_encode($tipos, 15, 512) ?>;

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

// Búsqueda de Usuarios
const searchInput = document.getElementById('buscar_usuario');
const container = document.getElementById('usuarios-container');
const asignadosContainer = document.getElementById('usuarios-asignados');
let searchTimeout;

// Crear dropdown de resultados
const resultsDropdown = document.createElement('div');
resultsDropdown.className = 'absolute z-10 w-full mt-1 bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-y-auto';
searchInput.parentNode.appendChild(resultsDropdown);

function fetchUsers(query) {
    fetch(`/usuarios/buscar?q=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            resultsDropdown.innerHTML = '';
            if (data.length > 0) {
                data.forEach(user => {
                    const item = document.createElement('div');
                    item.className = 'px-4 py-2 cursor-pointer hover:bg-gray-100 text-sm text-gray-800';
                    item.textContent = `${user.name} (${user.email})`;
                    item.onclick = () => addUser(user);
                    resultsDropdown.appendChild(item);
                });
                resultsDropdown.classList.remove('hidden');
            } else {
                resultsDropdown.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">No se encontraron usuarios</div>';
                resultsDropdown.classList.remove('hidden');
            }
        });
}

searchInput.addEventListener('click', function() {
    if (resultsDropdown.classList.contains('hidden')) {
        fetchUsers(this.value);
    }
});

searchInput.addEventListener('focus', function() {
    if (resultsDropdown.classList.contains('hidden')) {
        fetchUsers(this.value);
    }
});

searchInput.addEventListener('input', function() {
    clearTimeout(searchTimeout);
    const query = this.value;

    searchTimeout = setTimeout(() => {
        fetchUsers(query);
    }, 300);
});

// Ocultar dropdown al hacer click fuera
document.addEventListener('click', function(e) {
    if (e.target !== searchInput && e.target !== resultsDropdown) {
        resultsDropdown.classList.add('hidden');
    }
});

const addedUsers = new Set();

function addUser(user) {
    if (addedUsers.has(user.id)) {
        alert('Este usuario ya está asignado.');
        return;
    }
    
    addedUsers.add(user.id);
    searchInput.value = '';
    resultsDropdown.classList.add('hidden');
    
    // Ocultar mensaje de vacío
    container.classList.add('hidden');
    
    const userBlock = document.createElement('div');
    userBlock.className = 'bg-gray-50 border border-gray-200 rounded-lg p-5 mb-4 relative';
    userBlock.id = `user-block-${user.id}`;
    
    userBlock.innerHTML = `
        <input type="hidden" name="usuarios[]" value="${user.id}">
        <div class="flex justify-between items-start mb-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900">${user.name}</h3>
                <p class="text-xs text-gray-500">${user.email}</p>
            </div>
            <button type="button" onclick="removeUser(${user.id})" class="text-red-500 hover:text-red-700 text-xs font-semibold transition">
                Eliminar
            </button>
        </div>
        
        <div class="border-t border-gray-200 pt-4 mt-2">
            <div class="flex justify-between items-center mb-2">
                <label class="block text-xs font-semibold text-gray-700">Tareas para este usuario</label>
                <button type="button" onclick="addTask(${user.id})" class="text-xs text-blue-600 hover:text-blue-800 font-semibold transition">+ Agregar Tarea</button>
            </div>
            <div id="tasks-container-${user.id}" class="space-y-3">
                <div class="flex gap-2 items-start task-item">
                    <textarea name="tareas[${user.id}][]" rows="1" required placeholder="Describe la tarea..." class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 transition resize-none"></textarea>
                    <button type="button" onclick="this.parentElement.remove()" class="mt-2 text-gray-400 hover:text-red-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
            </div>
        </div>
    `;
    
    asignadosContainer.appendChild(userBlock);
}

function removeUser(userId) {
    addedUsers.delete(userId);
    document.getElementById(`user-block-${userId}`).remove();
    
    if (addedUsers.size === 0) {
        container.classList.remove('hidden');
    }
}

function addTask(userId) {
    const tasksContainer = document.getElementById(`tasks-container-${userId}`);
    const taskDiv = document.createElement('div');
    taskDiv.className = 'flex gap-2 items-start task-item';
    taskDiv.innerHTML = `
        <textarea name="tareas[${userId}][]" rows="1" required placeholder="Describe la tarea..." class="w-full px-3 py-2 border border-gray-300 rounded-md bg-white text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:border-blue-500 transition resize-none"></textarea>
        <button type="button" onclick="this.parentElement.remove()" class="mt-2 text-gray-400 hover:text-red-500">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    `;
    tasksContainer.appendChild(taskDiv);
}
</script>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/casos/crear.blade.php ENDPATH**/ ?>