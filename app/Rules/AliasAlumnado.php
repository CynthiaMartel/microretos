<?php

namespace App\Rules;

use App\Support\AliasGenerator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Solo alias "NombreDePila Animal Color" (ver AliasGenerator): una sola palabra de nombre y
 * un animal + color de las listas, para que no quepan apellidos ni otros datos.
 */
class AliasAlumnado implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!AliasGenerator::esValido(is_string($value) ? $value : null)) {
            $fail('El alias tiene que ser el nombre de pila (una sola palabra, sin apellidos) seguido del animal y el color generados.');
        }
    }
}
