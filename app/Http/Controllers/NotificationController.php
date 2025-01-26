<?php

namespace App\Http\Controllers;

use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\WhatsappRequest;

class NotificationController extends Controller
{
    public function __construct(
        private readonly WhatsappService $whatsapp
    ) {}

    public function sendWhatsapp(WhatsappRequest $request): JsonResponse
    {
        $response = $this->whatsapp->sendMessage(
            $request->validated('phone'),
            $request->validated('message')
        );

        if ($response['success']) {
            return response()->json(['message' => 'Mensaje enviado']);
        }

        return response()->json(['error' => $response['error']], 500);
    }
}