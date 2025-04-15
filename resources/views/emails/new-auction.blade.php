<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nueva Subasta Disponible</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #003366;
            color: white;
            padding: 15px;
            text-align: center;
        }
        .content {
            padding: 20px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #e63946;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin-top: 15px;
        }
        .info-box {
            background-color: #fff;
            border: 1px solid #ddd;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
        }
        table td:first-child {
            font-weight: bold;
            width: 40%;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>¡Nueva Subasta Disponible!</h2>
        </div>
        
        <div class="content">
            <p>Estimado revendedor,</p>
            
            <p>Te notificamos que pronto comenzará una nueva subasta. ¡No te la pierdas!</p>
            
            <div class="info-box">
                <table>
                    <tr>
                        <td>Vehículo:</td>
                        <td>{{ $vehiculo }}</td>
                    </tr>
                    <tr>
                        <td>Fecha de Inicio:</td>
                        <td>{{ $fecha_inicio }}</td>
                    </tr>
                    <tr>
                        <td>Fecha de Fin:</td>
                        <td>{{ $fecha_fin }}</td>
                    </tr>
                </table>
            </div>
            
            <p>Recuerda ingresar a tiempo para hacer tus ofertas y no perder esta oportunidad.</p>
            
            <div style="text-align: center;">
                <a href="{{ config('app.url') }}" class="btn">Ir a la Subasta</a>
            </div>
        </div>
        
        <div class="footer">
            <p>Este es un mensaje automático, por favor no responda a este correo.</p>
            <p>&copy; {{ date('Y') }} Mitsui Automotriz. Todos los derechos reservados.</p>
        </div>
    </div>
</body>
</html> 