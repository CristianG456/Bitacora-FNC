<?php $__env->startSection('title', 'Bitácora - ' . $caso->radicado); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/historial.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>


<!-- HEADER -->
<div class="mb-6 -mx-4 sm:-mx-6 -mt-6 px-4 sm:px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex flex-wrap items-center gap-3 mb-2">
        <a href="<?php echo e(route('historial.index')); ?>" class="text-gray-400 hover:text-gray-700 transition shrink-0">
            <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
        </a>
        <div class="flex-1 min-w-0">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-900 truncate">Bitácora: <?php echo e($caso->radicado); ?></h1>
            <p class="text-sm text-gray-500"><?php echo e($caso->tipo?->nombre); ?> • <?php echo e($caso->subtipo?->nombre); ?></p>
        </div>
        <div class="flex gap-2 shrink-0 w-full sm:w-auto mt-2 sm:mt-0">
            <a href="<?php echo e(route('historial.exportar.pdf', ['caso_id' => $caso->id])); ?>" target="_blank" class="btn-secondary flex-1 sm:flex-initial justify-center text-xs sm:text-sm" style="color: #b11226; border-color: #fca5a5;">
                <i data-lucide="file-down" style="width:14px;height:14px;"></i>
                PDF
            </a>
            <a href="<?php echo e(route('historial.exportar.excel', ['caso_id' => $caso->id])); ?>" class="btn-secondary flex-1 sm:flex-initial justify-center text-xs sm:text-sm" style="color: #16a34a; border-color: #86efac;">
                <i data-lucide="sheet" style="width:14px;height:14px;"></i>
                Excel
            </a>
        </div>
    </div>
</div>

<!-- INFO DEL CASO -->
<div class="caso-info-card">
    <div class="caso-info-grid">
        <div>
            <div class="info-item-label">Solicitante</div>
            <div class="info-item-value"><?php echo e($caso->solicitante?->nombre ?? 'N/A'); ?></div>
        </div>
        <div>
            <div class="info-item-label">Documento</div>
            <div class="info-item-value"><?php echo e($caso->solicitante?->documento ?? 'N/A'); ?></div>
        </div>
        <div>
            <div class="info-item-label">Usuarios Asignados</div>
            <div class="info-item-value"><?php echo e($caso->usuarios->count()); ?></div>
        </div>
        <div>
            <div class="info-item-label">Total Eventos</div>
            <div class="info-item-value"><?php echo e($totalEventos); ?></div>
        </div>
        <div>
            <div class="info-item-label">Fecha Creación</div>
            <div class="info-item-value"><?php echo e($caso->created_at->format('d/m/Y')); ?></div>
        </div>
        <div>
            <div class="info-item-label">Fecha Finalización</div>
            <div class="info-item-value"><?php echo e($caso->updated_at->format('d/m/Y')); ?></div>
        </div>
    </div>
</div>

<!-- FILTROS -->
<div class="filter-card">
    <form method="GET" action="<?php echo e(route('historial.show', $caso->id)); ?>" class="flex flex-col sm:flex-row gap-4">
        <select name="evento" class="form-select w-full sm:w-48" onchange="this.form.submit()">
            <option value="">Todos los eventos</option>
            <?php $__currentLoopData = $acciones; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $accion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($accion); ?>" <?php echo e(request('evento') == $accion ? 'selected' : ''); ?>>
                    <?php echo e($accion); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <select name="usuario_id" class="form-select w-full sm:w-48" onchange="this.form.submit()">
            <option value="">Todos los usuarios</option>
            <?php $__currentLoopData = $usuarios; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $user): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <option value="<?php echo e($user->id); ?>" <?php echo e(request('usuario_id') == $user->id ? 'selected' : ''); ?>>
                    <?php echo e($user->name); ?>

                </option>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </select>

        <?php if(request()->hasAny(['evento', 'usuario_id'])): ?>
            <a href="<?php echo e(route('historial.show', $caso->id)); ?>" class="btn-secondary justify-center shrink-0">
                <i data-lucide="x" style="width:14px;height:14px;"></i>
                Limpiar
            </a>
        <?php endif; ?>
    </form>
</div>

<!-- CONTADOR -->
<div class="text-sm text-gray-500 mb-2">
    Mostrando <?php echo e($eventos->count()); ?> de <?php echo e($eventos->total()); ?> eventos
</div>

<!-- LÍNEA DE TIEMPO -->
<?php if($eventos->isEmpty()): ?>
    <div class="bg-white rounded-xl border border-gray-200 p-12 text-center mt-4">
        <i data-lucide="inbox" class="mx-auto mb-3 text-gray-300" style="width:48px;height:48px;"></i>
        <h3 class="text-base font-semibold text-gray-600">No hay eventos para mostrar</h3>
        <p class="text-sm text-gray-400 mt-2">Intenta ajustar los filtros de búsqueda.</p>
    </div>
<?php else: ?>
    <div class="timeline">
        <?php $__currentLoopData = $eventos; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $evento): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <?php
                $claseEvento = 'evento-defecto';
                $icono = 'circle';
                $badgeClass = 'bg-gray-badge';

                $accionStr = strtolower($evento->accion);
                $moduloStr = strtolower($evento->modulo ?? '');

                if (str_contains($accionStr, 'crear') || str_contains($accionStr, 'nuevo')) {
                    $claseEvento = 'evento-creado';
                    $icono = 'file-plus';
                    $badgeClass = 'bg-green-badge';
                } elseif (str_contains($accionStr, 'asignar') || str_contains($accionStr, 'reemplazar')) {
                    $claseEvento = 'evento-asignacion';
                    $icono = 'user-plus';
                    $badgeClass = 'bg-blue-badge';
                } elseif ($moduloStr === 'tareas' || str_contains($accionStr, 'tarea')) {
                    $claseEvento = 'evento-tarea';
                    $icono = 'check-square';
                    $badgeClass = 'bg-yellow-badge';
                } elseif ($moduloStr === 'chat' || $moduloStr === 'mensajes') {
                    $claseEvento = 'evento-mensaje';
                    $icono = 'message-square';
                    $badgeClass = 'bg-purple-badge';
                } elseif (str_contains($accionStr, 'actualizar') || str_contains($accionStr, 'estado') || str_contains($accionStr, 'finaliz')) {
                    $claseEvento = 'evento-defecto';
                    $icono = 'edit-3';
                    $badgeClass = 'bg-gray-badge';
                }
            ?>

            <div class="timeline-item <?php echo e($claseEvento); ?>">
                <div class="timeline-icon">
                    <i data-lucide="<?php echo e($icono); ?>" style="width: 14px; height: 14px;"></i>
                </div>
                <div class="timeline-content">
                    <div class="event-header">
                        <div>
                            <span class="badge-event <?php echo e($badgeClass); ?>"><?php echo e($evento->accion); ?></span>
                        </div>
                        <div class="event-time">
                            <?php echo e($evento->created_at->format('d/m/Y - H:i')); ?>

                        </div>
                    </div>
                    
                    <div class="event-desc text-sm mt-1">
                        <?php echo e($evento->descripcion); ?>

                        
                        <?php if(!empty($evento->metadata) && isset($evento->metadata['observacion'])): ?>
                            <div class="mt-2 p-2.5 bg-yellow-50/50 border border-yellow-100 rounded text-gray-700 italic text-xs">
                                <span class="font-semibold text-gray-900 not-italic block mb-0.5"><i data-lucide="message-square" style="width:12px;height:12px;display:inline;margin-top:-2px;"></i> Observación:</span> 
                                <?php echo e($evento->metadata['observacion']); ?>

                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="event-meta">
                        <div style="display: flex; align-items: center;">
                            <i data-lucide="user"></i>
                            <?php echo e($evento->usuario ? $evento->usuario->name : 'Sistema'); ?>

                        </div>
                        
                        <?php if($evento->usuario && $evento->usuario->role): ?>
                            <div style="display: flex; align-items: center;">
                                <i data-lucide="shield"></i>
                                <?php echo e($evento->usuario->role->nombre); ?>

                            </div>
                        <?php endif; ?>

                        <?php if($evento->modulo): ?>
                            <div style="display: flex; align-items: center;">
                                <i data-lucide="box"></i>
                                <?php echo e($evento->modulo); ?>

                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <div style="margin-top: 24px;">
        <?php echo e($eventos->links()); ?>

    </div>
<?php endif; ?>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/historial/show.blade.php ENDPATH**/ ?>