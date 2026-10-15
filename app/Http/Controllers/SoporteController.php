<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSoporteRequest;
use App\Services\SoporteService;
use Illuminate\Http\JsonResponse;

class SoporteController extends Controller
{
    private const MENSAJE_OK = 'Hemos recibido tu consulta. Te responderemos por email lo antes posible.';

    public function store(StoreSoporteRequest $request, SoporteService $soporte): JsonResponse
    {
        $data = $request->validated();

        // Honeypot: se responde igual que a un envío válido para no dar pistas al bot
        if (!empty($data['website'])) {
            return response()->json(['success' => true, 'message' => self::MENSAJE_OK], 201);
        }

        $soporte->enviar($data);

        return response()->json(['success' => true, 'message' => self::MENSAJE_OK], 201);
    }
}
