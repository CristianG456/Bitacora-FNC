<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Exportar Historial Global</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
            line-height: 1.5;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #b11226;
            padding-bottom: 10px;
        }
        .header h1 {
            color: #b11226;
            margin: 0;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0 0;
            font-size: 14px;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f9f9f9;
            color: #b11226;
            font-weight: bold;
        }
        tr:nth-child(even) {
            background-color: #fcfcfc;
        }
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10px;
            color: #999;
        }
        
        @media print {
            body {
                padding: 0;
            }
            .btn-print {
                display: none;
            }
        }
        
        .btn-print {
            background-color: #b11226;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            border-radius: 4px;
            font-weight: bold;
            display: block;
            margin: 0 auto 20px;
        }
    </style>
</head>
<body>

    <button class="btn-print" onclick="window.print()">Imprimir / Guardar como PDF</button>

    <div class="header">
        <h1>Historial Global del Sistema</h1>
        <p>Bitácora de casos finalizados generada el {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha y Hora</th>
                <th>Radicado</th>
                <th>Evento</th>
                <th>Descripción</th>
                <th>Usuario</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            @foreach($eventos as $evento)
            <tr>
                <td>{{ $evento->created_at->format('d/m/Y H:i') }}</td>
                <td>{{ $evento->caso ? $evento->caso->radicado : 'N/A' }}</td>
                <td>{{ $evento->accion }}</td>
                <td>{{ $evento->descripcion }}</td>
                <td>{{ $evento->usuario ? $evento->usuario->name : 'Sistema' }}</td>
                <td>{{ $evento->usuario && $evento->usuario->role ? $evento->usuario->role->nombre : 'N/A' }}</td>
            </tr>
            @endforeach
            
            @if($eventos->isEmpty())
            <tr>
                <td colspan="6" style="text-align: center; padding: 20px;">No hay eventos registrados con los filtros seleccionados.</td>
            </tr>
            @endif
        </tbody>
    </table>

    <div class="footer">
        Sistema de Gestión de Casos Jurídicos - Federación Nacional de Cafeteros
    </div>

    <script>
        // Imprimir automáticamente al abrir la vista para facilidad del usuario
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>
