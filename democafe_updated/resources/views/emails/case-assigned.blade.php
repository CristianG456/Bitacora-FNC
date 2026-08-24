<!DOCTYPE html>
<html>
<head>
    <title>Asignación de Caso</title>
</head>
<body style="font-family: Arial, sans-serif; color: #333;">
    <h2 style="color: #c84661;">Hola, {{ $user->name }}</h2>
    <p>Se te ha asignado un nuevo caso en el Sistema de Gestión de Casos Jurídicos.</p>
    
    <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
        <p><strong>Radicado:</strong> {{ $caso->radicado }}</p>
        <p><strong>Tipo de Proceso:</strong> {{ $caso->tipoProceso->nombre ?? 'N/A' }}</p>
        <p><strong>Descripción:</strong> {{ $caso->descripcion }}</p>
    </div>

    @if(!empty($tareas) && count($tareas) > 0)
    <h3>Tareas Asignadas:</h3>
    <ul>
        @foreach($tareas as $tarea)
            <li>{{ is_string($tarea) ? $tarea : $tarea->descripcion }}</li>
        @endforeach
    </ul>
    @endif

    <p>
        <a href="{{ route('casos.show', $caso->id) }}" style="display: inline-block; padding: 10px 20px; background-color: #c84661; color: #fff; text-decoration: none; border-radius: 5px;">
            Ver Caso Completo
        </a>
    </p>
    <br>
    <p>Saludos,<br>El equipo del sistema.</p>
</body>
</html>
