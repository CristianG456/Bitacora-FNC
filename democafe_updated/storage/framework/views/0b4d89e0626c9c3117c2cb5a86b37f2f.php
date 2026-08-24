<!DOCTYPE html>
<html>
<head>
    <title>Asignación de Caso</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2 style="color: #c84661;">Hola, <?php echo e($user->name); ?></h2>
    <p>Se te ha asignado un nuevo caso en el Sistema de Gestión de Casos Jurídicos.</p>
    
    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p><strong>Radicado:</strong> <?php echo e($caso->radicado); ?></p>
        <p><strong>Tipo de Proceso:</strong> <?php echo e($caso->tipoProceso->nombre ?? 'N/A'); ?></p>
        <p><strong>Descripción:</strong> <?php echo e($caso->descripcion); ?></p>
    </div>

    <?php if(!empty($tareas) && count($tareas) > 0): ?>
    <h3>Tareas Asignadas:</h3>
    <ul>
        <?php $__currentLoopData = $tareas; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tarea): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <li><?php echo e(is_string($tarea) ? $tarea : $tarea->descripcion); ?></li>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </ul>
    <?php endif; ?>

    <p>
        <a href="<?php echo e(route('casos.show', $caso->id)); ?>" style="display: inline-block; padding: 10px 20px; background-color: #c84661; color: #fff; text-decoration: none; border-radius: 5px;">
            Ver Caso Completo
        </a>
    </p>
    <br>
    <p>Saludos,<br>El equipo del sistema.</p>
</body>
</html>
<?php /**PATH /var/www/resources/views/emails/case-assigned.blade.php ENDPATH**/ ?>