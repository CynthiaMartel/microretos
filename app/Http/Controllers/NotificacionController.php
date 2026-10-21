<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificacionResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificacionController extends Controller
{
    /**
     * GET /api/notificaciones?per_page=&solo_no_leidas=1
     * Siempre sobre $request->user()->notifications(): nunca se accede a las de otro usuario.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        /** @var User $user  ruta bajo auth:sanctum */
        $user  = $request->user();
        $query = $user->notifications()->latest();
        if ($request->boolean('solo_no_leidas')) {
            $query->whereNull('read_at');
        }

        return NotificacionResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/notificaciones/no-leidas — contador para el badge del panel lateral
     */
    public function contadorNoLeidas(Request $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();

        return response()->json(['no_leidas' => $user->unreadNotifications()->count()]);
    }

    /**
     * PATCH /api/notificaciones/{id}/leida
     */
    public function marcarLeida(Request $request, string $id): NotificacionResource
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();

        // Búsqueda acotada al usuario: un id ajeno da 404 (sin IDOR)
        $notificacion = $user->notifications()->whereKey($id)->firstOrFail();
        $notificacion->markAsRead();

        return new NotificacionResource($notificacion);
    }

    /**
     * POST /api/notificaciones/leer-todas
     */
    public function marcarTodasLeidas(Request $request): JsonResponse
    {
        /** @var User $user  ruta bajo auth:sanctum */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['no_leidas' => 0]);
    }
}
