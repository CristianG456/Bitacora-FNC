<!DOCTYPE html>
<html>
<head>
    <title>Bienvenido al Sistema</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2 style="color: #c84661;">Hola, {{ $user->name }}</h2>
    <p>Se ha creado una cuenta para ti en el Sistema de Gestión de Casos Jurídicos.</p>
    <p>Tu correo de acceso es: <strong>{{ $user->email }}</strong></p>
    <p>
        <strong>Contraseña temporal:</strong> El administrador te comunicará tu contraseña de acceso
        por un canal seguro (presencialmente o por medio verificado).
    </p>
    <p><em>Por motivos de seguridad, el sistema te solicitará cambiar esta contraseña temporal en tu primer inicio de sesión.</em></p>
    <p>
        <a href="{{ route('login') }}" style="display: inline-block; padding: 10px 20px; background-color: #c84661; color: #fff; text-decoration: none; border-radius: 5px;">
            Ingresar al Sistema
        </a>
    </p>
    <br>
    <p>Saludos,<br>El equipo del sistema.</p>
</body>
</html>
