<?php

namespace App\Http\Controllers;

use App\Services\MetaWaService;
use App\Http\Requests\MetaWaRequest;

class MetaWaController extends Controller
{
    protected $metaWaService;

    public function __construct(MetaWaService $metaWaService)
    {
        $this->metaWaService = $metaWaService;
    }

    public function sendMessage(MetaWaRequest $request)
    {
        try {
            $response = $this->metaWaService->sendMessage(
                $request->recipient,
                $request->message
            );

            return response()->json([
                'success' => true,
                'data' => $response
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}