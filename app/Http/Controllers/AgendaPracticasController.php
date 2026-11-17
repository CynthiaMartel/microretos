<?php

namespace App\Http\Controllers;

use App\Services\AgendaPracticasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /agenda-practicas: cronogramas de planes de proyecto con el avance de cada grupo.
 * El docente ve los suyos (y los compartidos); el superadmin, todos (DuaLab Central).
 */
class AgendaPracticasController extends Controller
{
    public function index(Request $request, AgendaPracticasService $agenda): JsonResponse
    {
        return response()->json($agenda->cronogramas($request->user()));
    }
}
