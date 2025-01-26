<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWaService
{
    private $token;
    private $phoneNumberId;
    private $apiVersion;
    private $baseUrl;

    public function __construct()
    {
        $this->token = config('services.meta_wa.token');
        $this->phoneNumberId = config('services.meta_wa.phone_number_id');
        $this->apiVersion = config('services.meta_wa.api_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    public function sendMessage(string $recipient, string $message)
    {
        try {
            $data = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $message
                ]
            ];

            Log::info('Intentando enviar mensaje de WhatsApp', [
                'to' => $recipient,
                'message' => $message
            ]);

            $response = Http::withToken($this->token)
                ->post("{$this->baseUrl}/messages", $data);

            if (!$response->successful()) {
                throw new \Exception("Error HTTP: {$response->status()} - {$response->body()}");
            }

            $decoded = $response->json();

            Log::info('Respuesta de WhatsApp recibida', [
                'response' => $decoded
            ]);

            return $decoded;

        } catch (\Exception $e) {
            Log::error('Error enviando mensaje de WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}c