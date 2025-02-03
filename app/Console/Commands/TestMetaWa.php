<?php

namespace App\Console\Commands;

use App\Services\MetaWaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestMetaWa extends Command
{
    protected $signature = 'meta-wa:test {recipient?} {placa?} {fecha_inicio?} {fecha_fin?}';
    protected $description = 'Envía un mensaje de prueba por WhatsApp usando Meta API';

    public function handle(MetaWaService $metaWaService)
    {
        try {
            $recipient = $this->argument('recipient') ?? config('services.meta_wa.test_number');
            if (empty($recipient)) {
                throw new \Exception('Se requiere un número de teléfono destinatario');
            }

            // Formatear el número correctamente
            $recipient = preg_replace('/[^0-9]/', '', $recipient);
            
            $placa = $this->argument('placa') ?? 'TEST-790';
            $fechaInicio = $this->argument('fecha_inicio') ?? '31/01/2025 14:11';
            $fechaFin = $this->argument('fecha_fin') ?? '03/02/2025 14:11';

            // Información de configuración
            $this->info("\nVerificando configuración...");
            $this->table(
                ['Configuración', 'Valor'],
                [
                    ['API Version', config('services.meta_wa.api_version', 'v19.0')],
                    ['Phone Number ID', config('services.meta_wa.phone_number_id')],
                    ['Destinatario', $recipient],
                ]
            );
            
            // Envío de plantilla
            $this->info("\nPreparando envío de plantilla...");
            
            $templateData = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'template',
                'template' => [
                    'name' => 'new_auction',
                    'language' => [
                        'code' => 'es_PE'
                    ],
                    'components' => [
                        [
                            'type' => 'body',
                            'parameters' => [
                                [
                                    'type' => 'text',
                                    'text' => $placa
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $fechaInicio
                                ],
                                [
                                    'type' => 'text',
                                    'text' => $fechaFin
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            $this->line("\nDatos a enviar:");
            $this->table(
                ['Campo', 'Valor'],
                [
                    ['Destinatario', $recipient],
                    ['Placa', $placa],
                    ['Inicio', $fechaInicio],
                    ['Fin', $fechaFin]
                ]
            );

            $this->line("\nPayload completo:");
            $this->line(json_encode($templateData, JSON_PRETTY_PRINT));

            $this->info("\nEnviando mensaje...");
            $response = $metaWaService->sendTemplateMessage($recipient, $templateData);
            
            if (isset($response['messages'][0]['id'])) {
                $this->info("\n✓ ¡Mensaje enviado exitosamente!");
                $this->info("ID del mensaje: " . $response['messages'][0]['id']);
                
                $this->line("\nRespuesta completa de la API:");
                $this->line(json_encode($response, JSON_PRETTY_PRINT));

                // Info adicional
                $this->line("\nPasos siguientes:");
                $this->line("1. Verifica que el número $recipient sea un número de WhatsApp activo");
                $this->line("2. Confirma que la plantilla 'new_auction' esté aprobada en Meta Business Manager");
                $this->line("3. El mensaje puede tardar unos minutos en ser entregado");
            } else {
                $this->warn("\n⚠️  El mensaje fue aceptado pero no se recibió ID de confirmación");
            }

            return 0;

        } catch (\Exception $e) {
            Log::error('Error en comando meta-wa:test', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->error("\n✕ Error: " . $e->getMessage());
            
            if (isset($templateData)) {
                $this->line("\nPayload que se intentó enviar:");
                $this->line(json_encode($templateData, JSON_PRETTY_PRINT));
            }

            return 1;
        }
    }
}