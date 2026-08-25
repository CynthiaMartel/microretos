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

        RateLimiter::for('startup-landing', function (Request $request) {
            return Limit::perMinute(30)->by($request->route('token') ?? $request->ip());
        });

        RateLimiter::for('datos-academicos', function (Request $request) {
            return Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('admin-login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });
    }
}
