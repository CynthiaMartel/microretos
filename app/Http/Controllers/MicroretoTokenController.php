<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Microreto;
use App\Models\MicroretoToken;
use App\Models\User;

// Endpoint API: /microretos/{id}/token (sin cambios). El frontend renombró su URL a /retos y /retos/crear,
// pero el modelo, la tabla y este controlador siguen llamándose "Microreto" — no renombrado a propósito.
class MicroretoTokenController extends Controller
{
    /**
     * Devuelve el token activo del microreto, si existe.
     * Solo accesible por docentes/admin de su propio centro, o superadmin.
     * GET /api/microretos/{id}/token
     */
    public function get(Request $request, $id)
    {
        $microreto = Microreto::with('empresa')->where('uuid', $id)->firstOrFail();
        $this->autorizarMicroreto($request->user(), $microreto);

        // Limpia tokens expirados de este microreto antes de consultar
        MicroretoToken::where('microreto_id', $microreto->id)
                       ->where('expires_at', '<=', now())
                       ->delete();

        $token = MicroretoToken::where('microreto_id', $microreto->id)->first();

        if (! $token) {
            return response()->json(['token' => null]);
        }

        return response()->json([
            'token'      => $token->token,
            'expires_at' => $token->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Crea un token nuevo para el microreto.
     * Solo se llama si no existe uno activo (el frontend lo controla).
     * Solo accesible por docentes/admin de su propio centro, o superadmin.
     * POST /api/microretos/{id}/token
     */
    public function generate(Request $request, $id)
    {
        $microreto = Microreto::with('empresa')->where('uuid', $id)->firstOrFail();
        $this->autorizarMicroreto($request->user(), $microreto);

        // Elimina cualquier token previo (activo o expirado) de este microreto
        MicroretoToken::where('microreto_id', $microreto->id)->delete();

        $token = MicroretoToken::create([
            'microreto_id' => $microreto->id,
            'token'        => Str::random(48),
            'expires_at'   => now()->addHours(48),
        ]);

        return response()->json([
            'token'      => $token->token,
            'expires_at' => $token->expires_at->toIso8601String(),
        ], 201);
    }

    /**
     * Resuelve un token público y devuelve los datos del microreto.
     * Público — sin autenticación.
     * GET /api/public/microreto/{token}
     */
    public function show($token)
    {
        $record = MicroretoToken::where('token', $token)->first();

        if (! $record || $record->isExpired()) {
            return response()->json(['error' => 'Token inválido o expirado'], 404);
        }

        $reto = Microreto::with([
            'empresa.centroEducativo',
            'empresa.familias',
        ])->findOrFail($record->microreto_id);

        $reto->es_simulado = (bool) $reto->es_simulado;

        if ($reto->empresa) {
            $reto->centro_educativo = $reto->empresa->centroEducativo?->nombre
                ?? $reto->empresa->centro_educativo
                ?? 'Centro Desconocido';
            $reto->familia = $reto->empresa->familias->first()?->nombre
                ?? 'Familia Desconocida';
        } else {
            $reto->centro_educativo = 'Centro Desconocido';
            $reto->familia          = 'Familia Desconocida';
        }

        return response()->json([
            'microreto'  => $reto,
            'expires_at' => $record->expires_at->toIso8601String(),
        ]);
    }

    /**
     * Revoca (elimina) el token activo del microreto.
     * Solo accesible por docentes/admin de su propio centro, o superadmin.
     * DELETE /api/microretos/{id}/token
     */
    public function destroy(Request $request, $id)
    {
        $microreto = Microreto::with('empresa')->where('uuid', $id)->firstOrFail();
        $this->autorizarMicroreto($request->user(), $microreto);

        MicroretoToken::where('microreto_id', $microreto->id)->delete();

        return response()->json(['message' => 'Acceso QR revocado correctamente']);
    }

    // Docente y admin solo pueden emitir/revocar el QR de microretos de su propio
    // centro; superadmin no tiene esta restricción. Aborta con 403 si no cumple.
    private function autorizarMicroreto(User $user, Microreto $microreto): void
    {
        if ($user->isSuperAdmin()) {
            return;
        }

        if (($user->isDocente() || $user->isAdmin())
            && $microreto->empresa
            && $microreto->empresa->perteneceAlCentroDe($user)) {
            return;
        }

        abort(403, 'No autorizado: este micro-reto no pertenece a tu centro educativo.');
    }
}
