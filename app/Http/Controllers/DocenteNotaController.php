<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDocenteNotaRequest;
use App\Http\Requests\UpdateDocenteNotaRequest;
use App\Http\Resources\DocenteNotaResource;
use App\Models\DocenteNota;
use Illuminate\Http\Request;

class DocenteNotaController extends Controller
{
    // Techo del listado sin paginar: el calendario las filtra en cliente
    private const MAX_LISTADO = 300;

    /**
     * GET /api/notas — solo las del usuario autenticado
     */
    public function index(Request $request)
    {
        $notas = DocenteNota::where('user_id', $request->user()->id)
            ->latest()
            ->take(self::MAX_LISTADO)
            ->get();

        return DocenteNotaResource::collection($notas);
    }

    /**
     * POST /api/notas
     */
    public function store(StoreDocenteNotaRequest $request)
    {
        $nota = new DocenteNota($request->validated());
        $nota->user_id = $request->user()->id;
        $nota->save();

        return (new DocenteNotaResource($nota))->response()->setStatusCode(201);
    }

    /**
     * PATCH /api/notas/{nota}
     */
    public function update(UpdateDocenteNotaRequest $request, DocenteNota $nota)
    {
        $this->authorize('update', $nota);
        $nota->update($request->validated());

        return new DocenteNotaResource($nota);
    }

    /**
     * DELETE /api/notas/{nota}
     */
    public function destroy(Request $request, DocenteNota $nota)
    {
        $this->authorize('delete', $nota);
        $nota->delete();

        return response()->noContent();
    }
}
