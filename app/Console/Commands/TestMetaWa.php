<?php

namespace App\Console\Commands;

use App\Services\MetaWaService;
use Illuminate\Console\Command;

class TestMetaWa extends Command
{
    protected $signature = 'meta-wa:test {recipient?}';
    protected $description = 'Envía un mensaje de prueba por WhatsApp usando Meta API';

    public function handle(MetaWaService $metaWaService)
    {
        $recipient = $this->argument('recipient') ?? config('services.meta_wa.test_number');
        $message = "Mensaje de prueba Meta WhatsApp - " . now();

        try {
            $response = $metaWaService->sendMessage($recipient, $message);
            $this->info("¡Mensaje enviado exitosamente!");
            $this->table(['Campo', 'Valor'], [
                ['Destinatario', $recipient],
                ['Mensaje', $message],
                ['ID Mensaje', $response['messages'][0]['id'] ?? 'N/A']
            ]);
        } catch (\Exception $e) {
            $this->error("Error enviando mensaje: " . $e->getMessage());
        }
    }
}