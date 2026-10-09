<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificacionResource;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    /**
     * GET /api/notificaciones?per_page=&solo_no_leidas=1
     * Siempre sobre $request->user()->notifications(): nunca se accede a las de otro usuario.
     */
    public function index(Request $request)
    {
        $perPage = min(max((int) $request->query('per_page', 20), 1), 50);

        $query = $request->user()->notifications()->latest();
        if ($request->boolean('solo_no_leidas')) {
            $query->whereNull('read_at');
        }

        return NotificacionResource::collection($query->paginate($perPage));
    }

    /**
     * GET /api/notificaciones/no-leidas — contador para el badge del panel lateral
     */
    public function contadorNoLeidas(Request $request)
    {
        return response()->json(['no_leidas' => $request->user()->unreadNotifications()->count()]);
    }

    /**
     * PATCH /api/notificaciones/{id}/leida
     */
    public function marcarLeida(Request $request, string $id)
    {
        // Búsqueda acotada al usuario: un id ajeno da 404 (sin IDOR)
        $notificacion = $request->user()->notifications()->whereKey($id)->firstOrFail();
        $notificacion->markAsRead();

        return new NotificacionResource($notificacion);
    }

    /**
     * POST /api/notificaciones/leer-todas
     */
    public function marcarTodasLeidas(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['no_leidas' => 0]);
    }
}
