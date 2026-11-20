<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecibirFormularioWebRequest;
use App\Services\CaptacionFormularioWebService;
use Illuminate\Http\JsonResponse;

/**
 * Webhook del frontoffice (dualab.es): cada formulario de /contacto llega aquí firmado
 * (middleware 'firma.frontoffice') y se guarda como captación pendiente de validar.
 */
class IntegracionFrontofficeController extends Controller
{
    public function __construct(private CaptacionFormularioWebService $formularios) {}

    /** POST /integraciones/frontoffice/formularios */
    public function recibir(RecibirFormularioWebRequest $request): JsonResponse
    {
        // Prueba de conexión desde Gestión web: firma correcta, pero no se guarda ninguna captación
        if ($request->boolean('prueba')) {
            return response()->json(['recibido' => true, 'prueba' => true]);
        }

        $resultado = $this->formularios->procesar($request->validated());

        // 200 también para lo que se ignora (administración, formulario rápido): el webhook no
        // debe reintentarlo
        return response()->json(['recibido' => true] + $resultado);
    }
}
