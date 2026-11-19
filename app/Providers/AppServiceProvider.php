<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rate limiters del workspace público de equipos (alumnado), por token de equipo
        // en vez de por IP: sin esto, `throttle:N,1` comparte un único cupo por IP entre
        // TODAS las rutas públicas del workspace (ver ThrottleRequests::resolveRequestSignature,
        // que para peticiones sin sesión usa sha1(dominio|IP) sin importar la ruta) — el
        // autoguardado y la navegación normal de un equipo agotaban el cupo antes de pedir
        // una sugerencia de IA, y varios equipos tras el mismo NAT de instituto compartían cupo.
        RateLimiter::for('workspace-lectura', function (Request $request) {
            return Limit::perMinute(120)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('workspace-prototipos', function (Request $request) {
            return Limit::perMinute(30)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('workspace-imagenes', function (Request $request) {
            return Limit::perMinute(30)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('workspace-ia', function (Request $request) {
            return Limit::perMinute(10)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('workspace-ia-codigo', function (Request $request) {
            return Limit::perMinute(15)->by($request->route('token') ?? $request->ip());
        });

        // Resto de rutas públicas: mismo problema de bucket compartido sin nombre
        // (sha1(dominio|IP) sin importar la ruta) — cada grupo aquí abajo tiene ahora
        // su propio cupo aislado, por token cuando la ruta expone uno, si no por IP.
        RateLimiter::for('publico-lectura', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        RateLimiter::for('microreto-publico', function (Request $request) {
            return Limit::perMinute(60)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('workspace-codigo', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        // Validación de la empresa por enlace: por IP. Con un cupo por token, probar tokens al azar
        // tendría uno nuevo en cada intento (sin límite real contra la fuerza bruta).
        RateLimiter::for('startup-landing', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Valoración final de la empresa: por IP (un cupo por token no frenaría probar tokens)
        RateLimiter::for('valoracion-empresa', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('datos-academicos', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Contraseña común del módulo Empresas: usuario + IP, como el login, para que ni
        // una cuenta desde varias IPs ni varias cuentas desde una IP puedan probar en bucle.
        RateLimiter::for('empresas-acceso', function (Request $request) {
            return Limit::perMinute(5)->by(($request->user()->id ?? 'anon') . '|' . $request->ip());
        });

        // Escaparate público de microretos (frontoffice) — throttle propio, no
        // comparte cupo con el resto de rutas públicas.
        RateLimiter::for('catalogo-publico', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // Formulario de la entrevista enviado a la empresa (público, por token): guarda al pasar
        // de pregunta, así que el cupo es holgado
        RateLimiter::for('formulario-empresa', function (Request $request) {
            return Limit::perMinute(60)->by($request->route('token') ?? $request->ip());
        });

        // Solicitud de retos enviada al docente (pública, por token): guarda al pasar de paso
        RateLimiter::for('solicitud-reto', function (Request $request) {
            return Limit::perMinute(60)->by('solicitud-reto|' . ($request->route('token') ?? $request->ip()));
        });

        // Formulario de soporte (público, envía 2 emails por petición): 3/min y 10/hora por IP
        RateLimiter::for('soporte', function (Request $request) {
            return [
                Limit::perMinute(3)->by('soporte-min|' . $request->ip()),
                Limit::perHour(10)->by('soporte-hora|' . $request->ip()),
            ];
        });
    }
}
