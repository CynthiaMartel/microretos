<?php

namespace App\Http\Controllers;

use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Services\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $service) {}

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->service->enviarEnlace(Str::lower($request->validated()['email']));

        // Mismo mensaje exista o no la cuenta (anti-enumeración)
        return response()->json([
            'success' => true,
            'message' => 'Si existe una cuenta con ese email, recibirás un enlace para restablecer la contraseña.',
        ]);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['email'] = Str::lower($data['email']);

        if (!$this->service->restablecer($data)) {
            return response()->json([
                'success' => false,
                'message' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada. Ya puedes iniciar sesión con la nueva contraseña.',
        ]);
    }
}
