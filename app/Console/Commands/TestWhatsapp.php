<?php

namespace App\Console\Commands;

use App\Services\WhatsappService;
use Illuminate\Console\Command;

class TestWhatsapp extends Command
{
    protected $signature = 'whatsapp:test {phone} {message?}';
    protected $description = 'Test WhatsApp message sending';

    public function handle(WhatsappService $whatsapp): int
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message') ?? 'Mensaje de prueba';

        $response = $whatsapp->sendMessage($phone, $message);

        if ($response['success']) {
            $this->info("Mensaje enviado correctamente. SID: {$response['sid']}");
            return Command::SUCCESS;
        }

        $this->error("Error: {$response['error']}");
        return Command::FAILURE;
    }
}