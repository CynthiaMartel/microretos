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

        // Una cookie de sesión y una XSRF-TOKEN por herramienta (app, comercial, Central):
        // entrar con otra cuenta en una no cambia la sesión de las demás. Global y al
        // principio para que el nombre esté decidido antes de StartSession.
        $middleware->prepend(\App\Http\Middleware\SesionPorHerramienta::class);
        $middleware->web(replace: [
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class => \App\Http\Middleware\ValidarCsrfPorHerramienta::class,
        ]);

        // Cabeceras de seguridad HTTP en todas las respuestas
        $middleware->append(SecurityHeaders::class);

        $middleware->alias([
            'admin'      => \App\Http\Middleware\EnsureIsAdmin::class,
            'superadmin' => \App\Http\Middleware\EnsureIsSuperAdmin::class,
            'docente'    => \App\Http\Middleware\EnsureIsDocente::class,
            'comercial'  => \App\Http\Middleware\EnsureIsComercial::class,
            'primer.contacto' => \App\Http\Middleware\EnsurePuedePrimerContacto::class,
            // Espacio «Red DuaLab» de Central (formularios, correo, captaciones): red_dualab, central_dualab y superadmin
            'red.dualab' => \App\Http\Middleware\EnsurePuedePrimerContacto::class,
            'practicas' => \App\Http\Middleware\EnsurePuedeGestionarPracticas::class,
            'usuarios' => \App\Http\Middleware\EnsurePuedeGestionarUsuarios::class,
            'admin.central' => \App\Http\Middleware\EnsureEsAdminCentral::class,
            'firma.frontoffice' => \App\Http\Middleware\VerificarFirmaFrontoffice::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
