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
        $this->apiVersion = config('services.meta_wa.api_version', 'v19.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    public function sendTemplateMessage(string $recipient, array $templateData)
    {
        try {
            Log::info('Intentando enviar plantilla WhatsApp', [
                'to' => $recipient,
                'template_name' => $templateData['template']['name'],
                'data' => $templateData
            ]);

            $response = Http::withToken($this->token)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ])
                ->post("{$this->baseUrl}/messages", $templateData);

            if (!$response->successful()) {
                $error = $response->json();
                Log::error('Error en respuesta de WhatsApp', [
                    'status' => $response->status(),
                    'error' => $error,
                    'data' => $templateData
                ]);
                throw new \Exception("Error en la API: " . ($error['error']['message'] ?? 'Error desconocido'));
            }

            $result = $response->json();
            Log::info('Respuesta exitosa de WhatsApp', [
                'response' => $result
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Error enviando plantilla WhatsApp', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $templateData
            ]);
            throw $e;
        }
    }
}