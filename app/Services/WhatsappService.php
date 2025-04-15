<?php

namespace App\Services;

use Twilio\Rest\Client;
use Exception;

class WhatsappService
{
    private readonly Client $client;
    private readonly string $fromNumber;

    public function __construct()
    {
        $this->client = new Client(
            config('services.twilio.sid'),
            config('services.twilio.token')
        );
        $this->fromNumber = config('services.twilio.whatsapp_from');
    }

    public function sendMessage(string $to, string $message): array
    {
        try {
            $message = $this->client->messages->create(
                "whatsapp:+{$to}",
                [
                    'from' => $this->fromNumber,
                    'body' => $message
                ]
            );
            
            return ['success' => true, 'sid' => $message->sid];
        } catch (Exception $e) {
            report($e);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}