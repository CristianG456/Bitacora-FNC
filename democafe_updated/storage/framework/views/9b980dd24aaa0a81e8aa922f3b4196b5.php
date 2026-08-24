<?php $__env->startSection('title', 'Tipos de Documento'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/tipos.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>
<div class="page-header flex flex-col sm:flex-row sm:justify-between sm:items-center gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Tipos de Documento</h1>
        <p class="text-sm text-gray-500">Gestiona los tipos y subtipos de documentos del sistema</p>
    </div>
    <button onclick="document.getElementById('modal-crear-tipo').classList.remove('hidden')" class="btn-primary flex items-center justify-center gap-2 bg-[#b11226] text-white px-4 py-2 rounded-lg hover:bg-[#8e0e1f] transition w-full sm:w-auto">
        <i data-lucide="plus" class="w-4 h-4"></i>
        Crear Tipo
    </button>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="tabla-tipos">
            <thead>
                <tr>
                    <th>Tipo</th>
                    <th>Código</th>
                    <th>Subtipos</th>
                    <th>Estado</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $__empty_1 = true; $__currentLoopData = $tipos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tipo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td>
                        <div class="flex items-start gap-3">
                            <i data-lucide="file-text" class="w-5 h-5 text-[#b11226] mt-0.5 shrink-0"></i>
                            <div>
                                <h3 class="font-bold text-sm text-gray-900"><?php echo e($tipo->nombre); ?></h3>
                                <?php if($tipo->descripcion): ?>
                                <p class="text-[12px] text-gray-500 mt-0.5"><?php echo e($tipo->descripcion); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="codigo-box"><?php echo e($tipo->codigo); ?></span>
                    </td>
                    <td>
                        <div class="flex flex-wrap gap-1.5">
                            <?php $__empty_2 = true; $__currentLoopData = $tipo->subtipos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $sub): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_2 = false; ?>
                                <span class="codigo-box" title="<?php echo e($sub->nombre); ?>"><?php echo e($sub->codigo); ?></span>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_2): ?>
                                <span class="text-xs text-gray-400 italic">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <span class="badge-estado <?php echo e($tipo->activo ? 'activo' : 'inactivo'); ?>">
                            <?php echo e($tipo->activo ? 'activo' : 'inactivo'); ?>

                        </span>
                    </td>
                    <td>
                        <div class="flex items-center justify-end gap-2">
                            <button onclick="abrirModalSubtipos(<?php echo e($tipo->id); ?>, '<?php echo e(addslashes($tipo->nombre)); ?>', '<?php echo e($tipo->codigo); ?>')" class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-600 bg-gray-50 hover:bg-gray-100 border border-gray-200 rounded-md transition">
                                <i data-lucide="eye" class="w-3.5 h-3.5"></i>
                                Ver subtipos
                            </button>
                            <button onclick="abrirModalEditarTipo(<?php echo e($tipo->id); ?>, '<?php echo e(addslashes($tipo->nombre)); ?>', '<?php echo e($tipo->codigo); ?>', '<?php echo e(addslashes($tipo->descripcion)); ?>')" class="p-1.5 text-gray-400 hover:text-blue-600 border border-transparent hover:border-blue-100 hover:bg-blue-50 rounded-md transition" title="Editar">
                                <i data-lucide="pencil" class="w-4 h-4"></i>
                            </button>
                            <form action="<?php echo e(route('tipos.estado', $tipo->id)); ?>" method="POST" class="inline" onsubmit="confirmarAccion(event, this, '¿Cambiar estado?', 'Esto afectará la disponibilidad de este tipo en nuevos casos.');">
                                <?php echo csrf_field(); ?>
                                <button type="submit" class="p-1.5 <?php echo e($tipo->activo ? 'text-gray-400 hover:text-orange-600 hover:bg-orange-50' : 'text-gray-400 hover:text-green-600 hover:bg-green-50'); ?> rounded-md transition border border-transparent" title="<?php echo e($tipo->activo ? 'Desactivar' : 'Activar'); ?>">
                                    <i data-lucide="<?php echo e($tipo->activo ? 'power-off' : 'check-circle'); ?>" class="w-4 h-4"></i>
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="5" class="text-center py-8 text-gray-500 text-sm">
                        No hay tipos de documentos registrados.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Crear Tipo -->
<div id="modal-crear-tipo" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden animate-fadeInScale">
        <div class="flex justify-between items-center p-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Crear Tipo de Documento</h2>
            <button onclick="document.getElementById('modal-crear-tipo').classList.add('hidden')" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-md transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form action="<?php echo e(route('tipos.store')); ?>" method="POST" class="p-5">
            <?php echo csrf_field(); ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Tipo</label>
                    <input type="text" name="nombre" required placeholder="Ej: Contratos" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código (2-3 letras)</label>
                    <input type="text" name="codigo" required maxlength="3" placeholder="Ej: CT" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
                    <textarea name="descripcion" rows="3" placeholder="Descripción del tipo de documento" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-crear-tipo').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg text-sm font-medium transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-[#d17581] hover:bg-[#b11226] text-white rounded-lg text-sm font-medium transition shadow-sm">
                    Crear
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Tipo -->
<div id="modal-editar-tipo" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="flex justify-between items-center p-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900">Editar Tipo de Documento</h2>
            <button onclick="document.getElementById('modal-editar-tipo').classList.add('hidden')" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-md transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="form-editar-tipo" method="POST" class="p-5">
            <?php echo csrf_field(); ?>
            <?php echo method_field('PUT'); ?>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Tipo</label>
                    <input type="text" name="nombre" id="edit-tipo-nombre" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código (2-3 letras)</label>
                    <input type="text" name="codigo" id="edit-tipo-codigo" required maxlength="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm uppercase">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Descripción (opcional)</label>
                    <textarea name="descripcion" id="edit-tipo-desc" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm resize-none"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-editar-tipo').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg text-sm font-medium transition">
                    Cancelar
                </button>
                <button type="submit" class="px-4 py-2 bg-[#d17581] hover:bg-[#b11226] text-white rounded-lg text-sm font-medium transition shadow-sm">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Ver Subtipos -->
<div id="modal-ver-subtipos" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-[calc(100%-2rem)] md:max-w-lg overflow-hidden flex flex-col max-h-[80vh]">
        <div class="flex justify-between items-center p-4 border-b border-gray-100 shrink-0">
            <h2 class="text-lg font-bold text-gray-900" id="subtipos-title">Subtipos de: </h2>
            <button onclick="document.getElementById('modal-ver-subtipos').classList.add('hidden')" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-md transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        
        <div class="p-4 border-b border-gray-100 bg-gray-50 shrink-0">
            <button onclick="abrirModalCrearSubtipo()" class="w-full py-2 bg-white border border-gray-200 shadow-sm text-gray-700 font-medium text-sm rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-2">
                <i data-lucide="plus" class="w-4 h-4"></i>
                Agregar Subtipo
            </button>
        </div>

        <div class="overflow-y-auto p-0 flex-1">
            <table class="w-full text-sm text-left">
                <thead class="text-xs text-gray-500 bg-gray-50 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 font-medium">Nombre</th>
                        <th class="px-4 py-3 font-medium text-center">Código</th>
                        <th class="px-4 py-3 font-medium text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody id="subtipos-tbody" class="divide-y divide-gray-100">
                    <!-- Rellenado por JS -->
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal Agregar Subtipo -->
<div id="modal-agregar-subtipo" class="fixed inset-0 z-50 hidden bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
        <div class="flex justify-between items-center p-4 border-b border-gray-100">
            <h2 class="text-lg font-bold text-gray-900" id="title-modal-subtipo">Agregar Subtipo</h2>
            <button onclick="document.getElementById('modal-agregar-subtipo').classList.add('hidden')" class="text-gray-400 hover:bg-gray-100 p-1.5 rounded-md transition">
                <i data-lucide="x" class="w-5 h-5"></i>
            </button>
        </div>
        <form id="form-subtipo" method="POST" class="p-5">
            <?php echo csrf_field(); ?>
            <!-- _method input added dynamically via JS if edit -->
            <div id="method-container"></div>
            
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del Subtipo</label>
                    <input type="text" name="nombre" id="input-subtipo-nombre" required placeholder="Ej: Orden de Compra" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Código (2 letras)</label>
                    <input type="text" name="codigo" id="input-subtipo-codigo" required maxlength="3" placeholder="Ej: OC" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 focus:bg-white focus:ring-2 focus:ring-[#b11226] focus:border-[#b11226] outline-none transition text-sm uppercase">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="document.getElementById('modal-agregar-subtipo').classList.add('hidden')" class="px-4 py-2 border border-gray-300 text-gray-700 bg-white hover:bg-gray-50 rounded-lg text-sm font-medium transition">
                    Cancelar
                </button>
                <button type="submit" id="btn-submit-subtipo" class="px-4 py-2 bg-[#d17581] hover:bg-[#b11226] text-white rounded-lg text-sm font-medium transition shadow-sm">
                    Agregar
                </button>
            </div>
        </form>
    </div>
</div>

<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script>
    // Variables globales para el modal de subtipos
    let currentTipoId = null;
    const tiposData = <?php echo json_encode($tipos, 15, 512) ?>;

    function abrirModalEditarTipo(id, nombre, codigo, desc) {
        document.getElementById('edit-tipo-nombre').value = nombre;
        document.getElementById('edit-tipo-codigo').value = codigo;
        document.getElementById('edit-tipo-desc').value = desc;
        document.getElementById('form-editar-tipo').action = `/tipos/${id}`;
        document.getElementById('modal-editar-tipo').classList.remove('hidden');
    }

    function abrirModalSubtipos(tipoId, nombre, codigo) {
        currentTipoId = tipoId;
        document.getElementById('subtipos-title').textContent = `Subtipos de: ${nombre} (${codigo})`;
        
        const tipoInfo = tiposData.find(t => t.id === tipoId);
        const tbody = document.getElementById('subtipos-tbody');
        tbody.innerHTML = '';

        if (tipoInfo && tipoInfo.subtipos && tipoInfo.subtipos.length > 0) {
            tipoInfo.subtipos.forEach(sub => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="px-4 py-3 text-gray-900">${sub.nombre}</td>
                    <td class="px-4 py-3 text-center"><span class="codigo-box">${sub.codigo}</span></td>
                    <td class="px-4 py-3 text-right">
                        <button onclick="editarSubtipo(${tipoId}, ${sub.id}, '${sub.nombre.replace(/'/g, "\\'")}', '${sub.codigo}')" class="p-1.5 text-gray-400 hover:text-blue-600 border border-gray-200 hover:border-blue-200 bg-white rounded-md transition inline-flex items-center justify-center">
                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="px-4 py-8 text-center text-gray-500 italic text-sm">No hay subtipos creados.</td></tr>`;
        }

        lucide.createIcons();
        document.getElementById('modal-ver-subtipos').classList.remove('hidden');
    }

    function abrirModalCrearSubtipo() {
        document.getElementById('modal-ver-subtipos').classList.add('hidden');
        
        document.getElementById('title-modal-subtipo').textContent = 'Agregar Subtipo';
        document.getElementById('btn-submit-subtipo').textContent = 'Agregar';
        document.getElementById('form-subtipo').action = `/tipos/${currentTipoId}/subtipos`;
        document.getElementById('method-container').innerHTML = '';
        
        document.getElementById('input-subtipo-nombre').value = '';
        document.getElementById('input-subtipo-codigo').value = '';
        
        document.getElementById('modal-agregar-subtipo').classList.remove('hidden');
    }

    function editarSubtipo(tipoId, subtipoId, nombre, codigo) {
        document.getElementById('modal-ver-subtipos').classList.add('hidden');
        
        document.getElementById('title-modal-subtipo').textContent = 'Editar Subtipo';
        document.getElementById('btn-submit-subtipo').textContent = 'Guardar';
        document.getElementById('form-subtipo').action = `/tipos/${tipoId}/subtipos/${subtipoId}`;
        document.getElementById('method-container').innerHTML = '<input type="hidden" name="_method" value="PUT">';
        
        document.getElementById('input-subtipo-nombre').value = nombre;
        document.getElementById('input-subtipo-codigo').value = codigo;
        
        document.getElementById('modal-agregar-subtipo').classList.remove('hidden');
    }
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/tipos/index.blade.php ENDPATH**/ ?>