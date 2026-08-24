<?php $__env->startSection('title', 'Todos los Casos - Sistema Jurídico'); ?>

<?php $__env->startSection('content'); ?>

<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between">
        <div class="mb-3 sm:mb-0">
            <h1 class="text-2xl font-bold text-gray-900">Todos los Casos</h1>
            <p class="text-gray-500 text-sm mt-1">Gestiona y revisa todos los casos jurídicos</p>
        </div>
        <?php if($esAdmin): ?>
        <a href="<?php echo e(route('casos.crear')); ?>" class="btn-primary w-full sm:w-auto justify-center">
            <i data-lucide="plus-circle" class="w-4 h-4"></i>
            Crear Nuevo Caso
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- FILTROS Y BÚSQUEDA -->
<div class="bg-white p-4 rounded-lg border border-gray-200 mb-6 flex items-center gap-4">
    <form action="<?php echo e(route('casos.index')); ?>" method="GET" class="flex-1 flex flex-col sm:flex-row gap-4">
        
        <div class="relative flex-1">
            <i data-lucide="search" class="absolute left-3 top-2.5 text-gray-400 w-[18px] h-[18px]"></i>
            <input type="text" name="search" value="<?php echo e(request('search')); ?>" 
                   placeholder="Buscar por radicado, descripción, nombre o documento..."
                   class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
        </div>
        
        <select name="estado" class="w-full sm:w-48 px-3 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition" onchange="this.form.submit()">
            <option value="Todos" <?php echo e(request('estado') === 'Todos' ? 'selected' : ''); ?>>Todos</option>
            <option value="Pendiente" <?php echo e(request('estado') === 'Pendiente' ? 'selected' : ''); ?>>Pendiente</option>
            <option value="En proceso" <?php echo e(request('estado') === 'En proceso' ? 'selected' : ''); ?>>En Proceso</option>
            <option value="Completado" <?php echo e(request('estado') === 'Completado' ? 'selected' : ''); ?>>Completado</option>
            <option value="Finalizado" <?php echo e(request('estado') === 'Finalizado' ? 'selected' : ''); ?>>Finalizado</option>
        </select>
        
    </form>
</div>

<!-- LISTA DE CASOS -->
<div class="space-y-4">
    <?php $__empty_1 = true; $__currentLoopData = $casos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $caso): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <div class="bg-white border border-gray-200 rounded-lg p-5 flex flex-col md:flex-row md:items-center justify-between gap-4 transition hover:border-gray-300 hover:shadow-sm">
        
        <div class="flex-1">
            <div class="flex items-center gap-3 mb-2">
                <h3 class="text-base font-bold text-gray-900"><?php echo e($caso->radicado); ?></h3>
                
                <?php
                    $badgeClass = match($caso->estado) {
                        'En proceso'  => 'bg-blue-100 text-blue-700',
                        'Completado'  => 'bg-green-100 text-green-700',
                        'Finalizado'  => 'bg-red-100 text-red-700',
                        default       => 'bg-yellow-100 text-yellow-800',
                    };
                ?>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold tracking-wide <?php echo e($badgeClass); ?>">
                    <?php echo e($caso->estado); ?>

                </span>
            </div>
            
            <p class="text-sm text-gray-600 mb-3 max-w-4xl truncate">
                <?php echo e($caso->descripcion); ?>

            </p>
            
            <div class="flex flex-wrap items-center gap-2 sm:gap-4 text-xs text-gray-500 font-medium">
                <span>Tipo: <span class="text-gray-700"><?php echo e($caso->tipo?->nombre ?? 'N/A'); ?></span></span>
                <span class="text-gray-300 hidden sm:inline">•</span>
                <span>Creado: <span class="text-gray-700"><?php echo e($caso->created_at->format('d/m/Y')); ?></span></span>
                <span class="text-gray-300 hidden sm:inline">•</span>
                <span><span class="text-gray-700"><?php echo e($caso->usuarios->count()); ?></span> usuario(s)</span>
            </div>
        </div>
        
        <div class="flex-shrink-0 w-full md:w-auto mt-2 md:mt-0">
            <a href="<?php echo e(route('casos.show', $caso->id)); ?>" class="btn-secondary w-full justify-center">
                Ver Detalles
            </a>
        </div>
        
    </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <div class="bg-white border border-gray-200 rounded-lg p-12 text-center text-gray-500">
        <i data-lucide="folder-open" class="mx-auto mb-3 text-gray-300 w-10 h-10"></i>
        <p class="text-sm">No se encontraron casos que coincidan con la búsqueda.</p>
    </div>
    <?php endif; ?>
</div>

<div class="mt-6">
    <?php echo e($casos->links()); ?>

</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/casos/index.blade.php ENDPATH**/ ?>