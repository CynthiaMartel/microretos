<?php

namespace App\Console\Commands\Concerns;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Boilerplate compartido por los comandos `demo:*` para invocar directamente los
 * métodos reales de los controllers HTTP (generación IA, persistencia, workflow de
 * equipos) sin pasar por rutas ni middleware, tal y como pide el encargo: reutilizar
 * la lógica ya implementada en vez de duplicar prompts o reglas de negocio.
 *
 * Se usa el mismo patrón en los 4 comandos generadores, así que vive aquí una sola
 * vez en lugar de repetirse en cada uno.
 */
trait InvocaControladoresReales
{
    /**
     * Autentica al usuario dado para ESTE proceso de consola: hace que tanto
     * Auth::user()/Gate (usado por $this->authorize() dentro de los controllers,
     * p.ej. MicroproyectoPolicy::update) como $request->user() (ver peticion())
     * resuelvan al mismo usuario, sin sesión HTTP ni token Sanctum real. Es la
     * misma técnica que se usa en tests de Laravel para "loguear" a un usuario
     * fuera de una petición HTTP real.
     */
    protected function actuarComo(User $usuario): void
    {
        Auth::setUser($usuario);
    }

    /**
     * Construye una Request (o FormRequest) sintética para invocar un método de
     * controller directamente, igual que si hubiera llegado por HTTP.
     *
     * Si $clase es una FormRequest, se dispara su ciclo de validación real
     * (prepareForValidation → authorize → rules), tal cual lo haría el contenedor
     * al inyectarla en una ruta — así el controller reutilizado no distingue esta
     * llamada de una petición real. Lanza ValidationException/AuthorizationException
     * si los datos no pasan, igual que en producción.
     *
     * Si $clase es el Request base (controllers que validan inline con
     * $request->validate() dentro del propio método, p.ej. MicroproyectoController::store),
     * solo se prepara el Request con el usuario — la validación ocurre dentro del
     * controller cuando se invoque.
     *
     * @template T of Request
     * @param class-string<T> $clase
     * @return T
     */
    protected function peticion(string $clase, array $datos, ?User $usuario = null): Request
    {
        /** @var T $request */
        $request = $clase::create('/', 'POST', $datos);

        if ($usuario) {
            $request->setUserResolver(fn () => $usuario);
        }

        if ($request instanceof FormRequest) {
            $request->setContainer(app());
            $request->setRedirector(app('redirect'));
            $request->validateResolved();
        }

        return $request;
    }
}
