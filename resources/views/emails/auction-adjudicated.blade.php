<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style type="text/css">
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
            padding: 20px;
            text-align: center;
        }
        .header h1 {
            color: white;
            margin: 0;
        }
        .content {
            padding: 20px;
            background-color: #f5f5f5;
        }
        .footer {
            text-align: center;
            padding: 10px;
            font-size: 12px;
            color: #777;
        }
        .btn {
            display: inline-block;
            background-color: #004d99;
            color: white !important;
            text-decoration: none;
            padding: 10px 20px;
            margin-top: 15px;
            border-radius: 4px;
        }
        .auction-details {
            margin: 15px 0;
            padding: 15px;
            background-color: white;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .auction-details p {
            margin: 5px 0;
        }
        .highlight {
            font-weight: bold;
            color: #004d99;
        }
        .success {
            background-color: #e6f7e6;
            border-left: 4px solid #33cc33;
            padding: 10px;
            margin: 15px 0;
        }
        .price-info {
            background-color: #ffffcc;
            padding: 10px;
            border-radius: 4px;
            margin-top: 15px;
        }
        .increment {
            color: #009933;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Subastas Mitsui</h1>
        </div>
        
        <div class="content">
            <h2>Subasta Adjudicada Exitosamente</h2>
            
            <div class="success">
                <p><strong>¡La subasta ha sido adjudicada correctamente!</strong></p>
            </div>
            
            <div class="auction-details">
                <h3>Detalles del Vehículo</h3>
                <p><strong>Placa:</strong> {{ $auction['placa'] }}</p>
                <p><strong>Marca:</strong> {{ $auction['marca'] }}</p>
                <p><strong>Modelo:</strong> {{ $auction['modelo'] }}</p>
                <p><strong>Año:</strong> {{ $auction['year'] }}</p>
                
                <h3>Detalles de la Subasta</h3>
                <p><strong>Fecha de inicio:</strong> {{ $auction['fecha_inicio'] }}</p>
                <p><strong>Fecha de cierre:</strong> {{ $auction['fecha_fin'] }}</p>
                <p><strong>Fecha de adjudicación:</strong> {{ $auction['fecha_adjudicacion'] }}</p>
                <p><strong>Total de pujas:</strong> {{ $auction['total_pujas'] }}</p>
                
                <div class="price-info">
                    <p><strong>Precio base:</strong> S/. {{ $auction['precio_base'] }}</p>
                    <p><strong>Precio final:</strong> S/. {{ $auction['precio_final'] }}</p>
                    <p><strong>Incremento:</strong> <span class="increment">S/. {{ $auction['incremento'] }} ({{ $auction['incremento_porcentaje'] }}%)</span></p>
                </div>
                
                <h3>Datos del Revendedor Adjudicado</h3>
                <p><strong>Nombre:</strong> {{ $reseller['nombre'] }}</p>
                <p><strong>Email:</strong> {{ $reseller['email'] }}</p>
            </div>
            
            <p>
                <a href="{{ config('app.url') }}/auctions/{{ $auction['id'] }}" class="btn">Ver Detalles de la Subasta</a>
            </p>
        </div>
        
        <div class="footer">
            <p>© {{ date('Y') }} Mitsui Automotriz. Todos los derechos reservados.</p>
            <p>Este es un correo automático, por favor no responda a este mensaje.</p>
        </div>
    </div>
</body>
</html> 