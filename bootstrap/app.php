<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\SecurityHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA stateful: cookies HttpOnly de sesión en vez de Bearer token en
        // localStorage. Añade EnsureFrontendRequestsAreStateful al grupo 'api' y activa
        // el sub-pipeline de sesión/CSRF ya declarado en config/sanctum.php.
        $middleware->statefulApi();

        // Cabeceras de seguridad HTTP en todas las respuestas
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin'      => \App\Http\Middleware\EnsureIsAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureIsSuperAdmin::class,
            'docente'    => \App\Http\Middleware\EnsureIsDocente::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
