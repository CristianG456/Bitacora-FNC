<!DOCTYPE html>
<html>
<head>
    <title>Bienvenido al Sistema</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2 style="color: #c84661;">Hola, <?php echo e($user->name); ?></h2>
    <p>Se ha creado una cuenta para ti en el Sistema de Gestión de Casos Jurídicos.</p>
    <p>Tus credenciales de acceso son:</p>
    <ul>
        <li><strong>Correo:</strong> <?php echo e($user->email); ?></li>
        <li><strong>Contraseña Temporal:</strong> <?php echo e($temporalPassword); ?></li>
    </ul>
    <p><em>Por motivos de seguridad, el sistema te solicitará cambiar esta contraseña temporal en tu primer inicio de sesión.</em></p>
    <p>
        <a href="<?php echo e(route('login')); ?>" style="display: inline-block; padding: 10px 20px; background-color: #c84661; color: #fff; text-decoration: none; border-radius: 5px;">
            Ingresar al Sistema
        </a>
    </p>
    <br>
    <p>Saludos,<br>El equipo del sistema.</p>
</body>
</html>
<?php /**PATH /var/www/resources/views/emails/user-created.blade.php ENDPATH**/ ?>