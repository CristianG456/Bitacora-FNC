<?php $__env->startSection('title', 'Dashboard - Sistema Jurídico'); ?>

<?php $__env->startPush('styles'); ?>
    <link rel="stylesheet" href="<?php echo e(asset('css/dashboard.css')); ?>">
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>


<div class="page-header">
    <?php if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
        <h1>Dashboard</h1>
        <p>Vista general de casos jurídicos</p>
    <?php else: ?>
        <h1>Mis Casos Asignados</h1>
        <p>Lista de casos que requieren tu atención</p>
    <?php endif; ?>
</div>


<?php if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
<div class="dashboard-stats-grid">

    
    <div class="stat-card">
        <div>
            <p class="stat-label">Total Casos</p>
            <h2 class="stat-value total"><?php echo e($totalCasos); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper">
            <i data-lucide="folder" class="stat-icon-svg total"></i>
        </div>
    </div>

    
    <div class="stat-card">
        <div>
            <p class="stat-label">En Proceso</p>
            <h2 class="stat-value proceso"><?php echo e($enProceso); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper proceso">
            <i data-lucide="clock" class="stat-icon-svg proceso"></i>
        </div>
    </div>

    
    <div class="stat-card">
        <div>
            <p class="stat-label">Completados</p>
            <h2 class="stat-value completado"><?php echo e($completados); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper completado">
            <i data-lucide="check-circle" class="stat-icon-svg completado"></i>
        </div>
    </div>

    
    <div class="stat-card">
        <div>
            <p class="stat-label">Finalizados</p>
            <h2 class="stat-value finalizado"><?php echo e($finalizados); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper finalizado">
            <i data-lucide="flag" class="stat-icon-svg finalizado"></i>
        </div>
    </div>

</div>
<?php else: ?>
<div class="dashboard-stats-grid user-stats">

    
    <div class="stat-card">
        <div>
            <p class="stat-label">Total Asignados</p>
            <h2 class="stat-value total"><?php echo e($totalCasos); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#6b7280; padding:0;">
            <i data-lucide="folder" style="width:20px; height:20px;"></i>
        </div>
    </div>

    
    <div class="stat-card">
        <div>
            <p class="stat-label">Pendientes</p>
            <h2 class="stat-value"><?php echo e($pendientes); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#6b7280; padding:0;">
            <i data-lucide="clock" style="width:20px; height:20px;"></i>
        </div>
    </div>

    
    <div class="stat-card">
        <div>
            <p class="stat-label">En Proceso</p>
            <h2 class="stat-value"><?php echo e($enProceso); ?></h2>
        </div>
        <div class="stat-icon stat-icon-wrapper" style="background:transparent; color:#3b82f6; padding:0;">
            <i data-lucide="info" style="width:20px; height:20px;"></i>
        </div>
    </div>

</div>
<?php endif; ?>


<div style="display: block;">

        
    <div class="recent-cases-wrapper">

        
        <div class="recent-cases-header" style="<?php echo e(!auth()->user()->tieneAlgunRol(['Administrador', 'Juridica']) ? 'display:none;' : ''); ?>">
            <h2 class="recent-cases-title">Casos Recientes</h2>
            <?php if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
            <a href="<?php echo e(route('casos.crear')); ?>" class="btn-primary btn-create-sm">
                <i data-lucide="plus" class="btn-create-icon"></i>
                Crear Nuevo Caso
            </a>
            <?php endif; ?>
        </div>

        <?php if($casosRecientes->isNotEmpty()): ?>
            <?php if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
            <div class="table-responsive">
                <table class="tabla-casos">
                    <thead>
                        <tr>
                            <th>Radicado</th>
                            <th>Tipo</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $__currentLoopData = $casosRecientes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $caso): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr>
                            <td>
                                <span class="radicado-link"><?php echo e($caso->radicado); ?></span>
                            </td>
                            <td>
                                <span class="tipo-link"><?php echo e($caso->tipo?->nombre ?? '—'); ?></span>
                            </td>
                            <td class="td-desc">
                                <span class="desc-truncate">
                                    <?php echo e($caso->descripcion); ?>

                                </span>
                            </td>
                            <td>
                                <?php
                                    $badgeClass = match($caso->estado) {
                                        'En proceso'  => 'badge-proceso',
                                        'Completado'  => 'badge-completado',
                                        'Finalizado'  => 'badge-finalizado',
                                        default       => 'badge-pendiente',
                                    };
                                ?>
                                <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($caso->estado); ?></span>
                            </td>
                            <td class="td-date">
                                <?php echo e($caso->created_at->format('d/m/Y')); ?>

                            </td>
                            <td>
                                <a href="<?php echo e(route('casos.show', $caso->id)); ?>" class="btn-ver">Ver</a>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="user-cases-list">
                <?php $__currentLoopData = $casosRecientes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $caso): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <?php
                    $badgeClass = match($caso->estado) {
                        'En proceso'  => 'badge-proceso',
                        'Completado'  => 'badge-completado',
                        'Finalizado'  => 'badge-finalizado',
                        default       => 'badge-pendiente',
                    };
                ?>
                <div class="user-case-card">
                    <div class="case-card-header">
                        <div class="case-card-title">
                            <strong><?php echo e($caso->radicado); ?></strong>
                            <span class="badge <?php echo e($badgeClass); ?>"><?php echo e($caso->estado); ?></span>
                        </div>
                        <a href="<?php echo e(route('casos.show', $caso->id)); ?>" class="btn-ver">Ver Caso</a>
                    </div>
                    <div class="case-card-body">
                        <p class="case-description"><?php echo e($caso->descripcion); ?></p>
                        <div class="case-meta">
                            <span class="meta-item">Tipo: <?php echo e($caso->tipo?->nombre ?? '—'); ?></span>
                            <span class="meta-separator">•</span>
                            <span class="meta-item">Asignado: <?php echo e($caso->pivot?->fecha_asignacion ? \Carbon\Carbon::parse($caso->pivot->fecha_asignacion)->format('j/n/Y') : $caso->created_at->format('j/n/Y')); ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div class="empty-state">
            <i data-lucide="folder-open" class="empty-state-icon"></i>
            <p class="empty-state-text">No hay casos registrados aún.</p>
            <?php if(auth()->user()->tieneAlgunRol(['Administrador', 'Juridica'])): ?>
            <a href="<?php echo e(route('casos.crear')); ?>" class="btn-primary empty-state-btn">
                Crear el primer caso
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div>



</div>

<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/dashboard.blade.php ENDPATH**/ ?>