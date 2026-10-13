<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportarCurriculoRequest;
use App\Models\CicloFormativo;
use App\Services\CurriculoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Carga del currículo (módulos, RA y CE) de un ciclo desde «Base de datos» (superadmin).
 * Sin módulos con RA y CE una familia no se ofrece para generar retos.
 */
class CurriculoController extends Controller
{
    /** POST /ciclos/{id}/curriculo/importar — con `simular` solo valida y resume. */
    public function importar(ImportarCurriculoRequest $request, CurriculoImportService $servicio, int $id): JsonResponse
    {
        $ciclo = CicloFormativo::find($id);
        if (!$ciclo) {
            return response()->json(['error' => 'Ciclo no encontrado'], 404);
        }

        $simular   = $request->boolean('simular');
        $resultado = $servicio->importar($ciclo, $request->file('archivo'), $simular);

        if ($resultado['errores']) {
            return response()->json(['error' => 'El archivo tiene errores: no se ha importado nada.'] + $resultado, 422);
        }

        if ($resultado['importado']) {
            Log::info('Currículo importado.', [
                'ciclo_id' => $ciclo->id,
                'user_id'  => $request->user()->id,
                'modulos'  => count($resultado['modulos_nuevos']),
                'ras'      => $resultado['total_ras'],
                'ces'      => $resultado['total_ces'],
            ]);
        }

        return response()->json($resultado);
    }
}
