<?php $__env->startSection('title', 'Crear Usuario - Sistema Jurídico'); ?>

<?php $__env->startSection('content'); ?>

<!-- HEADER -->
<div class="mb-6 -mx-6 -mt-6 px-6 py-4 border-b border-gray-200 bg-white">
    <div class="flex items-center gap-3">
        <a href="<?php echo e(route('usuarios.index')); ?>" class="text-gray-400 hover:text-gray-700 transition">
            <i data-lucide="arrow-left" style="width:20px;height:20px;"></i>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Crear Nuevo Usuario</h1>
            <p class="text-gray-500 text-sm mt-1">Registra un nuevo acceso al sistema</p>
        </div>
    </div>
</div>

<div class="max-w-3xl bg-white rounded-lg border border-gray-200 shadow-sm p-6">
    <form action="<?php echo e(route('usuarios.guardar')); ?>" method="POST">
        <?php echo csrf_field(); ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <!-- Nombre -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Nombre Completo *</label>
                <input type="text" name="name" value="<?php echo e(old('name')); ?>" required
                       pattern="[a-zA-Z\sñÑáéíóúÁÉÍÓÚ]+" title="Solo se permiten letras y espacios"
                       oninput="this.value = this.value.replace(/[^a-zA-Z\sñÑáéíóúÁÉÍÓÚ]/g, '').replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Correo -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Correo Electrónico *</label>
                <input type="email" name="email" value="<?php echo e(old('email')); ?>" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Contraseña *</label>
                <input type="password" name="password" required minlength="8"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Confirmar Contraseña -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Confirmar Contraseña *</label>
                <input type="password" name="password_confirmation" required minlength="8"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
            </div>

            <!-- Rol -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Rol del Sistema *</label>
                <select name="rol_id" required
                        class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                    <option value="">Seleccione un rol...</option>
                    <?php $__currentLoopData = $roles; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rol): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <option value="<?php echo e($rol->id); ?>" <?php echo e(old('rol_id') == $rol->id ? 'selected' : ''); ?>>
                            <?php echo e($rol->nombre); ?>

                        </option>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </select>
                <?php $__errorArgs = ['rol_id'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>

            <!-- Área -->
            <div>
                <label class="block text-xs font-semibold text-gray-900 mb-2">Área / Departamento</label>
                <input type="text" name="area" value="<?php echo e(old('area')); ?>"
                       oninput="this.value = this.value.replace(/(?:^|\s)\S/g, function(a) { return a.toUpperCase(); });"
                       class="w-full px-4 py-2 border border-gray-300 rounded-md bg-gray-50 text-sm focus:bg-white focus:border-red-500 outline-none transition">
                <?php $__errorArgs = ['area'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> <span class="text-red-500 text-xs mt-1 block"><?php echo e($message); ?></span> <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
            </div>
        </div>

        <!-- Estado -->
        <div class="mb-8 p-4 bg-gray-50 rounded-md border border-gray-200">
            <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" name="activo" value="1" <?php echo e(old('activo', true) ? 'checked' : ''); ?>

                       class="w-4 h-4 text-red-600 border-gray-300 rounded focus:ring-red-500">
                <span class="text-sm font-semibold text-gray-900">Usuario Activo</span>
            </label>
            <p class="text-xs text-gray-500 mt-1 ml-7">Si se desmarca, el usuario no podrá ingresar al sistema.</p>
        </div>

        <div class="flex justify-end gap-3 pt-4 border-t border-gray-100">
            <a href="<?php echo e(route('usuarios.index')); ?>" class="px-5 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition">
                Cancelar
            </a>
            <button type="submit" class="btn-primary">
                Guardar Usuario
            </button>
        </div>
    </form>
</div>

<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/resources/views/usuarios/crear.blade.php ENDPATH**/ ?>